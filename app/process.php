<?php
require 'vendor/autoload.php';

use Aws\S3\S3Client;
use Dotenv\Dotenv;

// Load environment variables from .env file
$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->load();

// Database credentials
$db_host = $_ENV['DB_HOST'];
$db_user = $_ENV['DB_USER'];
$db_pass = $_ENV['DB_PASSWORD'];
$db_name = $_ENV['DB_NAME'];

// S3 / bucket config
$aws_region = $_ENV['AWS_DEFAULT_REGION'] ?? 'us-east-1';
$bucket     = $_ENV['S3_BUCKET'] ?? 'telegram-qna-splits';
$s3_prefix  = 'initial-splits/'; // prefix inside the bucket

// Connect to DB
$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
if ($conn->connect_error) {
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
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
 *
 * Uses table: Question (ID, Title, Date, Transcription)
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

    if ($mode === 'both') {
        $sql = "SELECT ID, Title, Date, Transcription
                FROM Question
                WHERE Title LIKE ? OR Transcription LIKE ?
                ORDER BY Date DESC
                LIMIT 100";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('ss', $like, $like);
    } else {
        $sql = "SELECT ID, Title, Date, Transcription
                FROM Question
                WHERE Title LIKE ?
                ORDER BY Date DESC
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
        $transcription = $row['Transcription'] ?? '';
        $snippet       = mb_substr($transcription, 0, 180);
        if (mb_strlen($transcription) > 180) {
            $snippet .= '…';
        }

        $results[] = [
            'id'      => (int)$row['ID'],
            'title'   => $row['Title'],
            'date'    => $row['Date'],
            'snippet' => $snippet
        ];
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
 * Uses DB Date + Title and S3 listObjects to find the best-matching MP3
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

    $sql  = "SELECT ID, Title, Date FROM Question WHERE ID = ?";
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

    $title = $row['Title'];
    $date  = $row['Date'];

    // Build S3 client (credentials are taken from environment / IAM role)
    $s3 = new S3Client([
        'version' => 'latest',
        'region'  => $aws_region
    ]);

    // Keys look like:
    //   initial-splits/2022-29-11 - Ruling of death benefits.mp3
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

        // Link valid for 20 minutes (adjust as needed)
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
