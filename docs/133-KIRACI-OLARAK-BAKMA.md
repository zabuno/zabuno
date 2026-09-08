# 133 — Kiracı olarak bakma ve destek görünümü

**`docs/122` Y7'nin karşılığı (FF-218).** Dalgaların en sonuncusu ve en
tehlikelisi. `docs/122` §5 bu paketin sözleşmesidir:

> Kiracı olarak bakmak bir destek aracıdır ve **en tehlikeli** süperadmin
> yeteneğidir. Bu yüzden kolay olmamalı: her oturum bir sebep ister, süreli
> olur, kiracının denetim günlüğüne **kiracının görebileceği biçimde**
> yazılır, ve o oturumda yapılabilecekler kısıtlıdır. Kolay bir
> impersonation, bir gün kimsenin hatırlamadığı bir erişim olur.

Bu belge dört şartın her birinin nasıl uygulandığını, neyin **bilerek zor**
bırakıldığını, impersonation'a hiç başvurmadan cevaplanabilen destek
sorularını ve **kiracının bunu nerede gördüğünü** taşır.

## 0. Bir telefon görüşmesi

**Salı sabahı.** Kadıköy'deki kebapçı arıyor: *"Menüm görünmüyor. Masadaki
karekodu okuttum, boş sayfa açılıyor."*

**Destek görevlisi ne yapıyor?** `/platform` → **Destek masası**. Ekranın en
üstünde bekleyen talepler duruyor (en eski üstte) ve `ZB-3F7K2` orada;
görevli restoranı seçiyor. Ekran, kimsenin hesabına girmeden şunu söylüyor:

- **Ne bozuk:** *"Bir karekod hiç yayınlanmamış bir menüyü gösteriyor, yani
  masadaki basılı karekod boş bir sayfa açıyor — Moda Caddesi."*
- **Misafirin gördüğü adres:** tıklanabilir bir bağlantı. Destek görevlisi
  ona tıklıyor ve müşterinin gördüğü sayfanın **aynısını** görüyor.
- **Talebi:** `ZB-3F7K2 · Menüm görünmüyor · alındı`.

Görüşme bitiyor: *"Kahvaltı menünüz hazır ama yayınlanmamış. Panelde Menü →
Kahvaltı → Yayınla deyin, karekodunuz dolacak."* **Hiçbir hesaba girilmedi.**

**Perşembe.** Aynı restoran yine arıyor: *"Yayınla dedim, hâlâ boş."* Ekranda
bu kez hiçbir bulgu yok — her şey yerinde görünüyor. **İşte impersonation
burada başlar:** destek görevlisi sayfanın en altındaki karta iniyor, sebebi
yazıyor — *"Sahip yayınladı ama misafir menüyü göremiyor diyor (ZB-3F7K2)"* —
ve on beş dakikalık bir oturum açıyor. Sahibin panelini onun gözüyle
geziyor, hiçbir şeye dokunamıyor, on beş dakika dolunca oturum kendiliğinden
kapanıyor.

**Cuma sabahı, restoran sahibi kendi panelinde.** Ayarlar → Denetim izi.
Listenin en üstünde, kilit ikonlu bir kutu:

> **Zabuno'dan biri hesabınıza baktı**
> Destek, bir soruyu cevaplamak için hesabınızda salt okunur bir oturum
> açabilir. Bu sırada hiçbir şey değiştirilemez, ödenemez, yayınlanamaz,
> davet edilemez veya silinemez ve her oturum, açılma sebebiyle birlikte
> burada listelenir.
>
> 2026-09-10 11:04:00 → 2026-09-10 11:19:00
> **Sebep:** Sahip yayınladı ama misafir menüyü göremiyor diyor (ZB-3F7K2)
> Açan: destek@zabuno.com

Sahip aynı sabah, oturum açıldığı anda **bir de e-posta** almıştı. Yani
"kimsenin hatırlamadığı bir erişim" olma ihtimali üç ayrı yerden kapatıldı:
kendi paneli, kendi gelen kutusu ve platformun denetim günlüğü.

## 1. Şart 1 — Sebep zorunlu

`OpenSupportAccessRequest`. Üç kural üst üste durur ve üçü de aynı kolaylığı
kapatır:

| Kural | Ne engelliyor |
| --- | --- |
| `required` | Alan hiç gönderilmeden oturum açmayı |
| Doğrulamadan ÖNCE kırpma | Yirmi boşluğun bir sebep sayılmasını |
| `min:12` | "test", "ok", "bakıyorum" |

**Varsayılan değer YOKTUR ve olmayacak.** Bir varsayılan, sebebi soran
kutuyu bir onay kutusuna çevirirdi. Ekran da bunu söyler: sebep alanının
altında *"en az 12 karakter, ve restoran sahibi buraya yazdığınızın
tam olarak aynısını okuyacak"* yazar. Süperadmin sebebi, kimin okuyacağını
bilerek yazar.

Sebep **kırpılmadan, kısaltılmadan, koda çevrilmeden** saklanır ve üç yerde
aynı cümle olarak görünür: kiracının paneli, kiracıya giden e-posta,
platformun denetim günlüğü.

## 2. Şart 2 — Süreli

Süre **koda gömülü değildir**: `support.access_session_minutes`
(env `SUPPORT_ACCESS_SESSION_MINUTES`), varsayılan **15 dakika**.

`SupportAccessWindow` yapılandırmayı koşulsuz okumaz; iki kural üstüne biner
ve **ikisi de kapıyı sıkar, gevşetmez**:

1. **Okunamayan değer sonsuz değildir.** Boş, sıfır, negatif ya da sayı
   olmayan bir değer "sınırsız" anlamına gelmez, `FALLBACK_MINUTES`'a (15)
   düşer. Bir yazım hatasının bütün gün açık kalan bir oturuma dönüşmesi, bu
   paketin engellemek için var olduğu kusurun ta kendisidir.
2. **Tavan vardır** (`support.access_session_max_minutes`, 60). Süreyi sahip
   seçer, ama "süreli olma" özelliğini yapılandırma iptal edemez.

**Süre dolması bir iş değil, bir sorgudur.** Oturumu kapatan bir kuyruk
işçisi, cron ya da zamanlayıcı **yoktur**: "açık mı?" sorusu her istekte
`expires_at > now` ile sorulur. Bir zamanlayıcıya bağlansaydı, zamanlayıcısı
çalışmayan bir kurulumda oturum sessizce açık kalır ve "çıkış yapmayı
unuttu" kusurunu tam olarak geri getirirdi.

**Uzatma ucu yoktur.** `SupportAccessPort` arayüzünde `extend`/`renew`
benzeri bir yöntem bulunmaz ve yönlendirme tablosunda `support-access`
kelimesini taşıyan **tam üç** yol vardır (bir okuma, bir açma, bir bitirme).
Bunu bir test dizgeyle değil, yönlendirme tablosunu okuyarak dondurur.

**Üst üste binen oturum da yoktur** — ve bunu ayrı bir kural yapmadık:
oturum açma ucunun kendisi bir yazmadır, dolayısıyla açık bir oturumda
reddedilir. Tek kural iki kusuru birden kapatıyor.

**Yeniden bakmak yasak değil, sessiz değil.** Süresi dolan bir oturumun
ardından ikinci bir oturum açılabilir; ama o, yeni bir sebep ister ve
kiracının panelinde **ikinci bir satır** olarak görünür. Sessiz yenileme
diye bir şey yok; ısrarlı bakış görünür bir iz bırakır.

## 3. Şart 3 — Kiracının GÖREBİLECEĞİ kayıt

Bu maddenin en kolay atlanacak yarısı, kaydın **yazılmasıyla** işin bittiğini
sanmaktır. Sahibin göremediği bir denetim kaydı, denetim değil bir günlük
dosyasıdır.

**Kiracı bunu nerede görüyor:** panelinde **Ayarlar → Denetim izi**. Yani
"bunu kim, ne zaman yaptı?" diye zaten baktığı ekran. Yeni bir uç açılmadı;
sahibin öğrenmesi gereken yeni bir yer yok.

Kayıt orada **iki kez** görünür ve ikisi de kasıtlıdır:

| Yer | Neden |
| --- | --- |
| Listenin **üstünde**, kilit ikonlu ayrı bir kutu | Kendi ekibinin fiyat değişikliğiyle, dışarıdan birinin hesabına bakması aynı ağırlıkta iki satır değildir. Yüz satırlık bir izin ortasına düşen kayıt "teknik olarak gösterildi" demektir, "sahibin gözüne çarptı" demek değil |
| Zaman çizgisinde, `Account access` kaynağıyla bir satır | Olayın kendi sırasında, kendi menü ve medya olaylarının arasında durması gerekir; ayrı bir kutu onu zaman çizgisinden kopararak "bu ayrı bir şey" izlenimi verirdi |

Kutu üç şeyi söyler ve üçü de sahibin sorduğu şeydir: **ne zaman ve ne kadar
süreyle**, **neden**, **kim**. Dördüncü bir cümle daha var ve sahibi
rahatlatan yarısı odur: *"Bu sırada hiçbir şey değiştirilemez, ödenemez,
yayınlanamaz, davet edilemez veya silinemez."*

**Metin sahibin dilinden yazıldı, platformun dilinden değil.**
"impersonation", "oturum jetonu", "salt okunur uç" gibi kelimeler yok;
birinin hesabına baktığı, ne kadar süreyle baktığı ve neden baktığı var.

**Ve haber verme kayıttan ayrıdır.** Oturum açıldığı an çalışma alanının
**sahiplerine** e-posta çıkar (`SupportAccessOpened`). `docs/93` ilkesi
aynen geçerlidir: önce kayıt, sonra gönderim; dışarı giden taşıyıcı yoksa
(`mail.default = log`) **hiç denenmez ve damga atılmaz** — günlüğe yazılmış
bir e-posta kimseye ulaşmamıştır. Postası yapılandırılmamış bir kurulumda
sahip yine de görür: kayıt panelinde durur.

Süperadmin tarafında da görünür (`/platform` → Denetim günlüğü, kaynak
`Tenant access`), ama **o, bunun yerine geçmez**. Platformun kendini
denetlemesi ayrı bir ihtiyaçtır; §5'in istediği, kiracının görmesidir.

## 4. Şart 4 — Yapılabilecekler kısıtlı

**Yasak bir `if` değil, bir katmandır.** `EnsureSupportAccessIsReadOnly`
hem `web` hem `api` rota gruplarına eklenmiştir. Açık bir destek oturumu
varken **güvenli olmayan her HTTP yöntemi** (POST/PUT/PATCH/DELETE) 403 alır
ve **denetleyicisine hiç ulaşmaz**.

Neden ara katman, neden denetleyici değil: kısıtı yüz denetleyiciye bir `if`
olarak dağıtmak, bir gün yazılacak yüz birinci denetleyicinin o `if`'i
unutmasıyla biterdi. Burada yeni bir uç eklemek yasağı gevşetmez, çünkü yeni
uç bu katmanın **arkasında** doğar.

Neden yöntem listesi, neden fiil listesi değil: "ödeme, plan değişikliği,
silme, davet, yayın" diye sayılmış bir liste, listeye girmeyi unutan bir
fiil demektir. HTTP'nin kendi ayrımı (RFC 9110 §9.2.1) hepsini kapsar.

**Ölçüm, dizgenin geçtiği yerde değil kullanıldığı yerde yapıldı.**
`TenantSupportAccessTest` beş ayrı gerçek yazma ucuna gerçek istek atar ve
beşinin de reddedildiğini ölçer:

| Aile | Uç |
| --- | --- |
| Ödeme | `POST /api/admin/workspaces/{w}/manual-payments` |
| Plan/kip değişikliği | `PUT /api/admin/settings/billing-mode` |
| Silme | `DELETE /api/workspaces/{w}/menu/{menu}` |
| Davet | `POST /api/workspaces/{w}/team/invitations` |
| Yayın | `POST /api/workspaces/{w}/menu/{menu}/publications` |

**İstisna listesi iki satırdır ve bir testle dondurulmuştur:**
`api/admin/support-access/end` (oturumu bitirmek) ve `logout` (üründen
çıkmak). İkisi de kiracı verisine dokunmaz. Üçüncü bir satır eklemek bir
kapsam kararıdır ve sessizce yapılamaz — test patlar.

**İkinci kilit: yetki.** `SupportAccessAuthorization`, açık oturumun işaret
ettiği **tek** çalışma alanı için yalnız yedi **okuma** izni verir:
`workspace.view`, `menu.view`, `qr.view`, `analytics.view`, `billing.view`,
`order.view`, `rating.view`. Yönetme izni **hiç yok**. Neden iki kilit: ilki
yazmayı kapatır, ikincisi ekranın yazma düğmesini **hiç çizmemesini** sağlar
(`docs/98` FF-74: yetkisiz eylem çizilmez, 403 gösterilmez). Tek kilitle
destek görevlisi tıklayabildiği ama işlemeyen düğmeler görürdü.

`workspace.manage` verilmediği için kiracının **ekip listesi**, **destek
talepleri** ve **kendi denetim izi** destek oturumunda da kapalıdır. Görünen
şey menü, karekod, puan ve kullanım durumudur; ekibin kim olduğu değil.

**Bakış tek restorana çivilenir.** Bağlam oturum açılırken **sunucuda**
kurulur; destek görevlisi kiracıyı bir yazma isteğiyle seçmez ve seçemez —
`PUT /api/workspace-context` de bir yazmadır ve kilidin arkasındadır.
"Sırayla hepsine bakmak" diye bir oturum yoktur.

**Oturum istemcide yaşamaz.** Ne çerezde, ne `localStorage`'da, ne ekranın
belleğinde. Bir veritabanı satırıdır ve her istekte yeniden okunur. Ekran
"hâlâ açık mı?" sorusunu her işlemden sonra sunucuya yeniden sorar; tarayıcıda
tutulan bir sayaç, sekme uyuduğunda ya da makine kapandığında yalan söyler.

## 5. Impersonation'sız cevaplanabilen destek soruları

`docs/122` §3 boşluk 3'ün istediği **destek görünümü**:
`GET /api/admin/workspaces/{workspace}/support-view`, ekranı `/platform` →
**Destek masası**.

**Bu ekranın varoluş sebebi impersonation'ı gereksiz kılmaktır.** En
tehlikeli yeteneği en son çare yapmanın yolu, ondan önce gelen çareyi
gerçekten kurmaktır. Ölçülen liste — kiracının gözüne **hiç girmeden**
cevaplanabilenler:

| Müşterinin cümlesi | Ekranın cevabı |
| --- | --- |
| "Menüm görünmüyor" | Karekod hangi menüyü gösteriyor, o menü hiç yayınlanmış mı, hangi sürüm |
| "Karekod boş sayfa açıyor" | Karekodun hedefi var mı, kapalı mı, hedefteki menü yayında mı |
| "Müşterim ne görüyor?" | Misafirin adresi, tıklanabilir. Karekod zaten masada, adres zaten herkese açık |
| "Siparişler gelmiyor" | Şubenin sipariş şalteri açık mı |
| "Hesabım çalışmıyor" | Çalışma alanı durumu, abonelik durumu ve planı |
| "Şubem yok mu?" | Kaç şube, kaç menü, hangi şubede karekod var |
| "Geçen hafta yazmıştım" | Bu restoranın destek talepleri: referans, konu, durum, ne zaman |
| "Bana kim baktı?" | Bakış geçmişi, sebepleriyle — kiracının gördüğü listenin aynısı |

**Bulgular uydurulmaz, türetilir.** Listedeki her kod bir sorgunun
sonucudur; hiçbiri tahmin, öneri ya da olasılık taşımaz. Cevabı bilinmeyen
bir soru listeye hiç girmez — "belki şudur" diyen bir destek ekranı, destek
görevlisini yanlış yere bakmaya yollar.

Sekiz bulgu var ve **sıraları kasıtlıdır**: en yukarıdaki, aşağıdaki her
şeyi anlamsız kılandır. Askıya alınmış bir çalışma alanında karekodun hangi
menüyü gösterdiğini tartışmanın anlamı yoktur.

`workspace_not_serving` · `subscription_not_active` · `no_location` ·
`location_has_no_menu` · `location_has_no_qr` · `qr_disabled` ·
`qr_has_no_destination` · `qr_menu_never_published`

**Kiracının içeriği çıkmaz.** Ürün adları, fiyatlar, misafir yorumları,
sipariş içerikleri ve destek talebinin gövdesi bu cevapta **yoktur**:
sorulan şey "ne var", "ne yazıyor" değil. Taşınmayan alan sızmaz.

**Ekranın sırası bu belgenin argümanıdır:** bekleyen talepler kuyruğu →
kiracı seçici → bulgular → misafirin gördüğü adresler → bu kiracının
talepleri → bakış geçmişi → **ve en sonda** kiracı olarak bakma. Son çare
olması gereken şey, ilk görülen şey olmamalı.

**Kuyruk kiracıdan önce gelir** çünkü destek günü bir restoran seçmekle
değil, bekleyen bir talebi okumakla başlar. Aynı kart `docs/125` §6'nın
açık bıraktığı borcu da kapatıyor: uçlar FF-201'de yazılmıştı, okuyan yer
yoktu. **Cevap yazma yüzeyi hâlâ yok** ve kart bunu kendi cümlesiyle
söylüyor — bir talebi "cevaplandı" işaretlemek zamanı kaydeder, hiçbir şey
göndermez. Var olmayan bir kutuyu çizmek, cevabın gittiğini sandırırdı.

## 6. Neyin bilerek zor bırakıldığı

Bu paketin reddettiği kolaylıklar, tek tek:

| Kolaylık | Neden reddedildi |
| --- | --- |
| Sebep alanını isteğe bağlı ya da varsayılan değerli yapmak | Varsayılan, sebebi soran kutuyu bir onay kutusuna çevirir |
| Süreyi uzun tutmak ya da yenilenebilir yapmak | Uzatılabilir bir süre yalnız ertelenmiş bir süresizliktir |
| Denetim kaydını yalnız süperadmin tarafında göstermek | Sahibin göremediği kayıt denetim değil, günlük dosyasıdır |
| "Şimdilik salt okunur, yazma sonra eklenir" kapısı bırakmak | Bırakılan kapı, bir gün kimsenin hatırlamadığı erişim olur |
| Oturumu çerezde ya da istemcide tutmak | Süre dolduğunda kimsenin zorlayamayacağı bir söz olurdu |
| Kiracı sahibine haber vermeden bakmak | Sessiz erişim, denetimi bir arşive dönüştürür |
| Kiracı ayrıntısı ekranına "bakmaya başla" düğmesi koymak | Zorluk şartını sessizce iptal ederdi (`docs/122` §5) |

Son satır ayrıca önemlidir: **kiracı ayrıntısı ekranı (Y2) salt okunur
kaldı.** Bakma kendi ucundan, kendi sebebiyle ve kendi süresiyle açılır.
Bir listenin yanına konmuş tek tıklık bir düğme, bu belgedeki her cümleyi
pratikte iptal ederdi.

## 7. Ne YAPILMADI

- **Uzatma yok.** Ne uç, ne arayüz yöntemi, ne düğme.
- **Başkasının oturumunu kapatma yok.** Bitirme ucu gövdesinde oturum
  kimliği taşımaz; kim olduğu oturumdan okunur.
- **Kiracı verisini değiştiren hiçbir fiil yok** — hiç.
- **Kiracının içeriği okunmaz:** destek görünümü ürün adı, fiyat ya da
  misafir yorumu döndürmez.
- **Kayıt düzeltilemez ve silinemez.** Düzeltilebilen bir denetim izi
  denetim izi değildir.
- **Kiracının kendi yönetim ekranları destek oturumunda da kapalı**
  (ekip, destek talepleri, denetim izi).

## 8. Kanıt

| Test | Ne donduruluyor |
| --- | --- |
| `TenantSupportAccessTest` (25) | Sebep (yok / boşluk / tek kelime / birebir saklanır); süre yapılandırmadan gelir, okunamayan değer sonsuz değildir, tavan uygulanır, süre kendiliğinden dolar; uzatma rotası yok; üst üste oturum yok; sahibin panelinde iki yerde görünür ve oturum bitince de durur; taşıyıcı varken e-posta çıkar, yokken damga atılmaz; **beş yazma ailesi istek düzeyinde reddedilir**; başka kiracıya sızmaz; bağlam değiştirilemez; yalnız yedi okuma izni; kiracının yönetim ekranları kapalı; bitirme ve iki kez bitirme; yetkisiz açamaz; istisna listesi iki satır; oturumsuz süperadmin kısıtlı değil |
| `TenantSupportViewApiTest` (12) | Yetki (misafir / kiracı sahibi / bilinmeyen kiracı); misafir adresi ve yayın sürümü; sekiz bulgunun ayrımı; bulgunun hangi şubeye ait olduğu; askıya alınmışta sıra; şubesiz kiracıda erken durma; talepler gövdesiz; bakış geçmişi; **ve hepsinin oturumsuz cevaplandığı** |
| `SupportDesk.test.tsx` (9) | Bulgu ve misafir adresi oturumsuz okunur; sebep yazılana kadar düğme pasif; tek kelime yetmez; sahibin okuyacağı uyarısı ekranda; açık oturum en üstte, süresi ve sebebiyle; ikinci oturum açılamaz; reddin cümlesi sunucunundur; bulgusuz hâl dürüst; çevirisi olmayan bulgu gizlenmez |
| `AuditTrailRegion.test.tsx` (4) | **Sahibin kendi panelinde** kutu, sebep, bitiş anı, kim ve "hiçbir şey değiştirilemez" cümlesi; zaman çizgisinde de satır; kimse bakmadıysa kutu hiç çizilmez; biten oturum penceresiyle yazılır |
| `SupportQueue.test.tsx` (6) | Kim yazdı, ne yazdı, nasıl ulaşılır; alındı e-postası çıkmayan talep işaretlenir; "işaretlemek göndermez" cümlesi ekranda; durum geçişi ve mevcut durumun düğmesinin çizilmemesi; süzgeç sunucuda; boş kuyruk dürüst |
| `PlatformApp.supportDesk.test.tsx` (2) | Gözetim grubunda bölüm; `/platform/support` adresi; kiracı seçilmeden bakma kartı çizilmez ama **kuyruk gelir** |
| `ModularApiRouteRegistrationTest` | Dört yeni imza; Y2'nin "bir gün belirirse kapsam kararıdır" notunun kararı |
| `scripts/mobile-ux-audit` | `SupportDesk` beş, `SupportQueue` iki hikâye; 320×568 gerçek Chrome |

## 9. Ürün iddiası

**Çalışır:** Destek görevlisi bir restoranın bugünkü durumunu — misafirin
gördüğü adres dahil — hiçbir hesaba girmeden okuyabiliyor ve çağrıların
çoğunu orada bitirebiliyor. Gerektiğinde sebep yazarak süreli, salt okunur
bir oturum açabiliyor; restoran sahibi bunu ertesi gün kendi panelinde ve
aynı gün kendi gelen kutusunda görüyor.

Bekleyen destek talepleri artık üründe okunuyor ve durumları işaretlenebiliyor
(`docs/125` §6'nın kapanışı).

**Çalışmaz:** Destek görevlisi kiracı adına **hiçbir şeyi düzeltemez** —
bu bilerek böyle (§4); düzeltme sahibin kendi elindedir ya da kendi
ekranından, kendi onayıyla yapılır. Kiracıya **cevabı üründen yazma** yüzeyi
hâlâ yok: cevap e-postayla yazılır ve bir talebi "cevaplandı" işaretlemek
hiçbir şey göndermez. Postası yapılandırılmamış bir kurulumda kiracıya
haber e-postası **çıkmaz**; kayıt yine de panelde durur.

## 10. Bu belgenin kendi gerekçe süresi

`docs/109` §8.6. Dört şart üründen değil, riskten türer ve ürün büyüdükçe
(iş ortağı portalı, bölgesel destek ekipleri) yeniden ölçülür — ama
gevşetilmesi için ölçülür, sıkılması için değil.
