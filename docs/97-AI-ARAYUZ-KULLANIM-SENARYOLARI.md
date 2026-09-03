# 97 — AI arayüz boşluğu: kullanım senaryoları, kullanıcı yolculukları, gereksinim analizi

**Bu belge bir GEREKSİNİM analizidir, uygulama değil.** Önceki turda bulunan
dört boşluğun (üç AI özelliğinin frontend'i yok, çalışma-zamanı yedek zinciri
yok, `ArtifactSchemaValidator` bağlı değil, çeviri OPT-04 bekliyor) ilk ikisini
**inşa edilebilir** hâle getirir: her UNK satırını somut bir senaryoya, her
senaryoyu bir kullanıcı yolculuğuna, her yolculuğu da bir gereksinime bağlar.
Bir sonraki adım (frontend inşası) bu belgeye karşı yapılır.

## 1. Kapsam

Kaynak: `docs/16` §O (`AI-01..16`) ve §W (`AIV-01..09`) — kanonik
unknown-unknowns kaydı. 25 satırın **tamamı değil**, yalnız üç eksik
ekranı (menü fotoğrafı inceleme, ürün açıklaması taslağı, yinelenen-ürün
adayları) ve yedek zincirini **doğrudan** etkileyenler seçildi; seçim
gerekçesi her satırda yazılı.

## 2. UNK → Kullanım senaryosu

Her satır `Verilen / Ne zaman / O zaman` biçiminde: soyut risk, somut ve
sınanabilir bir davranışa dönüşür.

### AI-14 — Alerjen iddiası (Kritik, sağlık + hukuk)

> **Verilen** bir AI taslağı `candidate_allergens` alanı taşıyor,
> **ne zaman** inceleme ekranı bu alanı gösterir,
> **o zaman** ekranda "alerjensiz" / "vegan onaylı" gibi bir onay kutusu
> **olamaz** — yalnız "olası alerjen adayı: süt (model önerisi, doğrulanmadı)"
> biçiminde salt-bilgilendirme metni olabilir; bu metnin yanına bir
> onay/kabul kontrolü **konmaz**.

Zaten backend'de kilitli (`ArtifactSchemaValidator::FORBIDDEN_FIELDS`,
satır-eşleyicilerin izin-listesi). Bu senaryo UI'a aktarılan kısıt: ekran
tasarımı bu alanı hiç render etmemeli, yanlışlıkla bir "onayla" düğmesi
eklenmemeli.

### AI-15 — Model fiyat uydurması (Faz 1'den beri backend'de var, UI'da YOK)

> **Verilen** bir taslak satırında `priceMinorAmount: null`,
> **ne zaman** inceleme ekranı satırları listeler,
> **o zaman** o satır **görsel olarak ayrışmalı** (rozet/renk/ikon —
> `docs/06` §5 form/feedback sözleşmesiyle tutarlı semantik renk) ve
> "Onayla" eylemi o satır için **devre dışı** olmalı; kullanıcı yalnız
> "elle düzelt" ya da "bu satırı atla" seçebilir.

Backend zaten fiyatı okunamayan satırı reddediyor (`ApplyMenuArtifact`,
`ApplyProductDescriptionDraft`); eksik olan, kullanıcının bunu **onaylamadan
önce görmesi**.

### AI-13 — İnceleme yığılması → otomasyon yanlılığı

> **Verilen** bir workspace'te aynı anda 10+ bekleyen taslak,
> **ne zaman** kullanıcı inceleme ekranını açar,
> **o zaman** ekran **toplu "hepsini onayla"** sunmaz — her taslak ayrı
> incelenir; düşük güvenli (`confidence < eşik`) satırlar listenin **en
> üstünde** gösterilir, otomatik gizlenmez.

Bu, ekranın bilgi mimarisine doğrudan gereksinim yazar (§4).

### AI-01 / AIV-02 — Vendor drift, "fallback provider tanımlı" (containment YANLIŞ)

> **Verilen** Gemini yapılandırılmış ve OpenAI de yapılandırılmış,
> **ne zaman** Gemini'nin gerçek API çağrısı çalışma zamanında başarısız
> olur (500/ağ/zaman aşımı),
> **o zaman** istek **otomatik olarak OpenAI'a düşmeli** ve kullanıcıya
> "bu öneri yedek sağlayıcıdan geldi" ibaresi gösterilmeli
> (`docs/51` UNK-03).

`docs/16`'nın AI-01/AIV-02 satırlarının "Containment" hücresi zaten
**"Fallback provider tanımlı"** yazıyor — ama önceki turda doğrulandığı gibi
bu **yanlış bir iddia**: yedek yalnız bağlanma anında statik seçim,
çalışma zamanında canlı geçiş yok. Bu belge bunu düzeltir: containment
**gereksinimdir**, henüz **gerçek değildir**.

### AI-09 — Tenant'lar arası gömme/önbellek sızıntısı (Kritik) — doğrulandı, kapalı

> **Verilen** iki farklı workspace,
> **ne zaman** ikisi de aynı anda yinelenen-ürün taraması çalıştırır,
> **o zaman** hiçbir gömme vektörü ya da sonuç ikisi arasında **paylaşılmaz**.

Doğrulama (bu turda yapıldı): `DetectDuplicateProductNames::handle()`
ürünleri `where('workspace_id', $workspaceId)` ile okuyor,
`GeminiEmbeddingProvider::embed()` sonucu **kalıcı olarak saklamıyor** —
her çağrı istek-ömürlü, paylaşılan bir önbellek tablosu yok. **Bugün
kapalı, ama gelecekteki bir performans optimizasyonu (embedding önbelleği
eklenirse) önbellek anahtarına workspace_id'yi ZORUNLU kılmalı** —
bu, §4'e ileriye dönük bir gereksinim olarak yazılır, bugünkü koda değil.

### AIV-07 — No-credit degraded UX gerçek kullanıcıda ölçülmedi

> **Verilen** AI kapalı ya da bütçe tükenmiş,
> **ne zaman** kullanıcı "AI ile öner" düğmesine bakar,
> **o zaman** düğme **görünmez** (var olan `ai-no-credit-degradation` skill
> kuralı) — ama bu belge şunu ekliyor: düğmenin yokluğu kullanıcıya
> **neden yok olduğunu** söylemez; ekran hiçbir zaman "AI şu an
> kullanılamıyor" gibi kalıcı bir gri metin/tooltip bile göstermemeli mi,
> yoksa öğrenilebilirlik için bir ipucu mu gerekli — **owner kararı
       gerektirir**, bu belge yalnız soruyu keskinleştirir (§5).

## 3. Kullanıcı yolculukları

Format `docs/14` §9a'daki 17 adımlı akışla aynı disiplinde: numaralı adım,
her adımda kim/ne/hangi kapı. Persona'lar `docs/02` §1.2'den — uydurma değil.

### Yolculuk A — Menü fotoğrafını inceleyip onaylama (Faz 1, backend var, UI YOK)

**Persona:** Brand/Location Manager (ya da Account/Workspace Owner) —
`Permission::MenuManage`.

1. Kullanıcı Menü Kataloğu ekranında **"Fotoğraftan içe aktar"** eylemini
   görür (bugün bu buton **yok**).
2. Fotoğraf/PDF yükler — mevcut medya yükleme yolunu kullanır (CORE-13,
   yeniden icat edilmez).
3. "Oku" düğmesine basar → `POST .../ai-imports` (zaten var, FF-32-34).
4. AI kapalıysa/bütçe yoksa 503 + sebep gösterilir, düğme kaybolmaz ama
   devre dışı görünür ve neden yazar (`ai-no-credit-degradation`).
5. Sağlayıcı hata verirse (502) — **bugün burada yedek yok** (§2 AI-01
   senaryosu); yolculuk "tekrar dene" ile devam eder.
6. Okuma bitince **inceleme ekranı** açılır: her satır kategori/ürün/fiyat/
   güven ile listelenir.
7. Fiyatı okunamayan satırlar (AI-15) görsel ayrışır, onay devre dışı.
8. Kullanıcı satır satır **kabul/düzenle/reddet** yapar (`docs/06` §7 —
   tek tık kabul, ama her satır ayrı; toplu onay yok, AI-13).
9. "Taslağa uygula" → `POST .../ai-imports/{id}/apply` (zaten var).
10. Sonuç: taslak güncellendi, **yayına dokunulmadı** — kullanıcı ayrıca
    "Yayınla" demeli.
11. Reddedilen satırlar (`rejectedRows`) ayrı bir listede kalır, kayıp
    olmaz — kullanıcı elle tamamlayabilir.

**Eksik adım (bugün yok):** 1, 6-8, 11'in ekranı. 3, 4, 5, 9, 10 backend'de
var.

### Yolculuk B — Ürün açıklaması taslağı isteme ve onaylama (FF-46, backend var, UI YOK)

**Persona:** Editor ya da Brand/Location Manager — `Permission::MenuManage`.

1. Ürün düzenleme formunda açıklama alanının yanında **"AI ile öner"**
   bağlantısı görünür (bugün yok).
2. Tıklanınca `POST .../menu-items/{id}/description-drafts` (var, FF-46).
3. AI kapalıysa bağlantı görünmez/devre dışı, sebep tooltip'te.
4. Öneri gelince **düzenlenebilir bir metin kutusunda** gösterilir —
   doğrudan alana yazılmaz (`docs/01` §3: taslak, kalıcı değil).
5. Güven düşükse (`uncertain: true`) kutunun üstünde uyarı: "bu öneri
   emin değil, gözden geçirin."
6. Kullanıcı düzenler ya da olduğu gibi kabul eder → `POST
   .../description-drafts/{artifact}/apply` (var, FF-46).
7. Kaydedilen açıklama normal "Kaydet" akışıyla aynı; AI burada yalnız
   **öneri kaynağı**, ayrı bir kalıcı durum değil.

**Eksik adım:** 1, 4, 5, 6'nın ekranı. 2, 3, 6 (backend), 7 zaten var.

### Yolculuk C — Yinelenen ürün adaylarını gözden geçirme (FF-47, backend var, UI YOK)

**Persona:** Editor ya da Brand/Location Manager.

1. Menü Kataloğu'nda **"Olası tekrarlar"** bölümü/rozeti görünür (bugün
   yok) — yalnız aday sayısı > 0 ise görünür, boşsa hiç yer kaplamaz.
2. Tıklanınca `GET .../menu/duplicate-candidates` (var, FF-47) — aday
   çiftleri benzerlik skoruyla listelenir.
3. Kullanıcı her çift için **hiçbir otomatik eylem görmez** — yalnız
   "bu ikisi aynı ürün olabilir" bilgisi; birleştirme/silme **bu ekranın
   sorumluluğu değil** (backend hiçbir kaydı değiştirmiyor, salt-okunur
   öneri).
4. Kullanıcı isterse mevcut "ürün adını değiştir" ya da "ürünü sil"
   akışlarına (zaten var) manuel geçer.

**Eksik adım:** 1, 2, 3'ün ekranı. Backend zaten salt-okunur ve tam.

### Yolculuk D — Sağlayıcı çalışma zamanında başarısız olur (mekanizma teslim edildi — FF-49)

**Persona:** Sistem (arka plan), sonucu gören: Yolculuk A/B'deki kullanıcı.

1. İstek Gemini'ye gider (öncelik sırasındaki ilk aday).
2. Gemini 500/ağ hatası döner.
3. **Artık:** `VisionExtractionRouter`/`StructuredGenerationRouter`
   (`app/Infrastructure/Ai/`) aynı isteği **otomatik olarak** OpenAI'a
   (varsa yapılandırılmış) yeniden dener — kullanıcı bunu görmez, yalnız
   sonucu görür.
4. OpenAI da başarısız olursa **ancak o zaman** 502 + "her iki sağlayıcı
   da yanıt vermedi" mesajı.
5. Başarılı yedek çağrısının sonucu `AiArtifact.usedFallback = true` ile
   işaretlenir ve `ai_artifacts.used_fallback`'e kalıcı yazılır
   (`docs/51` UNK-03) — sessiz geçiş yok. **UI'da gösterimi henüz yok**
   (Yolculuk A/B/C'nin ekranı yazılınca bu alanı okumalı).
6. Denetim kaydı (`ai_invocations`) her iki denemeyi de taşır — ilk
   başarısız, ikinci başarılı; maliyet yalnız başarılı olandan sayılır.
   **Teslim edildi**, ayrıca test edildi (`VaultAiRoutingTest`,
   `VisionExtractionRouterTest`, `StructuredGenerationRouterTest`).

## 4. Gereksinim analizi

Her madde bir yolculuk adımına ve/veya bir UNK id'sine bağlı — kaynaksız
gereksinim yok.

### 4.1 İşlevsel — üç inceleme ekranı (Yolculuk A/B/C)

| # | Gereksinim | Kaynak |
| --- | --- | --- |
| R1 | Menü Kataloğu'nda "Fotoğraftan içe aktar" eylemi ve yükleme akışı | Yolculuk A.1-2 |
| R2 | Fotoğraf inceleme ekranı: satır listesi, güven göstergesi, satır-bazlı kabul/düzenle/reddet | Yolculuk A.6-8, AI-13 |
| R3 | Fiyatı/gerekli alanı okunamayan satır görsel ayrışması + onay kilidi | AI-15 |
| R4 | Ürün formunda "AI ile öner" (açıklama) — düzenlenebilir öneri kutusu | Yolculuk B.1, 4 |
| R5 | Düşük güvenli öneri için görünür uyarı metni | Yolculuk B.5 |
| R6 | Menü Kataloğu'nda "Olası tekrarlar" — yalnız aday varsa görünen, salt-okunur liste | Yolculuk C.1-3 |
| R7 | Üç ekranın hiçbiri toplu/otomatik onay sunmaz | AI-13 |
| R8 | Alerjen alanı hiçbir ekranda onay kontrolü olarak render edilmez | AI-14 |
| R9 | AI kapalı/bütçe yokken ilgili eylem görünmez ve **neden** kısa metinle belirtilir | AIV-07 (kısmi — §5'e bkz.) |

### 4.2 İşlevsel — çalışma zamanı yedek zinciri (Yolculuk D)

| # | Gereksinim | Kaynak |
| --- | --- | --- |
| R10 | **Teslim edildi (FF-49).** Bir `VisionExtractionPort`/`StructuredGenerationPort` çağrısı `ProviderCallException` fırlatırsa, yapılandırılmış bir sonraki sağlayıcıya **aynı istekle** otomatik yeniden denenir | Yolculuk D.3, AI-01/AIV-02 |
| R11 | **Teslim edildi (FF-49).** Yalnız TÜM adaylar tükendiğinde 502 döner | Yolculuk D.4 |
| R12 | **Backend teslim edildi (FF-49)** — `AiArtifact.usedFallback`, `ai_artifacts.used_fallback`, üç controller cevabında `usedFallback`. **UI tarafı R1-R9 ile birlikte bekliyor** — üç inceleme ekranı yazılınca bu alanı okuyup göstermeleri gerekir | Yolculuk D.5, `docs/51` UNK-03 |
| R13 | **Teslim edildi (FF-49).** Her deneme (başarılı/başarısız) `ai_invocations`'a ayrı satır yazar (her adaptörün kendi `record()` çağrısı); maliyet yalnız başarılı denemeden | Yolculuk D.6 |

### 4.3 İşlevsel — doğrulama katmanının gerçek bağlanması

| # | Gereksinim | Kaynak |
| --- | --- | --- |
| R14 | **Teslim edildi (FF-50).** `ArtifactSchemaValidator`, her `AiArtifact` üretiminden hemen sonra, use case içinde (`ExtractMenuFromImage`, `GenerateProductDescriptionDraft`) gerçekten çağrılır; ihlal `ProviderCallException('...', 'invalid-schema: ...')`'e çevrilir, taslak **kaydedilmez**. | önceki tur bulgusu |
| R15 | **Teslim edildi (FF-50), kapsamı düzeltilerek.** `requiredFieldsBySchema`, adı SABİT alan taşıyan tek şema için (`product-description.v1` → `description`) yapılandırıldı. `menu-extract.v1`'in satırları dinamik adlıdır (`row.1`, `row.2`…) — zorunluluk zaten ayrı bir katmanda (`ApplyMenuArtifact::readRows`) zorlanıyor, bu doğrulayıcıya taşınmadı. `embedding.v1` bu doğrulayıcıdan **hiç geçmez** — `EmbeddingPort` bir `AiArtifact` değil, çıplak vektör döner (`vector`, `model`); `FieldValue`/forbidden-field yüzeyi yok. İlk yazımdaki "üç şema da" ifadesi yanlıştı, düzeltildi. | önceki tur bulgusu |

### 4.4 Gelecek-korumalı (bugün eylem gerektirmez, kayıt altına alınır)

| # | Gereksinim | Kaynak |
| --- | --- | --- |
| R16 | Gömme sonuçları ileride önbelleğe alınırsa, önbellek anahtarı `workspace_id` içermek ZORUNDA | AI-09 (bugün kapalı, önbellek eklenirse geçerli) |

### 4.5 Non-functional

| # | Gereksinim | Kaynak |
| --- | --- | --- |
| R17 | Üç ekran de WCAG 2.2 AA (`docs/06` §8, `modules/ai-platform.md` AI Capability Manifest) | standart |
| R18 | Üç ekran de i18n kataloğundan okur, hardcoded dize yok (`UntranslatableStringScanner` kapısı) | standart |
| R19 | İnceleme ekranlarında hiçbir satır **otomatik** uygulanmaz — her satır ayrı insan eylemi gerektirir | `docs/01` §3, AI-13 |

## 5. Owner kararı gerekir mi?

1. **AIV-07'nin ölçülmemiş sorusu**: AI kapalıyken düğmenin tamamen
   yok olması mı, yoksa gri/devre-dışı+tooltip mi daha iyi öğrenilebilirlik
   sağlar — bu bir tasarım/marka kararı, pilot kullanıcı testi gerektirir
   (`docs/16` AIV-07 zaten "MVP Exit Gate sonrası" diye işaretli).
2. **R12'deki "yedek sağlayıcı" etiketinin tam metni** — kullanıcıya
   "farklı bir yapay zeka kullanıldı" mı, yoksa teknik detay vermeden
   "biraz gecikti ama tamamlandı" mı denecek — marka sesi kararı.

Geri kalan tüm gereksinimler (R1-R11, R13-R19) geri döndürülebilir teknik
kararlardır, MASTER'da kalır.

## 6. Kanonik sahiplik

Bu belge UNK kayıtlarını (`docs/16`) tekrar etmez, yalnız ilgili satırları
somutlaştırır. Persona/rol tanımları `docs/02`'de, AI yetenek/model/hesap
eşlemesi `docs/96`'da, faz sırası `docs/95`'te kanoniktir. R1-R19 burada
kanoniktir — bir sonraki uygulama turu bu numaralara referans verir.
