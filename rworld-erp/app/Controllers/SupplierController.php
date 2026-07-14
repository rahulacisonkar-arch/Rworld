<?php
/**
 * SupplierController — Full CRUD for supplier master records.
 */
class SupplierController extends Controller {

    public function index() {
        $cid = $this->getCompanyId();
        $search = $this->get('q', '');
        $page = max(1, (int)$this->get('page', 1));

        $sql = "SELECT id, code, name, phone1, email, city, credit_limit, is_active
                FROM suppliers WHERE company_id = ?
                " . ($search ? "AND (name LIKE ? OR code LIKE ? OR phone1 LIKE ?)" : "") . "
                ORDER BY name ASC";

        $params = [$cid];
        if ($search) { $params[] = "%$search%"; $params[] = "%$search%"; $params[] = "%$search%"; }

        $paged = $this->paginate($sql, $params, $page);

        $this->render('masters/suppliers/index', [
            'pageTitle' => 'Suppliers',
            'records' => $paged,
            'search' => $search,
            'breadcrumbs' => [
                ['label' => 'Dashboard', 'url' => APP_URL . '/dashboard'],
                ['label' => 'Masters', 'url' => '#'],
                ['label' => 'Suppliers', 'url' => APP_URL . '/supplier'],
            ]
        ]);
    }

    public function create() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->validateCsrf();
            $cid = $this->getCompanyId();

            $lastCode = $this->db->fetchColumn(
                "SELECT code FROM suppliers WHERE company_id=? ORDER BY id DESC LIMIT 1", [$cid]
            );
            $num = $lastCode ? ((int)preg_replace('/\D/', '', $lastCode) + 1) : 1;
            $code = 'S' . str_pad($num, 4, '0', STR_PAD_LEFT);

            $this->db->execute(
                "INSERT INTO suppliers (company_id, code, name, alias, phone1, phone2, email,
                 address1, city, state, country, credit_limit, credit_days, is_active)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,1)",
                [
                    $cid, $code,
                    $this->post('name'), $this->post('alias'),
                    $this->post('phone1'), $this->post('phone2'), $this->post('email'),
                    $this->post('address1'), $this->post('city'), $this->post('state'),
                    $this->post('country', 'USA'),
                    (float)$this->post('credit_limit', 0), (int)$this->post('credit_days', 30)
                ]
            );

            $id = $this->db->lastInsertId();
            $this->auditLog('CREATE', 'supplier', $id, $code);
            $this->flash('success', "Supplier '{$this->post('name')}' created successfully.");
            $this->redirect('supplier');
        }

        $this->render('masters/suppliers/form', [
            'pageTitle' => 'New Supplier',
            'record' => null,
            'breadcrumbs' => [
                ['label' => 'Dashboard', 'url' => APP_URL . '/dashboard'],
                ['label' => 'Suppliers', 'url' => APP_URL . '/supplier'],
                ['label' => 'New Supplier', 'url' => '#'],
            ]
        ]);
    }

    public function edit($id = null) {
        $cid = $this->getCompanyId();
        $record = $this->db->fetchOne(
            "SELECT * FROM suppliers WHERE id=? AND company_id=?", [(int)$id, $cid]
        );
        if (!$record) { $this->flash('error', 'Supplier not found.'); $this->redirect('supplier'); }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->validateCsrf();
            $old = $record;
            $this->db->execute(
                "UPDATE suppliers SET name=?, alias=?, phone1=?, phone2=?, email=?,
                 address1=?, city=?, state=?, country=?, credit_limit=?, credit_days=?, is_active=?
                 WHERE id=? AND company_id=?",
                [
                    $this->post('name'), $this->post('alias'),
                    $this->post('phone1'), $this->post('phone2'), $this->post('email'),
                    $this->post('address1'), $this->post('city'), $this->post('state'),
                    $this->post('country', 'USA'),
                    (float)$this->post('credit_limit', 0), (int)$this->post('credit_days', 30),
                    $this->post('is_active', 1),
                    (int)$id, $cid
                ]
            );
            $this->auditLog('UPDATE', 'supplier', $id, $record['code'], $old, $this->post('name'));
            $this->flash('success', 'Supplier updated successfully.');
            $this->redirect('supplier');
        }

        $this->render('masters/suppliers/form', [
            'pageTitle' => 'Edit Supplier',
            'record' => $record,
            'breadcrumbs' => [
                ['label' => 'Dashboard', 'url' => APP_URL . '/dashboard'],
                ['label' => 'Suppliers', 'url' => APP_URL . '/supplier'],
                ['label' => 'Edit: ' . $record['name'], 'url' => '#'],
            ]
        ]);
    }

    public function delete($id = null) {
        $this->validateCsrf();
        $cid = $this->getCompanyId();
        $this->db->execute("UPDATE suppliers SET is_active=0 WHERE id=? AND company_id=?", [(int)$id, $cid]);
        $this->auditLog('DELETE', 'supplier', $id);
        $this->json(['success' => true, 'message' => 'Supplier deactivated.']);
    }

    public function search() {
        $cid = $this->getCompanyId();
        $q = $this->get('q', '');
        $rows = $this->db->fetchAll(
            "SELECT id, code, name, phone1 FROM suppliers
             WHERE company_id=? AND is_active=1 AND (name LIKE ? OR code LIKE ?)
             ORDER BY name LIMIT 20",
            [$cid, "%$q%", "%$q%"]
        );
        $this->json($rows);
    }
}
