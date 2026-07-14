"use client";

import { useEffect, useState } from "react";

interface Store {
  id: number;
  store_code: string;
  store_name: string;
  city: string;
}

interface Employee {
  id: number;
  store_id: number;
  name: string;
  email: string;
  phone: string;
  designation: string;
  hourly_rate: number;
  employment_type: string;
  store_name: string;
  city: string;
}

interface AttendanceLog {
  id: number;
  employee_id: number;
  employee_name: string;
  store_name: string;
  city: string;
  date: string;
  login_time: string;
  logout_time: string;
  calculated_hours: number;
  calculated_overtime: number;
  status: string;
}

interface PayrollSummary {
  employee_id: number;
  employee_name: string;
  designation: string;
  store_name: string;
  city: string;
  hourly_rate: number;
  total_regular_hours: number;
  total_overtime_hours: number;
  estimated_payout: number;
}

interface ParsedShift {
  employee_id: number;
  employee_name: string;
  date: string;
  login_time: string;
  logout_time: string;
  calculated_hours: number;
  calculated_overtime: number;
}

export default function AttendancePage() {
  const [stores, setStores] = useState<Store[]>([]);
  const [employees, setEmployees] = useState<Employee[]>([]);
  const [logs, setLogs] = useState<AttendanceLog[]>([]);
  const [payroll, setPayroll] = useState<PayrollSummary[]>([]);
  
  const [activeSubTab, setActiveSubTab] = useState<"employees" | "logs" | "ocr" | "payroll">("logs");
  const [loading, setLoading] = useState(true);
  
  // Form States (Employee Registration)
  const [empName, setEmpName] = useState("");
  const [empEmail, setEmpEmail] = useState("");
  const [empPhone, setEmpPhone] = useState("");
  const [empDesignation, setEmpDesignation] = useState("Sales Associate");
  const [empStoreId, setEmpStoreId] = useState<number>(0);
  const [empRate, setEmpRate] = useState<number>(15.00);
  const [empType, setEmpType] = useState("Full-time");
  
  // Form States (Manual Log)
  const [logEmpId, setLogEmpId] = useState<number>(0);
  const [logStoreId, setLogStoreId] = useState<number>(0);
  const [logDate, setLogDate] = useState("");
  const [logIn, setLogIn] = useState("09:00");
  const [logOut, setLogOut] = useState("17:00");
  const [logStatus, setLogStatus] = useState("Checked Out");
  const [logType, setLogType] = useState("Regular");

  // OCR Upload States
  const [ocrFile, setOcrFile] = useState<File | null>(null);
  const [ocrRawText, setOcrRawText] = useState("");
  const [ocrParsedShifts, setOcrParsedShifts] = useState<ParsedShift[]>([]);
  const [ocrScanning, setOcrScanning] = useState(false);

  const [message, setMessage] = useState("");
  const [logMessage, setLogMessage] = useState("");
  const [ocrMessage, setOcrMessage] = useState("");

  const fetchData = async () => {
    const token = localStorage.getItem("rworld_token");
    if (!token) return;
    setLoading(true);
    try {
      // Stores
      const storesResp = await fetch("http://localhost:8000/api/attendance/stores", {
        headers: { Authorization: `Bearer ${token}` },
      });
      if (storesResp.ok) {
        const storeData = await storesResp.json();
        setStores(storeData);
        if (storeData.length > 0) {
          setEmpStoreId(storeData[0].id);
          setLogStoreId(storeData[0].id);
        }
      }

      // Employees
      const empResp = await fetch("http://localhost:8000/api/attendance/employees", {
        headers: { Authorization: `Bearer ${token}` },
      });
      if (empResp.ok) {
        const empData = await empResp.json();
        setEmployees(empData);
        if (empData.length > 0) {
          setLogEmpId(empData[0].id);
        }
      }

      // Attendance Logs
      const logsResp = await fetch("http://localhost:8000/api/attendance/logs", {
        headers: { Authorization: `Bearer ${token}` },
      });
      if (logsResp.ok) {
        setLogs(await logsResp.json());
      }

      // Payroll
      const payrollResp = await fetch("http://localhost:8000/api/attendance/payroll", {
        headers: { Authorization: `Bearer ${token}` },
      });
      if (payrollResp.ok) {
        setPayroll(await payrollResp.json());
      }
    } catch (err) {
      console.error("Error loading attendance details:", err);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchData();
  }, []);

  const handleRegisterEmployee = async (e: React.FormEvent) => {
    e.preventDefault();
    setMessage("");
    const token = localStorage.getItem("rworld_token");
    try {
      const response = await fetch("http://localhost:8000/api/attendance/employees", {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
          Authorization: `Bearer ${token}`,
        },
        body: JSON.stringify({
          store_id: empStoreId,
          name: empName,
          email: empEmail,
          phone: empPhone,
          designation: empDesignation,
          hourly_rate: empRate,
          employment_type: empType,
        }),
      });

      const data = await response.json();
      if (!response.ok) throw new Error(data.detail || "Failed to register employee");

      setMessage("Employee registered successfully!");
      setEmpName("");
      setEmpEmail("");
      setEmpPhone("");
      fetchData();
    } catch (err: any) {
      setMessage(`Error: ${err.message}`);
    }
  };

  const handleAddManualLog = async (e: React.FormEvent) => {
    e.preventDefault();
    setLogMessage("");
    const token = localStorage.getItem("rworld_token");
    try {
      const response = await fetch("http://localhost:8000/api/attendance/logs/manual", {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
          Authorization: `Bearer ${token}`,
        },
        body: JSON.stringify({
          employee_id: logEmpId,
          store_id: logStoreId,
          date: logDate || new Date().toISOString().split("T")[0],
          login_time: logIn,
          logout_time: logOut,
          status: logStatus,
          log_type: logType,
        }),
      });

      const data = await response.json();
      if (!response.ok) throw new Error(data.detail || "Failed to add shift log");

      setLogMessage("Manual shift log registered successfully!");
      fetchData();
    } catch (err: any) {
      setLogMessage(`Error: ${err.message}`);
    }
  };

  const handleOCRScan = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!ocrFile) return;
    setOcrScanning(true);
    setOcrMessage("");
    setOcrRawText("");
    setOcrParsedShifts([]);

    const token = localStorage.getItem("rworld_token");
    const formData = new FormData();
    formData.append("file", ocrFile);

    try {
      const response = await fetch("http://localhost:8000/api/attendance/ocr", {
        method: "POST",
        headers: {
          Authorization: `Bearer ${token}`,
        },
        body: formData,
      });

      const data = await response.json();
      if (!response.ok) throw new Error(data.detail || "OCR scanning failed");

      setOcrRawText(data.raw_text);
      setOcrParsedShifts(data.parsed_logs);
      setOcrMessage("Timesheet scanned successfully! Review the shifts below.");
    } catch (err: any) {
      setOcrMessage(`OCR Scan Error: ${err.message}`);
    } finally {
      setOcrScanning(false);
    }
  };

  const handleImportOCRShifts = async () => {
    const token = localStorage.getItem("rworld_token");
    let successCount = 0;
    try {
      for (const shift of ocrParsedShifts) {
        const response = await fetch("http://localhost:8000/api/attendance/logs/manual", {
          method: "POST",
          headers: {
            "Content-Type": "application/json",
            Authorization: `Bearer ${token}`,
          },
          body: JSON.stringify({
            employee_id: shift.employee_id,
            store_id: empStoreId, // default to selected store in dropdown
            date: shift.date,
            login_time: shift.login_time,
            logout_time: shift.logout_time,
            status: "Checked Out",
            log_type: "Regular",
          }),
        });
        if (response.ok) {
          successCount++;
        }
      }
      setOcrMessage(`Successfully imported ${successCount} timesheet logs!`);
      setOcrParsedShifts([]);
      setOcrRawText("");
      setOcrFile(null);
      fetchData();
    } catch (err: any) {
      setOcrMessage(`Import Error: ${err.message}`);
    }
  };

  return (
    <div className="space-y-8">
      {/* Sub tabs selector */}
      <div className="flex border-b border-gray-900 space-x-6">
        <button
          onClick={() => setActiveSubTab("logs")}
          className={`pb-4 px-2 font-bold cursor-pointer text-sm transition-colors ${
            activeSubTab === "logs" ? "border-b-2 border-cyan-500 text-cyan-400" : "text-gray-400 hover:text-white"
          }`}
        >
          Daily Shift Logs
        </button>
        <button
          onClick={() => setActiveSubTab("employees")}
          className={`pb-4 px-2 font-bold cursor-pointer text-sm transition-colors ${
            activeSubTab === "employees" ? "border-b-2 border-cyan-500 text-cyan-400" : "text-gray-400 hover:text-white"
          }`}
        >
          Employee Directory
        </button>
        <button
          onClick={() => setActiveSubTab("ocr")}
          className={`pb-4 px-2 font-bold cursor-pointer text-sm transition-colors ${
            activeSubTab === "ocr" ? "border-b-2 border-cyan-500 text-cyan-400" : "text-gray-400 hover:text-white"
          }`}
        >
          AI Timesheet OCR Scanner
        </button>
        <button
          onClick={() => setActiveSubTab("payroll")}
          className={`pb-4 px-2 font-bold cursor-pointer text-sm transition-colors ${
            activeSubTab === "payroll" ? "border-b-2 border-cyan-500 text-cyan-400" : "text-gray-400 hover:text-white"
          }`}
        >
          Payroll & Earnings Projection
        </button>
      </div>

      {loading ? (
        <div className="text-center py-12">
          <div className="animate-spin rounded-full h-8 w-8 border-t-2 border-b-2 border-cyan-500 mx-auto"></div>
          <p className="mt-4 text-gray-500 text-sm">Loading attendance modules...</p>
        </div>
      ) : (
        <>
          {activeSubTab === "logs" && (
            <div className="grid grid-cols-1 lg:grid-cols-3 gap-8">
              {/* Form Manual Log */}
              <div className="lg:col-span-1 glass-panel rounded-xl p-6 h-fit space-y-6">
                <h3 className="text-lg font-bold text-gray-200">Register Shift Log</h3>
                {logMessage && (
                  <div className="p-3 bg-cyan-950/40 border border-cyan-500/20 rounded-lg text-cyan-300 text-sm text-center">
                    {logMessage}
                  </div>
                )}
                <form onSubmit={handleAddManualLog} className="space-y-4">
                  <div className="space-y-1">
                    <label className="text-xs text-gray-400 font-semibold">Select Employee</label>
                    <select
                      value={logEmpId}
                      onChange={(e) => setLogEmpId(parseInt(e.target.value))}
                      className="w-full px-3 py-2 bg-gray-900 border border-gray-800 rounded text-sm text-white focus:outline-none focus:ring-1 focus:ring-cyan-500"
                    >
                      {employees.map((e) => (
                        <option key={e.id} value={e.id}>
                          {e.name} ({e.designation})
                        </option>
                      ))}
                    </select>
                  </div>

                  <div className="space-y-1">
                    <label className="text-xs text-gray-400 font-semibold">Shift Store Location</label>
                    <select
                      value={logStoreId}
                      onChange={(e) => setLogStoreId(parseInt(e.target.value))}
                      className="w-full px-3 py-2 bg-gray-900 border border-gray-800 rounded text-sm text-white focus:outline-none focus:ring-1 focus:ring-cyan-500"
                    >
                      {stores.map((s) => (
                        <option key={s.id} value={s.id}>
                          {s.store_name} - {s.city} (Code: {s.store_code})
                        </option>
                      ))}
                    </select>
                  </div>

                  <div className="space-y-1">
                    <label className="text-xs text-gray-400 font-semibold">Date</label>
                    <input
                      type="date"
                      value={logDate}
                      onChange={(e) => setLogDate(e.target.value)}
                      className="w-full px-3 py-2 bg-gray-900 border border-gray-800 rounded text-sm text-white"
                    />
                  </div>

                  <div className="grid grid-cols-2 gap-4">
                    <div className="space-y-1">
                      <label className="text-xs text-gray-400 font-semibold">Clock-In Time</label>
                      <input
                        type="time"
                        value={logIn}
                        onChange={(e) => setLogIn(e.target.value)}
                        className="w-full px-3 py-2 bg-gray-900 border border-gray-800 rounded text-sm text-white"
                      />
                    </div>
                    <div className="space-y-1">
                      <label className="text-xs text-gray-400 font-semibold">Clock-Out Time</label>
                      <input
                        type="time"
                        value={logOut}
                        onChange={(e) => setLogOut(e.target.value)}
                        className="w-full px-3 py-2 bg-gray-900 border border-gray-800 rounded text-sm text-white"
                      />
                    </div>
                  </div>

                  <div className="grid grid-cols-2 gap-4">
                    <div className="space-y-1">
                      <label className="text-xs text-gray-400 font-semibold">Status</label>
                      <select
                        value={logStatus}
                        onChange={(e) => setLogStatus(e.target.value)}
                        className="w-full px-3 py-2 bg-gray-900 border border-gray-800 rounded text-sm text-white focus:outline-none"
                      >
                        <option value="Checked In">Checked In</option>
                        <option value="Checked Out">Checked Out</option>
                        <option value="On Break">On Break</option>
                      </select>
                    </div>
                    <div className="space-y-1">
                      <label className="text-xs text-gray-400 font-semibold">Log Type</label>
                      <select
                        value={logType}
                        onChange={(e) => setLogType(e.target.value)}
                        className="w-full px-3 py-2 bg-gray-900 border border-gray-800 rounded text-sm text-white focus:outline-none"
                      >
                        <option value="Regular">Regular Shift</option>
                        <option value="Holiday">Holiday Shift</option>
                        <option value="Overtime">Overtime Only</option>
                      </select>
                    </div>
                  </div>

                  <button
                    type="submit"
                    className="w-full py-2.5 bg-gradient-to-r from-cyan-500 to-emerald-500 hover:from-cyan-600 hover:to-emerald-600 text-white font-semibold rounded text-sm transition-all cursor-pointer"
                  >
                    Submit Shift Log
                  </button>
                </form>
              </div>

              {/* Logs Table */}
              <div className="lg:col-span-2 glass-panel rounded-xl p-6 overflow-x-auto">
                <h3 className="text-lg font-bold text-gray-200 mb-6">Recent Shift Activity Logs</h3>
                {logs.length === 0 ? (
                  <p className="text-sm text-gray-500">No shift logs registered. Add shifts to display history.</p>
                ) : (
                  <table className="w-full text-left border-collapse">
                    <thead>
                      <tr className="border-b border-gray-800 text-xs font-semibold text-gray-400 uppercase tracking-wider">
                        <th className="pb-3">Employee</th>
                        <th className="pb-3">Location</th>
                        <th className="pb-3">Date</th>
                        <th className="pb-3">Check-In/Out</th>
                        <th className="pb-3 text-right">Hours</th>
                        <th className="pb-3 text-right">OT</th>
                      </tr>
                    </thead>
                    <tbody className="divide-y divide-gray-900 text-sm">
                      {logs.map((l) => (
                        <tr key={l.id} className="hover:bg-gray-900/10">
                          <td className="py-4 font-semibold text-gray-200">{l.employee_name}</td>
                          <td className="py-4 text-gray-400">{l.store_name} ({l.city})</td>
                          <td className="py-4 text-gray-300 font-mono text-xs">{l.date}</td>
                          <td className="py-4 font-mono text-xs text-cyan-400">
                            {l.login_time} - {l.logout_time || "--:--"}
                          </td>
                          <td className="py-4 text-right text-gray-200">{l.calculated_hours.toFixed(2)} hrs</td>
                          <td className="py-4 text-right text-emerald-400">{l.calculated_overtime.toFixed(2)} hrs</td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                )}
              </div>
            </div>
          )}

          {activeSubTab === "employees" && (
            <div className="grid grid-cols-1 lg:grid-cols-3 gap-8">
              {/* Register Employee Form */}
              <div className="lg:col-span-1 glass-panel rounded-xl p-6 h-fit space-y-6">
                <h3 className="text-lg font-bold text-gray-200">Register Store Employee</h3>
                {message && (
                  <div className="p-3 bg-cyan-950/40 border border-cyan-500/20 rounded-lg text-cyan-300 text-sm text-center">
                    {message}
                  </div>
                )}
                <form onSubmit={handleRegisterEmployee} className="space-y-4">
                  <div className="space-y-1">
                    <label className="text-xs text-gray-400 font-semibold">Employee Name</label>
                    <input
                      required
                      value={empName}
                      onChange={(e) => setEmpName(e.target.value)}
                      className="w-full px-3 py-2 bg-gray-900 border border-gray-800 rounded text-sm text-white"
                      placeholder="e.g. Michael Scott"
                    />
                  </div>

                  <div className="space-y-1">
                    <label className="text-xs text-gray-400 font-semibold">Email Address</label>
                    <input
                      type="email"
                      required
                      value={empEmail}
                      onChange={(e) => setEmpEmail(e.target.value)}
                      className="w-full px-3 py-2 bg-gray-900 border border-gray-800 rounded text-sm text-white"
                      placeholder="e.g. michael@artee.com"
                    />
                  </div>

                  <div className="space-y-1">
                    <label className="text-xs text-gray-400 font-semibold">Phone Number</label>
                    <input
                      required
                      value={empPhone}
                      onChange={(e) => setEmpPhone(e.target.value)}
                      className="w-full px-3 py-2 bg-gray-900 border border-gray-800 rounded text-sm text-white"
                      placeholder="e.g. 555-0199"
                    />
                  </div>

                  <div className="grid grid-cols-2 gap-4">
                    <div className="space-y-1">
                      <label className="text-xs text-gray-400 font-semibold">Hourly Rate ($)</label>
                      <input
                        type="number"
                        step="0.50"
                        required
                        value={empRate}
                        onChange={(e) => setEmpRate(parseFloat(e.target.value))}
                        className="w-full px-3 py-2 bg-gray-900 border border-gray-800 rounded text-sm text-white"
                      />
                    </div>
                    <div className="space-y-1">
                      <label className="text-xs text-gray-400 font-semibold">Emp Type</label>
                      <select
                        value={empType}
                        onChange={(e) => setEmpType(e.target.value)}
                        className="w-full px-3 py-2 bg-gray-900 border border-gray-800 rounded text-sm text-white focus:outline-none"
                      >
                        <option value="Full-time">Full-time</option>
                        <option value="Part-time">Part-time</option>
                        <option value="Contract">Contract</option>
                      </select>
                    </div>
                  </div>

                  <div className="space-y-1">
                    <label className="text-xs text-gray-400 font-semibold">Designation</label>
                    <select
                      value={empDesignation}
                      onChange={(e) => setEmpDesignation(e.target.value)}
                      className="w-full px-3 py-2 bg-gray-900 border border-gray-800 rounded text-sm text-white focus:outline-none"
                    >
                      <option value="Store Manager">Store Manager</option>
                      <option value="Sales Associate">Sales Associate</option>
                      <option value="Warehouse Clerk">Warehouse Clerk</option>
                      <option value="Cashier">Cashier</option>
                    </select>
                  </div>

                  <div className="space-y-1">
                    <label className="text-xs text-gray-400 font-semibold">Assign to Store Location</label>
                    <select
                      value={empStoreId}
                      onChange={(e) => setEmpStoreId(parseInt(e.target.value))}
                      className="w-full px-3 py-2 bg-gray-900 border border-gray-800 rounded text-sm text-white focus:outline-none focus:ring-1 focus:ring-cyan-500"
                    >
                      {stores.map((s) => (
                        <option key={s.id} value={s.id}>
                          {s.store_name} ({s.city})
                        </option>
                      ))}
                    </select>
                  </div>

                  <button
                    type="submit"
                    className="w-full py-2.5 bg-gradient-to-r from-cyan-500 to-emerald-500 hover:from-cyan-600 hover:to-emerald-600 text-white font-semibold rounded text-sm transition-all cursor-pointer"
                  >
                    Register Employee
                  </button>
                </form>
              </div>

              {/* Employee Directory List */}
              <div className="lg:col-span-2 glass-panel rounded-xl p-6 overflow-x-auto">
                <h3 className="text-lg font-bold text-gray-200 mb-6">Artee Store Employees</h3>
                {employees.length === 0 ? (
                  <p className="text-sm text-gray-500">No employees registered yet.</p>
                ) : (
                  <table className="w-full text-left border-collapse">
                    <thead>
                      <tr className="border-b border-gray-800 text-xs font-semibold text-gray-400 uppercase tracking-wider">
                        <th className="pb-3">Name</th>
                        <th className="pb-3">Contacts</th>
                        <th className="pb-3">Role</th>
                        <th className="pb-3">Store Location</th>
                        <th className="pb-3 text-right">Hourly Rate</th>
                      </tr>
                    </thead>
                    <tbody className="divide-y divide-gray-900 text-sm">
                      {employees.map((e) => (
                        <tr key={e.id} className="hover:bg-gray-900/10">
                          <td className="py-4">
                            <div className="font-semibold text-gray-200">{e.name}</div>
                            <div className="text-xs text-gray-500 font-mono">{e.employment_type}</div>
                          </td>
                          <td className="py-4">
                            <div className="text-gray-300 font-mono text-xs">{e.email}</div>
                            <div className="text-xs text-gray-500">{e.phone}</div>
                          </td>
                          <td className="py-4 font-semibold text-cyan-400 text-xs uppercase tracking-wide">
                            {e.designation}
                          </td>
                          <td className="py-4 text-gray-400">
                            {e.store_name} ({e.city})
                          </td>
                          <td className="py-4 text-right font-semibold text-emerald-400">${e.hourly_rate.toFixed(2)}/hr</td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                )}
              </div>
            </div>
          )}

          {activeSubTab === "ocr" && (
            <div className="grid grid-cols-1 lg:grid-cols-3 gap-8">
              {/* OCR Uploader Form */}
              <div className="lg:col-span-1 glass-panel rounded-xl p-6 h-fit space-y-6">
                <h3 className="text-lg font-bold text-gray-200">AI Timesheet OCR Processor</h3>
                <p className="text-xs text-gray-400 leading-relaxed">
                  Upload a photo or scanned PDF of a handwritten or printed weekly employee timesheet. The platform will run local character recognition to match store employee listings and parse clock-in/out hours.
                </p>
                {ocrMessage && (
                  <div className="p-3 bg-cyan-950/40 border border-cyan-500/20 rounded-lg text-cyan-300 text-sm text-center">
                    {ocrMessage}
                  </div>
                )}
                <form onSubmit={handleOCRScan} className="space-y-4">
                  <div className="space-y-1">
                    <label className="text-xs text-gray-400 font-semibold">Select Timesheet Document</label>
                    <input
                      type="file"
                      required
                      accept="image/*,application/pdf"
                      onChange={(e) => setOcrFile(e.target.files ? e.target.files[0] : null)}
                      className="w-full text-sm text-gray-400 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-xs file:font-semibold file:bg-gray-800 file:text-cyan-400 file:cursor-pointer cursor-pointer"
                    />
                  </div>

                  <div className="space-y-1">
                    <label className="text-xs text-gray-400 font-semibold">Default Store Location</label>
                    <select
                      value={empStoreId}
                      onChange={(e) => setEmpStoreId(parseInt(e.target.value))}
                      className="w-full px-3 py-2 bg-gray-900 border border-gray-800 rounded text-sm text-white focus:outline-none focus:ring-1 focus:ring-cyan-500"
                    >
                      {stores.map((s) => (
                        <option key={s.id} value={s.id}>
                          {s.store_name} ({s.city})
                        </option>
                      ))}
                    </select>
                  </div>

                  <button
                    type="submit"
                    disabled={ocrScanning || !ocrFile}
                    className="w-full py-2.5 bg-gradient-to-r from-cyan-500 to-emerald-500 hover:from-cyan-600 hover:to-emerald-600 text-white font-semibold rounded text-sm transition-all disabled:opacity-50 disabled:cursor-not-allowed cursor-pointer"
                  >
                    {ocrScanning ? "Running OCR Analysis..." : "Process Timesheet Document"}
                  </button>
                </form>
              </div>

              {/* OCR Parsing Preview */}
              <div className="lg:col-span-2 space-y-6">
                {ocrRawText && (
                  <div className="glass-panel rounded-xl p-6 space-y-4">
                    <h4 className="text-sm font-bold text-gray-300">Raw Extracted Text Stream</h4>
                    <pre className="p-4 bg-gray-950 border border-gray-900 rounded font-mono text-xs text-gray-400 overflow-x-auto whitespace-pre">
                      {ocrRawText}
                    </pre>
                  </div>
                )}

                {ocrParsedShifts.length > 0 && (
                  <div className="glass-panel rounded-xl p-6 space-y-6">
                    <div className="flex items-center justify-between">
                      <h4 className="text-sm font-bold text-gray-200">Parsed Shift Log Matches</h4>
                      <button
                        onClick={handleImportOCRShifts}
                        className="py-1.5 px-3 bg-emerald-500 hover:bg-emerald-600 text-white font-semibold rounded text-xs transition-colors cursor-pointer"
                      >
                        Save & Import Shifts
                      </button>
                    </div>
                    <div className="overflow-x-auto">
                      <table className="w-full text-left border-collapse">
                        <thead>
                          <tr className="border-b border-gray-800 text-xs font-semibold text-gray-400 uppercase">
                            <th className="pb-3">Matched Employee</th>
                            <th className="pb-3">Date</th>
                            <th className="pb-3">Clock In/Out</th>
                            <th className="pb-3 text-right">Regular Hrs</th>
                            <th className="pb-3 text-right">Overtime Hrs</th>
                          </tr>
                        </thead>
                        <tbody className="divide-y divide-gray-900 text-sm">
                          {ocrParsedShifts.map((s, idx) => (
                            <tr key={idx} className="hover:bg-gray-900/10">
                              <td className="py-3 font-semibold text-gray-200">{s.employee_name}</td>
                              <td className="py-3 font-mono text-xs text-gray-300">{s.date}</td>
                              <td className="py-3 font-mono text-xs text-cyan-400">
                                {s.login_time} - {s.logout_time}
                              </td>
                              <td className="py-3 text-right text-gray-300">{s.calculated_hours.toFixed(2)}</td>
                              <td className="py-3 text-right text-emerald-400">{s.calculated_overtime.toFixed(2)}</td>
                            </tr>
                          ))}
                        </tbody>
                      </table>
                    </div>
                  </div>
                )}

                {!ocrRawText && !ocrScanning && (
                  <div className="glass-panel rounded-xl p-12 text-center text-gray-500">
                    <span className="text-3xl">📄</span>
                    <p className="mt-4 text-sm">Upload a weekly retail timesheet sheet to scan with OCR and extract details.</p>
                  </div>
                )}
              </div>
            </div>
          )}

          {activeSubTab === "payroll" && (
            <div className="glass-panel rounded-xl p-6 space-y-6">
              <div className="flex items-center justify-between border-b border-gray-900 pb-4">
                <h3 className="text-lg font-bold text-gray-200">Weekly Labor Expense Estimates</h3>
                <div className="text-right">
                  <div className="text-xs text-gray-400">Total Projected Payroll</div>
                  <div className="text-2xl font-extrabold text-gradient-cyan">
                    ${payroll.reduce((acc, curr) => acc + curr.estimated_payout, 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}
                  </div>
                </div>
              </div>

              <div className="overflow-x-auto">
                <table className="w-full text-left border-collapse">
                  <thead>
                    <tr className="border-b border-gray-800 text-xs font-semibold text-gray-400 uppercase tracking-wider">
                      <th className="pb-3">Employee</th>
                      <th className="pb-3">Store Location</th>
                      <th className="pb-3 text-right">Regular Hours</th>
                      <th className="pb-3 text-right">Overtime Hours</th>
                      <th className="pb-3 text-right">Hourly Rate</th>
                      <th className="pb-3 text-right">Estimated Payout</th>
                    </tr>
                  </thead>
                  <tbody className="divide-y divide-gray-900 text-sm">
                    {payroll.map((p) => (
                      <tr key={p.employee_id} className="hover:bg-gray-900/10">
                        <td className="py-4">
                          <div className="font-semibold text-gray-200">{p.employee_name}</div>
                          <div className="text-xs text-gray-500">{p.designation}</div>
                        </td>
                        <td className="py-4 text-gray-400">
                          {p.store_name} ({p.city})
                        </td>
                        <td className="py-4 text-right text-gray-300">{p.total_regular_hours.toFixed(2)} hrs</td>
                        <td className="py-4 text-right text-emerald-400">{p.total_overtime_hours.toFixed(2)} hrs</td>
                        <td className="py-4 text-right text-gray-400">${p.hourly_rate.toFixed(2)}/hr</td>
                        <td className="py-4 text-right font-bold text-cyan-300">
                          ${p.estimated_payout.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            </div>
          )}
        </>
      )}
    </div>
  );
}
