<?php
/**
 * SalesController — POS / New Sale + Sales List + Print Invoice
 */
class SalesController extends Controller {

    /**
     * For AJAX calls, return JSON 401 instead of redirect
     */
    protected function checkAuth() {
        if (empty($_SESSION['user_id'])) {
            $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH'])
                   || strpos($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') !== false
                   || isset($_GET['q']);
            if ($isAjax) {
                $this->json(['error' => 'Unauthenticated'], 401);
            } else {
                $this->redirect('auth/login');
            }
        }
    }

    /**
     * New POS Invoice page
     */
    public function create() {
        $db        = $this->db;
        $companyId = $this->getCompanyId();
        $branchId  = $this->getBranchId();
        $uid       = $_SESSION['user_id'] ?? 0;

        // Check active open register shift session
        $openShift = $db->fetchOne(
            "SELECT id FROM shift_closure WHERE company_id = ? AND branch_id = ? AND user_id = ? AND closed_at IS NULL",
            [$companyId, $branchId, $uid]
        );

        if (!$openShift) {
            $this->flash('warning', 'Please open a cash drawer shift before starting checkout.');
            $this->redirect('shift/open');
        }

        $_SESSION['shift_id'] = $openShift['id'];

        $customers = $db->fetchAll(
            "SELECT id, code, name, phone1, is_tax_exempt FROM customers WHERE company_id=? AND is_active=1 ORDER BY name",
            [$companyId]
        ) ?: [];

        $taxTypes = $db->fetchAll(
            "SELECT * FROM tax_types WHERE company_id=? AND is_active=1",
            [$companyId]
        ) ?: [];

        $this->render('sales/create', [
            'pageTitle' => 'New Sale (POS)',
            'customers' => $customers,
            'taxTypes'  => $taxTypes
        ]);
    }

    /**
     * Store POS sale via AJAX POST
     */
    public function store() {
        $this->validateCsrf();
        $db        = $this->db;
        $companyId = $this->getCompanyId();
        $branchId  = $this->getBranchId();
        $uid       = $_SESSION['user_id'] ?? 0;

        // Check active open register shift session
        $openShift = $db->fetchOne(
            "SELECT id FROM shift_closure WHERE company_id = ? AND branch_id = ? AND user_id = ? AND closed_at IS NULL",
            [$companyId, $branchId, $uid]
        );
        if (!$openShift) {
            $this->json(['success' => false, 'message' => 'Please open a cash register shift session before making sales.'], 403);
            return;
        }

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

        if (empty($items)) {
            $this->json(['success' => false, 'message' => 'No items in sale.'], 422);
            return;
        }

        // Generate invoice doc_no
        $last  = $db->fetchColumn("SELECT doc_no FROM sales_header WHERE company_id=? ORDER BY id DESC LIMIT 1", [$companyId]);
        $num   = $last ? ((int)preg_replace('/\D/', '', $last) + 1) : 1;
        $docNo = 'INV' . date('Y') . str_pad($num, 5, '0', STR_PAD_LEFT);

        $balance = max(0.0, $netAmt - $paidNow);

        $db->beginTransaction();
        try {
            // 1. Insert sales_header
            $db->execute(
                "INSERT INTO sales_header
                 (company_id, branch_id, doc_no, doc_date, doc_time,
                  customer_id, customer_name, gross_amount, bill_disc_perc, bill_disc_amount,
                  taxable_amount, total_tax, net_amount, paid_amount, balance_due, status, remarks, created_by)
                 VALUES (?,?,?,CURDATE(),CURTIME(),?,?,?,?,?,?,?,?,?,?,?,?)",
                [
                    $companyId, $branchId, $docNo,
                    $customerId ?: null, $custName,
                    $grossAmt, $discPerc, $discAmt,
                    $grossAmt - $discAmt, $taxAmt, $netAmt, $paidNow, $balance,
                    'confirmed', $remarks, $_SESSION['user_id'] ?? null
                ]
            );
            $saleId = $db->lastInsertId();

            // 2. Insert detail lines & Stock ledger
            foreach ($items as $idx => $ln) {
                $qty      = (float)($ln['qty']   ?? 1);
                $rate     = (float)($ln['price'] ?? 0);
                $discPRow = (float)($ln['disc']  ?? 0);
                $value    = $qty * $rate;
                $discAmtR = $value * $discPRow / 100;
                $netVal   = $value - $discAmtR;

                $taxTypeId = $ln['tax_type_id'] ?? null;
                $taxRate = 0.0;
                if ($taxTypeId) {
                    $taxRate = (float)$db->fetchColumn("SELECT rate FROM tax_types WHERE id = ?", [$taxTypeId]);
                }
                $lineTax = $netVal * ($taxRate / 100);

                $db->execute(
                    "INSERT INTO sales_detail
                     (header_id, srl_no, item_id, qty, rate, value, disc_perc, disc_amount, net_value, tax_type_id, total_tax_perc, t1_amount)
                     VALUES (?,?,?,?,?,?,?,?,?,?,?,?)",
                    [
                        $saleId, $idx + 1,
                        $ln['id'] ?? null,
                        $qty, $rate, $value,
                        $discPRow, $discAmtR, $netVal,
                        $taxTypeId, $taxRate, $lineTax
                    ]
                );

                // Update stock ledger
                if (!empty($ln['id'])) {
                    $db->execute(
                        "INSERT INTO stock_ledger (company_id, branch_id, item_id, txn_date, txn_type, doc_no, qty_in, qty_out, rate, value, balance_qty)
                         VALUES (?, ?, ?, CURDATE(), 'SALE', ?, 0.0000, ?, ?, ?, COALESCE((SELECT SUM(qty_in - qty_out) FROM stock_ledger sl WHERE sl.item_id = ? AND sl.branch_id = ?), 0) - ?)",
                        [
                            $companyId, $branchId, $ln['id'], $docNo,
                            $qty, $rate, $netVal, $ln['id'], $branchId, $qty
                        ]
                    );
                }
            }

            // Record payment logs if paidNow > 0
            if ($paidNow > 0) {
                $db->execute(
                    "INSERT INTO payments_received (company_id, branch_id, doc_no, doc_date, customer_id, payment_mode_id, amount, narration, against_sale_id, created_by)
                     VALUES (?, ?, ?, CURDATE(), ?, 1, ?, 'Payment received on sale', ?, ?)",
                    [$companyId, $branchId, $docNo, $customerId ?: null, $paidNow, $saleId, $_SESSION['user_id'] ?? null]
                );
            }

            // 3. Post to General Ledger (automated double-entry journaling)
            require_once APP_PATH . '/Models/JournalModel.php';
            $journal = new JournalModel();
            $journal->postSale($companyId, $branchId, $saleId);

            $db->commit();
            $this->auditLog('CREATE', 'sale', $saleId, $docNo);
            $this->json(['success' => true, 'sale_id' => $saleId, 'doc_no' => $docNo]);

        } catch (Exception $e) {
            $db->rollback();
            $this->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Print standard invoice
     */
    public function print($id = null) {
        $cid = $this->getCompanyId();
        $sale = $this->db->fetchOne(
            "SELECT s.*, COALESCE(c.name, s.customer_name, 'Walk-in Customer') AS customer_display,
                    COALESCE(c.phone1, '') AS customer_phone
             FROM sales_header s
             LEFT JOIN customers c ON c.id = s.customer_id
             WHERE s.id=? AND s.company_id=?",
            [(int)$id, $cid]
        );
        if (!$sale) { die("Invoice not found."); }

        $lines = $this->db->fetchAll(
            "SELECT sd.*, i.stock_no, i.description FROM sales_detail sd
             LEFT JOIN items i ON i.id = sd.item_id
             WHERE sd.header_id=?",
            [(int)$id]
        ) ?: [];

        $company = $this->db->fetchOne("SELECT * FROM companies WHERE id=?", [$cid]);

        // Render minimal print layout
        $this->render('billing/print_invoice', [
            'type'    => 'TAX INVOICE',
            'record'  => $sale,
            'lines'   => $lines,
            'company' => $company
        ], 'minimal');
    }

    /**
     * AJAX item lookup
     */
    public function search() {
        $db        = $this->db;
        $companyId = $this->getCompanyId();
        $q         = trim($this->get('q', ''));
        $limit     = min((int)$this->get('limit', 10), 30);

        if (strlen($q) < 1) {
            $this->json([]);
            return;
        }

        $rows = $db->fetchAll(
            "SELECT i.id, i.stock_no, i.description, i.price1, i.tax_type_id,
                    t.code AS tax_code, t.is_inclusive,
                    COALESCE(u.code, '') AS unit_code
             FROM items i
             LEFT JOIN tax_types t ON t.id = i.tax_type_id
             LEFT JOIN units     u ON u.id = i.unit_id
             WHERE i.company_id = ? AND i.is_active = 1
               AND (i.stock_no = ? OR i.stock_no LIKE ? OR i.description LIKE ?)
             ORDER BY (i.stock_no = ?) DESC, i.stock_no ASC
             LIMIT ?",
            [$companyId, $q, $q . '%', '%' . $q . '%', $q, $limit]
        );

        $this->json($rows ?: []);
    }

    /**
     * Sales list
     */
    public function index() {
        $db        = $this->db;
        $companyId = $this->getCompanyId();
        $branchId  = $this->getBranchId();

        $sales = $db->fetchAll(
            "SELECT s.*, COALESCE(s.customer_name, 'Walk-in Customer') AS display_name
             FROM sales_header s
             WHERE s.company_id=? AND s.branch_id=?
             ORDER BY s.id DESC",
            [$companyId, $branchId]
        ) ?: [];

        $this->render('sales/index', [
            'pageTitle' => 'Sales List',
            'sales'     => $sales
        ]);
    }
}
