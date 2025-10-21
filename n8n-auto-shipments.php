<?php
/**
 * ==================================================
 * N8N İÇİN OTOMATİK SEVKİYAT SORGULAMA
 * n8n-auto-shipments.asp'den dönüştürülmüş - 10 günlük veri
 * ==================================================
 */

// Gerekli PHP dosyalarını dahil et
require_once 'config-only.php';
require_once 'helper-shipments.php';
require_once 'database-shipments.php';
require_once 'security-helper.php';

// Session başlat
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Uzun işlemler için PHP ayarları
ini_set('max_execution_time', 900); // 15 dakika
ini_set('memory_limit', '512M');
set_time_limit(900);

// UTF-8 karakter seti ve JSON response headers ayarla
header('Content-Type: application/json; charset=utf-8');

// Rate limiting kontrolü
$clientIP = getClientIP();
if (!checkRateLimit($clientIP, 5, 3600)) { // Saatte 5 istek
    echo json_encode([
        "success" => false,
        "error" => "Çok fazla istek. Lütfen daha sonra tekrar deneyin."
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$apiResult = "";
$errorMsg = "";
$successMsg = "";

// Çoklu bayi token kontrolü (Sevkiyat)
if (!ensureAllTokensShipments()) {
    echo json_encode([
        "success" => false,
        "error" => "Token alınamadı - API bağlantısı kontrol edilmeli"
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * Çoklu bayi sevkiyat API çağrısı fonksiyonu
 */
function callShipmentsAPI($startDate, $endDate) {
    global $apiResult, $errorMsg, $successMsg;
    
    // Debug: Token durumlarını kontrol et (Sevkiyat için)
    $debugTokens = "Token Durumu: ";
    for ($i = 0; $i < AKTIF_BAYI_SAYISI; $i++) {
        if (!empty($_SESSION["AccessToken_Shipments_" . $i])) {
            $debugTokens .= getBayiName($i) . ": BAŞARILI ";
        } else {
            $debugTokens .= getBayiName($i) . ": BAŞARISIZ ";
        }
    }
    
    // Tüm bayilerden veri çek ve birleştir
    $apiResult = getCombinedShipments($startDate, $endDate);
    
    if (!empty($apiResult)) {
        // API sonucundan debug bilgisini çıkar
        $apiData = json_decode($apiResult, true);
        $apiDebugInfo = isset($apiData['messages']) ? $apiData['messages'] : '';
        
        // Veritabanına kaydet
        $vtKayitSonuc = sevkiyatKaydet($apiResult);
        
        $successMsg = "Sevkiyatlar başarıyla alındı! (" . AKTIF_BAYI_SAYISI . " bayi birleştirildi) - " . $debugTokens . " - API Detayları: " . $apiDebugInfo . " - Veritabanı Kayıt: " . $vtKayitSonuc;
        return true;
    } else {
        $errorMsg = "Sevkiyatlar API hatası: Veri alınamadı - " . $debugTokens;
        return false;
    }
}

// Tarih hesaplama (son 30 gün - timeout önleme)
$today = new DateTime();
$daysAgo = new DateTime();
$daysAgo->modify('-7 days');

$endDate = $today->format('Y-m-d');
$startDate = $daysAgo->format('Y-m-d');

// Formatlı tarihleri hazırla
$formattedStart = formatDate(strtotime($startDate));
$formattedEnd = formatDate(strtotime($endDate));

// API çağrısını yap
$apiSuccess = callShipmentsAPI($formattedStart, $formattedEnd);

// JSON yanıt döndür
if (!empty($successMsg)) {
    // Başarı durumunda detaylı bilgi ver
    $apiData = json_decode($apiResult, true);
    $messageCode = isset($apiData['messageCode']) ? $apiData['messageCode'] : '200';
    
    // Veritabanı kayıt sonucunu çıkar
    $vtKayitSonuc = "Bilinmiyor";
    $vtStart = strpos($successMsg, "Veritabanı Kayıt: ");
    if ($vtStart !== false) {
        $vtStart = $vtStart + 18;
        $vtKayitSonuc = substr($successMsg, $vtStart);
    }
    
    // Veri sayısını hesapla
    $dataCount = 0;
    if (isset($apiData['data']) && is_array($apiData['data'])) {
        $dataCount = count($apiData['data']);
    }
    
    // ASP formatında JSON oluştur (birebir aynı)
    $vtKayitSonucEscaped = str_replace(['"', "\n"], ['\"', '\\n'], $vtKayitSonuc);
    
    echo '{';
    echo '"success": true,';
    echo '"message": "Sevkiyatlar başarıyla alındı ve veritabanına kaydedildi",';
    echo '"date_range": "' . $startDate . ' - ' . $endDate . '",';
    echo '"api_status": "' . $messageCode . '",';
    echo '"database_result": "' . $vtKayitSonucEscaped . '",';
    echo '"bayi_count": ' . AKTIF_BAYI_SAYISI . ',';
    echo '"raw_data": ' . $apiResult . ',';
    echo '"timestamp": "' . date('Y-m-d H:i:s') . '"';
    echo '}';
} else {
    // ASP formatında hata JSON'u (birebir aynı)
    $errorMsgEscaped = str_replace('"', '\"', $errorMsg);
    
    echo '{';
    echo '"success": false,';
    echo '"error": "' . $errorMsgEscaped . '",';
    echo '"timestamp": "' . date('Y-m-d H:i:s') . '"';
    echo '}';
}

?>