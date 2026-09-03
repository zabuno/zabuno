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

## Sırada

- **Faz 3/5** — Mailgun runtime tüketimi (kasadan okuyan gönderici).
- **Faz 3/5** — Mailgun runtime tüketimi (kasadan okuyan gönderici).
- **Faz 4/5** — OpenAI adaptörü + Gemini + routing (kasadan anahtar).
- **Faz 5/5** — GUI ayarlar paneli + i18n.

## Ürün iddiası

Çalışır: kasa çekirdeği + superadmin API. Bir superadmin sırrı API üzerinden
girebilir/döndürebilir/kapatabilir; şifreli saklanıyor, maskeli okunuyor,
her yazma denetime geçiyor, env yedeği bozulmadan duruyor.
Henüz çalışmaz: bunun bir **GUI paneli** yok (Faz 5/5), ve posta/AI henüz
kasadan OKUMUYOR (Faz 3–4) — API'dan girilen Mailgun anahtarı gönderici
tarafından kullanılmıyor, çünkü gönderici hâlâ env'den okuyor.
