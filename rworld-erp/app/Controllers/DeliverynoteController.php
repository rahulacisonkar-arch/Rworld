<?php
/**
 * DeliverynoteController — Production Delivery Notes controller
 * Table: delivery_notes — real columns: customer_id, sales_id, status (NO customer_name, NO remarks)
 */
class DeliverynoteController extends Controller {

    public function index() {
        $cid    = $this->getCompanyId();
        $bid    = $this->getBranchId();
        $search = $this->get('q', '');
        $page   = max(1, (int)$this->get('page', 1));

        $where  = "WHERE dn.company_id=? AND dn.branch_id=?";
        $params = [$cid, $bid];
        if ($search) {
            $where   .= " AND (dn.doc_no LIKE ? OR c.name LIKE ?)";
            $params[] = "%$search%";
            $params[] = "%$search%";
        }

        $sql = "SELECT dn.*, COALESCE(c.name, 'Walk-in Customer') AS customer_display_name
                FROM delivery_notes dn
                LEFT JOIN customers c ON c.id = dn.customer_id
                $where ORDER BY dn.id DESC";

        $paged = $this->paginate($sql, $params, $page);

        $this->render('billing/deliverynotes/index', [
            'pageTitle' => 'Delivery Notes',
            'records'   => $paged,
            'search'    => $search,
            'breadcrumbs' => [
                ['label' => 'Dashboard', 'url' => APP_URL . '/dashboard'],
                ['label' => 'Billing', 'url' => '#'],
                ['label' => 'Delivery Notes', 'url' => APP_URL . '/deliverynote'],
            ]
        ]);
    }

    public function create() {
        $cid = $this->getCompanyId();
        $bid = $this->getBranchId();

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->validateCsrf();

            $last  = $this->db->fetchColumn("SELECT doc_no FROM delivery_notes WHERE company_id=? ORDER BY id DESC LIMIT 1", [$cid]);
            $num   = $last ? ((int)preg_replace('/\D/', '', $last) + 1) : 1;
            $docNo = 'DN' . date('Y') . str_pad($num, 5, '0', STR_PAD_LEFT);
            $lineItems = json_decode($this->post('line_items', '[]'), true) ?: [];

            $this->db->beginTransaction();
            try {
                $this->db->execute(
                    "INSERT INTO delivery_notes (company_id, branch_id, doc_no, doc_date, customer_id, status, created_by)
                     VALUES (?, ?, ?, CURDATE(), ?, 'delivered', ?)",
                    [
                        $cid, $bid, $docNo,
                        $this->post('customer_id') ?: null,
                        $_SESSION['user_id'] ?? null
                    ]
                );
                $dnId = $this->db->lastInsertId();

                foreach ($lineItems as $ln) {
                    $itemId = (int)($ln['item_id'] ?? 0);
                    $qty = (float)($ln['qty'] ?? 0);
                    if (!$itemId || $qty <= 0) continue;

                    // Insert detail into delivery_note_detail (header_id, item_id, qty)
                    $this->db->execute(
                        "INSERT INTO delivery_note_detail (header_id, item_id, qty)
                         VALUES (?, ?, ?)",
                        [$dnId, $itemId, $qty]
                    );

                    // Fetch cost_price to adjust stock ledger
                    $costPrice = (float)$this->db->fetchColumn("SELECT cost_price FROM items WHERE id=?", [$itemId]);

                    // Add entry to stock ledger (reducing stock)
                    $this->db->execute(
                        "INSERT INTO stock_ledger (company_id, branch_id, item_id, txn_date, txn_type, doc_no, qty_in, qty_out, rate, value, balance_qty)
                         VALUES (?, ?, ?, CURDATE(), 'DELIVERY_NOTE', ?, 0.0000, ?, ?, ?, COALESCE((SELECT SUM(qty_in - qty_out) FROM stock_ledger sl WHERE sl.item_id = ? AND sl.branch_id = ?), 0) - ?)",
                        [
                            $cid, $bid, $itemId, $docNo,
                            $qty, $costPrice, ($qty * $costPrice),
                            $itemId, $bid, $qty
                        ]
                    );
                }

                $this->db->commit();
                $this->auditLog('CREATE', 'delivery_note', $dnId, $docNo);
                $this->flash('success', "Delivery Note {$docNo} created successfully.");
                $this->redirect('deliverynote');
            } catch (Exception $e) {
                $this->db->rollback();
                $this->flash('error', 'Error creating delivery note: ' . $e->getMessage());
            }
        }

        $customers = $this->db->fetchAll("SELECT id, code, name FROM customers WHERE company_id=? AND is_active=1 ORDER BY name", [$cid]) ?: [];
        $items     = [];

        $this->render('billing/deliverynotes/form', [
            'pageTitle' => 'New Delivery Note',
            'customers' => $customers,
            'items'     => $items,
            'breadcrumbs' => [
                ['label' => 'Dashboard', 'url' => APP_URL . '/dashboard'],
                ['label' => 'Delivery Notes', 'url' => APP_URL . '/deliverynote'],
                ['label' => 'New Delivery Note', 'url' => '#'],
            ]
        ]);
    }
}
