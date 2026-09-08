# 146 — Sahne motoru: kurumsal sitenin hareketi (Döngü 1/3)

**Sahibin emri (2026-09-08):**

> *"UX estetiği önemli. Yüksek kaliteli animasyonlar, 3D gibi görünen 2D
> efektler, parallax ve sağlı sollu hareket eden landing pages, particles vb.
> Yüksek kalite UX estetiği ve animasyon içeren sayfalar yarat. **Bir uzay
> teknolojileri şirketi gibi, abartı dursun, görünsün, hissettirsin.**"*

Ve: *"Kurallar mı engelliyor? Yeni kurallar yaz. React mı lazım? Ekle. Ne
lazım? Ekle, çöz, yap."*

Bu belge **Döngü 1**'in çıktısıdır: motor kuruldu ve ana sayfa onunla yeniden
yazıldı. Döngü 2 bunu eleştirip yükseltecek, Döngü 3 cilalayıp öteki sayfalara
yayacak. §9 **kasten eksik bırakılanları** sayar.

Ezilen kısıtların kaydı `docs/118` E11–E14'te. Renk paleti ve yüzey dili
`docs/145`'te; bu belge onun üstüne **hareketi** koyuyor.

---

## 1. Değişmeyen üç şey

Sahibin emri bu üçünü kaldırmadı ve paket üçünü de ölçtü.

**1. `prefers-reduced-motion` mutlaktır.** Kapalıyken sayfa **tam işlevli ve
eksiksiz**. Bu bir "azaltılmış sürüm" değil: aynı bölümler, aynı bağlantılar,
aynı metin, aynı yüzey dili — yalnız kıpırdamıyor. Parallax bazı insanlarda
fiziksel rahatsızlık (baş dönmesi, mide bulantısı) yapar; bu bir zevk meselesi
değildir.

Ölçüldü (`scripts/scene-perf-gate`, tercih `reduce`):

| Ölçüt | Değer |
| --- | --- |
| `data-motion` özniteliği | **yazılmadı** |
| animasyon taşıyan öğe | **0** |
| giriş animasyonu yüzünden gizli kalan bölüm | **0** |
| bölüm / bağlantı / başlık sayısı | 6 / 8 / 14 — hareketli hâlle **aynı** |

**2. Uydurma yok.** Sahte müşteri, sahte rakam, sahte logo, sahte canlı
gösterge yok. Akan bantlardaki her sözcük, sayfanın kendi bölümlerinde de
yazan gerçek bir ürün yeteneğidir ve katalogdan gelir. `HOME-HONEST-03`
yerinde duruyor.

**3. CDN yok, emoji yok, çeviri yok.** Motor `npm`'den değil, depodan gelir
(hiç bağımlılığı yok) ve CSP `default-src 'self'` hiç gevşetilmedi. Ana
sayfada tek bir yeni görünür dize yazılmadı: `lang/untranslatable-debt.json`
hâlâ **sıfır**.

---

## 2. Teknoloji: ölçüldü, kütüphane alınmadı

Kısıt kalktı (`docs/118` E11). Ölçüm yine elle yazmayı seçti.

| Aday | gzip (`gzip -9`, npm dist) | Neden alınmadı |
| --- | --- | --- |
| three.js | **86.569 bayt** | Sahne grafiği, malzeme sistemi, ışık modeli — hiçbiri bu sahnede kullanılmıyor. |
| motion | **46.644 bayt** | Zaman çizelgesi motoru; sahne kaydırmaya bağlı, zaman çizelgesine değil. |
| GSAP + ScrollTrigger | **46.339 bayt** | Aynı gerekçe; ayrıca kaydırma tetikleyicisi bu sayfada tek bir `IntersectionObserver` ediyor. |
| GSAP çekirdek | **28.314 bayt** | Tek başına parallax ve tuval vermiyor. |
| **elle yazılan motor** | **4.417 bayt** | — |

**Asıl gerekçe ağırlık değil.** Bir kütüphane, düşük güçlü bir telefonda
**hangi katmanın söneceğini** bilemez. Derecelendirme merdiveni (§6) bu
paketin en önemli parçası ve ürünün kendi kodunda olmak zorunda.

**Neden React yok.** Sahnenin etkileşim durumu yok — tuval, kaydırma, imleç.
`docs/118` E5 madde 3 zaten şunu söylüyordu: *"React adacığı yalnız bir bileşen
GERÇEKTEN etkileşim gerektirdiğinde açılır; süs için açılmaz."* Kurumsal
sayfalar React paketini hiç yüklemiyor ve bu ölçülmüş bir kazanç
(`HOME-NO-REACT-05`). Kapı kapalı değil: yarın gerçekten etkileşimli bir
bileşen gerekirse adacık açılır.

**Neden WebGL, ama tek bir bağlam.** Yıldız alanı gerçek perspektifle çiziliyor
(§4.1) ve ilerleme tamamen **köşe gölgelendiricisinde**: her karede yalnız
birkaç `uniform` yazılıyor, tek çizim çağrısı yapılıyor, CPU hiç döngü
kurmuyor. WebGL yoksa aynı geometri canvas 2D'de yarı yıldızla çiziliyor —
sahne kaybolmuyor, ucuzluyor.

---

## 3. Ağırlık: öncesi, sonrası, yeni bütçe

| Ölçüt | Öncesi | Sonrası | Fark |
| --- | --- | --- | --- |
| Kurumsal JavaScript (gzip) | **0** | **4.417 bayt** | +4.417 |
| Kurumsal CSS (gzip, ortak paket) | 30.410 bayt | 34.147 bayt | **+3.737** |
| Kurumsal CSS (ham) | 175.861 bayt | 203.548 bayt | +27.687 |
| Ana sayfa HTML | 20.264 bayt | 27.118 bayt | +6.854 |

Yeni tavan ve **gerekçesi** `scripts/scene-budget.json` içinde yaşıyor — bu
belgede değil, çünkü bir sayıyı iki yere yazmak ilk ayrışmada hangisinin doğru
olduğunu belirsiz yapar:

- kurumsal betik ≤ **6.144 bayt gzip**
- kurumsal stil ≤ **40.960 bayt gzip**

Kapı: `node scripts/scene-budget-gate --fail`.

**Bütçe kaldırılmadı, yükseltildi** (`docs/118` E12). Kaldırılmış bir bütçe,
bir gün "bir kütüphane daha ekleyelim" denildiğinde kimsenin fark etmeyeceği
bir yerdir.

**Stil tavanına yaklaşan döngü için kural:** 40 KB'a yaklaşıldığında tavanı
yükseltmeden önce ortak paketi *kurumsal* ve *panel* olarak bölmeyi
değerlendirmek zorunludur. Bugün kurumsal sayfa, panelin AEP + Flowbite
CSS'ini de indiriyor ve sahnenin payı o toplamın onda biri bile değil.

---

## 4. Dağarcık — sahne sözlüğü

Motor sayfayı **isimle değil öznitelikle** tanır: bir sayfa `data-scene="field"`
yazarak sahneyi ister, motor onu bulur. Sayfa adlarını betiğe yazmak, her yeni
kurumsal sayfada motoru düzenlemek demekti; düzenlenmeyi unutan ilk sayfa
sessizce sahnesiz kalırdı.

### 4.1 Derinlikli alan — `<canvas data-scene="field">`

Her yıldızın gerçek bir `z` değeri var; ekrandaki yeri `x/z` ile hesaplanıyor.
Yani parallax bir efekt değil, **geometrinin sonucu**: yakın yıldızlar hızlı,
uzak yıldızlar yavaş akar ve merkezden dışarı açılır. Üç "katman" yerine
sürekli bir derinlik var.

- Kamera imleçle ve kaydırmayla ötelenir (`data-scene-sway`, `data-scene-speed`).
- Toplamalı karışım: iki yıldız üst üste geldiğinde biri ötekini örtmez, ikisi
  birden parlar — ışıma (bloom) ayrı bir geçiş olmadan doğar.
- Parlaklık dağılımı **üssel**: binlerce sönük nokta, birkaç göze çarpan.
  Düz dağılım denendi ve "kar tanesi gürültüsü" gibi okundu (1280×800 ölçümü).
- Yıldızlar **sabit tohumlu**: aynı sayfa iki kez açıldığında aynı gökyüzü
  doğar, yani ekran görüntüsü karşılaştırması anlamlı kalır.
- Görünmeyen sahne boyanmaz (`IntersectionObserver`).

### 4.2 Parallax düzlemleri — `[data-plane="near|mid|far"]`

Betik yalnız −1…1 arası bir **ilerleme** (`--scene-shift`) yazar; o ilerlemenin
kaç piksel ettiğine CSS karar verir (`--motion-depth-near|mid|far`). Böylece üç
düzlemin hızı tek bir jeton kümesinden okunur.

Kare içinde **hiç DOM ölçülmez**: `getBoundingClientRect()` düzeni yeniden
hesaplatır ve kare süresini katman sayısıyla birlikte büyütür. Ölçüm yalnız
açılışta, boyut değişince ve derece düşünce.

### 4.3 Akan bantlar — `.scene-drift[data-direction="start|end"]`

Sağdan sola ve soldan sağa akan iki şerit. Yön **mantıksaldır**: sağdan sola
yazılan bir dilde ikisi de kendiliğinden yer değiştirir. Şerit iki özdeş kopya
taşır (dikişsiz döngü) ve kopya **Blade'de** basılır — betiği engellenmiş bir
ziyaretçide de bant tam görünür. `aria-hidden`, çünkü aynı sözcükler birkaç
santim aşağıda okunabilir bir bölümde zaten duruyor.

### 4.4 3D görünen 2D kartlar — `.scene-tilt`

Kalınlık `site-panel`den geliyor (parıltı + kenar + renkli gölge); eğilme ve
ışık kırılması buradan. **Yalnız işaretleyicili cihazda**: dokunmada `hover`
yoktur ve parmak kartın üstünü kapatır. Ayrım iki katmanda birden yazılı — CSS
efekti çizmez, betik olay dinleyicisini hiç bağlamaz. Klavyeyle odaklanan
kullanıcı da kalkışı görür.

### 4.5 Kaydırmaya bağlı bölüm geçişleri — `[data-scene-progress]`

`--scene-progress` bir bölümün görüntü alanından geçişini 0 → 1 yazar. Ufkun
büyümesi, ışığın güçlenmesi ve derinlik katmanları **aynı sayıyı** okur: üç
ayrı efekt değil, tek bir geçişin üç yüzü.

### 4.6 Giriş — `.scene-reveal`

`IntersectionObserver`; kare almaz. **Tek yönlü**: bir kez doğan öğe geri
kaybolmaz — yukarı kaydıran ziyaretçi okuduğu içeriğin silinmesini görmemeli.
Gizleme kuralı yalnız `:root[data-motion='on']` altında: betik ayakta değilse
**hiçbir şey saklanmaz**. "Önce gizle, sonra betikle göster" deseni betik
düşerse sayfayı boş bırakır.

### 4.7 Atmosfer — `.scene-nebula`, `.scene-beam`, `.scene-horizon`, `.scene-vignette`

Saf CSS; betiğe hiç bağlı değil, yalnız `prefers-reduced-motion` kapısından
geçer. Gerekçe ölçüldü: kurumsal sayfanın en çok okunduğu an, betiklerin en çok
engellendiği andır (`docs/118` E8). Betiği engellenmiş bir ziyaretçi hareketsiz
bir sayfa değil, **daha sakin** bir sayfa görür.

Vinyet'in ikinci bir işi var: okunan metnin arkasındaki zemini koyulaştırır,
yani kontrastı düşürmez **yükseltir**. Bu yüzden katman sırası kasıtlı —
yıldızların üstünde, metnin altında.

---

## 5. Ana sayfa: ekranda ne var

Yukarıdan aşağı:

1. **Kahraman** — derin sahne. Yıldız alanı, nebula, tarayıcı hüzme, gezegen
   kenarı (ufuk), vinyet; üstünde başlık, giriş paragrafı ve üç eylem. 320×480
   ölçüldü: **üç düğme de ilk ekranda**.
2. **İki akan bant** — üstteki ürün yetenekleri, alttaki adımlar; zıt yönlerde.
3. **Yetenekler** — dört eğilen kart, numaralı, gradyan zeminli.
4. **Nasıl çalışır** — ikinci derin bant; dört adım, altında gezegen kenarı
   kaydırmayla büyüyor.
5. **Fiyat** — sahne **susar**. Bir fiyatın okunduğu yer, dikkatin bölünmemesi
   gereken yerdir. Rakam plan kataloğundan gelir.
6. **SSS** — üç panel.
7. **Kapanış** — üçüncü derin bant, tek eylem.

Düzen `resources/css/site-home.css`'te. **Tek bir kırılma noktası yok**
(`HOME-FLUID-04`): her ölçü `clamp()` ya da `minmax(min(100%, …), 1fr)` — 320
pikselde alt sınır, geniş ekranda üst sınır, aradaki her genişlikte tanımlı.

---

## 6. Derecelendirme — sahne gizlenmez, sadeleşir

Üç derece, `:root[data-scene-tier]` olarak kök öğede:

| Derece | Yıldız | Tuval çözünürlüğü | Kapanan |
| --- | --- | --- | --- |
| `full` | 1.400 | ×1, dpr ≤ 2 | — |
| `reduced` | 640 | ×0,7, dpr ≤ 1,5 | tarayıcı hüzme |
| `minimal` | — | tuval hiç boyanmaz | hüzme, nebula ve bant hareketi |

`minimal`de bile sayfa bir **sahne** olarak kalır: gradyan zemin, panel
kalınlığı, ışıyan kenar, gezegen kenarı yerinde durur — yalnız kıpırdamaz.

Derece iki kaynaktan gelir ve ikisi de gerekli:

- **Açılış tahmini** — çekirdek sayısı, cihaz belleği, boyanacak piksel.
  Hiçbir ipucu yoksa `full` değil `reduced` seçilir: bilinmeyen bir cihazı
  güçlü saymak, hatayı en zayıf cihaza ödetmek olurdu.
- **Çalışırken ölçüm** — kare süresi bütçeyi arka arkaya 30 kare aşarsa derece
  bir basamak iner. Merdiven **tek yönlüdür**: yükseltmek, cihaz bir an
  rahatladığında sahneyi ağırlaştırır ve ısınınca yine düşürür; kullanıcı
  sahnenin sürekli kılık değiştirdiğini görür. Daha az akıllı ama **kararlı**.

Merdivenin gerçekten indiği ölçüldü (`--concurrency` ile çekirdek sayısı
taklidi):

| Taklit | Sonuç |
| --- | --- |
| 2 çekirdek | derece `minimal`, tuval boyanmıyor, içerik **tam** |
| 4 çekirdek | derece `reduced`, WebGL açık, 640 yıldız |
| gerçek makine | derece `full` |

---

## 7. Kapılar

| Kapı | Ne ölçüyor |
| --- | --- |
| `SceneContractTest` (SAHNE-B1…B6) | Dağarcık eksiksiz mi; tam kanamalı bant kırpılan kabın içinde mi; hareket doğru kapının arkasında mı; motor React ve kurumsal jeton adı taşıyor mu; kabuk motoru yüklüyor mu; bütçe ve gerekçesi yazılı mı. |
| `resources/js/site/scene/scene.test.ts` (SAHNE-01…07, 22 test) | Hareket kapısı, derece merdiveni, kare süresi yöneticisi, giriş kipi ayrımı, temizlik, palet köprüsü. |
| `scripts/scene-budget-gate --fail` | `SITE-SCENE-BUDGET-01` — bayt tavanı. |
| `scripts/scene-perf-gate` | Kare süresi (p95), derece, azaltılmış hareket davranışı. Gerçek Chrome, CPU kısmalı. |
| `scripts/mobile-ux-audit` | Yatay taşma, kırpılma, dokunma hedefi, yoğunluk — 320'den 1920'ye. |

**jsdom bir kare bile çizmez.** "Sahne akıcı mı", "320 pikselde taşıyor mu"
soruları vitest'te SORULAMAZ ve bu paket onları sorduğunu iddia etmiyor;
cevapları son iki satırda, gerçek Chrome'da.

### Ölçülen kare süreleri (2026-09-08)

| Görüntü alanı | CPU kısma | p50 | p95 | maks | uzun kare | derece |
| --- | --- | --- | --- | --- | --- | --- |
| 320×480 | ×4 | 16,7 ms | 16,7 ms | 16,8 ms | 0 | full |
| 320×568 | ×6 | 16,7 ms | 16,7 ms | 16,8 ms | 0 | full |
| 1280×800 | ×4 | 16,7 ms | 16,8 ms | 16,8 ms | 0 | full |
| 1920×1080 | ×4 | 16,7 ms | 16,7 ms | 16,8 ms | 0 | full |
| 1920×1080 | ×1 | 16,7 ms | 16,8 ms | 16,8 ms | 0 | full |

Ölçüm **kaydırırken** yapılıyor (gidiş-dönüş): duran bir sayfada ölçülen kare
süresi sahnenin en ucuz hâlidir ve hiçbir şey kanıtlamaz. Başsız Chrome
yazılım rasterleştirici kullanabilir; bu ölçümü gerçek bir cihazdan **kötü**
yapar, iyi değil — yani kapı iyimser değil kötümserdir.

### Ölçülen düzen (2026-09-08, `scripts/mobile-ux-audit`)

18 kurumsal sayfa, dört genişlik (320 · 1280 · 1920 · 320-RTL): **yatay taşma
yok, kırpılan etkileşimli öğe yok.** Ana sayfada kullanılabilir genişlik 320
pikselde **308 piksel (%96,3)** — eşik %72.

Kalan üç `small-target` bulgusu paketten ÖNCE de vardı ve cümle içindeki
bağlantılardır (`Contact us`, `Ask us`, `Pricing`); WCAG 2.2 ölçüt 2.5.8 metin
akışındaki hedefleri bu ölçüden muaf tutar ve deponun kendi kuralı da öyle
diyor (`site-shell.css`, `.site-action` gerekçesi).

---

## 8. Sonraki ajan için: nasıl kullanılır

Yeni bir kurumsal sayfaya sahne eklemek için **motoru düzenlemeye gerek yok**:

```html
<section class="site-stage site-deep" data-scene-progress>
    <div class="site-stage-layer">
        <canvas class="scene-canvas" data-scene="field" aria-hidden="true"></canvas>
    </div>
    <div class="site-stage-layer scene-plane" data-plane="far" aria-hidden="true">
        <span class="scene-nebula"></span>
    </div>
    <div class="site-stage-layer" data-depth="front" aria-hidden="true">
        <span class="scene-vignette"></span>
    </div>

    <div class="site-stage-content site-measure-page">…</div>
</section>
```

**Pazarlığa kapalı üç kural:**

1. Dekoratif her katman `.site-stage` **içinde** ve `aria-hidden`.
2. `.site-bleed` yalnız bir `.site-stage` torununda (kapı: SAHNE-B2).
3. Hareket başlatan her CSS bildirimi
   `@media (prefers-reduced-motion: no-preference)` içinde (kapı: SAHNE-B3).

---

## 9. Döngü 2 için eksik bırakılanlar

Bu bölüm **kasten** yazıldı: bir sonraki döngü buradan devam edecek.

1. **Sahne yalnız ana sayfada.** `/pricing`, `/about`, `/contact`, `/help`,
   yasal sayfalar ve kütükten çizilen 386 kurumsal sayfa bugünkü hâlleriyle
   duruyor. Motor hazır ve öznitelikle çağrılıyor; iş, her sayfanın kendi
   kompozisyonuna karar vermek.
2. **Fiyat bölümü sahnesiz ve sade.** Bu Döngü 1'de bilinçli bir tercihti
   (dikkat bölünmesin) ama bugünkü hâli "sade" değil **fakir**: partial'ın
   kutuları kurumsal yüzey dilini (`site-panel`) hiç kullanmıyor. Partial
   `/pricing` ile paylaşıldığı için değiştirmek iki sayfayı birden etkiler ve
   bu paketin kapsamı dışındaydı.
3. **Üst çubuk 320'de 128 piksel yüksek.** Bu, ilk ekranın dörtte biri.
   Kahraman ona göre daraltıldı ama asıl çözüm kabuğun kendisinde.
4. **İkinci tuval ölçülmedi.** İkinci bir WebGL bağlamının maliyeti
   *tahmin edildi* (§2), ölçülmedi. Döngü 2 isterse ölçsün: `scene-perf-gate`
   bunun için hazır.
5. **Yıldız alanı tek bir görsel fikir.** Uzay şirketi dağarcığında henüz
   olmayanlar: yörünge çizgileri, veri akış hatları, ızgara/tel-kafes zemin,
   sayfa içi yön değiştiren kamera, bölümler arası gerçek **morph** geçişi.
   Sahibin "sağlı sollu hareket eden landing page" istediği bugün iki akan
   şeritle karşılanıyor; bölümlerin kendisi yatay hareket etmiyor.
6. **Dokunmada derinlik pasif.** İmleç yok, o yüzden kamera yalnız kaydırmaya
   tepki veriyor. Cihaz yönelimi (`deviceorientation`) bir seçenek ama izin
   istiyor ve ölçülmedi.
7. **Ekran görüntüsü kapısı yok.** Sahne sabit tohumla çiziliyor, yani görsel
   bir gerileme testi mümkün — kurulmadı.
8. **`prefers-contrast: more` ve `forced-colors` görsel olarak doğrulanmadı.**
   Kurallar yazıldı ve CSS'te duruyor; ekran görüntüsüyle bakılmadı.
9. **iOS Safari doğrulanmadı.** Bütün ölçümler Chrome'da. `svh`, `overflow:
   clip` ve WebGL bağlam sınırları orada farklı davranabilir.
10. **Sahne bir kez düştüğünde geri kalkmıyor.** Merdiven tek yönlü ve bu
    bilinçli; ama sekme uzun süre arka planda kaldıktan sonra dönen bir
    kullanıcı, ısınma yüzünden inmiş bir dereceyle kalır. Karar Döngü 2'nin.
