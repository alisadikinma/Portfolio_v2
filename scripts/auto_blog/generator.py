"""Generate blog posts using OpenRouter API (OpenAI-compatible)."""

import os
import json
import requests


def generate_blog_post(topic):
    """Generate a complete blog post about the given topic via OpenRouter."""
    api_key = os.getenv("OPENROUTER_API_KEY")
    model = os.getenv("AI_MODEL", "google/gemini-2.5-flash-lite")

    prompt = f"""Write a comprehensive, SEO-optimized blog post about: {topic}

Requirements:
- Target audience: developers and tech professionals
- Length: 1200-1800 words
- Tone: authoritative, practical, slightly conversational
- Include practical examples and code snippets where relevant
- Structure with H2 and H3 headings

Return the response as valid JSON with these exact fields:
{{
  "title": "Engaging title (max 70 chars)",
  "content": "Full HTML content with headings, paragraphs, code blocks",
  "excerpt": "2-3 sentence summary (max 200 chars)",
  "meta_title": "SEO title (max 60 chars)",
  "meta_description": "SEO description (max 155 chars)",
  "tags": "comma-separated tags (max 5)"
}}

Important: Return ONLY the JSON object, no markdown formatting."""

    response = requests.post(
        "https://openrouter.ai/api/v1/chat/completions",
        headers={
            "Authorization": f"Bearer {api_key}",
            "Content-Type": "application/json",
            "HTTP-Referer": "https://alisadikinma.com",
            "X-Title": "Ali Sadikin Auto Blog",
        },
        json={
            "model": model,
            "max_tokens": 4096,
            "messages": [
                {"role": "user", "content": prompt}
            ],
        },
        timeout=60,
    )

    response.raise_for_status()
    data = response.json()
    response_text = data["choices"][0]["message"]["content"].strip()

    # Handle potential markdown wrapping
    if response_text.startswith("```"):
        response_text = response_text.split("```")[1]
        if response_text.startswith("json"):
            response_text = response_text[4:]

    return json.loads(response_text)


if __name__ == "__main__":
    from dotenv import load_dotenv
    load_dotenv()

    post = generate_blog_post("Building Production RAG Pipelines")
    print(f"Title: {post['title']}")
    print(f"Excerpt: {post['excerpt']}")
    print(f"Tags: {post['tags']}")
    print(f"Content length: {len(post['content'])} chars")
