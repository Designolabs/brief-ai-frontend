from typing import List, Optional
from pydantic import BaseModel

class Story(BaseModel):
    title: str
    summary: str
    url: str
    image_url: Optional[str] = None
    short_headline: str
    is_highlighted: bool = False
    is_lead: bool = False
    why_it_matters: Optional[str] = None

class AdvancedBriefingResponse(BaseModel):
    greeting: str
    intro: str
    stories: List[Story]
    more_stories: List[Story]
    topics: List[str]
