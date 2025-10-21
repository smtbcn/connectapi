<?php
/**
 * ==================================================
 * DOĞTAŞ API KONFİGÜRASYON - ÇOKLU BAYİ DESTEĞİ
 * Esnek yapı: İstediğin kadar bayi ekleyebilirsin
 * ==================================================
 */

/**
 * .env dosyasından konfigürasyon bilgilerini yükle
 */
function loadConfigFromEnv($filePath = '.env') {
    if (!file_exists($filePath)) {
        throw new Exception(".env dosyası bulunamadı: $filePath");
    }
    
    $lines = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) {
            continue; // Yorum satırlarını atla
        }
        
        if (strpos($line, '=') === false) {
            continue; // = işareti yoksa atla
        }
        
        list($name, $value) = explode('=', $line, 2);
        $name = trim($name);
        $value = trim($value);
        
        if (!array_key_exists($name, $_ENV)) {
            $_ENV[$name] = $value;
        }
    }
}

// .env dosyasını yükle
loadConfigFromEnv(__DIR__ . '/.env');

// GENEL API AYARLARI (.env'den)
define('API_BASE_URL', $_ENV['API_BASE_URL'] ?? 'https://connectapi.doganlarmobilyagrubu.com');
define('API_CLIENT_ID', $_ENV['API_CLIENT_ID'] ?? 'External');
define('API_CLIENT_SECRET', $_ENV['API_CLIENT_SECRET'] ?? 'externaldMG2024@!');
define('API_APP_CODE', $_ENV['API_APP_CODE'] ?? 'Connect');

// ÇOKLU BAYİ AYARLARI - .env'den yüklenir
$BAYILER = array();

// Aktif bayi sayısı (.env'den)
define('AKTIF_BAYI_SAYISI', (int)($_ENV['AKTIF_BAYI_SAYISI'] ?? 3));

// Bayi bilgilerini .env'den yükle
for ($i = 0; $i < AKTIF_BAYI_SAYISI; $i++) {
    $bayiKey = 'BAYI_' . $i;
    if (isset($_ENV[$bayiKey]) && !empty($_ENV[$bayiKey])) {
        $BAYILER[$i] = $_ENV[$bayiKey];
    }
}

// Config versiyonu (.env'den)
define('CONFIG_VERSION', $_ENV['CONFIG_VERSION'] ?? 'v1.1');

// Rapor domain'i (.env'den)
define('REPORT_DOMAIN', $_ENV['REPORT_DOMAIN'] ?? 'domain.com');
/**
 
* Bayi bilgisi çıkarma fonksiyonları
 */

/**
 * Bayi kullanıcı adını döndürür
 * @param int $bayiIndex Bayi indeksi (0-9 arası)
 * @return string Bayi kullanıcı adı veya boş string
 */
function getBayiUsername($bayiIndex) {
    global $BAYILER;
    
    if ($bayiIndex < AKTIF_BAYI_SAYISI && isset($BAYILER[$bayiIndex]) && $BAYILER[$bayiIndex] !== "") {
        $parts = explode("|", $BAYILER[$bayiIndex]);
        return $parts[0];
    } else {
        return "";
    }
}

/**
 * Bayi şifresini döndürür
 * @param int $bayiIndex Bayi indeksi (0-9 arası)
 * @return string Bayi şifresi veya boş string
 */
function getBayiPassword($bayiIndex) {
    global $BAYILER;
    
    if ($bayiIndex < AKTIF_BAYI_SAYISI && isset($BAYILER[$bayiIndex]) && $BAYILER[$bayiIndex] !== "") {
        $parts = explode("|", $BAYILER[$bayiIndex]);
        return $parts[1];
    } else {
        return "";
    }
}

/**
 * Bayi sipariş verenini döndürür
 * @param int $bayiIndex Bayi indeksi (0-9 arası)
 * @return string Bayi sipariş veren kodu veya boş string
 */
function getBayiOrderer($bayiIndex) {
    global $BAYILER;
    
    if ($bayiIndex < AKTIF_BAYI_SAYISI && isset($BAYILER[$bayiIndex]) && $BAYILER[$bayiIndex] !== "") {
        $parts = explode("|", $BAYILER[$bayiIndex]);
        return $parts[2];
    } else {
        return "";
    }
}

/**
 * Bayi adını döndürür
 * @param int $bayiIndex Bayi indeksi (0-9 arası)
 * @return string Bayi adı veya varsayılan ad
 */
function getBayiName($bayiIndex) {
    global $BAYILER;
    
    if ($bayiIndex < AKTIF_BAYI_SAYISI && isset($BAYILER[$bayiIndex]) && $BAYILER[$bayiIndex] !== "") {
        $parts = explode("|", $BAYILER[$bayiIndex]);
        return $parts[3];
    } else {
        return "Bayi " . ($bayiIndex + 1);
    }
}

?>