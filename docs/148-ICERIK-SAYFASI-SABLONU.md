<!--
    KARAR BELGESİ — FF-241, 2026-09-08.

    Ölçüm önce yapıldı, karar sonra verildi. Belgedeki her sayı gerçekten
    koşturulmuş bir komuttan ya da gerçek bir düzen motorunda okunmuş bir
    kutudan gelir; ölçülemeyenler §8'de AÇIKÇA "bilinmiyor" diye ayrılmıştır.
-->

# İçerik sayfası şablonu — on blok türünün görsel işi

## 1. Bu belge neyi bağlar

`docs/136` kurumsal sitenin **kabuğunu** yasalaştırdı: bileşen kütüphanesi,
tema türetimi, ikon dili, hareket zemini. Bu belge o yasanın altında duran bir
sonraki katmanı bağlar: **kütükten çizilen içerik sayfalarının gövdesini.**

Bugün yazılmış on sekiz sayfa var (`app/Infrastructure/Content/Pages/`) ve
hepsi **tek bir şablondan** çiziliyor: `ShowCorporatePageController` →
`resources/views/content/page.blade.php` → on blok parçası. Sayfa başına
şablon yoktur ve olmayacak — yönergenin §7'si 414 yol için 414 layout
üretmeyi baştan yasaklıyor.

Bunun tersi de doğru ve bu paketin bütün meselesi o:

> **Bir blok türünün görünümüne dokunmak, on sekiz sayfaya birden dokunmaktır.**

**Kapsam dışı:** metnin kendisi. İçerik ölçülerek yazıldı ve bu pakette bir
tek kelimesi değişmedi. Kapsam dışı ayrıca hareket dağarcığı (parallax,
parçacık, derinlik): `docs/136` §7 onun jetonlarını hazırladı, yazılması ayrı
bir paketin işi. Bu paket **yerleşim ve tipografi** yapar.

**Sahibin isteği:** *"MVP değil, maturity level UX estetiği"*, *"bir uzay
teknolojileri şirketi gibi"*. Bu pakette o isteğin karşılığı **efekt değil,
DÜZEN**: on ayrı okuma işinin ekranda birbirinden ayrılması.

---

## 2. Kural: aynı görünen iki blok, aynı işi yapıyor demektir

`BlockType` bugün on tür taşıyor ve **türler artmaz** — yeni bir sayfa yeni
bir tür değil, aynı türlerin farklı içeriğidir. Şablondan önceki hâlinde bu
on türün **yedisi birebir aynı çiziliyordu**: bir `h2`, altında `gap-3` ile
paragraflar ya da satırlar. Yani okuyan kişi, "problem" ile "çözüm" ya da
"yetenekler" ile "sınırlar" arasındaki farkı yalnız METİNDEN çıkarabiliyordu.

Ölçülebilir sonucu şuydu: 320 pikselde 6.400 piksellik bir sayfa, baştan sona
aynı ritimde akıyordu. Uzun bir metni telefonda okunur yapan şey punto değil,
**okuyanın nerede olduğunu bilmesi**dir.

Kanca sınıf adı değil, **blok türünün kendisi**: her blok en dış öğesinde
`data-block="<tür>"` taşır ve CSS oradan tutunur. Bir gün `BlockType` büyürse
kancası olmayan tür `CorporatePageTemplateTest`te görünür — ekranda değil.

---

## 3. On blok türü, on görsel iş

| Sıra | Tür | Okuma işi | Görsel karşılığı |
|---|---|---|---|
| 10 | `direct_answer` | Sayfanın cevabı, ilk ekranda | Marka raylı **giriş cümlesi**; başlıksız, `--text-section`, tam mürekkep |
| 20 | `problem` | Misafirin bugünkü dünyası | Düzyazı, **ikincil** başlık mürekkebi |
| 30 | `solution` | Ürünün cevabı | Aynı düzyazı, **tam** mürekkep + başlık altında marka çizgisi |
| 40 | `how_it_works` | Süreç, adım adım | **Numaralı omurga**: sayaçlı madalyonlar, aralarında ince zincir |
| 50 | `capabilities` | Tarama (okuma değil) | **Akışkan ray ızgarası**; 320'de tek sütun, geniş ekranda çoğalır |
| 70 | `requirements` | İki sütunlu cevap | **Tablo**; dar ekranda etiket değerin üstüne geçer |
| 80 | `limitations` | Dürüstlük imzası | **Çukur panel** + çıkarma çizgileri; alarm rengi yok |
| 120 | `faq` | Herkesin hepsini okumadığı altı soru | **Betiksiz katlanır** `details`; işaret artı/eksi |
| 130 | `cta` | Tek karar | Marka çizgisiyle **kapanış bandı** + `dz-btn` |
| 140 | `related` | Gezinti | **Kenarlıklı hedef kutuları**, 44 piksel |

Aşağıda her birinin gerekçesi.

### 3.1 `direct_answer` — sayfanın cevabı

Kendi başlığı **yok** ve bir `section` de **değil**. İkisi de bilinçli: başlık
koymak ilk ekranı içerikten önce başlıkla doldurmak olurdu
(`TOUCH-FIRST-INTERFACE` madde 3), bir bölüm kabuğuna sarmak ise ekran
okuyucuya başlığı olmayan bir bölüm ilan etmek.

Başlığa **yapışır** (`--space-4`, bölüm aralığı değil): o, H1'in cevabıdır,
sayfanın ilk bölümü değil.

Başlangıç kenarındaki ray **marka rengidir**. `docs/06` §11 sarının asla metin
ön planı olmamasını, **yapısal vurgu** (ray, kenarlık, gösterge) olarak
kullanılmasını şart koşuyor — ve sayfanın en önemli cümlesi tam olarak bunun
doğru yeri. Cümlenin kendisi tam mürekkep kalır.

### 3.2 `problem` → `solution` — sayfanın tek anlatı dönüşü

Bunlar iki bölüm değil, **bir cümlenin iki yarısı**. Eski şablonda ikisi
birebir aynı çiziliyordu ve aralarındaki ilişki ekranda hiç görünmüyordu.

Ayrım **kutuyla değil tonla** kuruldu ve bu bir zevk tercihi değil bir
geometri kararı: 320 pikselde bir kutu iki yanından 32 piksel yer, bir ton
sıfır. Problem sessiz anlatılır (başlığı bile ikincil mürekkep), çözüm tam
mürekkeple gelir ve başlığının altında kısa bir marka çizgisi taşır. Sayfada
"burada bir şey değişiyor" diyen tek işaret budur.

İkisi arasındaki boşluk da dar (`--space-fluid-md`): birbirine yakın duran iki
şey, aynı şeyin parçası olarak okunur.

### 3.3 `how_it_works` — numara işaretlemede yazılmaz

Adımların sırası bilginin kendisidir, bu yüzden gerçek bir `ol`. Numara **CSS
sayacından** gelir: elle yazılmış bir "3.", listeyi yeniden sıralayan kişinin
güncellemeyi unutmasına açıktır ve o gün numara ile sıra birbirini yalanlar.

Madalyonları ince bir dikey çizgi birleştirir — süreç, kopuk beş satır değil
bir zincir. Çizgi mutlak konumlu ve `pointer-events` taşımaz; metnin sütununa
hiç girmez.

`role="list"` bir süs değil: `list-style: none` verilen bir `ol`u Safari
listelikten çıkarır ve "beş öğeli liste" duyurusu kaybolur. Kural tarayıcının
değil belgenin kararı olduğu için işaretlemede yazılı.

Adım başlığı ile açıklaması **ayrı satırlarda**: 320 pikselde ikisini aynı
satıra koymak, kalın başlığın açıklamanın ilk cümlesine yapışması demekti.

### 3.4 `capabilities` — bu bölüm okunmaz, taranır

Sekiz yetenek düzyazı değildir; okuyan kişi aradığını arar. Tek uzun sütun
yerine akışkan bir ızgara: `repeat(auto-fit, minmax(15rem, 1fr))`. 320'de tek
sütun, 1280'de üç sütun. **Kırılma noktası yok** (`MP-05`).

Her satır bir **kutu değil bir ray** taşır. Sebep ölçülebilir: 320 pikselde
sekiz kutu sayfayı bir kart tarlasına çevirir ve her biri iki yanından 32
piksel yer; ray 14 piksel yer ve aynı ayrımı kurar. Bu, sahibin
*"grid gap ve grid margin mobil cihazlarda fazla çalıyor ekrandan"*
şikâyetinin doğrudan karşılığı.

`dl` korunuyor: ad ile açıklama arasındaki ilişki görsel değil anlamsal.

### 3.5 `requirements` — çizim değişti, anlam değişmedi

**Ölçülmüş bir kusur** (2026-09-08, 320×568): tablo iki kolon olarak
çizildiğinde etiket **114**, değer **182** piksele düşüyordu — değer sütunu
satır başına ~19 karakter taşıyordu. Tablo "okunuyordu" ama okunmuyordu.

Satır artık bir ızgara (`repeat(auto-fit, minmax(17rem, 1fr))`): 320'de etiket
değerin **üstüne** geçer ve **ikisi de 296 pikselin tamamını** kullanır; kap
genişledikçe yan yana gelirler. Yatay kaydırma kabı da kalktı, çünkü
kaydıracak bir şey kalmadı.

**Anlam korundu ve bu en kolay kaçırılacak yerdi.** `display` değiştiği an
tarayıcı tablo rollerini düşürür; bunu fark etmek zordur, çünkü ekranda hiçbir
şey olmaz. Roller (`table` / `rowgroup` / `row` / `rowheader` / `cell`) bu
yüzden işaretlemede **açıkça** yazılı. Yazılmasaydı "cevap sistemleri tabloyu
tablo olarak okur" cümlesi sessizce yalan olurdu.

### 3.6 `limitations` — görünsün, ama korkutmasın

Sayfanın en değerli bölümü budur: okuyan kişi eksik olanı zaten sorar; cevabı
sayfada bulamazsa **varsayar**, ve genellikle yanlış varsayar.

İki kolay hata var; ikisinden de kaçınıldı.

**(1) Gizlemek.** Bölüm katlanmaz, sona sürülmez, küçük punto almaz. Bir tık
ardına konan dürüstlük dürüstlük değildir. Testle sabit (CONTENT-TEMPLATE-05):
bu bölümün içinde `details` bulunursa kapı kırılır.

**(2) Alarm çalmak.** Kırmızı bir kutu ziyaretçiye *"burada bir arıza var"*
der; oysa burada yazan şey ürünün **bilinçli kapsamıdır**. Bölüm hata/uyarı
jetonlarına (`--fg-danger`, `--surface-danger`, `--surface-warning` …) **hiç
dokunmaz** ve bu da ölçülüyor (CONTENT-TEMPLATE-06).

Ayrımı **çukur yüzey** (`--surface-subtle`) ve kenarlık kuruyor: sayfadaki tek
"içeri gömülü" alan. Göz onu ayırır ama irkilmez. Her satırın başındaki kısa
çizgi CSS ile çizilir ve bir ikon değil bir **aritmetik işareti**dir: burada
bir şey çıkarılıyor.

Panelin dolgusu akışkan (`--space-fluid-md`): 320 pikselde 12 piksel, yani
içerik hâlâ ekranın **%85**'ini kullanıyor. Sabit 24 piksel yazsaydık dar
ekranda panelin dolgusu sayfanın dolgusuna eklenirdi (`docs/117` K2).

### 3.7 `faq` — betiksiz katlanır, cevabı HTML'de durur

**Ölçüldü (320×568):** altı soruluk SSS bölümü açık hâlde **1.162 piksel**
yer tutuyordu; eylem çağrısına ulaşmak için altı cevabın tamamını kaydırmak
gerekiyordu — ve onları okumayan kişi için o kaydırma tamamen boşa. Katlanmış
hâli **406 piksel**: sayfadan **756 piksel** kaydırma düştü.

`details`/`summary` tarayıcının **kendi** açılır kapanır düğmesidir: klavyeyle
çalışır, durumunu ekran okuyucuya söyler, betik olmadan açılır (`docs/118` E8)
ve kabuk zaten aynı ilkeyi kullanıyor (menü, altbilgi pSEO bandı). İkinci bir
düzenek icat etmek, aynı işi iki farklı yerde farklı yapmak olurdu.

**Katlanan şey yalnız görünüm** ve bunun üç ayrı sonucu var:

- **Cevap ilk HTML yanıtında zaten var.** Betik çalıştırmayan bir bot onu
  okuyor ve `FAQPage` işaretlemesi görünmeyen bir bilgi ilan etmiyor (§14).
  Bu, eski şablonun "katlanmış cevap üretilmez" gerekçesinin karşılığıdır:
  gerekçe cevabın **sunucudan gelmesini** koruyordu, ekranda açık durmasını
  değil.
- **Soru gizlenmiyor.** Hâlâ görünür bir `h3` ve `summary`nin **içinde**
  duruyor — HTML `summary` için başlık içeriğini açıkça izin verilen tek
  istisna olarak tanımlar. Ekran okuyucu kullanıcısının başlıkla gezinmesi
  bozulmuyor.
- **`scripts/mobile-ux-audit` yanılmıyor.** Araç ölçümden önce sayfadaki her
  `details`i açar; yani katlama, bir kusuru ölçümden saklamak için
  kullanılamaz.

**İşaret artı'dır, ok değil.** İki sebep: artı "burada daha fazlası var"
demenin en kısa yoludur, ve bir ok sağdan sola yazılan bir dilde aynalanmak
zorundadır — artının yönü yoktur. Çizim tek bir sözde-öğede iki gradyan
katmanıyla yapılır; **gövdeye SVG girmez** (`docs/136` §4, ICON-04).

### 3.8 `cta` — kapanış noktalaması

Tek birincil eylem; ikinci bir eşit ağırlıklı düğme kararı böler. Üstündeki
marka çizgisi bir süs değil bir **noktalama**: sayfanın metni burada bitiyor,
geriye yalnız bir seçim kalıyor. Giriş cümlesindeki dikey rayla aynı renk ve
aynı kalınlık — **açan ve kapatan işaret aynı**.

Düğme daisyUI'nin `dz-btn`i (`docs/136` E9), geometrisi buranın: daisyUI gövde
boyutunu 14 piksele sabitliyor ve yoğunluk fontu küçülterek sağlanmaz
(`docs/118` E3).

### 3.9 `related` — gezinti, cümle değil

Bunlar cümle içindeki bağlantı değil, gezinti hedefi: her biri kendi
satırında, tam dokunma yüksekliğinde, kenarlıklı. Burada okunan bir metin yok;
dokunulacak bir hedef var ve hedefin sınırı görünür olmalı. Geniş ekranda
ızgara kendiliğinden çoğalır.

Süzgeç şablonda değil denetleyicide: yayınlanmamış sayfa hiçbir yerden iç
bağlantı almaz (`docs/105` §2.2(3)). Süzgeçten hiçbir şey geçmediyse bölüm
hiç çizilmez.

---

## 4. Kırıntı — iki kural aynı anda

İki kural birden tutulmak zorundaydı:

1. Bir bağlantı 44 pikselden kısa olamaz (`docs/117` K1). **Ölçüm:** kırıntı
   bağlantıları **112×20** ve **124×20** idi — yarısından az, ve bu, on sekiz
   sayfanın **on beşindeki** tek dokunma kusuruydu.
2. Kırıntı ilk ekranı içerikten önce dolduramaz
   (`TOUCH-FIRST-INTERFACE` madde 3).

İkisini birden tutmanın tek yolu **sarmamaktı**. Sarsaydı en derin sayfada
(üç basamak, 379 piksel) kırıntı 320'de iki satıra çıkardı — sadece 44
pikselden 72'ye, yani başlığın yarısı kadar yer. Tek satır kalıyor (44 px),
sığmazsa **kendi içinde** kayıyor; sayfa gövdesi yatay kaymıyor.

Kayan şey en fazla **son** basamaktır ve o basamak zaten bağlantı değil:
bulunduğun sayfanın adı, bir satır aşağıda H1 olarak tekrar yazıyor.

Yayınlanmamış ata ve bulunduğun sayfa 44 piksele **büyütülmedi**: tıklanamayan
bir şeye dokunma hedefi vermek, tıklanabilir görüntüsü vermek olurdu.

---

## 5. Tipografi ölçeği — yeni bir rol YARATILMADI

`docs/136` §5'in kararı aynen geçerli: tipografi, boşluk ve dokunma ölçüleri
`app.css` `@theme`ten ve AEP jetonlarından gelir; bu pakette hiçbiri yeniden
tanımlanmadı.

| Katman | Rol | 320 px | 1280 px |
|---|---|---|---|
| H1 | `--text-title` | 24 px | 32 px |
| Giriş cümlesi | `--text-section`, 400 | 18 px | 22 px |
| Bölüm başlığı (H2) | `--text-section`, 600 | 18 px | 22 px |
| Gövde | `--text-body` | 16 px | 16 px |

**Bilinçli bir ödün var ve gizlenmiyor:** eski şablon H1'i `text-3xl` (30 px)
ile çiziyordu; `--text-title` 320 pikselde 24 pikseldir, yani başlık
**küçüldü**. Alternatifi yeni bir tipografi rolü yazmaktı ve o, depoda ikinci
bir tipografi kaynağı açardı — `docs/136` §5'in tam olarak kapattığı kapı.
Başlığın ağırlığı boyutla değil **ilişkiyle** kuruldu: kırıntıya yapışması,
`700` ağırlık, sıkı harf aralığı ve hemen altındaki raylı giriş cümlesi.

Giriş cümlesi ile bölüm başlığı **aynı boyuttadır**; ayrımı ağırlık (400 ↔
600), mürekkep ve ray taşır. İkisini boyutla ayırmak yeni bir rol gerektirirdi.

Uzun metnin satır aralığı için tek bir yerel jeton tanımlandı
(`--doc-leading: 1.65`), gerekçesi dosyada yazılı: rol jetonlarının 1.5'i
arayüz içindir; telefonda okunan beş cümlelik bir paragraf daha fazlasını
ister.

**İki ölçü, tek kenar.** Sayfa kolonu 56 rem (kabuğun 64 rem'i altbilgi
ızgarası için doğrudur, düzyazı için değil: 1024 pikselde bir paragraf satır
başına ~140 karakter taşır). Düzyazı blokları 66 karakterde durur. İkisi de
**aynı kenardan** başlar; 320 pikselde ikisi de %100'dür.

---

## 6. Ölçüm — 320 ve 1280, gerçek Chrome'da

Ölçüm için on sekiz sayfa **yerelde geçici olarak** yayına alındı (ayrı bir
sqlite, depoda durum değişmedi), `php artisan site:export-static` ile çizildi
ve `scripts/mobile-ux-audit` ile ölçüldü. Aynı ölçüm bu paketten önce de
alındı; tek bir sayı tek başına bir şey söylemez.

| Genişlik | Önce | Sonra |
|---|---|---|
| **320×568** | 18/36 sayfa etkilenmiş · 25 bulgu | **3/36** · **5 bulgu** |
| **1280×800** | 18/36 · 26 `small-target` + 2 `tight-gap` | **3/36** · **5 bulgu** |
| **320×568, RTL** | 18/36 · 25 bulgu | **3/36** · **5 bulgu** |

**İçerik sayfalarında:** on sekiz sayfanın **on beşi** etkilenmişti, toplam
**20 bulgu** vardı; şimdi **sıfır** — üç genişlikte de. Kalan beş bulgunun
hepsi ana sayfa, `/help` ve `/pricing` gövdelerinde ve **hepsi ölçümden önce
de vardı**; onlar başka paketlerin dosyaları ve WCAG 2.2'nin 2.5.8 ölçütü
metin akışındaki hedefleri 44 pikselden muaf tutuyor.

**Yeni ihlal: 0.** Ölçüm dosya dosya karşılaştırıldı; bu pakette eklenen tek
bir bulgu yok.

Tek açıklanamayan fark, `1280` ölçümünde `/help` sayfasının kabuk menüsündeki
üç bulgunun (`998×44` + iki `tight-gap`) kaybolması. O sayfaya bu pakette
dokunulmadı; 44 pikselin **alt piksel sınırındaki** bir yuvarlama farkı en
olası açıklama (`Math.round` 43,99'u da 44 yazar). Ölçüm tekrarlandı ve ikinci
koşuda da yok. Bir kazanç olarak sahiplenilmiyor.

### 6.1 Ölçülen tek tek kutular (320×568)

| Ne | Önce | Sonra |
|---|---|---|
| Kırıntı bağlantısı | 112×20 / 124×20 | **≥44 px yükseklik** |
| Kırıntı şeridinin yüksekliği (3 basamak) | — | **44 px** (sararsa 72 olurdu) |
| Gereksinim satırı, etiket / değer | 114 / 182 px | **296 / 296 px** |
| SSS bölümü | 1.162 px | **406 px** (−756 px kaydırma) |
| İçerik sütunu / görüntü alanı | 309 / 320 | **309 / 320** (değişmedi) |

### 6.2 CSS ağırlığı

`php artisan view:clear && rm -rf public/build && npm run build`, iki kez;
tek fark `site-content.css`in içeriği.

| Aşama | Ham | gzip |
|---|---|---|
| `site-content.css` boşken | 160.822 B | 27.963 B |
| **Bu paketin teslimi** | **168.559 B** | **29.166 B** |

**Şablonun maliyeti: +7.737 ham bayt, +1.203 gzip bayt** — sıkıştırılmış
CSS'te **%4,3** artış. Karşılığında on sekiz sayfanın gövdesinden yardımcı
sınıf yığını kalktı ve düzen tek bir yerden yönetiliyor.

React paketi değişmedi: kurumsal sayfalar hâlâ **sıfır React** yüklüyor.

---

## 7. Kapılar

`tests/Feature/Content/CorporatePageTemplateTest.php`:

| Kimlik | Ne ölçüyor |
|---|---|
| CONTENT-TEMPLATE-01 | Zorunlu her blok türü kendi `data-block` kimliğiyle **bir kez** çiziliyor |
| CONTENT-TEMPLATE-02 | Doğrudan cevap H1'in hemen ardında, `section` değil, paragraf |
| CONTENT-TEMPLATE-03 | Adımlar `role="list"` taşıyan bir `ol`; numara işaretlemede yok, sayaç CSS'te |
| CONTENT-TEMPLATE-04 | Gereksinimler hâlâ tablo: `table`/`rowgroup`/`row`/`rowheader`/`cell` rolleri yerinde |
| CONTENT-TEMPLATE-05 | Sınırlar katlanmıyor, gizlenmiyor |
| CONTENT-TEMPLATE-06 | Sınırlar alarm paletini ödünç almıyor |
| CONTENT-TEMPLATE-07 | SSS betiksiz katlanıyor, cevabı sunucudan geliyor, sorusu hâlâ `h3` |
| CONTENT-TEMPLATE-08 | Kırıntı tek satır (`nowrap` + kendi içinde kayma) ve bağlantısı `--control-height` |
| CONTENT-TEMPLATE-09 | Gövde kendi ikonunu çizmiyor; emoji yok |
| CONTENT-TEMPLATE-10 | Stil dosyası `app.css`ten çağrılıyor; ham renk, kırılma noktası, `!important`, ham süre/yumuşatma yok; hareket yalnız `no-preference` altında |

Devralınan kapılar aynen geçerli ve kırılmadı: `CorporateProductPageTest`
(içerik, sıra, şema, tek H1), `ServerRenderedStringsAreTranslatableTest`
(şablonda tek bir sabit kullanıcı metni yok), `LogicalDirectionScanTest` +
`scripts/logical-direction-gate` (fiziksel yön yok), `SiteShellSingleSourceTest`
(gövde kendi kabuğunu kurmuyor).

---

## 8. Bu paketin ölçemedikleri — açıkça

1. **iOS Safari doğrulanmadı.** Ölçüm Chrome'da yapılıyor. `details` tabanlı
   SSS'in, `role` ile korunan tablo anlamının ve `::marker` gizlemenin
   iOS'taki davranışı belgelenmiş davranışa dayanıyor, gerçek cihazda
   ölçülmedi.
2. **Ekran okuyucu ile gerçek bir gezinti yapılmadı.** `role="list"` ve tablo
   rolleri belgelenmiş davranış gereği yazıldı; VoiceOver/NVDA ile
   doğrulanmadı.
3. **Estetik ölçülmedi, geometri ölçüldü.** `scripts/mobile-ux-audit` taşma,
   kırpılma, dokunma hedefi ve yoğunluk ölçer; hiyerarşinin güzelliği insan
   kararıdır ve bu araç onu ölçtüğünü iddia etmiyor.
4. **Kontrast oranları bu pakette ölçülmedi.** Renk jeton katmanının işidir ve
   orada ölçülür; burada ölçülen, şablonun o katmandan **beslendiği** — tek
   bir ham renk yazılmadı.
5. **Türkçe metinle ölçülmedi**, çünkü Türkçe içerik yuvası bilerek boş
   (`docs/118` E4). Uzayan metnin düzeni nasıl etkileyeceği, o karar
   verildiğinde ölçülecek. Yön ölçüldü (`--direction rtl`), **dil ölçülmedi**.
6. **Üretimde bugün hiçbir içerik sayfası yayında değil.** Buradaki bütün
   ölçümler, on sekiz sayfanın yerelde geçici olarak yayına alındığı bir
   kopyadan geliyor.

---

## 9. Sonraki içerik yazarlarına talimat

**Yeni bir sayfa yazarken hiçbir Blade dosyasına dokunmuyorsun.** Bir sayfa,
`app/Infrastructure/Content/Pages/` altında bir sınıftır; blokları
`BlockType`ten seçersin, şablon onları bu belgede yazan görsel işlerle
çizer.

**Serbest:**

- On türün her birini kullanmak. Zorunlu olanlar
  `BlockType::requiredForProductPage()`te yazılı.
- `related` bloğunu yazmak: bağlantılar yayınlanmış sayfalara **süzülür**,
  yayınlanmamış olan satır bile almaz.
- `capabilities` ve `requirements` satırlarına `source` yazmak — ekranda
  görünmez, iddianın deponun neresinde kanıtlandığını söyler.

**Yasak:**

- Blok türü **eklemek**. Yeni bir sayfa yeni bir tür değil; aynı türlerin
  farklı içeriğidir. Gerçekten yeni bir okuma işi doğduysa önce bu belge
  güncellenir, sonra `BlockType`, sonra şablon ve kapı.
- Aynı bloğu iki kez yazmak, sırayı bozmak, boş blok bırakmak — `PageContent`
  üçünü de kırar.
- Ürünün bugün yapmadığı bir şeyi "yakında" diye yazmak (yönerge §1 madde 18).
  `limitations` bölümü bunun için var ve gizlenmiyor.
- Metnin içine biçim sokmak: kalın, madde işareti, satır sonu. Görünüm blok
  türünün kararıdır, cümlenin değil.

**Şablonun görünümüne dokunacak ajana:**

- Ölçüm **en dar genişlikte** yapılır ve gerçek bir düzen motorunda: 18 sayfa
  yerelde yayına alınır, `site:export-static` ile çizilir,
  `scripts/mobile-ux-audit` ile 320 ve 1280'de okunur. Yeni ihlal bırakılmaz.
- Kırılma noktası (`sm:` …) yasak — `MP-05` kırar. Çok sütun `auto-fit` ile
  gelir.
- Ham renk, ham süre, `!important`, emoji, kendi SVG'si yasak
  (CONTENT-TEMPLATE-09, -10).
- Gövde kendi kabuğunu kurmaz: `header`/`footer` `public.layout`un işidir.
- Hareket eklenecekse `docs/136` §7'nin jetonları ve `.site-stage` kabı
  kullanılır; kural `prefers-reduced-motion: no-preference` üzerinden yazılır,
  `reduce` üzerinden değil.

---

## 10. Rapor alanları

- **once:** On sekiz kurumsal sayfa tek bir şablondan çiziliyordu ve o şablon
  on blok türünün **yedisini birebir aynı** çiziyordu: bir başlık, altında
  paragraflar. 320 pikselde 6.400 piksellik bir sayfa baştan sona aynı
  ritimde akıyordu; okuyan kişi "problem" ile "çözüm"ü ya da "yetenekler" ile
  "sınırlar"ı yalnız metinden ayırabiliyordu. Gereksinim tablosunun değer
  sütunu 182 piksele düşüyordu, SSS 1.162 piksellik bir duvar gibi duruyordu
  ve kırıntı bağlantıları 112×20 idi — on sekiz sayfanın on beşinde ölçülen
  tek dokunma kusuru.
- **simdi:** Her blok türü kendi kimliğiyle çiziliyor ve kendi görsel işini
  yapıyor: cevap raylı bir giriş cümlesi, süreç numaralı bir omurga,
  yetenekler akışkan bir ray ızgarası, gereksinimler dar ekranda alt alta
  inen ama **hâlâ tablo olan** bir tablo, sınırlar gizlenmeyen ama alarm
  çalmayan bir panel, SSS betiksiz katlanan bir açılır kapanır. On kapı bunu
  sabitliyor.
- **fark:** 320'de içerik sayfalarındaki bulgu **20 → 0**, 1280'de **20 → 0**,
  RTL'de **20 → 0**; yeni ihlal yok. SSS'ten 756 piksel kaydırma düştü,
  gereksinim değeri 182 → 296 piksele çıktı, kırıntı 20 → 44 piksel oldu ve
  sarmadı. Sıkıştırılmış CSS 27.963 → 29.166 bayt (+%4,3). İçeriğin **tek
  kelimesi** değişmedi.
- **kullaniciYolculugu:** Bir kebapçı telefonunda "QR menü" sayfasını açıyor.
  Kırıntı tek satır, hemen altında başlık, onun altında marka raylı tek
  paragraf: sorusunun cevabı ilk ekranda. Kaydırıyor — problemin sessiz tonu,
  çözümün altındaki sarı çizgi, numaralı beş adım. "Neye ihtiyacım var"
  tablosunda etiket ve değer alt alta, ikisi de tam genişlikte. Sonra çukur
  panel: **ürünün yapmadıkları.** Porsiyon yok, kalori yok, misafir buradan
  ödeme yapmıyor — okuyor, irkilmiyor, karar veriyor. Altı soru katlı duruyor;
  yalnız merak ettiğine dokunuyor, ötekiler yolunu kapatmıyor. Sayfanın
  sonundaki sarı çizginin altında tek bir düğme var. Daha önce bu yolculuğun
  hiçbir durağı ötekinden ayırt edilemiyordu.
- **kalanEngel:** Kütükte yayınlanmış tek bir içerik sayfası yok — bu bir
  kusur değil, bekleyen bir **insan kararı** (`docs/128`). Hareket dağarcığı
  ve renk paleti ayrı paketlerin işi; bu şablon ikisine de hazır ama ikisini
  de içermiyor. iOS Safari ve ekran okuyucu doğrulaması yapılmadı (§8).
- **capability_delta:** Kurumsal içerik sayfalarının **bir tasarımı** var ve o
  tasarım tek bir yerde yaşıyor: bir blok türünün görsel işini değiştirmek on
  sekiz sayfayı birden değiştiriyor, ve bu artık bir yan etki değil, belgesi
  ve kapısı olan bir sözleşme.
- **Çalışabilen:** On sekiz sayfanın tamamı 320 pikselde ve 1280 pikselde
  ölçülmüş bir düzenle, sağdan sola dâhil, betik çalışmadan da gezilir
  hâlde çiziliyor; her dokunma hedefi 44 piksel; yatay kayma yok; SSS
  klavyeyle açılıp kapanıyor; yapısal veri (`SoftwareApplication`,
  `BreadcrumbList`, `FAQPage`) bozulmadan üretiliyor.
- **Çalışamayan:** Bu sayfaların hiçbiri bugün üretimde yayında değil, çünkü
  yayın kararı verilmedi. Sahibin istediği "uzay teknolojileri şirketi" hissi
  bu pakette **hareketle** verilmedi — yalnız düzen ve tipografiyle; efekt
  dağarcığı hâlâ yazılmadı.
