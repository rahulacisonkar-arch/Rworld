<?php
// Database seeder script for Artee Fabrics & Home Attendance Portal
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'artee_attendance');
define('DB_CHARSET', 'utf8mb4');

echo "<h2>Artee Attendance Portal Database Seeder</h2>";

try {
    // Connect to MySQL without dbname first to create it if it doesn't exist
    $dsnNoDb = "mysql:host=" . DB_HOST . ";charset=" . DB_CHARSET;
    $tempPdo = new PDO($dsnNoDb, DB_USER, DB_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $tempPdo->exec("CREATE DATABASE IF NOT EXISTS " . DB_NAME);
    $tempPdo = null; // Close connection

    // Re-connect to the created database
    $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);

    // Read and execute schema.sql to initialize tables
    $schemaFile = __DIR__ . '/schema.sql';
    if (!file_exists($schemaFile)) {
        throw new Exception("schema.sql not found at $schemaFile");
    }
    
    $sql = file_get_contents($schemaFile);
    $pdo->exec($sql);
    echo "<p style='color:green;'>✓ Database tables recreated successfully.</p>";

    // Seed Stores (11 Locations from Shipping Portal)
    $stores = [
        ['78', 'ARTEE FABRICS & HOME', '600 HIGH ST', 'PORTSMOUTH', 'VA', '23704', '757-966-1808'],
        ['82', "PRINTER'S ALLEY", '5910-111 DURALEIGH ROAD', 'RALEIGH', 'NC', '27612', '919-781-1777'],
        ['63', 'ARTEE FABRICS & HOME', '7016 B MARKET STREET', 'WILMINGTON', 'NC', '28411', '910-686-2950'],
        ['64', 'ARTEE FABRICS & HOME', '1776 LASKIN ROAD SUITE 106', 'VIRGINIA BEACH', 'VA', '23454', '757-963-7820'],
        ['73', 'GOOD GOODS', '859 POST ROAD', 'DARIEN', 'CT', '06820', '203-655-8100'],
        ['71', 'RAGS & RICHES', '3762 SHELBURNE ROAD', 'SHELBURNE', 'VT', '05482', '802-862-3288'],
        ['62', 'ARTEE FABRICS & HOME', '8045 WEST BROAD STREET', 'HENRICO', 'VA', '23294', '804-285-9591'],
        ['70', 'ARTEE FABRICS & HOME', '9543 FIELDS ERTEL ROAD', 'LOVELAND', 'OH', '45140', '513-683-5400'],
        ['67', 'ARTEE FABRICS & HOME', '1801 AIRLINE DRIVE SUITE A', 'METAIRIE', 'LA', '70001', '504-302-2160'],
        ['02', 'ARTEE FABRICS AND HOME', '7 DUNNELL LANE EAST', 'PAWTUCKET', 'RI', '02860', '978-212-2683'],
        ['03', "PRINTER'S ALLEY", '736 S MAIN STREET', 'BURLINGTON', 'NC', '27215', '336-270-4812']
    ];

    $insertStore = $pdo->prepare("INSERT INTO stores (store_code, store_name, address, city, state, zip, phone) VALUES (?, ?, ?, ?, ?, ?, ?)");
    foreach ($stores as $s) {
        $insertStore->execute($s);
    }
    echo "<p style='color:green;'>✓ Seeded 11 US Stores successfully.</p>";

    // Fetch stores to associate users and default employees
    $stmt = $pdo->query("SELECT id, store_name, store_code, city FROM stores");
    $dbStores = $stmt->fetchAll();

    // Seed Users
    $users = [
        [null, 'Super Admin', 'admin', 'admin123', 'Super Admin']
    ];

    // Create store user accounts dynamically
    foreach ($dbStores as $sRow) {
        $storeId = $sRow['id'];
        $storeCity = strtolower(str_replace(' ', '_', $sRow['city']));
        $storeCode = strtolower($sRow['store_code']);
        
        $users[] = [
            $storeId,
            $sRow['store_name'] . ' (' . $sRow['city'] . ') User',
            'store_' . $storeCode . '_' . $storeCity,
            'store123',
            'Store User'
        ];
    }

    $insertUser = $pdo->prepare("INSERT INTO users (store_id, name, username, password_hash, role) VALUES (?, ?, ?, ?, ?)");
    foreach ($users as $u) {
        $hash = password_hash($u[3], PASSWORD_BCRYPT);
        $insertUser->execute([
            $u[0], // store_id
            $u[1], // name
            $u[2], // username
            $hash, // password_hash
            $u[4]  // role
        ]);
    }
    echo "<p style='color:green;'>✓ Seeded admin and store logins successfully.</p>";

    echo "<h3 style='color:blue;'>Seeding Completed! Ready to login.</h3>";

} catch (Exception $e) {
    echo "<p style='color:red;'>Error during seeding: " . $e->getMessage() . "</p>";
}
