"use client";

import { useEffect, useState } from "react";

interface ERPSummary {
  total_orders: number;
  total_revenue: number;
  total_stock: number;
}

interface ShippingJob {
  id: number;
  email_subject: string;
  sender: string;
  status: string;
  created_at: string;
}

export default function DashboardPage() {
  const [summary, setSummary] = useState<ERPSummary | null>(null);
  const [shippingJobs, setShippingJobs] = useState<ShippingJob[]>([]);
  const [employeeCount, setEmployeeCount] = useState<number>(0);
  const [totalBillsAmt, setTotalBillsAmt] = useState<number>(0.0);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    const fetchData = async () => {
      const token = localStorage.getItem("rworld_token");
      if (!token) return;

      try {
        // 1. Fetch ERP summary reports
        const erpResponse = await fetch("${process.env.NEXT_PUBLIC_API_URL || 'http://localhost:8000'}/api/erp/reports/summary", {
          headers: { Authorization: `Bearer ${token}` },
        });
        if (erpResponse.ok) {
          const erpData = await erpResponse.json();
          setSummary(erpData);
        }

        // 2. Fetch Shipping agent jobs
        const shippingResponse = await fetch("${process.env.NEXT_PUBLIC_API_URL || 'http://localhost:8000'}/api/shipping-agent/jobs", {
          headers: { Authorization: `Bearer ${token}` },
        });
        if (shippingResponse.ok) {
          const shippingData = await shippingResponse.json();
          setShippingJobs(shippingData.slice(0, 5)); // show latest 5
        }

        // 3. Fetch Attendance employees
        const attResponse = await fetch("${process.env.NEXT_PUBLIC_API_URL || 'http://localhost:8000'}/api/attendance/employees", {
          headers: { Authorization: `Bearer ${token}` },
        });
        if (attResponse.ok) {
          const empData = await attResponse.json();
          setEmployeeCount(empData.length);
        }

        // 4. Fetch Utility bills spend summary
        const utilResponse = await fetch("${process.env.NEXT_PUBLIC_API_URL || 'http://localhost:8000'}/api/utility/bills", {
          headers: { Authorization: `Bearer ${token}` },
        });
        if (utilResponse.ok) {
          const billData = await utilResponse.json();
          const totalAmt = billData.reduce((acc: number, curr: any) => acc + curr.amount, 0.0);
          setTotalBillsAmt(totalAmt);
        }

      } catch (err) {
        console.error("Error loading dashboard data:", err);
      } finally {
        setLoading(false);
      }
    };

    fetchData();
  }, []);

  return (
    <div className="space-y-8 font-sans">
      {/* Metric Cards Grid */}
      <div className="grid grid-cols-1 md:grid-cols-4 gap-6">
        <div className="glass-panel-glow rounded-xl p-6 hover-scale">
          <div className="text-sm font-semibold text-gray-400">Total ERP Sales Revenue</div>
          <div className="text-3xl font-extrabold mt-2 text-gradient-cyan">
            ${summary?.total_revenue.toLocaleString(undefined, { minimumFractionDigits: 2 }) ?? "0.00"}
          </div>
          <div className="text-xs text-emerald-400 mt-2">↑ 12% increase from last week</div>
        </div>

        <div className="glass-panel rounded-xl p-6 hover-scale">
          <div className="text-sm font-semibold text-gray-400">Fulfillment Sales Orders</div>
          <div className="text-3xl font-extrabold mt-2 text-white">
            {summary?.total_orders ?? 0}
          </div>
          <div className="text-xs text-gray-500 mt-2">Active store sales invoices logged</div>
        </div>

        <div className="glass-panel rounded-xl p-6 hover-scale">
          <div className="text-sm font-semibold text-gray-400">Store Staff Registry</div>
          <div className="text-3xl font-extrabold mt-2 text-white">
            {employeeCount} Employees
          </div>
          <div className="text-xs text-cyan-400 mt-2">Active staff accounts registered</div>
        </div>

        <div className="glass-panel rounded-xl p-6 hover-scale">
          <div className="text-sm font-semibold text-gray-400">Total Utility Operations Spend</div>
          <div className="text-3xl font-extrabold mt-2 text-white">
            ${totalBillsAmt.toLocaleString(undefined, { minimumFractionDigits: 2 })}
          </div>
          <div className="text-xs text-yellow-400 mt-2">Electricity & gas spend recorded</div>
        </div>
      </div>

      {/* Main Split Layout */}
      <div className="grid grid-cols-1 lg:grid-cols-3 gap-8">
        {/* Module Health Check */}
        <div className="lg:col-span-1 glass-panel rounded-xl p-6 flex flex-col space-y-6">
          <h3 className="text-lg font-bold text-gray-200">RWorld Platform Modules</h3>
          
          <div className="space-y-3 flex-1">
            <div className="flex items-center justify-between p-3 bg-gray-900/40 rounded-lg border border-gray-800/40">
              <div className="flex items-center space-x-3">
                <span className="text-xl">📦</span>
                <span className="text-sm font-semibold text-gray-300">Artee ERP</span>
              </div>
              <span className="text-xs px-2 py-0.5 bg-emerald-950/40 border border-emerald-500/20 text-emerald-400 rounded-full font-semibold">
                Active
              </span>
            </div>

            <div className="flex items-center justify-between p-3 bg-gray-900/40 rounded-lg border border-gray-800/40">
              <div className="flex items-center space-x-3">
                <span className="text-xl">✈️</span>
                <span className="text-sm font-semibold text-gray-300">AI Shipping Agent</span>
              </div>
              <span className="text-xs px-2 py-0.5 bg-emerald-950/40 border border-emerald-500/20 text-emerald-400 rounded-full font-semibold">
                Active
              </span>
            </div>

            <div className="flex items-center justify-between p-3 bg-gray-900/40 rounded-lg border border-gray-800/40">
              <div className="flex items-center space-x-3">
                <span className="text-xl">🌐</span>
                <span className="text-sm font-semibold text-gray-300">Fabric Scraper</span>
              </div>
              <span className="text-xs px-2 py-0.5 bg-emerald-950/40 border border-emerald-500/20 text-emerald-400 rounded-full font-semibold">
                Active
              </span>
            </div>

            <div className="flex items-center justify-between p-3 bg-gray-900/40 rounded-lg border border-gray-800/40">
              <div className="flex items-center space-x-3">
                <span className="text-xl">🏠</span>
                <span className="text-sm font-semibold text-gray-300">RoofIQ AI</span>
              </div>
              <span className="text-xs px-2 py-0.5 bg-emerald-950/40 border border-emerald-500/20 text-emerald-400 rounded-full font-semibold">
                Active
              </span>
            </div>

            <div className="flex items-center justify-between p-3 bg-gray-900/40 rounded-lg border border-gray-800/40">
              <div className="flex items-center space-x-3">
                <span className="text-xl">🕒</span>
                <span className="text-sm font-semibold text-gray-300">Attendance Ops</span>
              </div>
              <span className="text-xs px-2 py-0.5 bg-emerald-950/40 border border-emerald-500/20 text-emerald-400 rounded-full font-semibold">
                Active
              </span>
            </div>

            <div className="flex items-center justify-between p-3 bg-gray-900/40 rounded-lg border border-gray-800/40">
              <div className="flex items-center space-x-3">
                <span className="text-xl">⚡</span>
                <span className="text-sm font-semibold text-gray-300">Utility Billing</span>
              </div>
              <span className="text-xs px-2 py-0.5 bg-emerald-950/40 border border-emerald-500/20 text-emerald-400 rounded-full font-semibold">
                Active
              </span>
            </div>
          </div>
        </div>

        {/* Recent Logistics Jobs */}
        <div className="lg:col-span-2 glass-panel rounded-xl p-6 flex flex-col">
          <h3 className="text-lg font-bold text-gray-200 mb-6">Recent Ingested Logistics Jobs</h3>
          
          <div className="flex-1 overflow-x-auto">
            {loading ? (
              <p className="text-sm text-gray-500">Loading operations stream...</p>
            ) : shippingJobs.length === 0 ? (
              <p className="text-sm text-gray-500">No jobs processed yet. Send shipping emails to trigger ingestion.</p>
            ) : (
              <table className="w-full text-left border-collapse">
                <thead>
                  <tr className="border-b border-gray-800 text-xs font-semibold text-gray-400 uppercase tracking-wider">
                    <th className="pb-3">Job ID</th>
                    <th className="pb-3">Subject / Document</th>
                    <th className="pb-3">Sender</th>
                    <th className="pb-3">Status</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-gray-900 text-sm">
                  {shippingJobs.map((job) => (
                    <tr key={job.id} className="hover:bg-gray-900/20 transition-colors">
                      <td className="py-4 font-mono text-xs text-gray-500">#{job.id}</td>
                      <td className="py-4 font-semibold text-gray-200 max-w-xs truncate">
                        {job.email_subject}
                      </td>
                      <td className="py-4 text-gray-400">{job.sender}</td>
                      <td className="py-4">
                        <span className={`text-xs px-2 py-1 rounded-full border ${
                          job.status === "completed" 
                            ? "bg-emerald-950/40 border-emerald-500/20 text-emerald-400"
                            : "bg-cyan-950/40 border-cyan-500/20 text-cyan-400"
                        }`}>
                          {job.status}
                        </span>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            )}
          </div>
        </div>
      </div>
    </div>
  );
}
