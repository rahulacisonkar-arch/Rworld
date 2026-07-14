<?php
/**
 * PurchasereturnController — Production controller for Purchase Returns
 */
class PurchasereturnController extends Controller {

    public function index() {
        $cid = $this->getCompanyId();
        $bid = $this->getBranchId();
        $search = $this->get('q', '');
        $page = max(1, (int)$this->get('page', 1));

        $where = "WHERE pr.company_id=? AND pr.branch_id=?";
        $params = [$cid, $bid];
        if ($search) {
            $where .= " AND (pr.doc_no LIKE ? OR pr.supplier_name LIKE ?)";
            $params[] = "%$search%";
            $params[] = "%$search%";
        }

        $sql = "SELECT pr.*
                FROM purchase_returns pr
                $where ORDER BY pr.id DESC";

        $paged = $this->paginate($sql, $params, $page);

        $this->render('purchase/returns/index', [
            'pageTitle' => 'Purchase Returns',
            'records' => $paged,
            'search' => $search,
            'breadcrumbs' => [
                ['label' => 'Dashboard', 'url' => APP_URL . '/dashboard'],
                ['label' => 'Purchase', 'url' => '#'],
                ['label' => 'Purchase Returns', 'url' => APP_URL . '/purchasereturn'],
            ]
        ]);
    }

    public function create() {
        $cid = $this->getCompanyId();
        $bid = $this->getBranchId();

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->validateCsrf();

            $last = $this->db->fetchColumn("SELECT doc_no FROM purchase_returns WHERE company_id=? ORDER BY id DESC LIMIT 1", [$cid]);
            $num = $last ? ((int)preg_replace('/\D/', '', $last) + 1) : 1;
            $docNo = 'PRT' . date('Y') . str_pad($num, 5, '0', STR_PAD_LEFT);
            $lineItems = json_decode($this->post('line_items', '[]'), true) ?: [];

            $gross = 0;
            foreach ($lineItems as $ln) {
                $gross += (float)($ln['qty'] ?? 1) * (float)($ln['rate'] ?? $ln['unit_price'] ?? 0);
            }
            $tax = round($gross * 0.0825, 4);
            $net = $gross + $tax;

            $this->db->beginTransaction();
            try {
                $this->db->execute(
                    "INSERT INTO purchase_returns (company_id, branch_id, doc_no, doc_date, supplier_id, supplier_name, gross_amount, total_tax, net_amount, status, remarks)
                     VALUES (?, ?, ?, CURDATE(), ?, ?, ?, ?, ?, 'confirmed', ?)",
                    [
                        $cid, $bid, $docNo,
                        $this->post('supplier_id') ?: null,
                        $this->post('supplier_name'),
                        $gross, $tax, $net,
                        $this->post('remarks')
                    ]
                );
                $prId = $this->db->lastInsertId();

                foreach ($lineItems as $i => $ln) {
                    $qty   = (float)($ln['qty']        ?? 1);
                    $rate  = (float)($ln['rate']       ?? $ln['unit_price'] ?? 0);
                    $value = $qty * $rate;
                    $this->db->execute(
                        "INSERT INTO purchase_return_detail (header_id, srl_no, item_id, qty, rate, value, net_value)
                         VALUES (?, ?, ?, ?, ?, ?, ?)",
                        [
                            $prId, $i + 1,
                            $ln['item_id'] ?? null,
                            $qty, $rate, $value, $value
                        ]
                    );

                    // Stock deduction since items are returned to supplier
                    if (!empty($ln['item_id'])) {
                        $this->db->execute(
                            "INSERT INTO stock_ledger (company_id, branch_id, item_id, txn_date, txn_type, doc_no, qty_in, qty_out, rate, value, balance_qty)
                             VALUES (?, ?, ?, CURDATE(), 'PURCHASE_RETURN', ?, 0, ?, ?, ?, COALESCE((SELECT SUM(qty_in - qty_out) FROM stock_ledger sl WHERE sl.item_id = ? AND sl.branch_id = ?), 0) - ?)",
                            [
                                $cid, $bid, $ln['item_id'], $docNo,
                                $qty, $rate, $value,
                                $ln['item_id'], $bid, $qty
                            ]
                        );
                    }
                }

                $this->db->commit();
                $this->auditLog('CREATE', 'purchase_return', $prId, $docNo);
                $this->flash('success', "Purchase Return $docNo created successfully.");
                $this->redirect('purchasereturn');
            } catch (Exception $e) {
                $this->db->rollback();
                $this->flash('error', 'Error creating purchase return: ' . $e->getMessage());
            }
        }

        $suppliers = $this->db->fetchAll("SELECT id, code, name FROM suppliers WHERE company_id=? AND is_active=1 ORDER BY name", [$cid]);
        $items = [];

        $this->render('purchase/returns/form', [
            'pageTitle' => 'New Purchase Return',
            'suppliers' => $suppliers,
            'items' => $items,
            'breadcrumbs' => [
                ['label' => 'Dashboard', 'url' => APP_URL . '/dashboard'],
                ['label' => 'Purchase Returns', 'url' => APP_URL . '/purchasereturn'],
                ['label' => 'New Return', 'url' => '#'],
            ]
        ]);
    }
}
