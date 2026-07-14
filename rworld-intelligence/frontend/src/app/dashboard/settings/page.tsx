"use client";

import { useEffect, useState } from "react";

export default function SettingsPage() {
  const [modules, setModules] = useState<{ name: string; slug: string; active: boolean }[]>([
    { name: "Artee ERP", slug: "artee-erp", active: true },
    { name: "AI Shipping Agent", slug: "shipping-agent", active: true },
    { name: "Fabric Scraper", slug: "fabric-scraper", active: true },
    { name: "RoofIQ AI", slug: "roofiq", active: true },
    { name: "Attendance", slug: "attendance", active: true },
    { name: "Utility Management", slug: "utility", active: true },
  ]);

  const toggleModule = (slug: string) => {
    setModules(
      modules.map((mod) =>
        mod.slug === slug ? { ...mod, active: !mod.active } : mod
      )
    );
  };

  return (
    <div className="space-y-8">
      <div className="grid grid-cols-1 lg:grid-cols-2 gap-8">
        {/* Module Management */}
        <div className="glass-panel rounded-xl p-6 space-y-6">
          <h3 className="text-lg font-bold text-gray-200">Modules Management</h3>
          <p className="text-sm text-gray-400">Enable or disable specific features dynamically across the RWorld platform.</p>
          
          <div className="space-y-4">
            {modules.map((mod) => (
              <div key={mod.slug} className="flex items-center justify-between p-4 bg-gray-900/40 rounded-lg border border-gray-800/40">
                <div className="flex flex-col">
                  <span className="text-sm font-semibold text-gray-200">{mod.name}</span>
                  <span className="text-xs text-gray-500 font-mono">/api/{mod.slug}</span>
                </div>
                <button
                  onClick={() => toggleModule(mod.slug)}
                  className={`px-3 py-1.5 text-xs font-semibold rounded-lg cursor-pointer border transition-all ${
                    mod.active
                      ? "bg-cyan-500/10 border-cyan-500/30 text-cyan-400"
                      : "bg-gray-950 border-gray-800 text-gray-500"
                  }`}
                >
                  {mod.active ? "Enabled" : "Disabled"}
                </button>
              </div>
            ))}
          </div>
        </div>

        {/* System Diagnostics */}
        <div className="glass-panel rounded-xl p-6 space-y-6">
          <h3 className="text-lg font-bold text-gray-200">System Diagnostics</h3>
          
          <div className="space-y-4 text-sm text-gray-300">
            <div className="flex justify-between items-center py-2 border-b border-gray-900">
              <span className="text-gray-400">Gateway Status</span>
              <span className="text-emerald-400 font-semibold">Online</span>
            </div>
            <div className="flex justify-between items-center py-2 border-b border-gray-900">
              <span className="text-gray-400">Primary Database</span>
              <span className="text-white font-semibold">SQLite (Active)</span>
            </div>
            <div className="flex justify-between items-center py-2 border-b border-gray-900">
              <span className="text-gray-400">Host OS Environment</span>
              <span className="text-white font-semibold">Windows Dev Machine</span>
            </div>
            <div className="flex justify-between items-center py-2 border-b border-gray-900">
              <span className="text-gray-400">API Connection URL</span>
              <span className="text-cyan-400 font-mono"></span>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
}
