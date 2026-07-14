<?php
require_once dirname(__DIR__) . '/src/config.php';
require_once dirname(__DIR__) . '/src/db.php';
require_once dirname(__DIR__) . '/src/functions.php';

echo "=== Testing Notification Label Enrichment ===\n";

// Sample notification title and message from the database query
$title = "Your shipping labels are ready - AR-1017";
$message = "Your shipping labels (1 cartons) are ready. SO#: PO/667. Tracking: 1Z0K43R30318107939 (UPS® Ground)";

$enrich = get_notification_labels($title, $message);

echo "Title: $title\n";
echo "Message: $message\n";
echo "Enrichment Result:\n";
print_r($enrich);

if ($enrich && !empty($enrich['labels'])) {
    echo "✓ Success: Found request ID {$enrich['request_id']} and " . count($enrich['labels']) . " label(s).\n";
} else {
    echo "✗ Failed to resolve labels or request number.\n";
}
