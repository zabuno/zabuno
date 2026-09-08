<!--
    KARAR BELGESİ — FF-232, 2026-09-08.

    Ölçüm önce yapıldı, karar sonra verildi. Belgedeki her sayı gerçekten
    koşturulmuş bir komuttan gelir; ölçülemeyenler §10'da AÇIKÇA "bilinmiyor"
    diye ayrılmıştır.
-->

# Kurumsal tasarım yasası — daisyUI, tema türetimi, ikon dili, hareket zemini

## 1. Sahibin kararı ve bu belgenin kapsamı

2026-09-08, aynı cümlede iki şey:

> *"daisyUI kullan, baştan yarat."* — ve —
> *"UX estetiği önemli. Yüksek kaliteli animasyonlar, 3D gibi görünen 2D
> efektler, parallax ve sağlı sollu hareket eden landing pages, particles vb.
> Bir uzay teknolojileri şirketi gibi, abartı dursun, görünsün, hissettirsin."*

Sahibin sözü: **"bu kararım kesin. daisyUI da kesin."**

**Bu belge neyi bağlar:** kurumsal sitenin (`zabuno.com` tanıtım yüzeyi)
bileşen kütüphanesini, tema türetimini, ikon dilini, yoğunluk kararını,
hareket jetonlarını ve altbilginin nasıl büyüdüğünü.

**Neyi BAĞLAMAZ:** paneli. Panel `flowbite-react` + AEP jetonları üzerine
kurulu ve kendi estetik olgunluk belgesi var (`docs/102`). Kurumsal siteyi
daisyUI'ye taşımak panelin yeniden yazılması **değildir** ve bu pakette
panele bir tek sınıf girmedi. İki yüzeyin aynı ürün gibi görünmesi ortak bir
bileşen kütüphanesiyle değil, **ortak marka jetonlarıyla** sağlanır — §3.

**Sayaç:** bu paket kabuğu ve yasayı teslim eder. Sayfa gövdeleri (ana sayfa
ve ötekiler) ile hareket/efekt dağarcığı **ayrı paketlerin** işidir; bu paket
onlara yalnız zemin hazırlar (§7, §9).

---

## 2. Tailwind v4'te daisyUI — nasıl kuruldu, ÖLÇÜLDÜ

Depo Tailwind v4 kullanıyor (`tailwindcss ^4.0.0`, `@tailwindcss/vite`). v4'te
eklenti bir JS yapılandırma dosyasından değil, **CSS'ten** yüklenir.

```
npm i -D daisyui@latest        # kurulan sürüm: 5.7.28
```

```css
/* resources/css/daisy-theme.css — app.css tarafından @import edilir */
@plugin "daisyui" {
    themes: false;
    logs: false;
    prefix: 'dz-';
}
```

Seçeneklerin davranışı daisyUI'nin belgesinden **hatırlanarak değil**, paketin
kendi kodundan ölçüldü: `node_modules/daisyui/functions/pluginOptionsHandler.js`
ve `node_modules/daisyui/theme/index.js`.

**CDN yok ve olamaz.** `app/Http/Middleware/SecurityHeaders.php` ölçüldü:
politika `default-src 'self'`, `style-src 'self' 'nonce-…'`,
`font-src 'self' data:`. Dışarıdan gelen bir stil sayfası tarayıcıda **hiç
çalışmaz**. daisyUI npm'den gelir ve Vite paketine derlenir; yazı tipi ve ikon
depoda.

### 2.1 `themes: false` — neden zorunlu

`pluginOptionsHandler.js` ölçüldü: seçenek verilmezse varsayılan
`["light --default", "dark --prefersdark"]`dir ve daisyUI **kendi renklerini**
`:root`a yazar. O an depoda ikinci bir renk kaynağı doğardı.

### 2.2 `prefix: 'dz-'` — ölçülmüş bir zorunluluk

İlk derlemede önek yoktu. Ölçüm: `.modal`, `.card`, `.btn`, `.menu` gibi
daisyUI sınıfları derlenmiş CSS'e **girdi** — kurumsal sitede o sınıfların
biri bile yazılmadığı hâlde. Sebep, Tailwind v4'ün aday tarayıcısının kaynak
dosyalardaki düz kelimeleri de sınıf adayı saymasıdır: `aria-modal="true"`
yazan bir React bileşeni tek başına `.modal` kuralının basılmasına yetiyor.

Bedeli yalnız bayt değil. Panelde bir gün `class="card"` yazan biri, farkında
olmadan kurumsal sitenin bileşen kütüphanesini panele giydirirdi — ve bunu
hiçbir test yakalamazdı, çünkü ikisi de "çalışıyor" görünürdü. `dz-` öneki o
kapıyı kapatır.

**Rakamla:** öneksiz derleme 236.347 bayt, önekli derleme (hiç daisyUI sınıfı
yazılmadan) 140.061 bayt. Öneğin tek başına kazandırdığı: **96.286 bayt.**

---

## 3. Tema marka jetonlarından TÜRER — ve bir test bunu sabitliyor

### 3.1 Kural

> daisyUI'nin hiçbir renk değişkeni **sayı taşımaz**.
> Her biri bir `--aep-*` marka jetonuna işaret eder.

```css
@plugin "daisyui/theme" {
    name: 'light';
    default: true;
    color-scheme: light;
    --color-base-100: var(--aep-surface-raised);
    --color-primary:  var(--aep-accent-primary);
    ...
}
```

### 3.2 Tema adları neden `light` ve `dark`

`partials/theme-bootstrap.blade.php` ilk boyamadan önce kök öğeye
`data-theme="light"` ya da `data-theme="dark"` yazıyor; AEP jetonları
(`aep/tokens/colors.css`) tam olarak `[data-theme="dark"]` seçicisiyle koyu
değerlerine geçiyor; daisyUI'nin tema seçicisi de aynı öznitelik. Temayı
`zabuno` diye adlandırmak, aynı anahtarın iki adı olması demekti: ziyaretçi
koyu temaya geçtiğinde AEP koyulaşır, daisyUI açık kalırdı.

Bunun güzel bir sonucu var: **koyu tema için ayrı bir eşleme yazılmadı.** İki
blok aynı `var(--aep-…)` satırlarını taşır; değerler farklıdır çünkü AEP
onları `[data-theme="dark"]` altında yeniden tanımlar. İki farklı eşleme
yazsaydık, bir gün yalnız birini güncellerdik.

### 3.3 `prefersdark` bilerek YOK

daisyUI `prefersdark: true` ile `@media (prefers-color-scheme: dark)` altında
`:root:not([data-theme])` için de koyu değerleri basar. AEP jetonlarının böyle
bir dalı **yoktur** — koyu değerler yalnız `[data-theme="dark"]` altında
yaşar. Bayrağı açsaydık, betiği engellenmiş bir ziyaretçide `color-scheme: dark`
ilan edilir ama renkler **açık** kalırdı: form kontrolleri koyu, zemin beyaz.
Bugünkü davranış (betiksiz ziyaretçi açık temayı görür) korunuyor.

### 3.4 Yarıçap, boyut, derinlik

| daisyUI | Değer | Gerekçe |
|---|---|---|
| `--radius-field` | `var(--aep-radius-sm)` (4px) | Girdi ve düğme |
| `--radius-box` / `--radius-selector` | `var(--aep-radius-lg)` (8px) | AEP yarıçap tavanı 8px |
| `--size-field` / `--size-selector` | `var(--aep-space-1)` (4px) | Atomik grid |
| `--border` | `1px` | Renk değil kalınlık; jeton dosyasında karşılığı yok |
| `--depth` | `0` | **Flat 2.0**: vurgu tonla verilir, gölgeyle değil (`docs/102` §1) |
| `--noise` | `0` | Marka jetonlarında karşılığı olmayan bir doku |

### 3.5 Kapı: `DaisyThemeDerivationTest`

| Kimlik | Ne ölçüyor |
|---|---|
| DAISY-THEME-01 | Tema dosyasında ham renk yok (`#`, `rgb(`, `oklch(`, `color-mix(` …) |
| DAISY-THEME-02 | Her `--color-*` bir `var(--aep-…)`dir **ve** o jeton `aep/tokens/` altında gerçekten tanımlıdır |
| DAISY-THEME-03 | daisyUI'nin beklediği **28** değişkenin hepsi iki temada da yazılmıştır — biri eksik kalsa daisyUI kendi hazır değerini sessizce doldururdu |
| DAISY-THEME-04 | Hazır temalar kapalı, `dz-` öneki yerinde |
| DAISY-THEME-05 | Tema adları `data-theme` değerleriyle aynı |

DAISY-THEME-03'ün beklediği liste **elle yazılmadı**: `node_modules/daisyui/themes.css`
içindeki hazır `light` temasından okunuyor. daisyUI bir gün yeni bir değişken
eklerse kapı onu ister.

**Ölçülmeyen:** rengin güzelliği ve kontrast oranları. Kontrast jeton
katmanının işidir ve orada ölçülür; burada ölçülen, kurumsal sitenin o
katmandan **beslendiği**.

---

## 4. İkon dili — emoji yasak, Phosphor ilk

`docs/118` E6 düzeltildi (2026-09-08). Kural **iki yüzey için de aynı**.

Kurumsal site React yüklemez, bu yüzden `@phosphor-icons/react` bileşenlerini
kullanamaz — ama **aynı aileye** erişir:
`resources/views/components/phosphor.blade.php` paketin `regular` ağırlığındaki
yol verisini bire bir taşır.

| Kimlik | Ne ölçüyor |
|---|---|
| ICON-01 | Kabuğun hiçbir dosyasında emoji yok |
| ICON-02 | Her `d` dizesi `@phosphor-icons/react` paketindeki tanımda **birebir** bulunuyor |
| ICON-03 | Yanında sözcük olan ikon `aria-hidden` ve `focusable="false"` |
| ICON-04 | Hiçbir kurumsal şablon kendi SVG'sini çizmiyor — ikon yalnız `<x-phosphor>`dan gelir |

Bugün kullanılan **üç** glif: `list`, `x`, `caret-down` — kabukta gerçekten
kullanılanların tamamı. Kullanılmayan bir glifi "ileride lazım olur" diye
bırakmak, ICON-02'nin ölçtüğü şeyi ölü koda çevirirdi.
Yeni bir glif eklemek, paketten kopyalayıp ICON-02'yi geçirmek demektir.

---

## 5. Tipografi, boşluk ve yoğunluk

Hiçbiri bu pakette **yeniden** tanımlanmadı; kaynak zaten depoda:

| Eksen | Kaynak | Kural |
|---|---|---|
| Tipografi | `app.css` `@theme` (`--text-title`, `--text-section`, …) | Rol adları boyuta göre değil **role** göre; gövde tabanı 1rem'in altına inmez |
| Boşluk | `aep/tokens/spacing.css` (4/8/12/16/24/32/48) | Ara değer yok |
| Dokunma | `--control-height` (≥44px) | `docs/118` E3 |

**Yoğunluk kararı (`docs/118` E3) daisyUI'yi ezer.** daisyUI'nin kendi
ölçüleri masaüstü ölçeğindedir: `.footer` 2.5rem boşluk verir, `.menu`
satırları ~30 piksel yüksektir. İkisi de dar ekranda yanlış — biri ekranı
yer, öteki parmağı ıskalatır. `site-shell.css` ikisini de kendi ölçeğine
çeker:

- **büyük hedef:** her gezinti ve altbilgi satırı `min-height: var(--control-height)`;
- **sıkı boşluk:** ızgara boşluğu `--space-5` / `--space-4`.

**Font da ezilir, ve bu en önemlisi.** daisyUI'nin `.menu`, `.footer` ve
`.btn` bileşenleri gövde boyutunu 14 piksele (`.875rem`) sabitliyor. Bu,
deponun kendi kararıyla doğrudan çelişir (`docs/118` E3: *"yoğunluk fontu
küçülterek DEĞİL"*) ve ölçülebilir sonucu şudur: 320 pikselde bir kebapçı,
gezinti ve altbilgi metnini sayfanın geri kalanından iki punto küçük okurdu.
Kabuk `--aep-text-body`ye (1rem) çeker; alt bileşenler `inherit` eder, yani
gövde boyutu bir gün değişirse kabuk onunla birlikte döner.

Bunun `!important` gerektirmediğini not etmek gerekiyor: daisyUI kuralları
`@layer utilities` içinde yaşar, `site-shell.css` ise katmansızdır ve
katmansız bir kural her zaman katmanlı bir kurala karşı kazanır.

**Kırılma noktası jetonu YASAK** (`MP-05`, testle sabit). Altbilgi ızgarası bu
yüzden `repeat(auto-fit, minmax(11rem, 1fr))`: 320 pikselde tek sütun, ekran
genişledikçe kendiliğinden çoğalır. daisyUI'nin `footer-horizontal` /
`footer-vertical` sınıfları bir **seçim** ister ve ikisi de yanlıştır.

---

## 6. Kabuk ve altbilgi — zenginlik kütükten türer

### 6.1 Ölçülen gerçek

Canlı sitede on dört adres var; içeriği yazılmış on altı kurumsal sayfa var;
**kesişimleri sıfır** — yazılmış on altısının hiçbiri yayınlanmış değil
(`docs/128`, `docs/129`).

Sahibin isteği: *"çoook zengin, çok katmanlı, çok row, çok menu grubu, pSEO
için footer üzerinde content menus."* Elle yazılmış zengin bir ızgara **bugün**
yüzlerce 404'e giden bağlantı demekti — üstelik altbilgide, yani kimsenin
bakmadığı yerde.

### 6.2 Verilen karar

Altbilgi üç kattır:

1. **Marka satırı** — her zaman var.
2. **Yaşayan gruplar** (`Ürün`, `Yasal`) — elle bildirilmiş, bugün dolu:
   4 + 8 = **12 bağlantı**.
3. **İçerik menüleri** — kütükten türeyen pSEO katı. **Bugün boş ve bu yüzden
   hiç çizilmiyor.**

Üçüncü kat elle yazılmaz. `SiteNavigation::contentMenus()` kütükten
bağlanabilir sayfaları okur, onları kendi `parent_key` hiyerarşilerine göre
gruplar, başlığı ve etiketi **sayfanın kendi başlığından** alır. Sahip bir
sayfayı yayına aldığı gün, tek bir Blade satırı değişmeden yeni bir grup ve
yeni bağlantılar belirir.

**Etiket neden katalogdan değil:** her yeni sayfa için bir katalog anahtarı
yazmak, bir kod değişikliği ve bir çeviri borcu üretirdi. Katalogda duran tek
dize bandı **açan** sözcüktür (`site.footer.contentMenus`); o kabuğa aittir,
kütüğe değil.

### 6.3 Kapı tek cümledir

> **Altbilgideki her bağlantı 200 döner.**

Bunu sağlayan şey dikkat değil, kaynak: liste ziyaretçinin alacağı HTTP kodunu
üreten **aynı** `ResolvePageDelivery` kararından süzülüyor (`docs/129` §3).

**Bu pakette kapatılan kusur.** `SiteNavigation` önceden `PageGate::decide()`i
doğrudan çağırıyordu. Kapı tek başına "bu satırın durumu gösterilebilir mi"
sorusunu yanıtlar; ziyaretçinin gördüğü 200/404 ise bir soru daha sorar: *o
dilde gerçekten yazılmış bir metin var mı?* Ölçüldü: kütükte `published`
işaretli ama metni yazılmamış bir sayfa **gezintide görünüyor ve tıklandığında
404 dönüyordu**. Türkçe içerik yuvası bilerek boş olduğu için (`docs/118` E4)
bu, bugün gerçek bir senaryodur — ve altbilginin zenginleşmesi onu yüzlerce
bağlantıya çoğaltacaktı.

| Kimlik | Ne ölçüyor |
|---|---|
| FOOTER-CONTENT-01 | Bugün bant hiç çizilmiyor — boş başlık bile yok |
| FOOTER-CONTENT-02 | Altbilgideki **her** bağlantı 200/302 döner |
| FOOTER-CONTENT-03 | "Yayında" işaretli ama metni yazılmamış sayfa altbilgiye asla girmez |
| FOOTER-CONTENT-04 | Gruplar `parent_key` hiyerarşisinden çıkar; etiket kütükten gelir; aynı adres iki kez yazılmaz |
| FOOTER-CONTENT-05 | Bandın içindeki her adres **betiksiz** gövdede duruyor |
| NAV-REGISTRY-05 | 404 dönen bir adrese hiçbir yerden bağlantı verilmez |

### 6.4 320 piksel

Çok satırlı bir altbilgi dar ekranın en büyük riskidir. pSEO katı bir
`<details>` içindedir ve **kapalı başlar**: ilk ekranı doldurmaz, parmakla
açılır, betiksiz çalışır, içindeki her bağlantı HTML'de zaten durur. Geniş
ekranda da kapalı başlar — **tek kod yolu** (`docs/118` E2); ikinci bir
davranış yazmak, ölçülmemiş bir varsayımı kurala çevirmek olurdu.

### 6.5 Betiksiz erişilebilirlik — taban HTML, tavan serbest

`docs/118` E8. Kural artık "kabukta betik yok" değil:

> Her gezinti hedefi sunucu HTML'inde `<a href>` olarak **bulunur**.
> JavaScript bunun **üstüne** serbestçe ekler.

`<summary>` bu yüzden hâlâ tabandır: tarayıcının kendi açılır kapanır
düğmesidir, klavyeyle çalışır, durumunu ekran okuyucuya söyler ve betik
olmadan açılır. Kapılar: `SHELL-SINGLE-SOURCE-04` ve `FOOTER-CONTENT-05`.

---

## 7. Hareket — bu paket dağarcığı YAZMAZ, ona yer AÇAR

Sahibin istediği abartı **sayfa gövdelerinde** yaşayacak. Kabuk onu **taşır**
ama kendisi sakin kalır, ve bu bir zevk tercihi değil: üst çubuk her sayfada
aynıdır ve her gün görülür; her açılışta kıpırdayan bir üst çubuk üçüncü
ziyarette gürültüdür. Hareketin etkisi nadirliğinden gelir.

### 7.1 Jetonlar (`site-shell.css`, `:root`)

| Jeton | Değer | Ne için |
|---|---|---|
| `--motion-scene-fast` / `-base` / `-slow` | 400 / 700 / 1200ms | **Sahne** süreleri. Arayüz süreleri ayrıdır ve AEP'den gelir (`--duration-*`, 120–240ms): bir menünün açılması bir arayüz olayıdır, bir parallax katmanı değil |
| `--motion-emphasized` | `cubic-bezier(.2,0,0,1)` | Girişte hızlanıp yavaşça oturan hareket |
| `--motion-drift` | `cubic-bezier(.4,0,.6,1)` | Sürekli hareket (parçacık, arka plan kayması). Doğrusala yakın olmayan sürekli hareket, döngünün başladığı yeri ele verir |
| `--motion-depth-near/mid/far` | 8 / 24 / 56px | Parallax **mesafesi**. Yüzde değil: yüzde kapsayıcıya bağlıdır ve aynı efekt iki bölümde iki farklı hızda akar |
| `--layer-scene-back/mid/front` | 0 / 1 / 2 | Sahne katmanları |
| `--layer-content` | 10 | İçerik her zaman sahnenin üstünde |

Kabuğun bugünkü yığınları: menü bölmesi 20, çerez şeridi 30, atlama bağlantısı
50. Sahne efektleri bunların **altına** girer ve bu bir tercih değil bir
kural: hiçbir dekoratif katman gezinmeyi ya da bir hukuki seçimi örtemez.

### 7.2 Kap: `.site-stage`

```html
<section class="site-stage">
    <div class="site-stage-layer" data-depth="mid">…efekt…</div>
    <div class="site-stage-content">…metin…</div>
</section>
```

`overflow: clip` (yeni kaydırma kabı yaratmaz, `sticky` ve çıpalar çalışmaya
devam eder), `isolation: isolate` (içerideki `z-index`ler dışarı sızmaz),
katmanlar `pointer-events: none`. Bir efekt kendi `position`/`overflow`
kararını vermez — sahnenin içine girer. Sebep ölçülebilir: `clip` olmadan yana
kayan bir katman sayfayı yatay kaydırır ve `scripts/mobile-ux-audit` bunu ilk
kuralında kırar.

### 7.3 `prefers-reduced-motion` — mutlak ve tek yönlü

Kural `no-preference` üzerinden yazılıdır, `reduce` üzerinden **değil**:
ikinci biçimde animasyon önce tanımlanır sonra iptal edilir ve iptali yazmayı
unutan bir satır sessizce hareket eder. Burada hareket, açıkça istenmiş
olmadan **hiç doğmaz**.

Hareket paketi kök öğeye `data-motion="on"` yazacak ve sahne animasyonları
yalnız o zaman çalışacak. Kanca CSS'te, betikte değil: `reduce` diyen bir
ziyaretçide betik özniteliği yazsa **bile** hiçbir sahne kuralı doğmaz. Tek
yönlü bir kapı — betik bu kararı geri alamaz.

---

## 8. CSS ağırlığı — ÖLÇÜLDÜ

`php artisan view:clear && rm -rf public/build && npm run build`, sonra
`public/build/assets/app-*.css`.

**Görünüm önbelleği neden temizleniyor.** `app.css` `@source
'../../storage/framework/views/*.php'` diyor: derlenmiş Blade önbelleği bir
tarama kaynağıdır. Test suiti koştuktan sonra o dizin dolar ve aynı kaynak
kodu **19 KB daha büyük** bir CSS üretir (ölçüldü: 161 KB → 180 KB).
Önbellek temizlenmeden alınan bir "önce/sonra" karşılaştırması iki farklı
şeyi ölçer. Aşağıdaki dört satır aynı koşulda alındı; `origin/main` ölçümü
ayrı bir çalışma ağacında, aynı komutlarla yapıldı.

| Aşama | Ham | gzip |
|---|---|---|
| daisyUI'den ÖNCE (`origin/main`, temiz) | 131.342 B | 23.556 B |
| daisyUI + tema, **öneksiz** (terk edildi) | 236.347 B | 37.682 B |
| daisyUI + tema, `dz-` önekli, henüz sınıf yazılmadan | 140.061 B | 25.015 B |
| **Bu paketin teslimi** (kabuk daisyUI'ye taşınmış, temiz) | **161.186 B** | **28.021 B** |

**daisyUI'nin net maliyeti: +29.844 ham bayt, +4.465 gzip bayt** — yani
sıkıştırılmış CSS'te **%18,9** artış.

Emilen sınıflar ölçüldü: `dz-btn`, `dz-menu`, `dz-dropdown`, `dz-navbar`,
`dz-footer` ve türevleri; **hiçbir kırılma noktası varyantı üretilmedi** ve
kullanılmayan bileşen (carousel, chat, mockup, timeline, calendar, kbd, dock,
hover3d, megamenu…) CSS'e **girmedi** — Tailwind v4 eklenti bileşenlerini
kullanıma göre eliyor.

React paketi değişmedi: kurumsal sayfalar hâlâ **sıfır React** yüklüyor
(`docs/38` §16).

### 8.1 Dar ve geniş ekran — ÖLÇÜLDÜ, ikisi de

`php artisan site:export-static` ile üretilen **14 canlı sayfa**, gerçek
Chrome'da, `scripts/mobile-ux-audit` ile. Aynı ölçüm `origin/main` için de
alındı; tek bir sayı tek başına bir şey söylemez.

| Genişlik | `origin/main` | Bu paket |
|---|---|---|
| **320×568** | 5/14 sayfa etkilenmiş · `small-target` 9 · `tight-gap` 3 | **3/14** · `small-target` 7 · **`tight-gap` 0** |
| **1280×800** | 6/14 · `small-target` 10 · `tight-gap` 6 | **4/14** · `small-target` 8 · `tight-gap` 2 |

**Geniş ekran ölçümü bu pakette mümkün oldu.** `scripts/mobile-ux-audit`
genişliği koda sabitlenmişti (320); `--width` bayrağı eklendi. Sıra
değişmedi — dar ekran hâlâ taban, CI kapısı hâlâ 320'de koşuyor — ama ikinci
bir soru artık sorulabiliyor: *aynı akışkan düzen geniş ekranda da ayakta mı?*

**Ve ilk sorduğunda bir kusur buldu.** daisyUI'nin `.footer > *` kuralı
`place-items: start` verir; sonuç, liste öğesinin içeriği kadar daralmasıdır.
1280'de "Help" bağlantısı **30 piksel** genişliğe düşüyor ve komşusuyla arası
**0 piksel** kalıyordu. 320'de görünmüyordu, çünkü orada sütun zaten dardı.
Düzeltildi (`.site-footer-list`, `> li` ve `> nav` tam genişlik) ve tablodaki
`tight-gap` düşüşü o düzeltmenin ölçüsüdür.

**Kalan bulgular kabukta DEĞİL, sayfa gövdelerinde** ve hepsi `origin/main`
ölçümünde de var: ana sayfanın metin akışı içindeki bağlantılar ("Contact
us", "Ask us") ve yardım sayfasının "Write to us" bağlantısı. WCAG 2.2'nin
2.5.8 ölçütü metin akışındaki hedefleri 44 pikselden muaf tutar; onları
büyütmek satır aralığını kırar. Bu paket sayfa gövdelerine dokunmadı.

**`scripts/shell-scroll-gate`**: 14/14 kontrol geçiyor (320×568 ve 1440×900).
Betiğin sonundaki geçici dizin temizliği bu makinede `ENOTEMPTY` ile
çöküyor — `origin/main`'de de **birebir aynı** (14 ok + aynı çökme), yani bu
paketin ürettiği bir şey değil.

---

## 9. Sonraki ajanlara talimat

**Serbest:**

- daisyUI bileşeni kullanmak — **`dz-` önekiyle**.
- Sayfa gövdelerinde hareket, parallax, parçacık, derinlik: sahne kabına
  (`.site-stage`) girmek ve §7 jetonlarını kullanmak koşuluyla.
- Kabuğun üstüne JavaScript eklemek (mega menü, arama, hareket) — taban
  `<a href>`ler yerinde kaldığı sürece.
- Yeni Phosphor glifi eklemek — paketten kopyalayarak, ICON-02'yi geçirerek.

**Yasak:**

- Tema dosyasına **ham renk** yazmak. Renk gerekiyorsa önce marka jetonu
  eklenir, sonra tema ona işaret eder.
- daisyUI'nin hazır temalarını açmak (`themes:` değerini değiştirmek).
- Öneki kaldırmak ya da panele daisyUI sınıfı sokmak.
- Kırılma noktası jetonu (`sm:` …) kullanmak — `MP-05` kırar.
- Emoji. Her yerde.
- Altbilgiye elle bir kütük bağlantısı yazmak. Zenginlik yayın kararından
  gelir, Blade'den değil.
- `!important`. Katmansız bir kural zaten katmanlı bir kuralı yener.
- Ham süre/yumuşatma yazmak (`0.6s`, `ease-out`). §7 jetonları kullanılır.
- Dış kaynak: CDN, uzak yazı tipi, uzak görsel. CSP zaten reddeder.
- Unsplash (`docs/118` E7). Görselin kaynağı, adresi, lisansı ve indirme
  tarihi kaydedilir.
- `status.zabuno.com` bağlantısı — o adres henüz yayında değil.

---

## 10. Bu paketin ölçemedikleri — açıkça

1. **Üretimde bugün altbilginin nasıl göründüğü bilinmiyor.** Üretim
   sunucusuna erişim yok. Buradaki "bant bugün boş" cümlesi, temiz bir
   veritabanına `site:import-map` koşturulmuş yerel ölçümden gelir. Üretimde
   bir sayfa yayına alınmışsa bant orada zaten doludur.

2. **daisyUI'nin görsel sonucu ölçülmedi, yalnız geometrisi ölçüldü.**
   `scripts/mobile-ux-audit` taşma, kırpılma, dokunma hedefi ve yoğunluk
   ölçer; estetik, hiyerarşi ve kelime seçimi insan kararıdır ve bu araç
   onları ölçtüğünü iddia etmiyor.

3. **iOS Safari doğrulanmadı.** Ölçüm Chrome'da yapılıyor. `<details>` tabanlı
   açılır bölmenin iOS'taki davranışı belgelenmiş davranışa dayanıyor, gerçek
   cihazda ölçülmedi.

4. **Hareketin kendisi ölçülmedi**, çünkü bu pakette hareket yok. §7 bir
   sözleşmedir; sözleşmenin tutup tutmadığı, hareket paketi yazıldığı gün
   ölçülecek.

---

## 11. Rapor alanları

- **once:** Kurumsal sitenin bileşen katmanı yoktu; kabuk elle yazılmış CSS'ti
  ve büyüdükçe her yeni parça kendi ölçüsünü icat ediyordu. Altbilgi iki
  gruptu ve zenginleştirilmesi, bugün 404 dönen yüzlerce adrese bağlantı
  vermek anlamına gelirdi. Gezinti, ziyaretçinin aldığı HTTP kodundan **farklı**
  bir kural okuyordu: "yayında" işaretli ama metni yazılmamış bir sayfa menüde
  görünüp 404 dönebiliyordu. İkon kararı belgede yanlış yazılmıştı.
- **simdi:** Bileşen katmanı daisyUI ve teması marka jetonlarından türüyor —
  bir tek renk elle yazılmıyor ve beş kapı bunu sabitliyor. Altbilgi kütükten
  besleniyor: yayına alınan her sayfa, kod değişmeden, doğru grubun altında
  beliriyor ve her bağlantı 200 dönüyor. İkon dili Phosphor, emoji yasak,
  glifler pakete karşı ölçülüyor. Hareket için jeton, katman sırası, sahne kabı
  ve `prefers-reduced-motion` kancası hazır.
- **fark:** Sıkıştırılmış CSS 23.559 → 27.972 bayt (+%18,7). Bugün ekranda
  görünen bağlantı sayısı **değişmedi** ve bu doğru sonuçtur: kütükte
  yayınlanmış tek bir sayfa yok. Değişen şey, yayın kararı verildiği anda
  altbilginin onu **kendiliğinden** yansıtacak olması.
- **kullaniciYolculugu:** Sahip panelde "Karekod menü" sayfasını yayına alır. O
  andan itibaren adres 200 döner (`docs/128`), `sitemap.xml` onu ilan eder
  (`docs/129`) ve **aynı karar** onu altbilgideki "Ürün" grubunun altına yazar.
  Ziyaretçi telefonunda altbilgiyi açar, sayfayı görür, dokunur — hedef 44
  piksel, boşluk sıkı, yatay kayma yok. Daha önce bu zincirin son halkası
  yoktu: sayfa açılıyor ama sitede hiçbir yerden ona gidilmiyordu.
- **kalanEngel:** Kütükte yayınlanmış sayfa yok — bu bir kusur değil, bekleyen
  bir **insan kararı**. Sayfa gövdeleri ve hareket dağarcığı ayrı paketlerin
  işi. Türkçe içerik yuvası hâlâ bilerek boş (`docs/118` E4), dolayısıyla üst
  çubuktaki keşif grubu bugün hiç çizilmiyor.
- **capability_delta:** Kurumsal sitenin görünümü artık bir **sistemden**
  geliyor ve o sistem panelin beslendiği jetonların aynısından besleniyor;
  altbilgi ise sayfa kütüğünün bir projeksiyonu — bir yayın kararı ile
  ziyaretçinin gördüğü gezinti arasında elle yapılacak hiçbir adım kalmadı.
- **Çalışabilen:** Kurumsal sitenin her adresi daisyUI kabuğunu giyiyor; tema
  koyu/açık geçişinde marka jetonlarıyla birlikte dönüyor; ikonlar Phosphor;
  bir sayfa yayına alındığı anda altbilgide beliriyor; her şey betik
  çalışmadan da geziliyor.
- **Çalışamayan:** Sayfa gövdelerinde henüz hiçbir efekt yok — sahibin istediği
  "uzay teknolojileri şirketi" hissi bu pakette **verilmedi**, yalnız zemini
  hazırlandı. Bugün hiçbir kurumsal sayfa yayında olmadığı için pSEO katı
  ekranda görünmüyor.
