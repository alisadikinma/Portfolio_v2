"""Fetch trending tech/AI topics from Google Trends."""

from pytrends.request import TrendReq

AI_KEYWORDS = [
    "artificial intelligence", "machine learning", "LLM", "GPT",
    "RAG", "AI agents", "prompt engineering", "computer vision",
    "NLP", "generative AI", "Claude", "ChatGPT", "Gemini",
    "AI automation", "no-code AI", "AI tools"
]


def get_trending_topics(max_results=5):
    """Fetch trending topics related to AI/tech."""
    pytrends = TrendReq(hl="en-US", tz=360)

    trending = []
    try:
        # Get trending searches
        df = pytrends.trending_searches(pn="united_states")
        all_trends = df[0].tolist()

        # Filter for tech/AI related
        for topic in all_trends:
            topic_lower = topic.lower()
            if any(kw in topic_lower for kw in ["ai", "tech", "code", "data", "model", "api", "cloud", "machine", "robot"]):
                trending.append(topic)
                if len(trending) >= max_results:
                    break

        # If no trending found, use related queries for AI keywords
        if not trending:
            pytrends.build_payload(["artificial intelligence"], timeframe="now 7-d")
            related = pytrends.related_queries()
            if "artificial intelligence" in related and related["artificial intelligence"]["rising"] is not None:
                rising = related["artificial intelligence"]["rising"]
                trending = rising["query"].head(max_results).tolist()

    except Exception as e:
        print(f"Error fetching trends: {e}")
        # Fallback topics
        trending = [
            "AI agents in production",
            "RAG pipeline optimization",
            "Prompt engineering best practices"
        ]

    return trending


if __name__ == "__main__":
    topics = get_trending_topics()
    print("Trending AI/Tech Topics:")
    for t in topics:
        print(f"  - {t}")
