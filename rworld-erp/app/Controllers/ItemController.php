<?php
/**
 * ItemController — Full CRUD for product/item master.
 */
class ItemController extends Controller {

    public function index() {
        $cid = $this->getCompanyId();
        $search = $this->get('q', '');
        $page = max(1, (int)$this->get('page', 1));

        $sql = "SELECT i.id, i.stock_no, i.description, i.barcode, i.price1, i.mrp,
                       i.cost_price, i.is_active, i.maintain_inventory,
                       c.name AS category_name, u.name AS unit_name
                FROM items i
                LEFT JOIN categories c ON c.id = i.cat1_id
                LEFT JOIN units u ON u.id = i.unit_id
                WHERE i.company_id = ?
                " . ($search ? "AND (i.description LIKE ? OR i.stock_no LIKE ? OR i.barcode LIKE ?)" : "") . "
                ORDER BY i.description ASC";

        $params = [$cid];
        if ($search) { $params[] = "%$search%"; $params[] = "%$search%"; $params[] = "%$search%"; }

        $paged = $this->paginate($sql, $params, $page);
        $categories = $this->db->fetchAll("SELECT id, name FROM categories WHERE company_id=? AND is_active=1 ORDER BY name", [$cid]);
        $units = $this->db->fetchAll("SELECT id, name FROM units WHERE company_id=? AND is_active=1 ORDER BY name", [$cid]);

        $this->render('masters/items/index', [
            'pageTitle' => 'Item Master',
            'records' => $paged,
            'search' => $search,
            'categories' => $categories,
            'units' => $units,
            'breadcrumbs' => [
                ['label' => 'Dashboard', 'url' => APP_URL . '/dashboard'],
                ['label' => 'Inventory', 'url' => '#'],
                ['label' => 'Item Master', 'url' => APP_URL . '/item'],
            ]
        ]);
    }

    public function create() {
        $cid = $this->getCompanyId();

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->validateCsrf();

            $lastCode = $this->db->fetchColumn(
                "SELECT stock_no FROM items WHERE company_id=? ORDER BY id DESC LIMIT 1", [$cid]
            );
            $num = $lastCode ? ((int)preg_replace('/\D/', '', $lastCode) + 1) : 1;
            $stockNo = 'ITM' . str_pad($num, 4, '0', STR_PAD_LEFT);

            $this->db->execute(
                "INSERT INTO items (company_id, stock_no, description, barcode, cat1_id, unit_id,
                 price1, price2, mrp, cost_price, reorder_level, maintain_inventory, is_active)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?,?,1)",
                [
                    $cid, $stockNo,
                    $this->post('description'),
                    $this->post('barcode'),
                    $this->post('cat1_id') ?: null,
                    $this->post('unit_id') ?: null,
                    (float)$this->post('price1', 0),
                    (float)$this->post('price2', 0),
                    (float)$this->post('mrp', 0),
                    (float)$this->post('cost_price', 0),
                    (float)$this->post('reorder_level', 0),
                    (int)$this->post('maintain_inventory', 1),
                ]
            );

            $id = $this->db->lastInsertId();
            $this->auditLog('CREATE', 'item', $id, $stockNo);
            $this->flash('success', "Item '{$this->post('description')}' created successfully.");
            $this->redirect('item');
        }

        $categories = $this->db->fetchAll("SELECT id, name FROM categories WHERE company_id=? AND is_active=1 ORDER BY name", [$cid]);
        $units = $this->db->fetchAll("SELECT id, name FROM units WHERE company_id=? AND is_active=1 ORDER BY name", [$cid]);

        $this->render('masters/items/form', [
            'pageTitle' => 'New Item',
            'record' => null,
            'categories' => $categories,
            'units' => $units,
            'breadcrumbs' => [
                ['label' => 'Dashboard', 'url' => APP_URL . '/dashboard'],
                ['label' => 'Item Master', 'url' => APP_URL . '/item'],
                ['label' => 'New Item', 'url' => '#'],
            ]
        ]);
    }

    public function edit($id = null) {
        $cid = $this->getCompanyId();
        $record = $this->db->fetchOne(
            "SELECT * FROM items WHERE id=? AND company_id=?", [(int)$id, $cid]
        );
        if (!$record) { $this->flash('error', 'Item not found.'); $this->redirect('item'); }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->validateCsrf();
            $old = $record;
            $this->db->execute(
                "UPDATE items SET description=?, barcode=?, cat1_id=?, unit_id=?,
                 price1=?, price2=?, mrp=?, cost_price=?, reorder_level=?, maintain_inventory=?, is_active=?
                 WHERE id=? AND company_id=?",
                [
                    $this->post('description'), $this->post('barcode'),
                    $this->post('cat1_id') ?: null, $this->post('unit_id') ?: null,
                    (float)$this->post('price1', 0), (float)$this->post('price2', 0),
                    (float)$this->post('mrp', 0), (float)$this->post('cost_price', 0),
                    (float)$this->post('reorder_level', 0),
                    (int)$this->post('maintain_inventory', 1),
                    (int)$this->post('is_active', 1),
                    (int)$id, $cid
                ]
            );
            $this->auditLog('UPDATE', 'item', $id, $record['stock_no'], $old);
            $this->flash('success', 'Item updated successfully.');
            $this->redirect('item');
        }

        $categories = $this->db->fetchAll("SELECT id, name FROM categories WHERE company_id=? AND is_active=1 ORDER BY name", [$cid]);
        $units = $this->db->fetchAll("SELECT id, name FROM units WHERE company_id=? AND is_active=1 ORDER BY name", [$cid]);

        $this->render('masters/items/form', [
            'pageTitle' => 'Edit Item',
            'record' => $record,
            'categories' => $categories,
            'units' => $units,
            'breadcrumbs' => [
                ['label' => 'Dashboard', 'url' => APP_URL . '/dashboard'],
                ['label' => 'Item Master', 'url' => APP_URL . '/item'],
                ['label' => 'Edit: ' . $record['description'], 'url' => '#'],
            ]
        ]);
    }

    public function delete($id = null) {
        $this->validateCsrf();
        $cid = $this->getCompanyId();
        $this->db->execute("UPDATE items SET is_active=0 WHERE id=? AND company_id=?", [(int)$id, $cid]);
        $this->auditLog('DELETE', 'item', $id);
        $this->json(['success' => true, 'message' => 'Item deactivated.']);
    }

    public function search() {
        $cid = $this->getCompanyId();
        $q = $this->get('q', '');
        $rows = $this->db->fetchAll(
            "SELECT i.id, i.stock_no, i.description, i.barcode, i.price1, i.cost_price, i.mrp,
                    u.name AS unit_name
             FROM items i
             LEFT JOIN units u ON u.id = i.unit_id
             WHERE i.company_id=? AND i.is_active=1
             AND (i.description LIKE ? OR i.stock_no LIKE ? OR i.barcode LIKE ?)
             ORDER BY i.description LIMIT 20",
            [$cid, "%$q%", "%$q%", "%$q%"]
        );
        $this->json($rows);
    }
}
