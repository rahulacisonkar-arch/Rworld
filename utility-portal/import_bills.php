<?php
// PHP script to import utility bills from bills_data.json into DB
require_once __DIR__ . '/src/config.php';
require_once __DIR__ . '/src/db.php';

$isCli = (php_sapi_name() === 'cli');

if (!$isCli) {
    echo "<!DOCTYPE html><html><head><title>Import Bills</title><link rel='stylesheet' href='public/css/bootstrap.min.css'></head><body class='container mt-5'><h2>Importing Bills Data...</h2><pre>";
} else {
    echo "Importing Bills Data...\n";
}

$jsonPath = __DIR__ . '/bills_data.json';
if (!file_exists($jsonPath)) {
    die("Error: bills_data.json not found. Run extract_and_save.py first.\n");
}

$jsonData = file_get_contents($jsonPath);
$bills = json_decode($jsonData, true);

if ($bills === null) {
    die("Error parsing bills_data.json.\n");
}

echo "Loaded " . count($bills) . " bills from JSON.\n";

$importedStores = 0;
$importedConnections = 0;
$importedBills = 0;

try {
    $pdo->beginTransaction();

    // Prepare statements
    $stmtFindStore = $pdo->prepare("SELECT id FROM stores WHERE store_code = ?");
    $stmtInsertStore = $pdo->prepare("INSERT INTO stores (store_code, store_name, address, city, state, zip, phone, notification_emails, location_type) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    
    $stmtFindConn = $pdo->prepare("SELECT id FROM utility_connections WHERE store_id = ? AND utility_type = ?");
    $stmtInsertConn = $pdo->prepare("INSERT INTO utility_connections (store_id, utility_type, provider_name, account_number, notes, status) VALUES (?, ?, ?, ?, ?, 'Active')");
    
    $stmtFindBill = $pdo->prepare("SELECT id FROM bills WHERE connection_id = ? AND store_id = ? AND statement_date = ? AND due_date = ? AND amount = ?");
    $stmtInsertBill = $pdo->prepare("INSERT INTO bills (connection_id, store_id, statement_date, due_date, amount, bill_file_path, status, paid_at, transaction_ref, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

    foreach ($bills as $bill) {
        $storeCode = $bill['store_code'];
        
        // 1. Get or Create Store
        $stmtFindStore->execute([$storeCode]);
        $storeRow = $stmtFindStore->fetch(PDO::FETCH_ASSOC);
        
        if ($storeRow) {
            $storeId = $storeRow['id'];
        } else {
            // Store does not exist, insert it
            $storeInfo = $bill['store_info'];
            if (!$storeInfo) {
                $storeInfo = [
                    'code' => $storeCode,
                    'name' => $bill['store_name'],
                    'address' => 'UNKNOWN ADDRESS',
                    'city' => 'UNKNOWN',
                    'state' => 'US',
                    'zip' => '00000',
                    'phone' => 'N/A',
                    'notification_emails' => 'admin@arteefabrics.com',
                    'location_type' => 'Owned Building' // Default for dynamically imported buildings
                ];
            } else {
                // If it's in our new stores info, it's an owned building
                $storeInfo['phone'] = 'N/A';
                $storeInfo['notification_emails'] = 'admin@arteefabrics.com';
                $storeInfo['location_type'] = 'Owned Building';
            }
            
            $stmtInsertStore->execute([
                $storeInfo['code'],
                $storeInfo['name'],
                $storeInfo['address'],
                $storeInfo['city'],
                $storeInfo['state'],
                $storeInfo['zip'],
                $storeInfo['phone'],
                $storeInfo['notification_emails'],
                $storeInfo['location_type']
            ]);
            $storeId = $pdo->lastInsertId();
            $importedStores++;
            echo "Created Store: " . $storeInfo['name'] . " (" . $storeCode . ")\n";
        }
        
        // 2. Get or Create Connection
        $utilityType = $bill['utility_type'];
        $stmtFindConn->execute([$storeId, $utilityType]);
        $connRow = $stmtFindConn->fetch(PDO::FETCH_ASSOC);
        
        if ($connRow) {
            $connectionId = $connRow['id'];
        } else {
            // Connection does not exist, insert it
            $providerName = $bill['provider_name'];
            $accountNumber = $bill['account_number'];
            $notes = "Imported from Excel - STORE UTILITY FILE 2025";
            
            $stmtInsertConn->execute([
                $storeId,
                $utilityType,
                $providerName,
                $accountNumber,
                $notes
            ]);
            $connectionId = $pdo->lastInsertId();
            $importedConnections++;
            echo "  Created Connection: " . $utilityType . " for Store ID " . $storeId . "\n";
        }
        
        // 3. Get or Create Bill
        $statementDate = $bill['statement_date'];
        $dueDate = $bill['due_date'];
        $amount = $bill['amount'];
        
        $stmtFindBill->execute([$connectionId, $storeId, $statementDate, $dueDate, $amount]);
        $billRow = $stmtFindBill->fetch(PDO::FETCH_ASSOC);
        
        if (!$billRow) {
            $billFilePath = 'imported_from_excel.pdf';
            $status = $bill['status'];
            $payDate = $bill['pay_date']; // might be null
            $paidAt = $payDate ? ($payDate . " 12:00:00") : null;
            $transactionRef = $payDate ? 'EXCEL-IMPORT' : null;
            $notes = "Imported from Excel - " . ($payDate ? "Paid on " . $payDate : "Unpaid");
            
            $stmtInsertBill->execute([
                $connectionId,
                $storeId,
                $statementDate,
                $dueDate,
                $amount,
                $billFilePath,
                $status,
                $paidAt,
                $transactionRef,
                $notes
            ]);
            $importedBills++;
        }
    }
    
    $pdo->commit();
    echo "\nSuccess! Database transaction committed.\n";
    echo "Summary:\n";
    echo "- Stores Created: $importedStores\n";
    echo "- Connections Created: $importedConnections\n";
    echo "- Bills Imported: $importedBills\n";
    
} catch (Exception $e) {
    $pdo->rollBack();
    echo "\nError during import: " . $e->getMessage() . "\n";
}

if (!$isCli) {
    echo "</pre><a href='public/dashboard.php' class='btn btn-primary'>Go to Dashboard</a></body></html>";
}
