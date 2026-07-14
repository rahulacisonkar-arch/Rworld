"use client";

import { useEffect, useState } from "react";

interface RoofIQProject {
  id: number;
  project_name: string;
  address: string;
  roof_area: number;
  estimated_bom: string; // JSON format
  total_price: number;
  created_at: string;
}

export default function RoofIQPage() {
  const [projects, setProjects] = useState<RoofIQProject[]>([]);
  const [name, setName] = useState("");
  const [address, setAddress] = useState("");
  const [area, setArea] = useState(1500);
  const [loading, setLoading] = useState(false);
  const [message, setMessage] = useState("");
  const [selectedBOM, setSelectedBOM] = useState<any | null>(null);

  const fetchProjects = async () => {
    const token = localStorage.getItem("rworld_token");
    try {
      const response = await fetch("${process.env.NEXT_PUBLIC_API_URL || 'http://localhost:8000'}/api/roofiq/projects", {
        headers: { Authorization: `Bearer ${token}` },
      });
      if (response.ok) {
        setProjects(await response.json());
      }
    } catch (err) {
      console.error("Error loading RoofIQ projects:", err);
    }
  };

  useEffect(() => {
    fetchProjects();
  }, []);

  const handleCreateProject = async (e: React.FormEvent) => {
    e.preventDefault();
    setLoading(true);
    setMessage("");
    const token = localStorage.getItem("rworld_token");

    try {
      const response = await fetch("${process.env.NEXT_PUBLIC_API_URL || 'http://localhost:8000'}/api/roofiq/projects", {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
          Authorization: `Bearer ${token}`,
        },
        body: JSON.stringify({ project_name: name, address, roof_area: area }),
      });

      const data = await response.json();
      if (!response.ok) throw new Error(data.detail || "Failed to create project");

      setMessage(`Project registered! Total Estimate: $${data.total_price.toFixed(2)}`);
      setName("");
      setAddress("");
      setArea(1500);
      fetchProjects();
    } catch (err: any) {
      setMessage(`Error: ${err.message}`);
    } finally {
      setLoading(false);
    }
  };

  return (
    <div className="space-y-8">
      <div className="grid grid-cols-1 lg:grid-cols-3 gap-8">
        {/* Estimator Input Form */}
        <div className="lg:col-span-1 glass-panel rounded-xl p-6 h-fit space-y-6">
          <h3 className="text-lg font-bold text-gray-200">Roof Area Calculator</h3>
          {message && (
            <div className="p-3 bg-cyan-950/40 border border-cyan-500/20 rounded-lg text-cyan-300 text-sm text-center">
              {message}
            </div>
          )}
          <form onSubmit={handleCreateProject} className="space-y-4">
            <div className="space-y-1">
              <label className="text-xs text-gray-400 font-semibold">Project Name</label>
              <input
                required
                value={name}
                onChange={(e) => setName(e.target.value)}
                className="w-full px-3 py-2 bg-gray-900 border border-gray-800 rounded text-sm text-white focus:outline-none focus:ring-1 focus:ring-cyan-500"
                placeholder="Smith Residential Roof"
              />
            </div>

            <div className="space-y-1">
              <label className="text-xs text-gray-400 font-semibold">Property Address</label>
              <input
                required
                value={address}
                onChange={(e) => setAddress(e.target.value)}
                className="w-full px-3 py-2 bg-gray-900 border border-gray-800 rounded text-sm text-white focus:outline-none focus:ring-1 focus:ring-cyan-500"
                placeholder="42 High St, Seattle, WA"
              />
            </div>

            <div className="space-y-1">
              <label className="text-xs text-gray-400 font-semibold">Roof Area (Sq Ft)</label>
              <input
                type="number"
                required
                min={100}
                max={50000}
                value={area}
                onChange={(e) => setArea(parseFloat(e.target.value))}
                className="w-full px-3 py-2 bg-gray-900 border border-gray-800 rounded text-sm text-white focus:outline-none focus:ring-1 focus:ring-cyan-500"
              />
            </div>

            <button
              type="submit"
              disabled={loading}
              className="w-full py-2.5 bg-gradient-to-r from-cyan-500 to-emerald-500 hover:from-cyan-600 hover:to-emerald-600 text-white font-semibold rounded text-sm cursor-pointer"
            >
              {loading ? "Generating BOM..." : "Calculate BOM & Save"}
            </button>
          </form>
        </div>

        {/* Saved Estimations Queue */}
        <div className="lg:col-span-2 glass-panel rounded-xl p-6">
          <h3 className="text-lg font-bold text-gray-200 mb-6">Roofing Projects & Estimations</h3>
          <div className="overflow-x-auto">
            <table className="w-full text-left border-collapse">
              <thead>
                <tr className="border-b border-gray-800 text-xs font-semibold text-gray-400 uppercase tracking-wider">
                  <th className="pb-3">Project</th>
                  <th className="pb-3">Address</th>
                  <th className="pb-3">Roof Area</th>
                  <th className="pb-3">Est. Price</th>
                  <th className="pb-3">BOM Specs</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-gray-900 text-sm">
                {projects.map((proj) => {
                  let bom = {};
                  try {
                    bom = JSON.parse(proj.estimated_bom);
                  } catch (e) {}

                  return (
                    <tr key={proj.id} className="hover:bg-gray-900/20 transition-colors">
                      <td className="py-4 font-semibold text-gray-200">{proj.project_name}</td>
                      <td className="py-4 text-xs text-gray-400 max-w-xs truncate">{proj.address}</td>
                      <td className="py-4">{proj.roof_area.toLocaleString()} sq ft</td>
                      <td className="py-4 text-cyan-400 font-semibold">${proj.total_price.toFixed(2)}</td>
                      <td className="py-4">
                        <button
                          onClick={() => setSelectedBOM(bom)}
                          className="text-xs px-2 py-1 bg-cyan-950 border border-cyan-500/30 text-cyan-300 rounded hover:bg-cyan-900 cursor-pointer"
                        >
                          View BOM
                        </button>
                      </td>
                    </tr>
                  );
                })}
                {projects.length === 0 && (
                  <tr>
                    <td colSpan={5} className="py-4 text-center text-gray-500">
                      No roofing estimate projects created.
                    </td>
                  </tr>
                )}
              </tbody>
            </table>
          </div>
        </div>
      </div>

      {/* BOM Detail Modal Overlay */}
      {selectedBOM && (
        <div className="fixed inset-0 bg-black/60 backdrop-blur-sm z-50 flex items-center justify-center p-4">
          <div className="w-full max-w-md glass-panel-glow rounded-xl p-6 space-y-6">
            <div className="flex justify-between items-center">
              <h4 className="text-lg font-bold text-gray-200">Bill of Materials (BOM) Specs</h4>
              <button onClick={() => setSelectedBOM(null)} className="text-gray-400 hover:text-white cursor-pointer">
                ✕
              </button>
            </div>
            
            <div className="space-y-4">
              <div className="flex justify-between items-center p-3 bg-gray-900/40 rounded border border-gray-800 text-sm">
                <span className="text-gray-400">Shingle Bundles</span>
                <span className="font-semibold text-white">{selectedBOM.shingles_bundles}</span>
              </div>

              <div className="flex justify-between items-center p-3 bg-gray-900/40 rounded border border-gray-800 text-sm">
                <span className="text-gray-400">Underlayment Rolls</span>
                <span className="font-semibold text-white">{selectedBOM.underlayment_rolls}</span>
              </div>

              <div className="flex justify-between items-center p-3 bg-gray-900/40 rounded border border-gray-800 text-sm">
                <span className="text-gray-400">Nail Boxes Needed</span>
                <span className="font-semibold text-white">{selectedBOM.nails_boxes}</span>
              </div>

              <div className="flex justify-between items-center p-3 bg-gray-900/40 rounded border border-gray-800 text-sm">
                <span className="text-gray-400">Estimated Labor (Hrs)</span>
                <span className="font-semibold text-white">{selectedBOM.labor_hours_estimate} hrs</span>
              </div>
            </div>

            <div className="flex justify-end">
              <button
                type="button"
                onClick={() => setSelectedBOM(null)}
                className="px-4 py-2 bg-cyan-500 hover:bg-cyan-600 text-white text-xs font-semibold rounded cursor-pointer"
              >
                Close
              </button>
            </div>
          </div>
        </div>
      )}
    </div>
  );
}
