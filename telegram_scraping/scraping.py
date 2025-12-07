import os
import json
import re
import time
import asyncio
import boto3
from telethon import TelegramClient
from FastTelethon import download_file
from pydub import AudioSegment
from dateutil import parser
import sys
import random

# UTF-8 stdout for Docker logs
sys.stdout.reconfigure(encoding='utf-8')

# === SETTINGS ===
ENABLE_TRANSCRIBE = True  # 🔄 Toggle this ON/OFF if needed
MIN_CLIP_MS = 5000        # ⏱️ Minimum clip length for transcription (5 seconds)

# === ENVIRONMENT VARIABLES ===
api_id = int(os.getenv("TELEGRAM_API_ID"))
api_hash = os.getenv("TELEGRAM_API_HASH")
aws_region = os.getenv("AWS_DEFAULT_REGION") or "us-east-1"
s3_bucket = os.getenv("S3_BUCKET", "telegram-qna-splits")

client = TelegramClient('anon', api_id, api_hash)
s3 = boto3.client('s3', region_name=aws_region)
transcribe = boto3.client('transcribe', region_name=aws_region)

STATE_FILE = "last_id.json"
DOWNLOADS_DIR = "downloads"
SPLIT_DIR = "splits"


# === STATE HANDLERS ===
def get_last_id():
    if os.path.exists(STATE_FILE):
        return json.load(open(STATE_FILE)).get("last_id", 0)
    return 0


def save_last_id(last_id):
    json.dump({"last_id": last_id}, open(STATE_FILE, "w"))


def clean_local_folders():
    for folder in [DOWNLOADS_DIR, SPLIT_DIR]:
        for f in os.listdir(folder):
            try:
                os.remove(os.path.join(folder, f))
            except Exception as e:
                print(f"⚠️ Could not delete {f}: {e}")


# === HELPERS ===
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
        print(f"{fname}: {current * 100 / total:.1f}%")


def is_qna_message(msg_text: str) -> bool:
    if not msg_text:
        return False
    cleaned = re.sub(r"https?://t\.me/\S+", "", msg_text, flags=re.IGNORECASE)
    cleaned = re.sub(r"\s+", " ", cleaned).strip()
    return "livestream counselling q&a timestamps" in cleaned.lower()


def extract_date(text: str) -> str:
    text = re.sub(r"https?://t\.me/\S+", "", text)
    text = text.replace("\n", " ").strip()
    m = re.search(
        r"(January|February|March|April|May|June|July|August|September|October|November|December)\s+(\d{1,2})(?:st|nd|rd|th)?,\s*(\d{4})",
        text,
    )
    if not m:
        return None
    month, day, year = m.groups()
    months = {
        "January": "01", "February": "02", "March": "03", "April": "04", "May": "05",
        "June": "06", "July": "07", "August": "08", "September": "09",
        "October": "10", "November": "11", "December": "12"
    }
    return f"{year}-{day.zfill(2)}-{months[month]}"


def parse_timestamps(text: str):
    lines = text.splitlines()
    pattern = re.compile(r"^(\d{1,2}:\d{2}(?::\d{2})?)\s*[-–]?\s*(.+)$")
    items = []
    for line in lines:
        m = pattern.match(line.strip())
        if m:
            time_str, question = m.groups()
            parts = [int(x) for x in time_str.split(":")]
            if len(parts) == 2:
                secs = parts[0] * 60 + parts[1]
            else:
                secs = parts[0] * 3600 + parts[1] * 60 + parts[2]
            items.append((secs, question.strip()))
    return items


def sanitize_filename(name: str) -> str:
    return re.sub(r'[<>:"/\\|?*\x00-\x1F]', "_", name)[:180]


def upload_to_s3(file_path, s3_key):
    try:
        s3.upload_file(file_path, s3_bucket, s3_key)
        print(f"✅ Uploaded to S3: s3://{s3_bucket}/{s3_key}")
    except Exception as e:
        print(f"❌ Failed to upload {file_path}: {e}")


def start_transcribe_job(s3_uri, job_name):
    try:
        transcribe.start_transcription_job(
            TranscriptionJobName=job_name,
            Media={"MediaFileUri": s3_uri},
            MediaFormat="mp3",
            OutputBucketName=s3_bucket,
            OutputKey=f"transcripts/{job_name}.json",
            IdentifyMultipleLanguages=True,
            LanguageOptions=["en-US", "ar-SA"],
        )
        print(f"🎧 Started Transcribe job: {job_name}")
    except Exception as e:
        print(f"⚠️ Could not start transcription job for {s3_uri}: {e}")


# === MAIN ===
async def main():
    channel = await client.get_entity(os.getenv("TELEGRAM_CHANNEL_URL", "https://t.me/devtestingchannel"))
    os.makedirs(DOWNLOADS_DIR, exist_ok=True)
    os.makedirs(SPLIT_DIR, exist_ok=True)

    last_id = get_last_id()
    print(f"📜 Last processed id: {last_id}")

    async for message in client.iter_messages(channel, min_id=last_id):
        if message.audio and message.audio.mime_type == "audio/mpeg" and is_qna_message(message.message):
            ts_items = parse_timestamps(message.message)
            if not ts_items:
                print("⚠️ No timestamps found, skipping...")
                continue

            date_str = extract_date(message.message) or "unknown-date"
            fname = f"{date_str}.mp3"
            path = os.path.join(DOWNLOADS_DIR, fname)

            print(f"\n⬇️ Downloading {fname}...")
            with open(path, "wb") as out:
                await download_file(
                    client,
                    message.document,
                    out,
                    progress_callback=lambda c, t, fname=fname: progress_bar(c, t, fname),
                )
            print(f"✅ Finished download: {path}")

            # Split audio cleanly
            audio = AudioSegment.from_file(path)
            duration_ms = len(audio)
            print(f"🎧 Audio length: {duration_ms / 1000:.1f} seconds")

            for idx, (start_sec, question) in enumerate(ts_items):
                start_ms = start_sec * 1000
                end_ms = ts_items[idx + 1][0] * 1000 if idx + 1 < len(ts_items) else duration_ms

                # Skip invalid or tiny clips
                if end_ms <= start_ms or (end_ms - start_ms) < MIN_CLIP_MS:
                    print(f"⚠️ Skipping too-short or invalid clip ({end_ms - start_ms} ms)")
                    continue

                clip = audio[start_ms:end_ms]
                q_name = f"{date_str} - {sanitize_filename(question)}.mp3"
                out_path = os.path.join(SPLIT_DIR, q_name)
                clip.export(out_path, format="mp3")
                print(f"🎧 Saved split: {out_path}")

                # Upload to S3
                s3_key = f"initial-splits/{q_name}"
                upload_to_s3(out_path, s3_key)

                # Start Transcribe if enabled
                if ENABLE_TRANSCRIBE:
                    s3_uri = f"s3://{s3_bucket}/{s3_key}"
                    base_name = re.sub(r"[^a-zA-Z0-9_-]", "_", q_name.split(".mp3")[0])
                    suffix = f"-{random.randint(100000, 999999)}"
                    job_name = base_name + suffix
                    start_transcribe_job(s3_uri, job_name)

            # Cleanup
            time.sleep(1)
            clean_local_folders()
            print("🧹 Cleaned up local folders for next session.")

        last_id = max(last_id, message.id)
        save_last_id(last_id)

    print(f"\n✅ Updated last_id to {last_id}")


with client:
    client.loop.run_until_complete(main())
