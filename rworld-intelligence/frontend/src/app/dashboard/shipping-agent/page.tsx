"use client";

import { useEffect, useState } from "react";

interface ShippingJob {
  id: number;
  email_subject: string;
  sender: string;
  raw_body: string;
  extracted_address: string;
  ocr_text: string;
  status: string;
  created_at: string;
}

interface Store {
  id: number;
  store_code: string;
  store_name: string;
  city: string;
}

interface SavedAddress {
  id: number;
  store_id: number;
  address_type: string;
  name: string;
  company: string;
  address1: string;
  address2?: string;
  city: string;
  state: string;
  zip: string;
  phone: string;
  email: string;
}

interface LabelRequest {
  id: number;
  request_number: string;
  store_id: number;
  store_name: string;
  store_code: string;
  ship_from_name: string;
  ship_from_company: string;
  ship_from_address1: string;
  ship_from_address2?: string;
  ship_from_city: string;
  ship_from_state: string;
  ship_from_zip: string;
  ship_from_phone: str;
  ship_from_email?: string;
  ship_to_name: string;
  ship_to_company: string;
  ship_to_address1: string;
  ship_to_address2?: string;
  ship_to_city: string;
  ship_to_state: string;
  ship_to_zip: string;
  ship_to_phone: string;
  ship_to_email?: string;
  sales_order_number: string;
  request_reference?: string;
  length: number;
  width: number;
  height: number;
  weight_lbs: number;
  shipping_method: string;
  customer_freight_charge: number;
  special_instructions?: string;
  internal_notes?: string;
  status: string;
  tracking_number?: string;
  carrier?: string;
  label_file?: string;
  easyship_cost?: number;
  created_at: string;
}

interface Quote {
  courier_id: string;
  courier_name: string;
  shipment_charge: number;
  delivery_time: string;
}

export default function ShippingPage() {
  const [activeTab, setActiveTab] = useState<"portal" | "ai_queue">("portal");
  const [activeSubTab, setActiveSubTab] = useState<"list" | "create">("list");
  
  // Existing AI Shipping Jobs queue states
  const [jobs, setJobs] = useState<ShippingJob[]>([]);
  const [subject, setSubject] = useState("");
  const [sender, setSender] = useState("");
  const [body, setBody] = useState("");
  const [loading, setLoading] = useState(false);
  const [aiMessage, setAiMessage] = useState("");
  const [selectedJob, setSelectedJob] = useState<ShippingJob | null>(null);
  const [ocrText, setOcrText] = useState("");

  // Shipping Portal states
  const [requests, setRequests] = useState<LabelRequest[]>([]);
  const [stores, setStores] = useState<Store[]>([]);
  const [savedAddresses, setSavedAddresses] = useState<SavedAddress[]>([]);
  const [quotes, setQuotes] = useState<Quote[]>([]);
  const [loadingQuotes, setLoadingQuotes] = useState(false);
  const [submittingRequest, setSubmittingRequest] = useState(false);
  const [portalMessage, setPortalMessage] = useState("");
  const [buyingLabelId, setBuyingLabelId] = useState<number | null>(null);
  const [buyingStatus, setBuyingStatus] = useState("");

  // Filters
  const [filterSearch, setFilterSearch] = useState("");
  const [filterStore, setFilterStore] = useState("");
  const [filterStatus, setFilterStatus] = useState("");

  // Form States (Label Request Create)
  const [selStoreId, setSelStoreId] = useState<number>(0);
  
  const [fromName, setFromName] = useState("");
  const [fromCompany, setFromCompany] = useState("");
  const [fromAddr1, setFromAddr1] = useState("");
  const [fromAddr2, setFromAddr2] = useState("");
  const [fromCity, setFromCity] = useState("");
  const [fromState, setFromState] = useState("");
  const [fromZip, setFromZip] = useState("");
  const [fromPhone, setFromPhone] = useState("");
  const [fromEmail, setFromEmail] = useState("");

  const [toName, setToName] = useState("");
  const [toCompany, setToCompany] = useState("");
  const [toAddr1, setToAddr1] = useState("");
  const [toAddr2, setToAddr2] = useState("");
  const [toCity, setToCity] = useState("");
  const [toState, setToState] = useState("");
  const [toZip, setToZip] = useState("");
  const [toPhone, setToPhone] = useState("");
  const [toEmail, setToEmail] = useState("");

  const [soNum, setSoNum] = useState("");
  const [reqRef, setReqRef] = useState("");
  
  const [pkgLen, setPkgLen] = useState<number>(12);
  const [pkgWid, setPkgWid] = useState<number>(10);
  const [pkgHgt, setPkgHgt] = useState<number>(8);
  const [pkgWgt, setPkgWgt] = useState<number>(15);
  
  const [shipMethod, setShipMethod] = useState("");
  const [freightCharge, setFreightCharge] = useState<number>(15.00);
  const [instructions, setInstructions] = useState("");
  const [internalNotes, setInternalNotes] = useState("");

  const fetchData = async () => {
    const token = localStorage.getItem("rworld_token");
    if (!token) return;

    try {
      // 1. Fetch AI jobs
      const jobsResp = await fetch("${process.env.NEXT_PUBLIC_API_URL || 'http://localhost:8000'}/api/shipping-agent/jobs", {
        headers: { Authorization: `Bearer ${token}` },
      });
      if (jobsResp.ok) setJobs(await jobsResp.json());

      // 2. Fetch Label Requests
      const reqsResp = await fetch("${process.env.NEXT_PUBLIC_API_URL || 'http://localhost:8000'}/api/shipping-agent/requests", {
        headers: { Authorization: `Bearer ${token}` },
      });
      if (reqsResp.ok) setRequests(await reqsResp.json());

      // 3. Fetch Stores
      const storesResp = await fetch("${process.env.NEXT_PUBLIC_API_URL || 'http://localhost:8000'}/api/attendance/stores", {
        headers: { Authorization: `Bearer ${token}` },
      });
      if (storesResp.ok) {
        const storeData = await storesResp.json();
        setStores(storeData);
        if (storeData.length > 0 && selStoreId === 0) {
          setSelStoreId(storeData[0].id);
        }
      }
    } catch (err) {
      console.error("Error fetching shipping details:", err);
    }
  };

  useEffect(() => {
    fetchData();
  }, []);

  // Fetch saved addresses when store is changed
  useEffect(() => {
    if (selStoreId === 0) return;
    const token = localStorage.getItem("rworld_token");
    fetch(`${process.env.NEXT_PUBLIC_API_URL || 'http://localhost:8000'}/api/shipping-agent/addresses?store_id=${selStoreId}`, {
      headers: { Authorization: `Bearer ${token}` },
    })
      .then((res) => res.json())
      .then((data) => setSavedAddresses(data))
      .catch((err) => console.error("Error loading addresses:", err));
  }, [selStoreId]);

  // Handle AI Ingest
  const handleIngest = async (e: React.FormEvent) => {
    e.preventDefault();
    setLoading(true);
    setAiMessage("");
    const token = localStorage.getItem("rworld_token");
    try {
      const response = await fetch("${process.env.NEXT_PUBLIC_API_URL || 'http://localhost:8000'}/api/shipping-agent/jobs/ingest", {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
          Authorization: `Bearer ${token}`,
        },
        body: JSON.stringify({ subject, sender, body }),
      });
      const data = await response.json();
      if (!response.ok) throw new Error(data.detail || "Failed to ingest");
      setAiMessage(`Success! Address Extracted: "${data.extracted_address}"`);
      setSubject("");
      setSender("");
      setBody("");
      fetchData();
    } catch (err: any) {
      setAiMessage(`Error: ${err.message}`);
    } finally {
      setLoading(false);
    }
  };

  // Handle OCR Update
  const handleOCRSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!selectedJob) return;
    const token = localStorage.getItem("rworld_token");
    try {
      const response = await fetch("${process.env.NEXT_PUBLIC_API_URL || 'http://localhost:8000'}/api/shipping-agent/jobs/ocr", {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
          Authorization: `Bearer ${token}`,
        },
        body: JSON.stringify({ job_id: selectedJob.id, raw_ocr_text: ocrText }),
      });
      if (response.ok) {
        setOcrText("");
        setSelectedJob(null);
        fetchData();
      }
    } catch (err) {
      console.error("Error processing OCR:", err);
    }
  };

  // Address auto-fill
  const handleSelectAddress = (addr: SavedAddress, type: "from" | "to") => {
    if (type === "from") {
      setFromName(addr.name);
      setFromCompany(addr.company);
      setFromAddr1(addr.address1);
      setFromAddr2(addr.address2 || "");
      setFromCity(addr.city);
      setFromState(addr.state);
      setFromZip(addr.zip);
      setFromPhone(addr.phone || "");
      setFromEmail(addr.email || "");
    } else {
      setToName(addr.name);
      setToCompany(addr.company);
      setToAddr1(addr.address1);
      setToAddr2(addr.address2 || "");
      setToCity(addr.city);
      setToState(addr.state);
      setToZip(addr.zip);
      setToPhone(addr.phone || "");
      setToEmail(addr.email || "");
    }
  };

  // Fetch quotes
  const handleFetchQuotes = async () => {
    if (!fromZip || !toZip) {
      alert("Please provide both sender and receiver zip codes first.");
      return;
    }
    setLoadingQuotes(true);
    setQuotes([]);
    const token = localStorage.getItem("rworld_token");
    try {
      const response = await fetch(
        `${process.env.NEXT_PUBLIC_API_URL || 'http://localhost:8000'}/api/shipping-agent/rates?length=${pkgLen}&width=${pkgWid}&height=${pkgHgt}&weight_lbs=${pkgWgt}&ship_from_zip=${fromZip}&ship_to_zip=${toZip}`,
        { headers: { Authorization: `Bearer ${token}` } }
      );
      if (response.ok) {
        setQuotes(await response.json());
      } else {
        throw new Error("Unable to fetch shipping quotes.");
      }
    } catch (err: any) {
      alert(err.message);
    } finally {
      setLoadingQuotes(false);
    }
  };

  // Submit shipping request
  const handleSubmitRequest = async (e: React.FormEvent) => {
    e.preventDefault();
    setPortalMessage("");
    setSubmittingRequest(true);
    const token = localStorage.getItem("rworld_token");

    try {
      const response = await fetch("${process.env.NEXT_PUBLIC_API_URL || 'http://localhost:8000'}/api/shipping-agent/requests", {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
          Authorization: `Bearer ${token}`,
        },
        body: JSON.stringify({
          store_id: selStoreId,
          ship_from_name: fromName,
          ship_from_company: fromCompany,
          ship_from_address1: fromAddr1,
          ship_from_address2: fromAddr2,
          ship_from_city: fromCity,
          ship_from_state: fromState,
          ship_from_zip: fromZip,
          ship_from_phone: fromPhone,
          ship_from_email: fromEmail,
          ship_to_name: toName,
          ship_to_company: toCompany,
          ship_to_address1: toAddr1,
          ship_to_address2: toAddr2,
          ship_to_city: toCity,
          ship_to_state: toState,
          ship_to_zip: toZip,
          ship_to_phone: toPhone,
          ship_to_email: toEmail,
          sales_order_number: soNum,
          request_reference: reqRef,
          length: pkgLen,
          width: pkgWid,
          height: pkgHgt,
          weight_lbs: pkgWgt,
          shipping_method: shipMethod || "UPS Ground",
          customer_freight_charge: freightCharge,
          special_instructions: instructions,
          internal_notes: internalNotes,
        }),
      });

      const data = await response.json();
      if (!response.ok) throw new Error(data.detail || "Failed to register request");

      setPortalMessage("Shipping label request created successfully!");
      // Reset form
      setFromName("");
      setFromCompany("");
      setFromAddr1("");
      setFromAddr2("");
      setFromCity("");
      setFromState("");
      setFromZip("");
      setFromPhone("");
      setFromEmail("");
      setToName("");
      setToCompany("");
      setToAddr1("");
      setToAddr2("");
      setToCity("");
      setToState("");
      setToZip("");
      setToPhone("");
      setToEmail("");
      setSoNum("");
      setReqRef("");
      setQuotes([]);
      setShipMethod("");
      
      setActiveSubTab("list");
      fetchData();
    } catch (err: any) {
      setPortalMessage(`Error: ${err.message}`);
    } finally {
      setSubmittingRequest(false);
    }
  };

  // Buy label
  const handleBuyLabel = async (reqId: number, quote: Quote) => {
    setBuyingLabelId(reqId);
    setBuyingStatus("Buying...");
    const token = localStorage.getItem("rworld_token");

    try {
      const response = await fetch(`${process.env.NEXT_PUBLIC_API_URL || 'http://localhost:8000'}/api/shipping-agent/requests/${reqId}/buy`, {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
          Authorization: `Bearer ${token}`,
        },
        body: JSON.stringify({
          courier_id: quote.courier_id,
          courier_name: quote.courier_name,
          actual_cost: quote.shipment_charge,
        }),
      });

      if (response.ok) {
        setBuyingStatus("Success!");
        fetchData();
      } else {
        const data = await response.json();
        throw new Error(data.detail || "Failed to buy label");
      }
    } catch (err: any) {
      alert(`Purchase Error: ${err.message}`);
    } finally {
      setTimeout(() => {
        setBuyingLabelId(null);
        setBuyingStatus("");
      }, 1500);
    }
  };

  // Cancel label request
  const handleCancelRequest = async (reqId: number) => {
    if (!confirm("Are you sure you want to cancel this shipping request?")) return;
    const token = localStorage.getItem("rworld_token");
    try {
      const response = await fetch(`${process.env.NEXT_PUBLIC_API_URL || 'http://localhost:8000'}/api/shipping-agent/requests/${reqId}/cancel`, {
        method: "POST",
        headers: { Authorization: `Bearer ${token}` },
      });
      if (response.ok) {
        fetchData();
      }
    } catch (err) {
      console.error(err);
    }
  };

  // Export CSV
  const handleExportCSV = () => {
    const headers = [
      "VENDORS /STORES", "DATE", "TRACKING NO.", "REF NO.1", "F NO.", 
      "RECEIVER", "UPS CHARGE", "Delivery date", "SHIPPING FROM", 
      "weight", "DIMENSION", "freight charge from cmr", "Remark", "PACKAGE LOST"
    ];

    const rows = filteredRequests.map((r) => {
      const dimension = `${r.length}x${r.width}x${r.height}`;
      const receiver = `${r.ship_to_name}${r.ship_to_company ? " - " + r.ship_to_company : ""}`;
      const shipFrom = `${r.ship_from_name}${r.ship_from_company ? " - " + r.ship_from_company : ""}`;
      const remark = r.internal_notes || r.special_instructions || "";
      const lost = r.status === "Cancelled" ? "Yes" : "No";
      
      return [
        r.store_name,
        r.created_at.split("T")[0],
        r.tracking_number || "Not Provided",
        r.sales_order_number,
        r.request_reference || "",
        receiver,
        r.easyship_cost !== null ? r.easyship_cost?.toFixed(2) : "0.00",
        r.created_at.split("T")[0], // delivery mock date
        shipFrom,
        r.weight_lbs,
        dimension,
        r.customer_freight_charge.toFixed(2),
        remark,
        lost
      ];
    });

    const csvContent = "data:text/csv;charset=utf-8," 
      + [headers.join(","), ...rows.map(e => e.map(val => `"${val}"`).join(","))].join("\n");
      
    const encodedUri = encodeURI(csvContent);
    const link = document.createElement("a");
    link.setAttribute("href", encodedUri);
    link.setAttribute("download", `artee_shipping_requests_${new Date().toISOString().split("T")[0]}.csv`);
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
  };

  // Filter requests
  const filteredRequests = requests.filter((r) => {
    const matchSearch = filterSearch === "" || 
      r.request_number.toLowerCase().includes(filterSearch.toLowerCase()) ||
      r.sales_order_number.toLowerCase().includes(filterSearch.toLowerCase()) ||
      (r.tracking_number && r.tracking_number.toLowerCase().includes(filterSearch.toLowerCase())) ||
      r.ship_to_name.toLowerCase().includes(filterSearch.toLowerCase());
      
    const matchStore = filterStore === "" || String(r.store_id) === filterStore;
    const matchStatus = filterStatus === "" || r.status === filterStatus;
    
    return matchSearch && matchStore && matchStatus;
  });

  return (
    <div className="space-y-8">
      {/* Top Main Tabs */}
      <div className="flex border-b border-gray-900 space-x-6">
        <button
          onClick={() => setActiveTab("portal")}
          className={`pb-4 px-2 font-bold cursor-pointer text-sm transition-colors ${
            activeTab === "portal" ? "border-b-2 border-cyan-500 text-cyan-400" : "text-gray-400 hover:text-white"
          }`}
        >
          Shipping Portal Hub
        </button>
        <button
          onClick={() => setActiveTab("ai_queue")}
          className={`pb-4 px-2 font-bold cursor-pointer text-sm transition-colors ${
            activeTab === "ai_queue" ? "border-b-2 border-cyan-500 text-cyan-400" : "text-gray-400 hover:text-white"
          }`}
        >
          AI Ingest & OCR Operations
        </button>
      </div>

      {activeTab === "portal" ? (
        <div className="space-y-6">
          {/* Sub menu controls */}
          <div className="flex justify-between items-center bg-gray-950/40 p-4 border border-gray-900 rounded-xl">
            <div className="flex space-x-4">
              <button
                onClick={() => setActiveSubTab("list")}
                className={`py-1.5 px-4 rounded text-xs font-semibold cursor-pointer transition-all ${
                  activeSubTab === "list" ? "bg-cyan-500/15 text-cyan-400 border border-cyan-500/30" : "text-gray-400 hover:text-white border border-transparent"
                }`}
              >
                Requests Summary
              </button>
              <button
                onClick={() => setActiveSubTab("create")}
                className={`py-1.5 px-4 rounded text-xs font-semibold cursor-pointer transition-all ${
                  activeSubTab === "create" ? "bg-cyan-500/15 text-cyan-400 border border-cyan-500/30" : "text-gray-400 hover:text-white border border-transparent"
                }`}
              >
                Create Request
              </button>
            </div>
            {activeSubTab === "list" && (
              <button
                onClick={handleExportCSV}
                className="py-1.5 px-4 bg-emerald-600 hover:bg-emerald-500 text-white rounded text-xs font-semibold transition-colors cursor-pointer"
              >
                📥 Export CSV
              </button>
            )}
          </div>

          {activeSubTab === "list" && (
            <div className="space-y-6">
              {/* Stats Summary */}
              <div className="grid grid-cols-4 gap-4">
                <div className="glass-panel p-4 rounded-xl text-center">
                  <div className="text-xs text-gray-500">Total Requests</div>
                  <div className="text-2xl font-bold text-gray-200">{requests.length}</div>
                </div>
                <div className="glass-panel p-4 rounded-xl text-center">
                  <div className="text-xs text-gray-500">Pending Quotes</div>
                  <div className="text-2xl font-bold text-yellow-400">
                    {requests.filter(r => r.status === "Pending").length}
                  </div>
                </div>
                <div className="glass-panel p-4 rounded-xl text-center">
                  <div className="text-xs text-gray-500">Labels Printed</div>
                  <div className="text-2xl font-bold text-cyan-400">
                    {requests.filter(r => r.status === "Label Created").length}
                  </div>
                </div>
                <div className="glass-panel p-4 rounded-xl text-center">
                  <div className="text-xs text-gray-500">Cancelled</div>
                  <div className="text-2xl font-bold text-red-400">
                    {requests.filter(r => r.status === "Cancelled").length}
                  </div>
                </div>
              </div>

              {/* Filters Panel */}
              <div className="glass-panel p-4 rounded-xl grid grid-cols-1 md:grid-cols-4 gap-4 items-end">
                <div className="space-y-1">
                  <label className="text-xs text-gray-400 font-semibold">Search keyword</label>
                  <input
                    value={filterSearch}
                    onChange={(e) => setFilterSearch(e.target.value)}
                    className="w-full px-3 py-1.5 bg-gray-900 border border-gray-800 rounded text-xs text-white"
                    placeholder="Search Request/SO/Tracking..."
                  />
                </div>
                <div className="space-y-1">
                  <label className="text-xs text-gray-400 font-semibold">Store Location</label>
                  <select
                    value={filterStore}
                    onChange={(e) => setFilterStore(e.target.value)}
                    className="w-full px-3 py-1.5 bg-gray-900 border border-gray-800 rounded text-xs text-white"
                  >
                    <option value="">All Stores</option>
                    {stores.map((s) => (
                      <option key={s.id} value={s.id}>
                        {s.store_name} ({s.city})
                      </option>
                    ))}
                  </select>
                </div>
                <div className="space-y-1">
                  <label className="text-xs text-gray-400 font-semibold">Status</label>
                  <select
                    value={filterStatus}
                    onChange={(e) => setFilterStatus(e.target.value)}
                    className="w-full px-3 py-1.5 bg-gray-900 border border-gray-800 rounded text-xs text-white"
                  >
                    <option value="">All Statuses</option>
                    <option value="Pending">Pending</option>
                    <option value="Label Created">Label Created</option>
                    <option value="Cancelled">Cancelled</option>
                  </select>
                </div>
                <button
                  onClick={() => { setFilterSearch(""); setFilterStore(""); setFilterStatus(""); }}
                  className="py-1.5 bg-gray-900 hover:bg-gray-850 text-gray-400 hover:text-white border border-gray-800 rounded text-xs transition-colors cursor-pointer"
                >
                  Clear Filters
                </button>
              </div>

              {/* Requests Ledger Table */}
              <div className="glass-panel p-6 rounded-xl overflow-x-auto">
                <table className="w-full text-left border-collapse text-xs">
                  <thead>
                    <tr className="border-b border-gray-800 text-gray-500 uppercase font-semibold">
                      <th className="pb-3">Request #</th>
                      <th className="pb-3">Store Location</th>
                      <th className="pb-3">Sender / Recipient</th>
                      <th className="pb-3">Sales Order</th>
                      <th className="pb-3 text-right">Freight Charged</th>
                      <th className="pb-3 text-center">Status</th>
                      <th className="pb-3 text-right">Actions</th>
                    </tr>
                  </thead>
                  <tbody className="divide-y divide-gray-900 text-gray-300">
                    {filteredRequests.map((r) => (
                      <tr key={r.id} className="hover:bg-gray-900/10">
                        <td className="py-4 font-mono font-bold text-gray-200">
                          {r.request_number}
                          <div className="text-[10px] text-gray-500 font-mono font-normal">
                            {r.created_at.split("T")[0]}
                          </div>
                        </td>
                        <td className="py-4">
                          <span className="font-semibold text-gray-400">{r.store_name}</span>
                          <span className="text-[10px] text-gray-500 ml-1">({r.store_code})</span>
                        </td>
                        <td className="py-4 max-w-xs truncate">
                          <div className="font-semibold text-gray-300">
                            From: {r.ship_from_company} ({r.ship_from_city}, {r.ship_from_state})
                          </div>
                          <div className="text-[10px] text-gray-500">
                            To: {r.ship_to_name} - {r.ship_to_company} ({r.ship_to_city}, {r.ship_to_state})
                          </div>
                        </td>
                        <td className="py-4">
                          <div className="font-mono">{r.sales_order_number}</div>
                          {r.request_reference && (
                            <div className="text-[10px] text-gray-500 font-mono">Ref: {r.request_reference}</div>
                          )}
                        </td>
                        <td className="py-4 text-right font-mono font-bold text-gray-200">
                          ${r.customer_freight_charge.toFixed(2)}
                        </td>
                        <td className="py-4 text-center">
                          <span className={`text-[10px] px-2 py-0.5 rounded-full border ${
                            r.status === "Label Created"
                              ? "bg-emerald-950/40 border-emerald-500/20 text-emerald-400"
                              : r.status === "Cancelled"
                              ? "bg-red-950/40 border-red-500/20 text-red-400"
                              : "bg-yellow-950/40 border-yellow-500/20 text-yellow-400"
                          }`}>
                            {r.status}
                          </span>
                        </td>
                        <td className="py-4 text-right space-y-1">
                          {r.status === "Pending" ? (
                            <div className="flex flex-col space-y-1 items-end">
                              <button
                                onClick={async () => {
                                  // Auto fetch mock rate and buy Ground
                                  const mockGroundQuote = {
                                    courier_id: "ups_ground",
                                    courier_name: "UPS Ground",
                                    shipment_charge: round(12.50 + r.weight_lbs * 0.45 + r.length * 0.1, 2),
                                    delivery_time: "2-3 business days"
                                  };
                                  // Helper to round
                                  function round(num: number, dec: number) {
                                    return Math.round(num * Math.pow(10, dec)) / Math.pow(10, dec);
                                  }
                                  handleBuyLabel(r.id, mockGroundQuote);
                                }}
                                className="py-1 px-3 bg-cyan-600 hover:bg-cyan-500 text-white rounded font-semibold transition-colors cursor-pointer text-[10px]"
                              >
                                {buyingLabelId === r.id ? buyingStatus : "Approve & Buy Label"}
                              </button>
                              <button
                                onClick={() => handleCancelRequest(r.id)}
                                className="text-[10px] text-red-400 hover:text-red-300 font-semibold cursor-pointer"
                              >
                                Cancel
                              </button>
                            </div>
                          ) : r.status === "Label Created" ? (
                            <div className="space-y-1 text-right">
                              <a
                                href={r.label_file}
                                target="_blank"
                                download="label.pdf"
                                className="inline-block py-1 px-2.5 bg-gray-900 hover:bg-gray-800 text-cyan-400 hover:text-cyan-300 border border-cyan-500/20 rounded font-semibold transition-colors text-[10px]"
                              >
                                Download Label PDF
                              </a>
                              <div className="text-[10px] text-gray-500 font-mono max-w-[120px] truncate" title={r.tracking_number}>
                                Trk: {r.tracking_number}
                              </div>
                            </div>
                          ) : (
                            <span className="text-gray-500 font-mono text-[10px]">Cancelled</span>
                          )}
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            </div>
          )}

          {activeSubTab === "create" && (
            <div className="glass-panel p-6 rounded-xl space-y-6">
              <h3 className="text-lg font-bold text-gray-200 border-b border-gray-900 pb-3">Create Label Request Form</h3>
              {portalMessage && (
                <div className="p-3 bg-cyan-950/40 border border-cyan-500/20 rounded-lg text-cyan-300 text-sm text-center">
                  {portalMessage}
                </div>
              )}
              <form onSubmit={handleSubmitRequest} className="space-y-6">
                <div className="grid grid-cols-1 md:grid-cols-2 gap-8">
                  {/* Ship From */}
                  <div className="space-y-4">
                    <div className="flex justify-between items-center">
                      <h4 className="text-sm font-bold text-cyan-400 uppercase tracking-wider">Ship From Address</h4>
                      <select
                        onChange={(e) => {
                          const addr = savedAddresses.find((a) => String(a.id) === e.target.value);
                          if (addr) handleSelectAddress(addr, "from");
                        }}
                        className="px-2 py-1 bg-gray-900 border border-gray-800 rounded text-xs text-gray-400 focus:outline-none"
                      >
                        <option value="">Use Saved Address</option>
                        {savedAddresses.filter((a) => a.address_type === "from").map((a) => (
                          <option key={a.id} value={a.id}>
                            {a.company} ({a.city})
                          </option>
                        ))}
                      </select>
                    </div>

                    <div className="space-y-1">
                      <label className="text-[10px] text-gray-500 uppercase font-semibold">Store / Address Location</label>
                      <select
                        value={selStoreId}
                        onChange={(e) => setSelStoreId(parseInt(e.target.value))}
                        className="w-full px-3 py-2 bg-gray-900 border border-gray-800 rounded text-xs text-white"
                      >
                        {stores.map((s) => (
                          <option key={s.id} value={s.id}>
                            {s.store_name} ({s.city})
                          </option>
                        ))}
                      </select>
                    </div>

                    <div className="grid grid-cols-2 gap-4">
                      <div className="space-y-1">
                        <label className="text-[10px] text-gray-500 uppercase font-semibold">Contact Name</label>
                        <input
                          required
                          value={fromName}
                          onChange={(e) => setFromName(e.target.value)}
                          className="w-full px-3 py-2 bg-gray-900 border border-gray-800 rounded text-xs text-white"
                        />
                      </div>
                      <div className="space-y-1">
                        <label className="text-[10px] text-gray-500 uppercase font-semibold">Company</label>
                        <input
                          required
                          value={fromCompany}
                          onChange={(e) => setFromCompany(e.target.value)}
                          className="w-full px-3 py-2 bg-gray-900 border border-gray-800 rounded text-xs text-white"
                        />
                      </div>
                    </div>

                    <div className="space-y-1">
                      <label className="text-[10px] text-gray-500 uppercase font-semibold">Address Line 1</label>
                      <input
                        required
                        value={fromAddr1}
                        onChange={(e) => setFromAddr1(e.target.value)}
                        className="w-full px-3 py-2 bg-gray-900 border border-gray-800 rounded text-xs text-white"
                      />
                    </div>
                    
                    <div className="space-y-1">
                      <label className="text-[10px] text-gray-500 uppercase font-semibold">Address Line 2 (Optional)</label>
                      <input
                        value={fromAddr2}
                        onChange={(e) => setFromAddr2(e.target.value)}
                        className="w-full px-3 py-2 bg-gray-900 border border-gray-800 rounded text-xs text-white"
                      />
                    </div>

                    <div className="grid grid-cols-3 gap-4">
                      <div className="space-y-1">
                        <label className="text-[10px] text-gray-500 uppercase font-semibold">City</label>
                        <input
                          required
                          value={fromCity}
                          onChange={(e) => setFromCity(e.target.value)}
                          className="w-full px-3 py-2 bg-gray-900 border border-gray-800 rounded text-xs text-white"
                        />
                      </div>
                      <div className="space-y-1">
                        <label className="text-[10px] text-gray-500 uppercase font-semibold">State</label>
                        <input
                          required
                          value={fromState}
                          onChange={(e) => setFromState(e.target.value)}
                          className="w-full px-3 py-2 bg-gray-900 border border-gray-800 rounded text-xs text-white"
                        />
                      </div>
                      <div className="space-y-1">
                        <label className="text-[10px] text-gray-500 uppercase font-semibold">ZIP Code</label>
                        <input
                          required
                          value={fromZip}
                          onChange={(e) => setFromZip(e.target.value)}
                          className="w-full px-3 py-2 bg-gray-900 border border-gray-800 rounded text-xs text-white"
                        />
                      </div>
                    </div>

                    <div className="grid grid-cols-2 gap-4">
                      <div className="space-y-1">
                        <label className="text-[10px] text-gray-500 uppercase font-semibold">Phone</label>
                        <input
                          required
                          value={fromPhone}
                          onChange={(e) => setFromPhone(e.target.value)}
                          className="w-full px-3 py-2 bg-gray-900 border border-gray-800 rounded text-xs text-white"
                        />
                      </div>
                      <div className="space-y-1">
                        <label className="text-[10px] text-gray-500 uppercase font-semibold">Email</label>
                        <input
                          type="email"
                          value={fromEmail}
                          onChange={(e) => setFromEmail(e.target.value)}
                          className="w-full px-3 py-2 bg-gray-900 border border-gray-800 rounded text-xs text-white"
                        />
                      </div>
                    </div>
                  </div>

                  {/* Ship To */}
                  <div className="space-y-4">
                    <div className="flex justify-between items-center">
                      <h4 className="text-sm font-bold text-cyan-400 uppercase tracking-wider">Ship To Address</h4>
                      <select
                        onChange={(e) => {
                          const addr = savedAddresses.find((a) => String(a.id) === e.target.value);
                          if (addr) handleSelectAddress(addr, "to");
                        }}
                        className="px-2 py-1 bg-gray-900 border border-gray-800 rounded text-xs text-gray-400 focus:outline-none"
                      >
                        <option value="">Use Saved Address</option>
                        {savedAddresses.filter((a) => a.address_type === "to").map((a) => (
                          <option key={a.id} value={a.id}>
                            {a.company} ({a.city})
                          </option>
                        ))}
                      </select>
                    </div>

                    <div className="grid grid-cols-2 gap-4">
                      <div className="space-y-1">
                        <label className="text-[10px] text-gray-500 uppercase font-semibold">Recipient Name</label>
                        <input
                          required
                          value={toName}
                          onChange={(e) => setToName(e.target.value)}
                          className="w-full px-3 py-2 bg-gray-900 border border-gray-800 rounded text-xs text-white"
                        />
                      </div>
                      <div className="space-y-1">
                        <label className="text-[10px] text-gray-500 uppercase font-semibold">Company</label>
                        <input
                          required
                          value={toCompany}
                          onChange={(e) => setToCompany(e.target.value)}
                          className="w-full px-3 py-2 bg-gray-900 border border-gray-800 rounded text-xs text-white"
                        />
                      </div>
                    </div>

                    <div className="space-y-1">
                      <label className="text-[10px] text-gray-500 uppercase font-semibold">Address Line 1</label>
                      <input
                        required
                        value={toAddr1}
                        onChange={(e) => setToAddr1(e.target.value)}
                        className="w-full px-3 py-2 bg-gray-900 border border-gray-800 rounded text-xs text-white"
                      />
                    </div>

                    <div className="space-y-1">
                      <label className="text-[10px] text-gray-500 uppercase font-semibold">Address Line 2 (Optional)</label>
                      <input
                        value={toAddr2}
                        onChange={(e) => setToAddr2(e.target.value)}
                        className="w-full px-3 py-2 bg-gray-900 border border-gray-800 rounded text-xs text-white"
                      />
                    </div>

                    <div className="grid grid-cols-3 gap-4">
                      <div className="space-y-1">
                        <label className="text-[10px] text-gray-500 uppercase font-semibold">City</label>
                        <input
                          required
                          value={toCity}
                          onChange={(e) => setToCity(e.target.value)}
                          className="w-full px-3 py-2 bg-gray-900 border border-gray-800 rounded text-xs text-white"
                        />
                      </div>
                      <div className="space-y-1">
                        <label className="text-[10px] text-gray-500 uppercase font-semibold">State</label>
                        <input
                          required
                          value={toState}
                          onChange={(e) => setToState(e.target.value)}
                          className="w-full px-3 py-2 bg-gray-900 border border-gray-800 rounded text-xs text-white"
                        />
                      </div>
                      <div className="space-y-1">
                        <label className="text-[10px] text-gray-500 uppercase font-semibold">ZIP Code</label>
                        <input
                          required
                          value={toZip}
                          onChange={(e) => setToZip(e.target.value)}
                          className="w-full px-3 py-2 bg-gray-900 border border-gray-800 rounded text-xs text-white"
                        />
                      </div>
                    </div>

                    <div className="grid grid-cols-2 gap-4">
                      <div className="space-y-1">
                        <label className="text-[10px] text-gray-500 uppercase font-semibold">Phone</label>
                        <input
                          required
                          value={toPhone}
                          onChange={(e) => setToPhone(e.target.value)}
                          className="w-full px-3 py-2 bg-gray-900 border border-gray-800 rounded text-xs text-white"
                        />
                      </div>
                      <div className="space-y-1">
                        <label className="text-[10px] text-gray-500 uppercase font-semibold">Email Address</label>
                        <input
                          required
                          type="email"
                          value={toEmail}
                          onChange={(e) => setToEmail(e.target.value)}
                          className="w-full px-3 py-2 bg-gray-900 border border-gray-800 rounded text-xs text-white"
                        />
                      </div>
                    </div>
                  </div>
                </div>

                {/* Package & Billing Specs */}
                <div className="border-t border-gray-900 pt-6 space-y-4">
                  <h4 className="text-sm font-bold text-cyan-400 uppercase tracking-wider">Package Dimensions & Billing</h4>
                  
                  <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
                    <div className="space-y-1">
                      <label className="text-[10px] text-gray-500 uppercase font-semibold">Length (in)</label>
                      <input
                        type="number"
                        required
                        value={pkgLen}
                        onChange={(e) => setPkgLen(parseFloat(e.target.value))}
                        className="w-full px-3 py-2 bg-gray-900 border border-gray-800 rounded text-xs text-white font-mono"
                      />
                    </div>
                    <div className="space-y-1">
                      <label className="text-[10px] text-gray-500 uppercase font-semibold">Width (in)</label>
                      <input
                        type="number"
                        required
                        value={pkgWid}
                        onChange={(e) => setPkgWid(parseFloat(e.target.value))}
                        className="w-full px-3 py-2 bg-gray-900 border border-gray-800 rounded text-xs text-white font-mono"
                      />
                    </div>
                    <div className="space-y-1">
                      <label className="text-[10px] text-gray-500 uppercase font-semibold">Height (in)</label>
                      <input
                        type="number"
                        required
                        value={pkgHgt}
                        onChange={(e) => setPkgHgt(parseFloat(e.target.value))}
                        className="w-full px-3 py-2 bg-gray-900 border border-gray-800 rounded text-xs text-white font-mono"
                      />
                    </div>
                    <div className="space-y-1">
                      <label className="text-[10px] text-gray-500 uppercase font-semibold">Weight (lbs)</label>
                      <input
                        type="number"
                        step="0.1"
                        required
                        value={pkgWgt}
                        onChange={(e) => setPkgWgt(parseFloat(e.target.value))}
                        className="w-full px-3 py-2 bg-gray-900 border border-gray-800 rounded text-xs text-white font-mono"
                      />
                    </div>
                  </div>

                  <div className="grid grid-cols-1 md:grid-cols-3 gap-6 items-end">
                    <div className="space-y-1">
                      <label className="text-[10px] text-gray-500 uppercase font-semibold">Customer Freight Charge ($)</label>
                      <input
                        type="number"
                        step="0.01"
                        required
                        value={freightCharge}
                        onChange={(e) => setFreightCharge(parseFloat(e.target.value))}
                        className={`w-full px-3 py-2 bg-gray-900 border rounded text-xs text-white font-mono ${
                          freightCharge < 15.00 ? "border-red-500 focus:ring-red-500" : "border-gray-800 focus:ring-cyan-500"
                        }`}
                      />
                      {freightCharge < 15.00 && (
                        <div className="text-[10px] text-red-400 mt-1 font-bold">Minimum threshold is $15.00!</div>
                      )}
                    </div>

                    <div className="space-y-1">
                      <label className="text-[10px] text-gray-500 uppercase font-semibold">Shipping Service Code</label>
                      <input
                        required
                        value={shipMethod}
                        onChange={(e) => setShipMethod(e.target.value)}
                        className="w-full px-3 py-2 bg-gray-900 border border-gray-800 rounded text-xs text-white"
                        placeholder="Select service from quotes..."
                      />
                    </div>

                    <button
                      type="button"
                      onClick={handleFetchQuotes}
                      disabled={loadingQuotes}
                      className="py-2.5 px-4 bg-gray-900 border border-gray-850 hover:border-cyan-500/30 text-cyan-400 hover:text-cyan-300 font-semibold rounded text-xs transition-all cursor-pointer"
                    >
                      {loadingQuotes ? "Fetching Courier Rates..." : "Get Live Quotes"}
                    </button>
                  </div>
                </div>

                {/* Quotes comparison list */}
                {quotes.length > 0 && (
                  <div className="space-y-3 bg-gray-950/20 p-4 border border-gray-900 rounded-xl">
                    <h5 className="text-xs font-bold text-gray-300">Live Available Courier Rates</h5>
                    <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                      {quotes.map((q) => (
                        <div
                          key={q.courier_id}
                          onClick={() => setShipMethod(q.courier_name)}
                          className={`p-3 border rounded-lg cursor-pointer transition-all ${
                            shipMethod === q.courier_name 
                              ? "bg-cyan-950/30 border-cyan-500/80" 
                              : "bg-gray-950 border-gray-900 hover:border-gray-800"
                          }`}
                        >
                          <div className="text-xs font-bold text-gray-200">{q.courier_name}</div>
                          <div className="text-[10px] text-gray-500 font-mono mt-1">{q.delivery_time}</div>
                          <div className="text-sm font-bold text-emerald-400 font-mono mt-2">${q.shipment_charge.toFixed(2)}</div>
                        </div>
                      ))}
                    </div>
                  </div>
                )}

                {/* Order metadata */}
                <div className="grid grid-cols-2 gap-4">
                  <div className="space-y-1">
                    <label className="text-[10px] text-gray-500 uppercase font-semibold">Sales Order Number (F No.)</label>
                    <input
                      required
                      value={soNum}
                      onChange={(e) => setSoNum(e.target.value)}
                      className="w-full px-3 py-2 bg-gray-900 border border-gray-800 rounded text-xs text-white font-mono"
                      placeholder="e.g. SO-10023"
                    />
                  </div>
                  <div className="space-y-1">
                    <label className="text-[10px] text-gray-500 uppercase font-semibold">Customer Reference / F No.</label>
                    <input
                      value={reqRef}
                      onChange={(e) => setReqRef(e.target.value)}
                      className="w-full px-3 py-2 bg-gray-900 border border-gray-800 rounded text-xs text-white font-mono"
                      placeholder="e.g. F-98210"
                    />
                  </div>
                </div>

                <div className="grid grid-cols-2 gap-4">
                  <div className="space-y-1">
                    <label className="text-[10px] text-gray-500 uppercase font-semibold">Special Instructions</label>
                    <textarea
                      value={instructions}
                      onChange={(e) => setInstructions(e.target.value)}
                      className="w-full px-3 py-2 bg-gray-900 border border-gray-800 rounded text-xs text-white h-20 resize-none focus:outline-none"
                    />
                  </div>
                  <div className="space-y-1">
                    <label className="text-[10px] text-gray-500 uppercase font-semibold">Internal Notes</label>
                    <textarea
                      value={internalNotes}
                      onChange={(e) => setInternalNotes(e.target.value)}
                      className="w-full px-3 py-2 bg-gray-900 border border-gray-800 rounded text-xs text-white h-20 resize-none focus:outline-none"
                    />
                  </div>
                </div>

                <button
                  type="submit"
                  disabled={submittingRequest || freightCharge < 15.00}
                  className="w-full py-2.5 bg-gradient-to-r from-cyan-500 to-emerald-500 hover:from-cyan-600 hover:to-emerald-600 text-white font-bold rounded text-sm transition-all disabled:opacity-50 disabled:cursor-not-allowed cursor-pointer"
                >
                  {submittingRequest ? "Registering Label Request..." : "Submit Shipping Request"}
                </button>
              </form>
            </div>
          )}
        </div>
      ) : (
        /* AI Shipping Jobs Queue (Existing UI) */
        <div className="grid grid-cols-1 lg:grid-cols-3 gap-8">
          {/* Email Ingest Simulator */}
          <div className="lg:col-span-1 glass-panel rounded-xl p-6 h-fit space-y-6">
            <h3 className="text-lg font-bold text-gray-200">Email Ingestion Simulator</h3>
            {aiMessage && (
              <div className="p-3 bg-cyan-950/40 border border-cyan-500/20 rounded-lg text-cyan-300 text-sm text-center">
                {aiMessage}
              </div>
            )}
            <form onSubmit={handleIngest} className="space-y-4">
              <div className="space-y-1">
                <label className="text-xs text-gray-400 font-semibold">Sender Email</label>
                <input
                  required
                  type="email"
                  value={sender}
                  onChange={(e) => setSender(e.target.value)}
                  className="w-full px-3 py-2 bg-gray-900 border border-gray-800 rounded text-sm text-white focus:outline-none focus:ring-1 focus:ring-cyan-500"
                  placeholder="shipper@example.com"
                />
              </div>

              <div className="space-y-1">
                <label className="text-xs text-gray-400 font-semibold">Subject Title</label>
                <input
                  required
                  value={subject}
                  onChange={(e) => setSubject(e.target.value)}
                  className="w-full px-3 py-2 bg-gray-900 border border-gray-800 rounded text-sm text-white focus:outline-none focus:ring-1 focus:ring-cyan-500"
                  placeholder="Fulfillment Order #9910"
                />
              </div>

              <div className="space-y-1">
                <label className="text-xs text-gray-400 font-semibold">Email Body</label>
                <textarea
                  required
                  rows={5}
                  value={body}
                  onChange={(e) => setBody(e.target.value)}
                  className="w-full px-3 py-2 bg-gray-900 border border-gray-800 rounded text-sm text-white focus:outline-none focus:ring-1 focus:ring-cyan-500"
                  placeholder="Please dispatch the cargo.&#10;Ship To: 104 Main St, Boston, MA 02110"
                />
              </div>

              <button
                type="submit"
                disabled={loading}
                className="w-full py-2.5 bg-gradient-to-r from-cyan-500 to-emerald-500 hover:from-cyan-600 hover:to-emerald-600 text-white font-semibold rounded text-sm cursor-pointer"
              >
                {loading ? "Processing Email..." : "Ingest & Parse"}
              </button>
            </form>
          </div>

          {/* Shipping Jobs Queue */}
          <div className="lg:col-span-2 glass-panel rounded-xl p-6">
            <h3 className="text-lg font-bold text-gray-200 mb-6">Operations Queue</h3>
            {jobs.length === 0 ? (
              <p className="text-sm text-gray-500">No operations items registered.</p>
            ) : (
              <table className="w-full text-left border-collapse">
                <thead>
                  <tr className="border-b border-gray-800 text-xs font-semibold text-gray-400 uppercase tracking-wider">
                    <th className="pb-3">ID</th>
                    <th className="pb-3">Subject</th>
                    <th className="pb-3">Extracted Address</th>
                    <th className="pb-3">Status</th>
                    <th className="pb-3">Actions</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-gray-900 text-sm">
                  {jobs.map((job) => (
                    <tr key={job.id} className="hover:bg-gray-900/20 transition-colors">
                      <td className="py-4 font-mono text-xs text-gray-500">#{job.id}</td>
                      <td className="py-4 font-semibold text-gray-200">
                        <div className="text-sm">{job.email_subject}</div>
                        <div className="text-xs text-gray-500">From: {job.sender}</div>
                      </td>
                      <td className="py-4 text-cyan-400 text-xs max-w-xs truncate" title={job.extracted_address}>
                        {job.extracted_address}
                      </td>
                      <td className="py-4">
                        <span className={`text-xs px-2 py-1 rounded-full border ${
                          job.status === "completed" 
                            ? "bg-emerald-950/40 border-emerald-500/20 text-emerald-400"
                            : "bg-cyan-950/40 border-cyan-500/20 text-cyan-400"
                        }`}>
                          {job.status}
                        </span>
                      </td>
                      <td className="py-4 font-mono">
                        {job.status !== "completed" ? (
                          <button
                            onClick={() => {
                              setSelectedJob(job);
                              setOcrText(job.ocr_text || "");
                            }}
                            className="text-xs px-2 py-1 bg-cyan-950 border border-cyan-500/30 text-cyan-300 rounded hover:bg-cyan-900 cursor-pointer"
                          >
                            OCR
                          </button>
                        ) : (
                          <span className="text-gray-500 font-mono text-xs">Processed</span>
                        )}
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            )}

            {/* Selected Job OCR Panel */}
            {selectedJob && (
              <div className="mt-8 border-t border-gray-900 pt-6 space-y-4">
                <h4 className="text-sm font-bold text-gray-300">Run OCR / Address Matching on Job #{selectedJob.id}</h4>
                <form onSubmit={handleOCRSubmit} className="space-y-4">
                  <div className="space-y-1">
                    <label className="text-xs text-gray-400">Handwritten Label OCR Text Stream</label>
                    <textarea
                      required
                      rows={4}
                      value={ocrText}
                      onChange={(e) => setOcrText(e.target.value)}
                      className="w-full px-3 py-2 bg-gray-900 border border-gray-800 rounded text-xs text-white focus:outline-none"
                      placeholder="Line 1: QUEYEN TRONG&#10;Line 2: 184 FOREST LANE..."
                    />
                  </div>
                  <div className="flex space-x-2">
                    <button
                      type="submit"
                      className="py-1.5 px-4 bg-cyan-600 hover:bg-cyan-500 text-white rounded text-xs font-semibold cursor-pointer"
                    >
                      Save Extracted Address Data
                    </button>
                    <button
                      type="button"
                      onClick={() => setSelectedJob(null)}
                      className="py-1.5 px-4 bg-gray-900 border border-gray-850 hover:border-gray-800 text-gray-400 rounded text-xs font-semibold cursor-pointer"
                    >
                      Cancel
                    </button>
                  </div>
                </form>
              </div>
            )}
          </div>
        </div>
      )}
    </div>
  );
}
