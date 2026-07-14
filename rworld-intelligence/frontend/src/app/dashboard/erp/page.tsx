"use client";

import { useEffect, useState } from "react";

interface InventoryItem {
  id: number;
  sku: string;
  name: string;
  category: string;
  quantity: number;
  price: number;
  location: string;
  description: string;
}

interface Customer {
  id: number;
  name: string;
  email: string;
  phone: string;
  address: string;
}

interface Order {
  id: number;
  customer_id: number;
  customer_name: string;
  total_amount: number;
  status: string;
  created_at: string;
}

interface SalesReturn {
  id: number;
  order_id: number;
  customer_name: string;
  return_date: string;
  amount: number;
  notes: string;
}

interface PurchaseOrder {
  id: number;
  po_number: string;
  supplier_name: string;
  item_sku: string;
  quantity: number;
  unit_cost: number;
  status: string;
  created_at: string;
}

interface GRNLog {
  id: number;
  po_id: number;
  po_number: string;
  supplier_name: string;
  item_sku: string;
  received_date: string;
  received_quantity: number;
  notes: string;
}

interface ActiveShift {
  id: number;
  cashier_name: string;
  open_time: string;
  close_time?: string;
  open_balance: number;
  close_balance: number;
  status: string;
}

interface PLReport {
  revenue: number;
  returns: number;
  net_sales: number;
  cost_of_goods: number;
  gross_profit: number;
}

interface GSTReport {
  taxable_turnover: number;
  total_tax_liability: number;
  cgst: number;
  sgst: number;
  igst: number;
}

interface StockReport {
  total_stock_valuation: number;
  low_stock_alerts: InventoryItem[];
}

interface DaybookRecord {
  tx_type: string;
  tx_id: number;
  date: string;
  amount: number;
  details: string;
}

export default function ERPPage() {
  const [activeTab, setActiveTab] = useState<"inventory" | "sales" | "purchasing" | "shifts" | "reports">("inventory");
  const [activeReportTab, setActiveReportTab] = useState<"pl" | "daybook" | "stock" | "gst">("pl");
  const [loading, setLoading] = useState(true);

  // Core Lists
  const [inventory, setInventory] = useState<InventoryItem[]>([]);
  const [customers, setCustomers] = useState<Customer[]>([]);
  const [orders, setOrders] = useState<Order[]>([]);
  const [returns, setReturns] = useState<SalesReturn[]>([]);
  const [purchaseOrders, setPurchaseOrders] = useState<PurchaseOrder[]>([]);
  const [grns, setGrns] = useState<GRNLog[]>([]);
  const [activeShift, setActiveShift] = useState<ActiveShift | null>(null);

  // Report States
  const [plReport, setPlReport] = useState<PLReport | null>(null);
  const [gstReport, setGstReport] = useState<GSTReport | null>(null);
  const [stockReport, setStockReport] = useState<StockReport | null>(null);
  const [daybook, setDaybook] = useState<DaybookRecord[]>([]);

  // Form Message States
  const [invMsg, setInvMsg] = useState("");
  const [salesMsg, setSalesMsg] = useState("");
  const [purchMsg, setPurchMsg] = useState("");
  const [shiftMsg, setShiftMsg] = useState("");

  // Inventory Form State
  const [sku, setSku] = useState("");
  const [name, setName] = useState("");
  const [category, setCategory] = useState("");
  const [quantity, setQuantity] = useState(0);
  const [price, setPrice] = useState(0.0);
  const [location, setLocation] = useState("");
  const [desc, setDesc] = useState("");

  // Customer & Sales Order Form State
  const [selCustId, setSelCustId] = useState<number>(0);
  const [orderAmt, setOrderAmt] = useState<number>(0.0);
  const [orderStatus, setOrderStatus] = useState("completed");
  const [retOrderId, setRetOrderId] = useState<number>(0);
  const [retAmt, setRetAmt] = useState<number>(0.0);
  const [retNotes, setRetNotes] = useState("");

  // Purchase Order & GRN Form State
  const [poSupplier, setPoSupplier] = useState("");
  const [poSku, setPoSku] = useState("");
  const [poQty, setPoQty] = useState<number>(100);
  const [poCost, setPoCost] = useState<number>(10.00);
  const [grnPoId, setGrnPoId] = useState<number>(0);
  const [grnQty, setGrnQty] = useState<number>(100);
  const [grnNotes, setGrnNotes] = useState("");

  // Shift Form State
  const [cashierName, setCashierName] = useState("");
  const [openBalance, setOpenBalance] = useState<number>(150.00);
  const [closeBalance, setCloseBalance] = useState<number>(0.00);

  const fetchERPData = async () => {
    const token = localStorage.getItem("rworld_token");
    if (!token) return;
    setLoading(true);

    try {
      // 1. Fetch Inventory
      const invResponse = await fetch("/api/erp/inventory", {
        headers: { Authorization: `Bearer ${token}` },
      });
      if (invResponse.ok) {
        const invData = await invResponse.json();
        setInventory(invData);
        if (invData.length > 0) setPoSku(invData[0].sku);
      }

      // 2. Fetch Customers
      const custResponse = await fetch("/api/erp/customers", {
        headers: { Authorization: `Bearer ${token}` },
      });
      if (custResponse.ok) {
        const custData = await custResponse.json();
        setCustomers(custData);
        if (custData.length > 0) setSelCustId(custData[0].id);
      }

      // 3. Fetch Orders
      const ordResponse = await fetch("/api/erp/orders", {
        headers: { Authorization: `Bearer ${token}` },
      });
      if (ordResponse.ok) {
        const ordData = await ordResponse.json();
        setOrders(ordData);
        if (ordData.length > 0) setRetOrderId(ordData[0].id);
      }

      // 4. Fetch Returns
      const retResponse = await fetch("/api/erp/returns", {
        headers: { Authorization: `Bearer ${token}` },
      });
      if (retResponse.ok) setReturns(await retResponse.json());

      // 5. Fetch POs
      const poResponse = await fetch("/api/erp/purchase-orders", {
        headers: { Authorization: `Bearer ${token}` },
      });
      if (poResponse.ok) {
        const poData = await poResponse.json();
        setPurchaseOrders(poData);
        const pendingPos = poData.filter((p: any) => p.status === "pending");
        if (pendingPos.length > 0) setGrnPoId(pendingPos[0].id);
      }

      // 6. Fetch GRNs
      const grnResponse = await fetch("/api/erp/grn", {
        headers: { Authorization: `Bearer ${token}` },
      });
      if (grnResponse.ok) setGrns(await grnResponse.json());

      // 7. Fetch active shift
      const shiftResponse = await fetch("/api/erp/shifts/active", {
        headers: { Authorization: `Bearer ${token}` },
      });
      if (shiftResponse.ok) {
        const shiftData = await shiftResponse.json();
        setActiveShift(shiftData || null);
      }

      // 8. Fetch Reports
      const plResp = await fetch("/api/erp/reports/pl", {
        headers: { Authorization: `Bearer ${token}` },
      });
      if (plResp.ok) setPlReport(await plResp.json());

      const gstResp = await fetch("/api/erp/reports/gst", {
        headers: { Authorization: `Bearer ${token}` },
      });
      if (gstResp.ok) setGstReport(await gstResp.json());

      const stockResp = await fetch("/api/erp/reports/stock", {
        headers: { Authorization: `Bearer ${token}` },
      });
      if (stockResp.ok) setStockReport(await stockResp.json());

      const dbResp = await fetch("/api/erp/reports/daybook", {
        headers: { Authorization: `Bearer ${token}` },
      });
      if (dbResp.ok) setDaybook(await dbResp.json());

    } catch (err) {
      console.error("Error loading ERP data:", err);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchERPData();
  }, []);

  // Submit Inventory
  const handleAddInventory = async (e: React.FormEvent) => {
    e.preventDefault();
    setInvMsg("");
    const token = localStorage.getItem("rworld_token");
    try {
      const response = await fetch("/api/erp/inventory", {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
          Authorization: `Bearer ${token}`,
        },
        body: JSON.stringify({ sku, name, category, quantity, price, location, description: desc }),
      });
      const data = await response.json();
      if (!response.ok) throw new Error(data.detail || "Failed to add item");
      setInvMsg("Product registered successfully!");
      setSku(""); setName(""); setCategory(""); setQuantity(0); setPrice(0.0); setLocation(""); setDesc("");
      fetchERPData();
    } catch (err: any) {
      setInvMsg(`Error: ${err.message}`);
    }
  };

  // Submit Order
  const handleAddOrder = async (e: React.FormEvent) => {
    e.preventDefault();
    setSalesMsg("");
    const token = localStorage.getItem("rworld_token");
    try {
      const response = await fetch("/api/erp/orders", {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
          Authorization: `Bearer ${token}`,
        },
        body: JSON.stringify({ customer_id: selCustId, total_amount: orderAmt, status: orderStatus }),
      });
      if (!response.ok) {
        const data = await response.json();
        throw new Error(data.detail || "Failed to log order");
      }
      setSalesMsg("Sales order logged successfully!");
      setOrderAmt(0.0);
      fetchERPData();
    } catch (err: any) {
      setSalesMsg(`Error: ${err.message}`);
    }
  };

  // Submit Return
  const handleAddReturn = async (e: React.FormEvent) => {
    e.preventDefault();
    setSalesMsg("");
    const token = localStorage.getItem("rworld_token");
    try {
      const response = await fetch("/api/erp/returns", {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
          Authorization: `Bearer ${token}`,
        },
        body: JSON.stringify({ order_id: retOrderId, amount: retAmt, notes: retNotes }),
      });
      const data = await response.json();
      if (!response.ok) throw new Error(data.detail || "Failed to log return");
      setSalesMsg("Sales return logged successfully!");
      setRetAmt(0.0); setRetNotes("");
      fetchERPData();
    } catch (err: any) {
      setSalesMsg(`Error: ${err.message}`);
    }
  };

  // Submit Purchase Order
  const handleAddPO = async (e: React.FormEvent) => {
    e.preventDefault();
    setPurchMsg("");
    const token = localStorage.getItem("rworld_token");
    try {
      const response = await fetch("/api/erp/purchase-orders", {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
          Authorization: `Bearer ${token}`,
        },
        body: JSON.stringify({ supplier_name: poSupplier, item_sku: poSku, quantity: poQty, unit_cost: poCost }),
      });
      const data = await response.json();
      if (!response.ok) throw new Error(data.detail || "Failed to raise PO");
      setPurchMsg("Purchase Order created successfully!");
      setPoSupplier("");
      fetchERPData();
    } catch (err: any) {
      setPurchMsg(`Error: ${err.message}`);
    }
  };

  // Submit GRN
  const handleAddGRN = async (e: React.FormEvent) => {
    e.preventDefault();
    setPurchMsg("");
    const token = localStorage.getItem("rworld_token");
    try {
      const response = await fetch("/api/erp/grn", {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
          Authorization: `Bearer ${token}`,
        },
        body: JSON.stringify({ po_id: grnPoId, received_quantity: grnQty, notes: grnNotes }),
      });
      const data = await response.json();
      if (!response.ok) throw new Error(data.detail || "Failed to log GRN");
      setPurchMsg("GRN logged successfully. Inventory quantities successfully adjusted!");
      setGrnNotes("");
      fetchERPData();
    } catch (err: any) {
      setPurchMsg(`Error: ${err.message}`);
    }
  };

  // Open register shift
  const handleOpenShift = async (e: React.FormEvent) => {
    e.preventDefault();
    setShiftMsg("");
    const token = localStorage.getItem("rworld_token");
    try {
      const response = await fetch("/api/erp/shifts/open", {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
          Authorization: `Bearer ${token}`,
        },
        body: JSON.stringify({ cashier_name: cashierName, open_balance: openBalance }),
      });
      const data = await response.json();
      if (!response.ok) throw new Error(data.detail || "Open shift failed");
      setShiftMsg("Cash register opened successfully!");
      setCashierName("");
      fetchERPData();
    } catch (err: any) {
      setShiftMsg(`Error: ${err.message}`);
    }
  };

  // Close shift
  const handleCloseShift = async (e: React.FormEvent) => {
    e.preventDefault();
    setShiftMsg("");
    const token = localStorage.getItem("rworld_token");
    try {
      const response = await fetch("/api/erp/shifts/close", {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
          Authorization: `Bearer ${token}`,
        },
        body: JSON.stringify({ close_balance: closeBalance }),
      });
      const data = await response.json();
      if (!response.ok) throw new Error(data.detail || "Close shift failed");
      setShiftMsg("Register closed successfully. Till locked!");
      setCloseBalance(0.00);
      fetchERPData();
    } catch (err: any) {
      setShiftMsg(`Error: ${err.message}`);
    }
  };

  return (
    <div className="space-y-8">
      {/* Top Menu Tabs */}
      <div className="flex border-b border-gray-900 space-x-6">
        <button
          onClick={() => setActiveTab("inventory")}
          className={`pb-4 px-2 font-bold cursor-pointer text-sm transition-colors ${
            activeTab === "inventory" ? "border-b-2 border-cyan-500 text-cyan-400" : "text-gray-400 hover:text-white"
          }`}
        >
          Inventory & Products
        </button>
        <button
          onClick={() => setActiveTab("sales")}
          className={`pb-4 px-2 font-bold cursor-pointer text-sm transition-colors ${
            activeTab === "sales" ? "border-b-2 border-cyan-500 text-cyan-400" : "text-gray-400 hover:text-white"
          }`}
        >
          Sales & Returns
        </button>
        <button
          onClick={() => setActiveTab("purchasing")}
          className={`pb-4 px-2 font-bold cursor-pointer text-sm transition-colors ${
            activeTab === "purchasing" ? "border-b-2 border-cyan-500 text-cyan-400" : "text-gray-400 hover:text-white"
          }`}
        >
          Purchasing & GRN
        </button>
        <button
          onClick={() => setActiveTab("shifts")}
          className={`pb-4 px-2 font-bold cursor-pointer text-sm transition-colors ${
            activeTab === "shifts" ? "border-b-2 border-cyan-500 text-cyan-400" : "text-gray-400 hover:text-white"
          }`}
        >
          Shift register
        </button>
        <button
          onClick={() => setActiveTab("reports")}
          className={`pb-4 px-2 font-bold cursor-pointer text-sm transition-colors ${
            activeTab === "reports" ? "border-b-2 border-cyan-500 text-cyan-400" : "text-gray-400 hover:text-white"
          }`}
        >
          Reports Hub
        </button>
      </div>

      {loading ? (
        <div className="text-center py-12">
          <div className="animate-spin rounded-full h-8 w-8 border-t-2 border-b-2 border-cyan-500 mx-auto"></div>
          <p className="mt-4 text-gray-500 text-sm">Loading ERP system...</p>
        </div>
      ) : (
        <>
          {activeTab === "inventory" && (
            <div className="grid grid-cols-1 lg:grid-cols-3 gap-8">
              {/* Product Register */}
              <div className="lg:col-span-1 glass-panel rounded-xl p-6 h-fit space-y-6">
                <h3 className="text-lg font-bold text-gray-200">Register New Product</h3>
                {invMsg && (
                  <div className="p-3 bg-cyan-950/40 border border-cyan-500/20 rounded-lg text-cyan-300 text-sm text-center">
                    {invMsg}
                  </div>
                )}
                <form onSubmit={handleAddInventory} className="space-y-4">
                  <div className="space-y-1">
                    <label className="text-xs text-gray-400 font-semibold">SKU Code</label>
                    <input
                      required
                      value={sku}
                      onChange={(e) => setSku(e.target.value)}
                      className="w-full px-3 py-2 bg-gray-900 border border-gray-800 rounded text-sm text-white"
                      placeholder="e.g. LINEN-WHITE-100"
                    />
                  </div>

                  <div className="space-y-1">
                    <label className="text-xs text-gray-400 font-semibold">Product Name</label>
                    <input
                      required
                      value={name}
                      onChange={(e) => setName(e.target.value)}
                      className="w-full px-3 py-2 bg-gray-900 border border-gray-800 rounded text-sm text-white"
                      placeholder="e.g. Soft Natural Drapery Linen"
                    />
                  </div>

                  <div className="grid grid-cols-2 gap-4">
                    <div className="space-y-1">
                      <label className="text-xs text-gray-400 font-semibold">Quantity</label>
                      <input
                        type="number"
                        required
                        value={quantity}
                        onChange={(e) => setQuantity(parseInt(e.target.value))}
                        className="w-full px-3 py-2 bg-gray-900 border border-gray-800 rounded text-sm text-white"
                      />
                    </div>
                    <div className="space-y-1">
                      <label className="text-xs text-gray-400 font-semibold">Retail Price ($)</label>
                      <input
                        type="number"
                        step="0.01"
                        required
                        value={price}
                        onChange={(e) => setPrice(parseFloat(e.target.value))}
                        className="w-full px-3 py-2 bg-gray-900 border border-gray-800 rounded text-sm text-white"
                      />
                    </div>
                  </div>

                  <div className="grid grid-cols-2 gap-4">
                    <div className="space-y-1">
                      <label className="text-xs text-gray-400 font-semibold">Category</label>
                      <input
                        value={category}
                        onChange={(e) => setCategory(e.target.value)}
                        className="w-full px-3 py-2 bg-gray-900 border border-gray-800 rounded text-sm text-white"
                        placeholder="Velvet, Linen..."
                      />
                    </div>
                    <div className="space-y-1">
                      <label className="text-xs text-gray-400 font-semibold">Warehouse Bin</label>
                      <input
                        value={location}
                        onChange={(e) => setLocation(e.target.value)}
                        className="w-full px-3 py-2 bg-gray-900 border border-gray-800 rounded text-sm text-white"
                        placeholder="A-01"
                      />
                    </div>
                  </div>

                  <button
                    type="submit"
                    className="w-full py-2.5 bg-gradient-to-r from-cyan-500 to-emerald-500 hover:from-cyan-600 hover:to-emerald-600 text-white font-bold rounded text-sm transition-all cursor-pointer"
                  >
                    Add Inventory Item
                  </button>
                </form>
              </div>

              {/* Product Inventory Table */}
              <div className="lg:col-span-2 glass-panel rounded-xl p-6 overflow-x-auto font-sans">
                <h3 className="text-lg font-bold text-gray-200 mb-6">Inventory Database</h3>
                <table className="w-full text-left border-collapse text-sm">
                  <thead>
                    <tr className="border-b border-gray-800 text-xs font-semibold text-gray-400 uppercase tracking-wider">
                      <th className="pb-3">SKU</th>
                      <th className="pb-3">Name</th>
                      <th className="pb-3">Qty</th>
                      <th className="pb-3">Retail Price</th>
                      <th className="pb-3">Location</th>
                    </tr>
                  </thead>
                  <tbody className="divide-y divide-gray-900 text-gray-300">
                    {inventory.map((item) => (
                      <tr key={item.id} className="hover:bg-gray-900/10">
                        <td className="py-4 font-mono text-xs text-cyan-400 font-semibold">{item.sku}</td>
                        <td className="py-4 font-semibold text-gray-200">{item.name}</td>
                        <td className={`py-4 font-semibold ${item.quantity < 150 ? "text-red-400" : "text-gray-300"}`}>
                          {item.quantity} {item.quantity < 150 ? "(Low)" : ""}
                        </td>
                        <td className="py-4 font-semibold font-mono">${item.price.toFixed(2)}</td>
                        <td className="py-4 text-gray-400">{item.location || "N/A"}</td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            </div>
          )}

          {activeTab === "sales" && (
            <div className="grid grid-cols-1 lg:grid-cols-3 gap-8">
              {/* Sales Forms */}
              <div className="lg:col-span-1 space-y-6">
                {/* Sales Order log form */}
                <div className="glass-panel rounded-xl p-6 space-y-4">
                  <h3 className="text-md font-bold text-gray-200 border-b border-gray-900 pb-2">Log Sales Order</h3>
                  {salesMsg && (
                    <div className="p-2 bg-cyan-950/40 border border-cyan-500/20 rounded text-cyan-300 text-xs text-center">
                      {salesMsg}
                    </div>
                  )}
                  <form onSubmit={handleAddOrder} className="space-y-4">
                    <div className="space-y-1">
                      <label className="text-[10px] text-gray-400 uppercase font-semibold">Select Customer</label>
                      <select
                        value={selCustId}
                        onChange={(e) => setSelCustId(parseInt(e.target.value))}
                        className="w-full px-3 py-2 bg-gray-900 border border-gray-800 rounded text-xs text-white"
                      >
                        {customers.map((c) => (
                          <option key={c.id} value={c.id}>{c.name}</option>
                        ))}
                      </select>
                    </div>

                    <div className="grid grid-cols-2 gap-4">
                      <div className="space-y-1">
                        <label className="text-[10px] text-gray-400 uppercase font-semibold">Total Amount ($)</label>
                        <input
                          type="number"
                          step="0.01"
                          required
                          value={orderAmt}
                          onChange={(e) => setOrderAmt(parseFloat(e.target.value))}
                          className="w-full px-3 py-2 bg-gray-900 border border-gray-800 rounded text-xs text-white"
                        />
                      </div>
                      <div className="space-y-1">
                        <label className="text-[10px] text-gray-400 uppercase font-semibold">Order Status</label>
                        <select
                          value={orderStatus}
                          onChange={(e) => setOrderStatus(e.target.value)}
                          className="w-full px-3 py-2 bg-gray-900 border border-gray-800 rounded text-xs text-white focus:outline-none"
                        >
                          <option value="completed">Completed</option>
                          <option value="pending">Pending</option>
                          <option value="cancelled">Cancelled</option>
                        </select>
                      </div>
                    </div>

                    <button
                      type="submit"
                      className="w-full py-2 bg-cyan-600 hover:bg-cyan-500 text-white rounded text-xs font-semibold cursor-pointer"
                    >
                      Log Customer Order
                    </button>
                  </form>
                </div>

                {/* Sales return form */}
                <div className="glass-panel rounded-xl p-6 space-y-4">
                  <h3 className="text-md font-bold text-gray-200 border-b border-gray-900 pb-2">Log Sales Return</h3>
                  <form onSubmit={handleAddReturn} className="space-y-4">
                    <div className="space-y-1">
                      <label className="text-[10px] text-gray-400 uppercase font-semibold">Select Order ID</label>
                      <select
                        value={retOrderId}
                        onChange={(e) => setRetOrderId(parseInt(e.target.value))}
                        className="w-full px-3 py-2 bg-gray-900 border border-gray-800 rounded text-xs text-white"
                      >
                        {orders.map((o) => (
                          <option key={o.id} value={o.id}>
                            Order #{o.id} - ${o.total_amount.toFixed(2)} ({o.customer_name})
                          </option>
                        ))}
                      </select>
                    </div>

                    <div className="space-y-1">
                      <label className="text-[10px] text-gray-400 uppercase font-semibold">Refund Amount ($)</label>
                      <input
                        type="number"
                        step="0.01"
                        required
                        value={retAmt}
                        onChange={(e) => setRetAmt(parseFloat(e.target.value))}
                        className="w-full px-3 py-2 bg-gray-900 border border-gray-800 rounded text-xs text-white"
                      />
                    </div>

                    <div className="space-y-1">
                      <label className="text-[10px] text-gray-400 uppercase font-semibold">Reason / Notes</label>
                      <textarea
                        value={retNotes}
                        onChange={(e) => setRetNotes(e.target.value)}
                        className="w-full px-3 py-2 bg-gray-900 border border-gray-800 rounded text-xs text-white h-16 resize-none focus:outline-none"
                        placeholder="Defective roll, etc..."
                      />
                    </div>

                    <button
                      type="submit"
                      className="w-full py-2 bg-red-950/40 border border-red-500/20 hover:bg-red-900/20 text-red-300 rounded text-xs font-semibold cursor-pointer"
                    >
                      Process Return Credit
                    </button>
                  </form>
                </div>
              </div>

              {/* Orders & Returns List */}
              <div className="lg:col-span-2 space-y-6">
                <div className="glass-panel rounded-xl p-6 overflow-x-auto">
                  <h3 className="text-md font-bold text-gray-200 mb-4">Completed Sales Invoices</h3>
                  <table className="w-full text-left border-collapse text-xs">
                    <thead>
                      <tr className="border-b border-gray-800 text-gray-500 uppercase font-semibold">
                        <th className="pb-3">Invoice ID</th>
                        <th className="pb-3">Customer</th>
                        <th className="pb-3">Statement Date</th>
                        <th className="pb-3 text-right">Invoice total</th>
                        <th className="pb-3 text-center">Status</th>
                      </tr>
                    </thead>
                    <tbody className="divide-y divide-gray-950 text-gray-300">
                      {orders.map((o) => (
                        <tr key={o.id} className="hover:bg-gray-900/10">
                          <td className="py-3 font-mono font-bold text-gray-400">#INV-{String(o.id).padStart(5, '0')}</td>
                          <td className="py-3 font-semibold text-gray-200">{o.customer_name}</td>
                          <td className="py-3 font-mono text-gray-500">{o.created_at.split("T")[0]}</td>
                          <td className="py-3 text-right font-mono font-semibold text-cyan-400">
                            ${o.total_amount.toFixed(2)}
                          </td>
                          <td className="py-3 text-center">
                            <span className={`text-[10px] px-2 py-0.5 rounded-full border ${
                              o.status === "completed"
                                ? "bg-emerald-950/40 border-emerald-500/20 text-emerald-400"
                                : "bg-cyan-950/40 border-cyan-500/20 text-cyan-400"
                            }`}>
                              {o.status}
                            </span>
                          </td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                </div>

                <div className="glass-panel rounded-xl p-6 overflow-x-auto">
                  <h3 className="text-md font-bold text-gray-200 mb-4">Processed Sales Returns</h3>
                  {returns.length === 0 ? (
                    <p className="text-xs text-gray-500">No returns logged.</p>
                  ) : (
                    <table className="w-full text-left border-collapse text-xs">
                      <thead>
                        <tr className="border-b border-gray-800 text-gray-500 uppercase font-semibold">
                          <th className="pb-3">Return ID</th>
                          <th className="pb-3">Customer</th>
                          <th className="pb-3">Date Logged</th>
                          <th className="pb-3">Reason</th>
                          <th className="pb-3 text-right">Refund Value</th>
                        </tr>
                      </thead>
                      <tbody className="divide-y divide-gray-950 text-gray-300">
                        {returns.map((r) => (
                          <tr key={r.id} className="hover:bg-gray-900/10">
                            <td className="py-3 font-mono text-gray-500">#RET-{String(r.id).padStart(4, '0')}</td>
                            <td className="py-3 font-semibold text-gray-200">{r.customer_name}</td>
                            <td className="py-3 font-mono text-gray-500">{r.return_date.split(" ")[0]}</td>
                            <td className="py-3 text-gray-400">{r.notes}</td>
                            <td className="py-3 text-right font-mono font-semibold text-red-400">
                              -${r.amount.toFixed(2)}
                            </td>
                          </tr>
                        ))}
                      </tbody>
                    </table>
                  )}
                </div>
              </div>
            </div>
          )}

          {activeTab === "purchasing" && (
            <div className="grid grid-cols-1 lg:grid-cols-3 gap-8">
              {/* Purchasing Forms */}
              <div className="lg:col-span-1 space-y-6">
                {/* PO Form */}
                <div className="glass-panel rounded-xl p-6 space-y-4">
                  <h3 className="text-md font-bold text-gray-200 border-b border-gray-900 pb-2">Create Purchase Order</h3>
                  {purchMsg && (
                    <div className="p-2 bg-cyan-950/40 border border-cyan-500/20 rounded text-cyan-300 text-xs text-center">
                      {purchMsg}
                    </div>
                  )}
                  <form onSubmit={handleAddPO} className="space-y-4">
                    <div className="space-y-1">
                      <label className="text-[10px] text-gray-400 uppercase font-semibold">Supplier Name</label>
                      <input
                        required
                        value={poSupplier}
                        onChange={(e) => setPoSupplier(e.target.value)}
                        className="w-full px-3 py-2 bg-gray-900 border border-gray-800 rounded text-xs text-white"
                        placeholder="Loomcraft Textiles, etc."
                      />
                    </div>

                    <div className="space-y-1">
                      <label className="text-[10px] text-gray-400 uppercase font-semibold">Product SKU</label>
                      <select
                        value={poSku}
                        onChange={(e) => setPoSku(e.target.value)}
                        className="w-full px-3 py-2 bg-gray-900 border border-gray-800 rounded text-xs text-white"
                      >
                        {inventory.map((item) => (
                          <option key={item.id} value={item.sku}>{item.sku} - {item.name}</option>
                        ))}
                      </select>
                    </div>

                    <div className="grid grid-cols-2 gap-4">
                      <div className="space-y-1">
                        <label className="text-[10px] text-gray-400 uppercase font-semibold">Quantity</label>
                        <input
                          type="number"
                          required
                          value={poQty}
                          onChange={(e) => setPoQty(parseInt(e.target.value))}
                          className="w-full px-3 py-2 bg-gray-900 border border-gray-800 rounded text-xs text-white"
                        />
                      </div>
                      <div className="space-y-1">
                        <label className="text-[10px] text-gray-400 uppercase font-semibold">Unit Cost ($)</label>
                        <input
                          type="number"
                          step="0.01"
                          required
                          value={poCost}
                          onChange={(e) => setPoCost(parseFloat(e.target.value))}
                          className="w-full px-3 py-2 bg-gray-900 border border-gray-800 rounded text-xs text-white"
                        />
                      </div>
                    </div>

                    <button
                      type="submit"
                      className="w-full py-2 bg-cyan-600 hover:bg-cyan-500 text-white rounded text-xs font-semibold cursor-pointer"
                    >
                      Issue Purchase Order
                    </button>
                  </form>
                </div>

                {/* GRN Form */}
                <div className="glass-panel rounded-xl p-6 space-y-4">
                  <h3 className="text-md font-bold text-gray-200 border-b border-gray-900 pb-2">Goods Receipt Note (GRN)</h3>
                  <form onSubmit={handleAddGRN} className="space-y-4">
                    <div className="space-y-1">
                      <label className="text-[10px] text-gray-400 uppercase font-semibold">Select Open PO</label>
                      <select
                        value={grnPoId}
                        onChange={(e) => setGrnPoId(parseInt(e.target.value))}
                        className="w-full px-3 py-2 bg-gray-900 border border-gray-800 rounded text-xs text-white"
                      >
                        {purchaseOrders.filter(p => p.status === "pending").map((p) => (
                          <option key={p.id} value={p.id}>
                            {p.po_number} - {p.supplier_name} ({p.item_sku})
                          </option>
                        ))}
                        {purchaseOrders.filter(p => p.status === "pending").length === 0 && (
                          <option value={0}>No pending POs available</option>
                        )}
                      </select>
                    </div>

                    <div className="space-y-1">
                      <label className="text-[10px] text-gray-400 uppercase font-semibold">Received Quantity</label>
                      <input
                        type="number"
                        required
                        value={grnQty}
                        onChange={(e) => setGrnQty(parseInt(e.target.value))}
                        className="w-full px-3 py-2 bg-gray-900 border border-gray-800 rounded text-xs text-white"
                      />
                    </div>

                    <div className="space-y-1">
                      <label className="text-[10px] text-gray-400 uppercase font-semibold">Inspection Memo</label>
                      <textarea
                        value={grnNotes}
                        onChange={(e) => setGrnNotes(e.target.value)}
                        className="w-full px-3 py-2 bg-gray-900 border border-gray-800 rounded text-xs text-white h-16 resize-none focus:outline-none"
                        placeholder="Count verified, shelved..."
                      />
                    </div>

                    <button
                      type="submit"
                      disabled={grnPoId === 0}
                      className="w-full py-2 bg-emerald-600 hover:bg-emerald-500 text-white rounded text-xs font-semibold disabled:opacity-50 disabled:cursor-not-allowed cursor-pointer"
                    >
                      Receive & Adjust Stock
                    </button>
                  </form>
                </div>
              </div>

              {/* POs & GRN List */}
              <div className="lg:col-span-2 space-y-6">
                <div className="glass-panel rounded-xl p-6 overflow-x-auto">
                  <h3 className="text-md font-bold text-gray-200 mb-4">Supplier Purchase Orders</h3>
                  <table className="w-full text-left border-collapse text-xs">
                    <thead>
                      <tr className="border-b border-gray-800 text-gray-500 uppercase font-semibold">
                        <th className="pb-3">PO Number</th>
                        <th className="pb-3">Supplier</th>
                        <th className="pb-3">Product SKU</th>
                        <th className="pb-3 text-right">Units</th>
                        <th className="pb-3 text-right">Total Cost</th>
                        <th className="pb-3 text-center">Status</th>
                      </tr>
                    </thead>
                    <tbody className="divide-y divide-gray-950 text-gray-300">
                      {purchaseOrders.map((p) => (
                        <tr key={p.id} className="hover:bg-gray-900/10">
                          <td className="py-3 font-mono font-bold text-gray-400">{p.po_number}</td>
                          <td className="py-3 font-semibold text-gray-200">{p.supplier_name}</td>
                          <td className="py-3 font-mono text-cyan-400 font-semibold">{p.item_sku}</td>
                          <td className="py-3 text-right">{p.quantity}</td>
                          <td className="py-3 text-right font-mono font-semibold text-gray-200">
                            ${(p.quantity * p.unit_cost).toFixed(2)}
                          </td>
                          <td className="py-3 text-center">
                            <span className={`text-[10px] px-2 py-0.5 rounded-full border ${
                              p.status === "received"
                                ? "bg-emerald-950/40 border-emerald-500/20 text-emerald-400"
                                : "bg-yellow-950/40 border-yellow-500/20 text-yellow-400"
                            }`}>
                              {p.status}
                            </span>
                          </td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                </div>

                <div className="glass-panel rounded-xl p-6 overflow-x-auto">
                  <h3 className="text-md font-bold text-gray-200 mb-4">Goods Receipt Notes (GRN) Ledger</h3>
                  {grns.length === 0 ? (
                    <p className="text-xs text-gray-500">No goods receipts logged.</p>
                  ) : (
                    <table className="w-full text-left border-collapse text-xs">
                      <thead>
                        <tr className="border-b border-gray-800 text-gray-500 uppercase font-semibold">
                          <th className="pb-3">GRN ID</th>
                          <th className="pb-3">PO Reference</th>
                          <th className="pb-3">Product SKU</th>
                          <th className="pb-3 font-mono">Date Received</th>
                          <th className="pb-3 text-right">Qty Received</th>
                        </tr>
                      </thead>
                      <tbody className="divide-y divide-gray-950 text-gray-300">
                        {grns.map((g) => (
                          <tr key={g.id} className="hover:bg-gray-900/10">
                            <td className="py-3 font-mono text-gray-500">#GRN-{String(g.id).padStart(4, '0')}</td>
                            <td className="py-3 font-semibold text-gray-200">{g.po_number} ({g.supplier_name})</td>
                            <td className="py-3 font-mono text-cyan-400 font-semibold">{g.item_sku}</td>
                            <td className="py-3 font-mono text-gray-500">{g.received_date.split(" ")[0]}</td>
                            <td className="py-3 text-right font-semibold text-emerald-400">+{g.received_quantity}</td>
                          </tr>
                        ))}
                      </tbody>
                    </table>
                  )}
                </div>
              </div>
            </div>
          )}

          {activeTab === "shifts" && (
            <div className="grid grid-cols-1 lg:grid-cols-3 gap-8">
              {/* Active Till Status */}
              <div className="lg:col-span-1 glass-panel rounded-xl p-6 space-y-6 h-fit text-center">
                <h3 className="text-md font-bold text-gray-200 border-b border-gray-900 pb-3 text-left">Cash Till Status</h3>
                
                {activeShift ? (
                  <div className="space-y-4">
                    <span className="inline-block px-3 py-1 bg-emerald-950/40 border border-emerald-500/20 text-emerald-400 rounded-full text-xs font-semibold">
                      Register Open
                    </span>
                    <div className="space-y-1">
                      <div className="text-xs text-gray-500">Active Cashier</div>
                      <div className="text-xl font-bold text-gray-200">{activeShift.cashier_name}</div>
                    </div>
                    <div className="space-y-1">
                      <div className="text-xs text-gray-500">Opening Balance</div>
                      <div className="text-lg font-mono font-semibold text-gray-300">${activeShift.open_balance.toFixed(2)}</div>
                    </div>
                    <div className="space-y-1">
                      <div className="text-xs text-gray-500">Shift Started At</div>
                      <div className="text-xs text-gray-400 font-mono">{activeShift.open_time}</div>
                    </div>
                  </div>
                ) : (
                  <div className="space-y-4 py-6">
                    <span className="inline-block px-3 py-1 bg-red-950/40 border border-red-500/20 text-red-400 rounded-full text-xs font-semibold">
                      Till Closed / Locked
                    </span>
                    <p className="text-xs text-gray-500 leading-relaxed">
                      Cash register is currently locked. cashier shifts must be opened before logging POS sales or processing drawer cash.
                    </p>
                  </div>
                )}
              </div>

              {/* Action Forms */}
              <div className="lg:col-span-2 glass-panel rounded-xl p-6 space-y-6">
                <h3 className="text-lg font-bold text-gray-200 border-b border-gray-900 pb-3">Open / Close Register Shift</h3>
                {shiftMsg && (
                  <div className="p-3 bg-cyan-950/40 border border-cyan-500/20 rounded-lg text-cyan-300 text-sm text-center">
                    {shiftMsg}
                  </div>
                )}

                {activeShift ? (
                  /* Form to close shift */
                  <form onSubmit={handleCloseShift} className="space-y-4">
                    <div className="space-y-1">
                      <label className="text-xs text-gray-400 font-semibold">Closing Balance ($)</label>
                      <input
                        type="number"
                        step="0.01"
                        required
                        value={closeBalance}
                        onChange={(e) => setCloseBalance(parseFloat(e.target.value))}
                        className="w-full px-3 py-2 bg-gray-900 border border-gray-800 rounded text-sm text-white font-mono"
                      />
                    </div>
                    <button
                      type="submit"
                      className="py-2.5 px-6 bg-red-600 hover:bg-red-500 text-white rounded text-sm font-semibold cursor-pointer"
                    >
                      Close Shift & Lock Register
                    </button>
                  </form>
                ) : (
                  /* Form to open shift */
                  <form onSubmit={handleOpenShift} className="space-y-4">
                    <div className="grid grid-cols-2 gap-4">
                      <div className="space-y-1">
                        <label className="text-xs text-gray-400 font-semibold">Cashier Name</label>
                        <input
                          required
                          value={cashierName}
                          onChange={(e) => setCashierName(e.target.value)}
                          className="w-full px-3 py-2 bg-gray-900 border border-gray-800 rounded text-sm text-white"
                          placeholder="e.g. Mark Cashier"
                        />
                      </div>
                      <div className="space-y-1">
                        <label className="text-xs text-gray-400 font-semibold">Opening Cash Balance ($)</label>
                        <input
                          type="number"
                          step="0.01"
                          required
                          value={openBalance}
                          onChange={(e) => setOpenBalance(parseFloat(e.target.value))}
                          className="w-full px-3 py-2 bg-gray-900 border border-gray-800 rounded text-sm text-white font-mono"
                        />
                      </div>
                    </div>
                    <button
                      type="submit"
                      className="py-2.5 px-6 bg-cyan-600 hover:bg-cyan-500 text-white rounded text-sm font-semibold cursor-pointer"
                    >
                      Open Shift & Unlock Drawer
                    </button>
                  </form>
                )}
              </div>
            </div>
          )}

          {activeTab === "reports" && (
            <div className="space-y-6">
              {/* Reports sub navigation */}
              <div className="flex space-x-4 bg-gray-950/40 p-2 border border-gray-900 rounded-xl w-fit">
                <button
                  onClick={() => setActiveReportTab("pl")}
                  className={`py-1 px-3 text-xs font-semibold rounded cursor-pointer transition-colors ${
                    activeReportTab === "pl" ? "bg-cyan-500/15 text-cyan-400" : "text-gray-400 hover:text-white"
                  }`}
                >
                  Profit & Loss (P&L)
                </button>
                <button
                  onClick={() => setActiveReportTab("daybook")}
                  className={`py-1 px-3 text-xs font-semibold rounded cursor-pointer transition-colors ${
                    activeReportTab === "daybook" ? "bg-cyan-500/15 text-cyan-400" : "text-gray-400 hover:text-white"
                  }`}
                >
                  Daybook Transactions
                </button>
                <button
                  onClick={() => setActiveReportTab("stock")}
                  className={`py-1 px-3 text-xs font-semibold rounded cursor-pointer transition-colors ${
                    activeReportTab === "stock" ? "bg-cyan-500/15 text-cyan-400" : "text-gray-400 hover:text-white"
                  }`}
                >
                  Stock Status & Alerts
                </button>
                <button
                  onClick={() => setActiveReportTab("gst")}
                  className={`py-1 px-3 text-xs font-semibold rounded cursor-pointer transition-colors ${
                    activeReportTab === "gst" ? "bg-cyan-500/15 text-cyan-400" : "text-gray-400 hover:text-white"
                  }`}
                >
                  GST Summary
                </button>
              </div>

              {activeReportTab === "pl" && plReport && (
                <div className="glass-panel p-6 rounded-xl space-y-6">
                  <h3 className="text-md font-bold text-gray-200 border-b border-gray-900 pb-3">Income Statement Summary</h3>
                  <div className="grid grid-cols-1 md:grid-cols-4 gap-6">
                    <div className="bg-gray-950/40 p-4 border border-gray-900 rounded-lg">
                      <div className="text-xs text-gray-500">Gross Sales Revenue</div>
                      <div className="text-lg font-bold text-gray-200 mt-1 font-mono">${plReport.revenue.toFixed(2)}</div>
                    </div>
                    <div className="bg-gray-950/40 p-4 border border-gray-900 rounded-lg">
                      <div className="text-xs text-gray-500">Sales Returns credit</div>
                      <div className="text-lg font-bold text-red-400 mt-1 font-mono">-${plReport.returns.toFixed(2)}</div>
                    </div>
                    <div className="bg-gray-950/40 p-4 border border-gray-900 rounded-lg">
                      <div className="text-xs text-gray-500">Cost of Goods (COGS)</div>
                      <div className="text-lg font-bold text-yellow-500 mt-1 font-mono">-${plReport.cost_of_goods.toFixed(2)}</div>
                    </div>
                    <div className="bg-cyan-950/10 p-4 border border-cyan-500/20 rounded-lg">
                      <div className="text-xs text-cyan-400 font-bold">Projected Net Profit</div>
                      <div className={`text-xl font-bold mt-1 font-mono ${plReport.gross_profit >= 0 ? "text-emerald-400" : "text-red-400"}`}>
                        ${plReport.gross_profit.toFixed(2)}
                      </div>
                    </div>
                  </div>
                </div>
              )}

              {activeReportTab === "daybook" && (
                <div className="glass-panel p-6 rounded-xl overflow-x-auto">
                  <h3 className="text-md font-bold text-gray-200 mb-4">Daybook Transactions Registry</h3>
                  <table className="w-full text-left border-collapse text-xs">
                    <thead>
                      <tr className="border-b border-gray-800 text-gray-500 uppercase font-semibold">
                        <th className="pb-3">Type</th>
                        <th className="pb-3">Ref ID</th>
                        <th className="pb-3">Timestamp</th>
                        <th className="pb-3">Details / Status</th>
                        <th className="pb-3 text-right">Amount</th>
                      </tr>
                    </thead>
                    <tbody className="divide-y divide-gray-950 text-gray-300">
                      {daybook.map((db, idx) => (
                        <tr key={idx} className="hover:bg-gray-900/10">
                          <td className={`py-3 font-semibold ${
                            db.tx_type === "Sales Order" ? "text-cyan-400" : db.tx_type === "Sales Return" ? "text-red-400" : "text-yellow-400"
                          }`}>{db.tx_type}</td>
                          <td className="py-3 font-mono font-bold text-gray-500">#{db.tx_id}</td>
                          <td className="py-3 font-mono text-gray-400">{db.date.replace("T", " ")}</td>
                          <td className="py-3 text-gray-400">{db.details}</td>
                          <td className={`py-3 text-right font-mono font-semibold ${
                            db.tx_type === "Sales Return" ? "text-red-400" : "text-gray-200"
                          }`}>
                            {db.tx_type === "Sales Return" ? "-" : ""}${db.amount.toFixed(2)}
                          </td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                </div>
              )}

              {activeReportTab === "stock" && stockReport && (
                <div className="space-y-6">
                  <div className="glass-panel p-6 rounded-xl">
                    <h3 className="text-md font-bold text-gray-200 mb-2">Inventory Valuation</h3>
                    <div className="text-xs text-gray-500">Total Stock Value Asset</div>
                    <div className="text-2xl font-extrabold text-cyan-400 font-mono mt-1">
                      ${stockReport.total_stock_valuation.toLocaleString(undefined, { minimumFractionDigits: 2 })}
                    </div>
                  </div>

                  <div className="glass-panel p-6 rounded-xl overflow-x-auto">
                    <h3 className="text-md font-bold text-gray-200 mb-4">Stock Restock Alerts</h3>
                    {stockReport.low_stock_alerts.length === 0 ? (
                      <p className="text-xs text-gray-500">All inventory counts are healthy (&gt;150 units).</p>
                    ) : (
                      <table className="w-full text-left border-collapse text-xs">
                        <thead>
                          <tr className="border-b border-gray-800 text-gray-500 uppercase font-semibold">
                            <th className="pb-3">SKU Code</th>
                            <th className="pb-3">Name</th>
                            <th className="pb-3">Location</th>
                            <th className="pb-3 text-right">Current Qty</th>
                          </tr>
                        </thead>
                        <tbody className="divide-y divide-gray-950 text-gray-300">
                          {stockReport.low_stock_alerts.map((alert) => (
                            <tr key={alert.id} className="hover:bg-gray-900/10">
                              <td className="py-3 font-mono font-semibold text-red-400">{alert.sku}</td>
                              <td className="py-3 font-semibold text-gray-200">{alert.name}</td>
                              <td className="py-3 text-gray-400">{alert.location}</td>
                              <td className="py-3 text-right font-bold text-red-400">{alert.quantity}</td>
                            </tr>
                          ))}
                        </tbody>
                      </table>
                    )}
                  </div>
                </div>
              )}

              {activeReportTab === "gst" && gstReport && (
                <div className="glass-panel p-6 rounded-xl space-y-6">
                  <h3 className="text-md font-bold text-gray-200 border-b border-gray-900 pb-3">Simulated GST Return Filings</h3>
                  <div className="grid grid-cols-2 md:grid-cols-4 gap-6">
                    <div className="bg-gray-950/40 p-4 border border-gray-900 rounded-lg">
                      <div className="text-xs text-gray-500">Taxable Turnover</div>
                      <div className="text-md font-bold text-gray-200 mt-1 font-mono">${gstReport.taxable_turnover.toFixed(2)}</div>
                    </div>
                    <div className="bg-gray-950/40 p-4 border border-gray-900 rounded-lg">
                      <div className="text-xs text-gray-500">Simulated CGST (9%)</div>
                      <div className="text-md font-bold text-gray-300 mt-1 font-mono">${gstReport.cgst.toFixed(2)}</div>
                    </div>
                    <div className="bg-gray-950/40 p-4 border border-gray-900 rounded-lg">
                      <div className="text-xs text-gray-500">Simulated SGST (9%)</div>
                      <div className="text-md font-bold text-gray-300 mt-1 font-mono">${gstReport.sgst.toFixed(2)}</div>
                    </div>
                    <div className="bg-cyan-950/10 p-4 border border-cyan-500/20 rounded-lg">
                      <div className="text-xs text-cyan-400 font-bold">Total Liability (18%)</div>
                      <div className="text-md font-bold text-cyan-400 mt-1 font-mono">${gstReport.total_tax_liability.toFixed(2)}</div>
                    </div>
                  </div>
                </div>
              )}
            </div>
          )}
        </>
      )}
    </div>
  );
}
