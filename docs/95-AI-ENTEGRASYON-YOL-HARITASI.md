# 95 — AI entegrasyon yol haritası (adlandırılmış ek plan: `AI-INTEGRATION-v1`)

**Bu belge sabit 38-WP payda sayacını DEĞİŞTİRMEZ** (`docs/17` §4). Kapsam
AI'ya özgü genişlediği için önceki sayaç geriye yazılmıyor — bu, o
adlandırılmış plan: adı `AI-INTEGRATION-v1`, sekiz fazı var ve **her fazı
tam olarak bir stage'e** eşlenir (`URL-SEO-v1`/`docs/39`, `I18N-RUNTIME-v1`/
`docs/40`, `DESIGN-2030-v1`/`docs/41` ile aynı kalıp).

## Neden bir ek plan gerekti

`docs/26` §1 matrisi zaten AI Platform ve AI Provider Account Vault için bir
stage iskeleti taşıyor: Vault "Stage 1 kapalı → Stage 2 tek platform hesabı →
Stage 3 çok-hesap/BYOK", AI Platform "Stage 1 çekirdek+tek dikey → Stage 2
genişleyen yetenekler". Ama sekiz stage anlatı dokümanının (`docs/18`–`docs/25`)
**hiçbirinin** "Module increments" bölümü bugüne kadar AI'dan söz etmiyordu —
sahibin okuduğu, restoran yolculuğuyla anlattığı dokümanlarda bu boşluk vardı.
Bu plan onu kapatır: her stage'e **somut özellik**, **UX/mimari karar** ve
**tetikleyici** bağlar.

## Bu planın üzerine oturduğu taban — mevcut AI politikaları

Aşağıdakiler burada **tekrar edilmez**, yalnız özetlenir; kanonik kaynak
parantez içindedir. Bu plan bu kuralları **değiştirmez**, onların üstüne
somut özellik/takvim ekler.

- **AI kapalıyken tam determinizm** — hiçbir kritik iş akışı AI'ya bağımlı
  değil; kill switch anlık ve global (`docs/14` §1, `modules/ai-platform.md`).
- **Sekiz yetenek portu** — `StructuredGenerationPort`, `OcrPort`,
  `EmbeddingPort`, `VisionExtractionPort`, `ClassificationPort`,
  `RerankPort`, `ToolIntentPort`, `EvaluationPort` (`docs/51` §3.1). Tek
  port değil, çünkü şema/maliyet/gecikme/gizlilik profilleri karışmaz.
- **Sağlayıcı hiyerarşisi** — `Provider → Connection (N adet) → ModelDeployment
  → CapabilityRoute` (`modules/ai-provider-account-vault.md`). Hesap sayısı
  koda gömülmez, çalışma zamanı yapılandırmasıdır.
- **Çoklu hesap kuralı (2026-08-27 eki)** — tenant→hesap eşlemesi **yapışkan**
  olmalı (prompt cache ve oturum bağlamı hesaba bağlıdır); rastgele dağıtım
  yasak; sağlıksız hesap havuzdan düşer, düştüğünde audit'e yazılır
  (`docs/14` §2a).
- **Tüketici abonelik yasağı** — ChatGPT Plus/Pro, Claude.ai Pro/Max girişi
  **hiçbir koşulda** üretim kimlik bilgisi sayılmaz; yalnız resmi API
  organization/project/service-account/key kabul edilir
  (`modules/ai-provider-account-vault.md` §Tüketici abonelik yasağı).
- **İnsan onayı zorunlu** — destructive/publish/payment/permission action'ları
  onaysız asla çalışmaz; bu kural backend'de tool-allowlist üzerinden
  zorlanır, yalnız bir UX tavsiyesi değildir (`docs/14` §4).
- **Bütçe tenant başına, sıfır = kapalı** — global tavan yok; dolunca AI
  durur, ürün durmaz (`docs/51` UNK-06, bu oturumda `AiBudgetLedger` ile
  koda döküldü).
- **Kasa çekirdeği ZATEN teslim edildi** — bu oturumda beş fazlık bir program
  (FF-37…FF-41) şunu kurdu: şifreli sır deposu, superadmin write-only API,
  Mailgun'un kasadan tüketimi, `/platform/credentials` GUI paneli, OpenAI
  görüntü adaptörü (gerçek API'ye karşı **doğrulanmadı** — bkz. `docs/94`).
  Bu, aşağıdaki Faz 2'nin **durumu**dur, planı değil.

## Sağlayıcı kaydı — bugün ve hedef

Bugün (`app/Domain/Platform/Credential/CredentialProvider.php`) dört
sağlayıcı tanımlı: `mailgun`, `iyzico`, `openai`, `gemini`. Sorulan
listedeki dördü ayrı ayrı nerede duruyor:

### Zaten kurulu — sorulan listede, kasada, aktif

**Gemini** ve **OpenAI** ikisi de bugün kasada (`CredentialProvider::Gemini`,
`CredentialProvider::OpenAi`), FF-37…FF-41'de teslim edildi. Yalnız
"kayıtlı" değiller — **Gemini, görüntü-okuma zincirinde birincil aday**:
`docs/51` §4b.1 sırayı açıkça veriyor, "görme yeteneği Gemini'de başlar
(ucuz, güçlü), yetmezse OpenAI, en son Claude." Bugün kod yalnız OpenAI
adaptörünü çalıştırıyor (`OpenAiVisionProvider`, FF-41) — Gemini'nin kendi
adaptörü henüz yazılmadı, ama kasadaki yeri ve sıradaki önceliği (OpenAI'dan
ÖNCE) zaten kayıtlı. Bunu aşağıya, "eksik" tablosunun dışında ayrı bir madde
olarak ekliyorum çünkü bu bir sağlayıcı-ekleme işi değil, **var olan
sağlayıcının ikinci bir portta (VisionExtractionPort) devreye alınması** —
küçük iş, Faz 3'ü beklemez.

### Sorulan listede olup henüz eksik

| Sorulan | Doktrindeki karşılığı | Faz |
| --- | --- | --- |
| Claude / Anthropic | Yeni `CredentialProvider::Anthropic` — doktrinde zaten adı geçiyor (`docs/51` §3.2 `Provider: anthropic \| google \| openai \| local`) | Faz 3 |
| Kimi (K3 dahil) | Yeni `CredentialProvider::Kimi` — doktrin bunu adıyla zaten aday gösteriyor (`docs/14` §2: "OpenAI, Claude, Gemini, Kimi, private/self-host…") | Faz 3 |
| **Qwen** | **Kendi başına bir sağlayıcı DEĞİL.** Doktrin onu `local`/self-host/OpenAI-uyumlu-uç-nokta sınıfına koyuyor (`docs/51` §3.2, §4.5 `vps-ai`/`private-gpu` profilleri). Dropdown'da "Qwen" değil, **"Özel uç nokta (OpenAI-uyumlu)"** seçeneği olur; superadmin o seçeneğin `base_url` alanına Qwen'in kendi uç noktasını (kendi barındırdığı ya da bir bulut sağlayıcıdan aldığı) yazar. | Faz 3 |

Bu ayrım kozmetik değil: Qwen'i "Gemini gibi" bir sağlayıcı olarak modellemek,
o hesabın sağlık kontrolü/kota davranışının OpenAI-uyumlu olduğunu **varsayar**
— ama uyumluluk garanti değildir (`docs/51` §4.5 "tam uyumluluk varsayılmaz").
Doğru model, onu genel "özel uç nokta" sınıfına koyup bir **uyumluluk katmanı**
ile sınamaktır.

---

## Faz 1 — AI Capability Plane çekirdeği (Stage 1 — MVP)

**Durum: teslim edildi** (bu oturumdan önceki paketler — FF-32…FF-34).

- Sekiz port arayüzü, `FakeProvider` ile sağlayıcısız uçtan uca CI akışı.
- İlk dikey: **fotoğraf/PDF → menü taslağı**, insan onaylı (`ExtractMenuFromImage`
  → `AiArtifact` → `ApplyMenuArtifact`), `applied_at` boş başlar.
- Tenant başına aylık bütçe, kill switch, `ai_invocations` denetim tablosu.

**Tetikleyici:** yok, tamam. Sonraki fazın önkoşulu.

## Faz 2 — Tek platform hesabı + sağlayıcı kasası (Stage 2 — Post-MVP)

**Durum: teslim edildi** (bu oturum — FF-35…FF-41).

- Mailgun taşıma katmanı, sunucu `.env`'inden kasaya geçiş (FF-35/36).
- Şifreli kasa çekirdeği: iki port (admin sırrı geri okuyamaz, resolver
  çözer), kasa > env önceliği (FF-37).
- Superadmin write-only API + sırsız audit (FF-38).
- Mailgun ve OpenAI görüntü okuma artık **kasadan** besleniyor (FF-39, FF-41).
- `/platform/credentials` GUI paneli — her sağlayıcı bir kart, sır yalnız
  `••••son4` maskesiyle görünür (FF-40).

**Genişleyen yetenekler:**

- **Gemini görüntü-okuma adaptörü teslim edildi** (FF-45) —
  `GeminiVisionProvider`, `docs/51` §4b.1'in sıraladığı gibi artık
  OpenAI'dan ÖNCE denenir.
- **Ürün açıklaması taslağı teslim edildi** (FF-46) — `GeminiTextProvider`
  (yeni `StructuredGenerationPort` adaptörü), `Capability::ProductDescription`,
  onay hattı (`GenerateProductDescriptionDraft` → insan onayı →
  `ApplyProductDescriptionDraft`, `renameMenuItemProduct`'ın var olan
  yolunu kullanır). Alerjen alanına asla yazmaz — şema doğrulayıcının
  yasak-alan listesi ve ayrı bir test bunu kilitler.
- **Çeviri taslağı (`opt-22-ai-translation`) ERTELENDİ, atlanmadı.** Gerçek
  bulgu: bu yeteneğin hedefi — çok-dilli ürün/kategori içeriği — bugün
  yazılı değil. `modules/opt-04-multi-language-content.md` (çeviri
  tablosu, dil sekmeleri, `missing→draft→reviewed→published` durum
  makinesi) kendisi "PLANNING ONLY" ve OPT katalogda M1'dir — Faz 2'nin
  bir parçası değil. AI taslağı üretmenin bir anlamı yok, yazacağı bir
  alan yok. **Doğru sıra: OPT-04 önce inşa edilmeli**, AI taslağı ondan
  sonra üstüne biner. `docs/96`'da bu netleştirildi.
- **Taksonomi yinelenen-terim tespiti teslim edildi** (FF-47) —
  `GeminiEmbeddingProvider` (`EmbeddingPort`), `DetectDuplicateProductNames`
  (kosinüs benzerliği, salt okunur öneri — hiçbir kaydı birleştirmez/silmez).
  **`docs/51` §4.4'ten bilinçli bir sapma:** yerel-first şart koşuluyordu
  ama `ai-local` sidecar bugün yok (§3.5) — Gemini geçici bulut yedeği;
  port aynı kaldığı için `vps-ai` kurulunca yalnız binding değişir.

**Faz 2 fiilen tamam** — çeviri taslağı hariç, o OPT-04'ün gerçek kapsamı
belirlenene kadar bilinçli olarak bekliyor.

**Yol boyunca bulunan iki gerçek kusur (ikisi de düzeltildi):**
1. FF-46 sırasında: `ConfiguredAvailability` yeni yeteneklerin adını
   `vaultServes()` allowlist'ine eklemeden "paid but doesn't work" kalıyordu
   — FF-34'ün dotted-config-key sınıfıyla aynı arıza biçimi, bu sefer
   eksik bir liste girdisi olarak. Artık her yeni gerçek adaptör eklenirken
   bu listenin güncellenmesi gerektiği docblock'ta açıkça yazıyor.
2. Faz 2 boyunca üç kez aynı iki-dal (branch) çakışması yaşandı — paralel
   FF-45/46/47 dalları aynı satırlara (`AppServiceProvider` binding bloğu,
   `vaultServes()` allowlist'i, route dosyası, FROZEN route imzaları)
   ekleme yaptı. Hiçbiri veri kaybı değildi — git birleştirme çakışması,
   elle çözüldü, her çözümden sonra tam paket yeniden koşturuldu.

## Faz 3 — Çok-hesap / BYOK + yeni sağlayıcılar (Stage 3 — GTM)

**Durum: planlı, henüz başlanmadı.** `docs/26`'nın kendi sözü tam burada:
"çok-hesap/BYOK". Sorulan UX'in gerçek karşılığı bu fazdır.

### Şema evrimi

Bugünkü `platform_credentials.provider` sütunu **unique** — yani bir
sağlayıcının kasada yalnız **bir** satırı olabilir. "N tane hesap ekle"
düğmesi bu kısıtı kaldırmadan çalışamaz. Gerekli değişiklik:

```
platform_credentials.provider  UNIQUE  →  platform_credential_connections
  id, provider, label (superadmin'in verdiği ad, örn. "OpenAI — Menü İçe Aktarma"),
  scope (platform_owned | tenant_byok), tenant_id (yalnız byok'ta dolu),
  secret_ciphertext, plain_fields, secret_hints, state, health_status,
  last_health_check_at, last_rotated_at, set_by_user_id
```

Mevcut `PlatformCredentialAdminPort`/`CredentialResolverPort` ayrımı ve
`CredentialProvider` enum'u **korunur** — yalnız "bir sağlayıcı → bir kayıt"
varsayımı "bir sağlayıcı → N bağlantı" olur (`Provider → Connection`
hiyerarşisi, `modules/ai-provider-account-vault.md`).

### Superadmin sağlayıcı ekleme akışı — UX sözleşmesi

Sorulan akış tam olarak şu şekilde koda dökülür:

1. **"+ Yeni bağlantı ekle"** düğmesi — sağlayıcı başına değil, panel
   genelinde tek düğme. Basıldığında yeni bir satır/form açılır.
2. **Sağlayıcı dropdown'u** — `CredentialProvider::cases()` + "Özel uç nokta
   (OpenAI-uyumlu)" seçeneği. Seçilene kadar boş.
3. **Ortak alanlar (bağlantı etiketi, kullanım amacı/routing etiketi)
   `disabled` durumda görünür** — sağlayıcı seçilmeden bu alanlar anlamsızdır
   (hangi sağlayıcıya ait bir etiket olduğu belirsiz). CSS tarafı: gerçek
   `disabled` özniteliği (yalnız görsel değil — erişilebilirlik için gerçek
   `:disabled` pseudo-class, ekran okuyucu da "devre dışı" der).
4. Sağlayıcı seçilince **koşullu form alanları** açılır — her sağlayıcının
   kendi şeması (`CredentialProvider::fields()` zaten bu şekli taşıyor:
   OpenAI → `api_key, base_url, organization, project`; Anthropic → `api_key,
   base_url`; Gemini → `api_key, base_url`; Kimi → `api_key, base_url`; Özel
   uç nokta → `base_url, api_key` opsiyonel). Ortak alanlar aynı anda
   `enabled` olur.
5. Kaydet → `PUT /api/admin/credentials/{provider}/connections` (yeni uç,
   mevcut `PUT /api/admin/credentials/{provider}`'ın N-hesap karşılığı) →
   sır şifreli yazılır, liste ekranında yeni kart belirir: sağlayıcı adı +
   superadmin'in verdiği etiket + durum rozeti (aktif/sağlıksız/kapalı).
6. Panelin liste görünümü artık **sağlayıcı → altında N bağlantı kartı**
   hiyerarşisiyle gruplanır (bugünkü düz "her sağlayıcı bir kart" görünümü
   yerine).

Bu, `docs/36-EXTERNAL-DESIGN-CORPUS.md`'deki token/foundations katmanına
bağlı kalınarak, mevcut `ProviderCredentialsPage.tsx`'in bir üst-bileşene
(bağlantı listesi + "ekle" formu) bölünmesiyle uygulanır — sıfırdan bir
tasarım dili icat edilmez.

### Yapışkanlık, sağlık, routing

- **Tenant → hesap eşlemesi yapışkan** (`docs/14` §2a) — bir tenant'ın ilk
  isteği hangi bağlantıya giderse, sonrakiler de oraya gider; önbellek/bağlam
  bu yüzden korunur.
- **Sağlık kontrolü** düzenli çalışır (`ProviderAccountHealthChanged` event'i,
  `modules/ai-provider-account-vault.md` §ECA hooks); sağlıksız bağlantı
  routing adaylarından **geçici** çıkar, otomatik silme/iptal **yapılmaz**
  (insan onayı gerekir).
- **BYOK**: tenant Owner kendi bağlantısını `scope: tenant_byok` ile ekler;
  bu bağlantı **hiçbir koşulda** başka tenant'ın routing adayları arasında
  görünmez — yapısal izolasyon, filtre değil.
- **Fallback görünür olur** (`docs/51` UNK-03): bir bağlantı sağlıksızsa ve
  istek yedeğe düşerse, öneri ekranında "bu öneri yedek bağlantıdan geldi"
  ibaresi çıkar.

**Tetikleyici:** Stage 3 GTM'e giriş — ya da daha erken, eğer Faz 2'nin tek
hesabı gerçek trafikte kotaya/kesintiye takılırsa (bkz. önceki tur, paylaşılan
kota vakası).

## Faz 4 — Görünür maliyet + geri bildirim özeti (Stage 4 — PMF)

**Durum: planlı.** `docs/26` bu stage'de Vault/Platform için ayrı satır
vermiyor (önceki seviye korunur) — ama gerçek fırsat var.

- Superadmin paneline **hesap bazlı maliyet/gecikme/hata oranı** paneli
  eklenir (`modules/ai-provider-account-vault.md` §Observability zaten bunu
  şart koşuyor, UI'ı yoktu). "Bu ay hangi bağlantı ne kadar harcadı" sorusu
  panelden cevaplanır — Faz 3'ün routing kararlarını **görünür** kılar.
- İlk `advisory` StructuredGenerationPort kullanım örneği devreye girer:
  **geri bildirim duygu/tema özeti** (`opt-25-feedback-nps`, `docs/32`'de
  zaten kayıtlı).

**Tetikleyici:** Faz 3'ün çok-hesap yapısı en az bir tam ay gerçek trafik
görmüş olmalı — ölçülmemiş bir maliyet panosu yanıltıcı olur.

## Faz 5 — Ölçekte yönlendirme politikası (Stage 5 — Growth)

**Durum: planlı.**

- `docs/26`'nın bu stage için verdiği modüller (Custom Branding, GA4/Yandex
  inbound) yanına: **weighted/cost/latency routing** gerçek anlamda devreye
  girer (`modules/ai-provider-account-vault.md` §Feature × provider/model ×
  account × policy routing) — Growth'tan önce trafik hacmi bu politikaları
  anlamlı kılacak kadar büyük değildi.
- Trend/anomali içgörü anlatımı (`opt-06-advanced-analytics`, advisory).
- Görsel gömme (`EmbeddingPort`, `docs/51` §4.4'te "Stage 2 planlı" ama
  gerçek hacim burada oluşur) — görsel–ürün uyumu, pHash'in üstüne.

**Tetikleyici:** ölçülen aylık AI çağrı hacmi, tek-bağlantı-yeterli eşiğini
aşması (bu eşik `docs/51`'in kendi kuralı gereği ölçülmeden yazılmaz).

## Faz 6 — Kurumsal izolasyon (Stage 6 — Enterprise)

**Durum: planlı.**

- `docs/26`: SSO/SCIM, Integration Hub genişlemesi (`agentic_guarded`:
  webhook/API alan eşleme + sandbox test, `opt-13-pos-integrations` ile aynı
  desen).
- Kurumsal tenant için **ayrılmış (dedicated) bağlantı havuzu** — yapışkanlık
  tablosundaki üç sebepten ikisi (`docs/51` §3.3: veri ikametgâhı, sözleşme/
  kota taahhüdü) burada gerçek hale gelir; artık teorik değil.
- `private-gpu` dağıtım profili (`docs/51` §4.5) — enterprise tenant kendi
  altyapısında model çalıştırmak isterse.

**Tetikleyici:** ilk enterprise sözleşme, veri ikametgâhı ya da özel SLA şartı
taşıyor olmalı.

## Faz 7 — İşletim katmanı olgunluğu (Stage 7 — Maturity)

**Durum: planlı.**

- `docs/51` §3.5'in "bugün YOK" dediği katman burada tamamlanır: kuyruk-işçisi,
  dead-letter kuyruğu, devre kesici (circuit breaker), idempotency anahtarı —
  bunlar Faz 3'ün çok-hesap yükü altında zaten gerekli hale gelmiş olur.
- Prompt-injection/kalite eval seti otomatikleştirilir (`docs/16` AI-02) ve
  CI kapısına bağlanır — model/hesap değişikliği artık kör bir bahis olmaz
  (`docs/51` UNK-08).
- `docs/24`'ün kendi konusuyla aynı desen: kod değişikliği minimal, süreç/
  kanıt disiplini maksimal.

**Tetikleyici:** yok — bu, olgunluk stage'inin kendi doğal kapsamı.

## Faz 8 — Devir kanıtı (Stage 8 — Exit Ready)

**Durum: planlı.**

- Yeni özellik yok. `docs/25`'in kendi deseniyle aynı: mevcut AI kararlarının
  **belge/kanıt envanteri** derlenir — hangi model, hangi hesap, ne maliyet,
  hangi denetim izi. Bir alıcı/yatırımcı "AI maliyetiniz gerçekten kontrollü
  mü" sorduğunda cevap `docs/28`/`docs/29` zincirinden doğrudan çıkar.

**Tetikleyici:** yok — bu, devir hazırlığının kendi doğal kapsamı.

---

## Owner kararı gerekir mi?

Üç madde geri döndürülemez ya da ürün/marka kapsamı taşıyor, bunlar
sahibindir (kök yönetişim talimatı "Owner load" maddesiyle tutarlı):

1. **BYOK hangi plan katmanına açılır** — yalnız Enterprise mi, yoksa daha
   erken bir ücretli katmana da mı? (Faz 3/6 sırasını etkiler.)
2. **Anthropic/Kimi anahtarlarının kimin bütçesinden karşılanacağı** — bugünkü
   gibi platform-owned tek havuz mu, yoksa Faz 3'ten itibaren özellik başına
   ayrı bütçe mi?
3. **Özel uç nokta (Qwen vb.) için barındırma kararı** — `vps-ai` profilinde
   mi (bugünkü sunucu), yoksa ayrı bir `private-gpu` sözleşmesiyle mi
   (`docs/51` §4.5 üç profil).

Geri döndürülebilir teknik kararlar (şema tasarımı, routing algoritması,
sağlık kontrolü sıklığı) MASTER'da kalır, sahibe sorulmaz.

## İlerleme

Bu, `docs/17` §4'teki sabit 0/8 sayacından **ayrı** bir yerel takip
tablosudur (`fastDeliveryGenomeOverlay` ile aynı ilke — ayrı payda).

| Faz | Stage | Durum |
| --- | --- | --- |
| 1 | Stage 1 MVP | ✅ teslim edildi |
| 2 | Stage 2 Post-MVP | ✅ teslim edildi (kasa çekirdeği) — çeviri/açıklama/taksonomi yetenekleri planlı |
| 3 | Stage 3 GTM | ⬜ planlı — çok-hesap/BYOK, yeni sağlayıcılar, UX sözleşmesi bu belgede |
| 4 | Stage 4 PMF | ⬜ planlı |
| 5 | Stage 5 Growth | ⬜ planlı |
| 6 | Stage 6 Enterprise | ⬜ planlı |
| 7 | Stage 7 Maturity | ⬜ planlı |
| 8 | Stage 8 Exit Ready | ⬜ planlı |

## Kanonik sahiplik

Bu belge **yalnız** stage×AI eşlemesinin somut özellik/UX/tetikleyici
detayını taşır. AI mimarisi `modules/ai-platform.md` ve `docs/14`'te, hesap/
bağlantı spesifikasyonu `modules/ai-provider-account-vault.md`'de, 61
modülün AI duruşu `docs/32`'de, port matrisi ve unknown-unknowns `docs/51`'de
kanoniktir — bu belge onları tekrar etmez, yalnız birine bağlar.

**Her satırın tam model/hesap/mekanizma detayı** —
`docs/96-AI-YETENEK-VE-AJAN-KAYDI.md`'de kanoniktir: hangi LLM, hangi hesap,
hangi ekran, hangi port, hangi Skill/Agent. Bu belge fazı planlar, `docs/96`
fazın içindeki her satırı somutlaştırır.
