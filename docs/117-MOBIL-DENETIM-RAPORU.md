# 117 — Mobil denetim: 320 pikselde ne oluyor?

**Sahibin kararı (2026-09-05):** *"Sana her zaman diyorum ki 320 px first,
mobile first… ama sen yine de önce bilgisayar, ardından telefon uyumluluğu
sağlamaya çalışıyorsun. Bunu genel global kuralların içerisine entegre
etmelisin."*

Kural artık global `CLAUDE.md` içinde `TOUCH-FIRST-INTERFACE` başlığıyla
yazılı ve teknolojiden bağımsız. Bu belge o kuralın bu depodaki ilk
uygulamasıdır: **ölçüm, eleştiri, plan.**

## 0. Bu raporun ilk bulgusu: ölçüm yoktu

Bu deponun bütün arayüz testleri jsdom'da koşuyor ve **jsdom düzen
hesaplamaz**: her kutu sıfır yüksekliktedir, hiçbir şey taşmaz, hiçbir dokunma
hedefi ölçülemez. Yani *"320 pikselde çalışıyor mu?"* sorusu bu depoda hiç
sorulmamıştı — ve sorulmadığı için cevabı bilinmiyordu, ama raporlarda
"geçti" diye görünüyordu.

Aynı gün kabuk düzeni **üç kez üst üste** sahibin ekranında kırıldı (taşma,
çökme, kaydırmanın ölmesi) ve üçünde de bütün testler yeşildi. Dördüncüsünü
beklemek yerine araç kuruldu: `scripts/mobile-ux-audit`, gerçek Chrome'da,
320×568'de, Storybook'un statik çıktısındaki **317 hikâyenin hepsini** ölçüyor.

### Aracın kendisi de bir kez yanıldı — ve bu kayda değer

İlk çalıştırma **236/317 hikâyede** sorun bildirdi. İnceleyince çoğu sahte
çıktı ve sebepleri öğreticiydi:

| Sahte kalıp | Kaç bulgu | Neden yanlıştı |
| --- | --- | --- |
| "en derin kutu dar" | 217 | Isı ızgarasının bir hücresi 34 piksel; bu dolgu israfı değil, hücrenin kendi boyu |
| "metin sessizce kırpıldı" | 138 | Ölçülen şey ekran okuyucuya özel 1×1 piksellik gizli metindi |
| "düğme kenardan kırpıldı" | 13 | Bilerek yana kayan kategori şeridi; kap kaydırılıyor, kusur yok |
| "onay kutusu 16 piksel" | 42 | Kullanıcı kutuya değil ETİKETİYLE birlikte dokunuyor |

Düzeltilmiş araç **67/317** bildiriyor. Bu fark bir ayrıntı değil: yanlış
ölçen bir araçla yazılmış bir rapor, ölçüm yokluğundan daha zararlıdır —
çünkü güvenilir görünür. Bu deponun tekrar eden kusuru zaten budur
(`docs/109` §8.7): *çalışıyor ama söylediği şeyi ölçmüyor.*

## 1. Ölçülen dört kusur ailesi

### K1 — Dokunma hedefi 44 pikselin altında · 49 hikâye

En kalabalık aile ve en sistematik olanı. Tek tek bileşen hatası değil, **iki
jetonun değeri**:

| Hedef | Ölçülen | Nerede |
| --- | --- | --- |
| Metin girdisi | 320×**42** | Her form; `--control-height` |
| Sekme düğmesi | 75×**42** | Sekme şeridi |
| İkon düğmesi | **36×36** | Üst çubuk (menü aç, arama) |
| Çekmece kapatma | **32×32** | Telefon gezinti çekmecesi |
| Marka bağlantısı | 82×**24** | Üst çubuk |
| Onay kutusu + etiketi | 237×**24** | Form alanları |
| Çalışma saati girdisi | 259×**24** | Şube saatleri |
| Yeniden adlandır | **12**×44 | Menü kataloğu — **en sert bulgu** |
| Taşı / Vazgeç | 39×**24** | Karekod listesi |

**42 piksel, 44'ün iki eksiği değil.** Parmak ucu ortalama 8-10 milimetredir;
44 CSS pikseli o ölçünün karşılığıdır ve altına inen her hedef yanlış dokunma
olasılığını taşır. 24 piksellik bir hedef ise **yarıdan azdır**.

**12 piksel genişliğindeki "yeniden adlandır" düğmesi** ayrı bir cümleyi hak
ediyor: menü kataloğunda bir ürünün adını değiştirmek, telefonda neredeyse
imkânsız. Bu bir stil kusuru değil, **erişilemeyen bir yetenek**.

### K2 — Ekranın genişliği iç içe dolgulara gidiyor · 12 ekran

Sahibin ifadesiyle: *"grid gap ve grid margin, mobil cihazlarda fazla çalıyor
ekrandan."* Ölçüm bunu doğruluyor:

| Ekran | Kullanılabilir | Sol | Sağ |
| --- | --- | --- | --- |
| Sayfa başlığı | **74**/320 | 0 | 246 |
| Kurulum yolculuğu | **178**/320 | 56 | 86 |
| Karekod dışa aktarma formu | **186**/320 | 32 | 102 |
| Karekod liste satırı | **182**/320 | 32 | 106 |
| Panel ana bölümleri | **214**/320 | 49 | 57 |

Karekod liste satırında **138 piksel** — genişliğin %43'ü — hiçbir şey
göstermiyor. Sebep birikme: sayfa dolgusu + kart dolgusu + satır dolgusu +
ikon sütunu üst üste biniyor. Masaüstünde 1440 pikselin 138'i görünmez;
320'nin 138'i ekranın yarısıdır.

Sahibin ekran görüntülerindeki "Fotoğra…" kesilmesi ve "+ Kategori"nin sağdan
taşması bunun görünen yüzü: içerik için kalan şeride sığmıyor.

### K3 — Komşu hedefler birbirine yapışık · 5 ekran

| Yer | Ayrım |
| --- | --- |
| Profil sayfası: iki parola girdisi | **0 px** |
| Profil sayfası: "Save new password" ↔ girdi | **0 px** |
| Sekme şeridi: sekmeler arası | 4 px |

Sıfır piksel ayrım, yanlış dokunmayı bir hata değil **bir olasılık** yapar.
Parmak ekranı kapattığı için kullanıcı neye bastığını göremez; yanlış alana
inen bir dokunuş sessizce başka bir alanı odaklar.

### K4 — Isı ızgarası sayfayı yana kaydırıyor · 4 hikâye

Yedi gün × yirmi dört saat tablosu 320 piksele sığmıyor (1132 piksel) ve
**kendi kaydırma kabı yok** — sayfanın kendisi kayıyor. Dar ekranda yatay
kaydırma, kullanıcının bir daha bulamayacağı içerik demektir.

## 2. Neyi ölçmedim ve iddia etmiyorum

- **Estetik, hiyerarşi, kelime seçimi, akış.** Bunlar insan kararıdır. Araç
  ölçtüğü şeyle sınırlıdır ve fazlasını iddia etmez.
- **iOS Safari davranışı.** Araç Chrome'da koşuyor. Aynı gün Safari'ye özgü
  bir kusur (belge kayması) yaşandı ve bu araç onu yakalayamazdı.
- **Gerçek parmak.** 44 piksel bir vekil ölçüdür, kullanıcı testi değildir.
- **Kapsam dışı hikâyeler** (`micro-`, `compound-`) yatay israf için
  ölçülmedi: bir avatarın kendi boyu israf değildir. Onlar için sonuç
  "ölçülmedi"dir — **"sorun yok" değil**.

## 3. Çözüm planı — sırası kasıtlı

Sıra, **düzeltmenin yayılma alanına** göre: bir jetonu düzeltmek elli ekranı
düzeltir, bir ekranı düzeltmek bir ekranı.

| # | İş | Kapsam | Neden bu sırada |
| --- | --- | --- | --- |
| M1 | Denetim aracını CI'ya bağla, mevcut borcu **dondur** | araç | Bugünkü borç düzelmeden yenisi eklenmesin. Yeni bir ihlal kırılır, mevcut olan sayılır |
| M2 | `--control-height` ve dokunma jetonları: dar ekranda 44 | jeton | 42→44 tek değişiklikle her form, sekme ve girdi düzelir |
| M3 | İkon düğmesi ve çekmece kapatma: 36/32 → 44 | jeton | Aynı aile; ikon boyu değişmeden dokunma alanı büyür |
| M4 | Boşluk ölçeği cihaza duyarlı: **büyük hedef + sıkı boşluk** | jeton | K2'nin kökü. Dolgu birikmesini kesmeden ekran ekran uğraşmak boşuna |
| M5 | Profil formu: bitişik hedefleri ayır | ekran | K3 |
| M6 | Karekod liste satırı ve dışa aktarma formu: dar ekran yerleşimi | ekran | K2'nin en sert iki örneği |
| M7 | Menü kataloğu: 12 piksellik düğme ve satır düzeni | ekran | K1'in erişilemez örneği; sahibin ekran görüntüsündeki kesilme |
| M8 | Isı ızgarasına kendi kaydırma kabı | bileşen | K4 |
| M9 | Sayfa başlığı: dar ekranda dikey ritim | ekran | İlk ekranı içerikten önce doldurmasın |

Durum: **M1, M2, M3, M4 bitti.** Kalan: M5–M9.

### M2 + M3 + M4 — yapıldı (2026-09-05)

Üçü tek pakette, çünkü üçü de aynı kökü değiştiriyor: **hedef ölçeği ile ölü
alan ölçeğinin ayrılması.**

#### Ne değişti

| # | Değişiklik | Yer |
| --- | --- | --- |
| M2 | Metin girdisi ve sekme yüksekliği `--control-height`e bağlandı (42 → 44) | `storybook-demo/micro/Input`, `catalog/navigation/compound/Tabs` |
| M3 | İkon düğmesi 36→44, kapatma düğmesi 32→44, marka bağlantısı 24→44 | `IconButton`, `CloseButton`, `BrandMark` |
| M3 | Onay kutusunun hedefi ETİKETİ oldu (24 → 44) | `CHOICE_LABEL_TOUCH_CLASS` → `CheckboxField`, `OpeningHoursFields` |
| M3 | Karekod satırındaki "taşı / vazgeç" 24 → 44 | `QrCodeListItem` |
| M3 | İki hikâye elle kurulmuş ham düğmesini kataloğun düğmesiyle değiştirdi | `EmptyState.stories`, `PageHeader.stories` |
| M4 | Ölü alan ölçeği dar ekranı TABAN aldı: `--space-fluid-*` tabanları 12/16/24 → 8/12/16; **tavanlar değişmedi** | `app.css` |
| M4 | Sayfa çerçevesi ve kart dolgusu sabit adımdan akışkan ölçeğe geçti | pano ve yayın yüzeyleri, ekran hikâyelerinin çerçeveleri |

**42, 44'ün iki eksiği değildi — bir KARAR bile değildi.** Ölçüm gösterdi ki
jeton (`--density-hit-area-min: 44px`) baştan doğruydu; eksik olan
TÜKETİMDİ. Girdi 42 pikselde çiziliyordu çünkü kimse 42 dememişti: `py-2` +
satır yüksekliği + kenarlık öyle toplanıyordu. Yükseklik bir yan üründü.

**M4'ün kök sebebi `clamp()`in ALT SINIRIYDI.** Akışkan ölçek dar ekranda hiç
daralmıyordu — 320 pikselde alt sınır zaten devredeydi ve o alt sınır masaüstü
ölçüsüydü (12/16/24px). Yani "akışkan" olan tek yön yukarıydı: dar ekran taban
değil, kırpılmış masaüstüydü. Tabanlar 8/12/16'ya indi, tavanlar aynı kaldı —
masaüstü görünümü korunur, kazanılan yer yalnız dar ekranda geri döner.

#### Ölçüm — önce / sonra

Aynı araç, aynı 317 hikâye, 320×568:

| | Önce | Sonra |
| --- | --- | --- |
| Etkilenen hikâye | 66 | **24** |
| `small-target` | 91 bulgu / 48 hikâye | **7 bulgu / 7 hikâye** |
| `wasted-width` | 12 bulgu / 12 hikâye | **10 bulgu / 10 hikâye** |
| `tight-gap` | 9 bulgu / 5 hikâye | **6 bulgu / 3 hikâye** |
| `overflow-x` | 4 bulgu / 4 hikâye | 4 bulgu / 4 hikâye |

Dondurulmuş borç listesinden **43 hikâye silindi**; yeni ihlal **0**.

`tight-gap` kendiliğinden düştü ve sebebi öğretici: sekmeler arasındaki 4
piksel, sekmeler 42 pikselken bir kusurdu. Hedef 44'e çıkınca aynı 4 piksel
kusur olmaktan çıktı — çünkü ayrım kuralı yalnız KÜÇÜK hedefler için geçerli.
Bir jetonu düzeltmek, ona bağlı ikinci bir kusur ailesini de kapattı.

#### Kapanmayan borç ve NEDEN

**Bunlar sessizce bırakılmadı; her biri bir karardır.**

- **Onay kutusunun kendisi 16×16 (4 hikâye).** Kutuyu 44 piksele büyütmek
  ikonu büyütmek olurdu ve 320 piksellik bir satırın önemli bir kısmını
  yerdi — M4'ün tam tersi. Kullanıcının dokunduğu şey zaten kutu değil,
  `htmlFor` ile bağlı etikettir ve o hedef artık 44 (`CheckboxField` ölçümü
  düzeldi). Kalan bulgu, etiketi OLMAYAN bir `micro` hikâyesinin kendisidir;
  üründe böyle bir kullanım yok. Araç bunu bir istisna olarak tanımıyor ve
  aracı bulgu susturmak için değiştirmek bu deponun en tehlikeli alışkanlığı
  olurdu (§0).
- **Metin içi bağlantı (2 hikâye).** Bir cümlenin içindeki bağlantıyı 44
  piksel yüksekliğine çıkarmak satır akışını bozar; WCAG 2.5.8 de "cümle
  içindeki hedef"i açıkça muaf tutar. Araçta o muafiyet yok.
- **Menü kataloğunda 12 piksellik "yeniden adlandır" (1 hikâye).** Düğmenin
  YÜKSEKLİĞİ zaten 44; kusur GENİŞLİKTE ve sebebi jeton değil, satırın 320
  pikseldeki yerleşimi. Bu **M7**'nin işi ve orada kalıyor.
- **Profil formunda bitişik hedefler (3 hikâye).** **M5.**
- **Isı ızgarasının yatay taşması (4 hikâye).** **M8.**
- **`wasted-width` kalan 10 hikâye.** İkiye ayrılıyor:
  - Dolgu birikmesi GERÇEKTEN azaldı: kurulum yolculuğu 178 → **224**/320,
    pano bölümleri 214 → sınırın üstüne çıktı ve listeden düştü, karekod
    satırının sol boşluğu 32 → **16**.
  - Ama kalan hikâyelerde ölçülen sayı artık dolguyu değil **içeriğin
    kendi genişliğini** anlatıyor. `qrexportconfigform` için "kullanılabilir
    186px" aslında `<legend>` etiketinin genişliğidir: o ekranda metin taşıyan
    tek yaprak odur, alanların metni `<label>` içinde bir metin düğümüdür ve
    araç yaprak öğe ölçer. `pageheader--default` için 74 piksel, "Orders"
    kelimesinin kendisidir. Dolguyu sıfırlamak bu sayıları değiştirmez.
    Bunlar aracın bir SINIRIDIR ve §0'ın kaydettiği türden bir sahte kalıptır;
    aracı düzeltmek ayrı bir iştir ve bu pakette YAPILMADI — o yüzden sonuç
    "sorun yok" değil, **"bu metrik burada dolguyu ölçmüyor"**.

#### Masaüstü bozulmadı

`shell-scroll-gate` 320×568 ve 1440×900'de yeşil; 1970 jsdom testi yeşil.
Akışkan ölçeğin TAVANLARI değişmediği için masaüstü boşlukları aynı kaldı.

### M4'ün kararı, ayrıca

**Dokunma hedefi büyür, boşluk daralır.** Bugün ikisi aynı ölçekten besleniyor
ve mobilde ikisi birden büyüyor. Doğru bileşim sahibin tarif ettiği şey:
büyük hedef, sıkı boşluk. Bu bir "mobilde her şeyi küçült" kararı **değil** —
font küçülmez, hedef küçülmez; küçülen tek şey hedefler arasındaki ölü alandır.

## 4. Test yaklaşımı — bugünkü derslerle

Bu programın testleri, aynı gün yaşanan dört kusuru dikkate alarak kurulur:

1. **Düzen testi gerçek motorda.** jsdom sınıf adını ölçer, davranışı
   ölçemez. Her M adımının kabulü `mobile-ux-audit` çıktısında görünür.
2. **Düzenek gerçek bileşenden sapamaz.** `shell-scroll-gate` düzeneği
   sınıfları elle taşıyordu; `AdminShell.contract.test.tsx` onu gerçek DOM'a
   bağladı. Aynı desen bu programda da geçerli.
3. **Araç bulamıyorsa "geçti" denmez.** Chrome yoksa kapı atlamaz, kırılır.
   Ölçüm yapılmadıysa sonuç **"bilinmiyor"** dur.
4. **Sahte bulgu, eksik bulgudan tehlikelidir.** Araç değiştiğinde önce kendi
   sahte kalıpları aranır; bu belgenin §0'ı o denetimin kaydıdır.

## 5. Bu belgenin kendi gerekçe süresi

`docs/109` §8.6: buradaki her eşik bugünün ölçümüdür.

- **44 piksel** bir vekil ölçüdür; gerçek kullanıcı verisi geldiğinde
  yeniden bakılır.
- **%72 kullanılabilir genişlik** bir hedef değil, bugünkü borcu görünür kılan
  bir sınır. Borç kapandıkça yükseltilmeli.
- **320 piksel**, desteklenen en dar cihaz değiştiğinde değişir; o gün taban
  da değişir, kural değişmez.
