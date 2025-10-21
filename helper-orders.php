<?php
/**
 * ==================================================
 * ORDERS HELPER - Sipariş işlemleri için yardımcı fonksiyonlar
 * Token yönetimi ve API çağrıları
 * ==================================================
 */

// Konfigürasyon ve token yöneticisini dahil et
require_once 'config-only.php';
require_once 'get-token.php';

// Session başlat
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

/**
 * Token kontrolü ve alma fonksiyonu (ilk bayi için çalışır)
 * @return bool Token geçerli mi?
 */
function ensureToken() {
    // İlk bayi için token kontrolü yap
    return ensureTokenForBayi(0);
}

/**
 * Belirli bir bayi için token kontrolü
 * @param int $bayiIndex Bayi indeksi
 * @return bool Token geçerli mi?
 */
function ensureTokenForBayi($bayiIndex) {
    $tokenKey = "AccessToken_" . $bayiIndex;
    $timeKey = "TokenTime_" . $bayiIndex;
    
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
 * Config değişikliği kontrolü ve session temizleme
 */
function checkConfigVersion() {
    if (!isset($_SESSION["ConfigVersion"]) || $_SESSION["ConfigVersion"] !== CONFIG_VERSION) {
        // Config değişmiş, tüm token sessionlarını temizle
        for ($i = 0; $i <= 10; $i++) { // Maksimum bayi sayısı kadar temizle
            unset($_SESSION["AccessToken_" . $i]);
            unset($_SESSION["TokenTime_" . $i]);
        }
        // Eski sessionları da temizle
        unset($_SESSION["AccessToken"]);
        unset($_SESSION["TokenTime"]);
        // Yeni versiyonu kaydet
        $_SESSION["ConfigVersion"] = CONFIG_VERSION;
    }
}

/**
 * Tüm bayiler için token kontrolü ve alma
 * @return bool Tüm tokenlar geçerli mi?
 */
function ensureAllTokens() {
    // Önce config versiyonunu kontrol et
    checkConfigVersion();
    
    $allValid = true;
    
    for ($i = 0; $i < AKTIF_BAYI_SAYISI; $i++) {
        if (!ensureTokenForBayi($i)) {
            // Token geçersiz, yeni token almaya çalış
            $newToken = getTokenForBayiOrders($i);
            if (empty($newToken)) {
                $allValid = false;
                error_log("Token alınamadı - Bayi $i: " . getBayiName($i));
            }
        }
    }
    
    return $allValid;
}

// Token alma fonksiyonu artık get-token.php'de merkezi olarak yönetiliyor

/**
 * Belirli bayi için API çağrısı
 * @param int $bayiIndex Bayi indeksi
 * @param string $endpoint API endpoint
 * @param string $requestBody İstek gövdesi
 * @return string API yanıtı veya hata mesajı
 */
function callAPIForBayi($bayiIndex, $endpoint, $requestBody) {
    $tokenKey = "AccessToken_" . $bayiIndex;
    
    // Token kontrolü
    if (empty($_SESSION[$tokenKey])) {
        return "ERROR:NO_TOKEN";
    }
    
    $url = API_BASE_URL . $endpoint;
    
    // cURL ile API çağrısı - ASP optimizasyonları
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $requestBody);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $_SESSION[$tokenKey]
    ]);
    // ASP benzeri hızlı ayarlar
    curl_setopt($ch, CURLOPT_TIMEOUT, 15); // 30'dan 15'e düşürdük
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5); // Bağlantı timeout'u
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_MAXREDIRS, 3);
    curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1); // HTTP/1.1 zorla
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);
    
    if ($curlError) {
        return "ERROR:CURL_" . $curlError;
    }
    
    if ($httpCode == 200) {
        return $response;
    } elseif ($httpCode == 401) {
        // Token süresi dolmuş, temizle
        unset($_SESSION[$tokenKey]);
        return "ERROR:TOKEN_EXPIRED";
    } else {
        return "ERROR:HTTP_" . $httpCode;
    }
}

/**
 * Tüm bayilerden sipariş verisi çekip birleştirme - PARALEL API ÇAĞRILARI (ASP benzeri hız)
 * @param string $startDate Başlangıç tarihi
 * @param string $endDate Bitiş tarihi
 * @return string Birleştirilmiş JSON yanıtı
 */
function getCombinedOrders($startDate, $endDate) {
    $combinedData = "";
    $debugInfo = "";
    
    // Paralel cURL çağrıları için multi handle
    $multiHandle = curl_multi_init();
    $curlHandles = [];
    $requestBodies = [];
    
    // Tüm bayiler için cURL handle'ları hazırla
    for ($i = 0; $i < AKTIF_BAYI_SAYISI; $i++) {
        $tokenKey = "AccessToken_" . $i;
        
        // Token kontrolü
        if (empty($_SESSION[$tokenKey])) {
            $debugInfo .= getBayiName($i) . ": NO_TOKEN | ";
            continue;
        }
        
        $requestBody = json_encode([
            "customerNo" => getBayiOrderer($i),
            "orderId" => "",
            "salesDocumentType" => "",
            "registrationDateStart" => $startDate,
            "registrationDateEnd" => $endDate,
            "referenceDocumentNo" => "",
            "materialNo" => ""
        ]);
        
        $url = API_BASE_URL . "/api/SapDealer/GetOrders";
        
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
        $requestBodies[$i] = $requestBody;
        
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
            unset($_SESSION["AccessToken_" . $i]);
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
 * Türkçe karakter desteği
 * @param string $text Temizlenecek metin
 * @return string Temizlenmiş metin
 */
function safeEncode($text) {
    $result = htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    $result = str_replace("Ã¼", "ü", $result);
    $result = str_replace("Ã¶", "ö", $result);
    $result = str_replace("Ã§", "ç", $result);
    $result = str_replace("Ä±", "ı", $result);
    $result = str_replace("Ä°", "İ", $result);
    $result = str_replace("Åž", "ş", $result);
    $result = str_replace("Ä", "ğ", $result);
    return $result;
}

/**
 * Tarih formatı
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

/**
 * JSON değer çıkarma
 * @param string $jsonText JSON metni
 * @param string $fieldName Alan adı
 * @return string Çıkarılan değer
 */
function extractJsonValue($jsonText, $fieldName) {
    $result = "";
    
    if (!empty($jsonText) && !empty($fieldName)) {
        $pattern = '"' . $fieldName . '":';
        $startPos = strpos($jsonText, $pattern);
        if ($startPos !== false) {
            $startPos = $startPos + strlen($pattern);
            if (substr($jsonText, $startPos, 1) === '"') {
                $startPos = $startPos + 1;
                $endPos = strpos($jsonText, '"', $startPos);
                if ($endPos !== false && $endPos > $startPos) {
                    $result = substr($jsonText, $startPos, $endPos - $startPos);
                }
            } else {
                $endPos = strpos($jsonText, ',', $startPos);
                if ($endPos === false) {
                    $endPos = strpos($jsonText, '}', $startPos);
                }
                if ($endPos !== false && $endPos > $startPos) {
                    $result = trim(substr($jsonText, $startPos, $endPos - $startPos));
                }
            }
        }
    }
    
    return $result;
}

/**
 * Data array çıkarma
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
 * Object değer çıkarma
 * @param string $objectText Object metni
 * @param string $fieldName Alan adı
 * @return string Çıkarılan değer
 */
function extractObjectValue($objectText, $fieldName) {
    $result = "";
    
    if (!empty($objectText) && !empty($fieldName)) {
        $pattern = '"' . $fieldName . '":';
        $startPos = strpos($objectText, $pattern);
        if ($startPos !== false) {
            $startPos = $startPos + strlen($pattern);
            if (substr($objectText, $startPos, 1) === '"') {
                $startPos = $startPos + 1;
                $endPos = strpos($objectText, '"', $startPos);
                if ($endPos !== false && $endPos > $startPos) {
                    $result = substr($objectText, $startPos, $endPos - $startPos);
                }
            } else {
                $endPos = strpos($objectText, ',', $startPos);
                if ($endPos === false) {
                    $endPos = strpos($objectText, '}', $startPos);
                }
                if ($endPos !== false && $endPos > $startPos) {
                    $result = trim(substr($objectText, $startPos, $endPos - $startPos));
                }
            }
        }
    }
    
    return $result;
}

?>