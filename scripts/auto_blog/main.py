"""
Auto Blog Service — Generates and publishes AI/tech blog posts.

Fetches trending topics from Google Trends, generates posts via Claude,
and publishes to the Portfolio API. Max 2 posts per day.
"""

import os
import time
from datetime import datetime

from dotenv import load_dotenv
import schedule

from trending import get_trending_topics
from generator import generate_blog_post
from publisher import check_duplicate, publish_post

load_dotenv()

MAX_POSTS_PER_DAY = int(os.getenv("MAX_POSTS_PER_DAY", "2"))
posts_today = 0
last_reset_date = datetime.now().date()


def reset_daily_counter():
    global posts_today, last_reset_date
    today = datetime.now().date()
    if today != last_reset_date:
        posts_today = 0
        last_reset_date = today


def run_auto_blog():
    global posts_today

    reset_daily_counter()

    if posts_today >= MAX_POSTS_PER_DAY:
        print(f"[{datetime.now()}] Daily limit reached ({MAX_POSTS_PER_DAY} posts). Skipping.")
        return

    print(f"\n[{datetime.now()}] Fetching trending topics...")
    topics = get_trending_topics(max_results=3)

    if not topics:
        print("No trending topics found. Skipping.")
        return

    for topic in topics:
        if posts_today >= MAX_POSTS_PER_DAY:
            break

        print(f"  Checking topic: {topic}")

        # Check for duplicates
        if check_duplicate(topic):
            print(f"    Skipping (duplicate): {topic}")
            continue

        try:
            print(f"  Generating post about: {topic}")
            post_data = generate_blog_post(topic)

            # Double-check duplicate with the generated title
            if check_duplicate(post_data["title"]):
                print(f"    Skipping (duplicate title): {post_data['title']}")
                continue

            if publish_post(post_data):
                posts_today += 1
                print(f"  Published ({posts_today}/{MAX_POSTS_PER_DAY} today)")
            else:
                print(f"  Failed to publish: {topic}")

        except Exception as e:
            print(f"  Error generating/publishing: {e}")

        # Rate limit between posts
        time.sleep(5)

    print(f"[{datetime.now()}] Run complete. {posts_today} posts today.")


if __name__ == "__main__":
    print("=" * 50)
    print("Auto Blog Service Started")
    print(f"Max posts per day: {MAX_POSTS_PER_DAY}")
    print(f"API URL: {os.getenv('PORTFOLIO_API_URL')}")
    print("=" * 50)

    # Run immediately on start
    run_auto_blog()

    # Schedule hourly runs
    schedule.every(1).hours.do(run_auto_blog)

    print("\nScheduler running (every 1 hour). Press Ctrl+C to stop.")
    while True:
        schedule.run_pending()
        time.sleep(60)
