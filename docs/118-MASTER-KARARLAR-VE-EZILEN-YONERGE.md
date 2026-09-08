# 118 — Master kararlar: yönergenin hangi maddesi ezildi ve neden

**Sahibin kararı (2026-09-05):** *"Bu dosyaları, bugün verdiğimiz kararları
master (değişmez) karar sayarak ezeceğiz, güncelleyeceğiz. Kararlarımızı bu
dosyaların değiştirmesine izin vermeyeceğiz."*

**Öncelik sırası, çelişkide:**

```
bugünün master kararları (bu belge)
  > docs/105 (kurumsal site kararları)
  > docs/119 (yönerge girdisi)  ·  docs/106 (site haritası girdisi)
```

Girdi dosyaları **düzenlenmez**. Bir girdiyi sonradan düzeltmek, kararın hangi
girdiden çıktığını gizlemek olurdu; ezilen madde burada adıyla yazılır.

## 0. Önce doğrusu: yönerge unutulmadı

Sahip haklı olarak sordu. Ölçüm:

| Girdi | Depodaki karşılığı | Durum |
| --- | --- | --- |
| `zabuno-com-tam-site-haritasi.md` | `docs/106` (kaynak) | Kopyalanmış |
| `zabuno-frontend-claude-uygulama-yonergesi.md` | `docs/119` (kaynak) | **Bugün kopyalandı** — eksikti |
| Yönergenin kararları | `docs/105` | Uygulanmış |
| Sayfa kütüğü | `content_pages`, **386 kayıt** | Çalışıyor |
| PageGate, hazırlanıyor sayfası, çeviri kilidi | Kod + testler | Çalışıyor |

Yani yönerge atıl kalmadı; kararlara dönüştü ve uygulandı. Eksik olan tek şey
girdinin kendisinin depoya alınmamış olmasıydı — ikiz girdilerden yalnız biri
kopyalanmıştı. Bu bugün kapandı.

## 1. Ezilen maddeler

### E1 — Sıralama: "masaüstü, tablet, mobil" → **dar ekran TABAN**

**Yönerge §20 (Tasarım):** *"Desktop, tablet ve mobil kontrol edildi."*

**Master karar:** global `CLAUDE.md` → `TOUCH-FIRST-INTERFACE`. Dar ekran
tabandır, geniş ekran ilerleyici zenginleştirmedir. Bir sayfanın tanımlı
bitişi, **en dar desteklenen genişlikte ölçülmüş** olmasını içerir.

**Neden ezildi:** sıralama bir üslup tercihi değil, kusur kaynağı. Aynı gün
kabuk düzeni üç kez üst üste sahibin ekranında kırıldı ve üçünde de bütün
testler yeşildi (`docs/117` §0). Geniş ekranda alınmış her karar dar ekranda
borç olarak geri döndü.

**Zorlayıcı karşılığı:** `scripts/mobile-ux-audit` — gerçek Chrome, 320×568,
her hikâye. Yeni ihlal kapıyı kırar.

### E2 — Giriş kipi ayrımı: yönergede **yok**, artık zorunlu

**Yönergede karşılığı yok.** §18 yalnız "minimum WCAG 2.2 AA" diyor.

**Master karar:** dokunma ile işaretleyici ayrı etkileşim modelleridir. `hover`
ile anlatılan hiçbir bilgi dokunmalı cihazda var olmaz; parmak ekranı kapatır;
uzun basma ve kaydırma dokunmada ucuz, işaretleyicide pahalıdır. Etkileşim
modeli farklıysa **ayrı kod yolu** yazılır.

**Neden eklendi:** "tek kod, medya sorgusuyla gizle" çözüm değil — gizlenen şey
yine indirilir, yine odaklanılabilir, yine bakım ister.

### E3 — Yoğunluk: **büyük hedef + SIKI boşluk**

**Yönergede karşılığı yok.** §18 yalnız "rastgele spacing kullanılmamalıdır"
diyor.

**Master karar (sahibin ifadesiyle):** *"buton boyutları büyük olması
dokunabilirlik açısından güzel ama grid gap ve grid margin mobil cihazlarda
fazla çalıyor ekrandan. Data-sensitive bir yaklaşımla planlarsan daha efektif
olacaktır mobile için. Desktop için değil."*

Dokunma hedefi ≥44 px kalır; **boşluk ölçeği dar ekranda daralır**. İç içe
kapların dolgusu birikmez. Font küçülmez, hedef küçülmez; küçülen tek şey ölü
alandır.

**Ölçülmüş sonuç:** boşluk ölçeğinin alt sınırları masaüstü ölçüsündeydi ve
320 pikselde zaten devredeydi — dar ekran taban değil, kırpılmış masaüstüydü.
Tabanlar indirildi, tavanlar korundu (`docs/117` M4).

### E4 — Ürün arayüzünün dili: **İngilizce**, kurumsal sitenin dili ayrı

**Yönerge §1 madde 1-3:** *"Ana ve kaynak dil Türkçedir… İngilizce içerik
yazma."*

**Master karar (2026-09-05):** *"Ben söylemedikçe tercüme çeviri yapma.
İngilizce kalsın."* `i18n.shipped_locales` yalnız `['en']` ve artık
`NegotiateLocale` **o listeyi** okuyor.

**Ayrım net ve ikisi birden doğru:**

| Yüzey | Dil kaynağı | Nasıl seçilir |
| --- | --- | --- |
| Ürün arayüzü (panel, misafir menüsü, kimlik) | `i18n.shipped_locales` = `['en']` | Tarayıcıyla pazarlık |
| Kurumsal site (`/tr/`, `/en/`) | Adresteki locale segmenti | Pazarlık **yok** |

Bu ayrım bugüne kadar **kazayla** doğruydu: şablon `lang`'i `$page->locale`'den
türetiyordu. Kazayla doğru olan bir şey bir gün kazayla yanlış olur; artık
`CORP-LOCALE-FROM-PATH-01` testi kilitliyor.

**Bu varsayım KAPANDI (2026-09-05, aynı gün).** Sahibin kararı: *"Ana dili
İngilizce, Türkçe opsiyonel"* — ve altyapı bugünden dokuz dili desteklemeli.
Ayrıntı, GILT karşılıkları, dil seçimi sinyal zinciri ve dil değiştirici
sözleşmesi `docs/120`de. Yönergenin "ana ve kaynak dil Türkçedir" maddesi
böylece tamamen ezildi: **kaynak dil İngilizcedir.**

Kütük, kabuk, şablonlar ve blok yapısı dilden bağımsız kaldı; içerik ayrı bir
katmanda yaşıyor. Bu yüzden karar aynı gün geldiğinde tek satır iş çöpe
gitmedi.

### E5 — Efekt kütüphaneleri: yönerge **yasaklıyordu**, sahip **istiyor**

**Yönerge §2.3:** *"React tabanlı SmoothUI, Animate UI, Magic UI, Aceternity ve
React Bits bileşenlerini projeye sokma"* — çalışan bir Blade sitesi varsa.

**Master karar (2026-09-05):** sahip GSAP animasyon deneyimi, parallax ve bu
kütüphanelerden MCP ile hareketli bileşenler istiyor.

**Ezildi, ama yönergenin gerekçesi ölçüldü ve bir kısmı korunuyor.** Gerekçe
gerçekti: aynı projede ikinci bir temel tasarım sistemi kurmak. Bugünkü kamu
sayfaları **hiç React yüklemiyor** (`docs/105` §1) ve bu bir başarı, kaybedilecek
bir şey.

**Uygulanacak biçim:**

1. **GSAP birinci sıradadır** ve çatıdan bağımsızdır. Sahibin istediği hareket
   kalitesini, sıfır-React bir yüzeye ikinci bir bileşen sistemi sokmadan verir.
2. Parallax ve arka plan hareketi **CSS + GSAP** ile yapılır.
3. React adacığı yalnız bir bileşen **gerçekten** etkileşim gerektirdiğinde
   açılır; süs için açılmaz.
4. MCP'den gelen her bileşen `docs/119` §17.4 kabul kapısından geçer: lisans,
   bağımlılık, jetonlara bağlama, ikon temizliği, klavye/ekran okuyucu,
   `prefers-reduced-motion`, paket etkisi, mobil ölçüm, manifest kaydı.
5. **Gerçek kapı ölçümdür:** `scripts/mobile-ux-audit` + paket bütçesi. Kapıyı
   geçmeyen efekt statik CSS varyantına düşer.
6. Sayfa başına en fazla bir baskın hareketli arka plan; aynı görüntü alanında
   ikinci bir WebGL yok (`docs/119` §17.5 korunuyor).

### E6 — İkon: **emoji yasak, Phosphor ilk** (2026-09-08'de DÜZELTİLDİ)

**Yönerge §1 madde 10:** *"İkon kullanılmayacaktır."*

**Bu belge 2026-09-05'te o maddeyi "korunuyor — yalnız kurumsal site için"
diye yazmıştı. YANLIŞ YAZILMIŞTI.** Sahibin düzeltmesi (2026-09-08):
*"hayır, Phosphor icon var, emoji yok. Kararı güncelle."*

**Yürürlükteki karar, İKİ YÜZEY İÇİN AYNI:**

> **Emoji yasak. Phosphor ilk.**

Panel `@phosphor-icons/react` kullanıyor. Kurumsal site React YÜKLEMEZ
(`docs/38` §16) ve bu bir kazanç, kaybedilecek bir şey; bu yüzden aynı ikon
ailesine **sunucu tarafından** erişir: `resources/views/components/phosphor.blade.php`
paketin `regular` ağırlığındaki yol verisini bire bir taşır ve
`ShellIconLanguageTest` (ICON-02) her glifi paketin kendi tanımıyla
karşılaştırır. Elle çizilmiş bir "benzeri" kapıyı kırar.

**Neden emoji ayrı bir yasak.** Emoji bir ikon değildir: işletim sistemine
göre başka çizilir, ekran okuyucuda uzun bir cümle olarak okunur, yazı tipi
yığınına bağlıdır ve marka jetonlarından hiç geçmez. ICON-01 kabuğun her
dosyasını tarar.

**Ne değişti kodda.** Üst çubuktaki açık/kapalı göstergesi bir "artı/eksi
geometrisi"ydi ve gerekçesi `header.blade.php` içine *"İKON YOK"* diye
yazılmıştı. O gerekçe bu düzeltmeyle birlikte kaldırıldı; yerine iki Phosphor
glifi (`list` / `x`) geldi ve hangisinin görüneceğini CSS `[open]` seçer —
betik gerekmiyor.

### E7 — Stok görsel kaynağı: **Unsplash değil**

**Master karar (2026-09-05):** *"unsplash değil, diğer stok foto sitelerinden
free lisanslı fotolar ekle."*

Kullanılabilir kaynaklar: Pexels, Pixabay, Openverse, Wikimedia Commons,
Burst, StockSnap, Kaboompics, Life of Pix, Picjumbo, Reshot ve muadilleri.

**Her görsel için kaydedilir:** kaynak, doğrudan adres, lisans adı, atıf
gerekliliği, indirme tarihi. Lisansı doğrulanamayan görsel **kullanılmaz** —
"muhtemelen serbest" bir lisans değildir.

### E8 — "Kabukta betik yok" kuralı: **gevşetildi** (2026-09-08)

**Eski kural** `header.blade.php` içinde yaşıyordu: kabuk JavaScript'e hiç
dokunmayacaktı. Gerekçesi gerçekti — kurumsal sitenin en çok okunduğu an,
betiklerin en çok engellendiği andır.

**Sahibin düzeltmesi:** *"gibi bir sınır koymak yanlış."*

**Yürürlükteki karar — taban HTML, tavan serbest:**

> Her gezinti hedefi SUNUCU HTML'inde `<a href>` olarak **bulunur**.
> JavaScript bunun **üstüne** serbestçe ekler.

Yani mega menü, arama, hareket ve efekt serbesttir; hiçbiri o tabanı silemez.
Ayrım, "betik yok" ile "betiksiz de çalışır" arasındaki farktır ve ikincisi
ölçülebilir: `SHELL-SINGLE-SOURCE-04` sayfayı betik gövdeleri atılmış hâlde
çizer ve her hedefi arar. Altbilginin pSEO katı da aynı kapıdan geçer
(`FOOTER-CONTENT-05`) — bir arama motoru için "betiksiz gövde" varsayılan
gövdedir.

**Bu, `docs/119` §17.5'i ve `prefers-reduced-motion` kuralını GEVŞETMEZ.**
Hareket hâlâ açıkça istenmiş olmadan doğmaz ve sayfa başına en fazla bir
baskın hareketli arka plan kuralı yerinde durur.

### E9 — Kurumsal sitenin bileşen kütüphanesi: **daisyUI** (2026-09-08)

**Sahibin kararı:** *"daisyUI kullan, baştan yarat… bu kararım kesin."*

Kurumsal kabuk daisyUI 5 üzerine yeniden yazıldı. Tema **jetonlardan türer**
(bir tek renk elle yazılmaz), sınıflar `dz-` önekiyle gelir ve panel
etkilenmez — panel `flowbite-react` + AEP üzerinde kalır (`docs/102`).
Ayrıntı, ölçüm ve sonraki ajanlara talimat: **`docs/136`**.

**Bu maddenin "marka jetonları" kısmı aynı gün akşam E10 ile ezildi**; daisyUI
kararının kendisi kesindir ve durmaktadır.

### E10 — Kurumsal sitenin RENK KAYNAĞI: panelden AYRILDI (2026-09-08 akşamı)

**Ezilen karar:** E9 ve `docs/136` §3 — *"tema marka jetonlarından
(`--aep-*`) türer"*.

**Sahibin kararı:** *"temanın mevcut marka jetonlarını sikerim, yeter
artık."* Ve tekrarlanan hedef: *"bir uzay teknolojileri şirketi gibi, abartı
dursun, görünsün, hissettirsin."*

**Neden ezildi — ölçüldü.** Kural uygulandı, daisyUI kuruldu, kapı yeşile
döndü ve **ekranda hiçbir şey değişmedi**. Sebep kimsenin hatası değil:
panelin jetonları sekiz saat bakılan bir ekran için seçilmişti (sakin, düz,
gölgesiz — `docs/102` §1). Kurumsal sayfanın işi tam tersidir; bir kez
bakılır ve hatırlanması gerekir. Aynı jetonu ikisine vermek ikisini de
ortalamaktı.

**Yürürlükteki karar:**

> Kurumsal sitenin renk kaynağı `--zc-*` jetonlarıdır ve tek tanımı
> `resources/css/site-identity.css`. Panelin `--aep-*` jetonlarına hiç
> bakmaz. Panel değişmez.

Panelle kalan tek kasıtlı bağ marka altınıdır — ve o da artık bir düğme
rengi değil, bir vurgu: kurumsal birincil düğme elektrik moru oldu, çünkü
sarının üstündeki beyaz yazı **1,73:1** ölçülmüştü (WCAG asgarisi 4,5) ve
yeni değeri **5,84:1**.

**Ne DEĞİŞMEDİ:** E1 (dar ekran taban), E3 (büyük hedef + sıkı boşluk), E6
(emoji yasak, Phosphor ilk), E8 (taban HTML, tavan serbest), daisyUI kararı,
`dz-` öneki, hazır temaların kapalılığı ve panelin dokunulmazlığı.

**Zorlayıcı karşılığı:** `CorporateIdentityContrastTest` (KIMLIK-01/02/03) ve
`CorporateIdentityScopeTest` (KIMLIK-04/05/06/07). Ayrıntı, palet, gerekçe ve
bütün ölçümler: **`docs/145`**.

### E11 — "Sıfır JavaScript kütüphanesi": **kalktı**, ama ölçüm kütüphaneyi seçmedi (2026-09-08 gecesi)

**Ezilen kısıt:** kurumsal sitede React yok, efekt kütüphanesi yok, betik
neredeyse hiç yok.

**Sahibin kararı:** *"Kurallar mı engelliyor? Yeni kurallar yaz. React mı
lazım? Ekle. Ne lazım? Ekle, çöz, yap."* Ve hedef: *"Bir uzay teknolojileri
şirketi gibi, abartı dursun, görünsün, hissettirsin."*

**Kısıt kalktı. Sonra ÖLÇÜLDÜ ve kütüphane yine alınmadı** — bu bir kural
değil, bir ölçüm sonucu:

| Aday | gzip (npm dist, `gzip -9`) |
| --- | --- |
| GSAP çekirdek | 28.314 bayt |
| GSAP + ScrollTrigger | 46.339 bayt |
| motion | 46.644 bayt |
| three.js | 86.569 bayt |
| **elle yazılan sahne motoru** | **4.417 bayt** |

Üçünün de taşıdığı şeyin çoğu bu sahnede kullanılmıyor (sahne grafiği,
malzeme sistemi, zaman çizelgesi motoru). Asıl mesele ağırlık bile değil:
**derecelendirme merdiveni**. Düşük güçlü bir telefonda hangi katmanın
söneceğine bir kütüphane karar veremez; o karar ürünün kendi kodunda olmalı.

**React de alınmadı** ve gerekçesi E5 madde 3'ün kendisidir: *"React adacığı
yalnız bir bileşen GERÇEKTEN etkileşim gerektirdiğinde açılır; süs için
açılmaz."* Sahnenin etkileşim durumu yok — tuval, kaydırma, imleç.

**Kısıt geri konmadı.** Yarın bir bileşen gerçekten React isterse kapı açık;
bugün ölçüm onu istemedi. Ayrıntı ve bütün sayılar: **`docs/146`**.

**Zorlayıcı karşılığı:** `SceneContractTest` (SAHNE-B4: motor React
yüklemez), `scripts/scene-budget-gate` (SITE-SCENE-BUDGET-01).

### E12 — Ağırlık bütçesi: **kaldırılmadı, YÜKSELTİLDİ** (2026-09-08 gecesi)

**Ezilen kısıt:** kurumsal sayfanın JavaScript'i sıfır, toplam ağırlığı ~32 KB.

**Sahibin kararı:** sahne zorunlu. O tavan sahneyi taşıyamıyordu.

**Yeni tavan ve gerekçesi `scripts/scene-budget.json` içinde yaşıyor**, bu
belgede değil — bir sayıyı iki yere yazmak, ilk ayrışmada hangisinin doğru
olduğunu belirsiz yapar:

- kurumsal betik ≤ **6.144 bayt gzip** (ölçüm: 4.417)
- kurumsal stil ≤ **40.960 bayt gzip** (ölçüm: 34.147; paket öncesi 30.410,
  yani sahnenin payı **3.737 bayt**)

**Neden bütçe KALDIRILMADI:** kaldırılmış bir bütçe, bir gün "bir kütüphane
daha ekleyelim" denildiğinde kimsenin fark etmeyeceği bir yerdir. Yükseltilmiş
bir bütçe ise bir soru sorar: bu bayt neyin karşılığında geldi?

**Zorlayıcı karşılığı:** `scripts/scene-budget-gate --fail` ve
`SceneContractTest` SAHNE-B6 (tavan var ve GEREKÇESİ yazılı).

### E13 — "Sayfa başına en fazla bir baskın hareketli arka plan": **kalktı** (2026-09-08 gecesi)

**Ezilen madde:** E5 madde 6 ve `docs/119` §17.5 — *"Sayfa başına en fazla bir
baskın hareketli arka plan; aynı görüntü alanında ikinci bir WebGL yok."*

**Sahibin kararı:** kural kalktı.

**Ama ikinci WebGL yine AÇILMADI** — ve bu ayrım önemli. Kuralın iki yarısı
vardı; biri ezildi, öteki ölçümle doğrulandı:

- **Ezildi:** ana sayfada bugün ÜÇ hareketli sahne var (kahraman, "nasıl
  çalışır" bandı, kapanış bandı) ve iki akan şerit. Sayfa bir vitrindir;
  tek bir bant onu taşıyamıyordu.
- **Duruyor, ama artık bir kural değil bir ÖLÇÜM sonucu:** tuval yalnız
  kahramanda. İkinci bir WebGL bağlamının vereceği derinliği ikinci bant
  saf CSS ile zaten alıyor; buna karşılık ikinci bağlam, düşük güçlü bir
  telefonda kare süresini iki katına çıkarır.

**Yerine gelen zorlayıcı karşılık, sayının kendisidir:** `scripts/scene-perf-gate`
kare süresini gerçek Chrome'da, CPU kısmalı olarak ölçer. Kaç sahne olduğu
artık bir tartışma değil, bir bütçe: p95 kare süresi 22 ms'yi geçerse kapı
kırılır. Ölçüm (2026-09-08): 320×480'den 1920×1080'e, CPU ×4 kısmalı, **p95 =
16,7–16,8 ms** — yani 60 kare/saniye, uzun kare yok.

### E14 — `mobile-ux-audit` yoğunluk ölçümü: **süs artık içerik sayılmıyor** (2026-09-08 gecesi)

Bu bir yönerge ezmesi değil, bir ÖLÇÜM ARACININ düzeltmesi; kaydı burada
duruyor çünkü bir kapının davranışını değiştiriyor.

**Arıza:** akan bant döngünün dikişsiz olması için iki özdeş kopya taşır ve
ikincisi görüntü alanının dışındadır. Bant `overflow: clip` ile kırpılıyor —
ziyaretçi hiçbir zaman yatay kaydırmıyor — ama `getBoundingClientRect()`
kırpılmayı bilmez. Araç "içerik 320 piksel yerine 2.764 piksel kullanıyor"
diyordu. Bu bir yanlış alarm DEĞİL, daha kötüsüydü: oran eşiğin (%72) çok
üstüne çıktığı için **yoğunluk kuralı o sayfada sessizce devre dışı
kalıyordu.**

**Düzeltme:** yoğunluk ölçümü artık `aria-hidden="true"` bir alt ağacın
içindeki metni saymıyor. Kapsam DAR: yalnız bu ölçüm. Yatay taşma, kırpılma ve
dokunma hedefi kuralları dekoratif bir öğeyi eskisi gibi görür — süs sayfayı
yatay kaydırıyorsa bu yine bir kusurdur.

**Düzeltme sonrası ölçüm:** ana sayfada kullanılabilir genişlik 320 pikselde
**308 piksel (%96,3)**.

## 2. Değişmeden korunan kararlar

Yönergenin şu maddeleri bugünkü kararlarla çelişmiyor ve aynen geçerli:

- Tek canonical URL, kopya landing page yok, doorway yok.
- Header/footer/mega menü yeni sayfa yaratmaz; aynı canonical'e bağlanır.
- Sayfa kütüğü + durum makinesi + merkezî `PageGate`; yüzlerce kopya sayfa
  bileşeni üretilmez.
- Yayınlanmamış sayfa production'da **404 + noindex**; soft-404 üretilmez.
- Çeviri kilidi dört katmanda kapalı; `ÇEVİRİLERE BAŞLA` denmeden açılmaz.
- Black-hat, negative, parasite SEO ve yanıltıcı structured data yasak.
- Structured data görünmeyen bilgi taşımaz; sahte rating üretilmez.
- Ürünün gerçekten desteklemediği özellik veya entegrasyon yayınlanmaz.
- Programmatic sayfa gerçek veri taşımak zorundadır; şehir adı değiştirilmiş
  kopya yayınlanmaz.
- Sayfa yayın kapısı (`docs/119` §20) — E1 ile güncellenmiş sıralamayla.

## 3. Bu belgenin kendi gerekçe süresi

`docs/109` §8.6. Özellikle E4'ün açık varsayımı (kurumsal sitenin ilk içerik
dili) sahibin kararıyla kapanır ve o gün bu belge güncellenir. E5'in biçimi de
ölçüme bağlıdır: GSAP ile istenen kalite alınamıyorsa React adacığı kararı
yeniden tartışılır — ama ölçüm sonrası, tahminle değil.

E5'in bu ölçümü 2026-09-08 gecesi YAPILDI ve sonucu E11'dedir: kütüphane
kısıtı kalktı, ölçüm yine kütüphane seçmedi. E11–E14 bir "döngü 1" paketinin
kararlarıdır; Döngü 2 onları eleştirecek ve gerekirse ezecek. Ezerse, kaydı
yine buraya yazılır.
