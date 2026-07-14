<?php
/**
 * GrnController — Goods Received Note controller
 * Table: grn — real columns: supplier_id, purchase_id, po_id, status (no total_amount, no net_amount)
 */
class GrnController extends Controller {

    public function index() {
        $cid  = $this->getCompanyId();
        $bid  = $this->getBranchId();
        $page = max(1, (int)$this->get('page', 1));

        $sql = "SELECT g.*, s.name AS supplier_name,
                       ph.doc_no AS purchase_doc_no
                 FROM grn g
                 LEFT JOIN suppliers s ON s.id = g.supplier_id
                 LEFT JOIN purchase_header ph ON ph.id = g.purchase_id
                 WHERE g.company_id=? AND g.branch_id=?
                 ORDER BY g.id DESC";

        $paged = $this->paginate($sql, [$cid, $bid], $page);

        $this->render('purchase/grn/index', [
            'pageTitle' => 'Goods Received Notes',
            'records'   => $paged,
            'breadcrumbs' => [
                ['label' => 'Dashboard', 'url' => APP_URL . '/dashboard'],
                ['label' => 'Purchase', 'url' => '#'],
                ['label' => 'GRN', 'url' => APP_URL . '/grn'],
            ]
        ]);
    }

    public function create() {
        $cid = $this->getCompanyId();
        $bid = $this->getBranchId();

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->validateCsrf();

            $last  = $this->db->fetchColumn("SELECT doc_no FROM grn WHERE company_id=? ORDER BY id DESC LIMIT 1", [$cid]);
            $num   = $last ? ((int)preg_replace('/\D/', '', $last) + 1) : 1;
            $docNo = 'GRN' . date('Y') . str_pad($num, 5, '0', STR_PAD_LEFT);
            $lineItems = json_decode($this->post('line_items', '[]'), true) ?: [];

            $this->db->beginTransaction();
            try {
                $this->db->execute(
                    "INSERT INTO grn (company_id, branch_id, doc_no, doc_date, supplier_id, purchase_id, status, created_by)
                     VALUES (?, ?, ?, ?, ?, ?, 'posted', ?)",
                    [
                        $cid, $bid, $docNo,
                        $this->post('doc_date', date('Y-m-d')),
                        $this->post('supplier_id') ?: null,
                        $this->post('purchase_id') ?: null,
                        $_SESSION['user_id'] ?? null
                    ]
                );
                $grnId = $this->db->lastInsertId();

                foreach ($lineItems as $ln) {
                    $itemId = $ln['item_id'] ?: null;
                    $qty = (float)($ln['qty'] ?? 1);
                    if (!$itemId) continue;

                    $this->db->execute(
                        "INSERT INTO grn_detail (header_id, item_id, received_qty)
                         VALUES (?, ?, ?)",
                        [$grnId, $itemId, $qty]
                    );

                    // Fetch cost price to calculate stock value
                    $costPrice = (float)$this->db->fetchColumn("SELECT cost_price FROM items WHERE id=?", [$itemId]);

                    // Add to stock ledger
                    $this->db->execute(
                        "INSERT INTO stock_ledger (company_id, branch_id, item_id, txn_date, txn_type, doc_no, qty_in, qty_out, rate, value, balance_qty)
                         VALUES (?, ?, ?, CURDATE(), 'GRN', ?, ?, 0.0000, ?, ?, COALESCE((SELECT SUM(qty_in - qty_out) FROM stock_ledger sl WHERE sl.item_id = ? AND sl.branch_id = ?), 0) + ?)",
                        [
                            $cid, $bid, $itemId, $docNo,
                            $qty, $costPrice, ($qty * $costPrice),
                            $itemId, $bid, $qty
                        ]
                    );
                }

                $this->db->commit();
                $this->auditLog('CREATE', 'grn', $grnId, $docNo);
                $this->flash('success', "GRN {$docNo} posted successfully.");
                $this->redirect('grn');
            } catch (Exception $e) {
                $this->db->rollback();
                $this->flash('error', 'Error posting Goods Receipt: ' . $e->getMessage());
            }
        }

        $suppliers = $this->db->fetchAll("SELECT id, code, name FROM suppliers WHERE company_id=? AND is_active=1 ORDER BY name", [$cid]) ?: [];
        $purchases = $this->db->fetchAll("SELECT id, doc_no FROM purchase_header WHERE company_id=? AND branch_id=? AND status='confirmed' ORDER BY id DESC LIMIT 50", [$cid, $bid]) ?: [];
        $items = [];

        $this->render('purchase/grn/form', [
            'pageTitle' => 'New GRN',
            'suppliers' => $suppliers,
            'purchases' => $purchases,
            'items'     => $items,
            'breadcrumbs' => [
                ['label' => 'Dashboard', 'url' => APP_URL . '/dashboard'],
                ['label' => 'GRN', 'url' => APP_URL . '/grn'],
                ['label' => 'New GRN', 'url' => '#'],
            ]
        ]);
    }
}
