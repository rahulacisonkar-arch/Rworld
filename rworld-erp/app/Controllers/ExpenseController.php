<?php
/**
 * ExpenseController — Production expense management
 * Table: expenses — real columns: category_id, amount, narration (no net_amount, no tax_amount, no expense_category_id)
 */
class ExpenseController extends Controller {

    public function index() {
        $cid = $this->getCompanyId();
        $bid = $this->getBranchId();
        $page     = max(1, (int)$this->get('page', 1));
        $fromDate = $this->get('from', date('Y-m-01'));
        $toDate   = $this->get('to', date('Y-m-d'));

        $sql = "SELECT e.id, e.doc_no, e.doc_date, e.amount, e.narration AS remarks, e.created_at,
                       ec.name AS category_name
                FROM expenses e
                LEFT JOIN expense_categories ec ON ec.id = e.category_id
                WHERE e.company_id=? AND e.branch_id=?
                AND e.doc_date BETWEEN ? AND ?
                ORDER BY e.id DESC";

        $paged      = $this->paginate($sql, [$cid, $bid, $fromDate, $toDate], $page);
        $categories = $this->db->fetchAll("SELECT id, name FROM expense_categories WHERE company_id=? ORDER BY name", [$cid]) ?: [];
        $total      = $this->db->fetchColumn("SELECT COALESCE(SUM(amount),0) FROM expenses WHERE company_id=? AND branch_id=? AND doc_date BETWEEN ? AND ?", [$cid, $bid, $fromDate, $toDate]);

        $this->render('finance/expenses/index', [
            'pageTitle'    => 'Expenses',
            'records'      => $paged,
            'categories'   => $categories,
            'fromDate'     => $fromDate,
            'toDate'       => $toDate,
            'totalExpense' => (float)$total,
            'breadcrumbs'  => [
                ['label' => 'Dashboard', 'url' => APP_URL . '/dashboard'],
                ['label' => 'Accounts', 'url' => '#'],
                ['label' => 'Expenses', 'url' => APP_URL . '/expense'],
            ]
        ]);
    }

    public function create() {
        $cid = $this->getCompanyId();
        $bid = $this->getBranchId();

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->validateCsrf();

            $amount = (float)$this->post('amount', 0);
            $last   = $this->db->fetchColumn("SELECT doc_no FROM expenses WHERE company_id=? ORDER BY id DESC LIMIT 1", [$cid]);
            $num    = $last ? ((int)preg_replace('/\D/', '', $last) + 1) : 1;
            $docNo  = 'EXP' . date('Y') . str_pad($num, 5, '0', STR_PAD_LEFT);

            $this->db->execute(
                "INSERT INTO expenses (company_id, branch_id, doc_no, doc_date, category_id, payment_mode_id, amount, narration, created_by)
                 VALUES (?,?,?,?,?,?,?,?,?)",
                [
                    $cid, $bid, $docNo,
                    $this->post('doc_date', date('Y-m-d')),
                    $this->post('expense_category_id') ?: null,
                    1, // default payment mode
                    $amount,
                    $this->post('remarks'),
                    $_SESSION['user_id'] ?? null
                ]
            );

            $id = $this->db->lastInsertId();
            $this->auditLog('CREATE', 'expense', $id, $docNo);
            $this->flash('success', "Expense {$docNo} recorded successfully.");
            $this->redirect('expense');
        }

        $categories = $this->db->fetchAll("SELECT id, name FROM expense_categories WHERE company_id=? ORDER BY name", [$cid]) ?: [];

        $this->render('finance/expenses/form', [
            'pageTitle'  => 'Record Expense',
            'record'     => null,
            'categories' => $categories,
            'breadcrumbs' => [
                ['label' => 'Dashboard', 'url' => APP_URL . '/dashboard'],
                ['label' => 'Expenses', 'url' => APP_URL . '/expense'],
                ['label' => 'New Expense', 'url' => '#'],
            ]
        ]);
    }
}
