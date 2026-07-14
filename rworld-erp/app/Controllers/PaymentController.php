<?php
/**
 * PaymentController — Customer Payments controller
 * Table: payments_received — real columns: customer_id, amount, payment_mode_id, narration, created_at (NO status column)
 */
class PaymentController extends Controller {

    public function index() {
        $cid  = $this->getCompanyId();
        $bid  = $this->getBranchId();
        $page = max(1, (int)$this->get('page', 1));

        $sql = "SELECT p.*, c.name AS customer_name
                FROM payments_received p
                LEFT JOIN customers c ON c.id = p.customer_id
                WHERE p.company_id=? AND p.branch_id=?
                ORDER BY p.id DESC";

        $paged = $this->paginate($sql, [$cid, $bid], $page);

        $this->render('finance/payments/index', [
            'pageTitle' => 'Payments Received',
            'records'   => $paged,
            'breadcrumbs' => [
                ['label' => 'Dashboard', 'url' => APP_URL . '/dashboard'],
                ['label' => 'Accounts', 'url' => '#'],
                ['label' => 'Payments Received', 'url' => APP_URL . '/payment'],
            ]
        ]);
    }

    public function create() {
        $cid = $this->getCompanyId();
        $bid = $this->getBranchId();

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->validateCsrf();

            $amount = (float)$this->post('amount', 0);
            $last   = $this->db->fetchColumn("SELECT doc_no FROM payments_received WHERE company_id=? ORDER BY id DESC LIMIT 1", [$cid]);
            $num    = $last ? ((int)preg_replace('/\D/', '', $last) + 1) : 1;
            $docNo  = 'REC' . date('Y') . str_pad($num, 5, '0', STR_PAD_LEFT);

            $this->db->execute(
                "INSERT INTO payments_received (company_id, branch_id, doc_no, doc_date, customer_id, payment_mode_id, amount, narration, created_by)
                 VALUES (?, ?, ?, ?, ?, 1, ?, ?, ?)",
                [
                    $cid, $bid, $docNo,
                    $this->post('doc_date', date('Y-m-d')),
                    $this->post('customer_id') ?: null,
                    $amount,
                    $this->post('remarks'),
                    $_SESSION['user_id'] ?? null
                ]
            );

            $pid = $this->db->lastInsertId();
            $this->auditLog('CREATE', 'payment_received', $pid, $docNo);
            $this->flash('success', "Payment receipt {$docNo} recorded successfully.");
            $this->redirect('payment');
        }

        $customers = $this->db->fetchAll("SELECT id, code, name FROM customers WHERE company_id=? AND is_active=1 ORDER BY name", [$cid]) ?: [];

        $this->render('finance/payments/form', [
            'pageTitle'  => 'New Payment Receipt',
            'customers'  => $customers,
            'breadcrumbs' => [
                ['label' => 'Dashboard', 'url' => APP_URL . '/dashboard'],
                ['label' => 'Payments Received', 'url' => APP_URL . '/payment'],
                ['label' => 'New Receipt', 'url' => '#'],
            ]
        ]);
    }
}
