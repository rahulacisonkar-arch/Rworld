<?php
// Simulate Session
session_start();
$_SESSION['user_id'] = 1;
$_SESSION['username'] = 'admin';
$_SESSION['role'] = 'Super Admin';
$_SESSION['store_id'] = null;
$_SESSION['store_name'] = 'HQ';

$_GET['id'] = 4; // Active request ID

ob_start();
require 'c:/Users/Artee Admin/Desktop/browser-use-main/shipping-portal/public/easyship_rates.php';
$output = ob_get_clean();

$data = json_decode($output, true);
if ($data) {
    if (isset($data['success']) && $data['success']) {
        echo "SUCCESS! Mode: " . ($data['mode'] ?? 'unknown') . "\n";
        if (isset($data['warning'])) {
            echo "Warning message: " . $data['warning'] . "\n";
        }
        if (isset($data['rates'])) {
            echo "Retrieved " . count($data['rates']) . " rates:\n";
            foreach ($data['rates'] as $index => $rate) {
                echo " - Rate #" . ($index + 1) . ": " . $rate['courier_name'] . " (" . $rate['courier_id'] . ") => $" . number_format($rate['shipment_charge'], 2) . " (Est: " . $rate['delivery_time'] . ")\n";
            }
        }
    } else {
        echo "API returned success false: " . print_r($data, true) . "\n";
    }
} else {
    echo "Failed to decode JSON. Raw response was: " . substr($output, 0, 500) . "\n";
}
