<?php
require 'vendor/autoload.php';

use Aws\S3\S3Client;
use Dotenv\Dotenv;

// Load environment
$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->load();

// Database credentials
$db_host = $_ENV['DB_HOST'];
$db_user = $_ENV['DB_USER'];
$db_pass = $_ENV['DB_PASSWORD'];
$db_name = $_ENV['DB_NAME'];

// S3 config
$aws_region = $_ENV['AWS_DEFAULT_REGION'] ?? 'us-east-1';
$bucket     = $_ENV['S3_BUCKET'] ?? 'telegram-qna-splits';
$s3_prefix  = 'initial-splits/';

// Connect to DB
$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Database connection failed.']);
    exit;
}

header('Content-Type: application/json; charset=utf-8');

// Determine action
$action = $_POST['action'] ?? $_GET['action'] ?? '';

if ($action === 'search') {
    handle_search($conn);
} elseif ($action === 'get_audio') {
    handle_get_audio($conn, $bucket, $aws_region, $s3_prefix);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Invalid action.']);
}

$conn->close();


/**
 * SEARCH: Always Title + Transcription
 */
function handle_search(mysqli $conn): void
{
    $query = trim($_POST['query'] ?? '');

    if ($query === '') {
        echo json_encode(['status' => 'ok', 'results' => []]);
        return;
    }

    $like = '%' . $query . '%';

    // Always search in BOTH Title and Transcript
    $sql = "SELECT ID, Title, Date, Transcription
            FROM Question
            WHERE Title LIKE ? OR Transcription LIKE ?
            ORDER BY Date DESC
            LIMIT 100";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param('ss', $like, $like);
    $stmt->execute();

    $result  = $stmt->get_result();
    $results = [];

    while ($row = $result->fetch_assoc()) {
        $transcription = $row['Transcription'] ?? '';
        $snippet = mb_substr($transcription, 0, 180);
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

    echo json_encode(['status' => 'ok', 'results' => $results]);
}



/**
 * GET AUDIO
 */
function handle_get_audio(mysqli $conn, string $bucket, string $aws_region, string $s3_prefix): void
{
    $id = (int)($_POST['id'] ?? 0);

    if ($id <= 0) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid ID.']);
        return;
    }

    // Fetch metadata
    $stmt = $conn->prepare("SELECT ID, Title, Date FROM Question WHERE ID = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute();

    $result = $stmt->get_result();
    $row    = $result->fetch_assoc();
    $stmt->close();

    if (!$row) {
        echo json_encode(['status' => 'error', 'message' => 'Question not found.']);
        return;
    }

    $title = $row['Title'];
    $date  = $row['Date'];

    // S3 client
    $s3 = new S3Client([
        'version' => 'latest',
        'region'  => $aws_region
    ]);

    // Object key prefix
    $prefix = $s3_prefix . $date . ' - ';

    try {
        $objects = $s3->listObjectsV2([
            'Bucket' => $bucket,
            'Prefix' => $prefix
        ]);
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => 'Could not list audio files.']);
        return;
    }

    if (empty($objects['Contents'])) {
        echo json_encode(['status' => 'error', 'message' => 'No audio files found.']);
        return;
    }

    // Fuzzy match audio filename to DB title
    $dbNorm = normalize_title($title);
    $bestKey = null;
    $bestScore = PHP_INT_MAX;

    foreach ($objects['Contents'] as $object) {
        $key = $object['Key'];

        // Extract actual title from filename
        $basename   = basename($key, '.mp3');
        $titlePart  = preg_replace('/^\d{4}-\d{2}-\d{2}\s*-\s*/', '', $basename);
        $norm       = normalize_title($titlePart);
        $distance   = levenshtein($dbNorm, $norm);

        if ($distance < $bestScore) {
            $bestScore = $distance;
            $bestKey   = $key;
        }
    }

    if ($bestKey === null) {
        echo json_encode(['status' => 'error', 'message' => 'No matching audio found.']);
        return;
    }

    // Generate pre-signed URL
    $cmd = $s3->getCommand('GetObject', [
        'Bucket' => $bucket,
        'Key'    => $bestKey
    ]);
    $request = $s3->createPresignedRequest($cmd, '+20 minutes');
    $url = (string)$request->getUri();

    echo json_encode([
        'status'    => 'ok',
        'title'     => $title,
        'audio_url' => $url
    ]);
}


/**
 * Normalize Title for Levenshtein
 */
function normalize_title(string $title): string
{
    $title = mb_strtolower($title, 'UTF-8');
    $title = preg_replace('/[^\p{L}\p{N}\s]+/u', ' ', $title);
    return trim(preg_replace('/\s+/u', ' ', $title));
}
