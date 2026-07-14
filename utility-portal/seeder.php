<?php
// Database seeder script for Artee Fabrics & Home Utility Portal
require_once __DIR__ . '/src/config.php';

echo "<h2>Artee Utility Portal Database Seeder</h2>";

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

    // 2. Seed Stores (11 Locations)
    $stores = [
        ['78', 'ARTEE FABRICS & HOME', '600 HIGH ST', 'PORTSMOUTH', 'VA', '23704', '757-966-1808', 'jfreeman.aci@gmail.com, portsmouth.afh@gmail.com', 'Showroom'],
        ['82', "PRINTER'S ALLEY", '5910-111 DURALEIGH ROAD', 'RALEIGH', 'NC', '27612', '919-781-1777', 'printersalleyraleigh@gmail.com', 'Showroom'],
        ['63', 'ARTEE FABRICS & HOME', '7016 B MARKET STREET', 'WILMINGTON', 'NC', '28411', '910-686-2950', 'wilmington.aci@gmail.com', 'Showroom'],
        ['64', 'ARTEE FABRICS & HOME', '1776 LASKIN ROAD SUITE 106', 'VIRGINIA BEACH', 'VA', '23454', '757-963-7820', 'jfreeman.aci@gmail.com, arteevbeach@gmail.com', 'Showroom'],
        ['73', 'GOOD GOODS', '859 POST ROAD', 'DARIEN', 'CT', '06820', '203-655-8100', 'goodgoodsgirls@gmail.com', 'Owned Building'],
        ['71', 'RAGS & RICHES', '3762 SHELBURNE ROAD', 'SHELBURNE', 'VT', '05482', '802-862-3288', 'ragsandriches@comcast.net', 'Owned Building'],
        ['62', 'ARTEE FABRICS & HOME', '8045 WEST BROAD STREET', 'HENRICO', 'VA', '23294', '804-285-9591', 'richmond@arteefabricsandhome.com', 'Showroom'],
        ['70', 'ARTEE FABRICS & HOME', '9543 FIELDS ERTEL ROAD', 'LOVELAND', 'OH', '45140', '513-683-5400', 'cincinnati@arteefabricsandhome.com', 'Showroom'],
        ['67', 'ARTEE FABRICS & HOME', '1801 AIRLINE DRIVE SUITE A', 'METAIRIE', 'LA', '70001', '504-302-2160', 'metairiearteefabrics@gmail.com', 'Showroom'],
        ['02', 'ARTEE FABRICS AND HOME', '7 DUNNELL LANE EAST', 'PAWTUCKET', 'RI', '02860', '978-212-2683', 'Arti.mehta@gmail.com', 'Owned Building'],
        ['03', "PRINTER'S ALLEY", '736 S MAIN STREET', 'BURLINGTON', 'NC', '27215', '336-270-4812', 'burlingtonwarehouse@arteefabricsandhome.com, gran4me@gmail.com', 'Owned Building']
    ];

    $insertStore = $pdo->prepare("INSERT INTO stores (store_code, store_name, address, city, state, zip, phone, notification_emails, location_type) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    foreach ($stores as $s) {
        $insertStore->execute($s);
    }
    echo "<p style='color:green;'>✓ Seeded 11 US Stores/Properties successfully.</p>";

    // Fetch stores to associate with utility connections
    $stmt = $pdo->query("SELECT id, store_name, store_code FROM stores");
    $dbStores = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 3. Seed Users
    $users = [
        [null, 'System Admin', 'admin@arteefabrics.com', '555-0100', 'admin', 'admin123', 'Admin', 'Active'],
        [null, 'Payments Officer', 'payments@arteefabrics.com', '555-0200', 'payments', 'payments123', 'Payments', 'Active']
    ];

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
    echo "<p style='color:green;'>✓ Seeded Admin and Payments users successfully.</p>";

    // 4. Seed Utility Connections for each store
    $utilityTypes = ['Telephone', 'Internet', 'Gas', 'Electricity', 'Sewer', 'Water'];
    $providers = [
        'Telephone' => ['AT&T Business', 'Verizon Enterprise', 'CenturyLink'],
        'Internet' => ['Comcast Business', 'Spectrum Enterprise', 'Cox Communications'],
        'Gas' => ['Dominion Energy', 'Piedmont Natural Gas', 'Duke Energy Gas'],
        'Electricity' => ['Duke Energy', 'Dominion Virginia Power', 'Ohio Edison', 'Entergy Louisiana'],
        'Sewer' => ['Municipal Sewer Authority', 'City Water & Sewer Department'],
        'Water' => ['City Water Department', 'Aqua America', 'American Water']
    ];

    $insertConnection = $pdo->prepare("INSERT INTO utility_connections (store_id, utility_type, provider_name, account_number, notes, status) VALUES (?, ?, ?, ?, ?, 'Active')");
    
    foreach ($dbStores as $sRow) {
        $storeId = $sRow['id'];
        $storeCode = $sRow['store_code'];
        
        foreach ($utilityTypes as $type) {
            // Pick a provider based on type
            $providerList = $providers[$type];
            $provider = $providerList[array_rand($providerList)];
            
            // Generate a dummy account number
            $accountNo = strtoupper($type[0]) . $storeCode . '-' . rand(100000, 999999);
            $notes = "Standard monthly " . strtolower($type) . " service connection for the " . $sRow['store_name'] . " location.";
            
            $insertConnection->execute([$storeId, $type, $provider, $accountNo, $notes]);
        }
    }
    echo "<p style='color:green;'>✓ Seeded standard utility connections for all 11 stores.</p>";

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
    echo "<li><strong>Admin User:</strong> username: <code>admin</code>, password: <code>admin123</code></li>";
    echo "<li><strong>Payments User:</strong> username: <code>payments</code>, password: <code>payments123</code></li>";
    echo "</ul>";

} catch (Exception $e) {
    echo "<p style='color:red;'>Error during seeding: " . $e->getMessage() . "</p>";
}
