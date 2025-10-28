# 🚀 Hızlı Başlangıç - Chunk Sistemi

## 📌 Ne Zaman Hangi Yöntemi Kullanmalı?

| Gün Sayısı | Yöntem | Timeout Riski |
|------------|--------|---------------|
| 7 gün | Standart (`n8n-auto-*.php`) | ❌ Yok |
| 30-120 gün | **Chunk Sistemi** | ❌ Yok |

---

## 🎯 Sipariş Verisi (Orders)

### Manuel Test (Tarayıcı)

```
# 1. Chunk listesini gör
https://domain.com/connectapi/n8n-auto-orders-orchestrator.php?days=120

# 2. İlk chunk'ı çek
https://domain.com/connectapi/n8n-auto-orders-chunked.php?chunk=0&days=120

# 3. İkinci chunk'ı çek
https://domain.com/connectapi/n8n-auto-orders-chunked.php?chunk=1&days=120
```

### PowerShell ile Otomatik

```powershell
# Domain'inizi değiştirin
$domain = "domain.com"

# 120 gün = 18 chunk
0..17 | ForEach-Object {
    Write-Host "Orders Chunk $_/17 işleniyor..."
    $r = Invoke-RestMethod "https://$domain/connectapi/n8n-auto-orders-chunked.php?chunk=$_&days=120"
    Write-Host "✓ Yeni: $($r.results.total.new), Güncelleme: $($r.results.total.updated), Data: $($r.data_count)"
    Start-Sleep -Seconds 2
}
Write-Host "✅ Tamamlandı!"
```

---

## 🚚 Sevkiyat Verisi (Shipments)

### Manuel Test (Tarayıcı)

```
# 1. Chunk listesini gör
https://domain.com/connectapi/n8n-auto-shipments-orchestrator.php?days=120

# 2. İlk chunk'ı çek
https://domain.com/connectapi/n8n-auto-shipments-chunked.php?chunk=0&days=120

# 3. İkinci chunk'ı çek
https://domain.com/connectapi/n8n-auto-shipments-chunked.php?chunk=1&days=120
```

### PowerShell ile Otomatik

```powershell
# Domain'inizi değiştirin
$domain = "domain.com"

# 120 gün = 18 chunk
0..17 | ForEach-Object {
    Write-Host "Shipments Chunk $_/17 işleniyor..."
    $r = Invoke-RestMethod "https://$domain/connectapi/n8n-auto-shipments-chunked.php?chunk=$_&days=120"
    Write-Host "✓ Yeni: $($r.results.new), Güncelleme: $($r.results.updated), Data: $($r.data_count)"
    Start-Sleep -Seconds 2
}
Write-Host "✅ Tamamlandı!"
```

---

## 🔄 n8n Workflow (Önerilen)

### Orders için Workflow

```
1. Schedule Trigger (her gün 08:00)
   ↓
2. HTTP Request
   URL: https://domain.com/connectapi/n8n-auto-orders-orchestrator.php?days=120
   ↓
3. Split in Batches
   Field: chunks
   Batch Size: 1
   ↓
4. HTTP Request
   URL: {{ $json.url }}
   ↓
5. Wait: 1 second
   ↓
6. Loop back to step 3
```

### Shipments için Workflow

```
1. Schedule Trigger (her gün 09:00)
   ↓
2. HTTP Request
   URL: https://domain.com/connectapi/n8n-auto-shipments-orchestrator.php?days=120
   ↓
3. Split in Batches
   Field: chunks
   Batch Size: 1
   ↓
4. HTTP Request
   URL: {{ $json.url }}
   ↓
5. Wait: 1 second
   ↓
6. Loop back to step 3
```

---

## 📊 Response Formatı

### Başarılı Chunk Response

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
    "updated": 12
  },
  "has_more": true,
  "next_chunk_url": "n8n-auto-orders-chunked.php?chunk=1&days=120",
  "timestamp": "2025-10-26 12:45:32"
}
```

### Son Chunk Response

```json
{
  "success": true,
  "chunk": {
    "current": 17,
    "total": 18,
    "progress": "100%"
  },
  "date_range": "2025-10-20 - 2025-10-26",
  "results": {
    "new": 45,
    "updated": 8
  },
  "has_more": false,
  "next_chunk_url": null,
  "timestamp": "2025-10-26 12:50:12"
}
```

---

## ⚙️ Özelleştirme

### Farklı Gün Aralıkları

```bash
# 30 gün (5 chunk)
?days=30

# 60 gün (9 chunk)
?days=60

# 180 gün (26 chunk)
?days=180

# 365 gün (53 chunk)
?days=365
```

### Chunk Boyutunu Değiştirme

`n8n-auto-*-chunked.php` dosyalarında:

```php
$chunkSize = 7; // 5 veya 10 yapabilirsin
```

---

## 🎯 Mevcut Sistem ile Karşılaştırma

| Özellik | Eski (`n8n-auto-*.php`) | Yeni (Chunk) |
|---------|-------------------------|--------------|
| Veri aralığı | 7 gün | 120+ gün |
| Request sayısı | 1 | 18 |
| Timeout riski | ✅ Var (>7 gün) | ❌ Yok |
| Progress tracking | ❌ Yok | ✅ Var |
| Süre | ~5 saniye | ~90 saniye (18 chunk) |
| n8n loop | Gerekli değil | Gerekli |

---

## ✅ Kontrol Listesi

- [ ] Domain'inizi değiştirin (PowerShell scriptlerde)
- [ ] İlk testi tarayıcıdan yapın (orchestrator URL)
- [ ] PowerShell ile 2-3 chunk test edin
- [ ] n8n workflow'u kurun
- [ ] Schedule trigger ekleyin (günlük/haftalık)
- [ ] İlk tam çalıştırmayı izleyin

---

## 🆘 Sorun Giderme

### "Geçersiz chunk index" Hatası

Chunk sayısından fazla index kullanıyorsun:
```
120 gün = 18 chunk (0-17 arası)
```

### Rate Limiting Hatası

Çok hızlı request atıyorsun. Her chunk arasında 1 saniye bekle:
```powershell
Start-Sleep -Seconds 1
```

### Token Hatası

Bayi tokenları yenilenemiyor. `.env` dosyasını kontrol et.

---

## 📚 Daha Fazla Bilgi

- Detaylı açıklama: [CHUNK-SYSTEM.md](CHUNK-SYSTEM.md)
- Genel bilgi: [README.md](README.md)
