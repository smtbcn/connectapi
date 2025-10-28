# Chunk Sistemi - Timeout Önleme 🚀

## 🎯 Problem
120 günlük veri çekerken tek request çok uzun sürüyor → **Request Timeout**

## ✅ Çözüm: Queue Bazlı Chunk Sistemi

Her request sadece **7 günlük** veri çeker. n8n veya başka bir orchestrator tüm chunk'ları sırayla çağırır.

---

## 📁 Yeni Dosyalar

### 1. `n8n-auto-shipments-chunked.php`
**Her çağrı 1 chunk işler** (timeout olmaz)

**Kullanım:**
```bash
# İlk chunk (0-6 gün)
GET /connectapi/n8n-auto-shipments-chunked.php?chunk=0&days=120

# İkinci chunk (7-13 gün)
GET /connectapi/n8n-auto-shipments-chunked.php?chunk=1&days=120

# Son chunk
GET /connectapi/n8n-auto-shipments-chunked.php?chunk=17&days=120
```

**Response:**
```json
{
  "success": true,
  "chunk": {
    "current": 0,
    "total": 18,
    "progress": "5.6%"
  },
  "date_range": "2024-07-29 - 2024-08-04",
  "results": {
    "new": 207,
    "updated": 0
  },
  "has_more": true,
  "next_chunk_url": "n8n-auto-shipments-chunked.php?chunk=1&days=120",
  "timestamp": "2025-10-26 12:35:42"
}
```

### 2. `n8n-auto-shipments-orchestrator.php`
**Tüm chunk'ların listesini verir** (n8n loop için)

**Kullanım:**
```bash
GET /connectapi/n8n-auto-shipments-orchestrator.php?days=120
```

**Response:**
```json
{
  "success": true,
  "total_days": 120,
  "chunk_size": 7,
  "total_chunks": 18,
  "chunks": [
    {
      "chunk_index": 0,
      "url": "https://domain.com/connectapi/n8n-auto-shipments-chunked.php?chunk=0&days=120"
    },
    {
      "chunk_index": 1,
      "url": "https://domain.com/connectapi/n8n-auto-shipments-chunked.php?chunk=1&days=120"
    }
    // ... 18 chunk
  ]
}
```

---

## 🔄 n8n Workflow Kurulumu

### Yöntem 1: Loop Node (Önerilen)

```
[Schedule Trigger]
    ↓
[HTTP Request: Orchestrator] → orchestrator.php'yi çağır
    ↓
[Split in Batches] → chunks array'ini iterate et
    ↓
[HTTP Request: Chunked] → Her chunk URL'ini çağır
    ↓
[Wait 1 sec] → Chunk'lar arası bekleme
    ↓
[Loop Back] → has_more = true ise devam
```

**n8n Node Ayarları:**

1. **HTTP Request (Orchestrator)**
   - URL: `https://domain.com/connectapi/n8n-auto-shipments-orchestrator.php?days=120`
   - Method: `GET`

2. **Split in Batches**
   - Input: `{{ $json.chunks }}`
   - Batch Size: `1`

3. **HTTP Request (Chunked)**
   - URL: `{{ $json.url }}`
   - Method: `GET`

4. **Wait**
   - Amount: `1 second`

---

### Yöntem 2: Recursive Call (Has More Pattern)

```
[Schedule Trigger]
    ↓
[HTTP Request: Chunk 0] → chunk=0 ile başla
    ↓
[IF: has_more = true?]
    ├─ YES → [HTTP Request: Next Chunk] → next_chunk_url'yi çağır
    │          ↓
    │       [Loop Back]
    │
    └─ NO → [Finish]
```

---

## 🧪 Manuel Test

### Terminal'den Test:

```bash
# 1. Orchestrator'ı çağır (chunk listesini al)
curl "https://domain.com/connectapi/n8n-auto-shipments-orchestrator.php?days=120"

# 2. İlk chunk'ı çağır
curl "https://domain.com/connectapi/n8n-auto-shipments-chunked.php?chunk=0&days=120"

# 3. İkinci chunk'ı çağır
curl "https://domain.com/connectapi/n8n-auto-shipments-chunked.php?chunk=1&days=120"
```

### PowerShell'den Test:

```powershell
# Domain'inizi değiştirin
$domain = "domain.com"

# İlk chunk
Invoke-RestMethod "https://$domain/connectapi/n8n-auto-shipments-chunked.php?chunk=0&days=120"

# Tüm chunk'ları sırayla çağır (18 chunk)
0..17 | ForEach-Object {
    $r = Invoke-RestMethod "https://$domain/connectapi/n8n-auto-shipments-chunked.php?chunk=$_&days=120"
    Write-Host "Chunk $_: Yeni=$($r.results.new), Güncelleme=$($r.results.updated), Data=$($r.data_count)"
    Start-Sleep -Seconds 1
}
```

---

## 📊 Avantajlar

✅ **Timeout Yok**: Her request sadece 7 gün çekiyor
✅ **Progress Tracking**: İlerleme yüzdesi görebiliyorsun
✅ **Hata Yönetimi**: Bir chunk hata verse bile diğerleri devam eder
✅ **Esnek**: `days` parametresi ile 30, 60, 120 gün seçebilirsin
✅ **n8n Uyumlu**: Loop ve Split nodes ile mükemmel çalışır

---

## ⚙️ Konfigürasyon

### Chunk Boyutunu Değiştirme:
`n8n-auto-shipments-chunked.php` içinde:
```php
$chunkSize = 7; // Her chunk 7 gün → 5 veya 10 yapabilirsin
```

### Farklı Gün Aralıkları:
```bash
# 30 gün (5 chunk)
?days=30

# 60 gün (9 chunk)  
?days=60

# 180 gün (26 chunk)
?days=180
```

---

## 🔒 Güvenlik

- Rate limiting: Saatte 100 chunk çağrısı
- Token yönetimi: Her chunk için otomatik token kontrolü
- Hata yönetimi: Geçersiz chunk index kontrolü

---

## 📈 Performans

- **Tek Chunk Süresi**: ~3-5 saniye
- **18 Chunk (120 gün)**: ~54-90 saniye (toplam)
- **Timeout Riski**: ❌ YOK (her request kısa)

---

## 🎯 Özet

| Özellik | Eski Sistem | Yeni Chunk Sistemi |
|---------|-------------|-------------------|
| Request sayısı | 1 (120 gün) | 18 (7'şer gün) |
| Timeout riski | ✅ VAR | ❌ YOK |
| Progress tracking | ❌ YOK | ✅ VAR |
| n8n uyumluluğu | Kısıtlı | ✅ Mükemmel |
| Hata recovery | Zor | ✅ Kolay |
