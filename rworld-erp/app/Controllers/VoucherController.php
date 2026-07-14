<?php
/**
 * VoucherController — Journal Vouchers, Receipts, and Payments management.
 */
class VoucherController extends Controller {

    public function index() {
        $cid = $this->getCompanyId();
        $bid = $this->getBranchId();
        $type = $this->get('type', '');
        $page = max(1, (int)$this->get('page', 1));

        $where = "WHERE company_id=? AND branch_id=?";
        $params = [$cid, $bid];
        if ($type) { $where .= " AND voucher_type=?"; $params[] = $type; }

        $sql = "SELECT id, doc_no, doc_date, voucher_type, narration, total_amount, status
                FROM vouchers $where ORDER BY id DESC";

        $paged = $this->paginate($sql, $params, $page);

        $this->render('finance/vouchers/index', [
            'pageTitle' => 'Journal Vouchers',
            'records' => $paged,
            'filterType' => $type,
            'breadcrumbs' => [
                ['label' => 'Dashboard', 'url' => APP_URL . '/dashboard'],
                ['label' => 'Accounts', 'url' => '#'],
                ['label' => 'Journal Vouchers', 'url' => APP_URL . '/voucher'],
            ]
        ]);
    }

    public function create() {
        $cid = $this->getCompanyId();
        $bid = $this->getBranchId();

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->validateCsrf();

            $last = $this->db->fetchColumn("SELECT doc_no FROM vouchers WHERE company_id=? ORDER BY id DESC LIMIT 1", [$cid]);
            $num = $last ? ((int)preg_replace('/\D/', '', $last) + 1) : 1;
            $docNo = 'JV' . date('Y') . str_pad($num, 5, '0', STR_PAD_LEFT);

            $this->db->execute(
                "INSERT INTO vouchers (company_id, branch_id, doc_no, doc_date, voucher_type, narration, total_amount, status)
                 VALUES (?,?,?,?,?,?,?,'confirmed')",
                [
                    $cid, $bid, $docNo,
                    $this->post('doc_date', date('Y-m-d')),
                    $this->post('voucher_type', 'journal'),
                    $this->post('narration'),
                    (float)$this->post('total_amount', 0),
                ]
            );

            $id = $this->db->lastInsertId();
            $this->auditLog('CREATE', 'voucher', $id, $docNo);
            $this->flash('success', "Voucher $docNo created successfully.");
            $this->redirect('voucher');
        }

        $this->render('finance/vouchers/form', [
            'pageTitle' => 'New Journal Voucher',
            'record' => null,
            'breadcrumbs' => [
                ['label' => 'Dashboard', 'url' => APP_URL . '/dashboard'],
                ['label' => 'Journal Vouchers', 'url' => APP_URL . '/voucher'],
                ['label' => 'New Voucher', 'url' => '#'],
            ]
        ]);
    }
}
