# Connect API - Fabrika Veri Entegrasyonu

Fabrika API'sinden sipariş ve sevkiyat verilerini otomatik olarak çeken ve veritabanına kaydeden modüler PHP sistemi.

## 🎯 Ne Yapar?

### 📦 Sipariş Modülü (Orders)
- **Kaynak**: `/api/SapDealer/GetOrders` endpoint'i
- **Veri**: Son 7 günün açık siparişleri
- **Hedef**: `musteriler` + `content_api` tabloları
- **Endpoint**: `n8n-auto-orders.php`
- **Bağımsız**: Kendi token yönetimi ve veritabanı bağlantısı

### 🚚 Sevkiyat Modülü (Shipments)  
- **Kaynak**: `/api/SapDealer/GetShipments` endpoint'i
- **Veri**: Son 7 günün sevkiyat bilgileri
- **Hedef**: `sevkiyatlar_api` tablosu
- **Endpoint**: `n8n-auto-shipments.php`
- **Bağımsız**: Kendi token yönetimi ve veritabanı bağlantısı

## 🔄 Veri Akışı

```
Fabrika API → Token Alma → Paralel Veri Çekme → Veritabanı Kayıt → N8N Response
```

1. **Token Alma**: Her modül kendi token'larını yönetir
2. **Paralel API Çağrıları**: Tüm bayilerden eş zamanlı veri çekme (ASP benzeri hız)
3. **Veri Birleştirme**: Bayilerden gelen veriler birleştirilir
4. **Veritabanı İşlemi**: Yeni kayıt veya mevcut kayıt güncelleme
5. **N8N Entegrasyonu**: JSON response ile otomatik işlem

## 📁 Modüler Dosya Yapısı

```
connectapi/
├── 🔧 Ortak Altyapı
│   ├── config-only.php       # .env okuma + bayi bilgileri
│   ├── get-token.php         # Merkezi token yöneticisi
│   ├── security-helper.php   # Güvenlik fonksiyonları
│   ├── .env                  # Hassas bilgiler (GitHub'a gönderilmez)
│   └── .env.example          # Konfigürasyon şablonu
│
├── 📦 Orders Modülü (Bağımsız)
│   ├── helper-orders.php     # Token yönetimi + API çağrıları
│   ├── database-orders.php   # Veritabanı işlemleri
│   └── n8n-auto-orders.php   # N8N endpoint'i
│
└── 🚚 Shipments Modülü (Bağımsız)
    ├── helper-shipments.php  # Token yönetimi + API çağrıları
    ├── database-shipments.php # Veritabanı işlemleri
    └── n8n-auto-shipments.php # N8N endpoint'i
```

## 🗄️ Veritabanı Güncelleme Mantığı

### MUSTERILER Tablosu
- **Güncellenen Alan**: `prosapSozlesme`
- **Koşul**: Sadece boş ise doldur
- **Mantık**: Eksik veri tamamlama

### CONTENT_API Tablosu (Siparişler)
- **Güncellenen Alanlar**: `orderStatus`, `purchaseInvoiceDate`, `prosapSozlesme`
- **Koşul**: Farklı ise güncelle
- **Mantık**: Sipariş durumu değişikliklerini takip

### SEVKIYATLAR_API Tablosu
- **Güncellenen Alanlar**: `shipmentVehicleDriverName`, `shipmentVehicleLicensePlate`, `actualGoodsMovementDate`
- **Koşul**: Farklı ise güncelle
- **Mantık**: Sevkiyat detayları güncellemesi

## 🚀 Kullanım

### Sipariş Verisi Çekme
```bash
GET https://your-domain.com/connectapi/n8n-auto-orders.php
```

### Sevkiyat Verisi Çekme
```bash
GET https://your-domain.com/connectapi/n8n-auto-shipments.php
```

### Token Yönetimi (Opsiyonel)
```bash
# Tüm bayiler için token al
GET https://your-domain.com/connectapi/get-token.php?mode=all

# Tek bayi için token al
GET https://your-domain.com/connectapi/get-token.php
```

### Yanıt Formatı
```json
{
  "success": true,
  "message": "Veriler başarıyla alındı ve veritabanına kaydedildi",
  "date_range": "2025-10-11 - 2025-10-21",
  "database_result": "Yeni=150, Guncelleme=25",
  "bayi_count": 3,
  "raw_data": {...}
}
```

## 🔒 Güvenlik Özellikleri

- **Hassas Veri Koruması**: Tüm kritik bilgiler .env dosyasında
- **Rate Limiting**: Saatte 5 istek (N8N için optimize)
- **Token Yönetimi**: 55 dakika geçerlilik, otomatik yenileme
- **SQL Injection Koruması**: PDO prepared statements
- **XSS Koruması**: Girdi temizleme ve doğrulama
- **Modüler İzolasyon**: Her modül kendi token'larını yönetir
- **Hata Yönetimi**: Detaylı loglama, güvenli hata mesajları

## ⚙️ Kurulum ve Konfigürasyon

### 1. Projeyi Klonla
```bash
git clone <repository-url>
cd connectapi
```

### 2. Bağımlılıkları Yükle
```bash
composer install
```

### 3. Konfigürasyon Dosyası Oluştur
```bash
cp .env.example .env
```

### 4. .env Dosyasını Düzenle
```env
# Veritabanı
DB_HOST=your_database_host
DB_USERNAME=your_database_username
DB_PASSWORD=your_database_password
DB_NAME=your_database_name

# API
API_BASE_URL=https://your-api-domain.com
API_CLIENT_SECRET=your_client_secret

# Bayiler
BAYI_0=username1|password1|orderer1|Bayi Adı 1
BAYI_1=username2|password2|orderer2|Bayi Adı 2
BAYI_2=username3|password3|orderer3|Bayi Adı 3

# Aktif bayi sayısı
AKTIF_BAYI_SAYISI=3

# Rapor domain'i
REPORT_DOMAIN=your-report-domain.com
```

### 5. Web Sunucusuna Yükle
- Dosyaları web sunucusuna yükle
- `.env` dosyasının web'den erişilemediğinden emin ol
- PHP 7.4+ ve gerekli uzantıların yüklü olduğunu kontrol et

## 🎯 Performans Optimizasyonları

- **Paralel API Çağrıları**: curl_multi ile eş zamanlı bayi sorguları
- **ASP Benzeri Hız**: Optimized cURL ayarları (15s timeout, HTTP/1.1)
- **Memory Efficient**: Optimized JSON parsing ve veri işleme
- **Connection Pooling**: HTTP/1.1 keep-alive bağlantıları
- **Timeout Yönetimi**: Akıllı timeout ayarları (token: 10s, API: 15s)

## 📊 Özellikler

### ✅ Mevcut
- ✅ Modüler mimari (Orders + Shipments)
- ✅ Çoklu bayi desteği (3 bayi)
- ✅ Otomatik token yönetimi
- ✅ Paralel API çağrıları
- ✅ Güvenli .env konfigürasyonu
- ✅ N8N entegrasyonu
- ✅ Detaylı raporlama

### 🔮 Gelecek Özellikler
- 🔄 Stok verisi entegrasyonu
- 🔄 Müşteri verisi senkronizasyonu
- 🔄 Ürün katalog güncellemeleri
- 🔄 Webhook desteği
- 🔄 Dashboard arayüzü

## 🤝 Katkıda Bulunma

1. Fork yapın
2. Feature branch oluşturun (`git checkout -b feature/amazing-feature`)
3. Commit yapın (`git commit -m 'Add amazing feature'`)
4. Push yapın (`git push origin feature/amazing-feature`)
5. Pull Request açın

## 📄 Lisans

Bu proje MIT lisansı altında lisanslanmıştır.