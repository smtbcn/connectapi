<?php
/**
 * ==================================================
 * N8N ORCHESTRATOR - CHUNK'LARI KOORDINE EDER
 * n8n loop node ile kullanılır
 * ==================================================
 */

// Gerekli PHP dosyalarını dahil et
require_once 'config-only.php';
require_once 'security-helper.php';

// Session başlat
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json; charset=utf-8');

// Rate limiting
$clientIP = getClientIP();
if (!checkRateLimit($clientIP, 10, 3600)) {
    echo json_encode([
        "success" => false,
        "error" => "Çok fazla istek"
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$totalDays = isset($_GET['days']) ? (int)$_GET['days'] : 120;
$chunkSize = 7;
$totalChunks = ceil($totalDays / $chunkSize);

// n8n için chunk listesi oluştur
$chunks = [];
for ($i = 0; $i < $totalChunks; $i++) {
    $chunks[] = [
        "chunk_index" => $i,
        "url" => "https://" . $_SERVER['HTTP_HOST'] . "/connectapi/n8n-auto-shipments-chunked.php?chunk=$i&days=$totalDays"
    ];
}

echo json_encode([
    "success" => true,
    "total_days" => $totalDays,
    "chunk_size" => $chunkSize,
    "total_chunks" => $totalChunks,
    "chunks" => $chunks,
    "instructions" => [
        "1" => "n8n'de Loop node kullanın",
        "2" => "chunks array'ini iterate edin",
        "3" => "Her chunk için HTTP Request yapın",
        "4" => "has_more = false olana kadar devam edin"
    ]
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

?>
