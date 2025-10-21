#MUST REMEMBER:
# pip install pydub (and install ffmpeg)
# pip install python-dateutil
# sudo apt update
# sudo apt install ffmpeg

import os
import json
import re
import time
import asyncio
from telethon import TelegramClient
from FastTelethon import download_file  # your fast downloader
from pydub import AudioSegment
from pydub.utils import mediainfo
import sys
from dateutil import parser

sys.stdout.reconfigure(encoding='utf-8')

api_id = int(os.getenv("TELEGRAM_API_ID"))
api_hash = os.getenv("TELEGRAM_API_HASH")

client = TelegramClient('anon', api_id, api_hash)

STATE_FILE = "last_id.json"
DOWNLOADS_DIR = "downloads"
SPLIT_DIR = "splits"

def get_last_id():
    if os.path.exists(STATE_FILE):
        return json.load(open(STATE_FILE))["last_id"]
    return 0

def save_last_id(last_id):
    json.dump({"last_id": last_id}, open(STATE_FILE, "w"))

# optional timer for progress
class Timer:
    def __init__(self, time_between=1.5):
        self.start_time = time.time()
        self.time_between = time_between
    def can_send(self):
        if time.time() > (self.start_time + self.time_between):
            self.start_time = time.time()
            return True
        return False

timer = Timer()

async def progress_bar(current, total, fname):
    if timer.can_send():
        print(f"{fname}: {current*100/total:.1f}%")

# check if message text looks like Q&A session
def is_qna_message(msg_text: str) -> bool:
    if not msg_text:
        return False
    # remove Telegram link if present
    cleaned = re.sub(r"https?://t\.me/\S+", "", msg_text, flags=re.IGNORECASE)
    # normalize spacing
    cleaned = re.sub(r"\s+", " ", cleaned).strip()
    return "livestream counselling q&a timestamps" in cleaned.lower()

# extract date string (e.g., "January 10th, 2025") and convert to yyyy-dd-mm
def extract_date(text: str) -> str:
    # remove links and compress spacing
    text = re.sub(r"https?://t\.me/\S+", "", text)
    text = text.replace("\n", " ").strip()

    # match e.g. "January 10th, 2025" or "May 9th, 2025:"
    m = re.search(r"(January|February|March|April|May|June|July|August|September|October|November|December)\s+(\d{1,2})(?:st|nd|rd|th)?,\s*(\d{4})", text)
    if not m:
        return None
    month, day, year = m.groups()
    months = {
        "January": "01", "February": "02", "March": "03", "April": "04", "May": "05",
        "June": "06", "July": "07", "August": "08", "September": "09",
        "October": "10", "November": "11", "December": "12"
    }
    return f"{year}-{day.zfill(2)}-{months[month]}"

# parse "0:00 - Question..." lines
def parse_timestamps(text: str):
    lines = text.splitlines()
    pattern = re.compile(r"^(\d{1,2}:\d{2}(?::\d{2})?)\s*[-–]?\s*(.+)$")
    items = []
    for line in lines:
        m = pattern.match(line.strip())
        if m:
            time_str, question = m.groups()
            # convert to seconds
            parts = [int(x) for x in time_str.split(":")]
            if len(parts) == 2:
                secs = parts[0]*60 + parts[1]
            else:
                secs = parts[0]*3600 + parts[1]*60 + parts[2]
            items.append((secs, question.strip()))
    return items

def sanitize_filename(name: str) -> str:
    return re.sub(r'[<>:"/\\|?*\x00-\x1F]', '_', name)[:180]

async def main():
    channel = await client.get_entity('https://t.me/devtestingchannel')
    os.makedirs(DOWNLOADS_DIR, exist_ok=True)
    os.makedirs(SPLIT_DIR, exist_ok=True)

    last_id = get_last_id()
    print(f"Last processed id: {last_id}")

    async for message in client.iter_messages(channel, min_id=last_id):
        if message.audio and message.audio.mime_type == 'audio/mpeg' and is_qna_message(message.message):
            # parse timestamps/questions from message text
            ts_items = parse_timestamps(message.message)
            if not ts_items:
                print("No timestamps found, skipping")
                continue

            # extract and format date
            date_str = extract_date(message.message) or "unknown-date"

            # rename downloaded file based on date
            fname = f"{date_str}.mp3"
            path = os.path.join(DOWNLOADS_DIR, fname)

            print(f"\nDownloading {fname}...")
            with open(path, "wb") as out:
                await download_file(
                    client,
                    message.document,
                    out,
                    progress_callback=lambda c,t,fname=fname: progress_bar(c,t,fname)
                )
            print(f"Finished: {path}")

            # split audio
            audio = AudioSegment.from_file(path)
            duration_ms = len(audio)

            for idx, (start_sec, question) in enumerate(ts_items):
                start_ms = start_sec * 1000
                end_ms = duration_ms
                if idx + 1 < len(ts_items):
                    end_ms = ts_items[idx+1][0] * 1000
                clip = audio[start_ms:end_ms]
                q_name = f"{date_str} - {sanitize_filename(question)}.mp3"
                out_path = os.path.join(SPLIT_DIR, q_name)
                clip.export(out_path, format="mp3")
                print(f"Saved clip: {out_path}")

        # update last_id after each message processed
        last_id = max(last_id, message.id)

    save_last_id(last_id)
    print(f"\nUpdated last_id to {last_id}")

with client:
    client.loop.run_until_complete(main())