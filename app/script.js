<?php
require 'vendor/autoload.php';

use Aws\S3\S3Client;
use Dotenv\Dotenv;

<<<<<<< HEAD
        const query = document.getElementById("query").value.trim();
=======
// Load environment variables from .env file
$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->load();
>>>>>>> parent of f3995ca (Uploading the index.html, process.php, script.js for the website displaying all the results & search)

// Database credentials
$db_host = $_ENV['DB_HOST'];
$db_user = $_ENV['DB_USER'];
$db_pass = $_ENV['DB_PASSWORD'];
$db_name = $_ENV['DB_NAME'];

// S3 / bucket config
$aws_region = $_ENV['AWS_DEFAULT_REGION'] ?? 'us-east-1';
$bucket     = $_ENV['TELEGRAM_QNA_BUCKET'] ?? 'telegram-qna-splits';
$s3_prefix  = 'initial-splits/'; // prefix inside the bucket

// Connect to DB
$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
if ($conn->connect_error) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode([
        'status'  => 'error',
        'message' => 'Database connection failed.'
    ]);
    exit;
}

header('Content-Type: application/json; charset=utf-8');

$action = $_POST['action'] ?? $_GET['action'] ?? '';

if ($action === 'search') {
    handle_search($conn);
} elseif ($action === 'get_audio') {
    handle_get_audio($conn, $bucket, $aws_region, $s3_prefix);
} else {
    echo json_encode([
        'status'  => 'error',
        'message' => 'Invalid action.'
    ]);
}

$conn->close();

/**
 * Handle search requests.
 * Parameters:
 *   - query (string)
 *   - mode  (title|both)
 */
function handle_search(mysqli $conn): void
{
    $query = trim($_POST['query'] ?? '');
    $mode  = $_POST['mode'] ?? 'title';

    if ($query === '') {
        echo json_encode([
            'status'  => 'ok',
            'results' => []
        ]);
        return;
    }

    $like = '%' . $query . '%';

    // TODO: change `questions` to your actual table name
    if ($mode === 'both') {
        $sql = "SELECT id, title, date, transcription
                FROM questions
                WHERE title LIKE ? OR transcription LIKE ?
                ORDER BY date DESC
                LIMIT 100";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('ss', $like, $like);
    } else {
        $sql = "SELECT id, title, date, transcription
                FROM questions
                WHERE title LIKE ?
                ORDER BY date DESC
                LIMIT 100";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('s', $like);
    }

    if (!$stmt->execute()) {
        echo json_encode([
            'status'  => 'error',
            'message' => 'Query failed.'
        ]);
        return;
    }

    $result  = $stmt->get_result();
    $results = [];

    while ($row = $result->fetch_assoc()) {
        $transcription = $row['transcription'] ?? '';
        $snippet       = mb_substr($transcription, 0, 180);
        if (mb_strlen($transcription) > 180) {
            $snippet .= '…';
        }

<<<<<<< HEAD
        fetch("process.php", {
            method: "POST",
            headers: { "Content-Type": "application/x-www-form-urlencoded" },
            body: `action=search&query=${encodeURIComponent(query)}`
        })
        .then(res => res.json())
        .then(data => {
            statusMessage.style.display = "none";
            resultsBody.innerHTML = "";

            if (data.status !== "ok" || !Array.isArray(data.results) || data.results.length === 0) {
                noResults.style.display = "block";
                return;
            }

            data.results.forEach(item => {
                const tr = document.createElement("tr");
                tr.innerHTML = `
                    <td>${item.id}</td>
                    <td>${item.title}</td>
                    <td>${item.date}</td>
                    <td class="text-right">
                        <button class="btn btn-sm btn-primary play-btn" data-id="${item.id}">
                            ▶ Play
                        </button>
                    </td>
                `;
                resultsBody.appendChild(tr);
            });

            attachPlayButtons();
        })
        .catch(err => {
            console.error(err);
            statusMessage.style.display = "block";
            statusMessage.innerText = "Error connecting to server.";
        });
    });


    function attachPlayButtons() {
        document.querySelectorAll(".play-btn").forEach(btn => {
            btn.addEventListener("click", function () {
                const id = this.dataset.id;

                fetch("process.php", {
                    method: "POST",
                    headers: { "Content-Type": "application/x-www-form-urlencoded" },
                    body: `action=get_audio&id=${encodeURIComponent(id)}`
                })
                .then(res => res.json())
                .then(data => {
                    if (data.status !== "ok") {
                        alert(data.message || "Audio not available.");
                        return;
                    }

                    document.getElementById("qaModalTitle").innerText = data.title;
                    document.getElementById("qaAudioSource").src = data.audio_url;

                    const audioTag = document.getElementById("qaAudio");
                    audioTag.load();
                    $("#qaModal").modal("show");
                })
                .catch(err => {
                    console.error(err);
                    alert("Failed to load audio.");
                });
            });
        });
=======
        $results[] = [
            'id'       => $row['id'],
            'title'    => $row['title'],
            'date'     => $row['date'],
            'snippet'  => $snippet
        ];
>>>>>>> parent of f3995ca (Uploading the index.html, process.php, script.js for the website displaying all the results & search)
    }

    $stmt->close();

    echo json_encode([
        'status'  => 'ok',
        'results' => $results
    ]);
}

/**
 * Handle get_audio requests.
 * Parameters:
 *   - id (int)
 *
 * Uses DB date + title and S3 listObjects to find the best-matching MP3
 * for that question. Returns a pre-signed URL because the bucket is private.
 */
function handle_get_audio(mysqli $conn, string $bucket, string $aws_region, string $s3_prefix): void
{
    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    if ($id <= 0) {
        echo json_encode([
            'status'  => 'error',
            'message' => 'Invalid ID.'
        ]);
        return;
    }

    // TODO: change `questions` to your actual table name
    $sql  = "SELECT id, title, date FROM questions WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $id);

    if (!$stmt->execute()) {
        echo json_encode([
            'status'  => 'error',
            'message' => 'Query failed.'
        ]);
        return;
    }

    $result = $stmt->get_result();
    $row    = $result->fetch_assoc();
    $stmt->close();

    if (!$row) {
        echo json_encode([
            'status'  => 'error',
            'message' => 'Question not found.'
        ]);
        return;
    }

    $title = $row['title'];
    $date  = $row['date'];

    // Build S3 client (credentials are taken from environment / IAM role)
    $s3 = new S3Client([
        'version' => 'latest',
        'region'  => $aws_region
    ]);

    // List all objects for that date; keys look like:
    // initial-splits/2022-29-11 - Some Title.mp3
    $prefix = $s3_prefix . $date . ' - ';

    try {
        $objects = $s3->listObjectsV2([
            'Bucket' => $bucket,
            'Prefix' => $prefix
        ]);
    } catch (Exception $e) {
        echo json_encode([
            'status'  => 'error',
            'message' => 'Could not list audio files for this date.'
        ]);
        return;
    }

    if (empty($objects['Contents'])) {
        echo json_encode([
            'status'  => 'error',
            'message' => 'No audio files found for this question.'
        ]);
        return;
    }

    $dbNormTitle = normalize_title($title);
    $bestKey     = null;
    $bestScore   = PHP_INT_MAX;

    foreach ($objects['Contents'] as $object) {
        $key = $object['Key'];

        // Get the part after "YYYY-XX-YY - " and before ".mp3"
        $basename   = basename($key, '.mp3');
        $titlePart  = preg_replace('/^\d{4}-\d{2}-\d{2}\s*-\s*/', '', $basename);
        $normTitle  = normalize_title($titlePart);
        $distance   = levenshtein($dbNormTitle, $normTitle);

        if ($distance < $bestScore) {
            $bestScore = $distance;
            $bestKey   = $key;
        }
    }

    if ($bestKey === null) {
        echo json_encode([
            'status'  => 'error',
            'message' => 'Could not match question to an audio file.'
        ]);
        return;
    }

    // Create a pre-signed URL (since bucket is private)
    try {
        $cmd = $s3->getCommand('GetObject', [
            'Bucket' => $bucket,
            'Key'    => $bestKey,
        ]);

        // Link valid for 20 minutes (adjust as you like)
        $request  = $s3->createPresignedRequest($cmd, '+20 minutes');
        $audioUrl = (string)$request->getUri();
    } catch (Exception $e) {
        echo json_encode([
            'status'  => 'error',
            'message' => 'Could not generate audio URL.'
        ]);
        return;
    }

    echo json_encode([
        'status'    => 'ok',
        'title'     => $title,
        'audio_url' => $audioUrl
    ]);
}

/**
 * Normalize a title for fuzzy comparison:
 * - lowercase
 * - remove punctuation
 * - collapse whitespace
 */
function normalize_title(string $title): string
{
    $title = mb_strtolower($title, 'UTF-8');
    // Keep letters, numbers, and spaces only
    $title = preg_replace('/[^\p{L}\p{N}\s]+/u', ' ', $title);
    // Collapse whitespace
    $title = preg_replace('/\s+/u', ' ', $title);
    return trim($title);
}
