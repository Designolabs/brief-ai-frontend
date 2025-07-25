import os
import base64
import requests
from fastapi import FastAPI, HTTPException, Query

# --- Google Cloud TTS base64 key support for Coolify ---
key_base64 = os.getenv('GCLOUD_TTS_KEY_BASE64')
key_path = '/tmp/gcloud-tts-key.json'
if key_base64:
    with open(key_path, 'wb') as f:
        f.write(base64.b64decode(key_base64))
    os.environ['GOOGLE_APPLICATION_CREDENTIALS'] = key_path
# If not present, fallback to GOOGLE_APPLICATION_CREDENTIALS or fail later in TTS logic

from fastapi.middleware.cors import CORSMiddleware
from fastapi.responses import JSONResponse
from pydantic import BaseModel
from typing import Optional
from fastapi.responses import StreamingResponse
import csv
import io
import asyncio
import httpx
import redis.asyncio as aioredis
import ssl

# === ENVIRONMENT VARIABLES ===
OPENROUTER_API_KEY = os.getenv("OPENROUTER_API_KEY")
ELEVENLABS_API_KEY = os.getenv("ELEVENLABS_API_KEY")
WP_API_URL = os.getenv("WP_API_URL", "https://innovopedia.com/wp-json/wp/v2/posts?orderby=date&order=desc")
# Set OPENROUTER_API_KEY in your environment for all LLM features.

# === FASTAPI APP ===
from fastapi.staticfiles import StaticFiles

app = FastAPI()

audio_dir = os.path.join(os.path.dirname(__file__), 'audio')
os.makedirs(audio_dir, exist_ok=True)
app.mount("/audio", StaticFiles(directory=audio_dir), name="audio")

# Allow only Innovopedia.com frontend
app.add_middleware(
    CORSMiddleware,
    allow_origins=[
        "https://innovopedia.com",
        "https://www.innovopedia.com",
        "http://localhost:3000",
        "http://innovopedia.local"
        # For local development
    ],
    allow_credentials=True,
    allow_methods=["GET", "POST", "PUT", "DELETE", "OPTIONS"],
    allow_headers=["*"],
    expose_headers=["*"]
)

# === ELEVENLABS: List Voices Endpoint ===
@app.get("/list-voices")
def list_voices():
    import httpx
    from elevenlabs.core.client_wrapper import SyncClientWrapper
    from elevenlabs.environment import ElevenLabsEnvironment
    from elevenlabs.api.voice import VoiceClient
    import logging
    if not ELEVENLABS_API_KEY:
        logging.error("ELEVENLABS_API_KEY not set!")
        raise HTTPException(status_code=500, detail="Audio API key not configured.")
    try:
        environment = ElevenLabsEnvironment(
            base="https://api.elevenlabs.io",
            wss="wss://api.elevenlabs.io/v1"
        )
        httpx_client = httpx.Client()
        client_wrapper = SyncClientWrapper(
            api_key=ELEVENLABS_API_KEY,
            environment=environment,
            httpx_client=httpx_client
        )
        voice_client = VoiceClient(client_wrapper=client_wrapper)
        voices = voice_client.get_all()
        result = [{"name": v.name, "voice_id": v.voice_id} for v in voices.voices]
        return JSONResponse(content=result)
    except Exception as e:
        logging.exception("Failed to list voices")
        raise HTTPException(status_code=500, detail=f"Failed to list voices: {e}")

# === ADMIN ENDPOINTS ===
import sqlite3
from fastapi.responses import JSONResponse
from datetime import datetime, timedelta
import os

DB_PATH = os.path.join(os.path.dirname(__file__), 'stats.db')

# Ensure DB and tables exist
conn = sqlite3.connect(DB_PATH)
c = conn.cursor()
c.execute('''CREATE TABLE IF NOT EXISTS stats (
    id INTEGER PRIMARY KEY,
    briefs_generated INTEGER DEFAULT 0,
    feedback_received INTEGER DEFAULT 0,
    qa_questions INTEGER DEFAULT 0,
    audio_generated INTEGER DEFAULT 0,
    last_ingest TEXT,
    ingest_errors INTEGER DEFAULT 0,
    created_at TEXT
)''')
c.execute('''CREATE TABLE IF NOT EXISTS feedback (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    feedback TEXT NOT NULL,
    rating INTEGER,
    user_id TEXT,
    context TEXT,
    submitted_at TEXT DEFAULT CURRENT_TIMESTAMP
)''')
c.execute('''CREATE TABLE IF NOT EXISTS questions (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    question TEXT NOT NULL,
    user_id TEXT,
    context TEXT,
    submitted_at TEXT DEFAULT CURRENT_TIMESTAMP
)''')
# Ensure single row exists in stats
c.execute('SELECT COUNT(*) FROM stats')
if c.fetchone()[0] == 0:
    c.execute('INSERT INTO stats (created_at) VALUES (?)', (datetime.utcnow().isoformat(),))
    conn.commit()
conn.close()

def get_stats():
    conn = sqlite3.connect(DB_PATH)
    c = conn.cursor()
    c.execute('SELECT * FROM stats LIMIT 1')
    row = c.fetchone()
    conn.close()
    if not row:
        return {}
    keys = ['id','briefs_generated','feedback_received','qa_questions','audio_generated','last_ingest','ingest_errors','created_at']
    return dict(zip(keys, row))

def update_stat(field, increment=1, last_ingest=None):
    conn = sqlite3.connect(DB_PATH)
    c = conn.cursor()
    if last_ingest:
        c.execute(f'UPDATE stats SET {field} = {field} + ?, last_ingest = ? WHERE id = 1', (increment, last_ingest))
    else:
        c.execute(f'UPDATE stats SET {field} = {field} + ? WHERE id = 1', (increment,))
    conn.commit()
    conn.close()

def update_audio_stat():
    conn = sqlite3.connect(DB_PATH)
    c = conn.cursor()
    c.execute('UPDATE stats SET audio_generated = audio_generated + 1 WHERE id = 1')
    conn.commit()
    conn.close()

@app.get("/admin-stats")
def admin_stats():
    stats = get_stats()
    # Uptime calculation
    if stats.get('created_at'):
        created = datetime.fromisoformat(stats['created_at'])
        uptime = datetime.utcnow() - created
        days = uptime.days
        hours = uptime.seconds // 3600
        stats['uptime'] = f"{days}d {hours}h"
    else:
        stats['uptime'] = "N/A"
    return JSONResponse({
        "briefs_generated": stats.get('briefs_generated', 0),
        "feedback_received": stats.get('feedback_received', 0),
        "qa_questions": stats.get('qa_questions', 0),
        "audio_generated": stats.get('audio_generated', 0),
        "last_ingest": stats.get('last_ingest', None),
        "ingest_errors": stats.get('ingest_errors', 0),
        "uptime": stats.get('uptime', 'N/A')
    })

@app.get("/admin-status")
def admin_status():
    # Check DB health
    try:
        stats = get_stats()
        return JSONResponse({"ok": True, "message": "All systems operational"})
    except Exception as e:
        return JSONResponse({"ok": False, "message": str(e)})

class BriefingResponse(BaseModel):
    briefingText: str
    audioUrl: Optional[str] = None

# === UTILITY: Fetch latest posts ===
# Note: By default, fetches all posts, not just a specific category
# If category is provided, fetch only posts from that category

def fetch_latest_posts():
    # Fetch all posts from the site (all pages)
    try:
        posts = []
        page = 1
        while True:
            url = f"{WP_API_URL}&per_page=100&page={page}"
            resp = requests.get(url, timeout=15)
            if resp.status_code == 400 and 'rest_post_invalid_page_number' in resp.text:
                break  # No more pages
            resp.raise_for_status()
            batch = resp.json()
            if not batch:
                break
            posts.extend(batch)
            if len(batch) < 100:
                break  # Last page
            page += 1
        return [
            {
                "title": p.get("title", {}).get("rendered", ""),
                "content": p.get("content", {}).get("rendered", ""),
                "url": p.get("link", ""),
                "image_url": (p.get("_embedded", {}).get("wp:featuredmedia", [{}])[0].get("source_url") if p.get("_embedded", {}).get("wp:featuredmedia") else None),
                "tags": p.get("tags", [])
            }
            for p in posts
        ]
    except Exception as e:
        raise HTTPException(status_code=502, detail=f"Failed to fetch posts: {e}")

# === UTILITY: Summarize with DeepSeek ===
from datetime import datetime, timedelta

def get_greeting(utc_offset=0):
    hour = (datetime.utcnow() + timedelta(hours=utc_offset)).hour
    if hour < 12:
        return "Good morning"
    elif hour < 18:
        return "Good afternoon"
    else:
        return "Good evening"

def summarize_with_deepseek(posts, utc_offset=0):
    import os
    import requests
    import json
    OPENROUTER_API_KEY = os.getenv("OPENROUTER_API_KEY")
    if not OPENROUTER_API_KEY:
        raise Exception("OPENROUTER_API_KEY not set in environment.")
    greeting = get_greeting(utc_offset)
    prompt = f"""
You are an expert news summarizer for Innovopedia.com. Summarize the following articles as a friendly, concise daily briefing. Always use this HTML structure exactly:

<p><strong>{greeting}, here’s your Innovopedia Briefing:</strong></p>
<ul>
<li><a href="[URL1]">[Title 1]</a>: [Summary 1]</li>
<li><a href="[URL2]">[Title 2]</a>: [Summary 2]</li>
<li><a href="[URL3]">[Title 3]</a>: [Summary 3]</li>
<li><a href="[URL4]">[Title 4]</a>: [Summary 4]</li>
<li><a href="[URL5]">[Title 5]</a>: [Summary 5]</li>
</ul>

Articles:
"""
    for p in posts[:5]:
        prompt += f"\nTitle: {p['title']}\nURL: {p['url']}\nContent: {p['content']}\n"
    try:
        response = requests.post(
            url="https://openrouter.ai/api/v1/chat/completions",
            headers={
                "Authorization": f"Bearer {OPENROUTER_API_KEY}",
                "Content-Type": "application/json",
                "HTTP-Referer": "https://innovopedia.com",
                "X-Title": "Innovopedia AI Briefing"
            },
            data=json.dumps({
                "model": "deepseek/deepseek-r1:free",
                "messages": [
                    {"role": "user", "content": prompt}
                ]
            })
        )
        response.raise_for_status()
        result = response.json()
        return result['choices'][0]['message']['content'].strip()
    except Exception as e:
        import logging
        logging.error(f"DeepSeek summarization failed: {e}")
        raise Exception(f"DeepSeek summarization failed: {e}")

# === UTILITY: Generate audio with ElevenLabs ===
def generate_audio(text, voice_id=None):
    import os
    import logging
    import httpx
    audio_dir = os.path.join(os.path.dirname(__file__), 'audio')
    os.makedirs(audio_dir, exist_ok=True)

    # Try ElevenLabs first
    try:
        from elevenlabs.core.client_wrapper import SyncClientWrapper
        from elevenlabs.text_to_speech.client import TextToSpeechClient
        from elevenlabs.environment import ElevenLabsEnvironment
        if not ELEVENLABS_API_KEY:
            raise Exception("ELEVENLABS_API_KEY not set!")
        if not voice_id:
            voice_id = "aMSt68OGf4xUZAnLpTU8"
        environment = ElevenLabsEnvironment(
            base="https://api.elevenlabs.io",
            wss="wss://api.elevenlabs.io/v1"
        )
        httpx_client = httpx.Client()
        client_wrapper = SyncClientWrapper(
            api_key=ELEVENLABS_API_KEY,
            environment=environment,
            httpx_client=httpx_client
        )
        tts_client = TextToSpeechClient(client_wrapper=client_wrapper)
        audio_iter = tts_client.convert(
            voice_id=voice_id,
            text=text,
            model_id="eleven_multilingual_v2",
            output_format="mp3_44100_128"
        )
        audio_bytes = b"".join(audio_iter)
        audio_filename = f"briefing_{os.urandom(6).hex()}.mp3"
        audio_path = os.path.join(audio_dir, audio_filename)
        with open(audio_path, "wb") as f:
            f.write(audio_bytes)
        return audio_path
    except Exception as e:
        logging.warning(f"ElevenLabs audio failed, falling back to Google TTS: {e}")
        # Fallback to Google Cloud TTS
        try:
            from google.cloud import texttospeech
            google_voice = os.getenv("GOOGLE_TTS_VOICE", "en-US-Chirp-HD-F")
            google_lang = os.getenv("GOOGLE_TTS_LANG", "en-US")
            client = texttospeech.TextToSpeechClient()
            synthesis_input = texttospeech.SynthesisInput(text=text)
            voice = texttospeech.VoiceSelectionParams(
                language_code=google_lang,
                name=google_voice
            )
            audio_config = texttospeech.AudioConfig(
                audio_encoding=texttospeech.AudioEncoding.MP3
            )
            response = client.synthesize_speech(
                input=synthesis_input,
                voice=voice,
                audio_config=audio_config
            )
            audio_filename = f"briefing_{os.urandom(6).hex()}_gcloud.mp3"
            audio_path = os.path.join(audio_dir, audio_filename)
            with open(audio_path, "wb") as out:
                out.write(response.audio_content)
            # --- Upload to Google Cloud Storage if configured ---
            import logging
            gcs_bucket = os.getenv("GCS_BUCKET")
            logging.warning(f"[AUDIO] GCS_BUCKET env: {gcs_bucket}")
            logging.warning(f"[AUDIO] Audio file to upload: {audio_path}")
            if gcs_bucket:
                try:
                    from google.cloud import storage
                    client = storage.Client()
                    bucket = client.bucket(gcs_bucket)
                    logging.warning(f"[AUDIO] Attempting to upload {audio_filename} to bucket {gcs_bucket}")
                    blob = bucket.blob(audio_filename)
                    blob.upload_from_filename(audio_path)
                    # Cannot use make_public() with uniform bucket-level access
                    gcs_url = f"https://storage.googleapis.com/{gcs_bucket}/{audio_filename}"
                    logging.warning(f"[AUDIO] Uploaded to GCS: {gcs_url}")
                    # Warn if bucket is not public
                    logging.warning("[AUDIO] Note: Bucket is not public. The audio URL will not be accessible unless public access is enabled or signed URLs are used.")
                    # Optionally remove local file after upload
                    os.remove(audio_path)
                    return gcs_url
                except Exception as upload_err:
                    logging.error(f"[AUDIO] Failed to upload audio to GCS: {upload_err}")
                    # Fallback to local file serving if upload fails
            else:
                logging.warning("[AUDIO] GCS_BUCKET not set, skipping upload.")
            return f"/audio/{audio_filename}"
        except Exception as ge:
            logging.exception("Both ElevenLabs and Google TTS failed")
            raise Exception(f"Audio generation failed: {ge}")

@app.get("/get-briefing", response_model=BriefingResponse)
def get_briefing(utc_offset: int = Query(0, description="User's UTC offset in hours (e.g., +2, -5)")):
    import logging
    posts = fetch_latest_posts()
    try:
        briefing_html = summarize_with_deepseek(posts, utc_offset)
    except Exception as e:
        logging.error(f"LLM summarization failed: {e}")
        raise HTTPException(status_code=500, detail=f"LLM summarization failed: {e}")
    update_stat('briefs_generated', increment=1)
    # Generate audio
    audio_url = None
    try:
        import re
        plain_text = re.sub('<[^<]+?>', '', briefing_html)
        audio_path = generate_audio(plain_text)
        audio_url = f"/audio/{os.path.basename(audio_path)}"
        update_audio_stat()
    except Exception as e:
        logging.warning(f"Audio generation failed: {e}")
        audio_url = None
    return {"briefingText": briefing_html, "audioUrl": audio_url}

# === ANALYZE TREND ENDPOINT ===
from fastapi import Body, Request
from pydantic import BaseModel, Field
from fastapi.responses import JSONResponse
from fastapi.exception_handlers import RequestValidationError
from fastapi.exceptions import RequestValidationError
from fastapi import status
import logging

class AnalyzeTrendRequest(BaseModel):
    trend: str = Field(..., min_length=2)
    briefingText: str = Field(..., min_length=2)

@app.post("/analyze-trend")
def analyze_trend(request: AnalyzeTrendRequest = Body(...)):
    """
    Analyze a trend in the context of the current briefing using DeepSeek R1 via OpenRouter.
    """
    if not request.trend.strip() or not request.briefingText.strip():
        raise HTTPException(status_code=400, detail="Trend and briefingText are required.")
    import os
    import requests
    import json
    OPENROUTER_API_KEY = os.getenv("OPENROUTER_API_KEY")
    if not OPENROUTER_API_KEY:
        raise HTTPException(status_code=500, detail="OPENROUTER_API_KEY not set in environment.")
    prompt = f"""
You are an expert tech and news analyst for Innovopedia.com. Given the following daily briefing and a trend or topic, analyze the trend in the context of the briefing. Be concise, insightful, and actionable. If the trend is not covered, mention this politely.

Briefing:
{request.briefingText}

Trend/Topic:
{request.trend}

Analysis:
"""
    try:
        response = requests.post(
            url="https://openrouter.ai/api/v1/chat/completions",
            headers={
                "Authorization": f"Bearer {OPENROUTER_API_KEY}",
                "Content-Type": "application/json",
                "HTTP-Referer": "https://innovopedia.com",
                "X-Title": "Innovopedia AI Briefing"
            },
            data=json.dumps({
                "model": "deepseek/deepseek-r1:free",
                "messages": [
                    {"role": "user", "content": prompt}
                ]
            })
        )
        response.raise_for_status()
        result = response.json()
        return {"result": result['choices'][0]['message']['content'].strip()}
    except Exception as e:
        import logging
        logging.exception("Trend analysis failed")
        raise HTTPException(status_code=500, detail=f"Trend analysis failed: {e}")


@app.get("/")
def root():
    return {
        "message": "Welcome to the Innovopedia API!",
        "endpoints": [
            "/get-briefing",
            "/get-briefing-text",
            "/get-briefing-advanced",
            "/analyze-trend",
            "/get-briefing-audio",
            "/list-voices",
            "/submit-feedback",
            "/submit-question",
            "/admin-stats",
            "/admin-feedback",
            "/admin-questions"
        ]
    }

@app.exception_handler(404)
async def custom_404_handler(request: Request, exc):
    return JSONResponse(status_code=404, content={"detail": "Not Found. Check your endpoint path."})

# === PARALLEL BRIEFING ENDPOINTS ===
from fastapi import Query
from fastapi import Request, Query
from typing import List, Optional
import json
from llm_prompts import build_advanced_briefing_prompt
from briefing_schema import AdvancedBriefingResponse

# === SIMPLE IN-MEMORY CACHE FOR BRIEFINGS ===
import time
import hashlib
briefing_cache = {}

REDIS_URL = "redis://default:Ui7yLRLwiSXRuFX1lErrJVwPMzSY3wsqg4OW00kgH6jeBbhVamw1RDm4lgBnie90@vwscwgkg480g408kwocw800g:6379/0"

redis_client = aioredis.from_url(
    REDIS_URL,
    decode_responses=True
    # Do NOT include ssl or ssl_cert_reqs for redis://
)

from slowapi import Limiter, _rate_limit_exceeded_handler
from slowapi.util import get_remote_address
from fastapi import Request
from slowapi.errors import RateLimitExceeded

limiter = Limiter(
    key_func=get_remote_address,
    storage_uri="redis://default:Ui7yLRLwiSXRuFX1lErrJVwPMzSY3wsqg4OW00kgH6jeBbhVamw1RDm4lgBnie90@vwscwgkg480g408kwocw800g:6379/0"
)
app.state.limiter = limiter
app.add_exception_handler(RateLimitExceeded, _rate_limit_exceeded_handler)

@app.get("/get-briefing-advanced")
@limiter.limit("5/minute")
async def get_briefing_advanced(
    request: Request,
    utc_offset: int = Query(0, description="User's UTC offset in hours (e.g., +2, -5)"),
    num_stories: int = Query(5, ge=1, le=10, description="Number of stories to include"),
    focus_topic: Optional[str] = Query(None, description="Focus topic for briefing"),
    interests: Optional[List[str]] = Query(None, description="User interests (comma-separated)"),
    language: Optional[str] = Query("en", description="Language code for the briefing (e.g., en, fr, es)"),
    category: Optional[str] = Query(None, description="Category to filter posts by (if available)"),
    exclude_topics: Optional[List[str]] = Query(None, description="Topics or tags to exclude from the briefing")
):
    import logging
    import json
    posts = fetch_latest_posts()
    # Filter posts by category if provided
    if category:
        posts = [p for p in posts if category.lower() in [str(tag).lower() for tag in p.get('tags', [])]]
    # Exclude posts with any excluded topics/tags
    if exclude_topics:
        posts = [p for p in posts if not any(str(tag).lower() in [et.lower() for et in exclude_topics] for tag in p.get('tags', []))]
    greeting = get_greeting(utc_offset)
    intro = (
        "Today’s top stories and insights from Innovopedia."
        if language == "en" else ""
    )
    prompt = build_advanced_briefing_prompt(posts, greeting, intro, num_stories, focus_topic, interests, language=language, exclude_topics=exclude_topics)
    logging.info(f"Generated prompt length: {len(prompt)} characters")
    logging.info(f"Number of posts fetched: {len(posts)}")
    
    # --- Caching key based on params ---
    import hashlib
    cache_key = hashlib.sha256(
        f"{utc_offset}-{num_stories}-{focus_topic}-{interests}-{language}-{category}-{exclude_topics}".encode()
    ).hexdigest()
    try:
        # Try Redis cache first
        data = None
        if redis_client:
            cached = await redis_client.get(cache_key)
            if cached:
                return json.loads(cached)
        # LLM call (async)
        OPENROUTER_API_KEY = os.getenv("OPENROUTER_API_KEY")
        if not OPENROUTER_API_KEY:
            logging.error("OPENROUTER_API_KEY not set!")
            raise HTTPException(status_code=500, detail="LLM API key not configured")
        
        async with httpx.AsyncClient(timeout=20) as client:
            request_data = {
                "model": "deepseek/deepseek-r1:free",
                "messages": [
                    {"role": "user", "content": prompt}
                ]
            }
            logging.info(f"Making LLM API request to OpenRouter with model: {request_data['model']}")
            
            response = await client.post(
                url="https://openrouter.ai/api/v1/chat/completions",
                headers={
                    "Authorization": f"Bearer {OPENROUTER_API_KEY}",
                    "Content-Type": "application/json",
                    "HTTP-Referer": "https://innovopedia.com",
                    "X-Title": "Innovopedia AI Briefing"
                },
                data=json.dumps(request_data)
            )
            response.raise_for_status()
            result = response.json()
            
            # Log the full response for debugging
            logging.info(f"Full LLM API response: {result}")
            
            # Check if the LLM API returned an error
            if 'error' in result:
                error_msg = f"LLM API returned an error: {result['error']}"
                logging.error(error_msg)
                raise HTTPException(status_code=500, detail=f"LLM API error: {result['error'].get('message', 'Unknown error')}")
            
            # Check if choices exist in the response
            if 'choices' not in result or not result['choices']:
                error_msg = f"LLM API returned no choices. Full response: {result}"
                logging.error(error_msg)
                raise HTTPException(status_code=500, detail="LLM API returned an invalid response format")
            
            # Check if the first choice has the expected structure
            if 'message' not in result['choices'][0] or 'content' not in result['choices'][0]['message']:
                error_msg = f"LLM API response missing message/content. Choice: {result['choices'][0]}"
                logging.error(error_msg)
                raise HTTPException(status_code=500, detail="LLM API response format is invalid")
            
            llm_content = result['choices'][0]['message']['content'].strip()
            logging.info(f"Raw LLM output: {llm_content}")
            # Try to parse JSON from LLM output
            try:
                # Some LLMs may wrap JSON in code blocks
                if llm_content.startswith('```json'):
                    llm_content = llm_content.split('```json')[1].split('```')[0].strip()
                elif llm_content.startswith('```'):
                    llm_content = llm_content.split('```')[1].split('```')[0].strip()
                try:
                    briefing_obj = json.loads(llm_content)
                except Exception:
                    import re
                    match = re.search(r'({[\s\S]*})', llm_content)
                    if match:
                        briefing_obj = json.loads(match.group(1))
                    else:
                        raise
                validated = AdvancedBriefingResponse(**briefing_obj)
                # Save to Redis cache (3 min)
                if redis_client:
                    await redis_client.set(cache_key, json.dumps(validated.dict()), ex=180)
                return validated.dict()
            except Exception as parse_err:
                logging.error(f"Failed to parse or validate LLM JSON output: {parse_err}\nRaw output: {llm_content}")
                return {"error": "LLM output could not be parsed as valid briefing JSON.", "raw": llm_content}
    except httpx.TimeoutException:
        logging.error("LLM API timed out")
        raise HTTPException(status_code=504, detail="LLM API timed out")
    except Exception as e:
        logging.error(f"Advanced briefing LLM call failed: {e}")
        raise HTTPException(status_code=500, detail=f"Advanced briefing LLM call failed: {e}")

@app.get("/get-briefing-audio")
def get_briefing_audio(voice_id: str = Query(None)):
    import logging
    posts = fetch_latest_posts()
    try:
        # You may use a fast summary for audio, or the same as text
        briefing_html = summarize_with_deepseek(posts)
        import re
        plain_text = re.sub('<[^<]+?>', '', briefing_html)
        audio_path = generate_audio(plain_text, voice_id=voice_id)
        audio_url = f"/audio/{os.path.basename(audio_path)}"
        update_audio_stat()
        return {"audioUrl": audio_url}
    except Exception as e:
        logging.warning(f"Audio generation failed: {e}")
        raise HTTPException(status_code=500, detail=f"Audio generation failed: {e}")

from fastapi import Query

@app.get("/get-briefing-text")
def get_briefing_text(utc_offset: int = Query(0, description="User's UTC offset in hours (e.g., +2, -5)")):
    import logging
    posts = fetch_latest_posts()
    try:
        briefing_html = summarize_with_deepseek(posts, utc_offset)
        update_stat('briefs_generated', increment=1)
        return {"briefingText": briefing_html}
    except Exception as e:
        logging.error(f"LLM summarization failed: {e}")
        raise HTTPException(status_code=500, detail=f"LLM summarization failed: {e}")

# Admin endpoints for feedback/questions
@app.get("/admin-feedback")
def admin_feedback():
    conn = sqlite3.connect(DB_PATH)
    c = conn.cursor()
    c.execute('SELECT id, feedback, rating, user_id, context, created_at FROM feedback ORDER BY created_at DESC LIMIT 100')
    rows = c.fetchall()
    conn.close()
    feedback_list = [
        {"id": row[0], "feedback": row[1], "rating": row[2], "user_id": row[3], "context": row[4], "submitted_at": row[5]}
        for row in rows
    ]
    return {"feedback": feedback_list}

@app.get("/admin-questions")
def admin_questions():
    conn = sqlite3.connect(DB_PATH)
    c = conn.cursor()
    c.execute('SELECT id, question, user_id, context, created_at FROM questions ORDER BY created_at DESC LIMIT 100')
    rows = c.fetchall()
    conn.close()
    questions_list = [
        {"id": row[0], "question": row[1], "user_id": row[2], "context": row[3], "submitted_at": row[4]}
        for row in rows
    ]
    return {"questions": questions_list}


class FeedbackSubmission(BaseModel):
    feedback: str = Field(..., min_length=2)
    rating: int | None = Field(None, ge=1, le=5)
    user_id: str | None = None
    context: str | None = None

@app.post("/submit-feedback")
def submit_feedback(submission: FeedbackSubmission = Body(...)):
    if not submission.feedback.strip():
        raise HTTPException(status_code=400, detail="Feedback is required.")
    update_stat('feedback_received', increment=1)
    # Persist feedback
    conn = sqlite3.connect(DB_PATH)
    c = conn.cursor()
    c.execute('INSERT INTO feedback (feedback, rating, user_id, context) VALUES (?, ?, ?, ?)',
              (submission.feedback, submission.rating, submission.user_id, submission.context))
    conn.commit()
    conn.close()
    return {"success": True}

class QuestionSubmission(BaseModel):
    question: str = Field(..., min_length=2)
    user_id: str | None = None
    context: str | None = None

@app.post("/submit-question")
def submit_question(submission: QuestionSubmission = Body(...)):
    if not submission.question.strip():
        raise HTTPException(status_code=400, detail="Question is required.")
    update_stat('qa_questions', increment=1)
    # Persist question
    conn = sqlite3.connect(DB_PATH)
    c = conn.cursor()
    c.execute('INSERT INTO questions (question, user_id, context) VALUES (?, ?, ?)',
              (submission.question, submission.user_id, submission.context))
    conn.commit()
    conn.close()
    return {"success": True}

@app.get("/analytics/export")
def export_analytics():
    # Example: Export stats as CSV (customize as needed)
    stats = get_stats()
    output = io.StringIO()
    writer = csv.writer(output)
    writer.writerow(["id","briefs_generated","feedback_received","qa_questions","audio_generated","last_ingest","ingest_errors","created_at"])
    writer.writerow([
        stats.get('id', 1),
        stats.get('briefs_generated', 0),
        stats.get('feedback_received', 0),
        stats.get('qa_questions', 0),
        stats.get('audio_generated', 0),
        stats.get('last_ingest', ''),
        stats.get('ingest_errors', 0),
        stats.get('created_at', '')
    ])
    output.seek(0)
    return StreamingResponse(output, media_type="text/csv", headers={"Content-Disposition": "attachment; filename=analytics.csv"})

@app.get("/feedback/export")
def export_feedback():
    # Example: Export feedback as CSV (customize as needed)
    conn = sqlite3.connect(DB_PATH)
    c = conn.cursor()
    c.execute('SELECT id, feedback, rating, user_id, context, created_at FROM feedback ORDER BY created_at DESC')
    rows = c.fetchall()
    conn.close()
    output = io.StringIO()
    writer = csv.writer(output)
    writer.writerow(["id","feedback","rating","user_id","context","created_at"])
    for row in rows:
        writer.writerow(row)
    output.seek(0)
    return StreamingResponse(output, media_type="text/csv", headers={"Content-Disposition": "attachment; filename=feedback.csv"})
