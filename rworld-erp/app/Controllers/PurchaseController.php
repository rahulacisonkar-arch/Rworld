<?php
/**
 * PurchaseController — Full production purchase invoice management (list, new, view).
 * Uses real DB column names: purchase_detail(header_id, rate, disc_perc, value, net_value)
 */
class PurchaseController extends Controller {

    public function index() {
        $cid = $this->getCompanyId();
        $bid = $this->getBranchId();
        $search = $this->get('q', '');
        $page = max(1, (int)$this->get('page', 1));

        $where = "WHERE ph.company_id=? AND ph.branch_id=?";
        $params = [$cid, $bid];
        if ($search) { $where .= " AND (ph.doc_no LIKE ? OR ph.supplier_name LIKE ?)"; $params[] = "%$search%"; $params[] = "%$search%"; }

        $sql = "SELECT ph.id, ph.doc_no, ph.doc_date, ph.supplier_name, ph.net_amount,
                       ph.paid_amount, ph.balance_due, ph.status
                FROM purchase_header ph $where ORDER BY ph.id DESC";

        $paged = $this->paginate($sql, $params, $page);

        $this->render('purchase/index', [
            'pageTitle' => 'Purchase List',
            'records' => $paged,
            'search' => $search,
            'breadcrumbs' => [
                ['label' => 'Dashboard', 'url' => APP_URL . '/dashboard'],
                ['label' => 'Purchase', 'url' => '#'],
                ['label' => 'Purchase List', 'url' => APP_URL . '/purchase'],
            ]
        ]);
    }

    public function create() {
        $cid = $this->getCompanyId();
        $bid = $this->getBranchId();

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->validateCsrf();

            $lineItems = json_decode($this->post('line_items', '[]'), true) ?: [];
            $gross = 0;
            foreach ($lineItems as $ln) {
                $gross += (float)($ln['qty'] ?? 1) * (float)($ln['rate'] ?? 0);
            }
            $tax = round($gross * 0.0825, 4);
            $net = $gross + $tax;

            $lastPH = $this->db->fetchColumn("SELECT doc_no FROM purchase_header WHERE company_id=? ORDER BY id DESC LIMIT 1", [$cid]);
            $num = $lastPH ? ((int)preg_replace('/\D/', '', $lastPH) + 1) : 1;
            $docNo = 'PUR' . date('Y') . str_pad($num, 5, '0', STR_PAD_LEFT);

            $this->db->beginTransaction();
            try {
                $this->db->execute(
                    "INSERT INTO purchase_header (company_id, branch_id, doc_no, doc_date, supplier_id, supplier_name,
                     supplier_inv_no, gross_amount, taxable_amount, total_tax, net_amount, balance_due, status, remarks)
                     VALUES (?,?,?,?,?,?,?,?,?,?,?,?,'confirmed',?)",
                    [
                        $cid, $bid, $docNo,
                        $this->post('doc_date', date('Y-m-d')),
                        $this->post('supplier_id') ?: null,
                        $this->post('supplier_name'),
                        $this->post('supplier_inv_no'),
                        $gross, $gross, $tax, $net, $net,
                        $this->post('remarks')
                    ]
                );
                $phId = $this->db->lastInsertId();

                foreach ($lineItems as $i => $ln) {
                    $qty      = (float)($ln['qty']          ?? 1);
                    $rate     = (float)($ln['rate']         ?? $ln['unit_price'] ?? 0);
                    $discPerc = (float)($ln['disc_perc']    ?? $ln['discount_pct'] ?? 0);
                    $value    = $qty * $rate;
                    $discAmt  = $value * $discPerc / 100;
                    $netVal   = $value - $discAmt;

                    $this->db->execute(
                        "INSERT INTO purchase_detail (header_id, srl_no, item_id, qty, rate, value, disc_perc, disc_amount, net_value)
                         VALUES (?,?,?,?,?,?,?,?,?)",
                        [
                            $phId, $i + 1,
                            $ln['item_id'] ?? null,
                            $qty, $rate, $value,
                            $discPerc, $discAmt, $netVal,
                        ]
                    );

                    // Update stock ledger
                    if (!empty($ln['item_id'])) {
                        $this->db->execute(
                            "INSERT INTO stock_ledger (company_id, branch_id, item_id, txn_date, txn_type, doc_no, qty_in, qty_out, rate, value, balance_qty)
                             VALUES (?,?,?,?,'PURCHASE',?,?,0,?,?,COALESCE((SELECT SUM(qty_in - qty_out) FROM stock_ledger sl WHERE sl.item_id = ? AND sl.branch_id = ?), 0) + ?)",
                            [
                                $cid, $bid, $ln['item_id'],
                                $this->post('doc_date', date('Y-m-d')),
                                $docNo,
                                $qty, $rate, $netVal,
                                $ln['item_id'], $bid, $qty
                            ]
                        );
                    }
                }

                // Post payable voucher to accounts
                $this->db->execute(
                    "INSERT INTO vouchers (company_id, branch_id, doc_no, doc_date, voucher_type, narration, total_amount, status)
                     VALUES (?,?,?,?,'journal',?,?,'confirmed')",
                    [$cid, $bid, 'JV-' . $docNo, $this->post('doc_date', date('Y-m-d')), "Purchase Invoice $docNo", $net]
                );

                $this->db->commit();
                $this->auditLog('CREATE', 'purchase', $phId, $docNo);
                $this->flash('success', "Purchase Invoice $docNo saved successfully.");
                $this->redirect("purchase/view/$phId");
            } catch (Exception $e) {
                $this->db->rollback();
                $this->flash('error', 'Error saving purchase: ' . $e->getMessage());
            }
        }

        $suppliers = $this->db->fetchAll("SELECT id, code, name FROM suppliers WHERE company_id=? AND is_active=1 ORDER BY name", [$cid]);
        $items = [];

        $this->render('purchase/form', [
            'pageTitle' => 'New Purchase',
            'record' => null,
            'suppliers' => $suppliers,
            'items' => $items,
            'breadcrumbs' => [
                ['label' => 'Dashboard', 'url' => APP_URL . '/dashboard'],
                ['label' => 'Purchase', 'url' => APP_URL . '/purchase'],
                ['label' => 'New Purchase', 'url' => '#'],
            ]
        ]);
    }

    public function view($id = null) {
        $cid = $this->getCompanyId();
        $record = $this->db->fetchOne(
            "SELECT * FROM purchase_header WHERE id=? AND company_id=?", [(int)$id, $cid]
        );
        if (!$record) { $this->flash('error', 'Purchase not found.'); $this->redirect('purchase'); }

        $lines = $this->db->fetchAll(
            "SELECT pd.*, i.stock_no, i.barcode, i.description FROM purchase_detail pd
             LEFT JOIN items i ON i.id = pd.item_id
             WHERE pd.header_id=?", [(int)$id]
        );

        $this->render('purchase/view', [
            'pageTitle' => 'Purchase ' . $record['doc_no'],
            'record' => $record,
            'lines' => $lines,
            'breadcrumbs' => [
                ['label' => 'Dashboard', 'url' => APP_URL . '/dashboard'],
                ['label' => 'Purchase', 'url' => APP_URL . '/purchase'],
                ['label' => $record['doc_no'], 'url' => '#'],
            ]
        ]);
    }
}
