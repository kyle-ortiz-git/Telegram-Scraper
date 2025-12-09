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
        const mode = document.querySelector("input[name='mode']:checked").value;

        if (query === "") {
            statusMessage.innerText = "Please enter a search term.";
            return;
        }

        fetch("process.php", {
            method: "POST",
            headers: { "Content-Type": "application/x-www-form-urlencoded" },
            body: `action=search&query=${encodeURIComponent(query)}&mode=${encodeURIComponent(mode)}`
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
    }
});
