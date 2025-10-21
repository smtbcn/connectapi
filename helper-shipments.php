<?php
/**
 * ==================================================
 * SHIPMENTS HELPER - config-only.php'den Config Alır
 * helper-shipments.asp'den dönüştürülmüş
 * ==================================================
 */

require_once 'config-only.php';
require_once 'get-token.php';

// Session başlat
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

/**
 * Sevkiyat için token kontrolü ve alma fonksiyonu
 * @return bool Token geçerli mi?
 */
function ensureTokenShipments() {
    return ensureTokenForBayiShipments(0);
}

/**
 * Belirli bir bayi için token kontrolü (Sevkiyat)
 * @param int $bayiIndex Bayi indeksi
 * @return bool Token geçerli mi?
 */
function ensureTokenForBayiShipments($bayiIndex) {
    $tokenKey = "AccessToken_Shipments_" . $bayiIndex;
    $timeKey = "TokenTime_Shipments_" . $bayiIndex;
    
    if (!empty($_SESSION[$tokenKey]) && !empty($_SESSION[$timeKey])) {
        // Token süre kontrolü (55 dakika)
        $tokenTime = $_SESSION[$timeKey];
        $currentTime = time();
        $diffMinutes = ($currentTime - $tokenTime) / 60;
        
        if ($diffMinutes < 55) {
            return true;
        }
    }
    
    return false;
}

/**
 * Tüm bayiler için token kontrolü ve alma (Sevkiyat)
 * @return bool Tüm tokenlar geçerli mi?
 */
function ensureAllTokensShipments() {
    $allValid = true;
    
    for ($i = 0; $i < AKTIF_BAYI_SAYISI; $i++) {
        if (!ensureTokenForBayiShipments($i)) {
            // Token geçersiz, yeni token almaya çalış
            $newToken = getTokenForBayiShipments($i);
            if (empty($newToken)) {
                $allValid = false;
                error_log("Sevkiyat Token alınamadı - Bayi $i: " . getBayiName($i));
            }
        }
    }
    
    return $allValid;
}

// Token alma fonksiyonu artık get-token.php'de merkezi olarak yönetiliyor

/**
 * Tüm bayilerden sevkiyat verisi çekip birleştirme - PARALEL API ÇAĞRILARI (ASP benzeri hız)
 * @param string $startDate Başlangıç tarihi
 * @param string $endDate Bitiş tarihi
 * @return string Birleştirilmiş JSON yanıtı
 */
function getCombinedShipments($startDate, $endDate) {
    $combinedData = "";
    $debugInfo = "";
    
    // Paralel cURL çağrıları için multi handle
    $multiHandle = curl_multi_init();
    $curlHandles = [];
    
    // Tüm bayiler için cURL handle'ları hazırla
    for ($i = 0; $i < AKTIF_BAYI_SAYISI; $i++) {
        $tokenKey = "AccessToken_Shipments_" . $i;
        
        // Token kontrolü
        if (empty($_SESSION[$tokenKey])) {
            $debugInfo .= getBayiName($i) . ": NO_TOKEN | ";
            continue;
        }
        
        // Sevkiyat API için özel request body
        $requestBody = json_encode([
            "deliveryDocument" => "",
            "orderer" => getBayiOrderer($i),
            "transportationNumber" => "",
            "documentDateStart" => $startDate,
            "documentDateEnd" => $endDate
        ]);
        
        $url = API_BASE_URL . "/api/SapDealer/GetShipments";
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $requestBody);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $_SESSION[$tokenKey]
        ]);
        // Hızlı ayarlar
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
        
        curl_multi_add_handle($multiHandle, $ch);
        $curlHandles[$i] = $ch;
        
        // Debug info başlangıcı - bayi adı ve kodu
    }
    
    // Paralel çağrıları çalıştır
    $running = null;
    do {
        curl_multi_exec($multiHandle, $running);
        curl_multi_select($multiHandle);
    } while ($running > 0);
    
    // Sonuçları topla
    for ($i = 0; $i < AKTIF_BAYI_SAYISI; $i++) {
        if (!isset($curlHandles[$i])) {
            $debugInfo .= getBayiName($i) . " (" . getBayiOrderer($i) . ") - NO_TOKEN | ";
            continue;
        }
        
        $ch = $curlHandles[$i];
        $response = curl_multi_getcontent($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        
        // Debug: Bayi bilgilerini ekle
        $debugInfo .= getBayiName($i) . " (" . getBayiOrderer($i) . ") - ";
        
        if ($httpCode == 200 && !empty($response)) {
            $bayiData = parseDataArray($response);
            if (!empty($bayiData)) {
                $debugInfo .= "Veri var | ";
                if (empty($combinedData)) {
                    $combinedData = $bayiData;
                } else {
                    $combinedData .= "," . $bayiData;
                }
            } else {
                $debugInfo .= "API OK ama veri yok | ";
            }
        } elseif ($httpCode == 401) {
            // Token süresi dolmuş
            unset($_SESSION["AccessToken_Shipments_" . $i]);
            $debugInfo .= "TOKEN_EXPIRED | ";
        } else {
            $debugInfo .= "HTTP_" . $httpCode . " | ";
        }
        
        curl_multi_remove_handle($multiHandle, $ch);
        curl_close($ch);
    }
    
    curl_multi_close($multiHandle);
    
    // Birleştirilmiş JSON oluştur
    if (!empty($combinedData)) {
        $finalResult = json_encode([
            "isSuccess" => true,
            "data" => json_decode("[" . $combinedData . "]", true),
            "messageCode" => "200",
            "messages" => $debugInfo
        ], JSON_UNESCAPED_UNICODE);
    } else {
        $finalResult = json_encode([
            "isSuccess" => true,
            "data" => [],
            "messageCode" => "200",
            "messages" => "Veri bulunamadı - " . $debugInfo
        ], JSON_UNESCAPED_UNICODE);
    }
    
    return $finalResult;
}

/**
 * Data array çıkarma (Sevkiyat için)
 * @param string $jsonText JSON metni
 * @return string Çıkarılan data array
 */
function parseDataArray($jsonText) {
    $dataContent = "";
    
    if (!empty($jsonText)) {
        $dataStart = strpos($jsonText, '"data":[');
        if ($dataStart !== false) {
            $dataStart = $dataStart + 8;
            $dataEnd = strpos($jsonText, '],"messageCode"', $dataStart);
            if ($dataEnd !== false && $dataEnd > $dataStart) {
                $dataContent = substr($jsonText, $dataStart, $dataEnd - $dataStart);
            }
        }
    }
    
    return $dataContent;
}

/**
 * Tarih formatı (Sevkiyat için)
 * @param mixed $dateInput Tarih girişi
 * @return string Formatlanmış tarih
 */
function formatDate($dateInput) {
    if (empty($dateInput)) {
        return "";
    }
    
    $timestamp = is_numeric($dateInput) ? $dateInput : strtotime($dateInput);
    if ($timestamp === false) {
        return "";
    }
    
    return date("d.m.Y", $timestamp);
}

?>