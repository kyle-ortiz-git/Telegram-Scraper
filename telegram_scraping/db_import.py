import os
import json
import boto3
import pymysql

# Load environment variables
DB_HOST = os.getenv("DB_HOST")
DB_USER = os.getenv("DB_USER")
DB_PASS = os.getenv("DB_PASS")
DB_NAME = os.getenv("DB_NAME")
DB_PORT = int(os.getenv("DB_PORT", 3306))

S3_BUCKET = os.getenv("S3_BUCKET", "telegram-qna-splits")
S3_PREFIX = "transcripts/"   # Folder where transcript .json files live
LOCAL_IMPORT_FOLDER = "dbIMPORT"

os.makedirs(LOCAL_IMPORT_FOLDER, exist_ok=True)

def get_db_connection():
    return pymysql.connect(
        host=DB_HOST,
        user=DB_USER,
        password=DB_PASS,
        database=DB_NAME,
        port=DB_PORT,
        charset="utf8mb4",
        cursorclass=pymysql.cursors.DictCursor
    )

def clean_transcription_text(transcript_json):
    """
    Extracts only the readable transcription body text from AWS Transcribe JSON.
    """
    try:
        results = transcript_json.get("results", {})
        transcripts = results.get("transcripts", [])
        if transcripts and "transcript" in transcripts[0]:
            text = transcripts[0]["transcript"].strip()
            # Remove any redundant whitespace
            return " ".join(text.split())
    except Exception as e:
        print(f"[WARN] Could not parse transcription: {e}")
    return ""

def extract_title_and_date(filename):
    """
    Example filename:
    2022-29-11_-_Addressing_the_non-muslim_with__dear__in_emails-584040.json
    → Date: 2022-29-11
      Title: Addressing the non muslim with dear in emails
    """
    base = os.path.basename(filename).replace(".json", "")
    parts = base.split("_-_", 1)
    if len(parts) < 2:
        return "", ""
    date_part = parts[0]
    title_part = parts[1]

    # Remove numeric suffix (e.g., -584040)
    if "-" in title_part:
        title_part = "-".join(title_part.split("-")[:-1])

    # Replace underscores with spaces
    title_part = title_part.replace("_", " ").strip()
    return title_part, date_part

def main():
    print("Fetching list of transcript files from S3...")
    s3 = boto3.client("s3")

    response = s3.list_objects_v2(Bucket=S3_BUCKET, Prefix=S3_PREFIX)
    if "Contents" not in response:
        print("No transcript files found in S3.")
        return

    connection = get_db_connection()
    cursor = connection.cursor()

    for obj in response["Contents"]:
        key = obj["Key"]
        if not key.endswith(".json"):
            continue

        filename = os.path.basename(key)
        local_path = os.path.join(LOCAL_IMPORT_FOLDER, filename)

        print(f"Downloading {filename}...")
        s3.download_file(S3_BUCKET, key, local_path)

        with open(local_path, "r", encoding="utf-8") as f:
            data = json.load(f)

        transcription = clean_transcription_text(data)
        title, date = extract_title_and_date(filename)

        if not transcription.strip():
            print(f"[SKIP] Empty transcription for {filename}")
            continue

        sql = """
            INSERT INTO Question (Title, Date, Transcription)
            VALUES (%s, %s, %s)
        """
        cursor.execute(sql, (title, date, transcription))
        connection.commit()

        print(f"Inserted: {title} ({date})")

    cursor.close()
    connection.close()
    print("All files processed and inserted successfully.")

if __name__ == "__main__":
    main()
