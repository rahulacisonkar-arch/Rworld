<?php
// Database seeder script for Artee Fabrics & Home Shipping Portal
require_once __DIR__ . '/src/config.php';

echo "<h2>Artee Shipping Portal Database Seeder</h2>";

try {
    // Connect to MySQL without dbname first to create it if it doesn't exist
    $dsnNoDb = "mysql:host=" . DB_HOST . ";charset=" . DB_CHARSET;
    $tempPdo = new PDO($dsnNoDb, DB_USER, DB_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $tempPdo->exec("CREATE DATABASE IF NOT EXISTS " . DB_NAME);
    $tempPdo = null; // Close connection

    // Now include db.php which connects to the created database
    require_once __DIR__ . '/src/db.php';

    // 1. Read and execute schema.sql to initialize tables
    $schemaFile = __DIR__ . '/schema.sql';
    if (!file_exists($schemaFile)) {
        throw new Exception("schema.sql not found at $schemaFile");
    }
    
    $sql = file_get_contents($schemaFile);
    
    // Execute schema SQL
    $pdo->exec($sql);
    echo "<p style='color:green;'>✓ Database tables recreated successfully.</p>";

    // 2. Seed Stores (13 Locations)
    $stores = [
        ['78', 'ARTEE FABRICS & HOME', '600 HIGH ST', 'PORTSMOUTH', 'VA', '23704', '757-966-1808', 'jfreeman.aci@gmail.com, portsmouth.afh@gmail.com'],
        ['82', "PRINTER'S ALLEY", '5910-111 DURALEIGH ROAD', 'RALEIGH', 'NC', '27612', '919-781-1777', 'printersalleyraleigh@gmail.com'],
        ['63', 'ARTEE FABRICS & HOME', '7016 B MARKET STREET', 'WILMINGTON', 'NC', '28411', '910-686-2950', 'wilmington.aci@gmail.com'],
        ['64', 'ARTEE FABRICS & HOME', '1776 LASKIN ROAD SUITE 106', 'VIRGINIA BEACH', 'VA', '23454', '757-963-7820', 'jfreeman.aci@gmail.com, arteevbeach@gmail.com'],
        ['73', 'GOOD GOODS', '859 POST ROAD', 'DARIEN', 'CT', '06820', '203-655-8100', 'goodgoodsgirls@gmail.com'],
        ['71', 'RAGS & RICHES', '3762 SHELBURNE ROAD', 'SHELBURNE', 'VT', '05482', '802-862-3288', 'ragsandriches@comcast.net'],
        ['62', 'ARTEE FABRICS & HOME', '8045 WEST BROAD STREET', 'HENRICO', 'VA', '23294', '804-285-9591', 'richmond@arteefabricsandhome.com'],
        ['70', 'ARTEE FABRICS & HOME', '9543 FIELDS ERTEL ROAD', 'LOVELAND', 'OH', '45140', '513-683-5400', 'cincinnati@arteefabricsandhome.com'],
        ['67', 'ARTEE FABRICS & HOME', '1801 AIRLINE DRIVE SUITE A', 'METAIRIE', 'LA', '70001', '504-302-2160', 'metairiearteefabrics@gmail.com'],
        ['02', 'ARTEE FABRICS AND HOME', '7 DUNNELL LANE EAST', 'PAWTUCKET', 'RI', '02860', '978-212-2683', 'Arti.mehta@gmail.com'],
        ['03', "PRINTER'S ALLEY", '736 S MAIN STREET', 'BURLINGTON', 'NC', '27215', '336-270-4812', 'burlingtonwarehouse@arteefabricsandhome.com, gran4me@gmail.com']
    ];

    $insertStore = $pdo->prepare("INSERT INTO stores (store_code, store_name, address, city, state, zip, phone, notification_emails) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    foreach ($stores as $s) {
        $insertStore->execute($s);
    }
    echo "<p style='color:green;'>✓ Seeded 11 US Stores successfully.</p>";

    // Fetch stores to associate with users
    $stmt = $pdo->query("SELECT id, store_name, store_code, phone, notification_emails FROM stores");
    $dbStores = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 3. Seed Users
    // Format: [store_id, name, email, phone, username, password, role, status]
    $users = [
        [null, 'Super Admin', 'admin@arteefabrics.com', '555-0100', 'admin', 'admin123', 'Super Admin', 'Active'],
        [null, 'Logistics Admin', 'logistics@arteefabrics.com', '555-0200', 'logistics', 'logistics123', 'Logistics Admin', 'Active']
    ];

    // Seed users for all stores dynamically
    foreach ($dbStores as $sRow) {
        $storeId = $sRow['id'];
        $storeName = $sRow['store_name'];
        $storeCode = strtolower(str_replace('AR-', '', $sRow['store_code']));
        // Extract first email for user contact email
        $emails = array_map('trim', explode(',', $sRow['notification_emails']));
        $primaryEmail = !empty($emails[0]) ? $emails[0] : 'store@arteefabrics.com';
        $phoneVal = !empty($sRow['phone']) ? $sRow['phone'] : '555-0000';
        
        $users[] = [
            $storeId,
            $storeName . ' Store User',
            $primaryEmail,
            $phoneVal,
            'store_' . strtolower(str_replace(' ', '_', $storeCode)),
            'store123',
            'Store User',
            'Active'
        ];
    }

    $insertUser = $pdo->prepare("INSERT INTO users (store_id, name, email, phone, username, password_hash, role, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    foreach ($users as $u) {
        $passwordHash = password_hash($u[5], PASSWORD_BCRYPT);
        $insertUser->execute([
            $u[0], // store_id
            $u[1], // name
            $u[2], // email
            $u[3], // phone
            $u[4], // username
            $passwordHash, // password_hash
            $u[6], // role
            $u[7]  // status
        ]);
    }
    echo "<p style='color:green;'>✓ Seeded Admin, Logistics, and store users successfully.</p>";

    // Seed default saved addresses for all stores
    $insertSaved = $pdo->prepare("INSERT INTO saved_addresses (store_id, address_type, name, company, address1, city, state, zip, phone, email) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    foreach ($dbStores as $sRow) {
        $storeId = $sRow['id'];
        
        // Seed Parlor Upholstery
        $insertSaved->execute([$storeId, 'from', 'Staff', 'PARLOR UPHOLSTERY', '201 DEXTER AVENUE', 'WEST HARTFORD', 'CT', '06110', '', '']);
        $insertSaved->execute([$storeId, 'to', 'Staff', 'PARLOR UPHOLSTERY', '201 DEXTER AVENUE', 'WEST HARTFORD', 'CT', '06110', '', '']);
        
        // Seed Queyen Trong
        $insertSaved->execute([$storeId, 'from', 'Staff', 'QUEYEN TRONG', '184 FOREST LANE', 'CHESHIRE', 'CT', '06410', '', '']);
        $insertSaved->execute([$storeId, 'to', 'Staff', 'QUEYEN TRONG', '184 FOREST LANE', 'CHESHIRE', 'CT', '06410', '', '']);
    }
    echo "<p style='color:green;'>✓ Seeded default saved addresses for all stores.</p>";

    // Seed Settings Table
    $insertSetting = $pdo->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES (?, ?)");
    $insertSetting->execute(['minimum_freight_charge', '15.00']);
    echo "<p style='color:green;'>✓ Seeded system settings successfully.</p>";

    // Create the secure uploads folder and add an .htaccess file
    $uploadDir = UPLOAD_DIR;
    if (!file_exists($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    
    // Write .htaccess to block direct access
    file_put_contents($uploadDir . '.htaccess', "Deny from all\n");
    echo "<p style='color:green;'>✓ Created secure uploads directory and added .htaccess protection.</p>";

    echo "<h3 style='color:darkblue;'>Seeding Completed! Ready to login.</h3>";
    echo "<ul>";
    echo "<li><strong>Super Admin:</strong> username: <code>admin</code>, password: <code>admin123</code></li>";
    echo "<li><strong>Logistics Admin:</strong> username: <code>logistics</code>, password: <code>logistics123</code></li>";
    echo "<li><strong>Store Users:</strong> usernames: <code>store_afh_portsmouth</code>, <code>store_pa_raleigh</code>, etc., password: <code>store123</code></li>";
    echo "</ul>";

} catch (Exception $e) {
    echo "<p style='color:red;'>Error during seeding: " . $e->getMessage() . "</p>";
}
