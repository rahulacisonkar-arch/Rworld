<?php
/**
 * LedgerController — General Ledger Accounts controller
 * Table: ledger_accounts — real columns: group_id, opening_type
 */
class LedgerController extends Controller {

    public function index() {
        $cid = $this->getCompanyId();
        $page = max(1, (int)$this->get('page', 1));

        $sql = "SELECT la.id, la.code, la.name, la.opening_balance, la.opening_type AS opening_bal_type, la.is_active,
                       lg.name AS group_name
                FROM ledger_accounts la
                LEFT JOIN ledger_groups lg ON lg.id = la.group_id
                WHERE la.company_id=?
                ORDER BY la.code ASC";

        $paged = $this->paginate($sql, [$cid], $page);

        $this->render('masters/ledgers/index', [
            'pageTitle' => 'Ledger Accounts',
            'records'   => $paged,
            'breadcrumbs' => [
                ['label' => 'Dashboard', 'url' => APP_URL . '/dashboard'],
                ['label' => 'Masters', 'url' => '#'],
                ['label' => 'Ledger Accounts', 'url' => APP_URL . '/ledger'],
            ]
        ]);
    }

    public function create() {
        $cid = $this->getCompanyId();

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->validateCsrf();

            $this->db->execute(
                "INSERT INTO ledger_accounts (company_id, group_id, code, name, opening_balance, opening_type, is_active)
                 VALUES (?, ?, ?, ?, ?, ?, 1)",
                [
                    $cid,
                    $this->post('ledger_group_id') ?: null,
                    $this->post('code'),
                    $this->post('name'),
                    (float)$this->post('opening_balance', 0),
                    $this->post('opening_bal_type', 'Dr')
                ]
            );

            $id = $this->db->lastInsertId();
            $this->auditLog('CREATE', 'ledger_account', $id, $this->post('code'));
            $this->flash('success', "Ledger account '{$this->post('name')}' created successfully.");
            $this->redirect('ledger');
        }

        $groups = $this->db->fetchAll("SELECT id, name FROM ledger_groups ORDER BY name") ?: [];

        $this->render('masters/ledgers/form', [
            'pageTitle' => 'New Ledger Account',
            'groups'    => $groups,
            'breadcrumbs' => [
                ['label' => 'Dashboard', 'url' => APP_URL . '/dashboard'],
                ['label' => 'Ledger Accounts', 'url' => APP_URL . '/ledger'],
                ['label' => 'New Account', 'url' => '#'],
            ]
        ]);
    }
}
