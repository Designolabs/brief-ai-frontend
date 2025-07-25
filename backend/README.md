# Innovopedia Backend API

## Overview
The Innovopedia backend is a FastAPI-based service that powers the Innovopedia AI Briefing platform and WordPress plugin. It provides editorial intelligence, news briefing endpoints, audio generation, feedback/question handling, and analytics for modern news and knowledge sites.

---

## Features

- **/get-briefing**: Returns the latest editorial briefing with lead story, highlights, and editorial context.
- **/get-briefing-advanced**: (If enabled) Returns structured JSON containing lead, main stories, up next, tags, and popular stories for the React SPA.
- **/get-briefing-audio**: Generates and returns audio for the lead story or articles using ElevenLabs with a fixed voice ID.
- **/admin-feedback**: Retrieves user feedback for editorial analytics and popular story calculation.
- **/admin-questions**: Retrieves user-submitted questions for engagement analytics.
- **/analyze-trend**: (Optional) Returns trending tags/topics based on editorial and user data.
- **/list-voices**: (Legacy) Lists available ElevenLabs voices (not used in SPA).
- **Security**: CORS, WordPress nonce validation, and API key checks for sensitive endpoints.

---

## Usage

- Deploy as a standalone FastAPI app (`app.py`).
- Configure environment variables in `.env` (WordPress API URL, ElevenLabs API key, etc).
- Integrates with the Innovopedia WordPress plugin for full editorial workflow and analytics.

---

## Example .env
```
WP_API_URL=https://example.com/wp-json/wp/v2/posts
ELEVENLABS_API_KEY=sk-xxxx
```

---

## Development
- All endpoints documented in `app.py`.
- See `briefing_schema.py` for JSON response structure and validation.
- Run with `uvicorn app:app --reload` for development.

---

## See Also
- [Innovopedia Briefing SPA Frontend](../wp-live-ai-brief/wp-live-ai-brief/README.md)
- [WordPress Plugin](../wp-live-ai-brief/innovopedia-briefing.php)
