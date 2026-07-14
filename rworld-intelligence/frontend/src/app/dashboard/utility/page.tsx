"use client";

import { useEffect, useState } from "react";

interface Store {
  id: number;
  store_code: string;
  store_name: string;
  city: string;
}

interface UtilityConnection {
  id: number;
  store_id: number;
  store_name: string;
  city: string;
  utility_type: string;
  provider_name: string;
  account_number: string;
  notes: string;
  status: string;
}

interface Bill {
  id: number;
  connection_id: number;
  store_id: number;
  store_name: string;
  city: string;
  utility_type: string;
  provider_name: string;
  account_number: string;
  statement_date: string;
  due_date: string;
  amount: number;
  bill_file_path: string;
  status: string;
  paid_at: string;
  transaction_ref: string;
  notes: string;
}

interface YoYData {
  electricity: {
    "2025": number[];
    "2026": number[];
  };
  gas: {
    "2025": number[];
    "2026": number[];
  };
}

export default function UtilityPage() {
  const [stores, setStores] = useState<Store[]>([]);
  const [connections, setConnections] = useState<UtilityConnection[]>([]);
  const [bills, setBills] = useState<Bill[]>([]);
  const [yoyData, setYoyData] = useState<YoYData | null>(null);
  
  const [activeSubTab, setActiveSubTab] = useState<"connections" | "bills" | "yoy">("bills");
  const [selectedYoyUtility, setSelectedYoyUtility] = useState<"electricity" | "gas">("electricity");
  const [loading, setLoading] = useState(true);
  
  // Form States (New Connection)
  const [connStoreId, setConnStoreId] = useState<number>(0);
  const [connType, setConnType] = useState("Electricity");
  const [connProvider, setConnProvider] = useState("");
  const [connAcct, setConnAcct] = useState("");
  const [connNotes, setConnNotes] = useState("");
  const [connMessage, setConnMessage] = useState("");

  // Form States (New Bill Upload)
  const [uploadConnId, setUploadConnId] = useState<number>(0);
  const [uploadStoreId, setUploadStoreId] = useState<number>(0);
  const [uploadStmtDate, setUploadStmtDate] = useState("");
  const [uploadDueDate, setUploadDueDate] = useState("");
  const [uploadAmount, setUploadAmount] = useState<number>(0.0);
  const [uploadNotes, setUploadNotes] = useState("");
  const [uploadFile, setUploadFile] = useState<File | null>(null);
  const [uploadMessage, setUploadMessage] = useState("");

  const fetchData = async () => {
    const token = localStorage.getItem("rworld_token");
    if (!token) return;
    setLoading(true);
    try {
      // Stores
      const storesResp = await fetch("/api/attendance/stores", {
        headers: { Authorization: `Bearer ${token}` },
      });
      if (storesResp.ok) {
        const storeData = await storesResp.json();
        setStores(storeData);
        if (storeData.length > 0) {
          setConnStoreId(storeData[0].id);
          setUploadStoreId(storeData[0].id);
        }
      }

      // Connections
      const connResp = await fetch("/api/utility/connections", {
        headers: { Authorization: `Bearer ${token}` },
      });
      if (connResp.ok) {
        const connData = await connResp.json();
        setConnections(connData);
        if (connData.length > 0) {
          setUploadConnId(connData[0].id);
        }
      }

      // Bills
      const billsResp = await fetch("/api/utility/bills", {
        headers: { Authorization: `Bearer ${token}` },
      });
      if (billsResp.ok) {
        setBills(await billsResp.json());
      }

      // YoY Reports
      const yoyResp = await fetch("/api/utility/reports/yoy", {
        headers: { Authorization: `Bearer ${token}` },
      });
      if (yoyResp.ok) {
        setYoyData(await yoyResp.json());
      }
    } catch (err) {
      console.error("Error loading utility details:", err);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchData();
  }, []);

  const handleCreateConnection = async (e: React.FormEvent) => {
    e.preventDefault();
    setConnMessage("");
    const token = localStorage.getItem("rworld_token");
    try {
      const response = await fetch("/api/utility/connections", {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
          Authorization: `Bearer ${token}`,
        },
        body: JSON.stringify({
          store_id: connStoreId,
          utility_type: connType,
          provider_name: connProvider,
          account_number: connAcct,
          notes: connNotes,
        }),
      });

      const data = await response.json();
      if (!response.ok) throw new Error(data.detail || "Failed to add utility connection");

      setConnMessage("Utility connection registered successfully!");
      setConnProvider("");
      setConnAcct("");
      setConnNotes("");
      fetchData();
    } catch (err: any) {
      setConnMessage(`Error: ${err.message}`);
    }
  };

  const handleUploadBill = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!uploadFile) return;
    setUploadMessage("");
    const token = localStorage.getItem("rworld_token");
    
    const formData = new FormData();
    formData.append("connection_id", String(uploadConnId));
    formData.append("store_id", String(uploadStoreId));
    formData.append("statement_date", uploadStmtDate || new Date().toISOString().split("T")[0]);
    formData.append("due_date", uploadDueDate || new Date().toISOString().split("T")[0]);
    formData.append("amount", String(uploadAmount));
    formData.append("notes", uploadNotes);
    formData.append("file", uploadFile);

    try {
      const response = await fetch("/api/utility/bills/upload", {
        method: "POST",
        headers: {
          Authorization: `Bearer ${token}`,
        },
        body: formData,
      });

      const data = await response.json();
      if (!response.ok) throw new Error(data.detail || "Failed to upload utility bill");

      setUploadMessage("Utility bill statement uploaded successfully!");
      setUploadAmount(0.0);
      setUploadNotes("");
      setUploadFile(null);
      fetchData();
    } catch (err: any) {
      setUploadMessage(`Error: ${err.message}`);
    }
  };

  const handlePayBill = async (billId: number) => {
    const token = localStorage.getItem("rworld_token");
    try {
      const response = await fetch(`/api/utility/bills/${billId}/pay`, {
        method: "POST",
        headers: {
          Authorization: `Bearer ${token}`,
        },
      });

      if (!response.ok) {
        const data = await response.json();
        throw new Error(data.detail || "Payment failed");
      }
      fetchData();
    } catch (err: any) {
      alert(`Payment Error: ${err.message}`);
    }
  };

  const monthNames = [
    "Jan", "Feb", "Mar", "Apr", "May", "Jun", 
    "Jul", "Aug", "Sep", "Oct", "Nov", "Dec"
  ];

  // YoY Spending Graph Helpers
  const renderYoYGraph = () => {
    if (!yoyData) return null;
    
    const utilityData = yoyData[selectedYoyUtility];
    const vals2025 = utilityData["2025"] || [];
    const vals2026 = utilityData["2026"] || [];
    
    const maxVal = Math.max(...vals2025, ...vals2026, 100);
    const chartHeight = 220;
    const chartWidth = 550;
    const padX = 40;
    const padY = 20;
    
    const scaleY = (val: number) => {
      return chartHeight - padY - (val / maxVal) * (chartHeight - 2 * padY);
    };

    return (
      <div className="space-y-6">
        <div className="flex justify-between items-center mb-2">
          <div>
            <h4 className="text-sm font-semibold text-gray-300">
              YoY Monthly {selectedYoyUtility === "electricity" ? "Electricity" : "Gas"} Spend (Store #78 Portsmouth)
            </h4>
            <p className="text-xs text-gray-500">Comparing monthly utility cost between 2025 (violet) and 2026 (cyan)</p>
          </div>
          <div className="flex space-x-4 text-xs font-semibold">
            <span className="flex items-center space-x-1">
              <span className="w-3 h-3 bg-violet-600 rounded"></span>
              <span className="text-gray-400">2025</span>
            </span>
            <span className="flex items-center space-x-1">
              <span className="w-3 h-3 bg-cyan-500 rounded"></span>
              <span className="text-gray-400">2026</span>
            </span>
          </div>
        </div>

        {/* Custom SVG Bar Chart */}
        <div className="w-full bg-gray-950/60 p-4 border border-gray-900 rounded-xl overflow-x-auto">
          <svg viewBox={`0 0 ${chartWidth} ${chartHeight}`} className="w-full min-w-[500px] h-[220px]">
            {/* Gridlines */}
            {[0, 0.25, 0.5, 0.75, 1].map((ratio, idx) => {
              const y = padY + ratio * (chartHeight - 2 * padY);
              const gridVal = maxVal * (1 - ratio);
              return (
                <g key={idx}>
                  <line
                    x1={padX}
                    y1={y}
                    x2={chartWidth - padY}
                    y2={y}
                    stroke="#1e293b"
                    strokeDasharray="4 4"
                  />
                  <text
                    x={padX - 8}
                    y={y + 4}
                    fill="#64748b"
                    fontSize="9 font-mono"
                    textAnchor="end"
                  >
                    ${Math.round(gridVal)}
                  </text>
                </g>
              );
            })}

            {/* Bars */}
            {monthNames.map((m, idx) => {
              const xPos = padX + idx * 40 + 10;
              const val2025 = vals2025[idx] || 0.0;
              const val2026 = vals2026[idx] || 0.0;
              
              const h2025 = scaleY(val2025);
              const h2026 = scaleY(val2026);
              
              return (
                <g key={idx} className="group">
                  {/* 2025 Bar */}
                  {val2025 > 0 && (
                    <rect
                      x={xPos}
                      y={h2025}
                      width="12"
                      height={chartHeight - padY - h2025}
                      fill="#7c3aed"
                      rx="2"
                      className="opacity-80 hover:opacity-100 transition-opacity"
                    >
                      <title>{m} 2025: ${val2025.toFixed(2)}</title>
                    </rect>
                  )}
                  {/* 2026 Bar */}
                  {val2026 > 0 && (
                    <rect
                      x={xPos + 14}
                      y={h2026}
                      width="12"
                      height={chartHeight - padY - h2026}
                      fill="#06b6d4"
                      rx="2"
                      className="opacity-90 hover:opacity-100 transition-opacity"
                    >
                      <title>{m} 2026: ${val2026.toFixed(2)}</title>
                    </rect>
                  )}
                  {/* Month Label */}
                  <text
                    x={xPos + 12}
                    y={chartHeight - 4}
                    fill="#64748b"
                    fontSize="9"
                    textAnchor="middle"
                  >
                    {m}
                  </text>
                </g>
              );
            })}
          </svg>
        </div>

        {/* Comparison Table */}
        <div className="overflow-x-auto">
          <table className="w-full text-left border-collapse text-xs">
            <thead>
              <tr className="border-b border-gray-900 text-gray-500 uppercase font-semibold">
                <th className="pb-2">Month</th>
                <th className="pb-2 text-right">2025 Expense</th>
                <th className="pb-2 text-right">2026 Expense</th>
                <th className="pb-2 text-right">Difference ($)</th>
                <th className="pb-2 text-right">Difference (%)</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-gray-950 font-mono text-gray-300">
              {monthNames.map((m, idx) => {
                const val2025 = vals2025[idx] || 0.0;
                const val2026 = vals2026[idx] || 0.0;
                const diff = val2026 - val2025;
                const pct = val2025 > 0 ? (diff / val2025) * 100 : 0.0;
                
                if (val2025 === 0 && val2026 === 0) return null;
                
                return (
                  <tr key={idx} className="hover:bg-gray-900/10">
                    <td className="py-2.5 font-sans font-semibold text-gray-400">{m}</td>
                    <td className="py-2.5 text-right">${val2025.toFixed(2)}</td>
                    <td className="py-2.5 text-right">{val2026 > 0 ? `$${val2026.toFixed(2)}` : "--"}</td>
                    <td className={`py-2.5 text-right ${diff > 0 ? "text-red-400" : "text-emerald-400"}`}>
                      {val2026 > 0 ? `${diff > 0 ? "+" : ""}$${diff.toFixed(2)}` : "--"}
                    </td>
                    <td className={`py-2.5 text-right ${diff > 0 ? "text-red-400" : "text-emerald-400"}`}>
                      {val2026 > 0 ? `${diff > 0 ? "+" : ""}${pct.toFixed(1)}%` : "--"}
                    </td>
                  </tr>
                );
              })}
            </tbody>
          </table>
        </div>
      </div>
    );
  };

  return (
    <div className="space-y-8">
      {/* Sub tabs selector */}
      <div className="flex border-b border-gray-900 space-x-6">
        <button
          onClick={() => setActiveSubTab("bills")}
          className={`pb-4 px-2 font-bold cursor-pointer text-sm transition-colors ${
            activeSubTab === "bills" ? "border-b-2 border-cyan-500 text-cyan-400" : "text-gray-400 hover:text-white"
          }`}
        >
          Bills & Statement Ledger
        </button>
        <button
          onClick={() => setActiveSubTab("connections")}
          className={`pb-4 px-2 font-bold cursor-pointer text-sm transition-colors ${
            activeSubTab === "connections" ? "border-b-2 border-cyan-500 text-cyan-400" : "text-gray-400 hover:text-white"
          }`}
        >
          Utility Connections
        </button>
        <button
          onClick={() => setActiveSubTab("yoy")}
          className={`pb-4 px-2 font-bold cursor-pointer text-sm transition-colors ${
            activeSubTab === "yoy" ? "border-b-2 border-cyan-500 text-cyan-400" : "text-gray-400 hover:text-white"
          }`}
        >
          YoY Cost Analytics
        </button>
      </div>

      {loading ? (
        <div className="text-center py-12">
          <div className="animate-spin rounded-full h-8 w-8 border-t-2 border-b-2 border-cyan-500 mx-auto"></div>
          <p className="mt-4 text-gray-500 text-sm">Loading billing modules...</p>
        </div>
      ) : (
        <>
          {activeSubTab === "bills" && (
            <div className="grid grid-cols-1 lg:grid-cols-3 gap-8">
              {/* Form Upload Bill */}
              <div className="lg:col-span-1 glass-panel rounded-xl p-6 h-fit space-y-6">
                <h3 className="text-lg font-bold text-gray-200">Upload Utility Invoice</h3>
                {uploadMessage && (
                  <div className="p-3 bg-cyan-950/40 border border-cyan-500/20 rounded-lg text-cyan-300 text-sm text-center">
                    {uploadMessage}
                  </div>
                )}
                <form onSubmit={handleUploadBill} className="space-y-4">
                  <div className="space-y-1">
                    <label className="text-xs text-gray-400 font-semibold">Store Location</label>
                    <select
                      value={uploadStoreId}
                      onChange={(e) => setUploadStoreId(parseInt(e.target.value))}
                      className="w-full px-3 py-2 bg-gray-900 border border-gray-800 rounded text-sm text-white focus:outline-none focus:ring-1 focus:ring-cyan-500"
                    >
                      {stores.map((s) => (
                        <option key={s.id} value={s.id}>
                          {s.store_name} ({s.city})
                        </option>
                      ))}
                    </select>
                  </div>

                  <div className="space-y-1">
                    <label className="text-xs text-gray-400 font-semibold">Associated Account Connection</label>
                    <select
                      value={uploadConnId}
                      onChange={(e) => setUploadConnId(parseInt(e.target.value))}
                      className="w-full px-3 py-2 bg-gray-900 border border-gray-800 rounded text-sm text-white focus:outline-none focus:ring-1 focus:ring-cyan-500"
                    >
                      {connections
                        .filter((c) => c.store_id === uploadStoreId)
                        .map((c) => (
                          <option key={c.id} value={c.id}>
                            {c.utility_type} ({c.provider_name})
                          </option>
                        ))}
                      {connections.filter((c) => c.store_id === uploadStoreId).length === 0 && (
                        <option value={0}>No accounts for this location</option>
                      )}
                    </select>
                  </div>

                  <div className="grid grid-cols-2 gap-4">
                    <div className="space-y-1">
                      <label className="text-xs text-gray-400 font-semibold">Statement Date</label>
                      <input
                        type="date"
                        value={uploadStmtDate}
                        onChange={(e) => setUploadStmtDate(e.target.value)}
                        className="w-full px-3 py-2 bg-gray-900 border border-gray-800 rounded text-sm text-white"
                      />
                    </div>
                    <div className="space-y-1">
                      <label className="text-xs text-gray-400 font-semibold">Due Date</label>
                      <input
                        type="date"
                        value={uploadDueDate}
                        onChange={(e) => setUploadDueDate(e.target.value)}
                        className="w-full px-3 py-2 bg-gray-900 border border-gray-800 rounded text-sm text-white"
                      />
                    </div>
                  </div>

                  <div className="space-y-1">
                    <label className="text-xs text-gray-400 font-semibold">Invoice Amount ($)</label>
                    <input
                      type="number"
                      step="0.01"
                      required
                      value={uploadAmount}
                      onChange={(e) => setUploadAmount(parseFloat(e.target.value))}
                      className="w-full px-3 py-2 bg-gray-900 border border-gray-800 rounded text-sm text-white"
                      placeholder="0.00"
                    />
                  </div>

                  <div className="space-y-1">
                    <label className="text-xs text-gray-400 font-semibold">Upload Invoice File</label>
                    <input
                      type="file"
                      required
                      onChange={(e) => setUploadFile(e.target.files ? e.target.files[0] : null)}
                      className="w-full text-sm text-gray-400 file:mr-4 file:py-2 file:px-4 file:rounded file:border-0 file:text-xs file:font-semibold file:bg-gray-800 file:text-cyan-400 file:cursor-pointer cursor-pointer"
                    />
                  </div>

                  <div className="space-y-1">
                    <label className="text-xs text-gray-400 font-semibold">Notes</label>
                    <textarea
                      value={uploadNotes}
                      onChange={(e) => setUploadNotes(e.target.value)}
                      className="w-full px-3 py-2 bg-gray-900 border border-gray-800 rounded text-sm text-white h-20 resize-none focus:outline-none"
                      placeholder="Memo/notes..."
                    />
                  </div>

                  <button
                    type="submit"
                    disabled={!uploadFile || uploadConnId === 0}
                    className="w-full py-2.5 bg-gradient-to-r from-cyan-500 to-emerald-500 hover:from-cyan-600 hover:to-emerald-600 text-white font-semibold rounded text-sm transition-all disabled:opacity-50 disabled:cursor-not-allowed cursor-pointer"
                  >
                    Upload Statement
                  </button>
                </form>
              </div>

              {/* Bills List Ledger */}
              <div className="lg:col-span-2 glass-panel rounded-xl p-6 overflow-x-auto">
                <h3 className="text-lg font-bold text-gray-200 mb-6">Utility Statements Ledger</h3>
                {bills.length === 0 ? (
                  <p className="text-sm text-gray-500">No statement records found.</p>
                ) : (
                  <table className="w-full text-left border-collapse text-sm">
                    <thead>
                      <tr className="border-b border-gray-800 text-xs font-semibold text-gray-400 uppercase tracking-wider">
                        <th className="pb-3">Location</th>
                        <th className="pb-3">Provider</th>
                        <th className="pb-3">Dates</th>
                        <th className="pb-3 text-right">Amount</th>
                        <th className="pb-3 text-center">Status</th>
                        <th className="pb-3 text-right">Actions</th>
                      </tr>
                    </thead>
                    <tbody className="divide-y divide-gray-900">
                      {bills.map((b) => (
                        <tr key={b.id} className="hover:bg-gray-900/10">
                          <td className="py-4">
                            <div className="font-semibold text-gray-200">{b.store_name}</div>
                            <div className="text-xs text-gray-500">{b.city}</div>
                          </td>
                          <td className="py-4">
                            <div className="text-gray-300 font-semibold">{b.utility_type}</div>
                            <div className="text-xs text-gray-500">{b.provider_name}</div>
                          </td>
                          <td className="py-4">
                            <div className="text-xs text-gray-400">Due: <span className="font-mono">{b.due_date}</span></div>
                            <div className="text-xs text-gray-500">Stmt: <span className="font-mono">{b.statement_date}</span></div>
                          </td>
                          <td className="py-4 text-right font-semibold text-gray-200">
                            ${b.amount.toFixed(2)}
                          </td>
                          <td className="py-4 text-center">
                            <span className={`text-xs px-2 py-0.5 rounded-full border ${
                              b.status === "Paid" 
                                ? "bg-emerald-950/40 border-emerald-500/20 text-emerald-400"
                                : b.status === "Overdue"
                                ? "bg-red-950/40 border-red-500/20 text-red-400"
                                : "bg-yellow-950/40 border-yellow-500/20 text-yellow-400"
                            }`}>
                              {b.status}
                            </span>
                          </td>
                          <td className="py-4 text-right">
                            {b.status === "Paid" ? (
                              <div className="text-xs text-gray-500">
                                Paid via Ref: <span className="font-mono">{b.transaction_ref}</span>
                              </div>
                            ) : (
                              <button
                                onClick={() => handlePayBill(b.id)}
                                className="py-1 px-3 bg-cyan-600 hover:bg-cyan-500 text-white rounded text-xs font-semibold transition-colors cursor-pointer"
                              >
                                Mark Paid
                              </button>
                            )}
                          </td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                )}
              </div>
            </div>
          )}

          {activeSubTab === "connections" && (
            <div className="grid grid-cols-1 lg:grid-cols-3 gap-8">
              {/* Add Connection Form */}
              <div className="lg:col-span-1 glass-panel rounded-xl p-6 h-fit space-y-6">
                <h3 className="text-lg font-bold text-gray-200">Add Account Connection</h3>
                {connMessage && (
                  <div className="p-3 bg-cyan-950/40 border border-cyan-500/20 rounded-lg text-cyan-300 text-sm text-center">
                    {connMessage}
                  </div>
                )}
                <form onSubmit={handleCreateConnection} className="space-y-4">
                  <div className="space-y-1">
                    <label className="text-xs text-gray-400 font-semibold">Store Location</label>
                    <select
                      value={connStoreId}
                      onChange={(e) => setConnStoreId(parseInt(e.target.value))}
                      className="w-full px-3 py-2 bg-gray-900 border border-gray-800 rounded text-sm text-white focus:outline-none focus:ring-1 focus:ring-cyan-500"
                    >
                      {stores.map((s) => (
                        <option key={s.id} value={s.id}>
                          {s.store_name} ({s.city})
                        </option>
                      ))}
                    </select>
                  </div>

                  <div className="space-y-1">
                    <label className="text-xs text-gray-400 font-semibold">Utility Type</label>
                    <select
                      value={connType}
                      onChange={(e) => setConnType(e.target.value)}
                      className="w-full px-3 py-2 bg-gray-900 border border-gray-800 rounded text-sm text-white focus:outline-none"
                    >
                      <option value="Electricity">Electricity</option>
                      <option value="Gas">Gas</option>
                      <option value="Internet">Internet</option>
                      <option value="Telephone">Telephone</option>
                      <option value="Water">Water</option>
                      <option value="Sewer">Sewer</option>
                    </select>
                  </div>

                  <div className="space-y-1">
                    <label className="text-xs text-gray-400 font-semibold">Provider Company Name</label>
                    <input
                      required
                      value={connProvider}
                      onChange={(e) => setConnProvider(e.target.value)}
                      className="w-full px-3 py-2 bg-gray-900 border border-gray-800 rounded text-sm text-white"
                      placeholder="e.g. Dominion Energy"
                    />
                  </div>

                  <div className="space-y-1">
                    <label className="text-xs text-gray-400 font-semibold">Utility Account Number</label>
                    <input
                      required
                      value={connAcct}
                      onChange={(e) => setConnAcct(e.target.value)}
                      className="w-full px-3 py-2 bg-gray-900 border border-gray-800 rounded text-sm text-white"
                      placeholder="e.g. E-80434-VA"
                    />
                  </div>

                  <div className="space-y-1">
                    <label className="text-xs text-gray-400 font-semibold">Notes</label>
                    <textarea
                      value={connNotes}
                      onChange={(e) => setConnNotes(e.target.value)}
                      className="w-full px-3 py-2 bg-gray-900 border border-gray-800 rounded text-sm text-white h-20 resize-none focus:outline-none"
                      placeholder="E.g. Auto-pay details..."
                    />
                  </div>

                  <button
                    type="submit"
                    className="w-full py-2.5 bg-gradient-to-r from-cyan-500 to-emerald-500 hover:from-cyan-600 hover:to-emerald-600 text-white font-semibold rounded text-sm transition-all cursor-pointer"
                  >
                    Add Connection
                  </button>
                </form>
              </div>

              {/* Connections List */}
              <div className="lg:col-span-2 glass-panel rounded-xl p-6 overflow-x-auto">
                <h3 className="text-lg font-bold text-gray-200 mb-6">Active Account Connections</h3>
                {connections.length === 0 ? (
                  <p className="text-sm text-gray-500">No active connection accounts found.</p>
                ) : (
                  <table className="w-full text-left border-collapse text-sm">
                    <thead>
                      <tr className="border-b border-gray-800 text-xs font-semibold text-gray-400 uppercase tracking-wider">
                        <th className="pb-3">Location</th>
                        <th className="pb-3">Utility Type</th>
                        <th className="pb-3">Provider / Account</th>
                        <th className="pb-3">Notes</th>
                        <th className="pb-3 text-center">Status</th>
                      </tr>
                    </thead>
                    <tbody className="divide-y divide-gray-900">
                      {connections.map((c) => (
                        <tr key={c.id} className="hover:bg-gray-900/10">
                          <td className="py-4">
                            <div className="font-semibold text-gray-200">{c.store_name}</div>
                            <div className="text-xs text-gray-500">{c.city}</div>
                          </td>
                          <td className="py-4 font-semibold text-cyan-400 text-xs uppercase tracking-wide">
                            {c.utility_type}
                          </td>
                          <td className="py-4 font-mono text-xs">
                            <div className="text-gray-300 font-semibold font-sans">{c.provider_name}</div>
                            <div className="text-gray-500">{c.account_number}</div>
                          </td>
                          <td className="py-4 text-xs text-gray-400 max-w-xs truncate">
                            {c.notes || "--"}
                          </td>
                          <td className="py-4 text-center">
                            <span className="text-xs px-2 py-0.5 rounded-full border bg-emerald-950/40 border-emerald-500/20 text-emerald-400">
                              {c.status}
                            </span>
                          </td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                )}
              </div>
            </div>
          )}

          {activeSubTab === "yoy" && (
            <div className="glass-panel rounded-xl p-6 space-y-6">
              <div className="flex border-b border-gray-900 pb-4 justify-between items-center">
                <h3 className="text-lg font-bold text-gray-200">Year-over-Year Spend Comparison</h3>
                <div className="flex space-x-4 bg-gray-900/40 p-1 border border-gray-800/40 rounded-lg">
                  <button
                    onClick={() => setSelectedYoyUtility("electricity")}
                    className={`py-1 px-3 text-xs font-semibold rounded cursor-pointer transition-colors ${
                      selectedYoyUtility === "electricity" ? "bg-cyan-500/15 text-cyan-400" : "text-gray-400 hover:text-white"
                    }`}
                  >
                    Electricity Spend
                  </button>
                  <button
                    onClick={() => setSelectedYoyUtility("gas")}
                    className={`py-1 px-3 text-xs font-semibold rounded cursor-pointer transition-colors ${
                      selectedYoyUtility === "gas" ? "bg-cyan-500/15 text-cyan-400" : "text-gray-400 hover:text-white"
                    }`}
                  >
                    Natural Gas Spend
                  </button>
                </div>
              </div>
              
              {renderYoYGraph()}
            </div>
          )}
        </>
      )}
    </div>
  );
}
