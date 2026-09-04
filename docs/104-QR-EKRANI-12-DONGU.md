# 104 — QR ekranı: teşhis raporu ve 12 döngü

**Sahibin talimatı (2026-09-04):** "Neden UX estetiği component bazlı bu kadar
kötü? Neden sayfa bazlı bu kadar kötü? Bu sayfa atıl kalmış. 12 döngüye al…
Bu uygulamanın kalbi burası, uygulamanın asıl amacı QR. Burası muhteşem
olmalı. Öncesinde multi parallel agents ile neden berbat olduğunu sorgula,
bul, raporla."

Üç bağımsız ajan aynı anda incelendi: (1) kullanıcı yolculuğu ve bilgi
mimarisi, (2) görsel sistem ve bileşen kullanımı, (3) rakip/alan araştırması
(web). Üçü birbirini görmeden aynı teşhise vardı.

---

## Teşhis: ekran bir ÜRETEÇ, oysa bir KÜTÜK olmalı

Sahip buraya "QR ayarı yapmaya" gelmiyor. 40 masası, bir mukavvası ve bir
yazıcısı var. Ekrandaki her öğe ya "bu benim masalarımdan biri" ya da "bu
yazıcıdan çıkacak kâğıt" diye savunulabilmeli. Bugün ekran, mühendislik
dikişlerine göre sıralanmış: hedef → dışa aktarım → toplu → temalar.

Bunun iki somut sonucu var ve ikisi de veritabanında ÇÖZÜLMÜŞ durumda:

1. **Masanın adı listeye hiç ulaşmıyor.** `qr_codes.dining_table_id` yazılıyor
   (`EloquentBulkQrCreationRepository`), ama liste DTO'su onu düşürüyor
   (`ListQrCodesController`). Sahip 40 kod arasından "masa 12"yi bulamıyor;
   ekranda 43 karakterlik token'lar var. Yeniden yazdırma fiilen imkânsız.
2. **Basılabilir sayfa yok.** PDF çıktısı A4'ün ortasına tek bir çıplak kare
   koyuyor (`MpdfQrCodePdfExportAdapter`): masa adı yok, restoran adı yok,
   "menü için okutun" yok, kesme çizgisi yok, sayfada tek kod. 40 masa = 40
   ayrı A4, her biri %97 beyaz ve baskıdan sonra birbirinden ayırt edilemez.

Araştırma bunu doğruluyor: bu kategoride kimse beş açılır liste göstermiyor.
MENU TIGER'da QR oluşturma ekranı bile yok — masa eklenir, kod sonucudur.
Toast bir sihirbaz sunar ve çıkışı basıma hazır PDF'tir. Uniqode/Bitly gibi
"QR platformu" ürünlerinde ise ekranın merkezi **adlandırılmış kodların
listesidir**.

## Bileşen bazında neden kötü

Ekran katalogdan kaçmıyor — daha incesi ve daha kötüsü: **micro** katmanı
kullanıp **compound** katmanının çözdüğü her şeyi (etiket, alan, kart, durum,
uyarı, yıkıcı eylem) elle kuruyor. Sonuç, her parçası token tüketen ama çıplak
bir `<form>` gibi görünen bir sayfa.

| Bulgu | Zaten var olan çözüm |
| --- | --- |
| 11 yerde `<label className={LABEL_CLASSES}>` + çıplak micro | `TextField` / `SelectField` — dokümanı tam da bu anti-deseni anlatıyor |
| Hata/boş/yükleniyor için çıplak `<p>` | `AlertMessage`, `Spinner`, `PageState` (tipleri "çıkışsız boş durum"u imkânsız kılmak için yazılmış) |
| Altı yerde elle kopyalanmış bağlantı sınıfı | `TextLink` — kopyalar `focus-visible` halkasını düşürüyor, yani altı klavye-görünmez bağlantı |
| "Disable" yıkıcı eylemi, "Move"la aynı boyut ve altı çizili, yalnız RENKLE ayrılıyor | `ActionMenu` + `ConfirmDialog` (`RowActions`'ta bu karar zaten yazılı) |
| Ham çözümleyici URL bağlantı metni; ham token seçenek metni | Kodun adı |
| Kalıcı devre dışı "Export" düğmesi, tek seçenekli devre dışı select, çalışmayan "bulk" alanı | Silinmeli — hiçbiri hiçbir zaman etkinleşemez |

Ve kök sebep: **bu beş dosyanın hiç story'si yok.** `DS-STORY-COVERAGE-01`
yalnız `catalog/` altını koruyor; `surface` katmanı her görsel kapının
dışında. `docs/102` §5b'nin "ekrana hiç bakılmadı" dediği körlük burada.

---

## 12 döngü

Her döngü aynı soruyu sorar — *kullanıcı yolculuğu neden kötü?* — ve kendi
kanıtıyla kapanır.

| # | Döngü | Kapsam | Durum |
| --- | --- | --- | --- |
| 0 | Kabuk: ekran yüksekliği ve yapışkan hesap | `AdminShell`, `DesktopChrome` | ✅ FF-106 |
| 1 | Ölü kontrolleri sil | tek seçenekli select, çalışmayan bulk alanı, kalıcı devre dışı Export | ✅ FF-107 |
| 2 | Hiyerarşi: indirme birincil olur | `ActionLink` primary; toplu sihirbaz ikincil | ✅ FF-107 |
| 3 | QR görseli bir TESLİMAT olur | beyaz plaka, sessiz bölge, boyut token'ı, hata durumu | ✅ FF-107 |
| 4 | Dürüst durumlar | yükleniyor/hata "önce yayınlayın" demez; 402 hakkı yükseltmeye yönlendirir | ✅ FF-108 |
| 5 | Ham URL ve token gizlenir | kodun adı öne, URL kopyala düğmesiyle ayrıntıya | ◐ FF-107 (ad alanı hazır, veri Döngü 6'da bağlanacak) |
| 6 | Masanın adı listeye ulaşır | DTO join; satır başlığı ve seçici etiketi | ⬜ |
| 7 | Yıkıcı eylem taşma menüsüne + onay | `ActionMenu` + `ConfirmDialog` | ⬜ |
| 8 | Basılabilir sayfa: N-up, ad, kesme çizgisi | PDF adaptörü + toplu uç | ⬜ |
| 9 | Baskı önizlemesi ve MİLİMETRE | kâğıt/yerleşim önizlemenin kontrolleri olur; kod boyu mm olarak yazılır | ⬜ |
| 10 | Temalar: marka + taranabilirlik kuralı | kontrast ≥ %40, logo varsa EC=H, ters kontrast yasak | ⬜ |
| 11 | Sözleşme metni: dinamik kod güvencesi | "bastırdıktan sonra da hedefi değişir; basılı kartlar çalışmaya devam eder" | ⬜ |
| 12 | Story'ler ve görsel kapı | beş dosyaya story; yüzey katmanı bir daha kapının dışında kalmaz | ◐ FF-107 (baskı bölgesi) |

---

## Döngü kararlarını yönlendiren teknik gerçekler

Araştırmadan gelen ve ürünün UYMAK ZORUNDA olduğu kurallar. Bunlar tercih
değil, kısıt:

- **Sessiz bölge:** her kenarda 4 modül boşluk (ISO/IEC 18004). Tema ya da
  çerçeve buraya giremez; dışa aktarımın içine gömülür, ayara açılmaz.
- **En küçük basılı boy:** pratikte 2,0–2,5 cm; masa kartı için 2,5–4 cm.
  Ürün milimetreyi YAZMALI ve tabanın altında uyarmalı.
- **10:1 kuralı:** kod genişliği ≈ okuma mesafesinin onda biri. "Masaya
  konacak → 4 cm, duvara asılacak → 10 cm" cümlesi, kâğıt boyu açılır
  listesinin yerine geçer.
- **Hata düzeltme:** logo varsa H (%30) zorunlu, logo alanı ≤ %25, bulucu
  desenlerin üstüne gelemez.
- **Kontrast:** ≥ %40 ve HER ZAMAN açık zemin üstünde koyu modül. Ters
  kontrastlı bir tema destek talebidir.
- **Çözünürlük:** son boyutta ≥300 DPI, 3 cm altında 600 DPI. Baskı için
  vektör; JPEG asla — sıkıştırma modül kenarlarını bulanıklaştırır.
- **Dinamik kod güvencesi:** basılı kartlar ürün kapanmadıkça çalışmalı ve
  hedefi değiştirilebilmeli. Sektördeki en pahalı arıza, üçüncü taraf
  kısaltıcıya bağlı kodların bir gün ölmesi. Bu ürünün en güçlü argümanı ve
  ekranda YAZMIYOR.

Kaynaklar: [Toast Mobile Order &
Pay](https://support.toasttab.com/en/article/Setting-Up-Toast-Mobile-Order-and-Pay),
[MENU TIGER](https://www.menutiger.com/blog/digital-menu-qr-code),
[Uniqode toplu işlemler](https://docs.uniqode.com/en/articles/5516399-bulk-operations-on-qr-codes),
[QRKit toplu üretim](https://useqrkit.com/bulk-qr-code-generator),
[QR Batch](https://qrbatch.io/),
[Microsoft — yazdırma deneyimi](https://learn.microsoft.com/en-us/windows/win32/uxguide/exper-printing),
[Scanova — en küçük boy](https://scanova.io/blog/minimum-qr-code-size/),
[QR Insights — tasarım kuralları](https://www.qr-insights.com/blog/2026-03-03-qr-code-design-best-practices),
[Restaurant Technology News — uygulama sorunu](https://restauranttechnologynews.com/2026/08/why-restaurant-qr-menus-have-an-execution-problem-not-a-concept-problem/),
[NN/g — aşamalı açığa çıkarma](https://www.nngroup.com/articles/progressive-disclosure/).

---

## "Bitti" neye benziyor

İlk kez giren bir restoran sahibi bir LİSTEYE iner, tek bir düğmeye basar,
"1" ve "40" yazar, A4 sayfanın *Masa 1 … Masa 40* ile dolduğunu ve her kodun
4,2 cm olduğunu görür, birini kendi telefonuyla okutması söylenir, Yazdır'a
basar — ve *format*, *yön*, *URL* kelimelerini hiç görmez.


---

## Tur 1 kaydı (FF-106, FF-107)

**FF-106 — kabuk.** Kök `min-h-screen` idi ve içerik uzayınca kabuk da
uzuyordu; hesap düğmesi sayfanın dibine gidiyordu. Kök `h-dvh` +
`overflow-hidden`, kaydırma ana alana, hesap düğmesi `sticky bottom-0`.

**FF-107 — görsel gerçek.** Silinenler: tek seçenekli "hedef türü" seçicisi,
hiçbir zaman etkinleşmeyen "bulk range" alanı, kalıcı devre dışı "Export"
düğmesi. Kâğıt ve yön artık PNG/SVG'de devre dışı çizilmiyor, HİÇ çizilmiyor
— devre dışı bir kontrol ekranda yer kaplar, okunur, tıklanır ve hiçbir şey
yapmaz; kullanıcı onu "bozuk" diye öğrenir.

Dizilim düzeldi: ayarlar ve tema önizlemenin ÜSTÜNE geçti (sebep sonucun
üstünde), indirme sayfanın tek marka vurgusu oldu, toplu sihirbaz ikincil
ağırlığa indi. QR görseli beyaz bir plakaya oturdu — bu hem çerçeve hem
işlevdir: taranabilmesi için sessiz bölge şart ve koyu temada saydam bir kod
taranamıyordu. Görsel üretilemezse artık tarayıcının kırık resim simgesi
değil, kodun çalışmaya devam ettiğini söyleyen bir cümle çıkıyor.

Ham çözümleyici adresi satırın başlığı olmaktan çıktı: başlık kodun adı,
adres altında kopyalanabilir bir ayrıntı ve yeni sekmede açılıyor. Devre dışı
satır artık kimliğini koruyor — eskiden yalnız "Disabled" kelimesine iniyor,
hangi kodun kapatıldığı anlaşılmıyordu.

Ve `QrPrintExportRegion.stories.tsx` eklendi: bu dosyaların hiç story'si
yoktu ve kök sebep buydu — `surface` katmanı her görsel kapının dışındaydı.


---

## Tur 2 kaydı (FF-108) — Döngü 4: sayfa yalan söylemez

Bu ekranda dört ayrı yalan vardı ve dördü de aynı kökten geliyordu: **bilmemek
ile yok olmak aynı şeye indirgenmişti.**

**1. "Önce menünüzü yayınlayın", üç ayrı dünyanın tek cümlesiydi.**
`useCurrentPublication`, cevap YOLDAYKEN de sunucu 500 DÖNDÜĞÜNDE de
`current: null` verir. Sayfa yalnız ona bakıyordu. Yayında bir menüsü ve
masalarında çalışan kartları olan sahip, sayfayı her açtığında bir an için —
sunucu yavaşsa uzunca bir an — kodlarının var olmadığını okuyordu. Oradan
çıkaracağı sonuç "yeniden yayınlayayım" ya da "yeniden bastırayım" olurdu;
ikisi de para ve zaman.

Üç hâl artık ayrı: *biliniyor*, *henüz bilinmiyor* ("Menünüzün yayında olup
olmadığı kontrol ediliyor…"), *sorulamadı* ("Sunucuya ulaşamadık. **Basılı
kodlarınız çalışmaya devam ediyor.**"). Üçüncüsündeki ikinci cümle bu ekranın
en önemli cümlesidir: kullanıcının aklına ilk gelen soruyu, sorulmadan yanıtlar.

**2. Kod listesi, yayın bilinmediğinde hiç ÇEKİLMİYORDU.** `loaded` false
kaldığı için ne "yükleniyor" ne "boş" yazıyordu — ekranda hiçbir şey ve hiçbir
açıklama yoktu. Oysa QR kodları yayından AYRI kayıtlardır: menü geri çekilmiş
olabilir, kartlar hâlâ masadadır. Yayın durumu artık yalnız **yeni kod
üretmeyi** kısıtlar, var olanı görmeyi değil.

**3. Liste hatasının çıkışı yoktu.** "Kodlar yüklenemedi. Tekrar deneyin."
yazıyor ama tekrar denemenin bir yolu yoktu; kullanıcının bildiği tek çare
sayfayı yenilemekti ve bunu da ona kimse söylemiyordu. Artık hatanın yanında
bir **Tekrar dene** düğmesi var ve basıldığında ekran dürüstçe yeniden
"yükleniyor" oluyor.

**4. Plan kısıtı, hata gibi gösteriliyordu.** Sunucu toplu masa kodu üretimi
için bilerek `402 + entitlement` döndürüyor (`StoreBulkQrCodesController`);
istemci 201 olmayan HER cevabı "Oluşturulamadı. Tekrar deneyin." diye
gösteriyordu. Tekrar denemek hiçbir zaman işe yaramaz — kullanıcı yetkisiz
değil, planı bu yeteneği içermiyor; çıkış yolu farklıdır. Artık kısıt bir
uyarı olarak söyleniyor ve yanında **Planları gör** düğmesi duruyor.
`AnalyticsPage` bu ayrımı zaten yapıyordu; QR ekranı ondan habersizdi.

**Yan bulgu — başka şubenin kartları.** Liste çıplak bir diziydi; sahip başka
bir şubeye geçtiğinde eski şubenin kodları ekranda kalıyor, yeni istek
başarısız olursa KALICI olarak kalıyordu. Liste artık çekildiği adresle
birlikte saklanıyor: cevap şu anki adrese ait değilse "henüz yüklenmedi"
sayılır. Aynı desen `useCurrentPublication`'da da kullanılıyor.

**Bir de sessiz bir performans arızası.** Liste çekme efektinin bağımlılığı
`[props]` idi — üst bileşenin her render'ı yeni bir nesne ürettiği için liste
durup dururken yeniden çekiliyordu. Bağımlılık adrese indirildi. Buna dayanan
tek test, "yeniden çekmeyi" bir render kazasıyla değil, kullanıcının bastığı
**Tekrar dene** düğmesiyle tetikleyecek şekilde yeniden yazıldı.

`kullaniciYolculugu`: Kadıköy şubesinin sahibi sabah paneli açar. Sunucu o an
yavaştır. Eskiden ekranda "Önce menünüzü yayınlayın" ve boş bir liste görür,
paniğe kapılıp menüyü yeniden yayınlardı. Şimdi "kontrol ediliyor…" yazısını
görür, bir saniye sonra kodları listelenir. Sunucu gerçekten çökmüşse
"ulaşamadık, basılı kodlarınız çalışıyor" yazısını ve bir **Tekrar dene**
düğmesini görür. 40 masa için toplu üretim planına dahil değilse "Planları gör"
düğmesine basar — sonsuza kadar "tekrar deneyin" duvarına toslamaz.
