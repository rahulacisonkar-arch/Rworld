<?php
/**
 * StockjournalController — Production Stock Journal for adjustments
 * Table: stock_journal — real columns: doc_no, doc_date, reason_id, narration, total_value, status
 */
class StockjournalController extends Controller {

    public function index() {
        $cid  = $this->getCompanyId();
        $bid  = $this->getBranchId();
        $page = max(1, (int)$this->get('page', 1));

        $sql = "SELECT sj.*
                FROM stock_journal sj
                WHERE sj.company_id=? AND sj.branch_id=?
                ORDER BY sj.id DESC";

        $paged = $this->paginate($sql, [$cid, $bid], $page);

        $this->render('inventory/stockjournal/index', [
            'pageTitle' => 'Stock Journal',
            'records'   => $paged,
            'breadcrumbs' => [
                ['label' => 'Dashboard', 'url' => APP_URL . '/dashboard'],
                ['label' => 'Inventory', 'url' => '#'],
                ['label' => 'Stock Journal', 'url' => APP_URL . '/stockjournal'],
            ]
        ]);
    }

    public function create() {
        $cid = $this->getCompanyId();
        $bid = $this->getBranchId();

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->validateCsrf();

            $last      = $this->db->fetchColumn("SELECT doc_no FROM stock_journal WHERE company_id=? ORDER BY id DESC LIMIT 1", [$cid]);
            $num       = $last ? ((int)preg_replace('/\D/', '', $last) + 1) : 1;
            $docNo     = 'SJ' . date('Y') . str_pad($num, 5, '0', STR_PAD_LEFT);
            $lineItems = json_decode($this->post('line_items', '[]'), true) ?: [];
            $direction = $this->post('direction', 'in');

            $this->db->beginTransaction();
            try {
                $totalValue = 0;
                $details = [];

                foreach ($lineItems as $idx => $ln) {
                    $itemId = (int)($ln['item_id'] ?? 0);
                    $qty = (float)($ln['qty'] ?? 0);
                    if (!$itemId || $qty <= 0) continue;

                    // Fetch cost price and unit
                    $item = $this->db->fetchOne("SELECT cost_price, unit_id FROM items WHERE id=? AND company_id=?", [$itemId, $cid]);
                    if (!$item) continue;

                    $rate = (float)$item['cost_price'];
                    $val = $qty * $rate;
                    $totalValue += $val;

                    $details[] = [
                        'item_id' => $itemId,
                        'qty' => $qty,
                        'rate' => $rate,
                        'value' => $val,
                        'unit_id' => $item['unit_id'] ? (int)$item['unit_id'] : null,
                        'srl_no' => $idx + 1
                    ];
                }

                if (empty($details)) {
                    throw new Exception("Add at least one valid item with quantity greater than 0.");
                }

                $this->db->execute(
                    "INSERT INTO stock_journal (company_id, branch_id, doc_no, doc_date, narration, total_value, status, created_by)
                     VALUES (?, ?, ?, CURDATE(), ?, ?, 'confirmed', ?)",
                    [
                        $cid, $bid, $docNo,
                        $this->post('remarks'),
                        $totalValue,
                        $_SESSION['user_id'] ?? null
                    ]
                );
                $sjId = $this->db->lastInsertId();

                foreach ($details as $det) {
                    $qtyIn = ($direction === 'in') ? $det['qty'] : 0.0000;
                    $qtyOut = ($direction === 'out') ? $det['qty'] : 0.0000;

                    // Insert detail using correct schema (header_id, srl_no, item_id, qty_in, qty_out, unit_id, rate, value)
                    $this->db->execute(
                        "INSERT INTO stock_journal_detail (header_id, srl_no, item_id, qty_in, qty_out, unit_id, rate, value)
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
                        [
                            $sjId, $det['srl_no'], $det['item_id'], $qtyIn, $qtyOut,
                            $det['unit_id'], $det['rate'], $det['value']
                        ]
                    );

                    // Insert to stock ledger
                    $this->db->execute(
                        "INSERT INTO stock_ledger (company_id, branch_id, item_id, txn_date, txn_type, doc_no, qty_in, qty_out, rate, value, balance_qty)
                         VALUES (?, ?, ?, CURDATE(), 'STOCK_JOURNAL', ?, ?, ?, ?, ?, COALESCE((SELECT SUM(qty_in - qty_out) FROM stock_ledger sl WHERE sl.item_id = ? AND sl.branch_id = ?), 0) + (? - ?))",
                        [
                            $cid, $bid, $det['item_id'], $docNo,
                            $qtyIn, $qtyOut, $det['rate'], $det['value'],
                            $det['item_id'], $bid, $qtyIn, $qtyOut
                        ]
                    );
                }

                $this->db->commit();
                $this->auditLog('CREATE', 'stock_journal', $sjId, $docNo);
                $this->flash('success', "Stock Journal {$docNo} posted successfully.");
                $this->redirect('stockjournal');
            } catch (Exception $e) {
                $this->db->rollback();
                $this->flash('error', 'Error posting stock journal: ' . $e->getMessage());
            }
        }

        $items = [];

        $this->render('inventory/stockjournal/form', [
            'pageTitle' => 'New Stock Adjustment',
            'items'     => $items,
            'breadcrumbs' => [
                ['label' => 'Dashboard', 'url' => APP_URL . '/dashboard'],
                ['label' => 'Stock Journal', 'url' => APP_URL . '/stockjournal'],
                ['label' => 'New Adjustment', 'url' => '#'],
            ]
        ]);
    }
}
