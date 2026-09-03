# 96 — AI yetenek ve ajan kaydı: hangi LLM, hangi hesap, nerede, ne için

**`docs/95`'in eki, ondan ayrı kanonik.** `docs/95` **fazı** planlar (ne
zaman); bu belge her fazdaki her yeteneği **somutlaştırır** (hangi model,
hangi hesap, hangi ekran, hangi mekanizma). Sahibin sorusu buydu: "hepsi
belirli mi? belirli olsun." Aşağıdaki her satır bir karardır, bir olasılık
değil — ama **geri döndürülebilir** kararlardır: model adı `config/ai.php`'de
yaşar, koda gömülmez (`docs/51` §4.3); bir satırı değiştirmek bir PR'dır, bu
belgeyi yeniden yazmak değil.

## Nasıl okunur

| Kolon | Anlamı |
| --- | --- |
| **Yetenek** | Kullanıcının (superadmin ya da restoran sahibi) gördüğü iş |
| **LLM + Model** | Hangi sağlayıcı, hangi model — `config/ai.php` içindeki gerçek anahtar |
| **Hesap** | Hangi bağlantı (`platform_credentials` satırı ya da Faz 3'ten sonra `platform_credential_connections` satırı) |
| **Konum** | Hangi ekran/modül tetikler |
| **Mekanizma** | Hangi yetenek portu (`docs/51` §3.1) + insan onayı var mı |

Bugün (Faz 1-2) her sağlayıcının kasada **yalnız bir** hesabı var — "Hesap"
kolonu bu yüzden çoğu satırda "Platform-owned tek hesap" yazıyor; bu bir
eksiklik değil, Faz 2'nin gerçek durumu. Faz 3'ten itibaren aynı sağlayıcının
birden çok adlandırılmış bağlantısı olabildiği için kolon somut etiket taşır.

---

## Faz 1 — Stage 1 MVP (teslim edildi)

| Yetenek | LLM + Model | Hesap | Konum | Mekanizma |
| --- | --- | --- | --- | --- |
| Fotoğraf/PDF → menü taslağı (CI/geliştirme) | `FakeProvider` — deterministik sahte, gerçek model değil | yok (anahtarsız çalışır) | Menü > İçe Aktar | `VisionExtractionPort`, insan onaylı (`ApplyMenuArtifact`) |

Gerçek sağlayıcı henüz bağlı değildi — kabul ölçütü tam buydu: "hiçbir
sağlayıcı bağlı değilken ürün TAM çalışır" (`docs/51` §3.6/1).

## Faz 2 — Stage 2 Post-MVP (teslim edildi + genişleyen)

| Yetenek | LLM + Model | Hesap | Konum | Mekanizma |
| --- | --- | --- | --- | --- |
| Fotoğraf/PDF → menü taslağı (gerçek) | **OpenAI `gpt-4o-mini`** — `config('ai.openai.vision_model')`, bugünkü kod varsayılanı (FF-41) | Platform-owned tek OpenAI hesabı (`/platform/credentials`) | Menü > İçe Aktar | `VisionExtractionPort`, insan onaylı; sağlayıcı hatası 502 |
| İletişim formu bildirimi | Mailgun (LLM değil, e-posta API'si) | Platform-owned tek Mailgun hesabı | `/contact` formu → sahibin e-postası | `Mail::mailer()`, insan onayı gerekmez (bildirim, mutasyon değil) |
| Fotoğraf/PDF → menü taslağı (**Gemini, birincil aday**) | **Gemini `gemini-flash-latest`** — `config('ai.gemini.vision_model')` (FF-45); gerçek API'ye karşı doğrulanmadı, `docs/94`/FF-41 ile aynı disiplin | Platform-owned tek Gemini hesabı | aynı ekran, OpenAI'dan **ÖNCE** denenir (`docs/51` §4b.1) | `VisionExtractionPort`, aynı onay zinciri; **teslim edildi (FF-45)** |
| **Çeviri taslağı — ERTELENDİ** (`opt-22`) | — | — | — | **Hedefi yok.** `opt-04-multi-language-content` (çeviri tablosu, dil sekmeleri) kendisi henüz yazılmadı — "PLANNING ONLY", OPT katalogda M1. AI taslağının yazacağı bir alan olmadan bu satır anlamsız; OPT-04 önce kurulmalı. |
| Ürün açıklaması taslağı (`opt-23`) — **teslim edildi (FF-46)** | **Gemini `gemini-flash-latest`** — `config('ai.gemini.text_model')`; doğrulanmadı, aynı disiplin | Platform-owned tek Gemini hesabı | Ürün düzenleme formu (`menu-items/{menuItem}/description-drafts`) | `StructuredGenerationPort`, insan onaylı; alerjen alanına asla yazmaz (test kilitli) |
| Taksonomi yinelenen-terim tespiti — **teslim edildi (FF-47)** | **Gemini `text-embedding-004`** — `config('ai.gemini.embedding_model')`. **Geçici bulut yedeği**: `docs/51` §4.4 yerel-first şart koşuyor ama `ai-local` sidecar bugün yok (§3.5); port aynı kaldığı için `vps-ai` kurulunca yalnız binding değişir | Platform-owned tek Gemini hesabı | `GET .../menu/duplicate-candidates`, Menü Kataloğu | `EmbeddingPort`, salt okunur öneri — hiçbir kaydı birleştirmez/silmez, insan karar verir |

**Neden Claude bu fazda hiçbir satırda yok:** Anthropic henüz kasada
(`CredentialProvider` enum'unda) tanımlı değil — bu Faz 3'ün işi. Bugün bir
geliştirici "Claude kullan" derse, sistem onu **çözemez**; bu bilinçli bir
sınır, eksik bir gözden kaçırma değil.

**Canlı yedek zinciri teslim edildi (FF-49, `docs/97` R10-R13).** Yukarıdaki
"Gemini, birincil aday" satırı artık yalnız bağlanma-anı tercihi değil:
`VisionExtractionRouter`/`StructuredGenerationRouter` Gemini çalışma
zamanında başarısız olursa aynı isteği OpenAI'a canlı yeniden dener,
sonucu `usedFallback` ile işaretler. UI tarafı henüz yok (bkz. `docs/97`).

## Faz 3 — Stage 3 GTM (planlı — çok-hesap/BYOK)

| Yetenek | LLM + Model | Hesap | Konum | Mekanizma |
| --- | --- | --- | --- | --- |
| Çeviri/açıklama taslağı — marka sesi yüksek risk | **Claude Sonnet 5** (`docs/51` §4b.2: "küçük model marka sesini düzleştirir") | Yeni bağlantı: "Anthropic — İçerik" | aynı ekranlar, artık iki model arasından seçilebilir | `StructuredGenerationPort`, insan onaylı |
| Ucuz taslak üretimi, yüksek hacim | **Kimi** (model adı henüz belirsiz — Kimi K3 ailesinden biri, superadmin karar verir) | Yeni bağlantı: "Kimi — Taslak" | Onboarding metni, bildirim şablonu gibi düşük-risk StructuredGenerationPort işleri | insan onaylı |
| **Toplu fotoğraf içe aktarma** (bir restoranın 40+ ürünü tek seferde) | OpenAI `gpt-4o-mini` (aynı model, **ayrı hesap**) | **Yeni, izole bağlantı:** "OpenAI — Toplu İçe Aktarma" | Menü > Toplu İçe Aktarma (yeni ekran) | Aynı `VisionExtractionPort`; izolasyonun amacı paylaşılan kotayı korumak, model değil |
| Kural taslağı + sandbox simülasyonu (`core-eca-rules`) | **Claude Opus 5** — bkz. Agents §1 "Kural Taslağı Ajanı" | Yeni bağlantı: "Anthropic — Otomasyon" (yüksek-stake işler ayrı izlenir) | Otomasyon Stüdyosu (ECA kural editörü) | `ToolIntentPort`, sandbox, **her zaman insan onaylı** |
| POS alan eşleme + sandbox test (`opt-13`) | **Claude Opus 5** — bkz. Agents §2 "Entegrasyon Eşleme Ajanı" | aynı "Anthropic — Otomasyon" bağlantısı | Entegrasyonlar > POS Kurulumu | `ToolIntentPort`, sandbox, insan onaylı |
| Özel uç nokta (Qwen vb.) | Superadmin'in girdiği model — sistem model adını bilmez, yalnız `base_url`'i çağırır | Yeni bağlantı: "Özel uç nokta" (OpenAI-uyumlu) | `/platform/credentials` — "Özel uç nokta" seçeneği | Uyumluluk katmanından geçer, hangi portları desteklediği test edilmeden aday olmaz |

## Faz 4 — Stage 4 PMF (planlı)

| Yetenek | LLM + Model | Hesap | Konum | Mekanizma |
| --- | --- | --- | --- | --- |
| Geri bildirim duygu/tema özeti (`opt-25`) | **Gemini Flash sınıfı** (kısa, düşük risk, yüksek hacim) | Platform-owned Gemini hesabı (Faz 2'den beri var) | Geri Bildirim panosu | `StructuredGenerationPort`, advisory — hiçbir alana yazmaz, yalnız özet gösterir |
| Hesap bazlı maliyet/gecikme panosu | (LLM değil — mevcut `ai_invocations` verisinin görselleştirilmesi) | tüm bağlantılar, tek ekranda | `/platform/credentials` yeni "Gözlemlenebilirlik" sekmesi | okuma-yalnız, onay gerekmez |

## Faz 5 — Stage 5 Growth (planlı)

| Yetenek | LLM + Model | Hesap | Konum | Mekanizma |
| --- | --- | --- | --- | --- |
| Trend/anomali içgörü anlatımı (`opt-06`) | **Claude Sonnet 5** (orta akıl yürütme, veri yorumlama) | "Anthropic — İçerik" bağlantısı (Faz 3'ten beri var) | Gelişmiş Analitik panosu | `StructuredGenerationPort`, advisory |
| Görsel gömme (ürün–görsel uyumu) | Yerel gömme modeli (görsel varyantı) | Hesap yok, yerel | Medya Yöneticisi, arka plan | `EmbeddingPort` |
| Weighted/cost/latency routing devreye girer | (routing algoritması — model seçimi değil) | tüm bağlantılar arasında | `AccountRoutingPort` (bkz. Skills §1) | otomatik, salt-okunur karar |

## Faz 6 — Stage 6 Enterprise (planlı)

| Yetenek | LLM + Model | Hesap | Konum | Mekanizma |
| --- | --- | --- | --- | --- |
| Kurumsal tenant AI işleri | Tenant'ın BYOK bağlantısı — model tenant'ın kendi tercihi | **Tenant-scoped, izole** (başka tenant'ın adayları arasında asla görünmez) | Tenant'ın kendi `/settings/ai` ekranı (yeni) | Aynı portlar, tenant BYOK izolasyon kuralı |
| Webhook/API eşleme + sandbox test (`integration-hub`) | **Claude Opus 5** — bkz. Agents §3 "Entegrasyon Webhook Ajanı" | "Anthropic — Otomasyon" (Faz 3'ten genişler) | Integration Hub | `ToolIntentPort`, sandbox, insan onaylı |

## Faz 7 — Stage 7 Maturity (planlı)

Yeni yetenek yok — mevcut tüm satırların **işletim katmanı** olgunlaşır
(kuyruk-işçisi, dead-letter, devre kesici, idempotency, otomatikleştirilmiş
eval). Hangi modelin hangi işi yaptığı değişmez, *nasıl* çalıştığı sertleşir.

## Faz 8 — Stage 8 Exit Ready (planlı)

Yeni yetenek yok — yukarıdaki tüm satırların denetim izi (`ai_invocations`,
`platform_credential_audits`) tek bir devir envanterinde derlenir.

---

## Skills — hangi skiller var (mevcut, `skills/` dizininde, AI'a özgü 3 tanesi)

Depoda **22 skill planı** var (`templates/SKILL-SPEC.md` disipliniyle
yazılı — her biri Trigger/Authority/Permitted-Forbidden/Human-approval/Eval
taşır). Bunların **üçü** doğrudan AI'a özgü, diğer 19'u genel platform
disiplinleri (i18n, auth, tenant-isolation vb.) — AI yetenekleri de onlara
uyar ama AI'a özgü değiller.

| Skill | Ne yapar | Ne yapamaz | Faz |
| --- | --- | --- | --- |
| **`skills/ai-account-routing.md`** | Bir istek geldiğinde **hangi hesabın** kullanılacağını seçer (priority/weighted/cost/latency/health skoru) | Hesap **oluşturmaz/silmez/rotasyona sokmaz** (insan eylemi); kota aşmak için otomatik hesap değiştirmez (kesin yasak) | Stage 2'den itibaren |
| **`skills/ai-no-credit-degradation.md`** | Kill switch/kota/429/outage anında kullanıcı taslağını **korur**, deterministik yola döner | Yeni karar üretmez, sessizce daha ucuz/kalitesiz modele kaydırmaz | Stage 2'den itibaren |
| **`skills/ai-provider-evaluator.md`** | Aylık periyodik: sağlayıcı fiyat/politika değişikliğini tarar, öneri üretir | Bir sağlayıcıyı otomatik devreye almaz/kapatmaz — yalnız öneri | Stage 2'den itibaren, aylık |

## Agents — hangi ajanlar olacak

**Dürüst durum:** depoda bugün ayrı bir "Agent" kayıt sistemi **yok** —
yalnız `docs/32`'deki `agentic_guarded` duruşu, üç modülde (`core-eca-rules`,
`opt-13-pos-integrations`, `integration-hub`). Sahibin "belirli olsun"
isteği doğru — bunu somutlaştırıyorum: `SKILL-SPEC.md`'nin aynı disipliniyle
üç ajan tanımlıyorum. Bu üçü **yeni bir öneri**, zaten yazılmış bir dosya
değil; onaylanırsa `templates/AGENT-SPEC.md` olarak resmileşir.

### 1. Kural Taslağı Ajanı — `core-eca-rules`

| Alan | Değer |
| --- | --- |
| **LLM + Model** | Claude Opus 5 — `ToolIntentPort` çağrısı typed komut adayı ürettiği için en güçlü talimat-takip modeli gerekir; ucuz model yanlış kural üretirse sandbox yanlış şeyi test eder |
| **Hesap** | "Anthropic — Otomasyon" (Faz 3, ayrı bağlantı — yüksek-stake işler diğer trafikten izole izlenir) |
| **Trigger** | Superadmin/Owner "olay→koşul→eylem kuralı öner" dediğinde |
| **Sandbox** | Üretilen kural **asla** doğrudan üretim ECA motoruna yazılmaz; önce simülasyon ortamında geçmiş olaylara karşı çalıştırılır, sonucu gösterilir |
| **İzin verilen** | Kural taslağı üretme, sandbox simülasyonu çalıştırma, olası çakışma/segregation-of-duty riskini işaretleme |
| **Yasak** | Kuralı doğrudan aktive etmek; mevcut bir kuralı sessizce değiştirmek |
| **İnsan onayı** | Zorunlu — taslak, simülasyon sonucuyla birlikte gösterilir, kabul/düzenle/reddet |

### 2. Entegrasyon Eşleme Ajanı — `opt-13-pos-integrations`

| Alan | Değer |
| --- | --- |
| **LLM + Model** | Claude Opus 5 — alan eşleme hatası veri hatasına dönüşür, düşük hata toleransı |
| **Hesap** | "Anthropic — Otomasyon" (Kural Taslağı Ajanı ile paylaşılan bağlantı — ikisi de aynı risk sınıfı) |
| **Trigger** | Restoran sahibi/entegratör bir POS sistemine bağlanmak istediğinde |
| **Sandbox** | Eşleme önce test verisiyle senkronize edilir, gerçek envantere dokunmaz |
| **İzin verilen** | Alan eşleme önerisi, sandbox test senkronizasyonu çalıştırma |
| **Yasak** | Gerçek POS verisine yazma; eşlemeyi insan onayı olmadan "canlı" işaretleme |
| **İnsan onayı** | Zorunlu — eşleme tablosu gösterilir, onaylanmadan canlıya alınmaz |

### 3. Entegrasyon Webhook Ajanı — `integration-hub`

| Alan | Değer |
| --- | --- |
| **LLM + Model** | Claude Opus 5 |
| **Hesap** | "Anthropic — Otomasyon" (Stage 6'da genişler, kurumsal SLA gerektirebilir) |
| **Trigger** | Enterprise tenant kendi webhook/API entegrasyonunu tanımlamak istediğinde |
| **Sandbox** | Payload/şema taslağı önce test uç noktasına gönderilir |
| **İzin verilen** | Webhook payload/şema taslağı üretme, sandbox test çağrısı |
| **Yasak** | Üretim webhook'una gerçek veri göndermek, kimlik bilgisi/secret taslağa yazmak |
| **İnsan onayı** | Zorunlu |

**Ortak desen — üçü de aynı üç kuralı paylaşır:** (1) hiçbiri doğrudan
mutasyon yapmaz, yalnız **typed komut adayı** üretir; (2) hiçbiri kendi
onayını veremez — final onay her zaman insan; (3) üçü de aynı "Anthropic —
Otomasyon" hesap sınıfını kullanır çünkü hepsi `ToolIntentPort` + yüksek
hata maliyeti taşır — bu bilinçli bir gruplama, rastgele değil.

---

## Owner kararı gerekir mi?

1. **Gemini görüntü adaptörünün tam model adı** (Faz 2, sıradaki iş) —
   öneri var (`docs/51` §4b.1 sıralaması), kesin model adı `config/ai.php`'ye
   yazılmadan önce MASTER test-first kurar, owner'a yalnız "bağlandı mı,
   çalışıyor mu" sorusu gider.
2. **Kimi K3'ün tam sürüm/model adı** (Faz 3) — aynı disiplin.
3. **Üç ajanın "Anthropic — Otomasyon" hesabının bütçesi** — platform-owned
   tek havuzdan mı, yoksa ayrı bir tavan mı (bu üçü yüksek-stake, farklı
   bütçe disiplini isteyebilir).

Model adı/sürümü gibi geri döndürülebilir teknik seçimler MASTER'da kalır.

## Kanonik sahiplik

Bu belge yetenek↔model↔hesap↔konum eşlemesinin **tek** somut kaydıdır.
Fazların kendisi `docs/95`'te, sağlayıcı/hesap mimarisi
`modules/ai-provider-account-vault.md`'de, port tanımları `docs/51`'de,
skill sözleşmeleri `skills/*.md`'de kanoniktir — bu belge onları tekrar
etmez, birbirine bağlar.

Bu belgedeki "Konum" kolonunun her satırı için gereken ekranın kullanıcı
yolculuğu ve gereksinim numarası (`R1`…`R19`) —
`docs/97-AI-ARAYUZ-KULLANIM-SENARYOLARI.md`'de kanoniktir.
