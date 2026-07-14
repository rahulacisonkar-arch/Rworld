<?php
/**
 * TransferController — Production Branch Transfer controller
 * Table: transfer_out — real columns: from_branch_id, to_branch_id (not src_branch_id, dest_branch_id)
 */
class TransferController extends Controller {

    public function index() {
        $cid  = $this->getCompanyId();
        $bid  = $this->getBranchId();
        $page = max(1, (int)$this->get('page', 1));

        $sql = "SELECT t.*, b.name AS dest_branch_name
                FROM transfer_out t
                LEFT JOIN branches b ON b.id = t.to_branch_id
                WHERE t.company_id=? AND t.from_branch_id=?
                ORDER BY t.id DESC";

        $paged = $this->paginate($sql, [$cid, $bid], $page);

        $this->render('inventory/transfers/index', [
            'pageTitle' => 'Branch Transfers',
            'records'   => $paged,
            'breadcrumbs' => [
                ['label' => 'Dashboard', 'url' => APP_URL . '/dashboard'],
                ['label' => 'Inventory', 'url' => '#'],
                ['label' => 'Branch Transfers', 'url' => APP_URL . '/transfer'],
            ]
        ]);
    }

    public function create() {
        $cid = $this->getCompanyId();
        $bid = $this->getBranchId();

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->validateCsrf();

            $last      = $this->db->fetchColumn("SELECT doc_no FROM transfer_out WHERE company_id=? ORDER BY id DESC LIMIT 1", [$cid]);
            $num       = $last ? ((int)preg_replace('/\D/', '', $last) + 1) : 1;
            $docNo     = 'TRF' . date('Y') . str_pad($num, 5, '0', STR_PAD_LEFT);
            $lineItems = json_decode($this->post('line_items', '[]'), true) ?: [];

            $this->db->beginTransaction();
            try {
                $netAmt = 0;
                $details = [];

                foreach ($lineItems as $idx => $ln) {
                    $itemId = (int)($ln['item_id'] ?? 0);
                    $qty = (float)($ln['qty'] ?? 0);
                    if (!$itemId || $qty <= 0) continue;

                    // Fetch item details (cost_price and unit_id)
                    $item = $this->db->fetchOne("SELECT cost_price, unit_id FROM items WHERE id=? AND company_id=?", [$itemId, $cid]);
                    if (!$item) continue;

                    $rate = (float)$item['cost_price'];
                    $value = $qty * $rate;
                    $netAmt += $value;

                    $details[] = [
                        'item_id' => $itemId,
                        'qty' => $qty,
                        'unit_id' => $item['unit_id'] ? (int)$item['unit_id'] : null,
                        'rate' => $rate,
                        'value' => $value,
                        'srl_no' => $idx + 1
                    ];
                }

                if (empty($details)) {
                    throw new Exception("Add at least one valid item with quantity greater than 0.");
                }

                $this->db->execute(
                    "INSERT INTO transfer_out (company_id, from_branch_id, to_branch_id, doc_no, doc_date, net_amount, remarks, status, created_by)
                     VALUES (?, ?, ?, ?, CURDATE(), ?, ?, 'open', ?)",
                    [
                        $cid, $bid,
                        (int)$this->post('dest_branch_id'),
                        $docNo, $netAmt,
                        $this->post('remarks'),
                        $_SESSION['user_id'] ?? null
                    ]
                );
                $trfId = $this->db->lastInsertId();

                foreach ($details as $det) {
                    // Insert detail
                    $this->db->execute(
                        "INSERT INTO transfer_out_detail (header_id, srl_no, item_id, qty, received_qty, unit_id, rate, value)
                         VALUES (?, ?, ?, ?, 0.0000, ?, ?, ?)",
                        [
                            $trfId, $det['srl_no'], $det['item_id'], $det['qty'],
                            $det['unit_id'], $det['rate'], $det['value']
                        ]
                    );

                    // Update stock ledger (reducing stock in from_branch_id)
                    $this->db->execute(
                        "INSERT INTO stock_ledger (company_id, branch_id, item_id, txn_date, txn_type, doc_no, qty_in, qty_out, rate, value, balance_qty)
                         VALUES (?, ?, ?, CURDATE(), 'BRANCH_TRANSFER_OUT', ?, 0.0000, ?, ?, ?, COALESCE((SELECT SUM(qty_in - qty_out) FROM stock_ledger sl WHERE sl.item_id = ? AND sl.branch_id = ?), 0) - ?)",
                        [
                            $cid, $bid, $det['item_id'], $docNo,
                            $det['qty'], $det['rate'], $det['value'],
                            $det['item_id'], $bid, $det['qty']
                        ]
                    );
                }

                $this->db->commit();
                $this->auditLog('CREATE', 'transfer_out', $trfId, $docNo);
                $this->flash('success', "Branch Transfer {$docNo} shipped successfully.");
                $this->redirect('transfer');
            } catch (Exception $e) {
                $this->db->rollback();
                $this->flash('error', 'Error creating branch transfer: ' . $e->getMessage());
            }
        }

        $branches = $this->db->fetchAll("SELECT id, name FROM branches WHERE company_id=? AND id!=? ORDER BY name", [$cid, $bid]) ?: [];
        $items    = [];

        $this->render('inventory/transfers/form', [
            'pageTitle' => 'New Branch Transfer',
            'branches'  => $branches,
            'items'     => $items,
            'breadcrumbs' => [
                ['label' => 'Dashboard', 'url' => APP_URL . '/dashboard'],
                ['label' => 'Branch Transfers', 'url' => APP_URL . '/transfer'],
                ['label' => 'New Transfer', 'url' => '#'],
            ]
        ]);
    }
}
