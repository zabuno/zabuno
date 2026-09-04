# 97 — AI arayüz boşluğu: kullanım senaryoları, kullanıcı yolculukları, gereksinim analizi

**Bu belge bir GEREKSİNİM analizi olarak başladı; şimdi kendi kanıtını da
taşıyor.** İki uygulama turunu kapsar:

- **§1-§4 — Faz 2'nin arayüz boşluğu (R1-R19, FF-49…FF-53).** Bulunan dört
  boşluğun (üç AI özelliğinin frontend'i yok, çalışma-zamanı yedek zinciri
  yok, `ArtifactSchemaValidator` bağlı değil, çeviri OPT-04 bekliyor)
  tamamı kapandı — yalnız çeviri bilinçli ertelendi (OPT-04 hâlâ yok).
- **§4b — Faz 3'ün çok-hesap/BYOK kapsamı (R20-R30, FF-54…FF-61).**

R1-R30'un tamamı ya "teslim edildi" ya da gerekçeli bir owner-kararı
bekliyor (§5). Uygulama sırasında üç kez, bu belgenin ya da `docs/16`'nın
gerçeği yanlış tarif ettiği ortaya çıktı (R2/Yolculuk A.8, R7 ve
AI-01/AIV-02'nin "fallback provider tanımlı" iddiası) — düzeltmeler
yerinde işaretli, kayıp değil.

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

### Yolculuk A — Menü fotoğrafını okutup taslağa ekleme (teslim edildi — FF-53)

**Persona:** Brand/Location Manager (ya da Account/Workspace Owner) —
`Permission::MenuManage`.

1. **Teslim edildi, kapsamı düzeltilerek.** Kullanıcı Menü Kataloğu
   ekranında **"Import from a photo (AI)"** eylemini görür. Fotoğraf
   yükleme burada **değil** — Media sayfasında (`menuImportSource` slotu,
   FF-53'te eklendi); bu bölüm yalnız **hazır** bir görseli seçtirir
   (mevcut "sunum düzenleyici" fotoğraf seçici ile aynı desen).
2. Fotoğraf zaten yüklüdür (CORE-13 — yeniden icat edilmedi, gerçekten
   kullanıldı).
3. "Read these photos" düğmesine basar → `POST .../ai-imports/batch`.
   **Kapsam genişlemesi (FF-61):** bu adım tekil `.../ai-imports` ucunu
   kullanıyordu; artık çok seçimli, çünkü bir restoranın menüsü tek
   fotoğrafa sığmıyor. Tekil uç API'de duruyor, ekran kullanmıyor.
4. **Teslim edildi.** AI kapalıysa/bütçe yoksa 503 + kısa bir mesaj
   gösterilir, ekran çökmez.
5. **Teslim edildi (FF-49 sayesinde).** Sağlayıcı hata verirse artık canlı
   yedek zinciri devreye girer (Gemini→OpenAI) — kullanıcı bunu görmez,
   yalnız sonucu görür; yedekten geldiyse "Read by a backup provider."
   notu çıkar.
6. Okuma bitince **inceleme listesi** açılır: her satır kategori/ürün/fiyat
   ile gösterilir.
7. **Teslim edildi.** Fiyatı okunamayan satırlar (AI-15) görsel ayrışır
   ("this row will be skipped").
8. **Kapsam düzeltmesi — önemli.** İlk yazımdaki "kullanıcı satır satır
   kabul/düzenle/reddet yapar" **gerçek backend'i yanlış tarif ediyordu.**
   `ApplyMenuArtifact::readRows()` bir insan kararı almaz — veri
   bütünlüğüne (kategori+ürün+geçerli fiyat+para birimi) göre **kendisi**
   otomatik uygular ya da atlar. Ekran bunu olduğu gibi yansıtıyor: tek bir
   **"Add these to the draft"** eylemi var, satır başına kontrol yok. Bu
   hâlâ AI-13'ü karşılıyor (toplu/otomatik ONAY yok — çünkü zaten insan
   onayı olan tek şey "ekle" kararının kendisi, satır seçimi değil).
9. "Add these to the draft" → `POST .../ai-imports/batch/apply` (FF-61).
10. **Teslim edildi.** Sonuç: taslak güncellendi, **yayına dokunulmadı**.
11. **Teslim edildi.** Reddedilen satırlar (backend'in kendi otomatik
    kararıyla) sebepleriyle listelenir ("Row 2: Fiyat okunamadı; bu satırı
    elle ekleyin.") — kullanıcı elle tamamlayabilir.

**Kapı:** `MenuCatalogWorkspace.aiImport.test.tsx` (9): boş medya mesajı,
satır önizleme + fiyat-eksik uyarısı, AI kapalı zarafeti, yedek etiketi,
uygula+reddedilen-satır listesi, toplu/otomatik onay kontrolü olmadığının
doğrulanması.

### Yolculuk B — Ürün açıklaması taslağı isteme ve onaylama (teslim edildi — FF-51)

**Persona:** Editor ya da Brand/Location Manager — `Permission::MenuManage`.

1. **Teslim edildi.** Sunum düzenleyicide (fotoğraf+açıklama formu) açıklama
   alanının altında **"Suggest with AI"** düğmesi var.
2. Tıklanınca `POST .../menu-items/{id}/description-drafts` (FF-46) —
   cevap artık açıklama METNİNİ de taşıyor (FF-51'de eklendi; önceden yalnız
   `id`/`uncertainFieldCount` dönüyordu, ekran ayrı bir GET yapmak zorunda
   kalırdı).
3. **Teslim edildi.** AI kapalıysa (503) düğme hata göstermez, kısa bir
   mesaj gösterir ("AI suggestions are not available right now.") ve elle
   yazma yolu bozulmadan çalışır.
4. **Teslim edildi.** Öneri gelince **var olan açıklama kutusunu** doldurur
   (ikinci bir kutu icat edilmedi) — kullanıcı serbestçe düzenler.
5. **Teslim edildi.** Güven düşükse (`uncertainFieldCount > 0`) kutunun
   altında uyarı metni; yedek sağlayıcıdan geldiyse (`usedFallback`) ayrı
   bir etiket (R12'nin ilk UI kullanımı).
6. **Teslim edildi.** "Save presentation" artık taslak varken düz PUT
   yerine `POST .../description-drafts/{artifact}/apply` çağırır —
   **düzenlenmiş metni** taşır. Bu, apply uç noktasının FF-51'de genişleyen
   tarafı: önceden yalnız taslağın kendi metnini yeniden okuyordu, kullanıcı
   düzenlemesi sessizce atılırdı.
7. Kaydedilen açıklama normal "Kaydet" akışıyla aynı ürün alanına gider; AI
   burada yalnız **öneri kaynağı**, ayrı bir kalıcı durum değil.

**Kapı:** `MenuCatalogWorkspace.presentation.test.tsx` +4 (öneri→kutu→onay,
503 zarifçe, belirsiz uyarı, yedek etiketi); `ProductDescriptionDraftTest`
+2 (düzenlenmiş metnin uygulanması, cevabın metni taşıması).

**Eksik adım:** 1, 4, 5, 6'nın ekranı. 2, 3, 6 (backend), 7 zaten var.

### Yolculuk C — Yinelenen ürün adaylarını gözden geçirme (teslim edildi — FF-52)

**Persona:** Editor ya da Brand/Location Manager.

1. **Teslim edildi.** Menü Kataloğu'nda **"Possible duplicates (N)"**
   bölümü — yalnız aday sayısı > 0 ise görünür, boşsa hiç yer kaplamaz
   (koşullu render, iskelet/boş durum yok).
2. **Teslim edildi.** Menü yüklenince `GET .../menu/duplicate-candidates`
   (FF-47) **bir kez** çekilir — her satır düzenlemesinde yeniden
   sorgulanmaz (bilinçli kapsam kararı: bu ikincil bir öneri, sahip menüyü
   yeniden açtığında tazelenir).
3. **Teslim edildi.** Her çift ada göre listelenir, **hiçbir otomatik
   eylem yok** — "bu ikisi aynı ürün olabilir" bilgisi; birleştirme/silme
   düğmesi **kasıtlı olarak eklenmedi** (backend hiçbir kaydı
   değiştirmiyor, salt-okunur öneri).
4. Kullanıcı isterse mevcut "ürün adını değiştir" ya da "ürünü sil"
   akışlarına (zaten var) manuel geçer — bu ekran onlara dokunmaz.
5. **Teslim edildi.** İstek başarısız olursa (503/502/ağ) bölüm sessizce
   görünmez, ana ekranı bozmaz — ikincil bir öneri için ayrı bir hata
   ekranı orantısız olurdu.

**Kapı:** `MenuCatalogWorkspace.duplicates.test.tsx` (4): aday varsa
listelenir, aday yoksa bölüm yok, istek başarısız olursa sessizce boş
kalır, birleştir/sil düğmesi yok.

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
| R1 | **Teslim edildi (FF-53).** Menü Kataloğu'nda "Import from a photo (AI)" eylemi. **Kapsam düzeltmesi:** yükleme akışı yeniden icat edilmedi — bu bölüm yalnız `menuImportSource` slotuna (FF-53'te eklendi) zaten yüklenmiş, hazır bir fotoğrafı seçtirir; yükleme Media sayfasında olur | Yolculuk A.1-2 |
| R2 | **Teslim edildi (FF-53), kapsamı düzeltilerek.** Fotoğraf inceleme ekranı: satır listesi + güven/belirsizlik göstergesi var. **"Satır-bazlı kabul/düzenle/reddet" YANLIŞ bir vaatti** — gerçek backend (`ApplyMenuArtifact::readRows`) satır düzeyinde bir insan kararı almaz; veri bütünlüğüne (kategori+ürün+geçerli fiyat+para birimi) göre kendisi otomatik uygular ya da atlar. Ekran bunu OLDUĞU gibi yansıtır: tek "Add these to the draft" eylemi, satır başına onay/düzenle kontrolü yok | Yolculuk A.6-8, AI-13 |
| R3 | **Teslim edildi (FF-53).** Fiyatı okunamayan satır görsel ayrışması (`text-fg-warning` + "will be skipped" metni) — kilit ayrıca gerekmiyor, zaten backend o satırı otomatik atlıyor | AI-15 |
| R4 | **Teslim edildi (FF-51).** Ürün formunda "AI ile öner" (açıklama) — düzenlenebilir öneri kutusu. Sunum düzenleyicideki mevcut `Textarea` yeniden kullanıldı, ikinci bir kutu icat edilmedi; öneri geldiğinde aynı alanı doldurur, kullanıcı serbestçe düzenler. Apply uç noktası **düzenlenmiş metni** kabul edecek şekilde genişletildi (önceden yalnız taslağın kendi metnini uyguluyordu — düzenleme sessizce atılırdı) | Yolculuk B.1, 4 |
| R5 | **Teslim edildi (FF-51).** Düşük güvenli öneri için görünür uyarı metni (`uncertainFieldCount > 0`); ayrıca yedek sağlayıcıdan gelen öneri ayrı etiketlenir (R12'nin UI karşılığı, ilk kullanım yeri) | Yolculuk B.5 |
| R6 | **Teslim edildi (FF-52).** Menü Kataloğu'nda "Olası tekrarlar" — yalnız aday varsa görünen, salt-okunur liste | Yolculuk C.1-3 |
| R7 | **Tam kapandı (FF-51/52/53).** Üç ekranın üçü de teslim edildi, hiçbiri toplu/otomatik onay sunmuyor: C salt-okunur (eylem yok), B satır-tek onay, A tek "Add these to the draft" — çok-satırlı olsa da satır-bazlı bir onay/seçim yok, tümü ya da hiçbiri değil, veri-bütünlüğü otomatiği (R2'nin düzeltmesiyle tutarlı) | AI-13 |
| R8 | **Teslim edildi (FF-54).** Alerjen alanı hiçbir ekranda onay kontrolü olarak render edilmez — artık ZORLAYICI bir kapısı var: `resources/js/components/ai-allergen.guard.test.ts` tüm `.tsx` kaynağını tarar, backend'in `FORBIDDEN_FIELDS` listesindeki bir adı ya da "alerjen + onay kutusu" aynı satırda bulursa kırılır. Backend kilidi taslağı reddediyordu ama ekranın kendi başına bir "alerjensiz (AI onayladı)" kutusu ÇİZMESİNİ engellemiyordu | AI-14 |
| R9 | **Teslim edildi (FF-54).** Yeni uç: `GET .../ai/availability` (sağlayıcı çağrısı yapmaz, bedava, hız sınırsız). Ekran tıklamadan ÖNCE sorar; kullanılamıyorsa eylemi hiç göstermez ve yerine SEBEBE ÖZEL tek satır koyar (kapalı / bütçe bitti / sağlayıcı yok — üçü üç farklı çözüme işaret eder). **Bilinmeyen durum "kapalı" sayılmaz**: istek başarısız olursa eylem gizlenmez, iyimser davranır — ağ yavaş diye çalışan bir özelliği gizlemek, sahibin onu bir daha aramamasına yol açardı. §5.1'in ölçülmemiş sorusu (gizle mi, gri düğme+tooltip mi) hâlâ açık; bu teslimat o soruyu KAPATMAZ, yalnız "hiçbir şey söylememe" seçeneğini eler | AIV-07 (§5'e bkz.) |

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
| R16 | **Kapı eklendi (FF-54).** Gömme sonuçları ileride önbelleğe alınırsa, önbellek anahtarı `workspace_id` içermek ZORUNDA. İki yeni test bunu artık kanıtla tutuyor: (a) iki workspace'te AYNI ürün adı varken çapraz eşleşme oluşmuyor — 404 testi yalnız YETKİYİ kanıtlıyordu, bu EŞLEŞTİRMENİN kendisini kanıtlıyor; (b) çağrıdan sonra adında `embedding` geçen hiçbir tablo doğmuyor, yani bir gün paylaşılan bir önbellek eklenirse test kırılır ve karar bilinçli alınır | AI-09 (bugün kapalı, önbellek eklenirse geçerli) |

### 4.5 Non-functional

| # | Gereksinim | Kaynak |
| --- | --- | --- |
| R17 | Üç ekran de WCAG 2.2 AA (`docs/06` §8, `modules/ai-platform.md` AI Capability Manifest) | standart |
| R18 | Üç ekran de i18n kataloğundan okur, hardcoded dize yok (`UntranslatableStringScanner` kapısı) | standart |
| R19 | İnceleme ekranlarında hiçbir satır **otomatik** uygulanmaz — her satır ayrı insan eylemi gerektirir | `docs/01` §3, AI-13 |

---

## 4b. FAZ 3 — çok-hesap, BYOK, yeni sağlayıcılar

Bu bölüm ikinci uygulama turunda (FF-54…FF-61) eklendi. Aynı disiplin: her
gereksinim bir UNK satırına ve/veya bir kullanıcı yolculuğu adımına bağlı.

### 4b.1 UNK → kullanım senaryosu (yeni satırlar)

#### AI-06 — Yapışkanlığın güvenlik sınırı sanılması (Yüksek)

> **Verilen** bir tenant'ın istekleri hep aynı hesaba gidiyor,
> **ne zaman** biri "demek ki izolasyon bununla sağlanıyor" diye düşünür,
> **o zaman** yanılmış olur — ve bu yanılgı, yapışkanlık bir gün
> performans için değiştirildiğinde sessiz bir sızıntıya dönüşür.

**Teslim edildiği hâl (FF-58):** ikisi ayrı mekanizma ve bu KODDA görünür.
İzolasyon `where('scope', ...)` ile SORGUDA — başka bir tenant'ın BYOK
satırı aday havuzuna hiç gelmez. Yapışkanlık ayrı bir tabloda
(`ai_connection_assignments`) ve yalnız önbellek/bağlam içindir. Yapışkanlık
tablosunu silmek izolasyonu bozmaz; bu, ikisinin gerçekten ayrı olduğunun
sınanabilir kanıtıdır.

#### AIV-03 — BYOK'un hangi plan katmanında açılacağı (owner kararı)

> **Verilen** bir tenant kendi API anahtarını getirmek istiyor,
> **ne zaman** bunu kendi panelinden yapmak ister,
> **o zaman** hangi plandan itibaren buna izin verildiği bir ÜRÜN kararıdır.

**Teslim edildiği hâl:** yapısal taraf hazır (kapsam alanı, izolasyon,
yönlendirme). Tenant'ın KENDİ ekranı (`/settings/ai`) bilinçli olarak
yazılmadı — `docs/96` onu Faz 6'ya koyuyor ve plan katmanı kararı owner'ın
(§5). Bugün BYOK bağlantısını superadmin, tenant adına ekler.

#### AIV-06 — Sağlık kontrolünün kendi maliyeti (ölçülmedi)

> **Verilen** her sağlık yoklaması bir sağlayıcı çağrısıdır,
> **ne zaman** bu düzenli/otomatik çalışır,
> **o zaman** kontrolün kendisi bir maliyet kalemi olur.

**Teslim edildiği hâl (FF-58/FF-60), kapsamı daraltılarak:** periyodik bir
sağlık taraması BİLEREK yapılmadı — maliyeti ölçülmemiş bir işi otomatiğe
bağlamak, tam da AIV-06'nın uyardığı şey olurdu. Onun yerine iki ücretsiz
kaynak: (a) gerçek çağrıların sonucu (başarı→sağlıklı, 401/403/429/5xx/ağ→
sağlıksız — hiçbiri EK çağrı değil), (b) superadmin'in elle tetiklediği
model-listesi yoklaması (token harcamaz). Periyodik tarama, maliyeti
ölçüldükten sonra ayrı bir karardır.

#### Tüketici abonelik yasağı — artık şemanın kendisi

> **Verilen** superadmin elinde bir Claude.ai Pro aboneliği var,
> **ne zaman** onu kasaya girmeye çalışır,
> **o zaman** girecek bir ALAN bulamaz.

**Teslim edildiği hâl (FF-55):** hiçbir sağlayıcı şemasında e-posta/parola/
oturum alanı yok ve bir test bunu kilitliyor. Kural bir belgede değil,
yapıda (`modules/ai-provider-account-vault.md` §Tüketici abonelik yasağı).

### 4b.2 Kullanıcı yolculukları

#### Yolculuk E — Superadmin ikinci bir hesap ekler (teslim edildi — FF-56/57)

**Persona:** Platform superadmin.

1. `/platform/credentials` açılır; görünüm artık **sağlayıcı → altında N
   bağlantı kartı** (FF-57).
2. Panel genelinde tek bir **"+ Add a connection"** düğmesi — sağlayıcı
   başına değil.
3. Sağlayıcı seçilene kadar ortak alanlar (ad, kapsam) **gerçek `disabled`**;
   sağlayıcıya özel alanlar hiç çizilmez.
4. Sağlayıcı seçilince o sağlayıcının kendi şeması açılır
   (`CredentialProvider::fields()`); ortak alanlar etkinleşir.
5. Ad zorunlu — sır görünmediği için iki kartı ayırt eden tek şey odur.
6. Kaydet → kart listede belirir; durum ve **sağlık** rozetiyle.
   "Not checked yet" ile "Healthy" ayrı gösterilir.
7. **"Test this connection"** (FF-60) tek ve ücretsiz bir çağrıyla anahtarı
   doğrular; sonuç sağlığa yazılır.

**Kapı:** `ConnectionApiTest` (11), `ConnectionProbeTest` (7),
`ProviderCredentialsPage.test.tsx` (9).

#### Yolculuk F — Bir restoran kendi anahtarını getirir (yapısal taraf teslim edildi — FF-56/58)

**Persona:** Platform superadmin, tenant adına (tenant'ın kendi ekranı Faz 6).

1. Yeni bağlantı formunda kapsam **"A customer's own key (BYOK)"** seçilir.
2. Workspace kimliği sorulur — yalnız bu kapsamda görünür.
3. O anahtar **yalnız** o workspace'in isteklerine hizmet eder; başka bir
   tenant'ın aday listesinde **asla** görünmez (sorgu sınırı, filtre değil).
4. Tenant'ın kendi anahtarı platform hesabını **yener** — yoksa girmenin bir
   etkisi olmazdı.
5. Anahtar kapatılırsa **ürün durmaz**: platform hesabına döner.
   (Faturalandırma politikası ayrı bir konudur ve burada karar verilmez.)

**Kapı:** `CredentialConnectionsTest` (12), `StickyAccountRoutingTest` (9).

#### Yolculuk G — Sahip dört sayfalık menüyü okutur (teslim edildi — FF-61)

**Persona:** Brand/Location Manager.

1. Media sayfasında dört sayfa `menuImportSource` slotuna yüklenir.
2. Menü Kataloğu'nda "Import from a photo (AI)" açılır; liste artık **çok
   seçimli** (onay kutuları — seçilenlerin hepsi aynı anda görünür kalmalı).
3. "Read these photos" → tek istek, dört okuma.
4. **Kısmi başarısızlık:** bulanık sayfa ayrıca listelenir, diğerlerinin
   sonucu incelemeye girer.
5. Tek "Add these to the draft" — dört ayrı onay düğmesi aynı kararı dört
   kez sormak olurdu.

**Kapı:** `BulkMenuImportTest` (9), `MenuCatalogWorkspace.aiImport.test.tsx` (9).

### 4b.3 Gereksinimler

| # | Gereksinim | Kaynak |
| --- | --- | --- |
| R20 | **Teslim edildi (FF-55).** Kasa Anthropic, Kimi ve "özel uç nokta"yı tanır. Qwen kendi case'ini ALMAZ — `local`/OpenAI-uyumlu sınıfına girer (`docs/51` §3.2, §4.5) | `docs/95` Faz 3 |
| R21 | **Teslim edildi (FF-55).** Hiçbir şemada tüketici-giriş alanı (e-posta/parola/oturum) yok — testle kilitli | vault modülü |
| R22 | **Teslim edildi (FF-56).** Bir sağlayıcı → N adlandırılmış bağlantı; göç veriyi taşır ve bu ayrıca test edilir (boş DB'de kopyalama döngüsü hiç çalışmaz, dolayısıyla "yeşil paket" onu kanıtlamaz) | `docs/95` Faz 3 §Şema evrimi |
| R23 | **Teslim edildi (FF-57).** Panel: sağlayıcı → N kart; tek "ekle" düğmesi; sağlayıcı seçilene kadar gerçek `disabled`; ad zorunlu | `docs/95` Faz 3 §UX sözleşmesi |
| R24 | **Teslim edildi (FF-58).** Tenant→bağlantı eşlemesi yapışkan; rastgele dağıtım yok | `docs/14` §2a |
| R25 | **Teslim edildi (FF-56/58).** BYOK yapısal izolasyon — sorgu sınırı, filtre değil; yapışkanlıktan AYRI mekanizma | AI-06 |
| R26 | **Teslim edildi (FF-58).** Sağlıksız bağlantı havuzdan geçici düşer, silinmez; yalnız DEĞİŞİM denetime yazılır. Hesaba ait olmayan hata (400/404/422) hesabı düşürmez | `docs/14` §2a |
| R27 | **Teslim edildi (FF-59).** Anthropic + OpenAI-uyumlu adaptörler; metin zinciri dört adaylı. Yetenek↔sağlayıcı eşlemesi yeteneğe göre — metin-yalnız bir sağlayıcı görme rotasını AÇMAZ | `docs/96` Faz 3 |
| R28 | **Teslim edildi (FF-60).** Sınanmamış özel uç nokta yönlendirme adayı DEĞİLDİR; bilinen sağlayıcı sınanmadan da adaydır | `docs/51` §4.5 |
| R29 | **Teslim edildi (FF-61).** Toplu okuma; kısmi başarısızlık tolere edilir; istek başına 10 fotoğraf sınırı | `docs/96` Faz 3 |
| R30 | **Teslim edildi (FF-75, 2026-09-04).** Yapışkanlığa `purpose` boyutu eklendi (`ai_connection_assignments.purpose`); toplu trafik `purpose=batch` etiketli bağlantıya yapışır; kiracı başına dakikalık bütçe `RateLimiter('ai-batch')`. Ağırlıklı/maliyet yönlendirmesi hâlâ yok (ölçüm önce) | `docs/adr/ADR-L11` |

## 5. Owner kararı gerekir mi?

1. **AIV-07'nin ölçülmemiş sorusu**: AI kapalıyken düğmenin tamamen
   yok olması mı, yoksa gri/devre-dışı+tooltip mi daha iyi öğrenilebilirlik
   sağlar — bu bir tasarım/marka kararı, pilot kullanıcı testi gerektirir
   (`docs/16` AIV-07 zaten "MVP Exit Gate sonrası" diye işaretli).
2. **R12'deki "yedek sağlayıcı" etiketinin tam metni** — kullanıcıya
   "farklı bir yapay zeka kullanıldı" mı, yoksa teknik detay vermeden
   "biraz gecikti ama tamamlandı" mı denecek — marka sesi kararı.

3. **BYOK hangi plan katmanında açılır** (`docs/16` AIV-03) — yalnız
   Enterprise mi, daha erken bir ücretli katman mı? Yapısal taraf hazır
   (FF-56/58); tenant'ın kendi ekranı bu karar verilene kadar yazılmadı.
4. **Anthropic/Kimi anahtarlarının bütçesi** — platform-owned tek havuz mu,
   özellik başına ayrı tavan mı (`docs/95` §Owner kararı 2).
5. **Özel uç nokta (Qwen vb.) barındırma kararı** — `vps-ai` mi,
   `private-gpu` mu (`docs/51` §4.5 üç profil).

Geri kalan tüm gereksinimler (R1-R11, R13-R30) geri döndürülebilir teknik
kararlardır, MASTER'da kalır.

## 6. Kanonik sahiplik

Bu belge UNK kayıtlarını (`docs/16`) tekrar etmez, yalnız ilgili satırları
somutlaştırır. Persona/rol tanımları `docs/02`'de, AI yetenek/model/hesap
eşlemesi `docs/96`'da, faz sırası `docs/95`'te kanoniktir. **R1-R30** burada
kanoniktir — bir sonraki uygulama turu bu numaralara referans verir.
R1-R19 ilk turda (Faz 2 arayüz boşluğu, FF-49…FF-53), R20-R30 ikinci turda
(Faz 3 çok-hesap/BYOK, FF-54…FF-61) yazıldı.
