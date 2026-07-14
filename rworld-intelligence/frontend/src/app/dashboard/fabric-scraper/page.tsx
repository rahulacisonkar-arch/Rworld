"use client";

import { useEffect, useState } from "react";

interface ScrapedProduct {
  id: number;
  url: string;
  name: string;
  sku: string;
  manufacturer: string;
  description: string;
  width: string;
  type_of_fabric: string;
  fiber_content: string;
  retail_price: string;
  scraped_at: string;
}

export default function FabricScraperPage() {
  const [products, setProducts] = useState<ScrapedProduct[]>([]);
  const [limit, setLimit] = useState(5);
  const [statusMessage, setStatusMessage] = useState("");
  const [running, setRunning] = useState(false);

  const fetchProducts = async () => {
    const token = localStorage.getItem("rworld_token");
    try {
      const response = await fetch("${process.env.NEXT_PUBLIC_API_URL || 'http://localhost:8000'}/api/fabric-scraper/products", {
        headers: { Authorization: `Bearer ${token}` },
      });
      if (response.ok) {
        setProducts(await response.json());
      }
    } catch (err) {
      console.error("Error loading products:", err);
    }
  };

  useEffect(() => {
    fetchProducts();
  }, []);

  const handleRunScraper = async (e: React.FormEvent) => {
    e.preventDefault();
    setRunning(true);
    setStatusMessage("Triggering scraper run...");
    const token = localStorage.getItem("rworld_token");

    try {
      const response = await fetch("${process.env.NEXT_PUBLIC_API_URL || 'http://localhost:8000'}/api/fabric-scraper/run", {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
          Authorization: `Bearer ${token}`,
        },
        body: JSON.stringify({ limit }),
      });

      const data = await response.json();
      if (!response.ok) throw new Error(data.detail || "Failed to start scraper");

      setStatusMessage("Scraper running in the background. Refreshing list shortly...");
      
      // Auto-reload data after a brief delay
      setTimeout(() => {
        fetchProducts();
        setRunning(false);
        setStatusMessage("Scraper completed! Database updated.");
      }, 4000);
    } catch (err: any) {
      setStatusMessage(`Error: ${err.message}`);
      setRunning(false);
    }
  };

  return (
    <div className="space-y-8">
      <div className="grid grid-cols-1 lg:grid-cols-3 gap-8">
        {/* Scraper Operations */}
        <div className="lg:col-span-1 glass-panel rounded-xl p-6 h-fit space-y-6">
          <h3 className="text-lg font-bold text-gray-200">Connector Control</h3>
          {statusMessage && (
            <div className="p-3 bg-cyan-950/40 border border-cyan-500/20 rounded-lg text-cyan-300 text-sm text-center">
              {statusMessage}
            </div>
          )}
          <form onSubmit={handleRunScraper} className="space-y-4">
            <div className="space-y-1">
              <label className="text-xs text-gray-400 font-semibold block">Target URL Sitemap</label>
              <div className="p-3 bg-gray-900/60 rounded border border-gray-800 text-xs text-gray-300">
                https://www.fabricmill.com/sitemap.xml
              </div>
            </div>

            <div className="space-y-1">
              <label className="text-xs text-gray-400 font-semibold">Scrape Limit (Items)</label>
              <input
                type="number"
                required
                min={1}
                max={50}
                value={limit}
                onChange={(e) => setLimit(parseInt(e.target.value))}
                className="w-full px-3 py-2 bg-gray-900 border border-gray-800 rounded text-sm text-white focus:outline-none focus:ring-1 focus:ring-cyan-500"
              />
            </div>

            <button
              type="submit"
              disabled={running}
              className="w-full py-2.5 bg-gradient-to-r from-cyan-500 to-emerald-500 hover:from-cyan-600 hover:to-emerald-600 text-white font-semibold rounded text-sm cursor-pointer disabled:opacity-55"
            >
              {running ? "Scraper Active..." : "Start Harvesting"}
            </button>
          </form>
        </div>

        {/* Harvested Products */}
        <div className="lg:col-span-2 glass-panel rounded-xl p-6">
          <h3 className="text-lg font-bold text-gray-200 mb-6">Harvested Catalog</h3>
          <div className="overflow-y-auto max-h-[500px]">
            {products.length === 0 ? (
              <p className="text-sm text-gray-500 text-center py-8">
                No products scraped yet. Click "Start Harvesting" to gather demo catalog items.
              </p>
            ) : (
              <div className="space-y-4 pr-2">
                {products.map((prod) => (
                  <div key={prod.id} className="p-4 bg-gray-950/60 border border-gray-900 rounded-lg hover:border-cyan-500/30 transition-all flex flex-col md:flex-row md:items-center justify-between gap-4">
                    <div className="space-y-1">
                      <div className="text-sm font-semibold text-gray-200">{prod.name}</div>
                      <div className="text-xs text-gray-400 font-mono">SKU: {prod.sku} | Manufacturer: {prod.manufacturer || "Unknown"}</div>
                      <div className="text-xs text-gray-500 line-clamp-1">{prod.description}</div>
                    </div>
                    <div className="text-right flex flex-row md:flex-col justify-between items-center md:items-end gap-2 md:gap-0 shrink-0">
                      <span className="text-sm font-extrabold text-cyan-400">{prod.retail_price || "$0.00"}</span>
                      <a
                        href={prod.url}
                        target="_blank"
                        rel="noreferrer"
                        className="text-xs text-gray-400 hover:text-white underline cursor-pointer"
                      >
                        Source Link
                      </a>
                    </div>
                  </div>
                ))}
              </div>
            )}
          </div>
        </div>
      </div>
    </div>
  );
}
