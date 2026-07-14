<?php
require_once __DIR__ . '/../src/config.php';
require_once __DIR__ . '/../src/db.php';

try {
    // 1. Fetch all local label records
    $stmt = $pdo->query("SELECT id, request_id, label_file, tracking_number FROM request_labels");
    $localLabels = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if (empty($localLabels)) {
        die("No local labels found in database.\n");
    }

    echo "Found " . count($localLabels) . " local labels in database.\n";

    // Map tracking number -> record
    $localMap = [];
    foreach ($localLabels as $l) {
        if (!empty($l['tracking_number'])) {
            $localMap[trim($l['tracking_number'])] = $l;
        }
    }

    // 2. Fetch recent shipments from Easyship
    $apiKey = EASYSHIP_PROD_API_KEY;
    $url = 'https://public-api.easyship.com/2024-09/shipments?limit=100';

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $apiKey,
        'Accept: application/json',
        'User-Agent: Mozilla/5.0'
    ]);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);

    if ($err) {
        throw new Exception("Curl error: " . $err);
    }
    if ($httpCode >= 400) {
        throw new Exception("Easyship API HTTP {$httpCode}: " . $response);
    }

    $data = json_decode($response, true);
    $shipments = $data['shipments'] ?? [];
    echo "Retrieved " . count($shipments) . " shipments from Easyship API.\n";

    $matchesFound = 0;
    foreach ($shipments as $s) {
        $trackingNo = '';
        foreach ($s['trackings'] ?? [] as $t) {
            if (!empty($t['tracking_number'])) {
                $trackingNo = trim($t['tracking_number']);
                break;
            }
        }
        if (empty($trackingNo) && !empty($s['label']['tracking_number'])) {
            $trackingNo = trim($s['label']['tracking_number']);
        }

        if (empty($trackingNo)) {
            continue;
        }

        if (isset($localMap[$trackingNo])) {
            $localRow = $localMap[$trackingNo];
            $destFile = UPLOAD_DIR . $localRow['label_file'];

            echo "Match found! Tracking: {$trackingNo} | Request ID: {$localRow['request_id']} | File: {$localRow['label_file']}\n";

            // Find label URL
            $labelUrl = '';
            foreach ($s['shipping_documents'] ?? [] as $doc) {
                if (($doc['category'] ?? '') === 'label') {
                    $labelUrl = $doc['url'] ?? '';
                    break;
                }
            }
            if (empty($labelUrl)) {
                $labelUrl = $s['label']['label_raw_url'] ?? $s['label']['label_url'] ?? '';
            }

            if (!empty($labelUrl)) {
                // Force page_size=4x6
                if (strpos($labelUrl, 'page_size=') !== false) {
                    $labelUrl = preg_replace('/page_size=[a-zA-Z0-9_]+/i', 'page_size=4x6', $labelUrl);
                } else {
                    $separator = (strpos($labelUrl, '?') !== false) ? '&' : '?';
                    $labelUrl .= $separator . 'page_size=4x6';
                }

                echo "-> Downloading 4x6 label from: {$labelUrl}\n";

                // Download PDF
                $dlCh = curl_init($labelUrl);
                curl_setopt($dlCh, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($dlCh, CURLOPT_FOLLOWLOCATION, true);
                curl_setopt($dlCh, CURLOPT_SSL_VERIFYPEER, false);
                curl_setopt($dlCh, CURLOPT_SSL_VERIFYHOST, 0);
                $pdfContent = curl_exec($dlCh);
                $pdfCode = curl_getinfo($dlCh, CURLINFO_HTTP_CODE);
                curl_close($dlCh);

                if ($pdfCode >= 400 || empty($pdfContent)) {
                    echo "-> Error: Failed to download PDF (HTTP {$pdfCode})\n";
                } else {
                    if (file_put_contents($destFile, $pdfContent) !== false) {
                        echo "-> Replaced PDF with 4x6 version (" . strlen($pdfContent) . " bytes)\n";
                        $matchesFound++;
                    } else {
                        echo "-> Error: Failed to save PDF to {$destFile}\n";
                    }
                }
            } else {
                echo "-> Error: No label URL found for shipment.\n";
            }
        }
    }

    echo "Done. Successfully re-downloaded {$matchesFound} labels in 4x6 format.\n";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
