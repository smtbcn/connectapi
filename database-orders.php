<?php
/**
 * ==================================================
 * SİPARİŞ VERİTABANI İŞLEMLERİ - vt-kayit.asp'den dönüştürülmüş
 * ==================================================
 */

/**
 * .env dosyasından veritabanı bilgilerini yükle
 */
function loadEnvFileOrders($filePath = '.env') {
    if (!file_exists($filePath)) {
        throw new Exception(".env dosyası bulunamadı: $filePath");
    }
    
    $lines = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) {
            continue; // Yorum satırlarını atla
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
loadEnvFileOrders(__DIR__ . '/.env');

/**
 * Veritabanı bağlantı bilgileri (.env dosyasından)
 */
$orders_host = $_ENV['DB_HOST'] ?? 'localhost';
$orders_dbuser = $_ENV['DB_USERNAME'] ?? '';
$orders_dbsifre = $_ENV['DB_PASSWORD'] ?? '';
$orders_db = $_ENV['DB_NAME'] ?? '';
$orders_charset = $_ENV['DB_CHARSET'] ?? 'utf8';

/**
 * PDO veritabanı bağlantısı oluştur (Siparişler için)
 * @return PDO|null Veritabanı bağlantısı veya null
 */
function getDatabaseConnectionOrders() {
    global $orders_host, $orders_dbuser, $orders_dbsifre, $orders_db, $orders_charset;
    
    try {
        $dsn = "mysql:host=$orders_host;dbname=$orders_db;charset=$orders_charset";
        $pdo = new PDO($dsn, $orders_dbuser, $orders_dbsifre, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES $orders_charset COLLATE {$orders_charset}_turkish_ci"
        ]);
        return $pdo;
    } catch (PDOException $e) {
        error_log("Orders database connection error: " . $e->getMessage());
        return null;
    }
}

/**
 * Türkçe karakter temizleme (Siparişler için)
 * @param string $metin Temizlenecek metin
 * @return string Temizlenmiş metin
 */
function turkceTemizleOrders($metin) {
    $sonuc = trim($metin);
    
    // Boş kontrolü
    if (empty($sonuc)) {
        return "";
    }
    
    // Türkçe karakterleri tek tek değiştir
    $sonuc = str_replace("ç", "c", $sonuc);
    $sonuc = str_replace("Ç", "c", $sonuc);
    $sonuc = str_replace("ğ", "g", $sonuc);
    $sonuc = str_replace("Ğ", "g", $sonuc);
    $sonuc = str_replace("ı", "i", $sonuc);
    $sonuc = str_replace("İ", "i", $sonuc);
    $sonuc = str_replace("ö", "o", $sonuc);
    $sonuc = str_replace("Ö", "o", $sonuc);
    $sonuc = str_replace("ş", "s", $sonuc);
    $sonuc = str_replace("Ş", "s", $sonuc);
    $sonuc = str_replace("ü", "u", $sonuc);
    $sonuc = str_replace("Ü", "u", $sonuc);
    
    // Büyük harfe çevir
    return strtoupper($sonuc);
}

/**
 * Tarih formatı dönüştürme (20250929 -> 29.09.2025) - Siparişler için
 * @param string $tarihStr Tarih string
 * @return string Formatlanmış tarih
 */
function tarihFormatlaOrders($tarihStr) {
    $temizTarih = trim($tarihStr);
    
    // Boş veya 00000000 ise boş döndür
    if (empty($temizTarih) || $temizTarih === "00000000" || $temizTarih === "null") {
        return "";
    }
    
    // YYYYMMDD formatından DD.MM.YYYY formatına çevir
    if (strlen($temizTarih) === 8 && is_numeric($temizTarih)) {
        $yil = substr($temizTarih, 0, 4);
        $ay = substr($temizTarih, 4, 2);
        $gun = substr($temizTarih, 6, 2);
        return $gun . "." . $ay . "." . $yil;
    } else {
        return $temizTarih;
    }
}

/**
 * Miktar formatı (2,000 -> 2) - Siparişler için
 * @param string $miktarStr Miktar string
 * @return string Formatlanmış miktar
 */
function miktarFormatlaOrders($miktarStr) {
    $temizMiktar = trim($miktarStr);
    
    // Virgülden sonrasını kaldır
    $virgulPos = strpos($temizMiktar, ",");
    if ($virgulPos !== false) {
        $temizMiktar = substr($temizMiktar, 0, $virgulPos);
    }
    
    return $temizMiktar;
}

/**
 * ProductId formatı (000000003120018287 -> 3120018287) - Siparişler için
 * @param string $productIdStr Product ID string
 * @return string Formatlanmış product ID
 */
function productIdFormatlaOrders($productIdStr) {
    $temizProductId = trim($productIdStr);
    
    // Boş ise boş döndür
    if (empty($temizProductId) || $temizProductId === "null") {
        return "";
    }
    
    // Başındaki sıfırları temizle
    $temizProductId = ltrim($temizProductId, "0");
    
    // Eğer tamamen sıfırlardan oluşuyorsa "0" döndür
    if (empty($temizProductId)) {
        return "0";
    }
    
    return $temizProductId;
}

/**
 * ProductName temizleme (content_api tablosu için) - Siparişler için
 * @param string $productNameStr Product name string
 * @return string Temizlenmiş product name
 */
function productNameTemizleOrders($productNameStr) {
    $temizProductName = trim($productNameStr);
    
    // Boş ise boş döndür
    if (empty($temizProductName) || $temizProductName === "null") {
        return "";
    }
    
    // Karakter dönüşümleri
    $temizProductName = str_replace("'", "-", $temizProductName);  // Tek tırnak -> tire
    $temizProductName = str_replace("+", "-", $temizProductName);  // Artı -> tire
    $temizProductName = str_replace("%", "", $temizProductName);   // Yüzde -> sil
    
    return $temizProductName;
}

/**
 * Müşteri kaydet - ASP mantığını tam olarak kopyalar
 * @param string $jsonVeri JSON veri
 * @return string Sonuç mesajı
 */
function musteriKaydet($jsonVeri) {
    if (empty($jsonVeri)) {
        return "JSON boş";
    }
    
    $pdo = getDatabaseConnectionOrders();
    if (!$pdo) {
        return "Veritabanı bağlantısı kurulamadı";
    }
    
    $yeniKayit = 0;
    $guncelleme = 0;
    $hataSayisi = 0;
    $contentApiYeni = 0;
    $contentApiGuncelleme = 0;
    
    // JSON'dan veri çıkar (basit yöntem - ASP mantığı)
    $dataStart = strpos($jsonVeri, '"data":[');
    if ($dataStart === false) {
        return "JSON data bulunamadı";
    }
    
    $dataStart = strpos($jsonVeri, '[', $dataStart) + 1;
    $dataEnd = strrpos($jsonVeri, ']');
    $dataKisim = substr($jsonVeri, $dataStart, $dataEnd - $dataStart);
    
    // Her obje için işlem yap
    $objStart = 0;
    
    while ($objStart < strlen($dataKisim)) {
        $objStart = strpos($dataKisim, '{', $objStart);
        if ($objStart === false) break;
        
        $objEnd = strpos($dataKisim, '}', $objStart);
        if ($objEnd === false) break;
        
        $currentObj = substr($dataKisim, $objStart, $objEnd - $objStart + 1);
        
        // Tüm değişkenleri tanımla ve JSON'dan çıkar
        $musteriAdi = extractFieldFromJsonOrders($currentObj, "prosapSozlesmeAdiSoyadi");
        $prosapSozlesme = extractFieldFromJsonOrders($currentObj, "prosapSozlesme");
        $contractId = extractFieldFromJsonOrders($currentObj, "orderIdContract");
        $teslimTarih = extractFieldFromJsonOrders($currentObj, "purchaseInvoiceDate");
        $urunAdi = extractFieldFromJsonOrders($currentObj, "productName");
        $malzemeKodu = extractFieldFromJsonOrders($currentObj, "productId");
        $urunAdet = extractFieldFromJsonOrders($currentObj, "orderLineQuantity");
        $orderStatus = extractFieldFromJsonOrders($currentObj, "orderStatus");
        
        // Content_API için tüm alanlar
        $orderId = extractFieldFromJsonOrders($currentObj, "orderId");
        $orderLineId = extractFieldFromJsonOrders($currentObj, "orderLineId");
        $orderDate1 = extractFieldFromJsonOrders($currentObj, "orderDate1");
        $orderDate2 = extractFieldFromJsonOrders($currentObj, "orderDate2");
        $deliveryDate = extractFieldFromJsonOrders($currentObj, "deliveryDate");
        $fromLocationId = extractFieldFromJsonOrders($currentObj, "fromLocationId");
        $plant = extractFieldFromJsonOrders($currentObj, "plant");
        $orderType = extractFieldFromJsonOrders($currentObj, "orderType");
        $orderTypeTxt = extractFieldFromJsonOrders($currentObj, "orderTypeTxt");
        $storageLocation = extractFieldFromJsonOrders($currentObj, "storageLocation");
        $orderCustName = extractFieldFromJsonOrders($currentObj, "orderCustName");
        $orderCustTelf = extractFieldFromJsonOrders($currentObj, "orderCustTelf");
        $orderCust = extractFieldFromJsonOrders($currentObj, "orderCust");
        $partnerNumber = extractFieldFromJsonOrders($currentObj, "partnerNumber");
        $partnerName = extractFieldFromJsonOrders($currentObj, "partnerName");
        $eanCode = extractFieldFromJsonOrders($currentObj, "eanCode");
        $specConf = extractFieldFromJsonOrders($currentObj, "specConf");
        $meins = extractFieldFromJsonOrders($currentObj, "meins");
        $priceListCode = extractFieldFromJsonOrders($currentObj, "priceListCode");
        $toLocationId = extractFieldFromJsonOrders($currentObj, "toLocationId");
        $salesOrg = extractFieldFromJsonOrders($currentObj, "salesOrg");
        $salesDist = extractFieldFromJsonOrders($currentObj, "salesDist");
        $custAccGr = extractFieldFromJsonOrders($currentObj, "custAccGr");
        $custAccTxt = extractFieldFromJsonOrders($currentObj, "custAccTxt");
        $netPrice = extractFieldFromJsonOrders($currentObj, "netPrice");
        $waerk = extractFieldFromJsonOrders($currentObj, "waerk");
        $originalPrice = extractFieldFromJsonOrders($currentObj, "originalPrice");
        $originalDiscount = extractFieldFromJsonOrders($currentObj, "originalDiscount");
        $orderCreateName = extractFieldFromJsonOrders($currentObj, "orderCreateName");
        $requestedDeliveryDate = extractFieldFromJsonOrders($currentObj, "requestedDeliveryDate");
        $purchaseInvoiceNo = extractFieldFromJsonOrders($currentObj, "purchaseInvoiceNo");
        $vat = extractFieldFromJsonOrders($currentObj, "vat");
        $vatInclude = extractFieldFromJsonOrders($currentObj, "vatInclude");
        $odemeKosulu = extractFieldFromJsonOrders($currentObj, "odemeKosulu");
        
        // Verileri temizle ve formatla
        $musteriAdi = turkceTemizleOrders($musteriAdi);
        $teslimTarih = tarihFormatlaOrders($teslimTarih);
        $urunAdi = productNameTemizleOrders($urunAdi);
        $malzemeKodu = productIdFormatlaOrders($malzemeKodu);
        $urunAdet = miktarFormatlaOrders($urunAdet);      
  
        try {
            // 1. MUSTERILER tablosuna kaydet (eski mantık)
            if (!empty($musteriAdi)) {
                $stmt = $pdo->prepare("SELECT prosapSozlesme FROM musteriler WHERE musteri_adi = ?");
                $stmt->execute([$musteriAdi]);
                $result = $stmt->fetch();
                
                if (!$result) {
                    // Yeni kayıt
                    $stmt = $pdo->prepare("INSERT INTO musteriler (musteri_adi, prosapSozlesme) VALUES (?, ?)");
                    if ($stmt->execute([$musteriAdi, $prosapSozlesme])) {
                        $yeniKayit++;
                    } else {
                        $hataSayisi++;
                    }
                } else {
                    // Güncelleme (sadece prosapSozlesme boşsa)
                    if (empty(trim($result['prosapSozlesme'])) && !empty($prosapSozlesme)) {
                        $stmt = $pdo->prepare("UPDATE musteriler SET prosapSozlesme = ? WHERE musteri_adi = ?");
                        if ($stmt->execute([$prosapSozlesme, $musteriAdi])) {
                            $guncelleme++;
                        } else {
                            $hataSayisi++;
                        }
                    }
                }
            }
            
            // 2. CONTENT_API tablosuna kaydet (tam veri)
            if (!empty($orderId) && !empty($orderLineId) && !empty($malzemeKodu)) {
                $stmt = $pdo->prepare("SELECT purchaseInvoiceDate, orderStatus, prosapSozlesme FROM content_api WHERE orderId = ? AND orderLineId = ? AND productId = ?");
                $stmt->execute([$orderId, $orderLineId, $malzemeKodu]);
                $result = $stmt->fetch();
                
                if (!$result) {
                    // Yeni kayıt ekle - Tüm alanlar
                    $insertSql = "INSERT INTO content_api (
                        orderId, orderLineId, orderDate1, orderDate2, deliveryDate, fromLocationId, plant, 
                        orderType, orderTypeTxt, orderIdContract, storageLocation, orderCustName, orderCustTelf, 
                        orderCust, partnerNumber, partnerName, eanCode, productId, productName, specConf, meins, 
                        orderLineQuantity, priceListCode, toLocationId, salesOrg, salesDist, custAccGr, custAccTxt, 
                        netPrice, waerk, originalPrice, originalDiscount, orderCreateName, requestedDeliveryDate, 
                        orderStatus, contractId, purchaseInvoiceNo, purchaseInvoiceDate, vat, vatInclude, 
                        prosapSozlesme, prosapSozlesmeAdiSoyadi, odemeKosulu
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                    
                    $stmt = $pdo->prepare($insertSql);
                    if ($stmt->execute([
                        $orderId, $orderLineId, $orderDate1, $orderDate2, $deliveryDate, $fromLocationId, $plant,
                        $orderType, $orderTypeTxt, $contractId, $storageLocation, $orderCustName, $orderCustTelf,
                        $orderCust, $partnerNumber, $partnerName, $eanCode, $malzemeKodu, $urunAdi, $specConf, $meins,
                        $urunAdet, $priceListCode, $toLocationId, $salesOrg, $salesDist, $custAccGr, $custAccTxt,
                        $netPrice, $waerk, $originalPrice, $originalDiscount, $orderCreateName, $requestedDeliveryDate,
                        $orderStatus, $contractId, $purchaseInvoiceNo, $teslimTarih, $vat, $vatInclude,
                        $prosapSozlesme, $musteriAdi, $odemeKosulu
                    ])) {
                        $contentApiYeni++;
                    } else {
                        $hataSayisi++;
                    }
                } else {
                    // ASP mantığı: orderStatus, purchaseInvoiceDate ve prosapSozlesme güncelle (boş olma kontrolü YOK!)
                    $needsUpdate = false;
                    $updateFields = [];
                    $updateValues = [];
                    
                    if (trim($result['orderStatus']) !== $orderStatus && !empty($orderStatus)) {
                        $updateFields[] = "orderStatus = ?";
                        $updateValues[] = $orderStatus;
                        $needsUpdate = true;
                    }
                    
                    if (trim($result['purchaseInvoiceDate']) !== $teslimTarih && !empty($teslimTarih)) {
                        $updateFields[] = "purchaseInvoiceDate = ?";
                        $updateValues[] = $teslimTarih;
                        $needsUpdate = true;
                    }
                    
                    if (trim($result['prosapSozlesme']) !== $prosapSozlesme && !empty($prosapSozlesme)) {
                        $updateFields[] = "prosapSozlesme = ?";
                        $updateValues[] = $prosapSozlesme;
                        $needsUpdate = true;
                    }
                    
                    if ($needsUpdate) {
                        $updateValues[] = $orderId;
                        $updateValues[] = $orderLineId;
                        $updateValues[] = $malzemeKodu;
                        
                        $updateSql = "UPDATE content_api SET " . implode(", ", $updateFields) . " WHERE orderId = ? AND orderLineId = ? AND productId = ?";
                        $stmt = $pdo->prepare($updateSql);
                        if ($stmt->execute($updateValues)) {
                            $contentApiGuncelleme++;
                        } else {
                            $hataSayisi++;
                        }
                    }
                }
            }
            
        } catch (PDOException $e) {
            error_log("Orders database error: " . $e->getMessage());
            $hataSayisi++;
        }
        
        $objStart = $objEnd + 1;
    }
    
    // Bugünün tarihi
    $bugun = date('Y-m-d');
    
    // Sonuç mesajı oluştur (ASP formatında)
    $sonuc = "Musteriler: Yeni=$yeniKayit, Guncelleme=$guncelleme, Hata=$hataSayisi | ";
    $sonuc .= "ContentAPI: Yeni=$contentApiYeni, Guncelleme=$contentApiGuncelleme\n\n";
    $sonuc .= "Detaylı rapor için https://" . REPORT_DOMAIN . "/ApiRapor?tarih=" . $bugun;
    
    if ($hataSayisi > 0) {
        $sonuc .= "\nHata: " . $hataSayisi;
    }
    
    return $sonuc;
}

/**
 * JSON'dan alan değeri çıkarma (string işlemleri ile - ASP mantığı) - Siparişler için
 * @param string $jsonObj JSON object string
 * @param string $fieldName Alan adı
 * @return string Çıkarılan değer
 */
function extractFieldFromJsonOrders($jsonObj, $fieldName) {
    $aramaMetni = '"' . $fieldName . '":"';
    $pos1 = strpos($jsonObj, $aramaMetni);
    
    if ($pos1 !== false) {
        $pos1 = $pos1 + strlen($aramaMetni);
        $pos2 = strpos($jsonObj, '"', $pos1);
        if ($pos2 !== false && $pos2 > $pos1) {
            return substr($jsonObj, $pos1, $pos2 - $pos1);
        }
    }
    
    return "";
}

?>