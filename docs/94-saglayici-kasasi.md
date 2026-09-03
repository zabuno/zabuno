# 94 — Sağlayıcı kasası: sırlar UI'dan girilir, koddan/sunucudan değil

**İstek:** superadmin, ayarlar panelinden Mailgun / Iyzico / OpenAI / Gemini
gibi sağlayıcıların anahtar/secret'larını **UI üzerinden** girip kaydedebilsin.
Kanonik tasarım: `modules/ai-provider-account-vault.md`.

Bu, 5 fazlık bir programdır. Bu belge fazlar ilerledikçe büyür.

## Neden bir kasa — ve neden asistan sırra dokunmadan

Bir API anahtarını asistan hiçbir alana giremez (sabit güvenlik kuralı) ve
sahibin her seferinde sunucuya SSH atıp `.env` düzenlemesi de sürdürülebilir
değil. Doğru çözüm: **ürünün kendi içinde, insanın güvenle girdiği bir kasa.**
Ne asistan sırra dokunur, ne sahip sunucuya iner.

## Faz 1/5 — Şifreli çekirdek (bu commit)

### İki port, tek depo — sır GERİ OKUNAMAZ

Kasanın iki yüzü var ve **bilerek ayrı**:

- `PlatformCredentialAdminPort` — panelin yüzü. Yazar (`put`), maskeli durum
  görür (`status`/`all`), döndürür, kapatır (`disable`). **`reveal` yoktur.**
- `CredentialResolverPort` — tüketicinin yüzü. Yalnız posta göndericisi ya da
  AI adaptörü çözer (`resolve`). HTTP controller'ları bu portu asla almaz.

Ayrım yapısaldır: HTTP katmanı, sırrı çözen metoda tip düzeyinde erişemez.

### Sır asla düz yazıya çıkmaz

`secret_ciphertext` uygulama anahtarıyla şifrelenir (master key webroot
dışında, env'de). Düz alanlar (domain, endpoint) ayrı sütunda açık durur —
sır değiller. `secret_hints` yalnız `••••` + son 4 karakteri taşır; panelin
gördüğü tek sır izi budur. Vault modülü §Data retention ile aynı disiplin:
"yalnız var/yok + maskelenmiş son 4".

### Öncelik: KASA > env (geçiş güvenli)

Kasa boşken `resolve`, sunucunun `.env`'ine düşer — mevcut Mailgun aktarımı
(`docs/93` FF-36) çalışmaya devam eder. Kasa doldurulunca env'in önüne geçer.
Yani bu özellik hiçbir şeyi bozmadan devreye girer; sahip UI'dan girene kadar
eski yol yaşar.

### Yarım yapılandırma sessizce "çalışıyor" görünmez

Zorunlu bir alan hiçbir kaynaktan gelmiyorsa `resolve` boş döner: eksik
anahtarlı bir sağlayıcı "kurulu" sanılıp ilk gerçek çağrıda patlamaz.

### Kapı (test-first)

`PlatformCredentialVaultTest` (7):

| Requirement | Ne donduruluyor |
| --- | --- |
| `VAULT-ENCRYPTED-AT-REST-01` | Sır satırda düz yazıyla YOK; tüketici yine çözebiliyor |
| `VAULT-MASKED-READBACK-01` | Admin yüzü tam sırrı değil, maskeyi döner |
| `VAULT-ENV-FALLBACK-01` | Kasa boşsa env; kasa doluysa kasa kazanır |
| `VAULT-ROTATE-PRESERVES-UNTOUCHED-SECRET-01` | Boş bırakılan sır öncekini korur |
| `VAULT-DISABLE-01` | Kapalı sağlayıcı çözülmez |
| `VAULT-UNKNOWN-FIELD-REJECTED-01` | Şema dışı alan reddedilir |
| `VAULT-ALL-LISTS-EVERY-PROVIDER-01` | Panel her sağlayıcıyı listeler, kurulu olmasa da |

## Faz 2/5 — Superadmin write-only API + audit (bu commit)

Çekirdek artık bir superadmin API'ının arkasında. Üç uç, hepsi
`EnsurePlatformSuperAdmin` + `auth:sanctum` + `verified` arkasında:

- `GET /api/admin/credentials` — her sağlayıcının **maskeli** durumu.
- `PUT /api/admin/credentials/{provider}` — yaz/döndür (throttle 20/dk).
- `POST /api/admin/credentials/{provider}/disable` — kapat (throttle 20/dk).

Güvenlik kararları:

- **Sır hiçbir cevaba çıkmaz.** Controller'lar yalnız admin portunu alır
  (geri okuyamaz) ve yalnız maskeli durumu döner. Testler PUT ve GET
  gövdelerinde ham sırrın olmadığını dondurur.
- **Enumeration-safe.** Platform rolü olmayan doğrulanmış kullanıcı 404
  alır — "burada bir şey var ama giremezsin" bile sızmaz.
- **Şema dışı alan 422, 500 değil.** Panel yalnız o sağlayıcının tanıdığı
  alanları yazabilir; bilinmeyen sağlayıcı 404.
- **Her yazma bir denetim satırı bırakır — SIRSIZ.** `platform_credential_audits`
  append-only: kim, hangi sağlayıcı, `set`/`disabled`, ne zaman. Sır değeri,
  alan içeriği ya da maske oraya yazılmaz.

### Kapı (test-first)

`ProviderCredentialApiTest` (8): AUTHZ (guest 401 / non-admin 404), LIST,
STORE + SECRET-NEVER-IN-RESPONSE, UNKNOWN-FIELD (422), UNKNOWN-PROVIDER
(404), DISABLE, AUDIT (sırsız). Route imzaları `ModularApiRouteRegistrationTest`
mührüne eklendi. Tam yerel paket **1183 yeşil**.

## Faz 3/5 — Mailgun gönderici kasadan okur (bu commit)

FF-36 aktarımı Mailgun'u sunucu `.env`'inden getiriyordu. Bu faz, kasadan
girilen anahtarı env'in **önüne** geçiriyor. Sorun şuydu: üretimde config
önyüklemede dondurulur (`config:cache`), kasa ise çalışma zamanında değişir
— o yüzden değeri göndermeden **hemen önce** çözmek gerek.

`MailTransportSelectorPort::select()` gönderimden önce Mailgun kimliğini
resolver'dan (KASA > env) çözer, `services.mailgun.*` config'ini tazeler ve
`mailgun` sürücüsünü seçer. Zorunlu alanlar (domain + secret) hiçbir
kaynaktan gelmiyorsa varsayılan sürücü (`mail.default`, genelde `log`)
kalır — kimlik yokken gönderici uydurmayız. İletişim formu artık bu porttan
geçiyor.

Sonuç: superadmin UI'dan (Faz 2 API) Mailgun anahtarı girdiği an, iletişim
bildirimi o anahtarla çıkar — sunucuya dokunmadan, yeniden deploy etmeden.
FF-35'in "sağlayıcı yoksa mesaj yine saklanır" davranışı korunuyor.

### Kapı (test-first)

`VaultMailConsumptionTest` (4): USES-VAULT (kasa env'i ezer), ENV-FALLBACK,
NONE-IS-DEFAULT, CONTACT-USES-SELECTED (uçtan uca, sır iletişim tablosuna
sızmaz). FF-35 `ContactDeliveryTest` (4) hâlâ yeşil. Tam yerel paket **1187**.

## Faz 4/5 — Superadmin GUI paneli (bu commit)

Kullanıcının asıl istediği ekran: platform panelinde **Sağlayıcı anahtarları**
bölümü. Her sağlayıcı (Mailgun, Iyzico, OpenAI, Gemini) bir kart; alanları
API'nin döndürdüğü şemadan gelir (frontend'e gömülü değil).

Not: bu iki kalan fazın sırasını bilerek değiştirdim. GUI, kullanıcının
gördüğü ve istediği parça ve **tamamen doğrulanabilir**; OpenAI adaptörü ise
"gerçek API'ye karşı doğrulanamayan" parça, onu en sona bıraktım. Denominatör
5 sabit; yalnız 4 ve 5 yer değiştirdi.

Güvenlik, ekranda:

- **Sır asla değer olarak görünmez.** Sır alanı `password` tipinde ve boş
  başlar; kayıtlıysa yanında yalnız `••••son4` maskesi durur. Boş bırakılan
  bir sır **gönderilmez** — mevcut değer korunur, çünkü panel onu zaten
  okuyamaz.
- Düz alanlar (domain, endpoint) tam değeriyle görünüp düzenlenebilir.
- Yazma istekleri CSRF önyüklemesi + `X-XSRF-TOKEN` ile gider.
- Kapatma düğmesi yalnız kurulu sağlayıcıda görünür.

### Kapı (test-first)

`ProviderCredentialsPage.test.tsx` (5): listeler ve ham sır göstermez, boş
sırrı göndermez ama yazılanı gönderir, girilen sırrı gönderir, kapatır, yükleme
hatasında yeniden-dener. `PlatformApp` gezinti testi hâlâ yeşil. i18n
projeksiyonları (`i18n:check`), lint, prettier, build, bundle-gate hepsi
yeşil. Frontend paketi **1124**, PHP paketi **1187**.

## Faz 5/5 — OpenAI görüntü adaptörü (bu commit)

Son parça: kasadaki OpenAI anahtarını gerçek foto-menü okumaya bağlamak.

**⚠️ DOĞRULAMA DAMGASI (baştan söylediğim gibi):** bu adaptör **gerçek OpenAI
API'sine karşı doğrulanmadı.** İstek şekli OpenAI'ın belgelenmiş Chat
Completions + yapılandırılmış çıktı biçimine göre yazıldı ve `Http::fake` ile
sınandı — yani sahtesine karşı. **Kod yeşil olması "çalışıyor" demek değil.**
İlk gerçek çağrıyı anahtarı olan kişi tek sayfalık bir menüyle yapmalı;
o denemeye kadar "doğrulanmadı" damgası durur.

Ne kuruldu:

- `OpenAiVisionProvider` — anahtarı kasadan (resolver) okur, görseli data-URI
  olarak `user` kanalına, talimatı `system` kanalına koyar (talimat/veri
  ayrımı tür düzeyinde), yapılandırılmış JSON çıktı zorlar, satırları
  `FieldValue`'ya eşler. Fiyatı okunamayan satır **belirsiz** işaretlenir —
  uydurma fiyat menüye geçmez.
- **Rota kasadan açılır.** `ConfiguredAvailability` artık config aday listesi
  boş olsa da kasada OpenAI yapılandırılmışsa yeteneği açık sayar. Superadmin
  anahtarı girdiği an rota açılır, config'e dokunmadan.
- **Bütçe hâlâ kapı.** Anahtar var ama aylık bütçe 0 ise cevap yine
  `BudgetExhausted` — sıfır bütçe "kapalı" demek, "sınırsız" değil. Her çağrı
  `ai_invocations`'a token + maliyet + sonuç bırakır (bütçe bundan türer).
- **Sağlayıcı hatası 502.** Yetenek açıkken çağrı başarısız olursa (ağ /
  http / anlaşılmaz cevap) uç 502 döner — 503'ten (yetenek yok) farklı,
  çıkış yolu farklı. Ham hata/anahtar sızmaz.
- **Model adı koda gömülü değil**, `config('ai.openai.vision_model')` — env'den
  güncellenir. Maliyet tavanı için `config('ai.pricing')` doldurulur; boşsa
  maliyet 0 yazılır ve bütçe yalnız aç/kapa görevi görür. Foto okuma
  İNSAN TETİKLİDİR (onay hattı, `docs/92`), o yüzden kontrolsüz döngü riski
  düşük.

### Kapı (test-first)

`OpenAiVisionProviderTest` (5): istek şekli (talimat=system, görsel=user,
json_schema), talimat/veri ayrımı (kötü niyetli veri system'e sızmaz), satır
eşleme (null fiyat → belirsiz), maliyet kaydı (75 kuruş), http hatası → failed
kaydı + fırlatma. `VaultAiRoutingTest` (3): kasa anahtarı rotayı açar, bütçe
hâlâ kapı, binding kasaya göre. Mevcut AI testleri (26) yeşil. Tam PHP paketi
**1195**.

## Program tamamlandı — 5/5

Kasa artık uçtan uca: superadmin panelden anahtar girer (Faz 4), şifreli
saklanır (Faz 1), API yalnız-yazılır ve denetimli (Faz 2), posta onu kullanır
(Faz 3), OpenAI foto-okuma onu kullanır (Faz 5). Iyzico anahtarı kasada
saklanır ve gösterilir ama ödeme akışı ayrı iştir (P1-02, bekliyor).
- **Faz 3/5** — Mailgun runtime tüketimi (kasadan okuyan gönderici).
- **Faz 4/5** — OpenAI adaptörü + Gemini + routing (kasadan anahtar).
- **Faz 5/5** — GUI ayarlar paneli + i18n.

## Ürün iddiası

Çalışır: kasa çekirdeği + superadmin API. Bir superadmin sırrı API üzerinden
girebilir/döndürebilir/kapatabilir; şifreli saklanıyor, maskeli okunuyor,
her yazma denetime geçiyor, env yedeği bozulmadan duruyor.
Çalışır (Faz 5): kasa uçtan uca. Panelden girilen Mailgun anahtarı postayı,
OpenAI anahtarı foto-menü okumayı besler — sunucuya dokunmadan.
**Doğrulanmadı:** OpenAI adaptörü gerçek API'ye karşı test edilmedi; ilk
gerçek çağrı anahtar sahibinin tek sayfalık denemesini bekliyor.
Ayrı iş: Gemini adaptörü (anahtar saklanıyor, tüketim yazılmadı) ve Iyzico
ödeme akışı (P1-02).
