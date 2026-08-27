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

### 3.3 Yönlendirme sırası — sahibinin kararı (2026-08-27)

Bir yetenek için hangi modelin çalışacağı **sabit bir sırayla** çözülür.
Sıra maliyet ve yetenek gerçekliğinden gelir, tercihten değil:

```
1. YEREL     küçük açık kaynak model      → bedava, veri sunucudan çıkmaz
2. GEMINI    bulut                        → ucuz; varsayılan bulut sağlayıcı
3. OPENAI    bulut                        → Gemini'nin yetmediği yerde
4. CLAUDE    bulut                        → EN SON, iki dar çerçevede
```

**Claude neden en sonda ve nerede:**

| Çerçeve | Kullanılır mı |
| --- | --- |
| Sistemin kendi **kodlama ve teknik gelişimi** | ✅ Birincil — bu, ürün çalışma zamanı değil, GELİŞTİRME hattıdır |
| Gemini ve OpenAI'nin **yapamadığı** iş | ✅ Yetenek matrisinde açıkça işaretlenmiş satırlar |
| Sıradan üretim/çeviri/etiketleme | ❌ Daha ucuzu yeterliyken pahalı olan seçilmez |

Bu ayrım kayda değer, çünkü iki farklı hattır: **ürün çalışma zamanı**
(restoran sahibinin tetiklediği AI) ile **geliştirme hattı** (bu deponun
kendi kodlama yardımı). Aynı bütçeden beslenmezler ve aynı matriste
durmazlar.

**Yükselme (escalation) kuralı:** bir alt basamak sonucu şemaya uymazsa ya
da güven eşiğinin altındaysa bir üst basamağa çıkılır — ve bu KULLANICIYA
GÖRÜNÜR olur (UNK-03). "Yerel model yetmedi, buluta çıkıldı" bilgisi
saklanmaz; maliyeti ve kaliteyi açıklayan tek şey odur.

### 3.4 Yetenek × model matrisi

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

### 3.5 Faz 1'in kabul ölçütü

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

## 4. Yerel küçük modeller — MVP'de indirilecek ve çalışacaklar

Sahibinin kararı: **önce yerel modeller, MVP'de gerçekten fayda sağlayanlar
indirilip çalıştırılacak.**

netcup VPS'te GPU yok. Bu, modelleri iki kümeye ayırır ve ayrım nettir:
**kodlayıcı (encoder) modeller CPU'da milisaniyelerde** çalışır; **üretken
(decoder) modeller saniyelerde.** Birincisi kullanıcı beklerken kullanılabilir,
ikincisi yalnız arka planda.

### 4.1 Kodlayıcı sınıfı — MVP'de indirilir

| Model sınıfı | Bu projede ne yapar | Gecikme |
| --- | --- | --- |
| **Çok dilli cümle gömme** | Yinelenen ürün ("Trileçe" × 2), menü içi anlamsal arama, kategori önerisi, çeviri belleği eşleşmesi | ~10–40 ms |
| **Görsel gömme (CLIP sınıfı)** | Yinelenen/benzer görsel, "bu görsel yemek mi logo mu", ürünle görselin uyuşması | ~50–150 ms |
| **Metin sınıflandırıcı** | Vejetaryen/vegan/helal işareti önerisi, ürün mü kategori mi | ~10 ms |
| **Dil algılama** | Menü içeriğinin dili; çeviri kuyruğunu besler | ~1 ms |
| **Yeniden sıralayıcı (cross-encoder)** | Arama sonuçlarının sıralaması | ~20 ms |
| **Alerjen NER** | Ürün adı/açıklamasından aday alerjen | ~15 ms |

**Bunlar LLM değil.** Şema sorunu yok (UNK-02), enjeksiyon yüzeyi yok
(UNK-07), maliyet yok. MVP'de indirilir ve çalışır.

### 4.2 LLM olmayan ama AI hattının parçası

| Araç | İş |
| --- | --- |
| **OCR (Tesseract sınıfı)** | Görselden/PDF'ten ham metin — çıkarımın deterministik yarısı |
| **Klasik CV** | Bulanıklık, karanlık, çözünürlük, yüz/nesne kadrajı |
| **Perceptual hash** | Birebir olmayan yinelenen görsel |

`vips` zaten imaja giriyor (`docs/49` Faz 1); CV işlerinin çoğu onunla yapılır.

### 4.3 Üretken küçük model — sınırlı ve arka planda

1–3B sınıfı nicelenmiş bir model CPU'da saniyede birkaç kelime üretir.
Kullanıcı beklerken **kullanılmaz**; şu işler için yeterlidir:

- Alt metin taslağı (arka planda üretilir, kullanıcı onaylar)
- Kısa alan tamamlama ("Adana" → "Adana Kebap")
- Metin toparlama (büyük/küçük harf, noktalama)

**Kalite dalgalanır ve bu gizlenmez**: çıktı her zaman öneri olarak, kaynağı
görünür biçimde sunulur.

### 4.4 Çalışma zamanı — yerel model bir SAĞLAYICIDIR

```
llama.cpp / ONNX  →  OpenAI-uyumlu HTTP uç noktası  →  aynı TextGenerationPort
```

OpenAI-uyumlu uç nokta seçilmesi bilinçli: **aynı adaptör kodu** hem bulut hem
yerel için çalışır. İki kod yolu olsaydı biri her zaman geride kalırdı (UNK-05).

**Kaynak sınırı zorunlu** (UNK-10): ayrı süreç, bellek ve CPU tavanı, istek
kuyruğu uzunluğu sınırı. Tavan aşılırsa AI isteği reddedilir — menü sayfası
etkilenmez.

**Model dosyaları sürüm kontrolünde DEĞİL**; `install.sh` indirir ve
sağlaması (checksum) doğrulanır. Depoya 500 MB'lık bir dosya koymak, klonlama
süresini ve CI'yı kalıcı olarak bozar.

---

## 4b. Büyük API modelleri — yerel modelin yapamadıkları

### 4b.1 Görselden menü çıkarma (sahibinin eklediği)

Sahibi netleştirdi: **yalnız PDF değil — resim, fotoğraf, grafik.**
Restoran sahibinin elinde çoğu zaman PDF yoktur; telefonla çekilmiş bir
menü fotoğrafı vardır.

Bu, iki yarımlı bir iştir ve ikisi de gereklidir:

```
DETERMİNİST YARIM                   AI YARIMI
─────────────────                   ─────────
görüntü düzeltme (perspektif)   →   görme modeli okur
kontrast/eğrilik                    → yapılandırılmış JSON üretir
OCR ham metin                       → kategori/ürün/fiyat ilişkisini kurar
                                    → belirsiz alanları İŞARETLER
```

**Kritik kural: model belirsizliği gizlemez.** Okunamayan bir fiyat "0" ya da
uydurma bir sayı olarak gelmez; `uncertain: true` ile gelir ve arayüz onu
kullanıcıya sorar. Uydurulmuş bir fiyat menüye girerse, kullanıcı bunu ancak
müşteri şikâyet edince öğrenir.

| Girdi | Ele alınışı |
| --- | --- |
| PDF (metin katmanlı) | OCR gereksiz; doğrudan metin + yapı |
| PDF (taranmış) | OCR + görme modeli |
| Fotoğraf (telefon) | Perspektif düzeltme + görme modeli |
| Ekran görüntüsü | Doğrudan görme modeli |
| Grafik/tasarım (PNG) | Görme modeli |
| Birden çok sayfa/fotoğraf | Sayfa sırası kullanıcıya doğrulatılır |

**Sağlayıcı:** görme yeteneği Gemini'de başlar (ucuz, güçlü), yetmezse
OpenAI, en son Claude. Yerel model bu işi yapamaz — 4.1'deki dürüst sınır.

### 4b.2 Büyük modellerin yaptığı diğer işler

| İş | Neden yerel model yapamaz |
| --- | --- |
| **Menü tonunda çeviri** | Kısa metin değil, marka sesi; küçük model düzleştirir |
| **Uzun bağlamlı tutarlılık denetimi** | "84 üründe fiyat mantığı tutarlı mı" — tüm menü tek bağlamda |
| **Analitik anomali açıklaması** | "Taramalar %40 düştü" → sebep hipotezi, veriyle bağ kurma |
| **Doğal dil komut merkezi** | `docs/14` §9a 14 adımlı akış; akıl yürütme gerekir |
| **Yayın öncesi risk taraması** | Eksik çeviri + eksik görsel + fiyat anomalisi, birlikte |
| **Alerjen çıkarımı (metinden)** | "İçinde tereyağı geçen tarif" → süt alerjeni; bilgi gerektirir |

---

## 5. Token optimizasyonu — ayrı bir servis

Sahibinin talebi. Bu bir "ipucu" değil, **ölçülen ve zorlanan bir katman**;
maliyetin tek başına en büyük kaldıracı.

`Application/Ai/TokenOptimizer` yedi işi yapar:

| # | Teknik | Kazanç |
| --- | --- | --- |
| 1 | **Model doğru boyutlandırma** | En küçük model, eval'den geçtiği sürece. Çoğu iş için Haiku sınıfı yeterli |
| 2 | **Prompt önbelleği** | Sabit ön ek (sistem yönergesi, şema) önbelleğe alınır — tenant→hesap yapışkanlığı bunun ön koşulu (UNK-01) |
| 3 | **Getirme, doldurma değil** | Tüm menüyü prompt'a koymak yerine gömme ile ilgili 5 ürünü getir |
| 4 | **Bağlam budama** | Yetenek için gereksiz alanlar (id, timestamp) prompt'a girmez |
| 5 | **Şema sıkılığı** | Çıktı şeması dar; model gereksiz açıklama üretmez |
| 6 | **Toplu işleme** | 20 ürünün alt metni tek istekte |
| 7 | **Yinelenen iş önleme** | Aynı girdi için sonuç önbelleği (içerik sağlaması anahtarlı) |

**Ölçülür:** her çağrı için giriş/çıkış token'ı ve kuruş cinsinden maliyet
denetim kaydına yazılır. Optimizasyon iddiası ölçülmeden yapılmaz.

**Skill olarak da açılır:** `skills/token-optimization.md` — geliştirme
hattındaki AI'nın da aynı kurallara uyması için.

---

## 6. Yönerge katmanı — MCP, skills, .md ve ECA kuralları

Sahibinin talebi: *"mcp'ler, skill'ler, .md dosyaları ile AI yönergeleri,
sınırlar, kurallar, eca rules."*

### 6.1 Dört ayrı şey — karıştırılmaz

| Katman | Nedir | Nerede yaşar |
| --- | --- | --- |
| **Yönerge (.md)** | AI'nın uyacağı yazılı kural; insan da okur | `ai/guidelines/*.md` |
| **Skill (.md + şema)** | Talep üzerine yüklenen yetenek tanımı: ne yapar, neyi yapamaz, hangi araçları çağırabilir | `ai/skills/*.md` |
| **MCP server** | Dış araç erişimi — kendi allowlist'iyle | `config/ai-mcp.php` |
| **ECA kuralı** | Olay → koşul → eylem. AI'yı NE ZAMAN çağıracağımız | `config/ai-eca.php` |

**Hiçbiri "always-on" değildir** (`docs/14` §5). Yönerge bile bir yeteneğe
bağlanır; her prompt'a her kuralı eklemek hem token yakar hem modeli
körleştirir.

### 6.2 Skill dosyasının zorunlu bölümleri

```markdown
---
name: menu-extraction
capability: menu.extract
requires_human_approval: true
allowed_tools: [media.read, menu.draft.write]
forbidden: [menu.publish, media.delete, billing.*]
schema: menu-extraction.v1
escalation: [local, gemini, openai]
---
### Ne yapar
### Ne YAPAMAZ
### Belirsizlik nasıl işaretlenir
### Örnekler (eval kümesinin çekirdeği)
```

`forbidden` listesi bir yorum değil, **çalışma zamanında zorlanır**: listede
olmayan araç çağrısı reddedilir ve denetime yazılır (`docs/14` §6).

### 6.3 ECA kuralları — AI'yı ne zaman çağırırız

Proaktif öneri **yalnız gerçek sinyal varsa** görünür (`docs/50` Faz 10).
ECA bunu bir kurala çevirir:

| Olay | Koşul | Eylem |
| --- | --- | --- |
| `menu.item.created` | alt metin yok VE görsel var | Alt metin önerisi kuyruğa |
| `menu.publish.requested` | eksik çeviri > 0 | Yayın öncesi tarama, insan onayı |
| `media.uploaded` | benzer gömme mesafesi < eşik | "Bu görsel zaten var" önerisi |
| `analytics.weekly` | değişim > %30 | Anomali açıklaması |
| `ai.budget.threshold` | %80 | Sahibine uyarı; %100'de AI durur, ürün durmaz |

ECA motoru **AI'yı çağırmaz, kuyruğa koyar.** Kullanıcı beklerken model
çalışmaz; öneri hazır olduğunda görünür.

## 7. Rol bazında AI — kim neyden yararlanır

Depodaki gerçek roller ölçüldü: restoran tarafında **Owner / Member /
Editor** (`MembershipRole`), platform tarafında **SuperAdmin**
(`PlatformRole`), 13 izinle (`Permission`).

**Temel kural:** AI hiçbir zaman rolün YAPAMADIĞI bir şeyi yapamaz. Öneri
üretmek serbesttir; uygulamak izne bağlıdır ve izin **sunucuda yeniden**
doğrulanır (`docs/14` §9a adım 13). Editor'a "menüyü yayınla" önerisi
gösterilebilir — ama düğme onda çalışmaz; öneri sahibine iletilir.

### 7.1 Restoran tarafı

| Rol | İzinleri | AI'nın onun için yaptığı iş | AI'nın YAPAMADIĞI |
| --- | --- | --- | --- |
| **Owner** | Hepsi | Yayın öncesi risk taraması; plan/kullanım açıklaması ("bu ay neden arttı"); analitik anomali açıklaması; ekip yetkisi önerisi (`docs/14` §9a akışı, insan onaylı + step-up) | Yayınlamak, ödeme, rol değiştirmek — hiçbiri onaysız |
| **Editor** | `menu.manage`, `qr.*`, medya | **En çok yararlanan rol.** Fotoğraf/PDF'ten menü çıkarma; ürün açıklaması taslağı; alt metin; alerjen önerisi; çeviri taslağı; yinelenen ürün/görsel tespiti; kırpma odak noktası | `menu.publish` yok → yayın önerisi üretir, Owner'a gider |
| **Member** | `*.view` | Okuma yüzeyi: menüde anlamsal arama ("içinde süt olan ürünler"); analitik özetini sade dille okuma | Hiçbir mutasyon önerisi gösterilmez — gösterilse boş bir umut olurdu |

**Neden Editor en çok yararlanır:** günlük iş onun elinde. Menü doldurmak,
görsel yüklemek, çeviri girmek — AI'nın gerçekten saat kazandırdığı işler
bunlar. Owner ise ayda birkaç kez karar verir; ona lazım olan üretim değil,
**özet ve risk**.

### 7.2 Platform tarafı (SuperAdmin)

Bu roldeki kişi restoran içeriğiyle ilgilenmez; **filoyu** yönetir.

| İş | AI'nın katkısı |
| --- | --- |
| Destek | Bir tenant'ın son 20 hatasını okuyup olası sebep; "bu menü neden yayınlanamıyor" |
| Sağlık | İşleme kuyruğundaki başarısızlıkların gruplanması: 40 ayrı hata mı, tek bir kök neden mi |
| Kötüye kullanım | Yüklenen medyada moderasyon işareti; anormal kota tüketimi |
| Maliyet | Hangi tenant hangi yeteneği ne kadar kullanıyor; hangi yetenek daha ucuz modele inebilir (eval geçerse) |
| İçerik incelemesi | Halka açık menüde riskli içerik önerisi — **karar insanın** |

**Sert sınır — tenant izolasyonu AI'da da geçerlidir.** SuperAdmin'in AI'sı
bir tenant'ın içeriğini başka bir tenant'ın bağlamına KOYAMAZ. "Bütün
tenant'ları karşılaştır" gibi bir istek, ancak toplulaştırılmış (agrega)
veriyle cevaplanır; ham içerik çapraz okunmaz (`docs/14` §3 tenant
isolation).

### 7.3 Geliştirme hattı — ayrı bütçe, ayrı matris

Sahibinin kararı gereği Claude bu hatta birincildir: **sistemin kendi
kodlama ve teknik gelişimi.** Bu hat ürün çalışma zamanı değildir:

- Tenant bütçesinden beslenmez
- Yetenek matrisinde görünmez
- Restoran verisine erişmez — geliştirme ortamında çalışır
- Kendi yönergeleri `ai/guidelines/` ve `skills/` altında

İki hattın karışması, en pahalı modelin en sık işi yapmasına yol açardı.

## 8. Faz faz AI — her fazda çekirdek, eklenti değil

Sahibinin kararı: **MVP'de AI çekirdeği güçlü olmalı.** Bu, Faz 1'in kapsamını
belirler — çekirdek MVP'de biter, yetenekler sonra gelir.

### Faz 1 — MVP AI ÇEKİRDEĞİ (kod bu fazda yazılır)

| # | İş | Neden Faz 1 |
| --- | --- | --- |
| 1 | `TextGenerationPort` + dört adaptör (yerel, Gemini, OpenAI, Claude) | Tek arayüz; yerel de bir sağlayıcıdır |
| 2 | Yapışkan hesap havuzu + sağlık kontrolü | Önbellek ve bağlam buna bağlı (UNK-01) |
| 3 | Yetenek × model matrisi + yükselme sırası | Yerel → Gemini → OpenAI → Claude |
| 4 | JSON şema doğrulaması | Şemaya uymayan cevap kullanıcıya ulaşmaz (UNK-02) |
| 5 | Redaction | Kişisel veri prompt'a girmez (UNK-04) |
| 6 | Enjeksiyon koruması — kullanıcı içeriği VERİ olarak işaretlenir | Menü içeriğinden gelir (UNK-07) |
| 7 | Tenant başına bütçe + kill switch | Dolunca AI durur, ürün durmaz (UNK-06) |
| 8 | **Token optimizasyonu servisi** (§5) | Sonradan eklenen optimizasyon, mimariyi yeniden yazdırır |
| 9 | Denetim: hesap, model, token, maliyet | Ölçülmeyen iddia edilmez |
| 10 | **Yerel model çalışma zamanı** + kaynak sınırı | MVP'de indirilir ve çalışır (UNK-10) |
| 11 | Yönerge/skill/MCP/ECA kayıt defterleri (§6) | Boş ama YERİNDE; sonradan kurulan kayıt defteri hep eksik kalır |
| 12 | Eval koşum düzeneği (altın küme boş başlar) | Model değişimi ölçülmeden yapılmaz (UNK-08) |

**Faz 1 kabul ölçütü:** hiçbir sağlayıcı bağlı değilken ürün TAM çalışır; sahte
sağlayıcıyla tüm zincir uçtan uca koşar; yerel gömme modeli indirilmiş ve
milisaniyelerde cevap veriyor.

### Faz 2–10 — yetenekler çekirdeğe takılır

| Faz | Ürün işi | AI yeteneği | Sağlayıcı | Determinist yol |
| --- | --- | --- | --- | --- |
| **2** | Güvenli alım | Alt metin taslağı; dil algılama | **Yerel** | Kullanıcı yazar |
| **3** | Sürüm/rendition | Yinelenen görsel/ürün (gömme); görsel-ürün uyumu | **Yerel** | Checksum |
| **4** | Kütüphane | Anlamsal arama; akıllı koleksiyon; görsel kalite | **Yerel** | Elle filtre |
| **5** | Kullanım + yayın | Yayın öncesi risk taraması; eksik çeviri tespiti | Yerel + **Gemini** | Kontrol listesi |
| **6** | Teslim/CDN | **Fotoğraf/görsel/PDF'ten menü çıkarma** (§4b.1) | **Gemini** → OpenAI | Elle giriş |
| **7** | Yönetişim | Anomali açıklaması; maliyet analizi; kuyruk hata gruplama | **Gemini** | Ham sayılar |
| **8** | Crop stüdyosu | Akıllı kırpma, odak noktası | **Yerel** (CV) | Elle odak |
| **9** | Komut merkezi | `docs/14` §9a 14 adımlı akış; doğal dil komut | **OpenAI/Claude** | Her ekranın formu |
| **10+** | İleri | Moderasyon, tonlu çeviri, video anlama | Duruma göre | — |

**Claude yalnız 9'da ve "diğerlerinin yapamadığı" satırlarda görünür** —
sahibinin sırası bu. Geliştirme hattındaki Claude ayrıdır (§7.3).

**Her satırda değişmez:** AI önerir, insan onaylar, sistem denetler, geri
alınabilir.

## 9. Sahibinin kararı gereken noktalar

Sahibi 2026-08-27'de yönlendirme sırasını, yerel model önceliğini, token
optimizasyonunu ve yönerge katmanını karara bağladı. Kalanlar:

| # | Karar | Neden gerekli | Ne zaman |
| --- | --- | --- | --- |
| 1 | **Hangi sağlayıcılarda gerçekten hesap var?** Gemini / OpenAI / Claude — kaçar tane | Olmayan sağlayıcı için adaptör yazılmaz; hesap sayısı havuzun şeklini belirler | **Faz 1 başlamadan** |
| 2 | **Yerel modeller aynı VPS'te mi?** | Aynı sunucudaysa bellek/CPU tavanı zorunlu, yoksa menü sayfası yavaşlar (UNK-10) | **Faz 1 başlamadan** |
| 3 | Tenant başına aylık AI bütçesi | Tavansız AI maliyeti öngörülemez. Öneri: paket başına, ve dolunca AI durur ürün durmaz | Faz 1 sonunda |
| 4 | Restoran sahibi model seçebilecek mi? | Seçim yüzeyi Faz 1'e girer ya da girmez. Öneri: **hayır** — matris seçsin, kullanıcı "hızlı/kaliteli" der | Faz 1 sonunda |
| 5 | AI çıktısı hangi dilde? | Menü dili mi panel dili mi — çeviri yeteneğinin tanımı buna bağlı | Faz 2 |

**Faz 1 kod yazımı 1. ve 2. karar olmadan başlamaz.** Diğer üçü için önerim
yukarıda; itiraz etmezseniz onlarla ilerlerim.

---

## 10. Ne ölçülecek — iddia edilmeyecek

AI planlarının en yaygın kusuru, kazancın hiç ölçülmemesidir. Bu plan üç şeyi
ölçer ve raporlar:

| Ölçüm | Nasıl |
| --- | --- |
| **Kalite** | Yetenek başına altın küme; model değişimi o kümeden geçmeden yayınlanmaz |
| **Maliyet** | Her çağrıda giriş/çıkış token'ı ve kuruş; tenant ve yetenek kırılımında |
| **Kazanılan zaman** | Öneri kabul oranı ve elle düzeltme miktarı. Kimse kabul etmiyorsa yetenek işe yaramıyordur |

Üçüncüsü en çok atlanan ve en önemlisidir: **kabul edilmeyen bir AI önerisi,
kullanıcıya zaman kaybettirmiştir.**
