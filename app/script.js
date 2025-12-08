document.addEventListener("DOMContentLoaded", () => {
    const form = document.getElementById("searchForm");
    const resultsBody = document.getElementById("resultsBody");
    const noResults = document.getElementById("noResults");
    const statusMessage = document.getElementById("statusMessage");

    form.addEventListener("submit", function (e) {
        e.preventDefault();
        noResults.style.display = "none";
        statusMessage.style.display = "block";
        statusMessage.innerText = "Searching...";

        const query = document.getElementById("query").value.trim();

        if (query === "") {
            statusMessage.innerText = "Please enter a search term.";
            return;
        }

        fetch("process.php", {
            method: "POST",
            headers: { "Content-Type": "application/x-www-form-urlencoded" },
            body: `action=search&query=${encodeURIComponent(query)}&mode=both`
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
                        <td>${item.snippet}</td>
                        <td class="audio-cell">
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
            btn.addEventListener("click", function (e) {
                e.preventDefault();
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

                        const modalTitle = document.getElementById("qaModalTitle");
                        const audioSrc = document.getElementById("qaAudioSource");
                        const audioTag = document.getElementById("qaAudio");

                        modalTitle.innerText = data.title;
                        audioSrc.src = data.audio_url;
                        audioTag.load();
                        $("#qaModal").modal("show");
                    })
                    .catch(err => {
                        console.error(err);
                        alert("Failed to load audio.");
                    });
            });
        });
    }
});
