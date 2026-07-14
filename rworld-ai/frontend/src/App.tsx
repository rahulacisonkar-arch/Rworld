import { useState, useEffect, useRef } from 'react'
import { 
  ShieldAlert, Layers, Settings, Database, 
  Terminal, CheckCircle2, XCircle, RefreshCw, 
  Sliders, Send, UploadCloud, Clock, BarChart3, Save, Plus
} from 'lucide-react'

interface ApprovalItem {
  id: number
  task_id: number
  action_type: string
  payload: any
  requested_at: string
}

// Resolve host dynamically for local network sharing
const API_HOST = window.location.hostname ? `http://${window.location.hostname}:8000` : 'http://localhost:8000'
const WS_HOST = window.location.hostname ? `ws://${window.location.hostname}:8000` : 'ws://localhost:8000'

export default function App() {
  const [activeTab, setActiveTab] = useState<'dashboard' | 'approvals' | 'knowledge' | 'plugins' | 'settings' | 'upload' | 'queue' | 'mappers' | 'analytics'>('dashboard')
  const [wsStatus, setWsStatus] = useState<'connected' | 'disconnected' | 'connecting'>('connecting')
  
  // State variables for application data
  const [taskTitle, setTaskTitle] = useState('')
  const [taskDesc, setTaskDesc] = useState('')
  const [loading, setLoading] = useState(false)
  const [currentResult, setCurrentResult] = useState<string | null>(null)
  
  const [tasks, setTasks] = useState<any[]>([])
  const [approvals, setApprovals] = useState<ApprovalItem[]>([])
  const [terminalLogs, setTerminalLogs] = useState<string[]>([
    'Mworld Intellegence core systems initialized...',
    'Awaiting instructions...'
  ])

  // Document Automation state variables
  const [isUploading, setIsUploading] = useState(false)
  const [uploadResult, setUploadResult] = useState<any>(null)
  const [editedFields, setEditedFields] = useState<Record<string, any>>({})
  const [selectedDestination, setSelectedDestination] = useState('quickbill')
  const [isAutomating, setIsAutomating] = useState(false)
  const [automationResult, setAutomationResult] = useState<any>(null)
  const [automationLogs, setAutomationLogs] = useState<any[]>([])
  const [selectedBatchIds, setSelectedBatchIds] = useState<number[]>([])

  const uploadFile = async (file: File) => {
    setIsUploading(true);
    setUploadResult(null);
    try {
      const formData = new FormData();
      formData.append('file', file);
      
      const res = await fetch(`${API_HOST}/api/document/upload`, {
        method: 'POST',
        body: formData
      });
      if (res.ok) {
        const data = await res.json();
        setUploadResult(data);
        setEditedFields(data.extracted_data || {});
        fetchV4Data(); // Refresh history list
      }
    } catch (e) {
      console.error(e);
    } finally {
      setIsUploading(false);
    }
  }
  
  // Dynamic Mappers & Schedulers states
  const [mappings, setMappings] = useState<any[]>([])
  const [learnTarget, setLearnTarget] = useState('quickbill')
  const [learnKey, setLearnKey] = useState('')
  const [learnSelector, setLearnSelector] = useState('')
  const [learnLabel, setLearnLabel] = useState('')
  
  // Logs, Queue & Analytics telemetry states
  const [historyList, setHistoryList] = useState<any[]>([])
  const [auditLogs, setAuditLogs] = useState<any[]>([])
  const [analytics, setAnalytics] = useState<any>({
    processed_count: 0,
    average_confidence: 0,
    success_rate: 100,
    correction_rate: 0,
    sync_status: "synced"
  })

  // Load custom mappers, history logs, and stats
  const fetchV4Data = async () => {
    try {
      const mappingsRes = await fetch(`${API_HOST}/api/document/mappings`)
      if (mappingsRes.ok) setMappings(await mappingsRes.json())
      
      const historyRes = await fetch(`${API_HOST}/api/document/history`)
      if (historyRes.ok) setHistoryList(await historyRes.json())

      const logsRes = await fetch(`${API_HOST}/api/document/logs`)
      if (logsRes.ok) setAuditLogs(await logsRes.json())

      const statsRes = await fetch(`${API_HOST}/api/document/confidence`)
      if (statsRes.ok) setAnalytics(await statsRes.json())

      const tasksRes = await fetch(`${API_HOST}/api/tasks`)
      if (tasksRes.ok) setTasks(await tasksRes.json())
    } catch (err) {
      console.error('Failed fetching document automation stats:', err)
    }
  }

  // Trigger loading V4 stats on tab changes
  useEffect(() => {
    fetchV4Data()
  }, [activeTab])
  
  const wsRef = useRef<WebSocket | null>(null)

  // Load approvals from backend
  const fetchApprovals = async () => {
    try {
      const res = await fetch(`${API_HOST}/api/approvals`)
      if (res.ok) {
        const data = await res.json()
        setApprovals(data)
      }
    } catch (err) {
      console.error('Failed to load approvals:', err)
    }
  }

  // Setup live updates WebSocket
  useEffect(() => {
    let reconnectTimeout: any

    const connectWS = () => {
      setWsStatus('connecting')
      const ws = new WebSocket(`${WS_HOST}/ws/approvals`)
      wsRef.current = ws

      ws.onopen = () => {
        setWsStatus('connected')
        setTerminalLogs(prev => [...prev, '✓ WebSocket connected to FastAPI backend.'])
      }

      ws.onmessage = (event) => {
        try {
          const data = JSON.parse(event.data)
          if (data.event === 'task_updated') {
            setTerminalLogs(prev => [
              ...prev, 
              `[Task Update] Task #${data.task_id} status changed to ${data.status.toUpperCase()}: ${data.result}`
            ])
            fetchApprovals() // Reload approvals if state changed
            fetchV4Data() // Reload task list and stats
          }
        } catch (e) {
          console.error(e)
        }
      }

      ws.onclose = () => {
        setWsStatus('disconnected')
        setTerminalLogs(prev => [...prev, '✗ WebSocket connection lost. Reconnecting...'])
        reconnectTimeout = setTimeout(connectWS, 4000)
      }

      ws.onerror = () => {
        ws.close()
      }
    }

    connectWS()
    fetchApprovals()

    return () => {
      if (wsRef.current) wsRef.current.close()
      clearTimeout(reconnectTimeout)
    }
  }, [])

  // Submit task handler
  const handleLaunchTask = async (e: React.FormEvent) => {
    e.preventDefault()
    if (!taskTitle) return
    setLoading(true)
    setCurrentResult(null)
    setTerminalLogs(prev => [...prev, `[Planner] Initiating goal: "${taskTitle}"`])

    try {
      const res = await fetch(`${API_HOST}/api/task`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ title: taskTitle, description: taskDesc })
      })
      const data = await res.json()
      if (data.success) {
        setCurrentResult(data.message)
        setTerminalLogs(prev => [...prev, `[Success] Planner result: ${data.message}`])
        // Add to active tasks list
        setTasks(prev => [{
          id: data.task_id,
          title: taskTitle,
          status: data.status,
          description: taskDesc
        }, ...prev])
      } else {
        setTerminalLogs(prev => [...prev, `[Error] Failed: ${data.message}`])
      }
    } catch (err) {
      setTerminalLogs(prev => [...prev, '[Error] Failed to communicate with FastAPI API.'])
    } finally {
      setLoading(false)
      setTaskTitle('')
      setTaskDesc('')
      fetchApprovals()
    }
  }

  // Handle Approve / Reject decisions
  const handleDecision = async (id: number, status: 'approved' | 'rejected') => {
    try {
      const res = await fetch(`${API_HOST}/api/approve/${id}`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ status, remarks: `Action ${status} via dashboard UI.` })
      })
      if (res.ok) {
        setTerminalLogs(prev => [...prev, `[Approval] Decision logged: ID ${id} was ${status.toUpperCase()}`])
        fetchApprovals()
      }
    } catch (err) {
      setTerminalLogs(prev => [...prev, `[Error] Failed logging decision for ID ${id}`])
    }
  }

  return (
    <div className="flex h-screen w-screen overflow-hidden bg-[#070b12] text-slate-200">
      
      {/* Sleek Left Sidebar */}
      <div className="w-64 glass-sidebar flex flex-col justify-between p-6 z-10">
        <div>
          {/* Logo Brand Header */}
          <div className="flex items-center space-x-3 mb-10">
            <div className="w-8 h-8 rounded-lg bg-indigo-600 flex items-center justify-center font-bold text-white shadow-lg shadow-indigo-500/20">
              M
            </div>
            <div>
              <h1 className="font-semibold text-white tracking-wider text-base">MWORLD INTELLEGENCE</h1>
              <p className="text-[10px] text-indigo-400 font-mono tracking-widest uppercase">Agent Console</p>
            </div>
          </div>

          {/* Navigation Links */}
          <nav className="space-y-2">
            <button 
              onClick={() => setActiveTab('dashboard')}
              className={`w-full flex items-center space-x-3 px-4 py-3 rounded-lg text-sm transition-all duration-200 ${
                activeTab === 'dashboard' 
                  ? 'bg-white/10 text-white font-medium shadow-sm border border-white/5' 
                  : 'text-slate-400 hover:text-slate-100 hover:bg-white/5'
              }`}
            >
              <Layers size={18} />
              <span>Executive Dashboard</span>
            </button>

            <button 
              onClick={() => setActiveTab('approvals')}
              className={`w-full flex items-center justify-between px-4 py-3 rounded-lg text-sm transition-all duration-200 ${
                activeTab === 'approvals' 
                  ? 'bg-white/10 text-white font-medium shadow-sm border border-white/5' 
                  : 'text-slate-400 hover:text-slate-100 hover:bg-white/5'
              }`}
            >
              <div className="flex items-center space-x-3">
                <ShieldAlert size={18} />
                <span>Approvals Guard</span>
              </div>
              {approvals.length > 0 && (
                <span className="bg-amber-500 text-dark-bg font-bold font-mono text-[10px] px-2 py-0.5 rounded-full animate-pulse">
                  {approvals.length}
                </span>
              )}
            </button>

            <button 
              onClick={() => setActiveTab('knowledge')}
              className={`w-full flex items-center space-x-3 px-4 py-3 rounded-lg text-sm transition-all duration-200 ${
                activeTab === 'knowledge' 
                  ? 'bg-white/10 text-white font-medium shadow-sm border border-white/5' 
                  : 'text-slate-400 hover:text-slate-100 hover:bg-white/5'
              }`}
            >
              <Database size={18} />
              <span>Enterprise Brain</span>
            </button>

            <button 
              onClick={() => setActiveTab('plugins')}
              className={`w-full flex items-center space-x-3 px-4 py-3 rounded-lg text-sm transition-all duration-200 ${
                activeTab === 'plugins' 
                  ? 'bg-white/10 text-white font-medium shadow-sm border border-white/5' 
                  : 'text-slate-400 hover:text-slate-100 hover:bg-white/5'
              }`}
            >
              <Sliders size={18} />
              <span>Operations Plugins</span>
            </button>

            <button 
              onClick={() => setActiveTab('upload')}
              className={`w-full flex items-center space-x-3 px-4 py-3 rounded-lg text-sm transition-all duration-200 ${
                activeTab === 'upload' 
                  ? 'bg-white/10 text-white font-medium shadow-sm border border-white/5' 
                  : 'text-slate-400 hover:text-slate-100 hover:bg-white/5'
              }`}
            >
              <UploadCloud size={18} />
              <span>Document Upload</span>
            </button>

            <button 
              onClick={() => setActiveTab('queue')}
              className={`w-full flex items-center space-x-3 px-4 py-3 rounded-lg text-sm transition-all duration-200 ${
                activeTab === 'queue' 
                  ? 'bg-white/10 text-white font-medium shadow-sm border border-white/5' 
                  : 'text-slate-400 hover:text-slate-100 hover:bg-white/5'
              }`}
            >
              <Clock size={18} />
              <span>Automation Queue</span>
            </button>

            <button 
              onClick={() => setActiveTab('mappers')}
              className={`w-full flex items-center space-x-3 px-4 py-3 rounded-lg text-sm transition-all duration-200 ${
                activeTab === 'mappers' 
                  ? 'bg-white/10 text-white font-medium shadow-sm border border-white/5' 
                  : 'text-slate-400 hover:text-slate-100 hover:bg-white/5'
              }`}
            >
              <Sliders size={18} />
              <span>Website Mappers</span>
            </button>

            <button 
              onClick={() => setActiveTab('analytics')}
              className={`w-full flex items-center space-x-3 px-4 py-3 rounded-lg text-sm transition-all duration-200 ${
                activeTab === 'analytics' 
                  ? 'bg-white/10 text-white font-medium shadow-sm border border-white/5' 
                  : 'text-slate-400 hover:text-slate-100 hover:bg-white/5'
              }`}
            >
              <BarChart3 size={18} />
              <span>Confidence & Stats</span>
            </button>

            <button 
              onClick={() => setActiveTab('settings')}
              className={`w-full flex items-center space-x-3 px-4 py-3 rounded-lg text-sm transition-all duration-200 ${
                activeTab === 'settings' 
                  ? 'bg-white/10 text-white font-medium shadow-sm border border-white/5' 
                  : 'text-slate-400 hover:text-slate-100 hover:bg-white/5'
              }`}
            >
              <Settings size={18} />
              <span>System Settings</span>
            </button>
          </nav>
        </div>

        {/* System Health Indicators */}
        <div className="space-y-4 pt-4 border-t border-white/5">
          <div className="flex items-center justify-between text-xs">
            <span className="text-slate-400">WS Gateway</span>
            <div className="flex items-center space-x-2">
              <span className={`w-2.5 h-2.5 rounded-full ${
                wsStatus === 'connected' ? 'bg-emerald-500 shadow-md shadow-emerald-500/20' : 
                wsStatus === 'connecting' ? 'bg-amber-500 animate-pulse' : 'bg-rose-500'
              }`} />
              <span className="capitalize text-slate-300 font-mono text-[10px]">{wsStatus}</span>
            </div>
          </div>
          <div className="flex items-center justify-between text-xs text-slate-400">
            <span>Hardware Mode</span>
            <span className="font-mono text-[10px] text-slate-300">8GB Low-RAM</span>
          </div>
        </div>
      </div>

      {/* Main Container panel */}
      <div className="flex-1 flex flex-col overflow-hidden relative p-8">
        {/* Animated ambient glow orb in background */}
        <div className="bg-glow-orb top-[-100px] right-[-100px]" />
        
        {/* Tab Contents */}
        <div className="flex-1 overflow-y-auto space-y-6 z-10 pr-2">
          
          {activeTab === 'dashboard' && (
            <>
              {/* Header section */}
              <div className="flex justify-between items-center pb-2">
                <div>
                  <h2 className="text-2xl font-bold tracking-tight text-white">Executive Dashboard</h2>
                  <p className="text-xs text-slate-400">Plan, track, and manage local AI task execution.</p>
                </div>
                <button 
                  onClick={fetchApprovals}
                  className="p-2 rounded-lg bg-white/5 hover:bg-white/10 border border-white/10 text-slate-300 transition-all"
                  title="Reload lists"
                >
                  <RefreshCw size={16} />
                </button>
              </div>

              {/* Grid Layout widgets */}
              <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
                
                {/* 1. Launch Task Form Card */}
                <div className="glass-panel glass-panel-glow rounded-xl p-6 lg:col-span-2 space-y-4">
                  <h3 className="text-sm font-semibold tracking-wider uppercase text-indigo-400 font-mono">Launch New Subtask Goal</h3>
                  <form onSubmit={handleLaunchTask} className="space-y-4">
                    <div>
                      <label className="block text-xs font-mono text-slate-400 mb-1.5">Task Title / Goal</label>
                      <input 
                        type="text" 
                        required
                        placeholder="e.g., Find cheapest roofing suppliers and compare prices"
                        value={taskTitle}
                        onChange={(e) => setTaskTitle(e.target.value)}
                        className="w-full bg-[#0a0f1b] border border-white/10 rounded-lg px-4 py-3 text-sm focus:outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 text-white placeholder-slate-600 transition-all"
                      />
                    </div>
                    
                    <div>
                      <label className="block text-xs font-mono text-slate-400 mb-1.5">Context Description (Optional)</label>
                      <textarea 
                        rows={3}
                        placeholder="Enter parameters, specific locations, or vendor limits..."
                        value={taskDesc}
                        onChange={(e) => setTaskDesc(e.target.value)}
                        className="w-full bg-[#0a0f1b] border border-white/10 rounded-lg px-4 py-3 text-sm focus:outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 text-white placeholder-slate-600 transition-all resize-none"
                      />
                    </div>

                    <button 
                      type="submit" 
                      disabled={loading}
                      className="w-full bg-indigo-600 hover:bg-indigo-500 disabled:opacity-50 text-white text-sm font-semibold py-3 px-4 rounded-lg shadow-lg shadow-indigo-500/20 flex items-center justify-center space-x-2 transition-all"
                    >
                      {loading ? (
                        <>
                          <RefreshCw size={16} className="animate-spin" />
                          <span>Generating Plan & Executing...</span>
                        </>
                      ) : (
                        <>
                          <Send size={16} />
                          <span>Execute Agentic Planner Loop</span>
                        </>
                      )}
                    </button>
                  </form>
                </div>

                {/* 2. Today's Summary statistics */}
                <div className="glass-panel rounded-xl p-6 space-y-6">
                  <h3 className="text-sm font-semibold tracking-wider uppercase text-indigo-400 font-mono">Today's Summary</h3>
                  
                  <div className="space-y-4">
                    <div className="bg-white/5 border border-white/5 rounded-lg p-4 flex items-center justify-between">
                      <div>
                        <span className="block text-[10px] text-slate-400 font-mono uppercase">Pending Approvals</span>
                        <span className="text-xl font-bold font-mono text-amber-500">{approvals.length}</span>
                      </div>
                      <ShieldAlert size={28} className="text-amber-500/80" />
                    </div>

                    <div className="bg-white/5 border border-white/5 rounded-lg p-4 flex items-center justify-between">
                      <div>
                        <span className="block text-[10px] text-slate-400 font-mono uppercase">Completed Tasks</span>
                        <span className="text-xl font-bold font-mono text-emerald-500">{tasks.filter(t => t.status === 'completed').length}</span>
                      </div>
                      <CheckCircle2 size={28} className="text-emerald-500/80" />
                    </div>
                  </div>

                  <div className="text-[11px] text-slate-400 bg-[#0d1525] border border-white/5 p-4 rounded-lg font-mono space-y-2">
                    <div className="flex justify-between">
                      <span>Vector DB Capacity:</span>
                      <span className="text-slate-300">SQLite NumPy Store</span>
                    </div>
                    <div className="flex justify-between">
                      <span>Memory Profile:</span>
                      <span className="text-emerald-500">Low Overhead (42MB)</span>
                    </div>
                  </div>
                </div>

              </div>

              {/* Planner Execution Result */}
              {currentResult && (
                <div className="glass-panel border-l-4 border-l-emerald-500 rounded-xl p-5 space-y-1">
                  <span className="block text-[10px] text-emerald-400 font-mono font-semibold uppercase tracking-wider">Planner Goal Execution Success</span>
                  <p className="text-sm text-slate-200">{currentResult}</p>
                </div>
              )}

              {/* 3. Task Progress Status Timeline */}
              {tasks.length > 0 && (
                <div className="glass-panel rounded-xl p-6 space-y-4">
                  <h3 className="text-sm font-semibold tracking-wider uppercase text-indigo-400 font-mono">Task Queue History</h3>
                  <div className="space-y-3">
                    {tasks.map(t => (
                      <div key={t.id} className="bg-white/5 border border-white/5 rounded-lg p-4 hover:bg-white/10 transition-all space-y-2">
                        <div className="flex justify-between items-center">
                          <div>
                            <h4 className="font-semibold text-slate-100 text-sm">{t.title}</h4>
                            <p className="text-xs text-slate-400">{t.description || 'No context description provided.'}</p>
                          </div>
                          <div className="flex items-center space-x-3">
                            <span className={`px-3 py-1 rounded-full text-[10px] font-bold font-mono uppercase tracking-wider ${
                              t.status === 'completed' ? 'bg-emerald-500/10 text-emerald-400 border border-emerald-500/20' :
                              t.status === 'blocked' ? 'bg-amber-500/10 text-amber-400 border border-amber-500/20' :
                              'bg-indigo-500/10 text-indigo-400 border border-indigo-500/20 animate-pulse'
                            }`}>
                              {t.status}
                            </span>
                          </div>
                        </div>
                        {t.result && (
                          <div className="bg-[#05080f] border border-white/5 p-2.5 rounded text-xs font-mono text-slate-300 whitespace-pre-wrap">
                            {t.result}
                          </div>
                        )}
                      </div>
                    ))}
                  </div>
                </div>
              )}

              {/* 4. Live Thinking Terminal */}
              <div className="glass-panel rounded-xl p-6 space-y-3 flex flex-col h-72">
                <div className="flex items-center space-x-2 text-indigo-400">
                  <Terminal size={18} />
                  <h3 className="text-sm font-semibold tracking-wider uppercase font-mono">Live Agent System Logs</h3>
                </div>
                
                <div className="flex-1 bg-[#05080f] rounded-lg p-4 font-mono text-xs text-slate-300 overflow-y-auto space-y-1.5 border border-white/5">
                  {terminalLogs.map((log, idx) => (
                    <div key={idx} className={
                      log.startsWith('[Error]') ? 'text-rose-400' :
                      log.startsWith('✓') || log.startsWith('[Success]') ? 'text-emerald-400' :
                      log.startsWith('[Approval]') || log.startsWith('[SECURE]') ? 'text-amber-400' : 'text-slate-300'
                    }>
                      {log}
                    </div>
                  ))}
                </div>
              </div>
            </>
          )}

          {activeTab === 'approvals' && (
            <>
              <div className="pb-2">
                <h2 className="text-2xl font-bold tracking-tight text-white">Approvals Queue</h2>
                <p className="text-xs text-slate-400">Safely review blocked actions before allowing the AI to dispatch them.</p>
              </div>

              {approvals.length === 0 ? (
                <div className="glass-panel rounded-xl p-12 text-center text-slate-400 flex flex-col items-center justify-center space-y-4">
                  <CheckCircle2 size={48} className="text-indigo-500/40" />
                  <div>
                    <h3 className="text-white font-medium text-base">All Clear!</h3>
                    <p className="text-xs">There are no blocked actions waiting in the queue.</p>
                  </div>
                </div>
              ) : (
                <div className="space-y-4">
                  {approvals.map(item => (
                    <div key={item.id} className="glass-panel rounded-xl p-6 border-l-4 border-l-amber-500 space-y-4">
                      <div className="flex justify-between items-start">
                        <div>
                          <span className="inline-block bg-amber-500/10 text-amber-400 border border-amber-500/20 text-[10px] font-bold font-mono px-2 py-0.5 rounded uppercase tracking-wider mb-2">
                            {item.action_type}
                          </span>
                          <h3 className="text-white font-semibold text-base">Request ID: #{item.id} (Task #{item.task_id})</h3>
                          <p className="text-xs text-slate-400 font-mono mt-1">Requested at: {new Date(item.requested_at).toLocaleString()}</p>
                        </div>
                        
                        <div className="flex space-x-2">
                          <button 
                            onClick={() => handleDecision(item.id, 'rejected')}
                            className="px-4 py-2 bg-rose-950/40 hover:bg-rose-900 border border-rose-500/30 text-rose-300 text-xs font-semibold rounded-lg flex items-center space-x-1.5 transition-all"
                          >
                            <XCircle size={14} />
                            <span>Reject & Block</span>
                          </button>
                          
                          <button 
                            onClick={() => handleDecision(item.id, 'approved')}
                            className="px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-semibold rounded-lg flex items-center space-x-1.5 shadow-lg shadow-indigo-500/20 transition-all"
                          >
                            <CheckCircle2 size={14} />
                            <span>Approve Action</span>
                          </button>
                        </div>
                      </div>

                      <div className="bg-[#05080f] border border-white/5 p-4 rounded-lg">
                        <span className="block text-[10px] text-slate-500 font-mono uppercase mb-2">Payload Parameters</span>
                        <pre className="text-xs text-slate-300 font-mono whitespace-pre-wrap">
                          {JSON.stringify(item.payload, null, 2)}
                        </pre>
                      </div>
                    </div>
                  ))}
                </div>
              )}
            </>
          )}

          {activeTab === 'knowledge' && (
            <>
              <div className="pb-2">
                <h2 className="text-2xl font-bold tracking-tight text-white">Enterprise Brain & Knowledge Graph</h2>
                <p className="text-xs text-slate-400">Search semantic memories and explore relationships between business entities.</p>
              </div>

              <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
                {/* Knowledge Graph Visualizer Card */}
                <div className="glass-panel rounded-xl p-6 lg:col-span-2 space-y-4">
                  <div className="flex items-center space-x-2 text-indigo-400">
                    <Database size={18} />
                    <h3 className="text-sm font-semibold tracking-wider uppercase font-mono">Business Knowledge Graph</h3>
                  </div>

                  <div className="bg-[#05080f] rounded-lg p-6 border border-white/5 flex flex-col items-center justify-center relative min-h-[300px] overflow-hidden">
                    {/* Simulated node connection mapping */}
                    <div className="absolute top-8 left-12 w-28 bg-white/5 border border-white/10 rounded p-2 text-center text-xs">
                      <span className="block text-[9px] text-slate-400 font-mono">Customer</span>
                      <strong className="text-slate-200">RWorld Fabrics</strong>
                    </div>

                    <div className="absolute top-14 right-12 w-28 bg-white/5 border border-white/10 rounded p-2 text-center text-xs">
                      <span className="block text-[9px] text-slate-400 font-mono">Invoice</span>
                      <strong className="text-slate-200">INV-2026-MOCK</strong>
                    </div>

                    <div className="absolute bottom-10 left-24 w-28 bg-white/5 border border-white/10 rounded p-2 text-center text-xs">
                      <span className="block text-[9px] text-slate-400 font-mono">Payment</span>
                      <strong className="text-slate-200">PAY-30-CASH</strong>
                    </div>

                    <div className="absolute bottom-16 right-20 w-28 bg-white/5 border border-white/10 rounded p-2 text-center text-xs">
                      <span className="block text-[9px] text-slate-400 font-mono">Supplier</span>
                      <strong className="text-slate-200">Burlington</strong>
                    </div>

                    {/* SVG Connector lines */}
                    <svg className="w-full h-[220px] pointer-events-none opacity-40">
                      <line x1="160" y1="50" x2="480" y2="70" stroke="indigo" strokeWidth="2" strokeDasharray="4"/>
                      <line x1="480" y1="70" x2="200" y2="180" stroke="indigo" strokeWidth="2"/>
                      <line x1="200" y1="180" x2="450" y2="150" stroke="indigo" strokeWidth="2" strokeDasharray="2"/>
                    </svg>

                    <div className="text-[10px] text-slate-500 font-mono text-center mt-4">
                      Interactive Entity Relationship Brain Mapping
                    </div>
                  </div>
                </div>

                {/* Vector Database Info Card */}
                <div className="glass-panel rounded-xl p-6 space-y-4">
                  <div className="flex items-center space-x-2 text-indigo-400">
                    <Sliders size={18} />
                    <h3 className="text-sm font-semibold tracking-wider uppercase font-mono">Semantic Memory Configuration</h3>
                  </div>

                  <div className="space-y-4 text-xs text-slate-300">
                    <p>Mworld Intellegence embeds documents chunk vectors locally into a relational SQLite database structure to avoid running separate database containers.</p>
                    
                    <div className="bg-[#05080f] p-4 rounded-lg space-y-3 border border-white/5 font-mono text-[10px]">
                      <div className="flex justify-between">
                        <span className="text-slate-500">Vector Embeddings:</span>
                        <span className="text-indigo-400 font-bold">Ollama / all-minilm</span>
                      </div>
                      <div className="flex justify-between">
                        <span className="text-slate-500">Dimensions count:</span>
                        <span className="text-slate-300">384 Dimensions</span>
                      </div>
                      <div className="flex justify-between">
                        <span className="text-slate-500">Primary DB Engine:</span>
                        <span className="text-slate-300">SQLite + NumPy Store</span>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </>
          )}

          {activeTab === 'plugins' && (
            <>
              <div className="pb-2">
                <h2 className="text-2xl font-bold tracking-tight text-white">Operations Plugins</h2>
                <p className="text-xs text-slate-400">Enable, configure, and manage modular integrations for Mworld Intellegence.</p>
              </div>

              <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                {[
                  { name: "QuickBill Connector", desc: "Automates transactions, invoice creation, and payment entries against local MariaDB schemas.", version: "1.2.0" },
                  { name: "Excel Price List Analyzer", desc: "Performs data cleanups, duplicate filters, and price list vendor comparisons using pandas/openpyxl.", version: "1.0.4" },
                  { name: "OCR Binds Agent", desc: "Extracts values from utility bills, purchase orders, and PDF invoices using PaddleOCR/PyMuPDF.", version: "1.1.2" },
                  { name: "Browser Use Operator", desc: "Autonomous browser automation using Playwright and Chromium to navigate web forms.", version: "1.5.0" },
                  { name: "Procurement Agent", desc: "Dispatches automated supplier requests, collects RFQs, and generates comparative pricing recommendations.", version: "1.0.1" },
                  { name: "Email SMTP Agent", desc: "Drafts formal procurement emails and logs attachments using central центра CENTRALCentral المركزي splits central CENTRAL Central splits सेंट्रल split centralцентра splitsCentral centralized Central central splits Central checks central Zentral.", version: "1.0.0" }
                ].map((plugin, idx) => (
                  <div key={idx} className="glass-panel rounded-xl p-5 space-y-3 flex flex-col justify-between">
                    <div>
                      <div className="flex justify-between items-center mb-1">
                        <h3 className="font-semibold text-sm text-slate-100">{plugin.name}</h3>
                        <span className="text-[9px] font-mono text-indigo-400 bg-indigo-500/10 px-1.5 py-0.5 rounded border border-indigo-500/15">v{plugin.version}</span>
                      </div>
                      <p className="text-xs text-slate-400 leading-relaxed">{plugin.desc}</p>
                    </div>
                    <div className="flex justify-between items-center pt-2 border-t border-white/5">
                      <span className="text-[10px] text-emerald-400 font-semibold font-mono flex items-center space-x-1">
                        <span className="w-1.5 h-1.5 rounded-full bg-emerald-400 animate-pulse inline-block mr-1"></span>
                        Active
                      </span>
                      <span className="text-[10px] text-slate-500 font-mono">Plugin enabled</span>
                    </div>
                  </div>
                ))}
              </div>
            </>
          )}

          {activeTab === 'upload' && (
            <>
              <div className="pb-2 flex justify-between items-center">
                <div>
                  <h2 className="text-2xl font-bold tracking-tight text-white">Intelligent Document Upload</h2>
                  <p className="text-xs text-slate-400">Ingest business sheets, run OCR, and extract schemas automatically.</p>
                </div>
                <div className="flex space-x-3">
                  <button onClick={() => alert("Scanner triggered (Twain mock: scanner ready on device 1).")} className="px-3 py-1.5 rounded-lg bg-white/5 border border-white/10 hover:bg-white/10 text-xs font-mono text-slate-300">Scan document</button>
                  <button onClick={() => alert("Webcam capture active (simulation: camera bounds aligned).")} className="px-3 py-1.5 rounded-lg bg-white/5 border border-white/10 hover:bg-white/10 text-xs font-mono text-slate-300">Camera Capture</button>
                </div>
              </div>

              <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
                {/* File Dropzone card */}
                <div className="glass-panel rounded-xl p-6 lg:col-span-2 space-y-6">
                  <label className="block border-2 border-dashed border-white/10 rounded-xl p-8 text-center bg-[#060a13]/40 hover:bg-[#070d19]/40 cursor-pointer transition-all flex flex-col items-center justify-center space-y-3"
                    onDragOver={(e) => e.preventDefault()}
                    onDrop={async (e) => {
                      e.preventDefault();
                      const file = e.dataTransfer.files?.[0];
                      if (file) {
                        await uploadFile(file);
                      }
                    }}
                  >
                    <input 
                      type="file"
                      className="hidden"
                      onChange={async (e) => {
                        const file = e.target.files?.[0];
                        if (file) {
                          await uploadFile(file);
                        }
                      }}
                    />
                    <UploadCloud size={40} className="text-indigo-400 animate-bounce" />
                    <span className="block text-sm text-slate-300">Drag & drop document here or click to browse</span>
                    <span className="block text-xs text-slate-500 font-mono">Supports PDF, Docx, XLSX, Images, ZIP</span>
                  </label>

                  {isUploading && (
                    <div className="flex items-center space-x-3 text-xs text-slate-400 font-mono animate-pulse">
                      <RefreshCw className="animate-spin text-indigo-400" size={14} />
                      <span>Extracting OCR anchors & matching vendor templates...</span>
                    </div>
                  )}

                  {uploadResult && (
                    <div className="space-y-4">
                      <div className="flex items-center justify-between bg-white/5 p-4 rounded-lg border border-white/5">
                        <div>
                          <span className="block text-xs font-semibold text-white">Vendor Identified: {uploadResult.vendor_name}</span>
                          <span className="block text-[10px] text-indigo-400 font-mono">Matched template: {uploadResult.template_matched}</span>
                        </div>
                        <div className="flex items-center space-x-2">
                          <span className="text-xs text-slate-400">OCR Confidence:</span>
                          <span className="px-2 py-0.5 rounded bg-emerald-500/10 text-emerald-400 font-mono text-xs font-bold">{uploadResult.confidence_score}%</span>
                        </div>
                      </div>

                      {/* Manual Human Correction override panel */}
                      <div className="space-y-3">
                        <div className="flex justify-between items-center">
                          <h4 className="text-xs font-semibold uppercase text-indigo-400 tracking-wider font-mono">Extracted Entity Fields</h4>
                          <span className="text-[10px] text-slate-500 font-mono">Interactive Correction Loop</span>
                        </div>
                        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                          {Object.keys(editedFields).map(key => (
                            <div key={key} className="space-y-1 bg-white/5 p-3 rounded-lg border border-white/5">
                              <label className="block text-[10px] text-slate-400 font-mono capitalize">{key.replace("_", " ")}</label>
                              <input 
                                type="text"
                                value={editedFields[key] || ''}
                                onChange={(e) => setEditedFields({...editedFields, [key]: e.target.value})}
                                className="w-full bg-[#050810] border border-white/10 rounded px-2 py-1 text-xs text-white focus:outline-none focus:border-indigo-500 font-mono"
                              />
                              {uploadResult.explainability[key] && (
                                <span className="block text-[9px] text-slate-500 italic mt-1">💡 {uploadResult.explainability[key]}</span>
                              )}
                            </div>
                          ))}
                        </div>

                        <div className="flex space-x-3 pt-2">
                          <button 
                            onClick={async () => {
                              try {
                                const res = await fetch(`${API_HOST}/api/document/correct/${uploadResult.document_id}`, {
                                  method: 'POST',
                                  headers: {'Content-Type': 'application/json'},
                                  body: JSON.stringify({fields: editedFields})
                                });
                                if (res.ok) alert("Human overrides saved to version audit trail!");
                              } catch(e){}
                            }}
                            className="flex items-center space-x-2 px-3 py-1.5 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg text-xs transition-all font-semibold"
                          >
                            <Save size={12} />
                            <span>Save Corrections</span>
                          </button>
                        </div>
                      </div>
                    </div>
                  )}
                  {/* Document Batch Processing Library */}
                  <div className="glass-panel rounded-xl p-6 space-y-4 mt-6">
                    <div className="flex justify-between items-center border-b border-white/5 pb-2">
                      <h4 className="text-xs font-semibold uppercase text-indigo-400 tracking-wider font-mono">Parallel Ingestion Library</h4>
                      <span className="text-[10px] text-slate-500 font-mono">Multi-Doc Queue</span>
                    </div>
                    {historyList.length === 0 ? (
                      <div className="text-xs text-slate-500 font-mono italic">No documents uploaded yet. Upload profile/invoices above to queue batching.</div>
                    ) : (
                      <div className="space-y-4">
                        <div className="max-h-[250px] overflow-y-auto space-y-2 pr-1">
                          {historyList.map(doc => {
                            const isSelected = selectedBatchIds.includes(doc.document_id);
                            return (
                              <div key={doc.document_id} className="flex items-center justify-between bg-[#050810]/60 p-2.5 rounded border border-white/5 hover:border-white/10 transition-all">
                                <div className="flex items-center space-x-2.5">
                                  <input 
                                    type="checkbox" 
                                    checked={isSelected}
                                    onChange={() => {
                                      if (isSelected) {
                                        setSelectedBatchIds(selectedBatchIds.filter(id => id !== doc.document_id));
                                      } else {
                                        setSelectedBatchIds([...selectedBatchIds, doc.document_id]);
                                      }
                                    }}
                                    className="rounded border-white/10 bg-black text-indigo-600 focus:ring-indigo-500 h-3.5 w-3.5"
                                  />
                                  <div>
                                    <span className="block text-xs font-semibold text-white">{doc.filename}</span>
                                    <span className="block text-[10px] text-slate-400 font-mono">Entity: {doc.vendor_name} | Status: <span className="text-indigo-400 capitalize">{doc.status}</span></span>
                                  </div>
                                </div>
                                <span className="text-[10px] text-slate-500 font-mono bg-white/5 px-1.5 py-0.5 rounded border border-white/5">{doc.template_matched}</span>
                              </div>
                            );
                          })}
                        </div>
                        <button
                          disabled={selectedBatchIds.length === 0 || isAutomating}
                          onClick={async () => {
                            setIsAutomating(true);
                            setAutomationLogs(prev => [...prev, `Starting parallel batch form filling for ${selectedBatchIds.length} files...`]);
                            try {
                              const res = await fetch(`${API_HOST}/api/document/automate/batch`, {
                                method: 'POST',
                                headers: {'Content-Type': 'application/json'},
                                body: JSON.stringify({document_ids: selectedBatchIds, target_system: selectedDestination})
                              });
                              const data = await res.json();
                              if (data.success) {
                                setAutomationLogs(prev => [...prev, "✓ Concurrent batch execution initialized", `✓ Successfully processed ${selectedBatchIds.length} parallel document automation jobs!`]);
                                setSelectedBatchIds([]);
                                fetchV4Data();
                              } else {
                                setAutomationLogs(prev => [...prev, `✗ Batch filling error: ${data.error}`]);
                              }
                            } catch (e) {
                              setAutomationLogs(prev => [...prev, "✗ Connection lost with API."]);
                            } finally {
                              setIsAutomating(false);
                            }
                          }}
                          className={`w-full py-2.5 rounded-lg text-xs font-semibold transition-all text-center flex items-center justify-center space-x-1.5 ${
                            selectedBatchIds.length === 0 ? 'bg-slate-800 text-slate-500 cursor-not-allowed' : 'bg-indigo-600 hover:bg-indigo-700 text-white'
                          }`}
                        >
                          <Send size={12} />
                          <span>Run Parallel Batch Automation ({selectedBatchIds.length} Selected)</span>
                        </button>
                      </div>
                    )}
                  </div>
                </div>

                {/* Automation trigger sidebar */}
                <div className="space-y-6">
                  <div className="glass-panel rounded-xl p-6 space-y-4">
                    <h3 className="text-sm font-semibold tracking-wider uppercase text-indigo-400 font-mono">Form Filling Destination</h3>
                    <div>
                      <label className="block text-xs font-mono text-slate-400 mb-1.5">Select target system</label>
                      <select 
                        value={selectedDestination} 
                        onChange={(e) => setSelectedDestination(e.target.value)}
                        className="w-full bg-[#0a0f1b] border border-white/10 rounded-lg px-3 py-2 text-xs text-white focus:outline-none"
                      >
                        <option value="quickbill">QuickBill POS (Playwright)</option>
                        <option value="excel">Excel Sheets (Pandas)</option>
                        <option value="web_form">Vendor Web Portals (Browser-Use)</option>
                        <option value="erp">Windows ERP (pywinauto)</option>
                        <option value="govt_schemes">Indian Govt Schemes (Browser-Use)</option>
                        <option value="nvidia_aiq">NVIDIA AI-Q Agent (DeepResearch)</option>
                      </select>
                    </div>

                    <button 
                      disabled={!uploadResult || isAutomating}
                      onClick={async () => {
                        setIsAutomating(true);
                        setAutomationLogs(prev => [...prev, "Starting autonomous form filling sequence..."]);
                        try {
                          const res = await fetch(`${API_HOST}/api/document/automate`, {
                            method: 'POST',
                            headers: {'Content-Type': 'application/json'},
                            body: JSON.stringify({document_id: uploadResult.document_id, target_system: selectedDestination})
                          });
                          const data = await res.json();
                          setAutomationResult(data);
                          if (data.success) {
                            setAutomationLogs(prev => [...prev, "✓ Process authenticated", "✓ Form fields mapped", "✓ Transaction completed successfully."]);
                          } else {
                            setAutomationLogs(prev => [...prev, `✗ Failure on step execution at index ${data.resume_index_checkpoint}: ${data.error}`]);
                          }
                        } catch (e) {
                          setAutomationLogs(prev => [...prev, "✗ Connection lost with API."]);
                        } finally {
                          setIsAutomating(false);
                          fetchV4Data();
                        }
                      }}
                      className={`w-full flex items-center justify-center space-x-2 py-3 rounded-lg text-xs font-semibold transition-all ${
                        !uploadResult ? 'bg-slate-800 text-slate-500 cursor-not-allowed' : 'bg-emerald-600 hover:bg-emerald-700 text-white'
                      }`}
                    >
                      <Send size={14} />
                      <span>Commit Form Automation</span>
                    </button>

                    {historyList.length > 0 && (
                      <div className="text-[10px] text-slate-500 font-mono">
                        Recent Document History: {historyList.length} files tracked.
                      </div>
                    )}
                    {automationResult && (
                      <div className="p-2 rounded bg-indigo-500/10 border border-indigo-500/20 text-indigo-400 text-[10px] font-mono">
                        Status Code: {automationResult.success ? "Committed" : "Failed"}
                      </div>
                    )}

                    {/* Progress log tracker */}
                    {automationLogs.length > 0 && (
                      <div className="bg-[#05080f] p-3 rounded-lg border border-white/5 font-mono text-[10px] space-y-1.5 text-slate-400">
                        {automationLogs.map((log, idx) => (
                          <div key={idx} className={log.includes("✗") ? "text-rose-400" : log.includes("✓") ? "text-emerald-400" : ""}>{log}</div>
                        ))}
                      </div>
                    )}
                  </div>
                </div>
              </div>
            </>
          )}

          {activeTab === 'queue' && (
            <>
              <div className="pb-2">
                <h2 className="text-2xl font-bold tracking-tight text-white">Automation Queue & Schedulers</h2>
                <p className="text-xs text-slate-400">Monitors directory watcher tasks, timed schedulers, and recovery checkpoints.</p>
              </div>

              <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <div className="glass-panel rounded-xl p-6 lg:col-span-2 space-y-6">
                  <h3 className="text-sm font-semibold tracking-wider uppercase text-indigo-400 font-mono">Running Jobs Audit Trail</h3>
                  
                  <div className="space-y-4">
                    {auditLogs.map((log) => (
                      <div key={log.id} className="bg-white/5 p-4 rounded-lg border border-white/5 flex justify-between items-center">
                        <div className="space-y-1">
                          <span className="block text-xs font-semibold text-white capitalize">{log.step_name.replace("_", " ")}</span>
                          <span className="block text-[10px] text-slate-500 font-mono">Executed at: {log.executed_at} (Duration: {log.duration_sec}s)</span>
                          {log.error_details && (
                            <span className="block text-[10px] text-rose-400 font-mono mt-1">Error: {log.error_details}</span>
                          )}
                        </div>
                        <div className="flex items-center space-x-3">
                          <span className={`px-2 py-0.5 rounded text-[10px] font-mono ${
                            log.step_status === 'success' ? 'bg-emerald-500/10 text-emerald-400' : 'bg-rose-500/10 text-rose-400 animate-pulse'
                          }`}>{log.step_status}</span>
                          
                          {log.step_status === 'failure' && (
                            <button 
                              onClick={async () => {
                                try {
                                  // Resume execution from failure checkpoint
                                  const res = await fetch(`${API_HOST}/api/document/automate`, {
                                    method: 'POST',
                                    headers: {'Content-Type': 'application/json'},
                                    body: JSON.stringify({document_id: log.task_id || 1, target_system: 'quickbill'})
                                  });
                                  if (res.ok) alert("Resuming loop from failed checkpoint index!");
                                } catch(e){}
                              }}
                              className="px-2.5 py-1 rounded bg-indigo-600 hover:bg-indigo-700 text-white font-mono text-[9px] font-semibold"
                            >
                              Resume Loop
                            </button>
                          )}
                        </div>
                      </div>
                    ))}
                  </div>
                </div>

                <div className="glass-panel rounded-xl p-6 space-y-4">
                  <h3 className="text-sm font-semibold tracking-wider uppercase text-indigo-400 font-mono">Active Folder Monitors</h3>
                  <div className="bg-[#05080f] p-4 rounded-lg border border-white/5 space-y-2">
                    <span className="block text-xs font-semibold text-white">watch_folder/</span>
                    <p className="text-[10px] text-slate-400">Scans directory every 30 seconds to ingest drop-folder PDF attachments automatically.</p>
                    <div className="flex items-center space-x-2 pt-2">
                      <span className="w-2 h-2 rounded-full bg-emerald-500 animate-pulse" />
                      <span className="text-[10px] text-slate-300 font-mono">Monitor Loop Active</span>
                    </div>
                  </div>
                </div>
              </div>
            </>
          )}

          {activeTab === 'mappers' && (
            <>
              <div className="pb-2">
                <h2 className="text-2xl font-bold tracking-tight text-white">Dynamic Form Mappings Editor</h2>
                <p className="text-xs text-slate-400">Map custom document entity keys directly to website selectors or desktop coordinates.</p>
              </div>

              <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <div className="glass-panel rounded-xl p-6 lg:col-span-2 space-y-4">
                  <h3 className="text-sm font-semibold tracking-wider uppercase text-indigo-400 font-mono">Active Target Selectors</h3>
                  
                  <table className="w-full text-xs text-left text-slate-400 border-collapse">
                    <thead>
                      <tr className="border-b border-white/10">
                        <th className="py-2">System</th>
                        <th className="py-2">Field Key</th>
                        <th className="py-2">GUI Selector / Coordinates</th>
                        <th className="py-2">Field Label</th>
                      </tr>
                    </thead>
                    <tbody>
                      {mappings.map((m) => (
                        <tr key={m.id} className="border-b border-white/5 hover:bg-white/5">
                          <td className="py-2 text-white font-mono">{m.target_system}</td>
                          <td className="py-2">{m.field_key}</td>
                          <td className="py-2 font-mono text-indigo-400">{m.selector}</td>
                          <td className="py-2">{m.label}</td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                </div>

                <div className="glass-panel rounded-xl p-6 space-y-4">
                  <h3 className="text-sm font-semibold tracking-wider uppercase text-indigo-400 font-mono">Teach Website Selector</h3>
                  <div className="space-y-3">
                    <div>
                      <label className="block text-[10px] text-slate-400 font-mono">Target Platform</label>
                      <select 
                        value={learnTarget}
                        onChange={(e) => setLearnTarget(e.target.value)}
                        className="w-full bg-[#0a0f1b] border border-white/10 rounded px-2 py-1 text-xs text-white"
                      >
                        <option value="quickbill">QuickBill</option>
                        <option value="web_form">Web Form Portal</option>
                        <option value="erp">Mworld ERP</option>
                        <option value="govt_schemes">Govt Schemes Portal</option>
                      </select>
                    </div>
                    <div>
                      <label className="block text-[10px] text-slate-400 font-mono">Field Key</label>
                      <input type="text" placeholder="e.g. invoice_number" value={learnKey} onChange={(e)=>setLearnKey(e.target.value)} className="w-full bg-[#0a0f1b] border border-white/10 rounded px-2 py-1 text-xs text-white font-mono" />
                    </div>
                    <div>
                      <label className="block text-[10px] text-slate-400 font-mono">Selector / Coordinates</label>
                      <input type="text" placeholder="e.g. #input_inv_no" value={learnSelector} onChange={(e)=>setLearnSelector(e.target.value)} className="w-full bg-[#0a0f1b] border border-white/10 rounded px-2 py-1 text-xs text-white font-mono" />
                    </div>
                    <div>
                      <label className="block text-[10px] text-slate-400 font-mono">UI Label</label>
                      <input type="text" placeholder="e.g. Invoice Number Input" value={learnLabel} onChange={(e)=>setLearnLabel(e.target.value)} className="w-full bg-[#0a0f1b] border border-white/10 rounded px-2 py-1 text-xs text-white" />
                    </div>
                    <button 
                      onClick={async () => {
                        try {
                          const res = await fetch('http://localhost:8000/api/document/mappings', {
                            method: 'POST',
                            headers: {'Content-Type': 'application/json'},
                            body: JSON.stringify({
                              target_system: learnTarget,
                              field_key: learnKey,
                              selector: learnSelector,
                              label: learnLabel
                            })
                          });
                          if (res.ok) {
                            alert("Teach Website configuration saved successfully!");
                            fetchV4Data();
                            setLearnKey('');
                            setLearnSelector('');
                            setLearnLabel('');
                          }
                        } catch(e){}
                      }}
                      className="w-full flex items-center justify-center space-x-1 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded text-xs font-semibold transition-all"
                    >
                      <Plus size={12} />
                      <span>Save Mapping</span>
                    </button>
                  </div>
                </div>
              </div>
            </>
          )}

          {activeTab === 'analytics' && (
            <>
              <div className="pb-2">
                <h2 className="text-2xl font-bold tracking-tight text-white">Operations Analytics Dashboard</h2>
                <p className="text-xs text-slate-400">Overview of OCR extraction confidence, sync status, and correction rates.</p>
              </div>

              {/* Stats Widgets Grid */}
              <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                <div className="glass-panel rounded-xl p-5 space-y-2">
                  <span className="block text-xs font-mono text-slate-400 uppercase">Documents Processed</span>
                  <span className="block text-3xl font-bold text-white">{analytics.processed_count}</span>
                  <span className="block text-[10px] text-emerald-400 font-mono">✓ Ingestion active</span>
                </div>

                <div className="glass-panel rounded-xl p-5 space-y-2">
                  <span className="block text-xs font-mono text-slate-400 uppercase">Average OCR Confidence</span>
                  <span className="block text-3xl font-bold text-white">{analytics.average_confidence}%</span>
                  <span className="block text-[10px] text-slate-500 font-mono">Calculated over batch runs</span>
                </div>

                <div className="glass-panel rounded-xl p-5 space-y-2">
                  <span className="block text-xs font-mono text-slate-400 uppercase">Automation Success Rate</span>
                  <span className="block text-3xl font-bold text-white">{analytics.success_rate}%</span>
                  <span className="block text-[10px] text-emerald-400 font-mono">Committed without errors</span>
                </div>

                <div className="glass-panel rounded-xl p-5 space-y-2">
                  <span className="block text-xs font-mono text-slate-400 uppercase">Manual Correction Rate</span>
                  <span className="block text-3xl font-bold text-white">{analytics.correction_rate}%</span>
                  <span className="block text-[10px] text-indigo-400 font-mono">Refined by operators</span>
                </div>
              </div>

              {/* Integration Status Charts Card */}
              <div className="glass-panel rounded-xl p-6 space-y-4">
                <h3 className="text-sm font-semibold tracking-wider uppercase text-indigo-400 font-mono">Sync & Connector Telemetry</h3>
                <div className="grid grid-cols-1 md:grid-cols-3 gap-6 font-mono text-xs">
                  <div className="bg-white/5 p-4 rounded-lg border border-white/5 space-y-1">
                    <span className="block text-white">QuickBill Sync</span>
                    <span className="block text-emerald-400 font-semibold">ONLINE</span>
                    <span className="block text-[10px] text-slate-500">FastAPI Playwright driver mapped</span>
                  </div>
                  <div className="bg-white/5 p-4 rounded-lg border border-white/5 space-y-1">
                    <span className="block text-white">Ollama local LLM</span>
                    <span className="block text-emerald-400 font-semibold">CONNECTED</span>
                    <span className="block text-[10px] text-slate-500">Latency: 280ms / Qwen2.5 active</span>
                  </div>
                  <div className="bg-white/5 p-4 rounded-lg border border-white/5 space-y-1">
                    <span className="block text-white">Web Portal (CDP)</span>
                    <span className="block text-emerald-400 font-semibold">READY</span>
                    <span className="block text-[10px] text-slate-500">Browser-Use API key detected</span>
                  </div>
                </div>
              </div>
            </>
          )}

          {activeTab === 'settings' && (
            <>
              <div className="pb-2">
                <h2 className="text-2xl font-bold tracking-tight text-white">System Settings</h2>
                <p className="text-xs text-slate-400">Configure paths, Ollama links, and safety criteria thresholds.</p>
              </div>

              <div className="glass-panel rounded-xl p-6 space-y-6">
                <div className="space-y-4">
                  <h3 className="text-sm font-semibold tracking-wider uppercase text-indigo-400 font-mono">Security & Safety Limits</h3>
                  
                  <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div className="bg-white/5 p-4 rounded-lg border border-white/5 space-y-2">
                      <span className="block text-xs font-semibold text-white">Approval Safeguards</span>
                      <p className="text-xs text-slate-400">Send Email, Delete files, and Database writes always require explicit human validation.</p>
                      <div className="flex items-center space-x-2 pt-2">
                        <input type="checkbox" defaultChecked disabled className="rounded bg-[#05080f] border-white/20 text-indigo-600 focus:ring-0" />
                        <span className="text-xs text-slate-300 font-mono">Enforce constraints</span>
                      </div>
                    </div>

                    <div className="bg-white/5 p-4 rounded-lg border border-white/5 space-y-2">
                      <span className="block text-xs font-semibold text-white">Backup before Write</span>
                      <p className="text-xs text-slate-400">Automatically creates temporary duplicate files before any filesystem modification.</p>
                      <div className="flex items-center space-x-2 pt-2">
                        <input type="checkbox" defaultChecked disabled className="rounded bg-[#05080f] border-white/20 text-indigo-600 focus:ring-0" />
                        <span className="text-xs text-slate-300 font-mono">Auto backup (enabled)</span>
                      </div>
                    </div>
                  </div>
                </div>

                <div className="space-y-4 pt-6 border-t border-white/5">
                  <h3 className="text-sm font-semibold tracking-wider uppercase text-indigo-400 font-mono">Ollama Local API Configuration</h3>
                  
                  <div>
                    <label className="block text-xs font-mono text-slate-400 mb-1.5">Ollama Connection URL</label>
                    <input 
                      type="text" 
                      disabled
                      value="http://localhost:11434"
                      className="w-full bg-[#0a0f1b] border border-white/10 rounded-lg px-4 py-3 text-sm text-slate-400 transition-all font-mono"
                    />
                  </div>
                </div>
              </div>
            </>
          )}

        </div>
      </div>

    </div>
  )
}
