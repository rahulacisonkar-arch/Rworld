<?php
/**
 * CustomerController — Full CRUD for customer master records.
 */
class CustomerController extends Controller {

    public function index() {
        $db = $this->db;
        $cid = $this->getCompanyId();
        $search = $this->get('q', '');
        $page = max(1, (int)$this->get('page', 1));

        $sql = "SELECT id, code, name, phone1, email, city, credit_limit, is_active
                FROM customers
                WHERE company_id = ?
                " . ($search ? "AND (name LIKE ? OR code LIKE ? OR phone1 LIKE ?)" : "") . "
                ORDER BY name ASC";

        $params = [$cid];
        if ($search) { $params[] = "%$search%"; $params[] = "%$search%"; $params[] = "%$search%"; }

        $paged = $this->paginate($sql, $params, $page);

        $this->render('masters/customers/index', [
            'pageTitle' => 'Customers',
            'records' => $paged,
            'search' => $search,
            'breadcrumbs' => [
                ['label' => 'Dashboard', 'url' => APP_URL . '/dashboard'],
                ['label' => 'Masters', 'url' => '#'],
                ['label' => 'Customers', 'url' => APP_URL . '/customer'],
            ]
        ]);
    }

    public function create() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->validateCsrf();
            $cid = $this->getCompanyId();

            // Auto-generate code
            $lastCode = $this->db->fetchColumn(
                "SELECT code FROM customers WHERE company_id=? ORDER BY id DESC LIMIT 1", [$cid]
            );
            $num = $lastCode ? ((int)preg_replace('/\D/', '', $lastCode) + 1) : 1;
            $code = 'C' . str_pad($num, 4, '0', STR_PAD_LEFT);

            $this->db->execute(
                "INSERT INTO customers (company_id, code, name, alias, phone1, phone2, email,
                 address1, city, state, country, pin, credit_limit, credit_days,
                 is_tax_exempt, resale_certificate_no, tax_exempt_reason, resale_cert_expiry, is_active)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,1)",
                [
                    $cid, $code,
                    $this->post('name'), $this->post('alias'),
                    $this->post('phone1'), $this->post('phone2'), $this->post('email'),
                    $this->post('address1'), $this->post('city'), $this->post('state'),
                    $this->post('country', 'USA'), $this->post('pin'),
                    (float)$this->post('credit_limit', 0),
                    (int)$this->post('credit_days', 0),
                    (int)$this->post('is_tax_exempt', 0),
                    $this->post('resale_certificate_no') ?: null,
                    $this->post('tax_exempt_reason') ?: null,
                    $this->post('resale_cert_expiry') ?: null
                ]
            );

            $id = $this->db->lastInsertId();
            $this->auditLog('CREATE', 'customer', $id, $code);
            $this->flash('success', "Customer '{$this->post('name')}' created successfully.");
            $this->redirect('customer');
        }

        $this->render('masters/customers/form', [
            'pageTitle' => 'New Customer',
            'record' => null,
            'breadcrumbs' => [
                ['label' => 'Dashboard', 'url' => APP_URL . '/dashboard'],
                ['label' => 'Customers', 'url' => APP_URL . '/customer'],
                ['label' => 'New Customer', 'url' => '#'],
            ]
        ]);
    }

    public function edit($id = null) {
        $cid = $this->getCompanyId();
        $record = $this->db->fetchOne(
            "SELECT * FROM customers WHERE id=? AND company_id=?", [(int)$id, $cid]
        );
        if (!$record) { $this->flash('error', 'Customer not found.'); $this->redirect('customer'); }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->validateCsrf();
            $old = $record;
            $this->db->execute(
                "UPDATE customers SET name=?, alias=?, phone1=?, phone2=?, email=?,
                 address1=?, city=?, state=?, country=?, pin=?, credit_limit=?, credit_days=?,
                 is_tax_exempt=?, resale_certificate_no=?, tax_exempt_reason=?, resale_cert_expiry=?, is_active=?
                 WHERE id=? AND company_id=?",
                [
                    $this->post('name'), $this->post('alias'),
                    $this->post('phone1'), $this->post('phone2'), $this->post('email'),
                    $this->post('address1'), $this->post('city'), $this->post('state'),
                    $this->post('country', 'USA'), $this->post('pin'),
                    (float)$this->post('credit_limit', 0), (int)$this->post('credit_days', 0),
                    (int)$this->post('is_tax_exempt', 0),
                    $this->post('resale_certificate_no') ?: null,
                    $this->post('tax_exempt_reason') ?: null,
                    $this->post('resale_cert_expiry') ?: null,
                    $this->post('is_active', 1),
                    (int)$id, $cid
                ]
            );
            $this->auditLog('UPDATE', 'customer', $id, $record['code'], $old, $this->post('name'));
            $this->flash('success', 'Customer updated successfully.');
            $this->redirect('customer');
        }

        $this->render('masters/customers/form', [
            'pageTitle' => 'Edit Customer',
            'record' => $record,
            'breadcrumbs' => [
                ['label' => 'Dashboard', 'url' => APP_URL . '/dashboard'],
                ['label' => 'Customers', 'url' => APP_URL . '/customer'],
                ['label' => 'Edit: ' . $record['name'], 'url' => '#'],
            ]
        ]);
    }

    public function delete($id = null) {
        $this->validateCsrf();
        $cid = $this->getCompanyId();
        $this->db->execute("UPDATE customers SET is_active=0 WHERE id=? AND company_id=?", [(int)$id, $cid]);
        $this->auditLog('DELETE', 'customer', $id);
        $this->json(['success' => true, 'message' => 'Customer deactivated.']);
    }

    public function search() {
        $cid = $this->getCompanyId();
        $q = $this->get('q', '');
        $rows = $this->db->fetchAll(
            "SELECT id, code, name, phone1, credit_limit FROM customers
             WHERE company_id=? AND is_active=1 AND (name LIKE ? OR code LIKE ? OR phone1 LIKE ?)
             ORDER BY name LIMIT 20",
            [$cid, "%$q%", "%$q%", "%$q%"]
        );
        $this->json($rows);
    }
}
