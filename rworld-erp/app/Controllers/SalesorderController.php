<?php
/**
 * SalesorderController — Sales Order module
 *
 * Workflow:  Quotation ──► Sales Order ──► Sale (Invoice)
 *            Partial payments can be recorded at any point.
 *
 * Routes:
 *   GET  /salesorder              → index()
 *   GET  /salesorder/create       → create()
 *   POST /salesorder/create       → create() → store
 *   GET  /salesorder/view/{id}    → view()
 *   POST /salesorder/pay/{id}     → pay()       record partial payment
 *   POST /salesorder/convert/{id} → convert()   → invoice
 *   POST /salesorder/cancel/{id}  → cancel()
 *   GET  /salesorder/search       → search()    AJAX item lookup
 */
class SalesorderController extends Controller {

    /**
     * Print Sales Order
     */
    public function print($id = null) {
        $cid = $this->getCompanyId();
        $order = $this->db->fetchOne(
            "SELECT so.*, COALESCE(c.name, so.customer_name, 'Walk-in Customer') AS customer_display,
                    COALESCE(c.phone1, '') AS customer_phone
             FROM sales_orders so
             LEFT JOIN customers c ON c.id = so.customer_id
             WHERE so.id=? AND so.company_id=?",
            [(int)$id, $cid]
        );
        if (!$order) { die("Sales Order not found."); }

        $lines = $this->db->fetchAll(
            "SELECT sd.*, i.stock_no, i.description FROM sales_order_detail sd
             LEFT JOIN items i ON i.id = sd.item_id
             WHERE sd.header_id=?",
            [(int)$id]
        ) ?: [];

        $company = $this->db->fetchOne("SELECT * FROM companies WHERE id=?", [$cid]);

        $this->render('billing/print_invoice', [
            'type'    => 'SALES ORDER',
            'record'  => $order,
            'lines'   => $lines,
            'company' => $company
        ], 'minimal');
    }

    /* ─── AJAX auth: return JSON 401 instead of redirect ─────────── */
    protected function checkAuth() {
        if (empty($_SESSION['user_id'])) {
            if (isset($_GET['q']) || !empty($_SERVER['HTTP_X_REQUESTED_WITH'])
                || str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json')) {
                $this->json(['error' => 'Unauthenticated'], 401);
            } else {
                $this->redirect('auth/login');
            }
        }
    }

    /* ─────────────────────────────────────────────────────────────── */
    /*  INDEX — list all sales orders                                  */
    /* ─────────────────────────────────────────────────────────────── */
    public function index() {
        $cid    = $this->getCompanyId();
        $bid    = $this->getBranchId();
        $page   = max(1, (int)$this->get('page', 1));
        $status = $this->get('status', '');
        $search = $this->get('q', '');

        $where  = "WHERE so.company_id=? AND so.branch_id=?";
        $params = [$cid, $bid];

        if ($status) { $where .= " AND so.status=?"; $params[] = $status; }
        if ($search) {
            $where .= " AND (so.doc_no LIKE ? OR so.customer_name LIKE ?)";
            $params[] = "%$search%";
            $params[] = "%$search%";
        }

        $sql = "SELECT so.*,
                       COALESCE(c.name, so.customer_name, 'Walk-in') AS customer_display,
                       (so.net_amount - so.paid_amount)               AS balance_remaining
                FROM sales_orders so
                LEFT JOIN customers c ON c.id = so.customer_id
                $where
                ORDER BY so.id DESC";

        $paged = $this->paginate($sql, $params, $page);

        $this->render('salesorder/index', [
            'pageTitle'  => 'Sales Orders',
            'records'    => $paged,
            'status'     => $status,
            'search'     => $search,
            'breadcrumbs' => [
                ['label' => 'Dashboard', 'url' => APP_URL . '/dashboard'],
                ['label' => 'Billing',   'url' => '#'],
                ['label' => 'Sales Orders', 'url' => APP_URL . '/salesorder'],
            ]
        ]);
    }

    /* ─────────────────────────────────────────────────────────────── */
    /*  CREATE — new sales order form + save (AJAX POST)               */
    /* ─────────────────────────────────────────────────────────────── */
    public function create() {
        $cid = $this->getCompanyId();
        $bid = $this->getBranchId();

        /* ── POST: save via AJAX ── */
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->validateCsrf();

            $payload    = json_decode(file_get_contents('php://input'), true);
            $items      = $payload['items']      ?? [];
            $customerId = $payload['customer_id'] ?? null;
            $custName   = $payload['customer_name'] ?? 'Walk-in Customer';
            $payMode    = $payload['payment_mode'] ?? 'Cash';
            $grossAmt   = (float)($payload['gross_amount']   ?? 0);
            $discPerc   = (float)($payload['disc_perc']      ?? 0);
            $discAmt    = (float)($payload['disc_amount']     ?? 0);
            $handling   = (float)($payload['handling_fee']   ?? 0);
            $taxAmt     = (float)($payload['tax_amount']      ?? 0);
            $netAmt     = (float)($payload['net_amount']      ?? 0);
            $paidNow    = (float)($payload['paid_amount']     ?? 0);
            $remarks    = $payload['remarks']    ?? '';
            $quotId     = !empty($payload['source_quotation_id']) ? (int)$payload['source_quotation_id'] : null;

            if (empty($items)) {
                $this->json(['success' => false, 'message' => 'No items in order.'], 422);
                return;
            }

            /* Generate doc_no */
            $last  = $this->db->fetchColumn("SELECT doc_no FROM sales_orders WHERE company_id=? ORDER BY id DESC LIMIT 1", [$cid]);
            $num   = $last ? ((int)preg_replace('/\D/', '', $last) + 1) : 1;
            $docNo = 'SO-' . date('Y') . '-' . str_pad($num, 5, '0', STR_PAD_LEFT);

            $balance    = $netAmt - $paidNow;
            $status     = ($balance <= 0) ? 'closed' : ($paidNow > 0 ? 'partial' : 'open');

            $this->db->beginTransaction();
            try {
                $this->db->execute(
                    "INSERT INTO sales_orders
                     (company_id, branch_id, doc_no, doc_date, customer_id, customer_name,
                      gross_amount, total_tax, bill_disc_perc, bill_disc_amount,
                      handling_fee, net_amount, paid_amount, balance_due,
                      payment_mode_id, source_quotation_id, status, remarks, created_by)
                     VALUES (?,?,?,CURDATE(),?,?,?,?,?,?,?,?,?,?,1,?,?,?,?)",
                    [
                        $cid, $bid, $docNo,
                        $customerId ?: null, $custName,
                        $grossAmt, $taxAmt, $discPerc, $discAmt,
                        $handling, $netAmt, $paidNow, $balance,
                        $quotId, $status, $remarks,
                        $_SESSION['user_id'] ?? null
                    ]
                );
                $orderId = $this->db->lastInsertId();

                /* Save line items */
                foreach ($items as $i => $ln) {
                    $qty      = (float)($ln['qty']   ?? 1);
                    $rate     = (float)($ln['price'] ?? 0);
                    $discPRow = (float)($ln['disc']  ?? 0);
                    $value    = $qty * $rate;
                    $discAmtR = $value * $discPRow / 100;
                    $netVal   = $value - $discAmtR;

                    $this->db->execute(
                        "INSERT INTO sales_order_detail
                         (header_id, srl_no, item_id, qty, delivered_qty, rate, value,
                          disc_perc, disc_amount, net_value, tax_type_id, total_tax)
                         VALUES (?,?,?,?,0,?,?,?,?,?,?,0)",
                        [
                            $orderId, $i + 1,
                            $ln['id'] ?? null,
                            $qty, $rate, $value,
                            $discPRow, $discAmtR, $netVal,
                            $ln['tax_type_id'] ?? null
                        ]
                    );
                }

                /* Record advance/deposit payment if paid > 0 */
                if ($paidNow > 0) {
                    $this->db->execute(
                        "INSERT INTO sales_order_payments
                         (company_id, order_id, doc_date, amount, payment_mode, narration, created_by)
                         VALUES (?,?,CURDATE(),?,?,'Advance/Deposit on order creation',?)",
                        [$cid, $orderId, $paidNow, $payMode, $_SESSION['user_id'] ?? null]
                    );
                }

                /* Mark source quotation as converted */
                if ($quotId) {
                    $this->db->execute(
                        "UPDATE quotations SET status='converted' WHERE id=? AND company_id=?",
                        [$quotId, $cid]
                    );
                }

                $this->db->commit();
                $this->auditLog('CREATE', 'sales_order', $orderId, $docNo);
                $this->json(['success' => true, 'order_id' => $orderId, 'doc_no' => $docNo]);

            } catch (Exception $e) {
                $this->db->rollback();
                $this->json(['success' => false, 'message' => $e->getMessage()], 500);
            }
            return;
        }

        /* ── GET: render form ── */
        $customers = $this->db->fetchAll(
            "SELECT id, code, name, phone1 FROM customers WHERE company_id=? AND is_active=1 ORDER BY name",
            [$cid]
        ) ?: [];

        /* Optionally pre-fill from quotation */
        $quotId    = (int)$this->get('from_quotation', 0);
        $quotData  = null;
        $quotItems = [];
        if ($quotId) {
            $quotData = $this->db->fetchOne(
                "SELECT q.*, COALESCE(c.name, q.customer_name, 'Walk-in') AS customer_display
                 FROM quotations q
                 LEFT JOIN customers c ON c.id = q.customer_id
                 WHERE q.id=? AND q.company_id=?",
                [$quotId, $cid]
            );
            if ($quotData) {
                $quotItems = $this->db->fetchAll(
                    "SELECT qd.*, i.stock_no, i.description, i.price1
                     FROM quotation_detail qd
                     JOIN items i ON i.id = qd.item_id
                     WHERE qd.header_id=?",
                    [$quotId]
                ) ?: [];
            }
        }

        $this->render('salesorder/create', [
            'pageTitle'  => 'New Sales Order',
            'customers'  => $customers,
            'quotData'   => $quotData,
            'quotItems'  => $quotItems,
            'breadcrumbs' => [
                ['label' => 'Dashboard',    'url' => APP_URL . '/dashboard'],
                ['label' => 'Sales Orders', 'url' => APP_URL . '/salesorder'],
                ['label' => 'New Order',    'url' => '#'],
            ]
        ]);
    }

    /* ─────────────────────────────────────────────────────────────── */
    /*  VIEW — order detail + payment history + action buttons         */
    /* ─────────────────────────────────────────────────────────────── */
    public function view($id = null) {
        $cid   = $this->getCompanyId();
        $order = $this->db->fetchOne(
            "SELECT so.*, COALESCE(c.name, so.customer_name, 'Walk-in') AS customer_display
             FROM sales_orders so
             LEFT JOIN customers c ON c.id = so.customer_id
             WHERE so.id=? AND so.company_id=?",
            [(int)$id, $cid]
        );
        if (!$order) { $this->redirect('salesorder'); return; }

        $lines = $this->db->fetchAll(
            "SELECT sd.*, i.stock_no, i.description
             FROM sales_order_detail sd
             LEFT JOIN items i ON i.id = sd.item_id
             WHERE sd.header_id=? ORDER BY sd.srl_no",
            [(int)$id]
        ) ?: [];

        $payments = $this->db->fetchAll(
            "SELECT * FROM sales_order_payments WHERE order_id=? ORDER BY id",
            [(int)$id]
        ) ?: [];

        $totalPaid = array_sum(array_column($payments, 'amount'));

        $this->render('salesorder/view', [
            'pageTitle' => 'Sales Order — ' . $order['doc_no'],
            'order'     => $order,
            'lines'     => $lines,
            'payments'  => $payments,
            'totalPaid' => $totalPaid,
            'breadcrumbs' => [
                ['label' => 'Dashboard',    'url' => APP_URL . '/dashboard'],
                ['label' => 'Sales Orders', 'url' => APP_URL . '/salesorder'],
                ['label' => $order['doc_no'], 'url' => '#'],
            ]
        ]);
    }

    /* ─────────────────────────────────────────────────────────────── */
    /*  PAY — record a partial / full payment on an open order         */
    /* ─────────────────────────────────────────────────────────────── */
    public function pay($id = null) {
        $this->validateCsrf();
        $cid    = $this->getCompanyId();
        $amount = (float)$this->post('amount', 0);
        $mode   = $this->post('payment_mode', 'Cash');
        $ref    = $this->post('reference', '');
        $note   = $this->post('narration', '');

        if ($amount <= 0) {
            $this->flash('error', 'Payment amount must be greater than zero.');
            $this->redirect("salesorder/view/$id");
            return;
        }

        $order = $this->db->fetchOne(
            "SELECT * FROM sales_orders WHERE id=? AND company_id=?",
            [(int)$id, $cid]
        );
        if (!$order || $order['status'] === 'cancelled') {
            $this->flash('error', 'Order not found or already cancelled.');
            $this->redirect('salesorder');
            return;
        }

        $this->db->beginTransaction();
        try {
            $this->db->execute(
                "INSERT INTO sales_order_payments
                 (company_id, order_id, doc_date, amount, payment_mode, reference, narration, created_by)
                 VALUES (?,?,CURDATE(),?,?,?,?,?)",
                [$cid, (int)$id, $amount, $mode, $ref, $note, $_SESSION['user_id'] ?? null]
            );

            /* Recalculate paid total */
            $totalPaid = (float)$this->db->fetchColumn(
                "SELECT COALESCE(SUM(amount),0) FROM sales_order_payments WHERE order_id=?",
                [(int)$id]
            );
            $balance   = $order['net_amount'] - $totalPaid;
            $newStatus = ($balance <= 0) ? 'closed' : 'partial';

            $this->db->execute(
                "UPDATE sales_orders SET paid_amount=?, balance_due=?, status=? WHERE id=?",
                [$totalPaid, max(0, $balance), $newStatus, (int)$id]
            );

            $this->db->commit();
            $this->auditLog('PAY', 'sales_order', $id, "$$amount");
            $this->flash('success', number_format($amount, 2) . ' payment recorded. Balance: ' . number_format(max(0, $balance), 2));
        } catch (Exception $e) {
            $this->db->rollback();
            $this->flash('error', 'Payment failed: ' . $e->getMessage());
        }

        $this->redirect("salesorder/view/$id");
    }

    /* ─────────────────────────────────────────────────────────────── */
    /*  CONVERT — Sales Order → Sales Invoice                          */
    /* ─────────────────────────────────────────────────────────────── */
    public function convert($id = null) {
        $this->validateCsrf();
        $cid = $this->getCompanyId();
        $bid = $this->getBranchId();

        $order = $this->db->fetchOne(
            "SELECT * FROM sales_orders WHERE id=? AND company_id=? AND status != 'cancelled'",
            [(int)$id, $cid]
        );
        if (!$order) {
            $this->flash('error', 'Order not found or already cancelled.');
            $this->redirect('salesorder');
            return;
        }
        if (!empty($order['converted_sale_id'])) {
            $this->flash('error', 'This order is already converted to invoice ' . $order['converted_sale_id']);
            $this->redirect("salesorder/view/$id");
            return;
        }

        $lines = $this->db->fetchAll(
            "SELECT * FROM sales_order_detail WHERE header_id=?",
            [(int)$id]
        ) ?: [];

        if (empty($lines)) {
            $this->flash('error', 'Cannot convert — order has no line items.');
            $this->redirect("salesorder/view/$id");
            return;
        }

        $this->db->beginTransaction();
        try {
            /* Generate invoice doc_no */
            $last  = $this->db->fetchColumn("SELECT doc_no FROM sales_header WHERE company_id=? ORDER BY id DESC LIMIT 1", [$cid]);
            $num   = $last ? ((int)preg_replace('/\D/', '', $last) + 1) : 1;
            $invNo = 'INV' . date('Y') . str_pad($num, 5, '0', STR_PAD_LEFT);

            $paidSoFar = (float)$order['paid_amount'];
            $netAmt    = (float)$order['net_amount'];
            $balance   = max(0, $netAmt - $paidSoFar);

            /* Insert sales_header */
            $this->db->execute(
                "INSERT INTO sales_header
                 (company_id, branch_id, doc_no, doc_date, doc_time,
                  customer_id, customer_name, ref_no,
                  gross_amount, bill_disc_perc, bill_disc_amount,
                  taxable_amount, total_tax, net_amount,
                  paid_amount, balance_due, status, remarks, created_by)
                 VALUES (?,?,?,CURDATE(),CURTIME(),?,?,?,?,?,?,?,?,?,?,?,'confirmed',?,?)",
                [
                    $cid, $bid, $invNo,
                    $order['customer_id'] ?: null,
                    $order['customer_name'],
                    'SO:' . $order['doc_no'],
                    $order['gross_amount'],
                    $order['bill_disc_perc'],
                    $order['bill_disc_amount'],
                    $order['gross_amount'] - $order['bill_disc_amount'],
                    $order['total_tax'],
                    $netAmt,
                    $paidSoFar,
                    $balance,
                    $order['remarks'],
                    $_SESSION['user_id'] ?? null
                ]
            );
            $saleId = $this->db->lastInsertId();

            /* Copy line items to sales_detail and log stock ledger */
            foreach ($lines as $i => $ln) {
                $this->db->execute(
                     "INSERT INTO sales_detail
                      (header_id, srl_no, item_id, qty, unit_id, rate, value,
                       disc_perc, disc_amount, net_value, tax_type_id)
                      VALUES (?,?,?,?,?,?,?,?,?,?,?)",
                     [
                         $saleId, $i + 1,
                         $ln['item_id'], $ln['qty'], $ln['unit_id'],
                         $ln['rate'], $ln['value'],
                         $ln['disc_perc'], $ln['disc_amount'], $ln['net_value'],
                         $ln['tax_type_id']
                     ]
                 );

                if (!empty($ln['item_id'])) {
                    $this->db->execute(
                        "INSERT INTO stock_ledger (company_id, branch_id, item_id, txn_date, txn_type, doc_no, qty_in, qty_out, rate, value, balance_qty)
                         VALUES (?, ?, ?, CURDATE(), 'SALE', ?, 0.0000, ?, ?, ?, COALESCE((SELECT SUM(qty_in - qty_out) FROM stock_ledger sl WHERE sl.item_id = ? AND sl.branch_id = ?), 0) - ?)",
                        [
                            $cid, $bid, $ln['item_id'], $invNo,
                            (float)$ln['qty'], (float)$ln['rate'], (float)$ln['net_value'],
                            $ln['item_id'], $bid, (float)$ln['qty']
                        ]
                    );
                }
            }

            /* Mark order as closed and record the sale reference */
            $this->db->execute(
                "UPDATE sales_orders SET status='closed', converted_sale_id=? WHERE id=?",
                [$saleId, (int)$id]
            );

            $this->db->commit();
            $this->auditLog('CONVERT', 'sales_order', $id, $invNo);
            $this->flash('success', "Sales Order {$order['doc_no']} successfully converted to Invoice <strong>{$invNo}</strong>.");
            $this->redirect("salesorder/view/$id");

        } catch (Exception $e) {
            $this->db->rollback();
            $this->flash('error', 'Conversion failed: ' . $e->getMessage());
            $this->redirect("salesorder/view/$id");
        }
    }

    /* ─────────────────────────────────────────────────────────────── */
    /*  CANCEL                                                          */
    /* ─────────────────────────────────────────────────────────────── */
    public function cancel($id = null) {
        $this->validateCsrf();
        $cid = $this->getCompanyId();
        $this->db->execute(
            "UPDATE sales_orders SET status='cancelled' WHERE id=? AND company_id=? AND status IN ('open','partial')",
            [(int)$id, $cid]
        );
        $this->auditLog('CANCEL', 'sales_order', $id);
        $this->flash('success', 'Order cancelled.');
        $this->redirect('salesorder');
    }

    /* ─────────────────────────────────────────────────────────────── */
    /*  SEARCH — AJAX item lookup (same as SalesController::search)    */
    /* ─────────────────────────────────────────────────────────────── */
    public function search() {
        $cid   = $this->getCompanyId();
        $q     = trim($this->get('q', ''));
        $limit = min((int)$this->get('limit', 10), 30);

        if (strlen($q) < 1) { $this->json([]); return; }

        $rows = $this->db->fetchAll(
            "SELECT i.id, i.stock_no, i.description, i.price1, i.tax_type_id,
                    COALESCE(u.code,'') AS unit_code
             FROM items i
             LEFT JOIN units u ON u.id = i.unit_id
             WHERE i.company_id=? AND i.is_active=1
               AND (i.stock_no=? OR i.stock_no LIKE ? OR i.description LIKE ?)
             ORDER BY (i.stock_no=?) DESC, i.stock_no ASC
             LIMIT ?",
            [$cid, $q, $q.'%', '%'.$q.'%', $q, $limit]
        );

        $this->json($rows ?: []);
    }
}
