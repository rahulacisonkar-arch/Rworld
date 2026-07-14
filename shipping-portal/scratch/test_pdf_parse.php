<?php
require_once dirname(__DIR__) . '/src/functions.php';

// Simulate a PDF text content
$pdfText = "
UPS GROUND
TRACKING: 1Z2223908335237616
BILLING: SENDER
EST. DELIVERY: 20-06-2026
";

// 1. Detect Tracking Number
$trackingNumber = '';
if (preg_match('/TRACKING:\s*([^\s\)]+)/i', $pdfText, $match)) {
    $trackingNumber = trim($match[1]);
} elseif (preg_match('/1Z[A-Z0-9]{16}/i', $pdfText, $match)) {
    $trackingNumber = trim($match[0]);
}

// 2. Detect Carrier
$carrier = 'UPS';
if (preg_match('/FedEx/i', $pdfText)) {
    $carrier = 'FedEx';
} elseif (preg_match('/DHL/i', $pdfText)) {
    $carrier = 'DHL';
} elseif (preg_match('/USPS/i', $pdfText)) {
    $carrier = 'USPS';
} elseif (preg_match('/UPS/i', $pdfText)) {
    $carrier = 'UPS';
}

// 3. Detect Est. Delivery Date
$estDelivery = null;
if (preg_match('/\b(20\d{2}-\d{2}-\d{2})\b/', $pdfText, $match)) {
    $estDelivery = date('Y-m-d', strtotime($match[1]));
} elseif (preg_match('/\b(\d{2}-\d{2}-20\d{2})\b/', $pdfText, $match)) {
    $estDelivery = date('Y-m-d', strtotime($match[1]));
} elseif (preg_match('/\b(\d{2}\/\d{2}\/20\d{2})\b/', $pdfText, $match)) {
    $estDelivery = date('Y-m-d', strtotime($match[1]));
}

echo "Tracking: " . $trackingNumber . "\n";
echo "Carrier: " . $carrier . "\n";
echo "Est Delivery: " . $estDelivery . "\n";
