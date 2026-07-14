<?php
/**
 * ReceiptController — Supplier Payment Receipts controller (payments_made)
 * Table: payments_made — real columns: supplier_id, amount, payment_mode_id, narration (NO status)
 */
class ReceiptController extends Controller {

    public function index() {
        $cid  = $this->getCompanyId();
        $bid  = $this->getBranchId();
        $page = max(1, (int)$this->get('page', 1));

        $sql = "SELECT p.*, s.name AS supplier_name
                FROM payments_made p
                LEFT JOIN suppliers s ON s.id = p.supplier_id
                WHERE p.company_id=? AND p.branch_id=?
                ORDER BY p.id DESC";

        $paged = $this->paginate($sql, [$cid, $bid], $page);

        $this->render('finance/payments/made_index', [
            'pageTitle' => 'Supplier Payments',
            'records'   => $paged,
            'breadcrumbs' => [
                ['label' => 'Dashboard', 'url' => APP_URL . '/dashboard'],
                ['label' => 'Accounts', 'url' => '#'],
                ['label' => 'Supplier Payments', 'url' => APP_URL . '/receipt'],
            ]
        ]);
    }

    public function create() {
        $cid = $this->getCompanyId();
        $bid = $this->getBranchId();

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->validateCsrf();

            $amount = (float)$this->post('amount', 0);
            $last   = $this->db->fetchColumn("SELECT doc_no FROM payments_made WHERE company_id=? ORDER BY id DESC LIMIT 1", [$cid]);
            $num    = $last ? ((int)preg_replace('/\D/', '', $last) + 1) : 1;
            $docNo  = 'PAY' . date('Y') . str_pad($num, 5, '0', STR_PAD_LEFT);

            $this->db->execute(
                "INSERT INTO payments_made (company_id, branch_id, doc_no, doc_date, supplier_id, payment_mode_id, amount, narration, created_by)
                 VALUES (?, ?, ?, ?, ?, 1, ?, ?, ?)",
                [
                    $cid, $bid, $docNo,
                    $this->post('doc_date', date('Y-m-d')),
                    $this->post('supplier_id') ?: null,
                    $amount,
                    $this->post('remarks'),
                    $_SESSION['user_id'] ?? null
                ]
            );

            $pid = $this->db->lastInsertId();
            $this->auditLog('CREATE', 'payment_made', $pid, $docNo);
            $this->flash('success', "Supplier payment {$docNo} recorded successfully.");
            $this->redirect('receipt');
        }

        $suppliers = $this->db->fetchAll("SELECT id, code, name FROM suppliers WHERE company_id=? AND is_active=1 ORDER BY name", [$cid]) ?: [];

        $this->render('finance/payments/made_form', [
            'pageTitle' => 'New Supplier Payment',
            'suppliers' => $suppliers,
            'breadcrumbs' => [
                ['label' => 'Dashboard', 'url' => APP_URL . '/dashboard'],
                ['label' => 'Supplier Payments', 'url' => APP_URL . '/receipt'],
                ['label' => 'New Payment', 'url' => '#'],
            ]
        ]);
    }
}
