# Advanced LLM Prompt for Innovopedia Briefing

def build_advanced_briefing_prompt(posts, greeting, intro, num_stories=5, focus_topic=None, interests=None, language="en", exclude_topics=None):
    """
    Construct a prompt for DeepSeek R1 to generate a highly editorial, structured JSON news briefing.
    """
    post_instructions = "".join([
        f"\nTitle: {p['title']}\nURL: {p['url']}\nContent: {p['content']}\nImage: {p.get('image_url', '')}\nTags: {', '.join(str(tag) for tag in p.get('tags', []))}" for p in posts[:num_stories]
    ])
    focus = f"\nUser is interested in: {', '.join(interests)}." if interests else ""
    focus += f"\nFocus on topic: {focus_topic}." if focus_topic else ""
    if exclude_topics:
        focus += f"\nAvoid stories about: {', '.join(exclude_topics)}."
    return f'''
You are a professional news editor for Innovopedia.com. Your job is to:
- Select the top {num_stories} stories by impact, novelty, and diversity. Avoid repetitive topics.
- Write in a professional, journalistic, and engaging style.
- Designate one story as the "lead story" (is_lead: true, is_highlighted: true) with a slightly longer summary and a "why_it_matters" field (1-2 lines explaining significance).
- For each story, include:
  - title (string)
  - summary (2-3 sentences, editorial, non-repetitive)
  - url (string)
  - image_url (string, if available)
  - short_headline (max 8 words, catchy)
  - is_highlighted (true for lead, else false)
  - is_lead (true for lead, else false)
  - why_it_matters (1-2 lines, if possible)
- Output a JSON object with keys: greeting, intro, stories (list), topics (3-5 trending topics from the articles)
- Use this JSON format:
{{
  "greeting": "{greeting}",
  "intro": "{intro}",
  "stories": [
    {{"title": "...", "summary": "...", "url": "...", "image_url": "...", "short_headline": "...", "is_highlighted": true, "is_lead": true, "why_it_matters": "..."}},
    ...
  ],
  "topics": ["...", "...", ...]
}}
IMPORTANT: Return ONLY valid JSON. Do NOT include markdown, code blocks, comments, or any extra text. Do NOT wrap the JSON in triple backticks or any other formatting. The output must be directly parsable as JSON.
{focus}

Articles:{post_instructions}
'''
