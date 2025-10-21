<?php
/**
 * ==================================================
 * SEVKİYAT VERİTABANI KAYIT SİSTEMİ
 * vt-kayit-sevkiyat.asp'den dönüştürülmüş
 * ==================================================
 */

/**
 * .env dosyasından veritabanı bilgilerini yükle
 */
function loadEnvFileShipment($filePath = '.env') {
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
loadEnvFileShipment(__DIR__ . '/.env');

/**
 * Veritabanı bağlantı bilgileri (.env dosyasından)
 */
$shipment_host = $_ENV['DB_HOST'] ?? 'localhost';
$shipment_dbuser = $_ENV['DB_USERNAME'] ?? '';
$shipment_dbsifre = $_ENV['DB_PASSWORD'] ?? '';
$shipment_db = $_ENV['DB_NAME'] ?? '';
$shipment_charset = $_ENV['DB_CHARSET'] ?? 'utf8';

/**
 * PDO veritabanı bağlantısı oluştur (Sevkiyat için)
 * @return PDO|null Veritabanı bağlantısı veya null
 */
function getDatabaseConnectionShipment() {
    global $shipment_host, $shipment_dbuser, $shipment_dbsifre, $shipment_db, $shipment_charset;
    
    try {
        $dsn = "mysql:host=$shipment_host;dbname=$shipment_db;charset=$shipment_charset";
        $pdo = new PDO($dsn, $shipment_dbuser, $shipment_dbsifre, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES $shipment_charset COLLATE {$shipment_charset}_turkish_ci"
        ]);
        return $pdo;
    } catch (PDOException $e) {
        error_log("Shipment database connection error: " . $e->getMessage());
        return null;
    }
}

/**
 * Türkçe karakter temizleme (Sevkiyat için)
 * @param string $metin Temizlenecek metin
 * @return string Temizlenmiş metin
 */
function turkceTemizleShipment($metin) {
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
 * Tarih formatı dönüştürme (20250929 -> 29.09.2025) - Sevkiyat için
 * @param string $tarihStr Tarih string
 * @return string Formatlanmış tarih
 */
function tarihFormatlaShipment($tarihStr) {
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
 * Miktar formatı (2,000 -> 2) - Sevkiyat için
 * @param string $miktarStr Miktar string
 * @return string Formatlanmış miktar
 */
function miktarFormatlaShipment($miktarStr) {
    $temizMiktar = trim($miktarStr);
    
    // Virgülden sonrasını kaldır
    $virgulPos = strpos($temizMiktar, ",");
    if ($virgulPos !== false) {
        $temizMiktar = substr($temizMiktar, 0, $virgulPos);
    }
    
    return $temizMiktar;
}

/**
 * Malzeme numarası formatı (000000003200420184 -> 3200420184)
 */
function malzemeNoFormatla($malzemeStr) {
    $temizMalzeme = trim($malzemeStr);
    
    if (empty($temizMalzeme) || $temizMalzeme === "null") {
        return "";
    }
    
    // Başındaki sıfırları temizle
    $temizMalzeme = ltrim($temizMalzeme, "0");
    
    // Eğer tamamen sıfırlardan oluşuyorsa "0" döndür
    if (empty($temizMalzeme)) {
        return "0";
    }
    
    return $temizMalzeme;
}

/**
 * SQL güvenlik temizleme
 */
function sqlTemizle($metin) {
    $sonuc = trim($metin);
    return $sonuc; // PDO prepared statements kullandığımız için ek escape gerekmez
}

/**
 * Ürün adı temizleme (büyük harf + Türkçe karakter temizleme)
 */
function urunAdiTemizleShipment($metin) {
    $sonuc = trim($metin);
    
    if (empty($sonuc) || $sonuc === "null") {
        return "";
    }
    
    // Önce büyük harfe çevir
    $sonuc = strtoupper($sonuc);
    
    // Türkçe karakterleri temizle
    $sonuc = str_replace(["ç", "Ç"], "C", $sonuc);
    $sonuc = str_replace(["ğ", "Ğ"], "G", $sonuc);
    $sonuc = str_replace(["ı", "İ"], "I", $sonuc);
    $sonuc = str_replace(["ö", "Ö"], "O", $sonuc);
    $sonuc = str_replace(["ş", "Ş"], "S", $sonuc);
    $sonuc = str_replace(["ü", "Ü"], "U", $sonuc);
    
    // Özel karakterleri temizle
    $sonuc = str_replace("'", "", $sonuc);     // Tek tırnak -> sil
    $sonuc = str_replace("+", "-", $sonuc);    // Artı -> tire
    $sonuc = str_replace("%", "", $sonuc);     // Yüzde -> sil
    
    return $sonuc;
}

/**
 * Sevkiyat kaydet - ASP mantığını tam olarak kopyalar
 */
function sevkiyatKaydet($jsonVeri) {
    if (empty($jsonVeri)) {
        return "JSON boş";
    }
    
    $pdo = getDatabaseConnectionShipment();
    if (!$pdo) {
        return "Veritabanı bağlantısı kurulamadı";
    }
    
    $yeniKayit = 0;
    $guncelleme = 0;
    $hataSayisi = 0;
    
    // JSON'dan veri çıkar
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
        
        // Sevkiyat alanlarını çıkar
        $documentNumber = extractJsonFieldShipment($currentObj, "documentNumber");
        $deliveryItemLine = extractJsonFieldShipment($currentObj, "deliveryItemLine");
        $documanetDate = extractJsonFieldShipment($currentObj, "documanetDate");
        $actualGoodsMovementDate = extractJsonFieldShipment($currentObj, "actualGoodsMovementDate");
        $storageLocation = extractJsonFieldShipment($currentObj, "storageLocation");
        $distributionDocumentNumber = extractJsonFieldShipment($currentObj, "distributionDocumentNumber");
        $invoceNumber = extractJsonFieldShipment($currentObj, "invoceNumber");
        $referenceDocumentNumber = extractJsonFieldShipment($currentObj, "referenceDocumentNumber");
        $referenceItemNumber = extractJsonFieldShipment($currentObj, "referenceItemNumber");
        $ean = extractJsonFieldShipment($currentObj, "ean");
        $materialNumber = extractJsonFieldShipment($currentObj, "materialNumber");
        $materialName = extractJsonFieldShipment($currentObj, "materialName");
        $materialQuantity = extractJsonFieldShipment($currentObj, "materialQuantity");
        $materialUnit = extractJsonFieldShipment($currentObj, "materialUnit");
        $materialVolume = extractJsonFieldShipment($currentObj, "materialVolume");
        $materialVolumeUnit = extractJsonFieldShipment($currentObj, "materialVolumeUnit");
        $productPackages = extractJsonFieldShipment($currentObj, "productPackages");
        $shipmentVehicleLicensePlate = extractJsonFieldShipment($currentObj, "shipmentVehicleLicensePlate");
        $shipmentVehicleDriverName = extractJsonFieldShipment($currentObj, "shipmentVehicleDriverName");
        $waybillNumber = extractJsonFieldShipment($currentObj, "waybillNumber");
        $receiver = extractJsonFieldShipment($currentObj, "receiver");
        $proSapContractNo = extractJsonFieldShipment($currentObj, "proSapContractNo");
        $proSapContractItemNo = extractJsonFieldShipment($currentObj, "proSapContractItemNo");
        $customerFullName = extractJsonFieldShipment($currentObj, "customerFullName");
        $customerNo = extractJsonFieldShipment($currentObj, "customerNo");
        
        // Verileri temizle ve formatla
        $documentNumber = sqlTemizle($documentNumber);
        $deliveryItemLine = sqlTemizle($deliveryItemLine);
        $documanetDate = tarihFormatlaShipment($documanetDate);
        $actualGoodsMovementDate = tarihFormatlaShipment($actualGoodsMovementDate);
        $storageLocation = sqlTemizle($storageLocation);
        $distributionDocumentNumber = sqlTemizle($distributionDocumentNumber);
        $invoceNumber = sqlTemizle($invoceNumber);
        $referenceDocumentNumber = sqlTemizle($referenceDocumentNumber);
        $referenceItemNumber = sqlTemizle($referenceItemNumber);
        $ean = sqlTemizle($ean);
        $materialNumber = malzemeNoFormatla($materialNumber);
        $materialName = urunAdiTemizleShipment($materialName);
        $materialQuantity = miktarFormatlaShipment($materialQuantity);
        $materialUnit = sqlTemizle($materialUnit);
        $materialVolume = sqlTemizle($materialVolume);
        $materialVolumeUnit = sqlTemizle($materialVolumeUnit);
        $productPackages = sqlTemizle($productPackages);
        $shipmentVehicleLicensePlate = sqlTemizle($shipmentVehicleLicensePlate);
        $shipmentVehicleDriverName = turkceTemizleShipment($shipmentVehicleDriverName);
        $waybillNumber = sqlTemizle($waybillNumber);
        $receiver = turkceTemizleShipment($receiver);
        $proSapContractNo = sqlTemizle($proSapContractNo);
        $proSapContractItemNo = sqlTemizle($proSapContractItemNo);
        $customerFullName = turkceTemizleShipment($customerFullName);
        $customerNo = sqlTemizle($customerNo);
        
        try {
            // Sevkiyatlar_api tablosuna kaydet
            if (!empty($documentNumber) && !empty($deliveryItemLine) && !empty($materialNumber)) {
                $stmt = $pdo->prepare("SELECT id, shipmentVehicleDriverName, shipmentVehicleLicensePlate, actualGoodsMovementDate FROM sevkiyatlar_api WHERE documentNumber = ? AND deliveryItemLine = ? AND materialNumber = ?");
                $stmt->execute([$documentNumber, $deliveryItemLine, $materialNumber]);
                $result = $stmt->fetch();
                
                if (!$result) {
                    // Yeni kayıt ekle
                    $insertSql = "INSERT INTO sevkiyatlar_api (
                        documentNumber, deliveryItemLine, documanetDate, actualGoodsMovementDate,
                        storageLocation, distributionDocumentNumber, invoceNumber, referenceDocumentNumber,
                        referenceItemNumber, ean, materialNumber, materialName, materialQuantity,
                        materialUnit, materialVolume, materialVolumeUnit, productPackages,
                        shipmentVehicleLicensePlate, shipmentVehicleDriverName, waybillNumber,
                        receiver, proSapContractNo, proSapContractItemNo, customerFullName, customerNo,
                        kayit_tarihi
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
                    
                    $stmt = $pdo->prepare($insertSql);
                    if ($stmt->execute([
                        $documentNumber, $deliveryItemLine, $documanetDate, $actualGoodsMovementDate,
                        $storageLocation, $distributionDocumentNumber, $invoceNumber, $referenceDocumentNumber,
                        $referenceItemNumber, $ean, $materialNumber, $materialName, $materialQuantity,
                        $materialUnit, $materialVolume, $materialVolumeUnit, $productPackages,
                        $shipmentVehicleLicensePlate, $shipmentVehicleDriverName, $waybillNumber,
                        $receiver, $proSapContractNo, $proSapContractItemNo, $customerFullName, $customerNo
                    ])) {
                        $yeniKayit++;
                    } else {
                        $hataSayisi++;
                    }
                } else {
                    // Mevcut kayıt var, sadece belirli alanları güncelle
                    $guncellenecek = false;
                    $updateFields = [];
                    $updateValues = [];
                    
                    if (trim($result['shipmentVehicleDriverName']) !== $shipmentVehicleDriverName && !empty($shipmentVehicleDriverName)) {
                        $updateFields[] = "shipmentVehicleDriverName = ?";
                        $updateValues[] = $shipmentVehicleDriverName;
                        $guncellenecek = true;
                    }
                    
                    if (trim($result['shipmentVehicleLicensePlate']) !== $shipmentVehicleLicensePlate && !empty($shipmentVehicleLicensePlate)) {
                        $updateFields[] = "shipmentVehicleLicensePlate = ?";
                        $updateValues[] = $shipmentVehicleLicensePlate;
                        $guncellenecek = true;
                    }
                    
                    if (trim($result['actualGoodsMovementDate']) !== $actualGoodsMovementDate && !empty($actualGoodsMovementDate)) {
                        $updateFields[] = "actualGoodsMovementDate = ?";
                        $updateValues[] = $actualGoodsMovementDate;
                        $guncellenecek = true;
                    }
                    
                    if ($guncellenecek) {
                        $updateValues[] = $documentNumber;
                        $updateValues[] = $deliveryItemLine;
                        $updateValues[] = $materialNumber;
                        
                        $updateSql = "UPDATE sevkiyatlar_api SET " . implode(", ", $updateFields) . " WHERE documentNumber = ? AND deliveryItemLine = ? AND materialNumber = ?";
                        $stmt = $pdo->prepare($updateSql);
                        if ($stmt->execute($updateValues)) {
                            $guncelleme++;
                        } else {
                            $hataSayisi++;
                        }
                    }
                }
            }
            
        } catch (PDOException $e) {
            error_log("Sevkiyat database error: " . $e->getMessage());
            $hataSayisi++;
        }
        
        $objStart = $objEnd + 1;
    }
    
    // Bugünün tarihi
    $bugun = date('Y-m-d');
    
    // Sonuç mesajı (ASP formatında)
    $sonucMesaj = "SEVKİYATLAR\n" .
                  "Yeni Eklenen: " . $yeniKayit . "\n" .
                  "Güncellenen: " . $guncelleme . "\n\n" .
                  "Detaylı rapor için https://" . REPORT_DOMAIN . "/SevkiyatRapor?tarih=" . $bugun;
    
    if ($hataSayisi > 0) {
        $sonucMesaj .= "\nHata: " . $hataSayisi;
    }
    
    return $sonucMesaj;
}

/**
 * JSON field çıkarma fonksiyonu
 */
function extractJsonFieldShipment($jsonObj, $fieldName) {
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