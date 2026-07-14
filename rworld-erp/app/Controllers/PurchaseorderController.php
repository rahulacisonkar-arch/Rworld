<?php
/**
 * PurchaseorderController — Production controller for Purchase Orders
 */
class PurchaseorderController extends Controller {

    public function index() {
        $cid = $this->getCompanyId();
        $bid = $this->getBranchId();
        $search = $this->get('q', '');
        $page = max(1, (int)$this->get('page', 1));

        $where = "WHERE po.company_id=? AND po.branch_id=?";
        $params = [$cid, $bid];
        if ($search) {
            $where .= " AND (po.doc_no LIKE ? OR po.supplier_name LIKE ?)";
            $params[] = "%$search%";
            $params[] = "%$search%";
        }

        $sql = "SELECT po.*
                FROM purchase_orders po
                $where ORDER BY po.id DESC";

        $paged = $this->paginate($sql, $params, $page);

        $this->render('purchase/orders/index', [
            'pageTitle' => 'Purchase Orders',
            'records' => $paged,
            'search' => $search,
            'breadcrumbs' => [
                ['label' => 'Dashboard', 'url' => APP_URL . '/dashboard'],
                ['label' => 'Purchase', 'url' => '#'],
                ['label' => 'Purchase Orders', 'url' => APP_URL . '/purchaseorder'],
            ]
        ]);
    }

    public function create() {
        $cid = $this->getCompanyId();
        $bid = $this->getBranchId();

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->validateCsrf();

            $last = $this->db->fetchColumn("SELECT doc_no FROM purchase_orders WHERE company_id=? ORDER BY id DESC LIMIT 1", [$cid]);
            $num = $last ? ((int)preg_replace('/\D/', '', $last) + 1) : 1;
            $docNo = 'PO' . date('Y') . str_pad($num, 5, '0', STR_PAD_LEFT);
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
                    "INSERT INTO purchase_orders (company_id, branch_id, doc_no, doc_date, valid_till, supplier_id, supplier_name, net_amount, status, remarks)
                     VALUES (?, ?, ?, CURDATE(), DATE_ADD(CURDATE(), INTERVAL 30 DAY), ?, ?, ?, 'open', ?)",
                    [
                        $cid, $bid, $docNo,
                        $this->post('supplier_id') ?: null,
                        $this->post('supplier_name'),
                        $net,
                        $this->post('remarks')
                    ]
                );
                $poId = $this->db->lastInsertId();

                foreach ($lineItems as $i => $ln) {
                    $qty   = (float)($ln['qty']         ?? 1);
                    $rate  = (float)($ln['rate']        ?? $ln['unit_price'] ?? 0);
                    $value = $qty * $rate;
                    $this->db->execute(
                        "INSERT INTO purchase_order_detail (header_id, srl_no, item_id, qty, rate, value)
                         VALUES (?, ?, ?, ?, ?, ?)",
                        [
                            $poId, $i + 1,
                            $ln['item_id'] ?? null,
                            $qty, $rate, $value
                        ]
                    );
                }

                $this->db->commit();
                $this->auditLog('CREATE', 'purchase_order', $poId, $docNo);
                $this->flash('success', "Purchase Order $docNo created successfully.");
                $this->redirect('purchaseorder');
            } catch (Exception $e) {
                $this->db->rollback();
                $this->flash('error', 'Error creating purchase order: ' . $e->getMessage());
            }
        }

        $suppliers = $this->db->fetchAll("SELECT id, code, name FROM suppliers WHERE company_id=? AND is_active=1 ORDER BY name", [$cid]);
        $items = [];

        $this->render('purchase/orders/form', [
            'pageTitle' => 'New Purchase Order',
            'suppliers' => $suppliers,
            'items' => $items,
            'breadcrumbs' => [
                ['label' => 'Dashboard', 'url' => APP_URL . '/dashboard'],
                ['label' => 'Purchase Orders', 'url' => APP_URL . '/purchaseorder'],
                ['label' => 'New Purchase Order', 'url' => '#'],
            ]
        ]);
    }
}
