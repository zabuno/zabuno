# 139 — Rol ve yetki matrisi

> **Yol haritası:** `docs/107` Faz 3.6 — *"Rol ve yetki matrisi belgesi;
> müşteri onu okuyup kendi ekibini kurabilsin."*

## 0. Kim okur, ne arar

Bu belgenin iki okuyucusu var ve ikisi aynı tabloya farklı sorularla bakar.

**Restoran sahibi** ekibini kurarken sorar: *"Garsona şu rolü verirsem ne
yapabilir? Fiyatlara dokunabilir mi?"* Onun cevabı ürünün İÇİNDEDİR —
Ekip ekranındaki rol kartı, her rolün yapabildiklerini ve yapamadıklarını
aynı listeden çizer (§5). Bu belge o ekranın kâğıt hâlidir, yedeği değil.

**Zincirin satın alma ya da hukuk birimi** sorar: *"Kim neye erişebiliyor?
Destek ekibiniz bizim verimize bakabiliyor mu, değiştirebiliyor mu?"* Onun
cevabı §2'deki ızgara, §2'deki "yapamadıkları" listeleri ve §2'deki
süperadmin/destek penceresi ölçümüdür.

## 1. Bu matris nasıl üretildi

**Elle yazılmadı ve elle düzeltilemez.** `docs/110`'un yöntemi burada da
geçerli: iddia belgeden değil KODDAN çıkar.

Kaynak üç dosya ve bir yönlendirici:

| Kaynak | Ne söyler |
| --- | --- |
| `app/Domain/Authorization/Permission.php` | Hangi yetenekler adlandırılmış |
| `app/Domain/Authorization/RolePermissions.php` | Hangi rol hangisini taşıyor |
| `app/Domain/Tenancy/MembershipRole.php` | Rol davet edilebilir / çıkarılabilir / sahiplik devralabilir mi |
| Laravel yönlendiricisi | `EnsurePlatformSuperAdmin` kapısının arkasında hangi uçlar var |

Zincir üç halka:

1. **Okuma** — `App\Domain\Authorization\RolePermissionMatrix` matrisi
   enum'ların kendi `cases()` çağrılarından türetir. Bu dosyada ne izin ne
   de rol listesi YAZILIDIR; yarın yirmi dördüncü bir izin doğduğunda
   dosyaya dokunmak gerekmez.
2. **Yazma** — `App\Infrastructure\Authorization\AuthorizationMatrixReport`
   aynı okumadan iki biçim üretir: bu belgenin §2 bölgesi ve ekranın
   okuduğu `resources/js/generated/role-permission-matrix.json`.
   Üretici: `php artisan authorization:matrix`.
3. **Kapı** — `tests/Feature/Authorization/AuthorizationMatrixArtifactTest`
   üretimi yeniden koşar ve depodaki baytlarla karşılaştırır.

### 1.1 Kod ile belgenin ayrıştığı gün ne olur

Kapı kırılır. Somut olarak:

- Bir role izin **eklenirse** ya da ondan bir izin **alınırsa**, §2'deki
  ızgara ile kodun ürettiği ızgara ayrışır ve test kırmızıya döner.
- Yeni bir **rol** ya da yeni bir **izin** doğarsa, ızgara bir satır/sütun
  eksik kalır ve test kırmızıya döner.
- Süperadmin kapısının arkasına bir **uç** eklenirse — özellikle kiracı
  penceresine bir **yazma** ucu — sayı değişir ve test kırmızıya döner.
- Bir izin **hiçbir yerde sorulmuyorsa** (adı konmuş ama hiçbir kapıya
  bağlanmamışsa) test kırmızıya döner: matrisin "yapabilir" dediği şeyin
  kodda bir karşılığı olmalıdır.
- `Permission` enum'una `rating.delete` **eklenirse** test kırmızıya döner
  (`docs/116` §4).
- Ekranın okuduğu etiket kataloğunda bir izin **etiketsiz kalırsa** ya da
  artık var olmayan bir izin için etiket **kalırsa**, ön uç kapısı
  (`TeamRoleGuide.matrix.test.tsx`) kırmızıya döner.

Bu desen `docs/111` §4.1'in modül rozetinden öğrenildi: *"Rozet gözlemsiz
çizilmez."* Buradaki karşılığı, §2'deki her hücrenin bir gözlemden gelmesi
ve hiçbirinin insan eliyle yazılabilir olmamasıdır.

---

## 2. Matris

<!-- KODDAN-URETILDI: BASLANGIC -->

<!--
    BU BÖLÜM ELLE DÜZENLENMEZ.

    Üreten: `php artisan authorization:matrix`
    Kapı:   `tests/Feature/Authorization/AuthorizationMatrixArtifactTest.php`

    Buradaki bir hücreyi elle değiştirmek, kodla belgeyi ayrıştırır ve
    kapı bir sonraki koşuda kırılır. Bir hücre yanlışsa düzeltilecek
    yer koddur; belge onun aynasıdır.
-->

### Kiracı rolleri ve yaşam döngüsü

Kaynak: `MembershipRole::invitable()`, `::removable()`,
`::ownershipTransferable()`.

| Rol | Taşıdığı izin | Davet edilebilir | Ekipten çıkarılabilir | Sahipliği devralabilir |
| --- | ---: | --- | --- | --- |
| `owner` | 25 / 25 | — | — | — |
| `manager` | 20 / 25 | ✓ | ✓ | ✓ |
| `editor` | 10 / 25 | ✓ | ✓ | ✓ |
| `member` | 6 / 25 | — | ✓ | — |
| `kitchen` | 6 / 25 | ✓ | ✓ | — |

### Rol × izin ızgarası

25 izin × 5 rol. Kaynak: `Permission::cases()` ve `RolePermissions::for()`.

| İzin | Eksen | `owner` | `manager` | `editor` | `member` | `kitchen` |
| --- | --- | :-: | :-: | :-: | :-: | :-: |
| `workspace.view` | workspace | ✓ | ✓ | ✓ | ✓ | ✓ |
| `workspace.manage` | workspace | ✓ | ✓ | — | — | — |
| `menu.view` | menu | ✓ | ✓ | ✓ | ✓ | ✓ |
| `menu.manage` | menu | ✓ | ✓ | ✓ | — | — |
| `menu.publish` | menu | ✓ | ✓ | — | — | — |
| `menu.allergens.manage` | menu | ✓ | ✓ | ✓ | — | ✓ |
| `menu.stock.manage` | menu | ✓ | ✓ | ✓ | — | ✓ |
| `qr.view` | qr | ✓ | ✓ | ✓ | ✓ | — |
| `qr.create` | qr | ✓ | ✓ | — | — | — |
| `qr.disable` | qr | ✓ | ✓ | — | — | — |
| `qr.design.manage` | qr | ✓ | ✓ | — | — | — |
| `analytics.view` | analytics | ✓ | ✓ | ✓ | ✓ | — |
| `billing.view` | billing | ✓ | ✓ | — | — | — |
| `billing.manage` | billing | ✓ | — | — | — | — |
| `security.evidence.view` | security | ✓ | — | — | — | — |
| `media.manage` | media | ✓ | ✓ | ✓ | — | — |
| `media.download_original` | media | ✓ | ✓ | ✓ | ✓ | — |
| `order.view` | order | ✓ | ✓ | — | — | ✓ |
| `order.confirm` | order | ✓ | ✓ | — | — | — |
| `order.kitchen` | order | ✓ | ✓ | — | — | ✓ |
| `order.settings` | order | ✓ | — | — | — | — |
| `workspace.data.export` | workspace | ✓ | — | — | — | — |
| `workspace.data.erase` | workspace | ✓ | — | — | — | — |
| `rating.view` | rating | ✓ | ✓ | ✓ | ✓ | — |
| `rating.reply` | rating | ✓ | ✓ | — | — | — |

### Her rolün YAPAMADIKLARI

Aynı döngünün öteki yarısı: bir rol için "yapabilir" listesine
girmeyen her izin buraya girer. İki liste birbirinden ayrışamaz.

#### `owner` — yapamadığı 0 iş

Bu rolün erişemediği adlandırılmış bir yetenek yok.

#### `manager` — yapamadığı 5 iş

- `billing.manage`
- `security.evidence.view`
- `order.settings`
- `workspace.data.export`
- `workspace.data.erase`

#### `editor` — yapamadığı 15 iş

- `workspace.manage`
- `menu.publish`
- `qr.create`
- `qr.disable`
- `qr.design.manage`
- `billing.view`
- `billing.manage`
- `security.evidence.view`
- `order.view`
- `order.confirm`
- `order.kitchen`
- `order.settings`
- `workspace.data.export`
- `workspace.data.erase`
- `rating.reply`

#### `member` — yapamadığı 19 iş

- `workspace.manage`
- `menu.manage`
- `menu.publish`
- `menu.allergens.manage`
- `menu.stock.manage`
- `qr.create`
- `qr.disable`
- `qr.design.manage`
- `billing.view`
- `billing.manage`
- `security.evidence.view`
- `media.manage`
- `order.view`
- `order.confirm`
- `order.kitchen`
- `order.settings`
- `workspace.data.export`
- `workspace.data.erase`
- `rating.reply`

#### `kitchen` — yapamadığı 19 iş

- `workspace.manage`
- `menu.manage`
- `menu.publish`
- `qr.view`
- `qr.create`
- `qr.disable`
- `qr.design.manage`
- `analytics.view`
- `billing.view`
- `billing.manage`
- `security.evidence.view`
- `media.manage`
- `media.download_original`
- `order.confirm`
- `order.settings`
- `workspace.data.export`
- `workspace.data.erase`
- `rating.view`
- `rating.reply`

### İznin kodda karşılığı var mı (ölçüm)

`app/` altında o izni okuyan dosya sayısı. İzni TANIMLAYAN dizin
(`app/Domain/Authorization/`) sayılmaz: bir iznin kendi tanımı, o
iznin uygulandığının kanıtı değildir. Sıfır, bir yeteneğin adı
konmuş ama hiçbir kapıya bağlanmamış olduğunu söyler.

| İzin | Okuyan dosya | İlk okuyan |
| --- | ---: | --- |
| `workspace.view` | 49 | `app/Http/Controllers/Media/ConvertMediaController.php` |
| `workspace.manage` | 18 | `app/Domain/Media/MediaBulkAction.php` |
| `menu.view` | 47 | `app/Http/Controllers/Ai/ApplyBulkMenuAiImportController.php` |
| `menu.manage` | 28 | `app/Http/Controllers/Ai/ApplyBulkMenuAiImportController.php` |
| `menu.publish` | 4 | `app/Http/Controllers/Publication/CancelPublicationScheduleController.php` |
| `menu.allergens.manage` | 1 | `app/Http/Controllers/MenuCatalog/UpdateMenuItemAllergensController.php` |
| `menu.stock.manage` | 2 | `app/Http/Controllers/MenuCatalog/UpdateMenuItemStockController.php` |
| `qr.view` | 15 | `app/Http/Controllers/QrDestination/DisableQrCodeController.php` |
| `qr.create` | 4 | `app/Http/Controllers/QrDestination/RenameDiningAreaController.php` |
| `qr.disable` | 2 | `app/Http/Controllers/QrDestination/DisableQrCodeController.php` |
| `qr.design.manage` | 6 | `app/Http/Controllers/QrDestination/ExportQrCardController.php` |
| `analytics.view` | 5 | `app/Http/Controllers/Analytics/ShowAnalyticsSummaryController.php` |
| `billing.view` | 9 | `app/Http/Controllers/Billing/DownloadInvoiceDocumentController.php` |
| `billing.manage` | 8 | `app/Http/Controllers/Billing/DestroyPlanChangeController.php` |
| `security.evidence.view` | 4 | `app/Http/Controllers/Security/ShowBackupRestoreEvidenceController.php` |
| `media.manage` | 21 | `app/Domain/Media/MediaBulkAction.php` |
| `media.download_original` | 3 | `app/Http/Controllers/Media/CreateOriginalDownloadLinkController.php` |
| `order.view` | 7 | `app/Http/Controllers/Ordering/ChangeOrderStatusController.php` |
| `order.confirm` | 1 | `app/Http/Controllers/Ordering/ChangeOrderStatusController.php` |
| `order.kitchen` | 2 | `app/Http/Controllers/Ordering/ChangeOrderStatusController.php` |
| `order.settings` | 2 | `app/Http/Controllers/Ordering/ShowOrderingSwitchController.php` |
| `workspace.data.export` | 2 | `app/Http/Controllers/Workspace/RequestWorkspaceDataExportController.php` |
| `workspace.data.erase` | 2 | `app/Http/Controllers/Workspace/CancelWorkspaceErasureController.php` |
| `rating.view` | 2 | `app/Http/Controllers/Rating/ListMenuRatingsController.php` |
| `rating.reply` | 2 | `app/Http/Controllers/Rating/DeleteRatingReplyController.php` |

### Süperadmin ve destek penceresi

Platform rolü tek: `super_admin`. Kiracı rolleri gibi bir izin listesi
TAŞIMAZ; yetkisi `EnsurePlatformSuperAdmin` kapısının arkasındaki
uçların kendisidir ve bu liste yönlendiriciye sorularak üretildi.

| Ölçüm | Sayı |
| --- | ---: |
| Kapının arkasındaki okuma ucu | 18 |
| Kapının arkasındaki yazma ucu | 15 |
| Tek kiracıyı adlandıran okuma ucu | 3 |
| Tek kiracıyı adlandıran YAZMA ucu | 3 |
| Bunlardan gerekçesi KAYITSIZ olan | 0 |
| Kiracı kimliğine bürünme ucu | 0 |

**Destek penceresi** — tek bir kiracıyı adlandıran her uç, yöntemiyle
ve yazan uçların kayıtlı gerekçesiyle:

| Uç | Kip | Gerekçe |
| --- | --- | --- |
| `GET /api/admin/workspaces/{workspace}` | okuma | — |
| `GET /api/admin/workspaces/{workspace}/subscription` | okuma | — |
| `GET /api/admin/workspaces/{workspace}/support-view` | okuma | — |
| `POST /api/admin/workspaces/{workspace}/manual-payments` | **YAZMA** | Elle tahsilat kaydı — abonelik defterine yazar (docs/123). |
| `POST /api/admin/workspaces/{workspace}/support-access` | **YAZMA** | Destek erişimi — kiracının görebileceği destek oturumu defterine yazar, içeriğine değil (docs/122 §5, docs/133). |
| `POST /api/admin/workspaces/{workspace}/transactions/{transaction}/refund` | **YAZMA** | İade — ödeme defterine ters kayıt (docs/107 Faz 1.1). |

**Kapının arkasındaki bütün yazma uçları:**

- `POST /api/admin/connections`
- `POST /api/admin/connections/{connection}/probe`
- `POST /api/admin/connections/{connection}/{state}`
- `POST /api/admin/credentials/{provider}/disable`
- `POST /api/admin/plans`
- `POST /api/admin/plans/{plan}/activate`
- `POST /api/admin/release-attestations`
- `POST /api/admin/support-access/end`
- `POST /api/admin/workspaces/{workspace}/manual-payments`
- `POST /api/admin/workspaces/{workspace}/support-access`
- `POST /api/admin/workspaces/{workspace}/transactions/{transaction}/refund`
- `PUT /api/admin/connections/{connection}`
- `PUT /api/admin/credentials/{provider}`
- `PUT /api/admin/settings/billing-mode`
- `PUT /api/admin/support-requests/{supportRequest}/status`

### Adı konmamış yetenekler

Bir yetkiyi kimseye vermemek ile o yetkiyi hiç adlandırmamak aynı
şey değildir. Aşağıdaki anahtarlar `Permission` enum'unda YOKTUR ve
yokluğu kapıyla korunur.

| Anahtar | Bugün var mı | Kaynak |
| --- | :-: | --- |
| `rating.delete` | yok | docs/116 §4 — puana yanıt verilir, puan kaldırılmaz. |

<!-- KODDAN-URETILDI: SON -->

---

## 3. Süperadmin ve destek penceresi — gerekçe

Sayılar §2'de; buradaki cümleler onların NEDEN öyle olduğunu söyler.

**Süperadmin bir kiracı rolü değildir.** `PlatformRole` enum'unun tek bir
satırı var (`super_admin`) ve o satır bir izin listesi taşımaz. Kiracı
rolleri "hangi izinleri var" diye sorulur; süperadmin "hangi kapının
arkasında" diye. Kapı `EnsurePlatformSuperAdmin`'dir ve yetkisiz isteğe 403
değil **404** döndürür: platform yüzeyinin varlığı, yetkisi olmayan birine
doğrulanmaz.

**Destek penceresi tek kiracıya çivilidir.** Destekçi bir çalışma alanına
`GET /api/admin/workspaces/{workspace}` üzerinden bakar. Adresin içindeki
`{workspace}` tesadüf değil: o önekin altında "bütün kiracılar" diye bir
görünüm yoktur, her bakış tek bir kiracıyı adlandırır.

**Bakılan pencerenin yazma eşi yoktur — ama önekin tamamı salt okunur
DEĞİLDİR.** Bu ayrım ölçülerek bulundu ve burada düzeltiliyor. Kiracı
ayrıntısı ve aboneliği yalnız okunur; o iki ucun POST/PUT/DELETE eşi yok.
Aynı önekte iki YAZMA ucu var ve ikisi de ödeme defterine yazar: elle
tahsilat kaydı ve iade. Kiracının menüsüne, ürününe, medyasına, ekibine ya
da karekoduna yazan bir uç YOKTUR.

Bu iki uç §2'de gerekçeleriyle listelenir ve liste **dondurulmuştur**.
Üçüncü bir kiracı-adresli yazma ucu eklendiği gün kapı kırılır: gerekçesi
kayıtsız olan uç sayısı sıfırdan büyük olur ve o ucun kiracı içeriğine
dokunup dokunmadığı konuşulmadan yeşile dönmez. "Salt okunur" cümlesini
elle korumak yerine, onu bozan değişikliği durduran bir kapı kuruldu —
çünkü elle korunan cümle bir gün yalan söyler; bu belge zaten bir kez
öyle yazılmıştı.

**Kiracı olarak oturum açma (impersonation) bu depoda yoktur.**
`ShowManagedWorkspaceController` sınıf yorumu bunu bir kararla dondurur ve
kaynağı `docs/122` §5'tir: impersonation en tehlikeli süperadmin
yeteneğidir ve kolay bir impersonation, bir gün kimsenin hatırlamadığı bir
erişim olur. §2'deki "kiracı kimliğine bürünme ucu" satırı bugün sıfırdır
ve sıfır kalmasını kapı zorlar.

**Süperadminin yazabildiği yer PLATFORM kaydıdır.** §2'nin yazma listesi
plan, tahsilat, iade, sağlayıcı anahtarı, kip anahtarı, destek talebi
durumu ve sürüm tanıklığından ibarettir. Bir gün başka bir uç eklenirse
listeye kendiliğinden düşer — belge onu gizleyemez.

**Ne ölçülmedi.** Bu belge veritabanına doğrudan erişimi ölçmez. Sunucuya
kabuk erişimi olan biri her şeyi görebilir; ürünün yetki katmanı bunu
engellemez ve engellediğini iddia etmek yanlış olurdu. Barındırma, yedek
erişimi ve personel taraması ayrı sorulardır (`docs/107` Faz 3.1 güven
merkezi, bugün yazılmadı).

---

## 4. Kapsam dışı — bilerek

Bu belge yalnız **bugün kodda olanı** anlatır. Aşağıdakiler bilerek dışarıda:

1. **Şubeye özel roller.** Bugün roller çalışma alanı düzeyindedir; "yalnız
   Kadıköy şubesini yönetsin" diye bir rol YOKTUR. Zincir ölçeği `docs/107`
   Faz 4'ün konusu ve o faz başlamadı. Burada bir "yakında" satırı
   yazılmadı.
2. **Özel rol tanımlama.** Müşteri kendi rolünü yaratamaz; roller enum'da
   sabittir. Bu bir eksiklik olarak değil, bir olgu olarak yazılıyor.
3. **Misafir yüzeyi.** Karekodu okutan misafirin hiçbir rolü ve hiçbir
   izni yoktur; menüyü yayınlanmış hâliyle okur. Yetki matrisinin konusu
   değildir.
4. **Kimlik doğrulama ve oturum.** Parola, iki adımlı doğrulama, oturum
   süresi ve cihaz yönetimi ayrı bir konudur (`docs/33`).
5. **Denetim kaydının kapsamı.** Hangi eylemin denetime düştüğü ayrı bir
   sorudur; `docs/107` Faz 3.3 hesap düzeyinde eksik olduğunu söylüyor.
6. **KVKK hakları.** Dışa aktarma ve silme akışları bu belgenin konusu
   değil (`docs/107` Faz 3.3).
7. **Yüzey haritası.** "Bu izin hangi ekranı açar" sorusuna §2 dosya
   sayısıyla cevap verir, ekran adıyla değil. Ekran-izin haritası üretmek
   için rota-düzeyi bir gözlem gerekir; bugün yok ve uydurulmadı.

---

## 5. Ürün içindeki yüzey — hangi karar, neden

**Karar: hem belge hem ürün içi, ama aynı içerik değil.**

Belge, ızgarayı ve uç sayılarını taşır; ürün içindeki kart yalnız kiracı
rollerinin yapabildiklerini ve yapamadıklarını taşır. Ayrım `docs/53`'ün
kuralıdır: restoran sahibinin ekranında `menu.publish` gibi bir iç anahtar
ya da "kapının arkasındaki uç sayısı" gibi bir mühendislik ölçümü yazmaz.

**Yer: Ekip ekranındaki "Roller ne yapabilir?" kartı.** Sahip rolü tam
orada seçer; sorusunu sorduğu an ile cevabın durduğu yer arasında bir
tıklama bile yoktur. Ayrı bir yardım sayfasına koymak, kararı veren kişiyi
kararın verildiği ekrandan uzaklaştırırdı.

**Ölçülen bugünkü durum (değişiklikten önce).** Kart beş rolün her biri
için ELLE YAZILMIŞ tek bir cümle gösteriyordu — örneğin Yönetici için
*"Menu, QR codes, publishing. Cannot touch billing."* Cümleler o gün
doğruydu, ama hiçbiri `RolePermissions` ile bağlı değildi: bir role izin
eklendiğinde cümle sessizce eskiyordu ve hiçbir kapı bunu görmüyordu. Beş
cümle, beş potansiyel yalandı.

**Şimdi.** Cümleler kaldı (rolün bir tarifi vardır ve liste onun yerine
geçmez), ama her rolün altında artık koddan gelen iki liste var:
"yapabilir" ve "yapamaz". İkisi de `role-permission-matrix.json`'dan
okunur; o dosyayı yazan tek şey `php artisan authorization:matrix`'tir.

**Neden açılır kapanır.** Beş rolün yirmi üçer satırını aynı anda çizmek,
320 pikselde kartı ekran boyu bir listeye çevirirdi — sahibin sorusu ise
tek bir rolle ilgilidir. Her rolün ayrıntısı kendi açılır bölümündedir;
kapalıyken kart eski boyutundadır.

**Neden ürün içinde ızgara YOK.** 23 satır × 5 sütunluk bir tablo 320
pikselde yatay kaydırma olmadan çizilemez ve yatay kaydırma, dokunmalı bir
ekranda kullanıcının bir daha bulamayacağı içerik demektir
(`docs/48`, `scripts/mobile-ux-audit` kuralı 1). Izgara karşılaştırma
aracıdır ve karşılaştırma yapan okuyucu hukuk birimidir — o da bu belgeyi
okur. Sahip tek rolün cevabını arar; onun için doğru biçim listedir.
Sonuç ölçüldü: ekip kartının hikâyelerinde 320 pikselde bulgu yok (§6).

---

## 6. Ölçüm

| Kapı | Ne ölçer |
| --- | --- |
| `tests/Feature/Authorization/AuthorizationMatrixArtifactTest` | Belge ve veri dosyası koddan üretilenle birebir aynı mı; her izin kodda okunuyor mu; kiracı penceresinde yazma ucu var mı; adı konmamış yetenekler hâlâ adsız mı |
| `resources/js/.../TeamRoleGuide.matrix.test.tsx` | Ekran koddan üretilen veriyi mi çiziyor; her iznin etiketi var mı; artık var olmayan bir izin için etiket kalmış mı |
| `scripts/mobile-ux-audit` | Kartın hikâyeleri 320×568'de yatay taşma, kırpılma, küçük hedef üretiyor mu |

`php artisan authorization:matrix --check` aynı ayrışmayı testi beklemeden
bildirir.
