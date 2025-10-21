<?php
/**
 * ==================================================
 * GÜVENLİK HELPER - Girdi doğrulama ve temizleme
 * ==================================================
 */

/**
 * Girdi temizleme ve doğrulama
 * @param mixed $input Temizlenecek girdi
 * @param string $type Veri tipi (string, int, email, date)
 * @param int $maxLength Maksimum uzunluk
 * @return mixed Temizlenmiş veri veya false
 */
function sanitizeInput($input, $type = 'string', $maxLength = 255) {
    if ($input === null || $input === '') {
        return '';
    }
    
    switch ($type) {
        case 'int':
            return filter_var($input, FILTER_VALIDATE_INT);
            
        case 'float':
            return filter_var($input, FILTER_VALIDATE_FLOAT);
            
        case 'email':
            return filter_var($input, FILTER_VALIDATE_EMAIL);
            
        case 'url':
            return filter_var($input, FILTER_VALIDATE_URL);
            
        case 'date':
            $date = DateTime::createFromFormat('Y-m-d', $input);
            return $date && $date->format('Y-m-d') === $input ? $input : false;
            
        case 'string':
        default:
            // HTML ve PHP etiketlerini temizle
            $cleaned = strip_tags($input);
            // Özel karakterleri encode et
            $cleaned = htmlspecialchars($cleaned, ENT_QUOTES, 'UTF-8');
            // Uzunluk kontrolü
            if (strlen($cleaned) > $maxLength) {
                $cleaned = substr($cleaned, 0, $maxLength);
            }
            return trim($cleaned);
    }
}

/**
 * SQL injection koruması için string escape
 * @param string $input Escape edilecek string
 * @return string Güvenli string
 */
function escapeString($input) {
    if (empty($input)) {
        return '';
    }
    
    // Tek tırnak karakterlerini escape et
    $escaped = str_replace("'", "''", $input);
    // Null byte saldırılarını önle
    $escaped = str_replace("\0", "", $escaped);
    // Diğer tehlikeli karakterleri temizle
    $escaped = str_replace(["\r", "\n", "\t"], " ", $escaped);
    
    return $escaped;
}

/**
 * GET/POST parametrelerini güvenli şekilde al
 * @param string $key Parametre anahtarı
 * @param string $method GET veya POST
 * @param string $type Veri tipi
 * @param mixed $default Varsayılan değer
 * @return mixed Güvenli parametre değeri
 */
function getSecureParam($key, $method = 'GET', $type = 'string', $default = '') {
    $source = ($method === 'POST') ? $_POST : $_GET;
    
    if (!isset($source[$key])) {
        return $default;
    }
    
    $value = sanitizeInput($source[$key], $type);
    return ($value !== false) ? $value : $default;
}

/**
 * Rate limiting kontrolü (basit implementasyon)
 * @param string $identifier Benzersiz tanımlayıcı (IP, user ID vb.)
 * @param int $maxRequests Maksimum istek sayısı
 * @param int $timeWindow Zaman penceresi (saniye)
 * @return bool İstek izni var mı?
 */
function checkRateLimit($identifier, $maxRequests = 60, $timeWindow = 3600) {
    if (session_status() == PHP_SESSION_NONE) {
        session_start();
    }
    
    $key = 'rate_limit_' . md5($identifier);
    $now = time();
    
    if (!isset($_SESSION[$key])) {
        $_SESSION[$key] = ['count' => 1, 'start_time' => $now];
        return true;
    }
    
    $data = $_SESSION[$key];
    
    // Zaman penceresi sıfırlandı mı?
    if (($now - $data['start_time']) > $timeWindow) {
        $_SESSION[$key] = ['count' => 1, 'start_time' => $now];
        return true;
    }
    
    // Limit aşıldı mı?
    if ($data['count'] >= $maxRequests) {
        return false;
    }
    
    // İstek sayısını artır
    $_SESSION[$key]['count']++;
    return true;
}

/**
 * IP adresini güvenli şekilde al
 * @return string IP adresi
 */
function getClientIP() {
    $ipKeys = ['HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'HTTP_CLIENT_IP', 'REMOTE_ADDR'];
    
    foreach ($ipKeys as $key) {
        if (!empty($_SERVER[$key])) {
            $ip = $_SERVER[$key];
            // Virgülle ayrılmış IP listesi varsa ilkini al
            if (strpos($ip, ',') !== false) {
                $ip = trim(explode(',', $ip)[0]);
            }
            // IP formatını doğrula
            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                return $ip;
            }
        }
    }
    
    return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
}

/**
 * Güvenlik başlıklarını ayarla
 */
function setSecurityHeaders() {
    // XSS koruması
    header('X-XSS-Protection: 1; mode=block');
    // Content type sniffing koruması
    header('X-Content-Type-Options: nosniff');
    // Clickjacking koruması
    header('X-Frame-Options: DENY');
    // HTTPS zorlama (production için)
    if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    }
    // Referrer policy
    header('Referrer-Policy: strict-origin-when-cross-origin');
}

/**
 * Tehlikeli dosya uzantılarını kontrol et
 * @param string $filename Dosya adı
 * @return bool Güvenli mi?
 */
function isSecureFilename($filename) {
    $dangerousExtensions = [
        'php', 'php3', 'php4', 'php5', 'phtml', 'asp', 'aspx', 'jsp', 'js', 'exe', 'bat', 'cmd', 'sh'
    ];
    
    $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    return !in_array($extension, $dangerousExtensions);
}

/**
 * JSON yanıtı için güvenlik kontrolü
 * @param array $data Yanıt verisi
 * @return string Güvenli JSON
 */
function secureJsonResponse($data) {
    // Hassas bilgileri temizle
    $cleanData = removeSecretKeys($data);
    
    // JSON encode ile güvenli çıktı
    $json = json_encode($cleanData, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
    
    if ($json === false) {
        return json_encode(['error' => 'JSON encoding hatası'], JSON_UNESCAPED_UNICODE);
    }
    
    return $json;
}

/**
 * Hassas anahtarları temizle
 * @param array $data Veri dizisi
 * @return array Temizlenmiş veri
 */
function removeSecretKeys($data) {
    $secretKeys = ['password', 'token', 'secret', 'key', 'auth', 'credential'];
    
    if (is_array($data)) {
        foreach ($data as $key => $value) {
            $lowerKey = strtolower($key);
            foreach ($secretKeys as $secretKey) {
                if (strpos($lowerKey, $secretKey) !== false) {
                    $data[$key] = '***HIDDEN***';
                    break;
                }
            }
            
            if (is_array($value)) {
                $data[$key] = removeSecretKeys($value);
            }
        }
    }
    
    return $data;
}

?>