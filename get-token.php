<?php
/**
 * ==================================================
 * DOĞTAŞ API TOKEN ALMA SİSTEMİ
 * Versiyon: 1.0 - Config config-only.php'den geliyor
 * ==================================================
 */

// Konfigürasyon dosyasını dahil et
require_once 'config-only.php';

// Session başlat
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// UTF-8 karakter seti ayarla (sadece doğrudan çağrıldığında)
if (basename($_SERVER['PHP_SELF']) === 'get-token.php') {
    header('Content-Type: application/json; charset=utf-8');
}

/**
 * JSON yanıtından access token çıkarır
 * @param string $jsonResponse API'den gelen JSON yanıt
 * @return string Access token veya boş string
 */
function extractAccessToken($jsonResponse) {
    $tokenValue = "";
    
    if (!empty($jsonResponse)) {
        $startPos = strpos($jsonResponse, '"accessToken":"');
        if ($startPos !== false) {
            $startPos = $startPos + 15; // '"accessToken":"' uzunluğu
            $endPos = strpos($jsonResponse, '"', $startPos);
            if ($endPos !== false && $endPos > $startPos) {
                $tokenValue = substr($jsonResponse, $startPos, $endPos - $startPos);
            }
        }
    }
    
    return $tokenValue;
}

/**
 * Merkezi token alma fonksiyonu - Tüm modüller için
 * @param int $bayiIndex Bayi indeksi
 * @param string $sessionPrefix Session key prefix (örn: "", "Shipments_")
 * @return string Token veya boş string
 */
function getTokenForBayi($bayiIndex, $sessionPrefix = "") {
    try {
        // Bayi bilgilerini kontrol et
        $username = getBayiUsername($bayiIndex);
        $password = getBayiPassword($bayiIndex);
        
        if (empty($username) || empty($password)) {
            error_log("Token alma hatası: Bayi bilgileri eksik (index: $bayiIndex, prefix: $sessionPrefix)");
            return "";
        }
        
        $token = "";
        $url = API_BASE_URL . "/api/Authorization/GetAccessToken";
        $requestBody = json_encode([
            "userName" => $username,
            "password" => $password,
            "clientId" => API_CLIENT_ID,
            "clientSecret" => API_CLIENT_SECRET,
            "applicationCode" => API_APP_CODE
        ]);
        
        if ($requestBody === false) {
            error_log("Token alma hatası: JSON encoding başarısız (bayi: $bayiIndex)");
            return "";
        }
        
        // cURL ile HTTP isteği - ASP benzeri hızlı ayarlar
        $ch = curl_init();
        if ($ch === false) {
            error_log("Token alma hatası: cURL init başarısız (bayi: $bayiIndex)");
            return "";
        }
        
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $requestBody);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Accept: application/json'
        ]);
        // ASP benzeri hızlı token alma ayarları
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);
        
        if ($curlError) {
            error_log("cURL Error (bayi $bayiIndex, prefix: $sessionPrefix): " . $curlError);
            return "";
        }
        
        if ($httpCode == 200 && !empty($response)) {
            $token = extractAccessToken($response);
            if (!empty($token)) {
                // Session key'leri oluştur
                $tokenKey = "AccessToken_" . $sessionPrefix . $bayiIndex;
                $timeKey = "TokenTime_" . $sessionPrefix . $bayiIndex;
                
                $_SESSION[$tokenKey] = $token;
                $_SESSION[$timeKey] = time();
                
                // Geriye uyumluluk için (sadece orders ve index 0 için)
                if (empty($sessionPrefix) && $bayiIndex == 0) {
                    $_SESSION["AccessToken"] = $token;
                    $_SESSION["TokenTime"] = time();
                }
                
                error_log("Token başarıyla alındı (bayi $bayiIndex, prefix: $sessionPrefix): " . getBayiName($bayiIndex));
            } else {
                error_log("Token çıkarma hatası (bayi $bayiIndex, prefix: $sessionPrefix): " . substr($response, 0, 200));
            }
        } else {
            error_log("API hatası (bayi $bayiIndex, prefix: $sessionPrefix): HTTP $httpCode - " . substr($response, 0, 200));
        }
        
        return $token;
        
    } catch (Exception $e) {
        error_log("Token alma genel hatası (bayi $bayiIndex, prefix: $sessionPrefix): " . $e->getMessage());
        return "";
    }
}

/**
 * Yeni token alır (0. bayi için - geriye uyumluluk)
 * @return string Token veya boş string
 */
function getNewToken() {
    return getTokenForBayi(0, "");
}

/**
 * Orders için token alma (wrapper fonksiyon)
 * @param int $bayiIndex Bayi indeksi (0-9 arası)
 * @return string Token veya boş string
 */
function getTokenForBayiOrders($bayiIndex) {
    return getTokenForBayi($bayiIndex, "");
}

/**
 * Shipments için token alma (wrapper fonksiyon)
 * @param int $bayiIndex Bayi indeksi (0-9 arası)
 * @return string Token veya boş string
 */
function getTokenForBayiShipments($bayiIndex) {
    return getTokenForBayi($bayiIndex, "Shipments_");
}

/**
 * Tüm bayiler için token alır (Orders)
 * @return bool Tüm tokenlar başarıyla alındı mı?
 */
function getAllTokensOrders() {
    $allSuccess = true;
    
    for ($i = 0; $i < AKTIF_BAYI_SAYISI; $i++) {
        $tokenResult = getTokenForBayiOrders($i);
        if (empty($tokenResult)) {
            $allSuccess = false;
        }
    }
    
    return $allSuccess;
}

/**
 * Tüm bayiler için token alır (Shipments)
 * @return bool Tüm tokenlar başarıyla alındı mı?
 */
function getAllTokensShipments() {
    $allSuccess = true;
    
    for ($i = 0; $i < AKTIF_BAYI_SAYISI; $i++) {
        $tokenResult = getTokenForBayiShipments($i);
        if (empty($tokenResult)) {
            $allSuccess = false;
        }
    }
    
    return $allSuccess;
}

/**
 * Tüm bayiler için token alır (geriye uyumluluk)
 * @return bool Tüm tokenlar başarıyla alındı mı?
 */
function getAllTokens() {
    return getAllTokensOrders();
}

// Web interface - sadece doğrudan çağrıldığında çalışır
if (basename($_SERVER['PHP_SELF']) === 'get-token.php') {
    // Hangi mod çalışacak kontrol et
    $mode = isset($_GET['mode']) ? $_GET['mode'] : '';

    if ($mode === 'all') {
        // Tüm bayiler için token al
        $allSuccess = getAllTokens();
        if ($allSuccess) {
            echo json_encode([
                "success" => true,
                "message" => "Tüm bayiler için token alındı",
                "bayiSayisi" => AKTIF_BAYI_SAYISI
            ], JSON_UNESCAPED_UNICODE);
        } else {
            echo json_encode([
                "success" => false,
                "error" => "Bazı bayiler için token alınamadı"
            ], JSON_UNESCAPED_UNICODE);
        }
    } else {
        // Tek bayi için token al (geriye uyumluluk)
        $token = getNewToken();
        if (!empty($token)) {
            echo json_encode([
                "success" => true,
                "token" => $token
            ], JSON_UNESCAPED_UNICODE);
        } else {
            echo json_encode([
                "success" => false,
                "error" => "Token alınamadı"
            ], JSON_UNESCAPED_UNICODE);
        }
    }
}

?>