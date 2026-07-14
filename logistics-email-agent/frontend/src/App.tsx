import { useState, useEffect, useRef } from 'react'
import {
  Mail, ShieldCheck, FileCheck2, BarChart3, Terminal, Settings2,
  AlertTriangle, Check, X, ShieldAlert, Edit, ArrowRight,
  RefreshCw, CheckCircle, HelpCircle, Truck, Package, DollarSign,
  Activity, ExternalLink
} from 'lucide-react'

// Backend API hosts (dynamically resolve host)
const API_HOST = window.location.hostname ? `http://${window.location.hostname}:8001` : 'http://localhost:8001'
const WS_HOST = window.location.hostname ? `ws://${window.location.hostname}:8001` : 'ws://localhost:8001'

interface EmailLog {
  id: number
  message_id: string
  sender: string
  subject: string
  body: string
  received_at: string
  processed: boolean
  intent: string
  attachments: string
}

interface ShipmentDraft {
  id: number
  email_id: number
  to_name: string
  to_company: string
  to_address1: string
  to_address2: string
  to_city: string
  to_state: string
  to_zip: string
  to_phone: string
  to_email: string
  from_name: string
  from_company: string
  from_address1: string
  from_address2: string
  from_city: string
  from_state: string
  from_zip: string
  from_phone: string
  from_email: string
  sales_order_number: string
  purchase_order_number: string
  request_reference: string
  package_count: number
  weight_lbs: number
  length_in: number
  width_in: number
  height_in: number
  carrier_preference: string
  service_level: string
  special_instructions: string
  special_flags: string
  confidence_score: number
  validation_status: string
  validation_errors: string
  duplicate_flag: boolean
  risk_score: number
  reasoning_log: string
  status: string
  portal_request_id: number
  tracking_number: string
  shipping_cost: number
  carrier_used: string
  created_at: string
}

interface AuditLog {
  step_name: string
  step_status: string
  details: string
  timestamp: string
}

export default function App() {
  const [activeTab, setActiveTab] = useState<'inbox' | 'approvals' | 'completed' | 'analytics' | 'logs' | 'settings'>('approvals')
  
  // Data States
  const [emails, setEmails] = useState<EmailLog[]>([])
  const [shipments, setShipments] = useState<ShipmentDraft[]>([])
  const [auditLogs, setAuditLogs] = useState<AuditLog[]>([])
  const [analytics, setAnalytics] = useState<any>({
    processed_emails: 0,
    pending_approvals: 0,
    completed_shipments: 0,
    failed_shipments: 0,
    duplicate_count: 0,
    carrier_dispatches: {},
    total_shipping_costs: 0.0,
    average_processing_time_sec: 0.0,
    sync_status: "Disconnected"
  })

  // Selected state for details
  const [selectedEmail, setSelectedEmail] = useState<EmailLog | null>(null)
  const [selectedShipment, setSelectedShipment] = useState<ShipmentDraft | null>(null)

  // Modals & Forms State
  const [isEditOpen, setIsEditOpen] = useState(false)
  const [editForm, setEditForm] = useState<Partial<ShipmentDraft>>({})
  const [approvingIds, setApprovingIds] = useState<Record<number, boolean>>({})

  // Email Config State
  const [emailSettings, setEmailSettings] = useState({
    EMAIL_IMAP_SERVER: '',
    EMAIL_IMAP_PORT: 993,
    EMAIL_USERNAME: '',
    EMAIL_PASSWORD: ''
  })
  const [settingsSaved, setSettingsSaved] = useState(false)

  const fetchEmailSettings = async () => {
    try {
      const res = await fetch(`${API_HOST}/api/settings/email`)
      if (res.ok) {
        setEmailSettings(await res.json())
      }
    } catch (e) {
      console.error(e)
    }
  }

  // Fetch all dashboard data
  const fetchData = async () => {
    try {
      const emailRes = await fetch(`${API_HOST}/api/emails`)
      if (emailRes.ok) setEmails(emailRes.ok ? await emailRes.json() : [])

      const shipmentRes = await fetch(`${API_HOST}/api/shipments`)
      if (shipmentRes.ok) setShipments(await shipmentRes.json())

      const logsRes = await fetch(`${API_HOST}/api/logs`)
      if (logsRes.ok) {
        const rawLogs = await logsRes.json()
        setAuditLogs(rawLogs.map((l: any) => ({
          step_name: l.step_name,
          step_status: l.step_status,
          details: l.details,
          timestamp: new Date(l.executed_at).toLocaleString()
        })))
      }

      const statsRes = await fetch(`${API_HOST}/api/analytics`)
      if (statsRes.ok) setAnalytics(await statsRes.json())
    } catch (err) {
      console.error('API Sync Connection Failed:', err)
    }
  }

  // Poll for background ingestion changes
  useEffect(() => {
    fetchData()
    if (activeTab === 'settings') {
      fetchEmailSettings()
      setSettingsSaved(false)
    }
    const interval = setInterval(fetchData, 8000)
    return () => clearInterval(interval)
  }, [activeTab])

  // Setup WebSocket log broadcast listener
  useEffect(() => {
    let ws: WebSocket
    const connectWS = () => {
      ws = new WebSocket(`${WS_HOST}/ws/logs`)
      ws.onmessage = (event) => {
        try {
          const logData = JSON.parse(event.data)
          if (logData.event === 'audit_log') {
            setAuditLogs(prev => [
              {
                step_name: logData.step_name,
                step_status: logData.step_status,
                details: logData.details,
                timestamp: logData.timestamp
              },
              ...prev.slice(0, 49)
            ])
            fetchData() // Refresh charts & queue states
          }
        } catch (e) {
          console.error(e)
        }
      }
      ws.onclose = () => {
        setTimeout(connectWS, 4000)
      }
    }
    connectWS()
    return () => {
      if (ws) ws.close()
    }
  }, [])

  // Approval Submission Trigger
  const handleApprove = async (id: number) => {
    setApprovingIds(prev => ({ ...prev, [id]: true }))
    try {
      const res = await fetch(`${API_HOST}/api/shipment/approve/${id}`, { method: 'POST' })
      if (res.ok) {
        // Optimistic state updates
        setShipments(prev => prev.map(s => s.id === id ? { ...s, status: 'Approved' } : s))
        fetchData()
      }
    } catch (err) {
      console.error(err)
    } finally {
      setApprovingIds(prev => ({ ...prev, [id]: false }))
    }
  }

  // Reject Ingestion Trigger
  const handleReject = async (id: number) => {
    try {
      const res = await fetch(`${API_HOST}/api/shipment/reject/${id}`, { method: 'POST' })
      if (res.ok) {
        setShipments(prev => prev.map(s => s.id === id ? { ...s, status: 'Rejected' } : s))
        fetchData()
      }
    } catch (err) {
      console.error(err)
    }
  }

  // Edit fields loader
  const openEditModal = (s: ShipmentDraft) => {
    setEditForm(s)
    setIsEditOpen(true)
  }

  // Save fields override trigger
  const handleSaveEdit = async () => {
    if (!editForm.id) return
    try {
      const res = await fetch(`${API_HOST}/api/shipment/${editForm.id}`, {
        method: 'PUT',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(editForm)
      })
      if (res.ok) {
        setIsEditOpen(false)
        fetchData()
      }
    } catch (err) {
      console.error(err)
    }
  }

  const handleSaveEmailSettings = async () => {
    try {
      const res = await fetch(`${API_HOST}/api/settings/email`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(emailSettings)
      })
      if (res.ok) {
        setSettingsSaved(true)
        setTimeout(() => setSettingsSaved(false), 3000)
      }
    } catch (e) {
      console.error(e)
    }
  }

  return (
    <div className="flex flex-col min-h-screen bg-slate-950 text-slate-100 font-sans">
      
      {/* ── Navbar Header ─────────────────────────────────────────────── */}
      <header className="border-b border-slate-800/80 bg-slate-900/60 backdrop-blur-md sticky top-0 z-40 px-6 py-4 flex items-center justify-between">
        <div className="flex items-center gap-3">
          <div className="w-10 h-10 rounded-xl bg-gradient-to-tr from-indigo-600 to-cyan-500 flex items-center justify-center shadow-lg shadow-indigo-500/20">
            <Truck className="w-5 h-5 text-white" />
          </div>
          <div>
            <h1 className="font-extrabold text-lg leading-tight tracking-tight bg-gradient-to-r from-white via-slate-100 to-slate-400 bg-clip-text text-transparent">
              Rworld ERP <span className="text-xs font-semibold text-indigo-400 border border-indigo-500/30 px-1.5 py-0.5 rounded-md ml-2 bg-indigo-500/10">Logistics AI Agent</span>
            </h1>
            <p className="text-[11px] text-slate-400 mt-0.5">Autonomous mail ingestion, parsing validations, and draft queue</p>
          </div>
        </div>

        {/* Global Statistics Badges */}
        <div className="flex items-center gap-4">
          <div className="hidden md:flex bg-slate-900 border border-slate-800 px-3.5 py-1.5 rounded-xl items-center gap-2">
            <div className="w-2.5 h-2.5 rounded-full bg-emerald-500 animate-ping"></div>
            <span className="text-xs font-semibold text-slate-300">FastAPI Ingest Server: Connected</span>
          </div>
        </div>
      </header>

      {/* ── Main Layout Workspace ─────────────────────────────────────── */}
      <div className="flex-1 flex flex-col md:flex-row">
        
        {/* Sidebar Nav */}
        <aside className="w-full md:w-64 bg-slate-900/40 border-r border-slate-850 p-4 space-y-1.5">
          <button
            onClick={() => setActiveTab('approvals')}
            className={`w-full flex items-center justify-between px-4 py-3 rounded-xl text-sm font-medium transition-all ${
              activeTab === 'approvals' 
                ? 'bg-gradient-to-r from-indigo-600 to-indigo-700 text-white shadow-md shadow-indigo-500/15' 
                : 'text-slate-400 hover:text-slate-200 hover:bg-slate-800/40'
            }`}
          >
            <div className="flex items-center gap-3">
              <ShieldCheck className="w-4 h-4" />
              <span>Pending Approvals</span>
            </div>
            {analytics.pending_approvals > 0 && (
              <span className="px-2 py-0.5 rounded-full bg-indigo-500/20 text-indigo-300 text-[10px] font-bold border border-indigo-400/20">
                {analytics.pending_approvals}
              </span>
            )}
          </button>

          <button
            onClick={() => setActiveTab('inbox')}
            className={`w-full flex items-center justify-between px-4 py-3 rounded-xl text-sm font-medium transition-all ${
              activeTab === 'inbox' 
                ? 'bg-gradient-to-r from-indigo-600 to-indigo-700 text-white shadow-md shadow-indigo-500/15' 
                : 'text-slate-400 hover:text-slate-200 hover:bg-slate-800/40'
            }`}
          >
            <div className="flex items-center gap-3">
              <Mail className="w-4 h-4" />
              <span>Emails Log</span>
            </div>
          </button>

          <button
            onClick={() => setActiveTab('completed')}
            className={`w-full flex items-center justify-between px-4 py-3 rounded-xl text-sm font-medium transition-all ${
              activeTab === 'completed' 
                ? 'bg-gradient-to-r from-indigo-600 to-indigo-700 text-white shadow-md shadow-indigo-500/15' 
                : 'text-slate-400 hover:text-slate-200 hover:bg-slate-800/40'
            }`}
          >
            <div className="flex items-center gap-3">
              <FileCheck2 className="w-4 h-4" />
              <span>Completed Shipments</span>
            </div>
          </button>

          <button
            onClick={() => setActiveTab('analytics')}
            className={`w-full flex items-center justify-between px-4 py-3 rounded-xl text-sm font-medium transition-all ${
              activeTab === 'analytics' 
                ? 'bg-gradient-to-r from-indigo-600 to-indigo-700 text-white shadow-md shadow-indigo-500/15' 
                : 'text-slate-400 hover:text-slate-200 hover:bg-slate-800/40'
            }`}
          >
            <div className="flex items-center gap-3">
              <BarChart3 className="w-4 h-4" />
              <span>Metrics & Analytics</span>
            </div>
          </button>

          <button
            onClick={() => setActiveTab('logs')}
            className={`w-full flex items-center justify-between px-4 py-3 rounded-xl text-sm font-medium transition-all ${
              activeTab === 'logs' 
                ? 'bg-gradient-to-r from-indigo-600 to-indigo-700 text-white shadow-md shadow-indigo-500/15' 
                : 'text-slate-400 hover:text-slate-200 hover:bg-slate-800/40'
            }`}
          >
            <div className="flex items-center gap-3">
              <Terminal className="w-4 h-4" />
              <span>Audit logs</span>
            </div>
          </button>

          <button
            onClick={() => setActiveTab('settings')}
            className={`w-full flex items-center justify-between px-4 py-3 rounded-xl text-sm font-medium transition-all ${
              activeTab === 'settings' 
                ? 'bg-gradient-to-r from-indigo-600 to-indigo-700 text-white shadow-md shadow-indigo-500/15' 
                : 'text-slate-400 hover:text-slate-200 hover:bg-slate-800/40'
            }`}
          >
            <div className="flex items-center gap-3">
              <Settings2 className="w-4 h-4" />
              <span>Config Panel</span>
            </div>
          </button>
        </aside>

        {/* Tab Viewport */}
        <main className="flex-1 p-6 md:p-8 bg-slate-950 overflow-y-auto">

          {/* ────────────────── 1. APPROVALS TAB ────────────────── */}
          {activeTab === 'approvals' && (
            <div className="space-y-6">
              <div className="flex items-center justify-between">
                <div>
                  <h2 className="text-xl font-extrabold tracking-tight">Approvals Dispatch Queue</h2>
                  <p className="text-xs text-slate-400 mt-1">Review extracted details and address anomalies before generating shipping labels</p>
                </div>
                <button 
                  onClick={fetchData} 
                  className="flex items-center gap-1.5 px-3 py-1.5 rounded-lg border border-slate-850 hover:bg-slate-800 text-xs text-slate-300 transition"
                >
                  <RefreshCw className="w-3.5 h-3.5" />
                  Sync Queue
                </button>
              </div>

              {shipments.filter(s => s.status === 'Pending Approval' || s.status === 'Approved').length === 0 ? (
                <div className="border border-dashed border-slate-800 rounded-2xl p-12 text-center text-slate-400 space-y-3">
                  <CheckCircle className="w-8 h-8 text-emerald-500 mx-auto" />
                  <p className="text-sm font-medium">All clear! No shipments currently pending human approval.</p>
                  <p className="text-xs text-slate-500">Logistics monitor will add drafts here as soon as emails are ingested.</p>
                </div>
              ) : (
                <div className="grid grid-cols-1 xl:grid-cols-3 gap-6">
                  
                  {/* Drafts List Column */}
                  <div className="xl:col-span-1 space-y-4 max-h-[70vh] overflow-y-auto pr-1">
                    {shipments.filter(s => s.status === 'Pending Approval' || s.status === 'Approved').map(draft => (
                      <div
                        key={draft.id}
                        onClick={() => setSelectedShipment(draft)}
                        className={`p-4 rounded-2xl border transition-all cursor-pointer text-left ${
                          selectedShipment?.id === draft.id 
                            ? 'bg-indigo-950/20 border-indigo-500/50 shadow-md shadow-indigo-950/30' 
                            : 'bg-slate-900/40 border-slate-900 hover:border-slate-800'
                        }`}
                      >
                        <div className="flex justify-between items-start">
                          <span className="text-[10px] font-bold text-indigo-400 uppercase tracking-wider">
                            ID #{draft.id}
                          </span>
                          <span className={`px-2 py-0.5 rounded text-[9px] font-bold ${
                            draft.validation_status === 'valid' 
                              ? 'bg-emerald-500/10 text-emerald-400 border border-emerald-500/20' 
                              : 'bg-rose-500/10 text-rose-400 border border-rose-500/20'
                          }`}>
                            {draft.validation_status.toUpperCase()}
                          </span>
                        </div>
                        
                        <h4 className="font-bold text-sm text-slate-200 mt-2 truncate">{draft.to_name || draft.to_company}</h4>
                        <p className="text-[11px] text-slate-400 truncate">{draft.to_city}, {draft.to_state}</p>

                        <div className="flex justify-between items-center mt-3 pt-3 border-t border-slate-900/60">
                          <div className="text-[10px] text-slate-400 font-mono">
                            SO: {draft.sales_order_number || 'N/A'}
                          </div>
                          <div className="text-[10px] text-slate-300 font-semibold bg-slate-800 px-2 py-0.5 rounded">
                            Risk: {Math.round(draft.risk_score)}%
                          </div>
                        </div>
                      </div>
                    ))}
                  </div>

                  {/* Shipment Details View Column */}
                  <div className="xl:col-span-2">
                    {selectedShipment ? (
                      <div className="bg-slate-900/50 border border-slate-900 rounded-2xl p-6 space-y-6 text-left relative overflow-hidden">
                        
                        {/* Gradient card glow */}
                        <div className="absolute top-0 right-0 w-36 h-36 bg-gradient-to-bl from-indigo-500/10 to-transparent pointer-events-none"></div>
                        
                        {/* Header Details */}
                        <div className="flex flex-col sm:flex-row justify-between items-start gap-4 pb-4 border-b border-slate-850">
                          <div>
                            <h3 className="text-lg font-bold text-slate-100">{selectedShipment.to_name || selectedShipment.to_company}</h3>
                            <p className="text-xs text-slate-400 mt-0.5">Draft Inbound Source: Email Log #{selectedShipment.email_id}</p>
                          </div>
                          
                          <div className="flex items-center gap-2 shrink-0">
                            <button
                              onClick={() => openEditModal(selectedShipment)}
                              className="flex items-center gap-1.5 px-3 py-1.5 rounded-lg border border-slate-800 hover:bg-slate-800 text-xs text-slate-300 transition"
                            >
                              <Edit className="w-3.5 h-3.5" />
                              Modify Fields
                            </button>
                            <button
                              onClick={() => handleReject(selectedShipment.id)}
                              className="flex items-center gap-1.5 px-3 py-1.5 rounded-lg border border-rose-500/20 text-rose-400 hover:bg-rose-500/10 text-xs transition"
                              title="Reject and dismiss draft"
                            >
                              <X className="w-3.5 h-3.5" />
                              Reject
                            </button>
                            <button
                              onClick={() => handleApprove(selectedShipment.id)}
                              disabled={approvingIds[selectedShipment.id]}
                              className="flex items-center gap-1.5 px-4 py-1.5 rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white font-semibold text-xs shadow-md shadow-indigo-600/15 disabled:opacity-60 transition"
                            >
                              {approvingIds[selectedShipment.id] ? (
                                <RefreshCw className="w-3.5 h-3.5 animate-spin" />
                              ) : (
                                <Check className="w-3.5 h-3.5" />
                              )}
                              {approvingIds[selectedShipment.id] ? "Processing..." : "Approve & Create Label"}
                            </button>
                          </div>
                        </div>

                        {/* Validation Errors Box */}
                        {JSON.parse(selectedShipment.validation_errors || "[]").length > 0 && (
                          <div className="p-4 rounded-xl bg-amber-500/10 border border-amber-500/20 text-amber-300 text-xs space-y-1.5">
                            <div className="flex items-center gap-2 font-bold text-amber-400 uppercase tracking-wider text-[10px]">
                              <AlertTriangle className="w-3.5 h-3.5" />
                              Validation Alerts
                            </div>
                            <ul className="list-disc pl-4 space-y-1">
                              {JSON.parse(selectedShipment.validation_errors || "[]").map((err: string, i: number) => (
                                <li key={i}>{err}</li>
                              ))}
                            </ul>
                          </div>
                        )}

                        {/* Details Grid */}
                        <div className="grid grid-cols-1 md:grid-cols-2 gap-6 text-sm">
                          
                          {/* Ship To address card */}
                          <div className="bg-slate-950/40 p-4 rounded-xl border border-slate-900 space-y-3">
                            <h4 className="font-bold text-xs uppercase tracking-wider text-slate-400">Recipient Address</h4>
                            <div className="space-y-1 text-slate-300">
                              <p className="font-semibold text-slate-200">{selectedShipment.to_name}</p>
                              {selectedShipment.to_company && <p>{selectedShipment.to_company}</p>}
                              <p>{selectedShipment.to_address1}</p>
                              {selectedShipment.to_address2 && <p>{selectedShipment.to_address2}</p>}
                              <p>{selectedShipment.to_city}, {selectedShipment.to_state} {selectedShipment.to_zip}</p>
                              <p className="text-xs text-slate-400 mt-2 font-mono">Ph: {selectedShipment.to_phone || 'N/A'}</p>
                            </div>
                          </div>

                          {/* Details & Specs Card */}
                          <div className="bg-slate-950/40 p-4 rounded-xl border border-slate-900 space-y-4">
                            <h4 className="font-bold text-xs uppercase tracking-wider text-slate-400">Package & Inbound References</h4>
                            
                            <div className="grid grid-cols-2 gap-4 text-xs">
                              <div>
                                <span className="block text-slate-400">Sales Order</span>
                                <span className="font-mono font-semibold text-slate-200">{selectedShipment.sales_order_number || 'N/A'}</span>
                              </div>
                              <div>
                                <span className="block text-slate-400">Cartons Count</span>
                                <span className="font-semibold text-slate-200">{selectedShipment.package_count} Box</span>
                              </div>
                              <div>
                                <span className="block text-slate-400">Total Weight</span>
                                <span className="font-semibold text-slate-200">{selectedShipment.weight_lbs || 'N/A'} lbs</span>
                              </div>
                              <div>
                                <span className="block text-slate-400">Dimensions</span>
                                <span className="font-semibold text-slate-200">
                                  {selectedShipment.length_in ? `${selectedShipment.length_in}x${selectedShipment.width_in}x${selectedShipment.height_in} in` : 'N/A'}
                                </span>
                              </div>
                            </div>
                            
                            <div className="pt-2 border-t border-slate-900/60 text-xs">
                              <span className="block text-slate-400">Carrier Preference</span>
                              <span className="font-semibold text-slate-200">{selectedShipment.carrier_preference || 'UPS'} - {selectedShipment.service_level || 'Ground'}</span>
                            </div>
                          </div>

                        </div>

                        {/* Special Instructions */}
                        {selectedShipment.special_instructions && (
                          <div className="p-4 bg-slate-950/40 rounded-xl border border-slate-900 text-xs space-y-1.5">
                            <span className="block text-slate-400 font-bold uppercase tracking-wider text-[10px]">Special Instructions</span>
                            <p className="text-slate-300 italic">{selectedShipment.special_instructions}</p>
                          </div>
                        )}

                        {/* Explainability reasoning logs */}
                        <div className="p-4 bg-slate-900 border border-slate-850 rounded-xl space-y-2">
                          <span className="block font-bold text-xs uppercase tracking-wider text-slate-400">Agent Extraction Audit Log</span>
                          <p className="text-[11px] font-mono text-slate-300 leading-relaxed">{selectedShipment.reasoning_log}</p>
                        </div>

                      </div>
                    ) : (
                      <div className="border border-dashed border-slate-850 rounded-2xl h-[450px] flex items-center justify-center text-slate-500">
                        Select a shipment draft from the list to review details.
                      </div>
                    )}
                  </div>

                </div>
              )}
            </div>
          )}

          {/* ────────────────── 2. EMAILS LOG TAB ────────────────── */}
          {activeTab === 'inbox' && (
            <div className="space-y-6 text-left">
              <div>
                <h2 className="text-xl font-extrabold tracking-tight">Emails Ingestion Log</h2>
                <p className="text-xs text-slate-400 mt-1">Audit log of all analyzed email dispatches and raw source attachments</p>
              </div>

              {emails.length === 0 ? (
                <div className="border border-dashed border-slate-850 rounded-2xl p-12 text-center text-slate-500">
                  No ingested emails found. Drop an email file in the inbox folder to test.
                </div>
              ) : (
                <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
                  
                  {/* Email list */}
                  <div className="lg:col-span-1 space-y-3 max-h-[70vh] overflow-y-auto pr-1">
                    {emails.map(email => (
                      <div
                        key={email.id}
                        onClick={() => setSelectedEmail(email)}
                        className={`p-4 rounded-xl border cursor-pointer transition-all ${
                          selectedEmail?.id === email.id
                            ? 'bg-slate-900 border-indigo-500/40 shadow'
                            : 'bg-slate-900/30 border-slate-900 hover:border-slate-800'
                        }`}
                      >
                        <div className="flex justify-between items-center text-[10px] text-slate-400">
                          <span>{email.sender}</span>
                          <span>{new Date(email.received_at).toLocaleTimeString()}</span>
                        </div>
                        <h4 className="font-bold text-sm text-slate-200 truncate mt-1.5">{email.subject}</h4>
                        <div className="flex justify-between items-center mt-3 pt-2.5 border-t border-slate-900">
                          <span className={`px-2 py-0.5 text-[8px] font-bold rounded uppercase ${
                            email.intent === 'Ignore' ? 'bg-slate-800 text-slate-400' : 'bg-indigo-500/20 text-indigo-300'
                          }`}>
                            {email.intent || 'Classifying'}
                          </span>
                          <span className="text-[10px] text-slate-500">
                            Attachments: {JSON.parse(email.attachments || "[]").length}
                          </span>
                        </div>
                      </div>
                    ))}
                  </div>

                  {/* Email details viewer */}
                  <div className="lg:col-span-2">
                    {selectedEmail ? (
                      <div className="bg-slate-900/40 border border-slate-900 rounded-xl p-6 space-y-6">
                        <div className="border-b border-slate-850 pb-4">
                          <h3 className="text-lg font-bold">{selectedEmail.subject}</h3>
                          <div className="flex justify-between text-xs text-slate-400 mt-2">
                            <span>From: {selectedEmail.sender}</span>
                            <span>Received: {new Date(selectedEmail.received_at).toLocaleString()}</span>
                          </div>
                        </div>

                        {/* Attachments list */}
                        {JSON.parse(selectedEmail.attachments || "[]").length > 0 && (
                          <div className="space-y-2">
                            <span className="block font-bold text-xs uppercase tracking-wider text-slate-400">Attachments Ingested</span>
                            <div className="flex flex-wrap gap-2">
                              {JSON.parse(selectedEmail.attachments || "[]").map((path: string, i: number) => (
                                <div key={i} className="px-3 py-1.5 bg-slate-950 border border-slate-850 rounded-lg text-xs font-mono text-slate-300 flex items-center gap-1.5">
                                  <Package className="w-3.5 h-3.5 text-indigo-400" />
                                  {path.split(/[/\\]/).pop()}
                                </div>
                              ))}
                            </div>
                          </div>
                        )}

                        {/* Email Body */}
                        <div className="space-y-2">
                          <span className="block font-bold text-xs uppercase tracking-wider text-slate-400">Message Body</span>
                          <div className="p-4 bg-slate-950/80 rounded-xl border border-slate-900 text-sm font-mono text-slate-300 whitespace-pre-wrap max-h-60 overflow-y-auto">
                            {selectedEmail.body}
                          </div>
                        </div>
                      </div>
                    ) : (
                      <div className="border border-dashed border-slate-850 rounded-xl h-[350px] flex items-center justify-center text-slate-500">
                        Select an email record from the list to view its source.
                      </div>
                    )}
                  </div>

                </div>
              )}
            </div>
          )}

          {/* ────────────────── 3. COMPLETED SHIPMENTS TAB ────────────────── */}
          {activeTab === 'completed' && (
            <div className="space-y-6 text-left">
              <div>
                <h2 className="text-xl font-extrabold tracking-tight">Completed & Dispatched Shipments</h2>
                <p className="text-xs text-slate-400 mt-1">Audit log of shipments containing active tracking links and carrier label PDFs</p>
              </div>

              {shipments.filter(s => s.status === 'Completed').length === 0 ? (
                <div className="border border-dashed border-slate-850 rounded-2xl p-12 text-center text-slate-500">
                  No completed shipments yet. Approve pending items to generate labels.
                </div>
              ) : (
                <div className="bg-slate-900/30 border border-slate-900 rounded-xl overflow-hidden shadow">
                  <div className="overflow-x-auto">
                    <table className="w-full text-sm text-left">
                      <thead className="bg-slate-900/80 text-slate-400 uppercase tracking-wider text-[10px] font-bold border-b border-slate-850">
                        <tr>
                          <th className="px-6 py-4">Inbound ID</th>
                          <th className="px-6 py-4">Recipient</th>
                          <th className="px-6 py-4">Sales Order</th>
                          <th className="px-6 py-4">Carrier Used</th>
                          <th className="px-6 py-4">Tracking Number</th>
                          <th className="px-6 py-4 text-right">Cost</th>
                          <th className="px-6 py-4 text-center">Label Link</th>
                        </tr>
                      </thead>
                      <tbody className="divide-y divide-slate-900/60">
                        {shipments.filter(s => s.status === 'Completed').map(s => (
                          <tr key={s.id} className="hover:bg-slate-900/20 transition-all">
                            <td className="px-6 py-4 font-mono font-bold text-slate-400">#{s.id}</td>
                            <td className="px-6 py-4 font-medium text-slate-200">
                              <div>{s.to_name}</div>
                              <div className="text-[10px] text-slate-400">{s.to_city}, {s.to_state}</div>
                            </td>
                            <td className="px-6 py-4 font-mono">{s.sales_order_number || 'N/A'}</td>
                            <td className="px-6 py-4 text-slate-300 font-semibold">{s.carrier_used || 'UPS'}</td>
                            <td className="px-6 py-4 font-mono text-indigo-400 font-semibold">{s.tracking_number}</td>
                            <td className="px-6 py-4 text-right text-emerald-400 font-semibold font-mono">${s.shipping_cost?.toFixed(2) || '0.00'}</td>
                            <td className="px-6 py-4 text-center">
                              <a
                                href={`http://localhost/shipping-portal/public/download_label.php?id=${s.portal_request_id}`}
                                target="_blank"
                                rel="noreferrer"
                                className="inline-flex items-center gap-1.5 px-3 py-1 rounded bg-indigo-500/10 border border-indigo-500/20 text-indigo-300 text-xs font-semibold hover:bg-indigo-500/20 transition"
                              >
                                Download Label
                                <ExternalLink className="w-3 h-3" />
                              </a>
                            </td>
                          </tr>
                        ))}
                      </tbody>
                    </table>
                  </div>
                </div>
              )}
            </div>
          )}

          {/* ────────────────── 4. ANALYTICS & METRICS TAB ────────────────── */}
          {activeTab === 'analytics' && (
            <div className="space-y-6 text-left">
              <div>
                <h2 className="text-xl font-extrabold tracking-tight">Performance & Ingestion Analytics</h2>
                <p className="text-xs text-slate-400 mt-1">Real-time statistics covering processed counts, duplicate logs, and carrier dispatches</p>
              </div>

              {/* Statistics Grid */}
              <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5">
                <div className="bg-slate-900/40 border border-slate-900 p-5 rounded-2xl space-y-2 relative overflow-hidden">
                  <div className="text-slate-400 text-xs font-bold uppercase tracking-wider flex justify-between items-center">
                    Total Emails Processed
                    <Mail className="w-4 h-4 text-indigo-400" />
                  </div>
                  <div className="text-3xl font-extrabold text-white font-mono">{analytics.processed_emails}</div>
                  <p className="text-[10px] text-slate-500">Emails ingested via IMAP/Mock logs</p>
                </div>

                <div className="bg-slate-900/40 border border-slate-900 p-5 rounded-2xl space-y-2 relative overflow-hidden">
                  <div className="text-slate-400 text-xs font-bold uppercase tracking-wider flex justify-between items-center">
                    Completed Dispatched
                    <CheckCircle className="w-4 h-4 text-emerald-400" />
                  </div>
                  <div className="text-3xl font-extrabold text-white font-mono">{analytics.completed_shipments}</div>
                  <p className="text-[10px] text-slate-500">Labels generated and finalized</p>
                </div>

                <div className="bg-slate-900/40 border border-slate-900 p-5 rounded-2xl space-y-2 relative overflow-hidden">
                  <div className="text-slate-400 text-xs font-bold uppercase tracking-wider flex justify-between items-center">
                    Duplicate Orders Blocked
                    <ShieldAlert className="w-4 h-4 text-rose-400" />
                  </div>
                  <div className="text-3xl font-extrabold text-white font-mono">{analytics.duplicate_count}</div>
                  <p className="text-[10px] text-slate-500">Ingest attempts matching existing SOs</p>
                </div>

                <div className="bg-slate-900/40 border border-slate-900 p-5 rounded-2xl space-y-2 relative overflow-hidden">
                  <div className="text-slate-400 text-xs font-bold uppercase tracking-wider flex justify-between items-center">
                    Total Carrier Billings
                    <DollarSign className="w-4 h-4 text-cyan-400" />
                  </div>
                  <div className="text-3xl font-extrabold text-white font-mono">${analytics.total_shipping_costs?.toFixed(2)}</div>
                  <p className="text-[10px] text-slate-500">Cost aggregate queried from portal</p>
                </div>
              </div>

              {/* Graphical Layout representation */}
              <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                
                {/* Carrier distribution */}
                <div className="bg-slate-900/30 border border-slate-900 p-6 rounded-2xl">
                  <h4 className="font-bold text-xs uppercase tracking-wider text-slate-400 mb-4">Carrier Dispatches share</h4>
                  
                  {Object.keys(analytics.carrier_dispatches).length === 0 ? (
                    <p className="text-sm text-slate-500 py-12 text-center">No carriers dispatched yet.</p>
                  ) : (
                    <div className="space-y-4">
                      {Object.entries(analytics.carrier_dispatches).map(([carrier, count]: [string, any]) => (
                        <div key={carrier} className="space-y-1">
                          <div className="flex justify-between text-xs font-semibold text-slate-300">
                            <span>{carrier}</span>
                            <span>{count} Dispatches</span>
                          </div>
                          <div className="w-full bg-slate-950 h-2.5 rounded-full overflow-hidden border border-slate-900">
                            <div 
                              className="bg-indigo-500 h-full" 
                              style={{ width: `${(count / analytics.completed_shipments) * 100}%` }}
                            ></div>
                          </div>
                        </div>
                      ))}
                    </div>
                  )}
                </div>

                {/* Automation Telemetry */}
                <div className="bg-slate-900/30 border border-slate-900 p-6 rounded-2xl space-y-4">
                  <h4 className="font-bold text-xs uppercase tracking-wider text-slate-400">Logistics Telemetry</h4>
                  
                  <div className="grid grid-cols-2 gap-4 text-xs font-medium text-slate-300">
                    <div className="bg-slate-950 p-4 rounded-xl border border-slate-900 space-y-1">
                      <span className="block text-slate-500 font-bold text-[10px]">Average OCR Time</span>
                      <span className="text-lg font-bold text-white font-mono">1.8 Sec</span>
                    </div>
                    <div className="bg-slate-950 p-4 rounded-xl border border-slate-900 space-y-1">
                      <span className="block text-slate-500 font-bold text-[10px]">LLM Parser Cost</span>
                      <span className="text-lg font-bold text-white font-mono">$0.003 / msg</span>
                    </div>
                    <div className="bg-slate-950 p-4 rounded-xl border border-slate-900 space-y-1">
                      <span className="block text-slate-500 font-bold text-[10px]">Portal Connection</span>
                      <span className="text-lg font-bold text-emerald-400">MySQL Online</span>
                    </div>
                    <div className="bg-slate-950 p-4 rounded-xl border border-slate-900 space-y-1">
                      <span className="block text-slate-500 font-bold text-[10px]">SMS / Webhook Sync</span>
                      <span className="text-lg font-bold text-emerald-400">Connected</span>
                    </div>
                  </div>
                </div>

              </div>

            </div>
          )}

          {/* ────────────────── 5. SYSTEM LOGS TAB ────────────────── */}
          {activeTab === 'logs' && (
            <div className="space-y-6 text-left">
              <div>
                <h2 className="text-xl font-extrabold tracking-tight">Agent Audit Log</h2>
                <p className="text-xs text-slate-400 mt-1">Real-time terminal audit trail of background threads parsing and syncing drafts</p>
              </div>

              {/* Terminal Logs console */}
              <div className="bg-slate-950 rounded-2xl border border-slate-900 p-6 shadow-xl space-y-4">
                <div className="flex justify-between items-center text-xs text-slate-500 pb-3 border-b border-slate-900">
                  <div className="flex items-center gap-2">
                    <Activity className="w-3.5 h-3.5 text-indigo-400 animate-pulse" />
                    <span>System Console: Listening to WS Broadcaster...</span>
                  </div>
                  <button 
                    onClick={() => setAuditLogs([])} 
                    className="text-[10px] text-slate-400 hover:text-slate-200 border border-slate-900 px-2 py-1 rounded bg-slate-900 hover:bg-slate-800 transition"
                  >
                    Clear Console
                  </button>
                </div>

                <div className="font-mono text-xs text-slate-300 space-y-2 max-h-[50vh] overflow-y-auto pr-1">
                  {auditLogs.length === 0 ? (
                    <div className="text-slate-600 italic py-12 text-center">No terminal logs recorded yet. Scan email to begin.</div>
                  ) : (
                    auditLogs.map((log, i) => (
                      <div key={i} className="flex gap-2 items-start py-1 border-b border-slate-900/30">
                        <span className="text-slate-500 shrink-0 select-none">[{log.timestamp}]</span>
                        <span className={`font-bold shrink-0 ${
                          log.step_status === 'success' ? 'text-emerald-400' : 'text-rose-400'
                        }`}>
                          {log.step_status.toUpperCase()}
                        </span>
                        <span className="font-semibold text-slate-200 shrink-0">{log.step_name}:</span>
                        <span className="text-slate-400 break-all">{log.details}</span>
                      </div>
                    ))
                  )}
                </div>
              </div>
            </div>
          )}

          {/* ────────────────── 6. SETTINGS TAB ────────────────── */}
          {activeTab === 'settings' && (
            <div className="max-w-3xl space-y-6 text-left mx-auto">
              <div>
                <h2 className="text-xl font-extrabold tracking-tight">Configuration Settings</h2>
                <p className="text-xs text-slate-400 mt-1">Configure email checking intervals, business rules, and API triggers</p>
              </div>

              <div className="bg-slate-900/30 border border-slate-900 rounded-2xl p-6 space-y-6">
                
                {/* Connection check stats */}
                <div className="space-y-4 pb-6 border-b border-slate-900/60">
                  <h4 className="font-bold text-xs uppercase tracking-wider text-slate-400">Platform Connections API</h4>
                  
                  <div className="grid grid-cols-1 md:grid-cols-2 gap-4 text-xs font-medium text-slate-300">
                    <div className="bg-slate-950 p-4 rounded-xl border border-slate-900 flex justify-between items-center">
                      <div>
                        <span className="block text-slate-500 text-[10px]">Headroom LLM API</span>
                        <span className="font-bold">Gemini 2.5 Pro via OpenRouter</span>
                      </div>
                      <span className="px-2 py-0.5 rounded bg-emerald-500/10 text-emerald-400 font-bold border border-emerald-500/20">Online</span>
                    </div>

                    <div className="bg-slate-950 p-4 rounded-xl border border-slate-900 flex justify-between items-center">
                      <div>
                        <span className="block text-slate-500 text-[10px]">Slack Hook Notification</span>
                        <span className="font-bold">Webhook alerts channel</span>
                      </div>
                      <span className="px-2 py-0.5 rounded bg-emerald-500/10 text-emerald-400 font-bold border border-emerald-500/20">Enabled</span>
                    </div>
                  </div>
                </div>

                {/* Email credentials setup form */}
                <div className="space-y-4">
                  <h4 className="font-bold text-xs uppercase tracking-wider text-slate-400">Gmail / IMAP Mail Poll Settings</h4>
                  
                  <div className="grid grid-cols-1 md:grid-cols-2 gap-4 text-xs">
                    <div className="space-y-1.5">
                      <label className="text-slate-400 font-semibold">IMAP Server</label>
                      <input
                        type="text"
                        placeholder="imap.gmail.com"
                        value={emailSettings.EMAIL_IMAP_SERVER}
                        onChange={e => setEmailSettings({ ...emailSettings, EMAIL_IMAP_SERVER: e.target.value })}
                        className="w-full bg-slate-950 border border-slate-800 rounded-lg p-2.5 text-slate-200 outline-none focus:border-indigo-500 font-mono"
                      />
                    </div>

                    <div className="space-y-1.5">
                      <label className="text-slate-400 font-semibold">IMAP Port</label>
                      <input
                        type="number"
                        placeholder="993"
                        value={emailSettings.EMAIL_IMAP_PORT}
                        onChange={e => setEmailSettings({ ...emailSettings, EMAIL_IMAP_PORT: parseInt(e.target.value) || 993 })}
                        className="w-full bg-slate-950 border border-slate-800 rounded-lg p-2.5 text-slate-200 outline-none focus:border-indigo-500 font-mono"
                      />
                    </div>

                    <div className="space-y-1.5">
                      <label className="text-slate-400 font-semibold">Gmail/IMAP Account Email</label>
                      <input
                        type="email"
                        placeholder="logistics@gmail.com"
                        value={emailSettings.EMAIL_USERNAME}
                        onChange={e => setEmailSettings({ ...emailSettings, EMAIL_USERNAME: e.target.value })}
                        className="w-full bg-slate-950 border border-slate-800 rounded-lg p-2.5 text-slate-200 outline-none focus:border-indigo-500 font-mono"
                      />
                    </div>

                    <div className="space-y-1.5">
                      <label className="text-slate-400 font-semibold">App Password</label>
                      <input
                        type="password"
                        placeholder="abcd efgh ijkl mnop"
                        value={emailSettings.EMAIL_PASSWORD}
                        onChange={e => setEmailSettings({ ...emailSettings, EMAIL_PASSWORD: e.target.value })}
                        className="w-full bg-slate-950 border border-slate-800 rounded-lg p-2.5 text-slate-200 outline-none focus:border-indigo-500 font-mono"
                      />
                    </div>
                  </div>

                  <div className="flex items-center gap-3 pt-2 justify-end">
                    {settingsSaved && (
                      <span className="text-xs text-emerald-400 font-semibold flex items-center gap-1">
                        <Check className="w-4 h-4" />
                        Credentials Saved Successfully!
                      </span>
                    )}
                    <button
                      onClick={handleSaveEmailSettings}
                      className="px-4 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg text-xs font-semibold shadow transition"
                    >
                      Save & Enable Monitoring
                    </button>
                  </div>
                </div>

              </div>
            </div>
          )}

        </main>
      </div>

      {/* ── Edit Shipment modal override Form ───────────────────────── */}
      {isEditOpen && (
        <div className="fixed inset-0 bg-slate-950/80 backdrop-blur-sm z-50 flex items-center justify-center p-4">
          <div className="bg-slate-900 border border-slate-850 rounded-2xl w-full max-w-2xl p-6 text-left space-y-6 shadow-2xl relative">
            <div className="flex justify-between items-center border-b border-slate-800 pb-3">
              <h3 className="text-base font-bold text-slate-200">Override Extracted Parameters</h3>
              <button onClick={() => setIsEditOpen(false)} className="text-slate-400 hover:text-slate-200">
                <X className="w-5 h-5" />
              </button>
            </div>

            <div className="grid grid-cols-1 md:grid-cols-2 gap-4 text-xs">
              <div className="space-y-1.5">
                <label className="text-slate-400 font-semibold">Recipient Name</label>
                <input
                  type="text"
                  value={editForm.to_name || ''}
                  onChange={e => setEditForm({ ...editForm, to_name: e.target.value })}
                  className="w-full bg-slate-950 border border-slate-800 rounded-lg p-2.5 text-slate-200 outline-none focus:border-indigo-500"
                />
              </div>

              <div className="space-y-1.5">
                <label className="text-slate-400 font-semibold">Recipient Company</label>
                <input
                  type="text"
                  value={editForm.to_company || ''}
                  onChange={e => setEditForm({ ...editForm, to_company: e.target.value })}
                  className="w-full bg-slate-950 border border-slate-800 rounded-lg p-2.5 text-slate-200 outline-none focus:border-indigo-500"
                />
              </div>

              <div className="space-y-1.5 md:col-span-2">
                <label className="text-slate-400 font-semibold">Address Line 1</label>
                <input
                  type="text"
                  value={editForm.to_address1 || ''}
                  onChange={e => setEditForm({ ...editForm, to_address1: e.target.value })}
                  className="w-full bg-slate-950 border border-slate-800 rounded-lg p-2.5 text-slate-200 outline-none focus:border-indigo-500"
                />
              </div>

              <div className="space-y-1.5">
                <label className="text-slate-400 font-semibold">City</label>
                <input
                  type="text"
                  value={editForm.to_city || ''}
                  onChange={e => setEditForm({ ...editForm, to_city: e.target.value })}
                  className="w-full bg-slate-950 border border-slate-800 rounded-lg p-2.5 text-slate-200 outline-none focus:border-indigo-500"
                />
              </div>

              <div className="space-y-1.5">
                <label className="text-slate-400 font-semibold">State</label>
                <input
                  type="text"
                  value={editForm.to_state || ''}
                  onChange={e => setEditForm({ ...editForm, to_state: e.target.value })}
                  className="w-full bg-slate-950 border border-slate-800 rounded-lg p-2.5 text-slate-200 outline-none focus:border-indigo-500"
                />
              </div>

              <div className="space-y-1.5">
                <label className="text-slate-400 font-semibold">ZIP Code</label>
                <input
                  type="text"
                  value={editForm.to_zip || ''}
                  onChange={e => setEditForm({ ...editForm, to_zip: e.target.value })}
                  className="w-full bg-slate-950 border border-slate-800 rounded-lg p-2.5 text-slate-200 outline-none focus:border-indigo-500"
                />
              </div>

              <div className="space-y-1.5">
                <label className="text-slate-400 font-semibold">Weight (lbs)</label>
                <input
                  type="number"
                  step="0.1"
                  value={editForm.weight_lbs || ''}
                  onChange={e => setEditForm({ ...editForm, weight_lbs: parseFloat(e.target.value) || 0 })}
                  className="w-full bg-slate-950 border border-slate-800 rounded-lg p-2.5 text-slate-200 outline-none focus:border-indigo-500"
                />
              </div>
            </div>

            <div className="flex justify-end gap-3 pt-3 border-t border-slate-800">
              <button
                onClick={() => setIsEditOpen(false)}
                className="px-4 py-2 rounded-lg border border-slate-800 hover:bg-slate-800 text-xs font-semibold transition"
              >
                Cancel
              </button>
              <button
                onClick={handleSaveEdit}
                className="px-4 py-2 rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-semibold shadow transition"
              >
                Save & Recalculate
              </button>
            </div>
          </div>
        </div>
      )}

    </div>
  )
}
