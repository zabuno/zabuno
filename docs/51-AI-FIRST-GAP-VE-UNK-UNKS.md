# 51 — AI-First: boşluk analizi, unk-unks ve fazlanmış çekirdek

**Durum:** Analiz + plan. Faz 1 çekirdeği tanımlı, kod yazılmadı.
**Requirement ID:** `AI-CORE-v1`
**İlgili:** `docs/14` (AI-First doktrini), `docs/32` (yetenek matrisi),
`docs/47` (form standardı Kural 10), `docs/49` (medya), `docs/50` (shell)

---

## 1. Boşluk analizi — doktrin var, uygulama yok

Ölçüldü, tahmin edilmedi.

| Katman | Doktrin | Uygulama |
| --- | --- | --- |
| Sağlayıcı/model kayıt defteri | `docs/14` §2 tanımlı | **YOK** |
| feature × model matrisi | `docs/14` §2 tanımlı | **YOK** |
| Tool allowlist / PDP zinciri | `docs/14` §3, §6 tanımlı | **YOK** |
| İnsan onayı (backend zorlaması) | `docs/14` §4 tanımlı | **YOK** |
| Kill switch, cost cap, redaction | `docs/14` §3 tanımlı | **YOK** |
| Eval seti (regresyon) | `docs/14` §3 tanımlı | **YOK** |
| Komut merkezi | `docs/14` §9a — 14 adımlı akış | Kabuk var, **her kontrol devre dışı** |
| Sayfa içi AI eylemleri | `docs/32` 61 modül | **YOK** (boş kart 2026-08-27'de kaldırıldı) |

**AI ile ilgili üretim PHP dosyası sayısı: 0.**

Bu bir kusur değil, bir SIRA meselesiydi: doktrin önce yazıldı. Ama artık
sıra geldi ve şu risk gerçek: doktrin uygulanmadan büyüdükçe, uygulama
geldiğinde doktrini karşılamayan bir kestirme cazip hâle gelir.

### 1.1 Sahibinin eklediği ve doktrinde OLMAYAN üç şey

| Talep | Doktrinde | Sonuç |
| --- | --- | --- |
| **Çoklu HESAP** (Claude 1/2/3, Gemini 1/2/3, OpenAI 1/2/3) | Yok — yalnız sağlayıcı vardı | Yeni: hesap havuzu, kota/limit dağıtımı, sağlıksız hesabın devre dışı kalması |
| **Kullanıcının model seçmesi** | Yok — matris otomatik çözüyordu | Yeni: seçim yüzeyi + matrisin sınırları içinde kalması |
| **Küçük açık kaynak modeller, basit VPS'te yerel** | Yok | Yeni: yerel çıkarım katmanı ve hangi işin oraya gideceği |

---

## 2. Unknown unknowns — sormadığımız için görmediğimiz riskler

Bunlar "bilmediğimizi bilmediğimiz" sınıfından; her biri ürünü sessizce
bozabilir ve hiçbiri bugünkü planda yoktu.

### UNK-01 — Hesap havuzu bir güvenlik SINIRIDIR, yalnız kota değil
Üç Claude hesabını sırayla kullanmak "daha çok kota" gibi görünür. Ama
hesaplar arasında **prompt cache, konuşma geçmişi ve organizasyon ayarları**
paylaşılmaz. Bir tenant'ın isteği bir hesaba, devamı başkasına giderse
önbellek ıskalanır (maliyet artar) ve bazı sağlayıcılarda oturum bağlamı
kaybolur. **Karar: tenant → hesap eşlemesi YAPIŞKAN olmalı**, rastgele değil.

### UNK-02 — Aynı prompt farklı modelde farklı ŞEMA döndürür
"Ürün açıklaması üret" üç modelde üç farklı JSON üretir. Şema doğrulaması
olmadan bu, arayüzde sessiz bozulmadır. **Karar: her yetenek bir JSON
şemasına bağlanır; şemaya uymayan cevap BAŞARISIZ sayılır**, kullanıcıya
gösterilmez.

### UNK-03 — Sağlayıcı çöktüğünde ürün de çökmemeli, ama SESSİZCE de bozulmamalı
Fallback zinciri (Claude → Gemini → yerel) iyi görünür. Tehlikesi: kullanıcı
hangi modelin cevapladığını bilmez ve kalite dalgalanır. **Karar: fallback
görünür olur** — "bu öneri yedek modelden geldi".

### UNK-04 — Menü içeriği KİŞİSEL VERİ içerebilir
"Şef Ayşe'nin özel tarifi", müşteri yorumları, personel adları. Prompt'a
giden her şey sağlayıcıya gider. **Karar: redaction bir Faz 1 işidir**, sonra
eklenecek bir güvenlik katmanı değil.

### UNK-05 — Yerel model, sağlayıcıyla AYNI ARAYÜZ arkasında olmalı
Yerel model "ucuz alternatif" diye ayrı bir yola konursa, iki kod yolu
oluşur ve biri her zaman geride kalır. **Karar: yerel model de bir
sağlayıcıdır**, aynı port arkasında.

### UNK-06 — Maliyet tavanı TENANT başına olmalı, global değil
Global tavan, bir tenant'ın tüketimiyle diğerlerinin AI'sını kapatır.
**Karar: bütçe tenant başına, ve dolduğunda ürün çalışmaya devam eder** —
yalnız AI önerileri durur (`docs/49` §10 ile aynı ilke: kota dolunca canlı
menü kesilmez).

### UNK-07 — Prompt enjeksiyonu MENÜ İÇERİĞİNDEN gelir
Restoran sahibi ürün açıklamasına "önceki talimatları yoksay" yazabilir —
ya da bir saldırgan, halka açık bir alandan. AI o metni özetlerken talimat
sanabilir. **Karar: kullanıcı içeriği prompt'ta VERİ olarak işaretlenir**,
talimat olarak değil.

### UNK-08 — Değerlendirme (eval) olmadan model değişimi kör bir bahistir
"Gemini 3 çıktı, geçelim" demek, ölçüm yoksa kalite kumarıdır. **Karar: her
yetenek için küçük bir altın küme**; model değişimi o kümeden geçmeden
yayınlanmaz.

### UNK-09 — AI çıktısı YAYINLANMIŞ menüye giderse geri alma zorlaşır
Bir AI çevirisi yayınlanır ve QR ile dağıtılırsa, geri alma yalnız veritabanı
işi değildir. **Karar: AI çıktısı taslakta kalır; yayın ayrı ve insan
onaylıdır** (`docs/49` Faz 5 publication snapshot ile aynı omurga).

### UNK-10 — Basit VPS'te yerel model, ASIL işi aç bırakabilir
Aynı sunucuda PHP-FPM, PostgreSQL ve bir LLM. Model bellek ve CPU'yu alırsa
menü sayfası yavaşlar. **Karar: yerel çıkarım ayrı bir süreç ve KAYNAK
SINIRLI**; sınır aşılırsa AI reddedilir, menü etkilenmez.

---

## 3. Faz 1 çekirdeği — LLM altyapısı

Sahibinin kararı: **temeli atmak Faz 1'in konusu.**

### 3.1 Katmanlar

```
Domain/Ai
  Capability          "ürün açıklaması üret" — YETENEK, model değil
  ModelId             sağlayıcı + model adı (yapılandırmadan, koddan değil)
  AiDecision          öneri: bağlam, etkilenen kayıtlar, diff, gerekçe
Application/Ai/Port
  TextGenerationPort  tek arayüz — bulut ve yerel AYNI portun arkasında
  AccountPoolPort     hesap seçimi ve sağlık durumu
  AiBudgetPort        tenant başına bütçe
Infrastructure/Ai
  AnthropicProvider · GeminiProvider · OpenAiProvider · LocalProvider
  StickyAccountPool   tenant → hesap YAPIŞKAN eşlemesi (UNK-01)
  RedactingPrompt     PII prompt'a girmeden temizlenir (UNK-04)
  SchemaValidated     şemaya uymayan cevap başarısızdır (UNK-02)
```

### 3.2 Yapılandırma — `config/ai.php`

```php
'providers' => [
    'anthropic' => [
        'accounts' => [
            ['key' => env('ANTHROPIC_KEY_1'), 'label' => 'claude-1'],
            ['key' => env('ANTHROPIC_KEY_2'), 'label' => 'claude-2'],
            ['key' => env('ANTHROPIC_KEY_3'), 'label' => 'claude-3'],
        ],
        'models' => ['claude-opus-5', 'claude-sonnet-5', 'claude-haiku-4-5-20251001'],
    ],
    'gemini' => ['accounts' => [...], 'models' => [...]],
    'openai' => ['accounts' => [...], 'models' => [...]],
    'local'  => ['endpoint' => env('AI_LOCAL_ENDPOINT'), 'models' => [...]],
],
```

**Model adları YAPILANDIRMADADIR, kodda değil.** Sebebi somut: sağlayıcılar
model katalogunu bizim sürüm döngümüzden bağımsız değiştirir. Kodda sabit bir
model adı, o ad emekliye ayrıldığı gün üretimi durdurur — ve düzeltmesi bir
deploy gerektirir. Yapılandırmada ise bir ortam değişkenidir.

### 3.3 Yetenek × model matrisi

```php
'capabilities' => [
    'product.description' => [
        'preferred' => 'anthropic:claude-haiku-4-5-20251001',
        'fallback'  => ['gemini:...', 'local:...'],
        'schema'    => 'product-description.v1',
        'requires_human_approval' => true,
        'max_input_tokens' => 2000,
    ],
],
```

Kullanıcı model seçebilir — **ama matrisin sınırları içinde.** Bir yetenek
için aday olmayan model seçilemez; aksi hâlde şema uyumu ve maliyet tavanı
anlamsızlaşır.

### 3.4 Faz 1'in kabul ölçütü

1. `TextGenerationPort` var; **hiçbir sağlayıcı bağlı değilken** ürün tam
   çalışıyor.
2. Hesap havuzu yapışkan eşleme yapıyor ve sağlıksız hesabı devre dışı
   bırakıyor.
3. Şemaya uymayan cevap kullanıcıya ULAŞMIYOR.
4. Redaction, prompt'a giden metinden bilinen kişisel alanları çıkarıyor.
5. Kill switch: tek ayarla bütün AI kapanıyor, ürün etkilenmiyor.
6. Tenant başına bütçe; dolunca AI durur, ürün durmaz.
7. Her çağrı denetim kaydı bırakıyor: hangi hesap, hangi model, kaç token,
   kaç kuruş.

**Sağlayıcı anahtarı olmadan geliştirme yapılabilir olmalı**: `local` ya da
sahte sağlayıcı ile tüm zincir çalışır.

---

## 4. Küçük açık kaynak modeller — basit VPS'te ne yapılabilir?

Sahibinin sorusu bu. Dürüst cevap: **çok şey, ama üretken metin değil.**

netcup VPS'te (AMD EPYC, GPU yok) çalışabilecek boyuttaki modeller, CPU
üzerinde saniyeler mertebesinde cevap verir. Bu, kullanıcı beklerken yapılan
işler için fazla yavaştır; **arka planda ve deterministik işler için ise
fazlasıyla yeterlidir.**

| İş | Yerel model uygun mu | Neden |
| --- | --- | --- |
| **Gömme (embedding) ve benzerlik** | ✅ Çok uygun | Küçük modeller, tek geçiş, milisaniyeler. Yinelenen ürün bulma, "benzer görsel", menü içi arama |
| **Sınıflandırma** | ✅ Uygun | "Bu ürün vejetaryen mi", "bu görsel yemek mi logo mu" — kısa çıktı, şema dar |
| **Dil algılama** | ✅ Uygun | Menü içeriğinin dili; çeviri kuyruğunu besler |
| **Alerjen İŞARETLEME (öneri)** | ✅ Uygun | Ürün adından aday alerjen; insan onaylı |
| **OCR / PDF'ten metin** | ✅ Uygun | Tesseract sınıfı araçlar; LLM bile değil |
| **Görsel kalite kontrolü** | ✅ Uygun | Bulanıklık, karanlık, yinelenen — klasik CV, LLM gerekmez |
| **Ürün açıklaması yazma** | ⚠️ Sınırlı | Küçük model yazar ama kalite dalgalanır; öneri olarak sunulabilir |
| **Çeviri** | ⚠️ Sınırlı | Kısa metinlerde iş görür; menü tonunu tutturmakta bulut modelleri belirgin üstün |
| **Serbest sohbet / komut merkezi** | ❌ Uygun değil | Gecikme ve akıl yürütme yetmez |
| **PDF'ten tam menü çıkarma** | ❌ Uygun değil | Uzun bağlam + yapılandırılmış çıktı; bulut modeli işi |

### 4.1 Karar

**Yerel model bir SAĞLAYICIDIR** (UNK-05), ayrı bir yol değil. Aynı
`TextGenerationPort` arkasında durur ve matriste bir aday olarak görünür.

**Kaynak sınırı zorunludur** (UNK-10): ayrı süreç, bellek ve CPU tavanı.
Tavan aşılırsa AI isteği reddedilir — menü sayfası etkilenmez. Bu, "AI
kapalıyken ürün çalışır" ilkesinin altyapı karşılığıdır.

### 4.2 Çerçeve seçimi — hangi fazda ne

| Faz | Çerçeve/araç | Neden o faz |
| --- | --- | --- |
| 1 | Sağlayıcı portu + HTTP istemcisi | Çerçeveye bağımlılık YOK; port kendi kodumuz |
| 3 | Gömme modeli (yerel) + vektör alanı | Yinelenen ürün/görsel bulma; `pgvector` PostgreSQL'de |
| 4 | Klasik CV (görsel kalite) | LLM gerekmez; `vips` zaten gelecek |
| 5 | OCR | PDF'ten menü içe aktarmanın deterministik yarısı |
| 6 | Bulut LLM — yapılandırılmış çıktı | PDF'ten menü çıkarma; şemaya bağlı |
| 7 | Eval seti | Model değişimi ölçülmeden yapılmaz (UNK-08) |

**Laravel'in resmi AI SDK'sı** `docs/14` §5 gereği koşullu adaptör arkasında
kalır; port bizim, adaptör değiştirilebilir.

---

## 5. Faz faz AI — her fazda çekirdek, eklenti değil

Sahibinin talebi: *"her fazda AI ile ilgili neler yapılacak, bu fazda çekirdek
olsun ve entegre edelim."*

| Faz | Ürün işi | **AI çekirdeği** | Determinist yol |
| --- | --- | --- | --- |
| **1** | Medya veri modeli ✅ | **LLM altyapısı**: port, hesap havuzu, matris, redaction, bütçe, kill switch, denetim | AI kapalı — her şey çalışır |
| **2** | Güvenli alım | Yükleme sırasında **alt metin önerisi** (öneri, otomatik değil) | Kullanıcı elle yazar |
| **3** | Sürüm/rendition | **Gömme ile yinelenen görsel** tespiti (yerel model) | Checksum ile birebir yinelenen zaten bulunur |
| **4** | Kütüphane arayüzü | **Akıllı koleksiyon**: "alt metni eksik", "kullanılmayan", "kalitesi düşük" | Elle filtre |
| **5** | Kullanım grafiği + yayın | **Yayın öncesi tarama**: eksik çeviri, eksik görsel, fiyat tutarsızlığı | Deterministik kontrol listesi |
| **6** | Teslim/CDN | **PDF/görselden menü çıkarma** (bulut LLM, şemalı) | Elle giriş |
| **7** | Yönetişim | **Anomali açıklaması**: "bu hafta taramalar %40 düştü, sebebi şu olabilir" | Ham sayılar |
| **8** | Crop stüdyosu | **Akıllı kırpma** — odak noktası önerisi | Elle odak noktası |
| **9** | AI önerileri | **Komut merkezi** `docs/14` §9a 14 adımlı akışıyla | Her ekranın kendi formu |
| **10+** | İleri | Moderasyon, video anlama | — |

**Her satırda değişmez:** AI önerir, insan onaylar, sistem denetler, geri
alınabilir (`docs/47` Kural 10).

---

## 6. Sahibinin kararı gereken noktalar

| # | Karar | Neden |
| --- | --- | --- |
| 1 | Hangi sağlayıcılarda gerçekten hesap var? | Faz 1 yapılandırması; olmayan sağlayıcı için kod yazılmaz |
| 2 | Tenant başına aylık AI bütçesi | Fiyatlandırma; tavansız AI maliyeti öngörülemez |
| 3 | Yerel modeller aynı VPS'te mi, ayrı sunucuda mı? | Aynı sunucuda kaynak sınırı zorunlu (UNK-10) |
| 4 | Restoran sahibi model seçebilecek mi, yoksa biz mi seçelim? | Seçim yüzeyi Faz 1'e girer ya da girmez |
| 5 | AI çıktısı hangi dilde? Menü dili mi, panel dili mi? | Çeviri yeteneğinin tanımı buna bağlı |

**Faz 1 kod yazımı 1. ve 3. karar olmadan başlamaz**; kalanlar Faz 2'de
gerekir.
