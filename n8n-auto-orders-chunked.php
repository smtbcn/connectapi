<?php
/**
 * ==================================================
 * CHUNK BAZLI SİPARİŞ SORGULAMA - QUEUE SİSTEMİ
 * Her çağrı sadece 1 chunk işler (timeout önleme)
 * ==================================================
 */

// Gerekli PHP dosyalarını dahil et
require_once 'config-only.php';
require_once 'helper-orders.php';
require_once 'database-orders.php';
require_once 'security-helper.php';

// Session başlat
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// UTF-8 karakter seti
header('Content-Type: application/json; charset=utf-8');

// Rate limiting kontrolü
$clientIP = getClientIP();
if (!checkRateLimit($clientIP, 100, 3600)) { // Saatte 100 istek (chunk'lar için)
    echo json_encode([
        "success" => false,
        "error" => "Çok fazla istek. Lütfen daha sonra tekrar deneyin."
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// Token kontrolü (Orders)
if (!ensureAllTokens()) {
    echo json_encode([
        "success" => false,
        "error" => "Token alınamadı - API bağlantısı kontrol edilmeli"
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// URL parametrelerinden chunk bilgisi al
$chunkIndex = isset($_GET['chunk']) ? (int)$_GET['chunk'] : 0;
$totalDays = isset($_GET['days']) ? (int)$_GET['days'] : 120;
$chunkSize = 7; // Her chunk 7 gün

// Toplam chunk sayısı
$totalChunks = ceil($totalDays / $chunkSize);

// Chunk index kontrolü
if ($chunkIndex < 0 || $chunkIndex >= $totalChunks) {
    echo json_encode([
        "success" => false,
        "error" => "Geçersiz chunk index: $chunkIndex (max: " . ($totalChunks - 1) . ")"
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// Bu chunk için tarih aralığını hesapla
$today = new DateTime();
$startDate = new DateTime();
$startDate->modify('-' . $totalDays . ' days');
$startDate->modify('+' . ($chunkIndex * $chunkSize) . ' days');

$endDate = clone $startDate;
$endDate->modify('+' . ($chunkSize - 1) . ' days');

// Son chunk'ta bugünü geçme
if ($endDate > $today) {
    $endDate = clone $today;
}

$chunkStartDate = $startDate->format('Y-m-d');
$chunkEndDate = $endDate->format('Y-m-d');

// Formatlı tarihleri hazırla
$formattedStart = formatDate(strtotime($chunkStartDate));
$formattedEnd = formatDate(strtotime($chunkEndDate));

// API çağrısı yap (Orders)
$chunkApiResult = getCombinedOrders($formattedStart, $formattedEnd);

if (!empty($chunkApiResult)) {
    // Veritabanına kaydet
    $vtKayitSonuc = musteriKaydet($chunkApiResult);
    
    // API data'yı parse et
    $apiData = json_decode($chunkApiResult, true);
    $dataArray = isset($apiData['data']) ? $apiData['data'] : [];
    
    // Sonuçları parse et - "Musteriler: Yeni=5 | ContentAPI: Yeni=150, Guncelleme=25" formatında
    $musteriYeni = 0;
    $musteriGuncelleme = 0;
    $contentYeni = 0;
    $contentGuncelleme = 0;
    
    // Musteriler kayıtları
    if (preg_match('/Musteriler: Yeni=(\d+)/', $vtKayitSonuc, $matches)) {
        $musteriYeni = (int)$matches[1];
    }
    if (preg_match('/Musteriler:.*Guncelleme=(\d+)/', $vtKayitSonuc, $matches)) {
        $musteriGuncelleme = (int)$matches[1];
    }
    
    // ContentAPI kayıtları
    if (preg_match('/ContentAPI: Yeni=(\d+)/', $vtKayitSonuc, $matches)) {
        $contentYeni = (int)$matches[1];
    }
    if (preg_match('/ContentAPI:.*Guncelleme=(\d+)/', $vtKayitSonuc, $matches)) {
        $contentGuncelleme = (int)$matches[1];
    }
    
    $newRecords = $musteriYeni + $contentYeni;
    $updates = $musteriGuncelleme + $contentGuncelleme;
    
    // Bir sonraki chunk var mı?
    $nextChunk = $chunkIndex + 1;
    $hasMore = $nextChunk < $totalChunks;
    
    // Response
    echo json_encode([
        "success" => true,
        "chunk" => [
            "current" => $chunkIndex,
            "total" => $totalChunks,
            "progress" => round(($chunkIndex + 1) / $totalChunks * 100, 1) . "%"
        ],
        "date_range" => "$chunkStartDate - $chunkEndDate",
        "results" => [
            "musteriler" => [
                "new" => $musteriYeni,
                "updated" => $musteriGuncelleme
            ],
            "content_api" => [
                "new" => $contentYeni,
                "updated" => $contentGuncelleme
            ],
            "total" => [
                "new" => $newRecords,
                "updated" => $updates
            ]
        ],
        "data_count" => count($dataArray),
        "data" => $dataArray,
        "has_more" => $hasMore,
        "next_chunk_url" => $hasMore ? "n8n-auto-orders-chunked.php?chunk=$nextChunk&days=$totalDays" : null,
        "timestamp" => date('Y-m-d H:i:s')
    ], JSON_UNESCAPED_UNICODE);
    
} else {
    echo json_encode([
        "success" => false,
        "error" => "Chunk $chunkIndex için veri alınamadı",
        "chunk" => [
            "current" => $chunkIndex,
            "total" => $totalChunks
        ],
        "timestamp" => date('Y-m-d H:i:s')
    ], JSON_UNESCAPED_UNICODE);
}

?>
