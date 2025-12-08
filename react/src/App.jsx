import { useState, useEffect } from "react";

export default function App() {
  const [query, setQuery] = useState("");
  const [results, setResults] = useState([]);
  const [mode, setMode] = useState("all");
  const [dark, setDark] = useState(false);

  useEffect(() => {
    document.documentElement.classList.toggle("dark", dark);
  }, [dark]);

  async function search() {
    if (!query.trim()) return;

    const params = new URLSearchParams({
      action: "search",
      query,
      mode,
    });

    const res = await fetch(`/process.php?${params.toString()}`);
    const data = await res.json();
    setResults(data.results || []);
  }

  function playAudio(id) {
    window.open(`/process.php?action=audio&id=${id}`, "_blank");
  }

  return (
    <div
      className={`min-h-screen px-4 py-10 transition-colors duration-300 ${
        dark ? "bg-gray-900 text-white" : "bg-gray-100 text-gray-900"
      }`}
    >
      {/* Header */}
      <div className="max-w-4xl mx-auto flex items-center justify-between mb-8">
        <h1 className="text-3xl font-bold">Live Q&A Search</h1>

        <button
          onClick={() => setDark(!dark)}
          className="px-4 py-2 rounded-md bg-gray-800 dark:bg-gray-200 text-white dark:text-black transition"
        >
          {dark ? "Light Mode" : "Dark Mode"}
        </button>
      </div>

      {/* Search Box */}
      <div className="max-w-4xl mx-auto bg-white dark:bg-gray-800 p-6 rounded-lg shadow-lg mb-8">
        <div className="flex gap-4 mb-4">
          <input
            type="text"
            placeholder="Search questions or transcript..."
            value={query}
            onChange={(e) => setQuery(e.target.value)}
            className="flex-1 px-4 py-3 rounded-md border dark:border-gray-700 dark:bg-gray-900"
          />

          <button
            onClick={search}
            className="px-6 py-3 bg-blue-600 hover:bg-blue-700 text-white rounded-md transition"
          >
            Search
          </button>
        </div>
      </div>

      {/* Results */}
      <div className="max-w-4xl mx-auto space-y-4">
        {results.map((item) => (
          <div
            key={item.ID}
            className="p-5 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg shadow"
          >
            <h2 className="text-xl font-semibold mb-2">{item.Title}</h2>

            <p className="text-gray-600 dark:text-gray-300 mb-3">
              {item.Transcription.slice(0, 200)}...
            </p>

            <button
              onClick={() => playAudio(item.ID)}
              className="px-4 py-2 bg-green-600 hover:bg-green-700 text-white rounded-md transition"
            >
              ▶ Play Audio
            </button>
          </div>
        ))}

        {results.length === 0 && (
          <p className="text-center text-gray-500 dark:text-gray-400">
            No results yet.
          </p>
        )}
      </div>
    </div>
  );
}
