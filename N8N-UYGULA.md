# 🔄 n8n Workflow Kurulumu

## 📋 İçindekiler
1. [Orders Workflow (Siparişler)](#orders-workflow)
2. [Shipments Workflow (Sevkiyatlar)](#shipments-workflow)
3. [Kurulum Adımları](#kurulum-adımları)
4. [Test ve Sorun Giderme](#test-ve-sorun-giderme)

---

## 🎯 Orders Workflow (Siparişler)

### Workflow Açıklaması

Bu workflow her gün saat **08:00**'de çalışır ve son 120 günlük sipariş verilerini chunk'lar halinde alır.

**Akış:**
```
Schedule Trigger (08:00)
    ↓
HTTP: Orchestrator (chunk listesi al)
    ↓
Split in Batches (18 chunk'a böl)
    ↓
HTTP: Chunk (her chunk'ı çağır)
    ↓
Wait (1 saniye)
    ↓
Loop (tüm chunk'lar bitene kadar)
    ↓
Aggregate (sonuçları topla)
    ↓
Set (özet rapor)
```

### JSON Workflow

n8n'de: **Settings** → **Import from URL or File** → Aşağıdaki JSON'u yapıştır

```json
{
  "name": "Orders API - Chunk System",
  "nodes": [
    {
      "parameters": {
        "rule": {
          "interval": [
            {
              "field": "cronExpression",
              "expression": "0 8 * * *"
            }
          ]
        }
      },
      "id": "schedule-trigger",
      "name": "Schedule Trigger",
      "type": "n8n-nodes-base.scheduleTrigger",
      "typeVersion": 1,
      "position": [250, 300]
    },
    {
      "parameters": {
        "url": "https://domain.com/connectapi/n8n-auto-orders-orchestrator.php?days=120",
        "options": {}
      },
      "id": "http-orchestrator",
      "name": "HTTP: Get Chunks",
      "type": "n8n-nodes-base.httpRequest",
      "typeVersion": 4.1,
      "position": [470, 300]
    },
    {
      "parameters": {
        "batchSize": 1,
        "options": {}
      },
      "id": "split-batches",
      "name": "Split in Batches",
      "type": "n8n-nodes-base.splitInBatches",
      "typeVersion": 3,
      "position": [690, 300]
    },
    {
      "parameters": {
        "url": "={{ $json.url }}",
        "options": {}
      },
      "id": "http-chunk",
      "name": "HTTP: Process Chunk",
      "type": "n8n-nodes-base.httpRequest",
      "typeVersion": 4.1,
      "position": [910, 300]
    },
    {
      "parameters": {
        "amount": 1,
        "unit": "seconds"
      },
      "id": "wait",
      "name": "Wait",
      "type": "n8n-nodes-base.wait",
      "typeVersion": 1,
      "position": [1130, 300]
    },
    {
      "parameters": {
        "aggregate": "aggregateAllItemData",
        "options": {}
      },
      "id": "aggregate",
      "name": "Aggregate",
      "type": "n8n-nodes-base.aggregate",
      "typeVersion": 1,
      "position": [1350, 300]
    },
    {
      "parameters": {
        "mode": "manual",
        "duplicateItem": false,
        "assignments": {
          "assignments": [
            {
              "id": "total_new",
              "name": "total_new",
              "value": "={{ $json.map(item => item.results.total.new).reduce((a, b) => a + b, 0) }}",
              "type": "number"
            },
            {
              "id": "total_updated",
              "name": "total_updated",
              "value": "={{ $json.map(item => item.results.total.updated).reduce((a, b) => a + b, 0) }}",
              "type": "number"
            },
            {
              "id": "chunks_processed",
              "name": "chunks_processed",
              "value": "={{ $json.length }}",
              "type": "number"
            },
            {
              "id": "status",
              "name": "status",
              "value": "SUCCESS",
              "type": "string"
            },
            {
              "id": "message",
              "name": "message",
              "value": "Orders imported successfully - New: {{ $json.map(item => item.results.total.new).reduce((a, b) => a + b, 0) }}, Updated: {{ $json.map(item => item.results.total.updated).reduce((a, b) => a + b, 0) }}",
              "type": "string"
            }
          ]
        },
        "options": {}
      },
      "id": "set-summary",
      "name": "Set Summary",
      "type": "n8n-nodes-base.set",
      "typeVersion": 3.2,
      "position": [1570, 300]
    }
  ],
  "connections": {
    "Schedule Trigger": {
      "main": [
        [
          {
            "node": "HTTP: Get Chunks",
            "type": "main",
            "index": 0
          }
        ]
      ]
    },
    "HTTP: Get Chunks": {
      "main": [
        [
          {
            "node": "Split in Batches",
            "type": "main",
            "index": 0
          }
        ]
      ]
    },
    "Split in Batches": {
      "main": [
        [
          {
            "node": "HTTP: Process Chunk",
            "type": "main",
            "index": 0
          }
        ]
      ]
    },
    "HTTP: Process Chunk": {
      "main": [
        [
          {
            "node": "Wait",
            "type": "main",
            "index": 0
          }
        ]
      ]
    },
    "Wait": {
      "main": [
        [
          {
            "node": "Split in Batches",
            "type": "main",
            "index": 0
          }
        ]
      ]
    },
    "Split in Batches": {
      "main": [
        null,
        [
          {
            "node": "Aggregate",
            "type": "main",
            "index": 0
          }
        ]
      ]
    },
    "Aggregate": {
      "main": [
        [
          {
            "node": "Set Summary",
            "type": "main",
            "index": 0
          }
        ]
      ]
    }
  },
  "pinData": {}
}
```

---

## 🚚 Shipments Workflow (Sevkiyatlar)

### Workflow Açıklaması

Bu workflow her gün saat **09:00**'da çalışır ve son 120 günlük sevkiyat verilerini chunk'lar halinde alır.

**Akış:** Orders ile aynı, sadece URL ve zaman farklı

### JSON Workflow

```json
{
  "name": "Shipments API - Chunk System",
  "nodes": [
    {
      "parameters": {
        "rule": {
          "interval": [
            {
              "field": "cronExpression",
              "expression": "0 9 * * *"
            }
          ]
        }
      },
      "id": "schedule-trigger",
      "name": "Schedule Trigger",
      "type": "n8n-nodes-base.scheduleTrigger",
      "typeVersion": 1,
      "position": [250, 300]
    },
    {
      "parameters": {
        "url": "https://domain.com/connectapi/n8n-auto-shipments-orchestrator.php?days=120",
        "options": {}
      },
      "id": "http-orchestrator",
      "name": "HTTP: Get Chunks",
      "type": "n8n-nodes-base.httpRequest",
      "typeVersion": 4.1,
      "position": [470, 300]
    },
    {
      "parameters": {
        "batchSize": 1,
        "options": {}
      },
      "id": "split-batches",
      "name": "Split in Batches",
      "type": "n8n-nodes-base.splitInBatches",
      "typeVersion": 3,
      "position": [690, 300]
    },
    {
      "parameters": {
        "url": "={{ $json.url }}",
        "options": {}
      },
      "id": "http-chunk",
      "name": "HTTP: Process Chunk",
      "type": "n8n-nodes-base.httpRequest",
      "typeVersion": 4.1,
      "position": [910, 300]
    },
    {
      "parameters": {
        "amount": 1,
        "unit": "seconds"
      },
      "id": "wait",
      "name": "Wait",
      "type": "n8n-nodes-base.wait",
      "typeVersion": 1,
      "position": [1130, 300]
    },
    {
      "parameters": {
        "aggregate": "aggregateAllItemData",
        "options": {}
      },
      "id": "aggregate",
      "name": "Aggregate",
      "type": "n8n-nodes-base.aggregate",
      "typeVersion": 1,
      "position": [1350, 300]
    },
    {
      "parameters": {
        "mode": "manual",
        "duplicateItem": false,
        "assignments": {
          "assignments": [
            {
              "id": "total_new",
              "name": "total_new",
              "value": "={{ $json.map(item => item.results.new).reduce((a, b) => a + b, 0) }}",
              "type": "number"
            },
            {
              "id": "total_updated",
              "name": "total_updated",
              "value": "={{ $json.map(item => item.results.updated).reduce((a, b) => a + b, 0) }}",
              "type": "number"
            },
            {
              "id": "chunks_processed",
              "name": "chunks_processed",
              "value": "={{ $json.length }}",
              "type": "number"
            },
            {
              "id": "status",
              "name": "status",
              "value": "SUCCESS",
              "type": "string"
            },
            {
              "id": "message",
              "name": "message",
              "value": "Shipments imported successfully - New: {{ $json.map(item => item.results.new).reduce((a, b) => a + b, 0) }}, Updated: {{ $json.map(item => item.results.updated).reduce((a, b) => a + b, 0) }}",
              "type": "string"
            }
          ]
        },
        "options": {}
      },
      "id": "set-summary",
      "name": "Set Summary",
      "type": "n8n-nodes-base.set",
      "typeVersion": 3.2,
      "position": [1570, 300]
    }
  ],
  "connections": {
    "Schedule Trigger": {
      "main": [
        [
          {
            "node": "HTTP: Get Chunks",
            "type": "main",
            "index": 0
          }
        ]
      ]
    },
    "HTTP: Get Chunks": {
      "main": [
        [
          {
            "node": "Split in Batches",
            "type": "main",
            "index": 0
          }
        ]
      ]
    },
    "Split in Batches": {
      "main": [
        [
          {
            "node": "HTTP: Process Chunk",
            "type": "main",
            "index": 0
          }
        ]
      ]
    },
    "HTTP: Process Chunk": {
      "main": [
        [
          {
            "node": "Wait",
            "type": "main",
            "index": 0
          }
        ]
      ]
    },
    "Wait": {
      "main": [
        [
          {
            "node": "Split in Batches",
            "type": "main",
            "index": 0
          }
        ]
      ]
    },
    "Split in Batches": {
      "main": [
        null,
        [
          {
            "node": "Aggregate",
            "type": "main",
            "index": 0
          }
        ]
      ]
    },
    "Aggregate": {
      "main": [
        [
          {
            "node": "Set Summary",
            "type": "main",
            "index": 0
          }
        ]
      ]
    }
  },
  "pinData": {}
}
```

---

## 🛠 Kurulum Adımları

### 1. n8n'i Aç

Tarayıcıda n8n'i aç: `http://localhost:5678` (veya cloud URL'in)

### 2. Orders Workflow'unu İçe Aktar

1. Sol üst **+** butonuna tıkla
2. **Import from File** seç
3. Yukarıdaki **Orders JSON**'unu kopyala
4. Bir `.json` dosyasına kaydet (`orders-workflow.json`)
5. Bu dosyayı import et

### 3. Domain'i Değiştir

**HTTP: Get Chunks** node'una tıkla:
- `https://domain.com` → Kendi domain'inle değiştir
- `https://yoursite.com/connectapi/n8n-auto-orders-orchestrator.php?days=120`

### 4. Zamanlamayı Ayarla (Opsiyonel)

**Schedule Trigger** node'una tıkla:
- Cron: `0 8 * * *` (Her gün 08:00)
- İstersen değiştir: `0 10 * * *` (10:00), `0 */6 * * *` (6 saatte bir)

### 5. Aktif Et

Sağ üst köşedeki **Inactive** → **Active** yap

### 6. Shipments İçin Tekrarla

Aynı adımları **Shipments JSON** ile tekrarla

---

## 🧪 Test ve Sorun Giderme

### Manuel Test

1. Workflow'u aç
2. **Schedule Trigger** node'una tıkla
3. **Execute Node** butonuna bas
4. Tüm node'ların yeşil olup olmadığını kontrol et

### Hata Çözümleri

#### ❌ "HTTP Request Failed"

**Sorun:** Domain yanlış veya API erişilemiyor

**Çözüm:**
1. Tarayıcıda URL'i aç: `https://domain.com/connectapi/n8n-auto-orders-orchestrator.php?days=120`
2. JSON response görüyorsan, n8n'de domain'i kontrol et
3. HTTPS sertifika hatası varsa: **HTTP Request** → **Ignore SSL Issues** aktif et

#### ❌ "Split in Batches" Çalışmıyor

**Sorun:** `chunks` field'i bulunamıyor

**Çözüm:**
1. **HTTP: Get Chunks** output'unu incele
2. `chunks` array'i var mı kontrol et
3. **Split in Batches** → Field: `chunks` yazılı mı?

#### ❌ Loop Sonsuza Kadar Dönüyor

**Sorun:** **Split in Batches** loop çıkışı yanlış bağlı

**Çözüm:**
- **Split in Batches** → İkinci output (done) → **Aggregate** olmalı
- İlk output (continue) → **HTTP: Process Chunk** olmalı

#### ❌ Aggregate Hata Veriyor

**Sorun:** Expression syntax hatası

**Çözüm:**
1. **Set Summary** node'unu sil
2. Tekrar ekle ve yukarıdaki expression'ları kopyala

### Başarı Kontrolü

Workflow başarılı çalıştıysa:

**Set Summary** output'u:
```json
{
  "total_new": 850,
  "total_updated": 127,
  "chunks_processed": 18,
  "status": "SUCCESS",
  "message": "Orders imported successfully - New: 850, Updated: 127"
}
```

---

## 📊 İzleme

### Execution Logları

**Executions** sekmesinden:
- Başarılı/başarısız execution'ları gör
- Her chunk'ın sonucunu incele
- Hata detaylarını kontrol et

### Bildirim Ekleme (Opsiyonel)

**Set Summary** sonrasına ekle:

**Başarı Bildirimi:**
- **Slack** / **Email** / **Telegram** node
- Message: `{{ $json.message }}`

**Hata Bildirimi:**
- Error Trigger workflow oluştur
- Hata olduğunda bildirim gönder

---

## 🎯 Özet

✅ **Orders**: Her gün 08:00, 120 gün, 18 chunk
✅ **Shipments**: Her gün 09:00, 120 gün, 18 chunk
✅ **Timeout**: Yok (her chunk ayrı request)
✅ **Otomatik**: Hiç manuel müdahale gerekmez

**İşlem Süresi:** ~2-3 dakika (18 chunk × 5 saniye)

---

## 📚 Ek Kaynaklar

- [BASLA.md](BASLA.md) - Hızlı başlangıç
- [QUICK-START.md](QUICK-START.md) - PowerShell alternatifleri
- [CHUNK-SYSTEM.md](CHUNK-SYSTEM.md) - Teknik detaylar
