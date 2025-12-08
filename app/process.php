// Handle search form submit
document.getElementById('searchForm').addEventListener('submit', function (event) {
    event.preventDefault();

    const queryInput = document.getElementById('query');
    const statusEl = document.getElementById('statusMessage');
    const resultsEl = document.getElementById('results');
    const noResultsEl = document.getElementById('noResults');

<<<<<<< HEAD
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
=======
    const query = queryInput.value.trim();
    if (!query) {
        return;
    }

    const mode = document.querySelector('input[name="mode"]:checked').value;

    // Clear UI
    statusEl.style.display = 'block';
    statusEl.textContent = 'Searching...';
    resultsEl.innerHTML = '';
    noResultsEl.style.display = 'none';

    const formData = new FormData();
    formData.append('action', 'search');
    formData.append('query', query);
    formData.append('mode', mode);

    fetch('process.php', {
        method: 'POST',
        body: formData
    })
        .then(response => response.json())
        .then(data => {
            if (data.status !== 'ok') {
                statusEl.textContent = data.message || 'An error occurred.';
                return;
            }

            statusEl.style.display = 'none';
            const results = data.results || [];

            if (!results.length) {
                noResultsEl.style.display = 'block';
                return;
            }

            resultsEl.innerHTML = '';

            results.forEach(item => {
                const a = document.createElement('a');
                a.href = '#';
                a.className = 'list-group-item list-group-item-action';
                a.dataset.id = item.id;
                a.dataset.title = item.title;

                const headerDiv = document.createElement('div');
                headerDiv.className = 'd-flex w-100 justify-content-between';

                const h5 = document.createElement('h5');
                h5.className = 'mb-1';
                h5.textContent = item.title;

                const small = document.createElement('small');
                small.textContent = item.date || '';

                headerDiv.appendChild(h5);
                headerDiv.appendChild(small);

                const p = document.createElement('p');
                p.className = 'mb-1';
                p.textContent = item.snippet || '';

                a.appendChild(headerDiv);
                a.appendChild(p);

                resultsEl.appendChild(a);
            });
        })
        .catch(err => {
            console.error(err);
            statusEl.style.display = 'block';
            statusEl.textContent = 'An error occurred while searching.';
        });
});

// Delegate click events on results list to open a question
document.getElementById('results').addEventListener('click', function (event) {
    const target = event.target.closest('.list-group-item');
    if (!target) return;
    event.preventDefault();

    const id = target.dataset.id;
    if (!id) return;

    openQuestionDialog(id);
});

function openQuestionDialog(id) {
    const formData = new FormData();
    formData.append('action', 'get_audio');
    formData.append('id', id);

    const statusEl = document.getElementById('statusMessage');
    statusEl.style.display = 'block';
    statusEl.textContent = 'Loading audio...';

    fetch('process.php', {
        method: 'POST',
        body: formData
    })
        .then(response => response.json())
        .then(data => {
            statusEl.style.display = 'none';

            if (data.status !== 'ok') {
                statusEl.style.display = 'block';
                statusEl.textContent = data.message || 'Unable to load audio.';
                return;
            }

            const title = data.title || '';
            const audioUrl = data.audio_url;

            const modalTitleEl = document.getElementById('qaModalTitle');
            const audioSourceEl = document.getElementById('qaAudioSource');
            const audioEl = document.getElementById('qaAudio');

            modalTitleEl.textContent = title;
            audioSourceEl.src = audioUrl;
            audioEl.load();

            // Show Bootstrap modal
            $('#qaModal').modal('show');
        })
        .catch(err => {
            console.error(err);
            statusEl.style.display = 'block';
            statusEl.textContent = 'An error occurred while loading the audio.';
        });
>>>>>>> parent of f3995ca (Uploading the index.html, process.php, script.js for the website displaying all the results & search)
}
