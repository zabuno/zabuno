# 123 — Ödeme: kip anahtarı, kendi kendine abonelik, iade (FF-197)

`docs/107` Faz 1.1 ve 1.3'ün kod karşılığı. Bu belge KARARLARI ve
gerekçelerini taşır; durum satırları `docs/107`'de, sayısal eşikler
`config/`'de yaşar — burada tekrar edilmez.

## 0. Ölçülen başlangıç ve varış

| | Önce (2026-09-06, ölçüldü) | Şimdi |
| --- | --- | --- |
| Geçit | Yalnız `IyzipaySandboxGateway`; para hareket etmez | + `IyzipayGateway` (üretim, adres sabit `https://api.iyzipay.com`, anahtar KASADAN) |
| Ödeme yolu | `POST …/iyzico-sandbox/session`: zaten aktif aboneliğin planını ücretlendirir; plan seçimi yok | `POST /workspaces/{w}/checkout` (`plan_id` + `idempotency_key`): kiracı planı kendi seçer; tutar sunucudan |
| Alıcı | `.env`'deki sandbox personası | `billing_profiles` (çalışma alanı başına, tam ya da yok) |
| Başarı | Defterde tek satır | + abonelik `active`, `ends_at` bir dönem ileri (varsa üstüne, yoksa bugünden) |
| Başarısızlık | İşlem `failed` | + sebep saklanır ve panelde ADIYLA okunur; yeni anahtarla tekrar |
| İade | Yok | `POST /admin/workspaces/{w}/transactions/{id}/refund` — sebep zorunlu, defterde ters kayıt, dönem düşülür, denetim |
| Kip | Yok | `services.iyzico.mode` + `platform_settings` billing.mode + kasa: üçü birden |
| Webhook | `/api/webhooks/iyzico-sandbox` | + `/api/webhooks/iyzico` (aynı imza formülü, gizli anahtar işlemin kipine göre) |

## 1. Kararlar

### K1 — Kip anahtarı ÜÇ kapılıdır; biri kapalıysa sandbox

`live` ancak şu üçü birden doğruyken etkindir:

1. Dağıtım izin veriyor: `services.iyzico.mode=live` (`IYZICO_MODE`).
2. Süperadmin panelden açıkça açtı: `PUT /admin/settings/billing-mode {mode: live}`.
3. Kasada Iyzico üretim anahtarı var (`PlatformCredentialAdminPort::status(Iyzico)->configured`).

**Neden üç, iki değil.** İstek "kasa dolu VE süperadmin açtı" diyordu. Üçüncü
kapı staging/CI için: üretim veritabanının kopyasıyla ayağa kalkan bir ortam,
ayarı `live` ve kasayı dolu TAŞIR — ve o ortamın env'i `sandbox` demezse
gerçek para çeker. Env, "bu makine para çekebilir" kararının tek yeridir;
veritabanı taşınır, env taşınmaz. Sahibin yükü artmıyor: `IYZICO_MODE=live`
üretim sunucusuna bir kez, `APP_URL` ile aynı sınıfta yazılır.

Kasa "dolu" mu sorusu ADMİN portuna sorulur, resolver'a değil: resolver kasa
boşken `.env`'e düşer ve sandbox anahtarını "var" gösterirdi.

`PUT … {mode: live}` kapalı bir kapıyla gelirse **422 + kapının adı**
(`deployment_mode_sandbox` / `vault_missing`); hiçbir şey yazılmaz. Etkin kip
sonradan düşerse (kasa kapatıldı) `GET` bunu `effective: sandbox` ve
`reasons` ile söyler. Her kip değişimi `platform_audits`'e (scope
`billing.mode`) yazılır.

### K2 — Canlı geçit yalnız kasadan; sandbox anahtarı canlıya asla

`IyzipayGateway` anahtarı `CredentialResolverPort::resolve(Iyzico)` ile çözer.
Resolver kasa boşken `.env` sandbox anahtarına düşer; canlı geçit çözülen
anahtar `.env` sandbox anahtarıyla AYNIYSA SDK'ya dokunmadan "kullanılamaz"
der. Sandbox anahtarı üretim adresine hiçbir koşulda gönderilmez.

Taban adres sabittir. Kasadaki `base_url` alanı canlı geçit tarafından
OKUNMAZ: kipi belirleyen anahtardır, bir metin alanı değil. Sahip kasaya
sandbox anahtarını girip `live` açarsa Iyzico reddeder → 502, para hareket
etmez; bu belgelenmiş, kapatılmayan bir sınırdır.

Alıcı IP'si isteğin kendisinden gelir (`Request::ip()`); yoksa geçit
çalışmaz. `0.0.0.0` gibi bir değer uydurulmaz.

### K3 — Fatura profili HER İKİ kipte zorunlu; sandbox personası env'de kalır

`billing_profiles` çalışma alanı başına bir satır; sekiz alan (unvan, vergi
no, vergi dairesi, adres, şehir, ülke kodu, e-posta, telefon), hepsi
zorunlu. Yarım profil kaydedilmez; dolayısıyla "var" eşittir "tam".

Profil yoksa `POST /checkout` **422 + `reason: billing_profile_missing`**
döner ve sağlayıcı hiç çağrılmaz. Bu kural sandbox kipinde de geçerli:
prova gerçek yolun aynısı olmalı. Sandbox geçidi ise Iyzico'ya `.env`
personasını gönderir — kiracının gerçek unvanı test ortamına taşınmaz.

Tüzel kişinin soyadı yoktur: Iyzico'nun `name` ve `surname` alanlarının
ikisine de unvan yazılır. Uydurulmuş bir soyad, olmayan bir kişiyi alıcı
gösterirdi. Vergi numarası `identityNumber` alanına gider (Iyzico'nun
kurumsal alıcı için beklediği yer). Posta kodu toplanmıyor, gönderilmiyor.

### K4 — Eski sandbox yüzeyi dondu; yeni yüzey ayrı

`IyzipaySandboxGateway`, `IyzicoSandboxGatewayPort`, `iyzico_sandbox_transactions`,
`/iyzico-sandbox/session`, `/webhooks/iyzico-sandbox` ve callback'i
DOKUNULMADAN durur; testleri o gövdeyi kilitler. Yeni yol:

- `PaymentGatewayPort` — ortak sözleşme (`initializeCheckout` + alıcı,
  `retrieveCheckout` + `payment_transaction_id`, `refund`). `IyzicoSandboxGatewayPort`
  bu sözleşmenin ÖNCESİDİR, ona bağlanmadı: bağlamak eski geçide `refund`
  eklemek olurdu.
- `SandboxPaymentGatewayPort` → `IyzipaySandboxModeGateway`,
  `LivePaymentGatewayPort` → `IyzipayGateway`. SDK konuşması tek yerde:
  `IyzipayCheckoutFormClient` (imza formülleri sandbox geçidiyle birebir).
- `PaymentGatewaySelectorPort::forMode()` geçidi KİP SÖYLENDİĞİNDE çözer:
  canlı geçit sandbox kipinde hiç kurulmaz. "Anahtar kapalıyken canlı geçit
  çağrılmaz" bir `if` değil, kurulmayan bir nesnedir.
- `payment_transactions` işlemin kipini taşır; geç gelen webhook, anahtar o
  arada değişse bile kendi kipinin geçidiyle doğrulanır.

Yeni akışın sandbox geçidi eski geçidin ~150 satırını tekrar eder. Bilinçli:
eski yüzey dondurulmuş ve ayrı iş olarak emekliye ayrılacak (aşağıda K10).

### K5 — Abonelik: bitişin ÜSTÜNE, ödenen plan geçerli olur

`SubscriptionRepositoryPort::extendFromPayment`: bitiş ileride ise onun
üstüne, geçmişte kaldıysa bugünden itibaren bir dönem
(`billing.subscription.period_days`). Erken ödeyen sahip gün kaybetmez.
Ödenen plan aboneliğin planı olur; kalan süre korunur. Plan düşürmede
fark iadesi YOK (docs/107 1.3'te açık madde).

Genişletme koşullu geçişten sonra ve YALNIZ bir kez: webhook ile callback
aynı anda gelse bile `markSucceeded` yalnız birine `true` döner; abonelik ve
defter yalnız o birinde yazılır.

### K6 — İade: dönem düşülür, defter ters yazılır

Yalnız `succeeded` bir işlem, yalnız süperadmin, yalnız sebeple. Sağlayıcıya
Iyzipay Refund (`paymentTransactionId` + tutar + para birimi; sebep
`description`, Iyzico'nun sabit sebebi `buyer_request`). Sağlayıcı
reddederse (502) hiçbir şey değişmez.

Kabulde: işlem `refunded` (kim, ne zaman, neden), defterde ters satır
(`revenue` borç / `cash` alacak, referans `payment:{id}:refund`), abonelik
bitişi **iade edilen dönem kadar** geri, `platform_audits` (scope
`billing.refund`).

**Neden "ödeme öncesine geri sar" değil.** Aradaki başka bir ödeme o bitişi
de taşımış olabilir; ona geri sarmak ödenmiş bir dönemi sessizce silerdi.
Düşülen şey bu ödemenin satın aldığı süredir.

Refund cevabının imzası doğrulanmıyor: istek bizden çıkar, kimlik doğrulanmış
API'ya TLS üstünden gider. Belgelenmiş açık; webhook gibi dışarıdan gelen
bir olay değil.

### K7 — Webhook: aynı formül, gizli anahtar kipe göre, uç durum tekrarına 200

İmza formülü sandbox ucuyla aynı (HMAC-SHA256, anahtar + eventType +
paymentId + token + conversationId + status, anahtar = gizli anahtar).
Aday anahtarlar: sandbox (`.env`) ve canlı (kasa, sandbox'la aynıysa yok).
Hangi anahtar doğruladıysa o kip; işlemin kipiyle uyuşmazsa 400. Bilinmeyen
conversation 404, jeton uyuşmazlığı 422. Zaten uç durumdaki işlem için 200 —
sağlayıcıya tekrar sorulmaz.

### K8 — Sağlayıcıya ulaşılamayan deneme sonsuza dek "ayrılmış" kalmaz

Rezervasyon alındıktan sonra geçit patlarsa satır `failed` +
`provider_unavailable: …` olur; aynı anahtar bu satırı döndürmeye devam
eder (o deneme bitti), yeni anahtarla yeni deneme açılır.

### K9 — Panel: kart alanı YOK, tek sütun, ≥44 px

`SubscribeCheckout` (kap) + `CheckoutPanel` (sunum) + `BillingProfileForm`.
Plan seçimi radyo satırları (`min-h-11`), profil özeti/formu, "Proceed to
payment". Katalog BİR kez iner: `PlanCatalog` `onPlansChange` ile sayfaya
verir, panel ikinci kez indirmez. Eski `IyzicoSandboxCheckout` bölgesi
üretim dışı kalmaya devam eder (K10).

### K10 — Ayrı iş: eski sandbox yüzeyinin emekliliği

Canlı tahsilat gerçek bir kartla kanıtlandıktan sonra eski uçlar, tablo,
geçit ve panel bölgesi tek pakette kaldırılır; `IyzipaySandboxModeGateway`
o gün tek sandbox geçidi olur.

## 2. Yüzeyler

| Uç | Kim | Ne |
| --- | --- | --- |
| `GET/PUT /api/workspaces/{w}/billing-profile` | billing.view / billing.manage | Fatura profili; PUT sekiz alanı doğrular |
| `GET /api/workspaces/{w}/checkout` | billing.view | `mode`, `period_days`, `profile_complete`, `latest` |
| `POST /api/workspaces/{w}/checkout` | billing.manage, 10/dk | `plan_id` + `idempotency_key`; 202 işlem; 422 adıyla |
| `POST /api/webhooks/iyzico` | açık | Sağlayıcı bildirimi |
| `POST /api/billing/iyzico/callback` | açık | Tarayıcı geri dönüşü → `/app#billing` |
| `GET/PUT /api/admin/settings/billing-mode` | süperadmin | Kip anahtarı |
| `POST /api/admin/workspaces/{w}/transactions/{id}/refund` | süperadmin, 5/dk | İade, sebep zorunlu |

## 3. Kapılar (ölçüldü)

Bkz. bu paketin commit mesajı — sayılar orada, burada tekrar edilmez.

## 4. Ürün iddiası

**Çalışır (sahte geçitle kanıtlı):** plan seç → profil → başlat → callback/
webhook SUCCESS → abonelik + defter; FAILURE → sebep + tekrar; iade; kip
anahtarı; sandbox kipinde uçtan uca prova.

**Çalışmaz / kanıtlanmadı:** gerçek kartla gerçek tahsilat. Kod hazır;
canlı tahsilat sahibin (1) kasaya Iyzico üretim anahtarını girmesine,
(2) üretim env'inde `IYZICO_MODE=live` olmasına, (3) `PUT …/billing-mode`
ile anahtarı açmasına ve (4) Iyzico panelinde webhook adresini
`/api/webhooks/iyzico` yapmasına bağlıdır. İlk gerçek ödeme ve ilk gerçek
iade bu dört adımdan sonra ölçülür; o güne kadar "kod hazır" yazılır,
"bitti" değil.
