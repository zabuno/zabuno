<!--
    KARAR BELGESİ — FF-254, 2026-09-08. WCAG 2.2 AA üç döngülük programın
    BİRİNCİ döngüsü.

    Ölçüm önce yapıldı, karar sonra verildi. Belgedeki her sayı gerçekten
    koşturulmuş bir komuttan geliyor; ölçülemeyenler §8'de AÇIKÇA
    "bilinmiyor" diye ayrılmıştır. "Muhtemelen uyumlu" bu belgede geçmez.
-->

# WCAG 2.2 AA — Döngü 1: ölçen bir kapı, ve okunmayan tek metin

## 1. Sahibin cümlesi iki iddia taşıyordu

> *"wcag 2.2 aa, standartları yok yazılar okunmuyor, 3 kere tam tur wcag
> döngüsü ile 'wcag 2.2 aa standartları'nı test et ve düzelt"*

İkisi de ciddiye alındı ve ikisi de ayrı ayrı cevaplandı:

| İddia | Bulunan |
|---|---|
| **"standartları yok"** | Doğru. WCAG 2.2 AA'ya karşı ölçen bir kapı YOKTU. Depoda erişilebilirlik testi vardı (`resources/js/design-system/a11y.guard.test.tsx`) ama jsdom'da koşuyor; jsdom düzen hesaplamaz, renk boyamaz, hiçbir metni bir sahnenin üstüne düşürmez. Yani "kontrast yeterli mi" sorusu bu depoda hiç SORULAMIYORDU. |
| **"yazılar okunmuyor"** | Doğru — ve tek bir yerde. Ölçüldü: ana sayfanın en uzak akan şeridi **2.32:1** (gerek 4.5:1) koyu kipte, **2.05:1** açık kipte. Sözcükler ekranda duruyordu, okunmuyordu. |

İkinci iddianın birinci iddiadan doğduğu da ölçüldü: kusur bir yıldır oradaydı
ve hiçbir kapı onu görmüyordu.

---

## 2. Ne yapıldı — üç şey

1. **`scripts/wcag-gate`** — gerçek Chrome'da, gerçek düzende, gerçek piksel
   üzerinden ölçen bir kapı. On sayfa, dört genişlik, üç kullanıcı hâli.
2. **`scripts/wcag-gate.contrast.mjs` + `scripts/wcag-gate.test.sh`** —
   kapının aritmetiği ve onun kendi deneği. Bir ölçüm aracının en tehlikeli
   hâli sessizce hiçbir şey bulmayan hâlidir; sentetik denekler o sessizliği
   bozar.
3. **Tek bir düzeltme** (`resources/css/site-scene.css`): en uzak akan
   şeridin opaklığı `0.42` → `0.8`.

**Kapsam dışı (bilerek):** `/accessibility` sayfası — o, "güven merkezi +
erişilebilirlik beyanı" paketinin işi. Bu paket o dosyaya dokunmadı; ama bu
belgedeki sayılar, o sayfanın iddialarının doğrulanabileceği tek kaynaktır.

---

## 3. Ne ölçüldü — sayılarla

### 3.1 Kapsam

| | |
|---|---|
| Sayfa | 10 — `/`, `/pricing`, `/about`, `/contact`, `/help`, `/terms` (yasal), `/en/product/qr-menu` (kütükten içerik), `/login`, `/register`, `/app` (oturumlu panel) |
| Genişlik | 4 — 320×480, 320×480 sağdan sola, 1280×800, 1920×1080 |
| Kullanıcı hâli | 3 — varsayılan, `prefers-contrast: more`, `forced-colors: active` |
| Toplam görünüm | **120** (40 görünüm × 3 hâl) |
| Ölçülen metin kutusu | **1772 / 1800** (%98) |
| Ölçülen kontrol sınırı | **36 / 36** |

Panel (`/app`) ilk kez ölçüldü. Kapı bunun için PAROLA taşımıyor: oturum
çerezi dışarıda üretilip `WCAG_SESSION_COOKIE` ile veriliyor (yöntem betiğin
başlığında yazılı). Çerez verilmezse panel "ölçülmedi" diye raporlanır.

### 3.2 Ölçütler ve bulgular

| Ölçüt | Nasıl ölçüldü | Önce | Sonra |
|---|---|---|---|
| 1.4.3 Metin kontrastı | axe-core + piksel ölçümü | **4** | **0** |
| 1.4.11 Metin dışı kontrast | kontrolün kenarındaki en güçlü geçiş | 0 | 0 |
| 1.4.4 Metin %200 | her puntonun iki katı, gerçek düzende | 0 | 0 |
| 1.4.10 Reflow (320) | ikinci eksende kaydırma | 0 | 0 |
| 1.4.12 Metin aralığı | ölçütün kendi CSS'i | 0 | 0 |
| 2.4.11 Odak gizlenmesi | sekmeyle gezip yapışkan katman altında kalma | 0 | 0 |
| 2.5.8 Hedef boyutu 24×24 | ölçütün kendi istisnalarıyla | 0 | 0 |
| 3.2.6 Tutarlı yardım | yardım bağlantısının göreli sırası | 0 | 0 |
| 3.3.8 Erişilebilir kimlik doğrulama | yapıştırma engeli, bilmece, CAPTCHA | 0 | 0 |
| 3.3.7 Gereksiz tekrar giriş (aday) | aynı sayfada yinelenen `autocomplete` | 0 | 0 |
| 3.1.2 Parçaların dili (aday) | ilan edilen dile yabancı yazı sezgisi | 0 | 0 |
| axe: diğer bütün AA kuralları | `wcag2a, wcag2aa, wcag21a, wcag21aa, wcag22aa` | 0 | 0 |

**Toplam: 4 bulgu (20 kusurlu öğe) → 0.**

### 3.3 En kötü kontrast oranı

| | Önce | Sonra |
|---|---|---|
| Ekranda okunması BEKLENEN metin (akan şerit dâhil) | **2.32:1** | **5.57:1** |
| Piksel ölçümünün en kötüsü (`a.site-action.site-cta «Create an account»`, home-1280) | 5.25:1 | 5.25:1 |

İkinci satır dokunulmadan kaldı ve bu bilerek: piksel ölçümü `aria-hidden`
alt ağaçları atlıyor (dekoratif bir katman "okunacak metin" değildir), yani
akan bandı hiç görmüyor. Bandı axe gördü. İki ölçümün AYNI şeye bakmaması bir
çelişki değil, kapsamın kendisi: biri ekranda boyanan pikseli, öteki
erişilebilirlik ağacındaki metni ölçüyor ve kusur ikincisinde yaşıyordu.

### 3.4 Hangi sayfa en çok bulgu verdi

Bulguların **tamamı ana sayfadaydı** (`/`) ve tamamı aynı bileşendendi: akan
bandın en uzak şeridi. Genişlik büyüdükçe şeritten daha çok madde görünür
alana giriyor, o yüzden bulgu sayısı genişlikle artıyordu:

| Görünüm | Kusurlu öğe |
|---|---|
| home-320 | 2 |
| home-320-rtl | 2 |
| home-1280 | 6 |
| home-1920 | 10 |

Dört görünüm, toplam 20 kusurlu öğe. Bulgular `prefers-contrast: more`
hâlinde çıktı; varsayılan hâlde axe aynı öğeleri "belirsiz" bırakıyor (§4),
`forced-colors: active` hâlinde ise axe'ın kontrast kuralı bilerek kapalı
(§6.4).

---

## 4. Neden bulgu YALNIZ tek bir hâlde göründü

Kusur her hâlde vardı; **kanıtlanabilir** olduğu yer bir taneydi. Bu, üç hâli
birden ölçmenin gerekçesidir: iki hâli ölçmeyen bir kapı, bu kusuru hiç
bulamazdı.

**Varsayılan hâlde** şeridin arkasında bir tuval, bir degrade ve bir ışıma
var. axe orada arka planın ne olduğunu BİLEMEZ ve kuralı "belirsiz" diye
bırakır — varsayılan koşuda 155 öğe bu şekilde belirsiz kalıyor. Belirsiz bir
sonuç bir bulgu değildir, ama bir temizlik de değildir.

**`prefers-contrast: more` hâlinde** deponun kendi CSS'i gradyanları ve camı
kapatıyor (`site-identity.css` §7); arka plan tek bir düz renge iniyor ve axe
hesabı yapabiliyor. Kusur burada, sayıyla ortaya çıktı.

**`forced-colors: active` hâlinde** axe'ın kontrast kuralı bilerek kapalı:
axe tarayıcının sistem paletini devralmasını göremiyor ve boyanmamış renkleri
ölçüyor (§6.3). O hâlde metin kontrastı kapının kendi piksel ölçümüyle
biliniyor ve orada bulgu yok.

Yani yüksek kontrast isteyen ziyaretçinin hâli, kusuru YARATMADI —
**görünür kıldı.**

---

## 5. Düzeltme: bir opaklık, iki kipte ölçülmüş

`resources/css/site-scene.css` §16:

```
.scene-drift[data-depth-lane='far'] { opacity: 0.42 → 0.8 }
```

| Opaklık | Koyu kip bileşik renk | Oran | Açık kip bileşik renk | Oran |
|---|---|---|---|---|
| 0.42 (önce) | `#4d4a66` | **2.31:1** | `#aca9bd` | **2.05:1** |
| 0.75 | `#817d9d` | 5.01:1 | `#74708d` | **4.22:1** ✗ |
| **0.8 (sonra)** | `#8985a5` | **5.57:1** | `#6c6785` | **4.78:1** |

`0.75` koyu kipte geçiyor ama AÇIK kipte geçmiyor; iki kipte birden geçen en
küçük yuvarlak değer `0.8`.

(axe koyu kip için `2.32:1` bildirdi; aradaki iki yüzdelik fark, axe'ın
bileşik rengi önce 8 bitlik `#4d4a66`ya yuvarlamasından geliyor. Aynı ölçüm,
aynı sonuç.)

### Uzay estetiği ölmedi

Sahibin kuralı yerinde duruyor: *"bir uzay teknolojileri şirketi gibi, abartı
dursun, görünsün, hissettirsin."* Koyu tema, sahne, yıldız alanı, üç şeritli
bant — hepsi olduğu gibi.

Değişen tek şey, şeridin "uzak" olduğunu anlatan İŞARETTİ. O işaret iki
katmanlıydı: **hız** (74 saniye, ötekilerin yarısı) ve **sönüklük**. Hız
işareti dokunulmadan duruyor; sönüklük ise okunmayan bir metin pahasına
taşınan İKİNCİ ve gereksiz bir işaretti. Atmosferik perspektif de zaten
böyle çalışır: uzaktaki şey daha yavaş geçer.

**Ölçülen görsel etki:** aynı ekran görüntülerinin kaba imzası
(`scene-visual-gate`, 16×12 hücrede ortalama parlaklık) üç görünümde
**en fazla 3/255** kaydı; kalan 21 görünümde HİÇ değişmedi. Yani sahne aynı
sahne.

---

## 6. Ölçümün KENDİ dört kusuru — ve neden burada yazılıyorlar

Bu kapı ilk yazıldığında dört kez YANLIŞ ölçtü ve dördü de "çalışıyor" gibi
görünüyordu. Dördü de belgeye giriyor, çünkü bir sonraki döngü aynı tuzaklara
düşmesin:

1. **Metni gizlediğini sanan levha metni gizlemiyordu.**
   Kontrastı ölçmek için bütün metin görünmez yapılıp bir "arka plan levhası"
   çekiliyor. İlk sürüm bunu `* { color: transparent !important }` ile
   deniyordu. Çalışmadı: depo kaskad KATMANLARI (`@layer`) kullanıyor ve
   katmanlı `!important` bildirimlerinde sıra TERSİNE döner — katmansız bir
   `!important` en altta kalır. Metin boyanmaya devam etti ve kapı metnin
   kendi rengini "arka plan" sanıp her paragrafta **1:1** bildirdi.
   *Çözüm:* metin, düzeni değiştirmeyen satır içi bir sarmalayıcıya konup
   `visibility: hidden` ile gizleniyor.

2. **%200 punto ölçümü puntoyu KATLIYORDU.**
   Tek geçişte okuyup yazan bir döngü, çocuğun puntosunu ebeveyn zaten
   değiştikten sonra okuyor ve ikiye katlanmış değeri bir kez daha
   katlıyordu. Altbilgide kök 32 piksel, çocuğu 64, torunu 128 oldu ve kapı
   *"%200'de 12.684 piksel taşıyor"* diye 18 bulgu yazdı. Hiçbiri gerçek
   değildi. *Çözüm:* önce hepsi okunuyor, sonra hepsi yazılıyor.

3. **axe zorlanmış renklerde BOYANMAMIŞ bir rengi ölçüyordu.**
   `forced-colors: active` hâlinde giriş formunun düğmesi için axe
   *"#080616 üstüne #000000, 1.04:1"* dedi — sekiz görünümde sekiz bulgu.
   Aynı düğme `getComputedStyle` ile ölçüldü: **beyaz üstüne siyah, 21:1**.
   Yani axe, tarayıcının sistem paletini devralmasını göremiyor ve yazarın
   artık ekranda olmayan rengini ölçüyordu. axe'ın kendi tasarımı bu kuralı
   gerçek yüksek kontrast kipinde zaten atlar; atlayamamasının sebebi kipi
   bir hile ile yoklaması ve CDP taklidinde o yoklamanın tutmamasıdır.
   *Çözüm:* `forced` hâlinde `color-contrast` kuralı kapatıldı ve bu hâlde
   metin kontrastı kapının KENDİ piksel ölçümüne bırakıldı — o, boyanan
   pikseli okuduğu için zorlanmış renklerde de doğrudur.

4. **Oturum çerezi ölçülen sayfayı sessizce değiştiriyordu.**
   Panel için verilen çerez yüzünden `/login` ve `/register` adresleri `/`
   adresine yönlendi ve kapı ANA SAYFAYI iki kez daha, "login" ve "register"
   adlarıyla ölçtü. Aynı bulgu üç sayfadan geliyormuş gibi göründü; oysa tek
   bir sayfaydı. *Çözüm:* her sayfanın kendi şartı var (`guest` / `auth`),
   çerez ona göre kuruluyor ya da siliniyor ve İNİLEN adres her koşuda
   doğrulanıyor.

Dördünün ortak dersi: **temiz bir rapor, çalışan bir ölçüm demek değildir.**
`scripts/wcag-gate.test.sh` tam bunun için var — sekiz sentetik denek,
kontrastı önceden bilinen görüntülerle kapının aritmetiğini sınıyor.

---

## 7. Döngü 2'ye devredilenler

Hiçbiri bugün bir WCAG AA ihlali DEĞİL; hepsi ölçülmüş ve adı konmuş açık uç.

1. **Kapı CI'da koşmuyor.** `wcag-gate` bir PHP sunucusu ister ve bu depoda
   sahne kapılarının hiçbiri (`scene-visual-gate`, `scene-perf-gate`) CI'ya
   bağlı değil. Elle koşulan bir kapı, koşulmadığı gün yoktur. Taban dosyası
   (`scripts/wcag-gate.baseline.json`) hazır; eksik olan CI adımı ve orada bir
   sunucu ayağa kaldırmak.
2. **Akan bandın uçları maskeyle sıfıra soluyor.**
   `mask-image: linear-gradient(…)` şeridin dış %12'sini saydamlaştırıyor.
   Oradaki sözcükler hiçbir orana ulaşmaz. Bant `aria-hidden` ve içeriği
   sayfanın başka bir yerinde okunabilir hâlde duruyor, yani ölçüt açısından
   savunulabilir; ama görsel olarak sayfanın en zayıf metni orası.
   **Karar sahibinin:** bant sözcük taşımaya devam mı etsin, yoksa gerçekten
   dekoratif mi olsun.
3. **2.4.7 Odak görünürlüğü ölçülmüyor.** Kapı odağın bir katmanın ALTINDA
   kalıp kalmadığına bakıyor (2.4.11) ama odaklanan öğede gözle görülür bir
   değişiklik OLDUĞUNU ölçmüyor. Bu ayrı bir ölçüm ve Döngü 2'nin işi.
4. **`prefers-contrast: more` metin rengini YÜKSELTMİYOR.** Bugün yalnız
   gradyan, cam ve ışıma kapanıyor; ikincil metin aynı renkte kalıyor
   (8.25:1 — ölçütü geçiyor, ama "daha fazla kontrast" isteyen kişiye
   verilen bir şey değil). Ölçüldü ve bir ihlal DEĞİL; bir eksik.
5. **Sahne boyanmış hâlde kontrast tabana bağlı değil.** `--motion` ile
   ölçülüyor (ana sayfada bulgu vermedi) ama hareketli bir sahnede sayı
   koşudan koşuya oynadığı için kapıya bağlanmadı.

---

## 8. ÖLÇÜLEMEYENLER — "bilinmiyor", "sorun yok" DEĞİL

Aşağıdakiler bu döngüde ölçülmedi. Hiçbiri "geçti" sayılmaz.

1. **2.5.7 Sürükleme hareketleri.** Kapı yalnız sürükleme YÜZEYLERİNİ
   buluyor (`draggable`, `[role=slider]`, `input[type=range]`); bir
   sürüklemenin sürüklemesiz alternatifi olup olmadığı bir ANLAM sorusudur ve
   ölçülmedi.
2. **3.3.7 Gereksiz tekrar giriş.** Yalnız tek sayfa içindeki yinelenen
   `autocomplete` alanları ölçüldü. Çok adımlı bir akış boyunca aynı bilginin
   iki kez istenip istenmediği ölçülmedi.
3. **3.1.2 Parçaların dili.** Kapı bir SEZGİ uyguluyor (ilan edilen dile
   yabancı yazı sistemi). Sezgi kanıt değildir; her parçanın doğru dili ilan
   ettiği ölçülmedi.
4. **Ekran okuyucu davranışı, anlamlı okuma sırası, klavye tuzağının
   tamamı.** Bunlar insan kararıdır ve hiçbir betik onları ölçtüğünü iddia
   edemez.
5. **iOS Safari ve gerçek yardımcı teknoloji.** Kapı Chrome'da koşuyor.
6. **Panelin bir ekranı ölçüldü (`/app`), on ekranı ölçülmedi.** Panelin
   derin ekranları (menü düzenleme, medya, faturalama) bu döngünün kapsamı
   dışındaydı.
7. **Metin kutularının %2'si (52 / 2640)** hiçbir kaydırma penceresine tam
   sığmadığı için ölçülmedi.

---

## 9. `scene-visual.baseline.json` NEDEN yenilenmedi

Bu paketin görevi tabanı "bilerek yenilemek"ti. Ölçüm başka bir şey söyledi
ve karar ölçüme uydu.

`scene-visual-gate` bu makinede **24 bulgu** veriyor. Sapmanın kaynağı
ayrıştırıldı: aynı kapı, aynı makinede, benim değişikliğim GERİ ALINMIŞ hâlde
koşturuldu ve iki imza karşılaştırıldı.

| Kaynak | En büyük hücre sapması |
|---|---|
| Benim değişikliğim | **3 / 255** (yalnız üç `home-1280` görünümünde) |
| Bu makinenin rasterleştirmesi (değişiklik YOKKEN) | **80 / 255** |

Yani 24 bulgunun tamamı bu makinenin kendi çizimindendir (başsız Chrome'un
yazılım rasterleştiricisi). Tabanı burada yenilemek, bu makinenin çizimini
depoya gömmek ve başka herkes için gerçek bir gerilemeyi görünmez kılmak
olurdu. **Taban dokunulmadan bırakıldı** ve sapmanın kaynağı bu belgeye
yazıldı; tabanı yenileyecek olan, CI'nın kendi çiziminde koşan bir pakettir.

---

## 10. Kapılar

| Kapı | Sonuç |
|---|---|
| `vendor/bin/pint --test` | Kapsam dışı — bu pakette değişen PHP dosyası YOK |
| `php artisan test` | 2996 geçti, 3 atlandı, 0 başarısız (24.084 doğrulama) |
| `npx vitest run resources/js` | 2206 geçti, 289 dosya |
| `npx prettier --check .` | Temiz |
| `npm run i18n:check` | Yansımalar PO kataloglarıyla aynı |
| `node scripts/scene-budget-gate --fail` | 0 bulgu |
| `scripts/mobile-ux-audit` (320) | Donmuş borç 16 hikâye, **yeni ihlal 0, düzelen 0** |
| `scripts/mobile-ux-audit` (320-RTL) | Donmuş borç 17 hikâye, **yeni ihlal 0, düzelen 0** |
| `scripts/wcag-gate.test.sh` | 8 denek, 8 geçti |
| `scripts/wcag-gate` (3 hâl × 40 görünüm) | **4 bulgu → 0** |
| `scripts/scene-visual-gate` | 24 bulgu — tamamı makine rasterleştirmesi (§9) |

---

## 11. Kullanıcı yolculuğu — bu ne demek

**Önce:** Görme gücü düşük bir restoran sahibi, işletim sisteminde "daha
fazla kontrast" seçili, telefonundan `zabuno.com` açıyor. Ana sayfada üç şeritli bir
bant akıyor ve en alttakinde ürünün on iki parçasının adı yazıyor. O satırı
göremiyor. Bir şeyler aktığını görüyor, ne yazdığını okuyamıyor. Sayfayı
kapatmıyor ama o bilgiyi de almıyor — ve kimse bunu bilmiyor, çünkü ölçen bir
şey yok.

**Şimdi:** Aynı satır 2.32:1'den 5.57:1'e çıktı; okunuyor. Ve daha önemlisi:
bir dahaki sefere aynı kusur eklenirse `scripts/wcag-gate` onu ölçüt adıyla,
öğesiyle, ölçülen ve beklenen değeriyle söylüyor — *"kontrast 2.32:1, gerek
4.5:1"* — "erişilebilirlik hatası" diye değil.

**Fark:** Sitenin okunmayan tek metni kalmadı (ölçülen kapsamda). Ölçen bir
kapı var, kapının kendi deneği var, ölçülmeyenler yazılı.

**Kalan engel:** Kapı CI'da koşmuyor; elle koşulan bir kapı, koşulmadığı gün
yoktur. Bant uçlarının maskeyle solması bir tasarım kararı olarak sahibinde.
Döngü 2 ve 3 ayrı paketler.

**Ürün iddiası:** Kurumsal sitenin ve panel giriş ekranlarının WCAG 2.2 AA
metin/metin-dışı kontrastı, yeniden akış, metin büyütme, metin aralığı, hedef
boyutu, odak gizlenmesi, tutarlı yardım ve erişilebilir kimlik doğrulama
ölçütleri **ölçüldü ve bugün bulgu vermiyor**. Bu bir UYUM BEYANI DEĞİLDİR:
§8'deki yedi madde ölçülmedi ve ölçülmemiş bir ölçüt, geçmiş bir ölçüt
değildir.
