# 🚀 Nasıl Başlarım?

## 1️⃣ Dosyaları Yükle

Tüm dosyaları web sunucunuza yükleyin:
```
/connectapi klasörüne
```

## 2️⃣ .env Dosyasını Oluştur

`.env.example` dosyasını kopyala → `.env` olarak kaydet

Şu bilgileri doldur:
```env
DB_HOST=your_database_host
DB_USERNAME=your_database_username
DB_PASSWORD=your_database_password
DB_NAME=your_database_name

API_BASE_URL=https://your-api-url.com
API_CLIENT_SECRET=your_secret

BAYI_0=username1|password1|orderer1|Bayi Adı 1
BAYI_1=username2|password2|orderer2|Bayi Adı 2
```

## 3️⃣ Test Et

Tarayıcıda aç:
```
https://domain.com/connectapi/n8n-auto-orders-chunked.php?chunk=0&days=7
```

Çalışıyorsa ✅ hazırsın!

---

## 📌 Kullanım

### Seçenek 1: PowerShell (Windows)

```powershell
$domain = "domain.com"

0..17 | ForEach-Object {
    $r = Invoke-RestMethod "https://$domain/connectapi/n8n-auto-orders-chunked.php?chunk=$_&days=120"
    Write-Host "Chunk $_: Yeni=$($r.results.total.new), Data=$($r.data_count)"
    Start-Sleep -Seconds 2
}
```

### Seçenek 2: n8n (Otomatik)

1. **HTTP Request** node ekle
2. URL: `https://domain.com/connectapi/n8n-auto-orders-orchestrator.php?days=120`
3. **Split in Batches** ekle
4. Field: `chunks`
5. **HTTP Request** ekle
6. URL: `{{ $json.url }}`
7. **Wait** 1 saniye
8. Loop

---

## 🎯 Ne Alırsın?

### Orders (Siparişler)
- 120 gün veri
- Timeout yok
- Detaylı sonuçlar

### Shipments (Sevkiyatlar)
- 120 gün veri
- Timeout yok
- Tam data

---

## 📚 Daha Fazla

- Hızlı başlangıç: [QUICK-START.md](QUICK-START.md)
- Detaylı anlatım: [CHUNK-SYSTEM.md](CHUNK-SYSTEM.md)
- Genel bilgi: [README.md](README.md)
