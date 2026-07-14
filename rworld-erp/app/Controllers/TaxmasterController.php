<?php
/**
 * TaxmasterController — Tax master settings CRUD controller
 */
class TaxmasterController extends Controller {

    public function index() {
        $cid = $this->getCompanyId();
        $page = max(1, (int)$this->get('page', 1));

        $sql = "SELECT t.*
                FROM tax_types t
                WHERE t.company_id=?
                ORDER BY t.code ASC";

        $paged = $this->paginate($sql, [$cid], $page);

        $this->render('masters/tax/index', [
            'pageTitle' => 'Tax Master',
            'records' => $paged,
            'breadcrumbs' => [
                ['label' => 'Dashboard', 'url' => APP_URL . '/dashboard'],
                ['label' => 'Masters', 'url' => '#'],
                ['label' => 'Tax Master', 'url' => APP_URL . '/taxmaster'],
            ]
        ]);
    }

    public function create() {
        $cid = $this->getCompanyId();

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->validateCsrf();

            $this->db->execute(
                "INSERT INTO tax_types (company_id, code, name, tax_region, is_inclusive, components_count, is_active)
                 VALUES (?, ?, ?, ?, ?, 1, 1)",
                [
                    $cid,
                    $this->post('code'),
                    $this->post('name'),
                    $this->post('tax_region', 'US_SALES_TAX'),
                    (int)$this->post('is_inclusive', 0)
                ]
            );

            $id = $this->db->lastInsertId();
            $this->auditLog('CREATE', 'tax_type', $id, $this->post('code'));
            $this->flash('success', "Tax Type '{$this->post('name')}' created successfully.");
            $this->redirect('taxmaster');
        }

        $this->render('masters/tax/form', [
            'pageTitle' => 'New Tax Type',
            'breadcrumbs' => [
                ['label' => 'Dashboard', 'url' => APP_URL . '/dashboard'],
                ['label' => 'Tax Master', 'url' => APP_URL . '/taxmaster'],
                ['label' => 'New Tax Type', 'url' => '#'],
            ]
        ]);
    }
}
