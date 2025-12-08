import { useState } from "react";

function App() {
  const [query, setQuery] = useState("");
  const [mode, setMode] = useState("all");
  const [results, setResults] = useState([]);

  async function search() {
    if (!query.trim()) return;

    const params = new URLSearchParams({
      action: "search",
      query,
      mode
    });

    const res = await fetch(`/process.php?${params.toString()}`);
    const data = await res.json();
    setResults(data.results || []);
  }

  function playAudio(id) {
    window.open(`/process.php?action=audio&id=${id}`, "_blank");
  }

  return (
    <div style={{ maxWidth: "900px", margin: "40px auto", fontFamily: "Arial" }}>
      <h1 style={{ textAlign: "center" }}>Live Q&A Search (React)</h1>

      <div style={{ display: "flex", gap: "10px", marginBottom: "20px" }}>
        <input
          type="text"
          placeholder="Search..."
          value={query}
          onChange={(e) => setQuery(e.target.value)}
          style={{ flex: 1, padding: "10px" }}
        />

        <button onClick={search} style={{ padding: "10px 20px" }}>
          Search
        </button>
      </div>

      <table width="100%" border="1" cellPadding="8">
        <thead>
          <tr>
            <th>ID</th>
            <th>Title</th>
            <th>Transcript</th>
            <th>Audio</th>
          </tr>
        </thead>

        <tbody>
          {results.map((r) => (
            <tr key={r.ID}>
              <td>{r.ID}</td>
              <td>{r.Title}</td>
              <td>{r.Transcription.slice(0, 120)}...</td>
              <td>
                <button onClick={() => playAudio(r.ID)}>Play</button>
              </td>
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  );
}

export default App;
