// Handle search form submit
document.getElementById('searchForm').addEventListener('submit', function (event) {
    event.preventDefault();

    const queryInput = document.getElementById('query');
    const statusEl = document.getElementById('statusMessage');
    const resultsEl = document.getElementById('results');
    const noResultsEl = document.getElementById('noResults');

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
}
