# 105 — Kurumsal site, URL politikası ve yayın sistemi: durum raporu ve plan

**Girdiler:** `zabuno-com-tam-site-haritasi.md` (414 canonical yol) ve
`zabuno-frontend-claude-uygulama-yonergesi.md` (ChatGPT'nin ürettiği uygulama
yönergesi), sahibin 2026-09-04 tarihli üç talebiyle birlikte:

1. Kenar çubuğu her sayfada SOLDAN açılmalı; kabuk standardı bu olsun.
2. Yönergedeki planı uygula, üç döngü tekrarla, test-first ilerle.
3. Herkese açık menü adresi şu biçimde olsun:
   `restoran/pasa-doner/menu/aB245iKj/#item=101` — başındaki segment dile göre
   değişsin (`restoran` / `restaurant`).

---

## 1. Mevcut durum (ölçülmüş, varsayılmamış)

Bu bölüm depo taranarak çıkarıldı; yönergedeki varsayımların hangilerinin bu
depoda karşılığı olduğunu belirler.

| Konu | Yönergenin varsayımı | Depodaki gerçek |
| --- | --- | --- |
| Public frontend | Yeni Astro uygulaması kurulacak | **Blade zaten çalışıyor**: `/`, `/pricing`, `/help`, `/contact`, `/terms`, `/privacy`, `/kvkk` sunucuda render ediliyor, React paketi hiç yüklenmiyor |
| Depo yapısı | `apps/backend` + `apps/web` monorepo | Tek Laravel uygulaması; onion mimari, `catalog/` tasarım sistemi, guard testleri |
| Çeviri | Yeni `content_page_localizations` tabloları | **PO/MO gettext boru hattı zaten var**: `lang/po` → `lang/mo` → JSON projeksiyonları, `TranslationPort`, `MoFileTranslator`, `I18N-SSR-RATCHET` kapısı |
| Dil URL'i | `/tr/` ve `/en/` dizinleri | Dizin YOK; dil `Accept-Language`, çerez ve `?lang=` ile çözülüyor |
| hreflang | Kurulacak | Depoda tek bir `hreflang` yok |
| Sitemap | Türe ve dile bölünmüş index | Tek `/sitemap.xml`; sabit yollar elle yazılmış (`/pricing`, `/help`, `/contact` **eksik**) |
| Menü adresi | Kapsam dışı sayılmış | `/q/{token}` → 302 → `/menu/{token}` (noindex) ve canonical `/menu/{key}/{slug?}` |
| İşletme slug'ı | Hiç konuşulmamış | `locations` tablosunda slug **yok**; `brands.slug` global benzersiz ve çalışma alanı başına tek marka |
| Rezerve kelimeler | Konuşulmamış | `config/url-policy.php` içinde gerçek bir `reserved_slugs` listesi var |

---

## 2. ChatGPT'nin planı — neyi doğru gördü, neyi eksik düşündü

### 2.1 Doğru ve korunacak kararlar

- **70 SEO etiketini 8 sisteme indirmesi.** "AEO, GEO, LLMO, AIO, AISO" için 70
  ayrı onay kutusu değil; crawl/render, on-page/entity, cevap sistemleri,
  programatik, uluslararası, medya, dağıtım ve yasaklı yöntemler olarak
  gruplaması doğrudur ve aynen korunuyor.
- **Yayınlanmamış yüzlerce sayfaya `200` ile aynı "hazırlanıyor" metnini
  vermemek.** Bu soft-404 ve ince içerik üretir; `404 + noindex,follow` kararı
  doğrudur.
- **Çeviri kilidinin dört katmanlı olması** (config, servis, kuyruk, panel).
- **Alan bazında Türkçe fallback** ve **kısmen çevrilmiş locale sayfasının
  noindex kalması, hreflang alternatifi ilan edilmemesi.**
- **Tek canonical, kopya landing page yasağı, black-hat yasağı.**

### 2.2 Eksik düşündükleri

**(1) Ana teknoloji kararını yanlış dalda verdi.** Yönergenin kendi §2.3'ü
"çalışan bir Blade public site varsa Astro açma" diyor — ve bu depoda o site
var. ChatGPT depoyu görmediği için birincil önerisini Astro yaptı. Astro'ya
geçmek bu depoda üç şeyi çatallar: tasarım sistemi (`catalog/` katmanları ve
katman guard'ları), i18n boru hattı (PO/MO) ve CI kapıları. `docs/36` bir kez
yaşanmış körlüğü kaydediyor: çalışan bir sistemin yanı başında ikincisini
kurmak. **Karar: Blade + Tailwind + Alpine kalıyor.**

**(2) İkinci bir çeviri sistemi icat etti.** `content_page_localizations`,
`translation_jobs`, `translation_glossary` tabloları, PO/MO boru hattının
yaptığı işi ikinci kez yapar. İki çeviri sistemi, bir gün iki farklı cümle
gösterir. **Karar: arayüz metni PO/MO'da kalır; yalnız EDİTORYAL sayfa içeriği
(uzun metin, bloklar) veritabanında dile göre saklanır.** İkisinin sınırı
yazılı olmalı.

**(3) Kendi CI listesiyle kendi 404 kararı çelişiyor.** "Broken link scan" CI
adımı istiyor; ama yayınlanmamış sayfalar 404 dönecek. Menüde/altbilgide/iç
bağlantıda duran her yayınlanmamış sayfa, kendi CI'ını kırar. **Eksik kural:
yayınlanmamış sayfa hiçbir yerden iç bağlantı almaz** — `navigation_visibility`
tek başına yetmez, içerik içi bağlantılar da kapıdan geçmelidir.

**(4) Yaşayan URL'lerin göçünü hiç planlamamış.** Bugün `/pricing` yayında,
`/sitemap.xml` içinde ve robots.txt'te. `/tr/fiyatlandirma/` yapmak; 301
zinciri, sitemap, canonical host politikası ve rezerve kelime listesiyle
birlikte planlanmak zorunda. Planda "redirects" tablosu var ama **mevcut canlı
adreslerin göç listesi yok.**

**(5) Kiracı adresini kapsam dışı bıraktı — oysa sahibin asıl talebi o.**
Sahibin istediği `restoran/pasa-doner/menu/aB245iKj/` adresi bir kurumsal sayfa
değil, bir KİRACI adresidir. Yönerge bunu hiç konuşmuyor; dolayısıyla işletme
slug'ının veri modeli, benzersizliği, rezerve kelimelerle çakışması ve eski
adreslerden 301'i planda yok. Bu belge onu §4'te tanımlıyor.

**(6) `#item=101` fragment'i bir adres değildir.** Fragment sunucuya hiç
ulaşmaz: indekslenmez, ayrı bir sayfa olarak ölçülemez, paylaşıldığında hangi
ürün olduğu sunucu tarafından bilinemez. Bu, deponun kendi kilitli kuralıyla da
çelişir ("ekran adresi asla fragment olamaz"). **Karar: ürün gerçek bir yol
segmenti olur; `#item=` çalışmaya devam eder ve istemci onu sessizce gerçek
adrese yükseltir.** Ayrıntı §4.3'te.

**(7) İkon yasağı ile deponun kendi kuralı çelişiyor.** Yönerge "ikon
kullanılmayacaktır" diyor; deponun kuralı "emoji yasak, önce Phosphor ikonları".
**Karar: yasak yalnız KURUMSAL SİTE için geçerli; yönetim paneli Phosphor
kullanmaya devam eder.** İki yüzeyin görsel dili zaten farklıdır.

**(8) Ölçüm hiç yok.** Sahibin kilitli kuralı: her şey kiracı bazında
ölçülebilmeli (GA4/Metrica/GTM/dataLayer). Kurumsal sitenin ölçüm sözleşmesi
planda yok; sayfa görüntüleme, form gönderimi ve CTA tıklaması aynı sözleşmeye
bağlanmalı.

**(9) 414 sayfayı KİMİN yazacağı yok.** Plan "her sayfa tamamlandığında durumu
ilerlet" diyor ama içerik üretim hattı yok. Tek kişilik, kod bilmeyen bir sahip
için gerçek darboğaz budur. İçerik taslağı üretmek ÇEVİRİ DEĞİLDİR ve çeviri
kilidine tabi değildir; bu ayrım yazılı olmalı.

**(10) CI bütçesi yok.** 13 ayrı CI adımı (Lighthouse CI, axe, Playwright,
schema doğrulama, broken link, duplicate scan) öneriyor. Bu deponun CI'ı bugün
tek işte ~7 dakika. Hepsini bir anda eklemek CI'ı kullanılamaz hâle getirir;
adımlar kademeli ve bütçeli eklenmeli.

**(11) `x-default`'un ne olacağını söylemiyor.** İngilizce tamamlanana kadar
`x-default` Türkçe canonical olmalıdır; plan bunu boş bırakıyor.

---

## 3. Uygulanacak mimari (bu depoya uyarlanmış)

```text
Laravel (tek uygulama)
  -> content_pages registry        (414 canonical yol, tek kayıt/tek yol)
  -> PageGate                      (durum -> HTTP + robots + sitemap + menü)
  -> Blade şablonları              (sayfa türü başına bir şablon)
  -> UnderConstruction bileşeni    (tek örnek, kopyalanmaz)
  -> PO/MO                         (arayüz metni)
  -> içerik yerelleştirmesi        (editoryal metin, kilitli)
  -> URL politikası                (canonical, 301, rezerve kelime)
  -> sitemap + robots + hreflang
```

Alpine.js yalnız gerçekten etkileşim gereken yerde. React bu yüzeye
sokulmayacak. SmoothUI/Aceternity/Magic UI React bileşenleridir ve bu karara
göre kurumsal siteye **girmiyor**; hareket ihtiyacı CSS ve `prefers-reduced-motion`
uyumlu küçük geçişlerle karşılanacak.

---

## 4. URL politikası

### 4.1 Kurumsal site

```text
/tr/urun/qr-menu/
/en/product/qr-menu/
```

- Dil dizini açık; her locale kendi canonical'ına sahip.
- Türkçe tamamlanana kadar `/en/` sayfaları `noindex,follow` ve sitemap dışı.
- `x-default` → Türkçe canonical.
- Mevcut `/pricing`, `/help`, `/contact`, `/terms`, `/privacy`, `/kvkk`
  adresleri tek atımlı 301 ile yeni adreslerine taşınır; zincir kurulmaz.

### 4.2 Kiracı menü adresi (sahibin talebi)

```text
/restoran/pasa-doner/menu/ab12cd34ef/
/restaurant/pasa-doner/menu/ab12cd34ef/
```

| Segment | Nedir | Neden |
| --- | --- | --- |
| `restoran` / `restaurant` | İşletme türünün dile göre yazılmış hâli | İnsanın okuduğunda ne olduğunu anladığı tek segment; arama motoru ve dil modeli için de varlık ipucu |
| `pasa-doner` | İşletmenin slug'ı | Pazarlamada söylenebilir, karta yazılabilir, hatırlanabilir |
| `menu` | Sabit | Aynı işletme altında ileride başka kaynaklar açılabilir |
| `ab12cd34ef` | Menünün kalıcı anahtarı | Ad değişse bile adres ölmez; anahtar sabittir |

**Anahtar biçimi küçük harf kalıyor.** Sahibin örneği `aB245iKj` karışık
harfliydi. Karışık harfli anahtar üç sorun üretir: telefonda sözlü olarak
aktarılamaz ("büyük B, küçük i"), URL'ler büyük/küçük harfe duyarlı olduğu için
`/AB245/` ile `/ab245/` iki ayrı sayfa olur ve kopya içerik doğurur, ve mevcut
basılı kodların anahtarları zaten küçük harflidir. **Bu yüzden `[a-z0-9]{10}`
korunuyor** — deponun bugünkü biçimi.

### 4.3 Ürün (yemek) adresi

Sahibin istediği `#item=101` fragment'i **çalışmaya devam eder** ama canonical
adres bir yol segmentidir:

```text
/restoran/pasa-doner/menu/ab12cd34ef/urun/101-adana-kebap
```

Sebep: fragment sunucuya hiç ulaşmaz. `#item=101` ile paylaşılan bir bağlantı
arama motorunda ayrı bir sayfa değildir, ölçümde ayrı bir görüntüleme değildir
ve yapay zekâ sistemlerine "bu bir ürün sayfası" demez. Yol segmenti üçünü de
yapar. Eski biçimi kıran bir şey yok: sayfa açılırken `#item=101` okunur, o
ürüne kaydırılır ve adres sessizce canonical hâline yükseltilir.

### 4.4 Rezerve kelimeler

`restoran`, `restaurant`, `menu`, `urun`, `product` ve dil kodları
`config/url-policy.php` rezerve listesine girer: hiçbir işletme bu slug'ı
alamaz, yoksa `/restoran/menu/...` gibi çözülemeyen adresler doğar.

---

## 5. Üç döngü

| # | Paket | Kapsam | Durum |
| --- | --- | --- | --- |
| 1 | FF-115 | Kabuk standardı: gezinme çekmecesi her yerde SOLDAN. Bu belge. | ✅ |
| 2 | FF-116 | Kiracı menü adresi: dile göre işletme türü öneki, işletme slug'ı, ürün yolu, eski adreslerden 301, canonical/sitemap | ✅ |
| 3 | FF-117 | Sayfa registry + PageGate + "Zabuno Service Pass" hazırlanıyor bileşeni + çeviri kilidi iskeleti (386 yol seed'lenir, hiçbiri yayına açılmaz) | ✅ |

Üç döngünün DIŞINDA kalanlar — ve bunun açıkça söylenmesi:

- 414 sayfanın Türkçe içeriği. Registry ve kapı kurulur; içerik yazımı ayrı bir
  programdır ve sayfa sayfa ilerler.
- `/tr/` ve `/en/` dizinlerine göç. Politikası bu belgede kararlaştırıldı;
  uygulaması FF-117'den sonra kendi paketinde yapılır.
- Çeviri üretimi. Kilit kurulur, **çalıştırılmaz**. Sahibin açık `ÇEVİRİLERE
  BAŞLA` komutu olmadan tek bir çeviri işi başlatılmaz.

---

## 6. Tur 1 kaydı (FF-115) — kabuk standardı

Telefonda gezinme çekmecesi SAĞDAN giriyordu. Masaüstünde kenar çubuğu solda
duruyor ve onu açan düğme de solda: parmağın bastığı yer ile panelin açıldığı
yer tersti. Aynı ürünün iki farklı zihinsel haritası.

- `DrawerPanel`'in varsayılanı `left` oldu; kural bileşenin içine gerekçesiyle
  yazıldı.
- Medya denetçisi tek istisna ve istisna ÇAĞRI NOKTASINDA açıkça yazılı: soldaki
  listeden seçilen dosyanın ayrıntısı sağda açılır, böylece liste ekranda kalır.
- İki kapı eklendi: telefon kabuğunun çıktısı gerçekten soldan mı geliyor, ve
  hiçbir gezinme yüzeyi `position="right"` yazmıyor mu. İkincisi kaynak tarar,
  çünkü bu bir bileşenin çıktısı değil KABUK AİLESİNİN yazım kuralıdır —
  `OpsShell.layout.test.tsx` aynı deseni kullanıyor.
- RTL notu koda düşüldü: Arapça arayüz açıldığında kural "başlangıç kenarı"
  olarak yeniden yazılmalı, fiziksel `left` olarak değil.


---

## 7. Tur 2 kaydı (FF-116) — kiracı adresi

Eski adres `/menu/ab12cd34ef/pasa-doner` idi: en anlamlı parça (işletme adı)
en sonda, en anlamsız parça (10 karakterlik anahtar) ortadaydı. Kartvizite
yazıldığında ya da telefonda söylendiğinde önce anlamsız kısım geliyordu.

Yeni adres:

```text
/restoran/pasa-doner/menu/ab12cd34ef
/restoran/pasa-doner/menu/ab12cd34ef/urun/101-adana-kebap
```

**Tür segmentinin dili işletmenin dilidir**, ziyaretçinin değil. Bir Türk
restoranının adresi, misafir arayüzü İngilizceye aldı diye `/restaurant/`
olmaz: menünün tek bir kanonik adresi vardır. Çevirisi tanımlı olmayan bir
dilde uydurma kelime üretilmez, uluslararası hâl kullanılır.

**Tek tür var ve bu dürüst bir ifadedir.** Zabuno'nun bugün bildiği her kiracı
bir yeme-içme işletmesidir. Ürün başka türleri gerçekten öğrendiğinde
(`kafe`, `bar`, `otel`) `BusinessType`'a yeni bir `case` eklenir; bugün olmayan
bir ayrımı veri modeline yazmak, doldurulmayacak bir sütun üretmek olurdu.

**Eski adresler ölmedi.** `/menu/{key}/{slug}` biçimi paylaşılmış
bağlantılarda, dış linklerde ve arama motorunun indeksinde duruyor; hepsi tek
atımlı 301 ile yeni adrese taşınıyor. Bunu kırmak, ürünün en güçlü vaadini
("basılı kart çalışmaya devam eder") kendi elimizle bozmak olurdu.

**İlk segment serbest değildir.** Rota yalnız bilinen tür segmentleriyle
eşleşir; serbest bıraksaydık `/pricing` dahil her şeyi yutardı. Segmentler
ayrıca rezerve kelimedir ve bunun kendi testi var — bir işletme `restoran`
slug'ını alırsa `/restoran/restoran/menu/...` gibi çözülemeyen adresler doğardı.

### Ürün sayfası ve `#item=101`

Sahibin örneği `#item=101` idi. Fragment sunucuya HİÇ ulaşmaz: indekslenmez,
ayrı bir görüntüleme olarak ölçülemez ve paylaşılan bağlantıda hangi ürün
olduğu sunucu tarafından bilinemez — deponun kendi kilitli kuralı da bunu
söylüyor ("ekran adresi asla fragment olamaz").

Bu yüzden ürünün gerçek adresi bir yol segmenti oldu. Fragment de çalışıyor:
menü sayfasındaki her satır artık `id="item-101"` taşıyor (tarayıcının kendi
çıpası, JavaScript gerektirmez) ve küçük bir betik `#item=101` biçimini o
çıpaya bağlıyor. Adres değiştirilmiyor: misafirin paylaştığı bağlantı elinde ne
ise o kalıyor.

**Kalite kapısı — burada bir tuzak vardı.** Ürün sayfası menü sayfasının
gövdesini kopyalayıp yalnız başlığı değiştirseydi, yüzlerce neredeyse aynı
sayfa doğardı; yönergenin §13.4'te yasakladığı şeyin ta kendisi. İki karar
alındı:

1. Ürün sayfası AYRI bir şablondur ve asıl içeriği ürünün kendisidir.
2. Anlatacak şeyi olmayan ürün (açıklaması, görseli ve alerjeni olmayan)
   indekslenmez — `noindex, follow` alır — ve menü sayfasından ona bağlantı
   VERİLMEZ. Hiçbir yere götürmeyen bir bağlantı bir yalandır.

Ürün adresleri bu pakette sitemap'e GİRMİYOR. Binlerce ürün adresini sitemap'e
dökmek kendi kalite kapısını ve sayfalanmış bir sitemap index'ini gerektirir;
keşif için menü sayfasındaki gerçek `<a href>` bağlantıları yeterli. Bu, kararı
ertelemek değil doğru sıraya koymaktır.

`kullaniciYolculugu`: Paşa Döner sahibinin Instagram'da paylaşacağı adres artık
`zabuno.com/restoran/pasa-doner/menu/ab12cd34ef`. Bir müşteri Adana Kebap'ı
arkadaşına göndermek istediğinde
`.../urun/101-adana-kebap` bağlantısını paylaşıyor; açan kişi ürünün fotoğrafını,
açıklamasını, fiyatını ve alerjenlerini görüyor ve tam menüye tek tıkla
geçebiliyor. Eskiden paylaşılabilecek tek adres `/menu/ab12cd34ef` idi ve
arkadaşının kırk ürün arasından o kebabı bulması gerekiyordu.


---

## 8. Tur 3 kaydı (FF-117) — sayfa kütüğü, kapı ve kilit

**Site haritası artık bir belge değil, bir kütük.** `docs/106-SITE-MAP-INPUT.md`
(sahibin verdiği girdi, depoya kopyalandı ki üretim tekrar edilebilsin) saf bir
ayrıştırıcıdan geçip `content_pages` tablosuna yazılıyor:
`php artisan site:import-map` → **386 sayfa**, hepsi `planned`.

Ayrıştırıcı iki kararı kendisi verdi ve ikisi de belgeden değil gerçeklikten
geliyor:

- **Ebeveyn girintiden değil YOLDAN türer.** Belgede `/tr/` ile `/tr/urun/`
  aynı girintide — kardeş yazılmışlar — oysa adres hiyerarşisinde biri
  diğerinin altında. Girintiye güvenmek, belgedeki bir biçim tercihini ürünün
  bilgi mimarisi sanmak olurdu.
- **`## 5` bölümü ağaç değildir.** Oradaki `/sitemap.xml` bir sayfa değil;
  sınırı bilmeyen bir ayrıştırıcı kütüğe olmayan sayfalar yazardı.

Komut YIKICI DEĞİLDİR: var olan bir kaydın yayın durumuna, tarihine ya da
geçmişine dokunmaz. Aksi hâlde belgeyi her yeniden içe aktarmak, yayındaki
sayfaları taslağa düşürürdü.

### Kapı

414 yol için 414 rota ya da 414 Blade dosyası yok. Tek bir denetleyici kütüğe
bakıyor, `PageGate`'e soruyor ve kararı uyguluyor. Bir sayfayı açmak için
koddan bileşen silinmiyor — yalnız yayın durumu değişiyor.

Kapının en önemli kararı: **yayınlanmamış URL `404` döner, `200` değil.** 414
URL'ye aynı "hazırlanıyor" metnini 200 ile vermek soft-404'tür ve alan adının
kalitesini topluca düşürür. Hazırlanıyor ekranı o 404'ün GÖVDESİDİR.

İki karar daha yazılı:

- **`approved` yayın değildir.** Aradaki farkı silmek, kalite kapısını
  atlamanın en kolay yolu olurdu.
- **503 yalnız gerçekten yayınlanmış bir sayfanın kısa bakımı içindir.** Hiç
  yayınlanmamış bir sayfada kullanmak, arama motoruna var olmayan bir şeyin
  geri geleceğini söylemektir; geçmişi olmayan sayfa 404'e düşer.

**Ortam yapılandırmadan okunur, `APP_ENV`'den türetilmez ve varsayılanı
production'dır.** Türetseydik yerelde ve testte staging davranışı çıkardı; asıl
tehlike ise tersidir — yapılandırması unutulmuş bir sunucunun taslakları 200
ile sunması.

Kapı **yalnız `/tr/` ve `/en/` altında** çalışıyor; bugün yayında olan
`/pricing`, `/help`, `/terms` adreslerine dokunmuyor. Onların dil dizinine
taşınması 301'leriyle birlikte ayrı bir paketin işi (§4.1).

### Yönergenin kendi çelişkisi kapandı

Plan hem "yayınlanmamış sayfa 404 döner" hem de CI'da "broken link scan"
istiyordu; menüde ya da içerik içinde duran her yayınlanmamış bağlantı kendi
CI'ını kırardı. Kural artık tek yerde: `PageRenderDecision::isLinkable()` —
bağlantı verilebilirlik, gerçekten çalışan bir sayfa olmakla aynı şey.

### İki guard yeni kodu yakaladı

Yazarken deponun kendi kapıları iki hata buldu ve ikisi de gerçekti:

1. `I18N-SSR-RATCHET-16`: hazırlanıyor sayfasına "Zabuno" kelimesi doğrudan
   yazılmıştı — sahip onu hiçbir PO dosyasında bulamazdı. Katalog anahtarına
   taşındı.
2. `RTL-LOGIN-DERIVED-02`: iki yeni şablon `dir="ltr"` sabitliyordu. Yön bir
   locale özelliğidir ve `DocumentLocale`'den türer; Arapça arayüz açıldığında
   sabit bir `ltr` sessizce yanlış olurdu.

### Çeviri kilidi — kurulu, kapalı

`TranslationGenerationLock` varsayılanı KAPALI. Yalnız kesin `true` açar:
`"1"`, `"true"`, `"yes"` gibi bir `.env` yazım hatasıyla kolayca oluşan değerler
kapalı sayılır. Kilit `env()` OKUMAZ — ortam değişkeni, kilidi bir sunucu
yapılandırmasıyla açılabilir hâle getirirdi; oysa bu bir dağıtım ayarı değil bir
ürün kararıdır ve kod incelemesinden geçmelidir. Deponun gerçek yapılandırma
dosyasının kapalı olduğunu ölçen bir test var.

**Bu pakette tek bir çeviri üretilmedi ve tek bir çeviri işi kuyruklanmadı.**
Sahip açıkça `ÇEVİRİLERE BAŞLA` demeden Faz 8'e geçilmeyecek.

`kullaniciYolculugu`: Sahip bugün `zabuno.com/tr/urun/qr-menu/` adresini
açtığında "Bu sayfa henüz servise çıkmadı — Sayfa: QR Menü, Durum: Sıraya
alındı" yazan bir servis fişi görüyor ve ana sayfaya, fiyatlandırmaya ya da
iletişime tek tıkla dönebiliyor. Arama motoru aynı adreste 404 görüyor ve onu
indekslemiyor. Sayfa yazıldığında tek bir alan `published` yapılacak ve aynı
adres gerçek içeriği sunmaya başlayacak — hiçbir dosya silinmeden.
