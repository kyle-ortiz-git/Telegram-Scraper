<?php
require 'vendor/autoload.php';

use Aws\S3\S3Client;
use Dotenv\Dotenv;

$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->load();

$db_host = $_ENV['DB_HOST'];
$db_user = $_ENV['DB_USER'];
$db_pass = $_ENV['DB_PASSWORD'];
$db_name = $_ENV['DB_NAME'];

$aws_region = $_ENV['AWS_DEFAULT_REGION'] ?? 'us-east-1';
$bucket     = $_ENV['S3_BUCKET'] ?? 'telegram-qna-splits';
$s3_prefix  = 'initial-splits/';

$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Database connection failed.']);
    exit;
}

header('Content-Type: application/json; charset=utf-8');

$action = $_POST['action'] ?? '';

if ($action === 'search') {
    handle_search($conn);
} elseif ($action === 'get_audio') {
    handle_get_audio($conn, $bucket, $aws_region, $s3_prefix);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Invalid action.']);
}

$conn->close();


/************************************************************
 * ALWAYS SEARCH BOTH TITLE + TRANSCRIPTION
 ************************************************************/
function handle_search(mysqli $conn): void
{
    $query = trim($_POST['query'] ?? '');

    if ($query === '') {
        echo json_encode(['status' => 'ok', 'results' => []]);
        return;
    }

    $like = '%' . $query . '%';

    $sql = "SELECT ID, Title, Date
            FROM Question
            WHERE Title LIKE ? OR Transcription LIKE ?
            ORDER BY Date DESC
            LIMIT 100";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param('ss', $like, $like);

    if (!$stmt->execute()) {
        echo json_encode(['status' => 'error', 'message' => 'Query failed.']);
        return;
    }

    $result  = $stmt->get_result();
    $results = [];

    while ($row = $result->fetch_assoc()) {
        $results[] = [
            'id'    => (int)$row['ID'],
            'title' => $row['Title'],
            'date'  => $row['Date']
        ];
    }

    $stmt->close();

    echo json_encode([
        'status'  => 'ok',
        'results' => $results
    ]);
}


/************************************************************
 * GET AUDIO LOGIC (UNCHANGED)
 ************************************************************/
function handle_get_audio(mysqli $conn, string $bucket, string $aws_region, string $s3_prefix): void
{
    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    if ($id <= 0) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid ID.']);
        return;
    }

    $sql = "SELECT ID, Title, Date FROM Question WHERE ID = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $id);

    if (!$stmt->execute()) {
        echo json_encode(['status' => 'error', 'message' => 'Query failed.']);
        return;
    }

    $result = $stmt->get_result();
    $row    = $result->fetch_assoc();
    $stmt->close();

    if (!$row) {
        echo json_encode(['status' => 'error', 'message' => 'Question not found.']);
        return;
    }

    $title = $row['Title'];
    $date  = $row['Date'];

    $s3 = new S3Client([
        'version' => 'latest',
        'region'  => $aws_region
    ]);

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
        echo json_encode(['status' => 'error', 'message' => 'No audio files for this question.']);
        return;
    }

    $dbNormTitle = normalize_title($title);
    $bestKey     = null;
    $bestScore   = PHP_INT_MAX;

    foreach ($objects['Contents'] as $object) {
        $key = $object['Key'];

        $basename  = basename($key, '.mp3');
        $titlePart = preg_replace('/^\d{4}-\d{2}-\d{2}\s*-\s*/', '', $basename);
        $normTitle = normalize_title($titlePart);

        $distance = levenshtein($dbNormTitle, $normTitle);

        if ($distance < $bestScore) {
            $bestScore = $distance;
            $bestKey   = $key;
        }
    }

    if ($bestKey === null) {
        echo json_encode(['status' => 'error', 'message' => 'Could not match audio file.']);
        return;
    }

    try {
        $cmd = $s3->getCommand('GetObject', [
            'Bucket' => $bucket,
            'Key'    => $bestKey
        ]);

        $request  = $s3->createPresignedRequest($cmd, '+20 minutes');
        $audioUrl = (string)$request->getUri();
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => 'Could not generate audio URL.']);
        return;
    }

    echo json_encode([
        'status'    => 'ok',
        'title'     => $title,
        'audio_url' => $audioUrl
    ]);
}


function normalize_title(string $title): string
{
    $title = mb_strtolower($title, 'UTF-8');
    $title = preg_replace('/[^\p{L}\p{N}\s]+/u', ' ', $title);
    $title = preg_replace('/\s+/u', ' ', $title);
    return trim($title);
}
