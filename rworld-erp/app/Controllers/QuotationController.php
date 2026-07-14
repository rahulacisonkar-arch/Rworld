<?php
/**
 * QuotationController — Full production quotation management with list, create, view, and convert-to-order.
 * Uses real DB column names:
 *   quotation_detail: (header_id, item_id, srl_no, qty, rate, disc_perc, disc_amount, net_value, tax_type_id, t1_amount..t5_amount)
 *   sales_order_detail: (header_id, srl_no, item_id, qty, delivered_qty, rate, value, disc_perc, disc_amount, net_value, tax_type_id, total_tax)
 */
class QuotationController extends Controller {

    public function index() {
        $cid = $this->getCompanyId();
        $bid = $this->getBranchId();
        $search = $this->get('q', '');
        $status = $this->get('status', '');
        $page = max(1, (int)$this->get('page', 1));

        $where = "WHERE q.company_id=? AND q.branch_id=?";
        $params = [$cid, $bid];
        if ($search) { $where .= " AND (q.doc_no LIKE ? OR q.customer_name LIKE ?)"; $params[] = "%$search%"; $params[] = "%$search%"; }
        if ($status) { $where .= " AND q.status=?"; $params[] = $status; }

        $sql = "SELECT q.id, q.doc_no, q.doc_date, q.valid_till, q.customer_name,
                       q.net_amount, q.status, q.created_at
                FROM quotations q $where ORDER BY q.id DESC";

        $paged = $this->paginate($sql, $params, $page);

        $this->render('billing/quotations/index', [
            'pageTitle' => 'Quotations',
            'records' => $paged,
            'search' => $search,
            'filterStatus' => $status,
            'breadcrumbs' => [
                ['label' => 'Dashboard', 'url' => APP_URL . '/dashboard'],
                ['label' => 'Billing', 'url' => '#'],
                ['label' => 'Quotations', 'url' => APP_URL . '/quotation'],
            ]
        ]);
    }

    /**
     * Print Quotation
     */
    public function print($id = null) {
        $cid = $this->getCompanyId();
        $q = $this->db->fetchOne(
            "SELECT q.*, COALESCE(c.name, q.customer_name, 'Walk-in Customer') AS customer_display,
                    COALESCE(c.phone1, '') AS customer_phone
             FROM quotations q
             LEFT JOIN customers c ON c.id = q.customer_id
             WHERE q.id=? AND q.company_id=?",
            [(int)$id, $cid]
        );
        if (!$q) { die("Quotation not found."); }

        // Use real column names (rate, disc_perc, net_value from quotation_detail)
        $lines = $this->db->fetchAll(
            "SELECT qd.*, i.stock_no, i.description FROM quotation_detail qd
             LEFT JOIN items i ON i.id = qd.item_id
             WHERE qd.header_id=?",
            [(int)$id]
        ) ?: [];

        $company = $this->db->fetchOne("SELECT * FROM companies WHERE id=?", [$cid]);

        $this->render('billing/print_invoice', [
            'type'    => 'QUOTATION',
            'record'  => $q,
            'lines'   => $lines,
            'company' => $company
        ], 'minimal');
    }

    public function create() {
        $cid = $this->getCompanyId();
        $bid = $this->getBranchId();

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->validateCsrf();

            $docNo = $this->generateDocNo('QTN', 'quotations');
            $lineItems = json_decode($this->post('line_items', '[]'), true) ?: [];

            $gross = 0;
            foreach ($lineItems as $ln) {
                $qty  = (float)($ln['qty']   ?? 1);
                $rate = (float)($ln['rate']  ?? $ln['unit_price'] ?? 0);
                $disc = (float)($ln['disc_perc'] ?? $ln['discount_pct'] ?? 0);
                $val  = $qty * $rate;
                $gross += $val - ($val * $disc / 100);
            }
            $tax = round($gross * 0.0825, 4);
            $net = $gross + $tax;

            $this->db->beginTransaction();
            try {
                $this->db->execute(
                    "INSERT INTO quotations (company_id, branch_id, doc_no, doc_date, valid_till,
                     customer_id, customer_name, gross_amount, total_tax, net_amount, status, remarks)
                     VALUES (?,?,?,?,?,?,?,?,?,?,'open',?)",
                    [
                        $cid, $bid, $docNo,
                        $this->post('doc_date', date('Y-m-d')),
                        $this->post('valid_till', date('Y-m-d', strtotime('+30 days'))),
                        $this->post('customer_id') ?: null,
                        $this->post('customer_name'),
                        $gross, $tax, $net,
                        $this->post('remarks')
                    ]
                );
                $qid = $this->db->lastInsertId();

                foreach ($lineItems as $i => $ln) {
                    $qty     = (float)($ln['qty']      ?? 1);
                    $rate    = (float)($ln['rate']     ?? $ln['unit_price'] ?? 0);
                    $discPrc = (float)($ln['disc_perc'] ?? $ln['discount_pct'] ?? 0);
                    $value   = $qty * $rate;
                    $discAmt = $value * $discPrc / 100;
                    $netVal  = $value - $discAmt;

                    $this->db->execute(
                        "INSERT INTO quotation_detail (header_id, srl_no, item_id, qty, rate, value, disc_perc, disc_amount, net_value, tax_type_id)
                         VALUES (?,?,?,?,?,?,?,?,?,?)",
                        [
                            $qid, $i + 1,
                            $ln['item_id'] ?? null,
                            $qty, $rate, $value,
                            $discPrc, $discAmt, $netVal,
                            $ln['tax_type_id'] ?? null,
                        ]
                    );
                }

                $this->db->commit();
                $this->auditLog('CREATE', 'quotation', $qid, $docNo);
                $this->flash('success', "Quotation $docNo created successfully.");
                $this->redirect("quotation/view/$qid");
            } catch (Exception $e) {
                $this->db->rollback();
                $this->flash('error', 'Error creating quotation: ' . $e->getMessage());
            }
        }

        $customers = $this->db->fetchAll("SELECT id, code, name FROM customers WHERE company_id=? AND is_active=1 ORDER BY name", [$cid]);
        $items = [];

        $this->render('billing/quotations/form', [
            'pageTitle' => 'New Quotation',
            'record' => null,
            'customers' => $customers,
            'items' => $items,
            'breadcrumbs' => [
                ['label' => 'Dashboard', 'url' => APP_URL . '/dashboard'],
                ['label' => 'Quotations', 'url' => APP_URL . '/quotation'],
                ['label' => 'New Quotation', 'url' => '#'],
            ]
        ]);
    }

    public function view($id = null) {
        $cid = $this->getCompanyId();
        $record = $this->db->fetchOne(
            "SELECT * FROM quotations WHERE id=? AND company_id=?", [(int)$id, $cid]
        );
        if (!$record) { $this->flash('error', 'Quotation not found.'); $this->redirect('quotation'); }

        $lines = $this->db->fetchAll(
            "SELECT qd.*, i.stock_no, i.barcode, i.description AS item_name FROM quotation_detail qd
             LEFT JOIN items i ON i.id = qd.item_id
             WHERE qd.header_id=?", [(int)$id]
        );

        $this->render('billing/quotations/view', [
            'pageTitle' => 'Quotation ' . $record['doc_no'],
            'record' => $record,
            'lines' => $lines,
            'breadcrumbs' => [
                ['label' => 'Dashboard', 'url' => APP_URL . '/dashboard'],
                ['label' => 'Quotations', 'url' => APP_URL . '/quotation'],
                ['label' => $record['doc_no'], 'url' => '#'],
            ]
        ]);
    }

    public function convert($id = null) {
        // Convert quotation to sales order using correct column names
        $this->validateCsrf();
        $cid = $this->getCompanyId();
        $bid = $this->getBranchId();

        $q = $this->db->fetchOne("SELECT * FROM quotations WHERE id=? AND company_id=? AND status='open'", [(int)$id, $cid]);
        if (!$q) { $this->json(['success' => false, 'message' => 'Quotation not found or already converted.']); return; }

        $lines = $this->db->fetchAll("SELECT * FROM quotation_detail WHERE header_id=?", [(int)$id]);

        $docNo = $this->generateDocNo('SO', 'sales_orders');

        $this->db->beginTransaction();
        try {
            $this->db->execute(
                "INSERT INTO sales_orders (company_id, branch_id, doc_no, doc_date, customer_id, customer_name, gross_amount, net_amount, status, remarks)
                 VALUES (?,?,?,CURDATE(),?,?,?,?,'open',?)",
                [$cid, $bid, $docNo, $q['customer_id'], $q['customer_name'], $q['gross_amount'], $q['net_amount'], 'Converted from ' . $q['doc_no']]
            );
            $soId = $this->db->lastInsertId();

            foreach ($lines as $i => $ln) {
                $this->db->execute(
                    "INSERT INTO sales_order_detail (header_id, srl_no, item_id, qty, delivered_qty, rate, value, disc_perc, disc_amount, net_value, tax_type_id, total_tax)
                     VALUES (?,?,?,?,0,?,?,?,?,?,?,0)",
                    [
                        $soId, $i + 1,
                        $ln['item_id'],
                        $ln['qty'],
                        $ln['rate'],
                        $ln['value'],
                        $ln['disc_perc'],
                        $ln['disc_amount'],
                        $ln['net_value'],
                        $ln['tax_type_id']
                    ]
                );
            }

            $this->db->execute("UPDATE quotations SET status='converted' WHERE id=?", [(int)$id]);
            $this->db->commit();

            $this->auditLog('CONVERT', 'quotation', $id, $q['doc_no']);
            $this->json(['success' => true, 'message' => "Converted to Sales Order $docNo", 'redirect' => APP_URL . '/quotation']);
        } catch (Exception $e) {
            $this->db->rollback();
            $this->json(['success' => false, 'message' => 'Conversion failed: ' . $e->getMessage()]);
        }
    }

    private function generateDocNo($prefix, $table) {
        $cid = $this->getCompanyId();
        $last = $this->db->fetchColumn(
            "SELECT doc_no FROM $table WHERE company_id=? ORDER BY id DESC LIMIT 1", [$cid]
        );
        $num = $last ? ((int)preg_replace('/\D/', '', $last) + 1) : 1;
        return $prefix . date('Y') . str_pad($num, 5, '0', STR_PAD_LEFT);
    }
}
