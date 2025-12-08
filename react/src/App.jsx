import { useState, useEffect } from "react";

export default function App() {
  const [query, setQuery] = useState("");
  const [results, setResults] = useState([]);
  const [dark, setDark] = useState(false);

  useEffect(() => {
    document.documentElement.classList.toggle("dark", dark);
  }, [dark]);

  async function search() {
    if (!query.trim()) return;

    const res = await fetch(`/process.php?action=search&query=${query}`);
    const data = await res.json();
    setResults(data.results || []);
  }

  function play(id) {
    window.open(`/process.php?action=audio&id=${id}`, "_blank");
  }

  return (
    <div className={`min-h-screen px-4 py-8 transition-colors duration-300 ${
      dark ? "bg-gray-900 text-gray-200" : "bg-gray-100 text-gray-900"
    }`}>

      {/* HEADER */}
      <div className="max-w-4xl mx-auto flex items-center justify-between mb-6">
        <h1 className="text-3xl font-bold">Live Q&A Search</h1>

        <button
          onClick={() => setDark(!dark)}
          className="px-4 py-2 rounded-md transition 
                     bg-gray-800 text-white dark:bg-gray-200 dark:text-gray-900"
        >
          {dark ? "Light Mode" : "Dark Mode"}
        </button>
      </div>

      {/* SEARCH BAR */}
      <div className="max-w-4xl mx-auto flex gap-3 mb-8">
        <input
          type="text"
          placeholder="Search questions or transcript..."
          value={query}
          onChange={(e) => setQuery(e.target.value)}
          className="flex-1 px-4 py-3 rounded-md shadow 
                     dark:bg-gray-800 dark:text-gray-200 dark:border-gray-700 
                     border border-gray-300 focus:ring-2 focus:ring-blue-500"
        />
        <button
          onClick={search}
          className="px-6 py-3 bg-blue-600 text-white rounded-md shadow 
                     hover:bg-blue-700 transition"
        >
          Search
        </button>
      </div>

      {/* RESULTS */}
      <div className="max-w-4xl mx-auto">
        {results.length === 0 ? (
          <p className="text-center text-gray-500 dark:text-gray-400">
            No results yet. Try searching something.
          </p>
        ) : (
          <div className="space-y-4">
            {results.map((item) => (
              <div
                key={item.ID}
                className="p-5 rounded-lg shadow border border-gray-300 dark:border-gray-700 
                           bg-white dark:bg-gray-800 transition hover:shadow-lg"
              >
                <h2 className="text-xl font-semibold mb-2">{item.Title}</h2>

                <p className="text-gray-600 dark:text-gray-300 mb-3">
                  {item.Transcription.slice(0, 200)}...
                </p>

                <button
                  onClick={() => play(item.ID)}
                  className="px-4 py-2 bg-green-600 text-white rounded-md 
                             hover:bg-green-700 transition"
                >
                  ▶ Play Audio
                </button>
              </div>
            ))}
          </div>
        )}
      </div>
    </div>
  );
}
