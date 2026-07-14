<?php
/**
 * StockledgerController — Production Stock Ledger management controller.
 */
class StockledgerController extends Controller {

    public function index() {
        $cid = $this->getCompanyId();
        $bid = $this->getBranchId();
        $page = max(1, (int)$this->get('page', 1));
        $itemId = $this->get('item_id', '');

        $where = "WHERE sl.company_id=? AND sl.branch_id=?";
        $params = [$cid, $bid];
        if ($itemId) {
            $where .= " AND sl.item_id=?";
            $params[] = $itemId;
        }

        $sql = "SELECT sl.*, i.description, i.stock_no
                FROM stock_ledger sl
                JOIN items i ON i.id = sl.item_id
                $where ORDER BY sl.id DESC";

        $paged = $this->paginate($sql, $params, $page);
        $selectedItemName = $itemId ? $this->db->fetchColumn("SELECT CONCAT(description, ' (', stock_no, ')') FROM items WHERE id=?", [$itemId]) : '';
        $items = [];

        $this->render('inventory/stockledger/index', [
            'pageTitle' => 'Stock Ledger',
            'records' => $paged,
            'items' => $items,
            'selectedItemId' => $itemId,
            'selectedItemName' => $selectedItemName,
            'breadcrumbs' => [
                ['label' => 'Dashboard', 'url' => APP_URL . '/dashboard'],
                ['label' => 'Inventory', 'url' => '#'],
                ['label' => 'Stock Ledger', 'url' => APP_URL . '/stockledger'],
            ]
        ]);
    }
}
