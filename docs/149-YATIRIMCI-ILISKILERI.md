# 149 — Yatırımcı ilişkileri: uydurmadan bir sayfa nasıl yazılır (FF-251)

**Sahibin isteği (2026-09-08):**

> *"Mesela 'yatırımcı ilişkileri', pitch deck vb. bilgiler için sayfalar
> olmalı"* — ve aynı cümlede: *"tabii ki **uydurulmuş rakamla değil**."*

Ve genel emri değişmedi: *"Bir uzay teknolojileri şirketi gibi, abartı dursun,
görünsün, hissettirsin"* (`docs/146`), *"gerçek mobile first, 320 px first"*
(`docs/118` E1).

---

## 1. Paketin tek zor sorusu

Bu ürün ürün-pazar uyumundan **önce**. Bir yatırımcı sayfasının normalde
taşıdığı hiçbir sayı bu depoda ölçülemez: müşteri sayısı, gelir, büyüme oranı,
elde tutma, pazar büyüklüğü, birim ekonomisi. Hiçbiri.

İki yol vardı ve ikincisi seçildi:

1. Tahmin yazmak. Altı ay dayanır, ilk soruda çöker ve çöktüğü an yalnız o
   sayıyı değil sayfadaki **her cümleyi** götürür.
2. **Yapılmış işi ve bilinen sınırları göstermek.** Ölçülebilen her şey
   yazılır, ölçülemeyen hiçbir şey yazılmaz, ve ölçülemediği **söylenir**.

Sayfanın en güçlü bölümü bu yüzden "ne yapmıyoruz"tur. Gerekçesi ürünün kendi
envanterinde yazılı: var sandığı bir yeteneğin olmadığını servis sırasında
öğrenen bir restoran, hiçbir şeyi değiştiremeyeceği saatte öğrenir.

---

## 2. Dört sayfa, ve neden dört

| Adres | Cevapladığı soru |
| --- | --- |
| `/investors` | Durum: ürün nedir, nesi çalışıyor, nesi yok, nesi taahhüt edilmedi. |
| `/investors/product` | Envanter: her parça, her sınır, her iddianın **dosyası**. |
| `/investors/deck` | Sıra: aynı olgular, bir kez okunacak düzende. |
| `/investors/contact` | Yol: nereye yazılır, muhatap kim. |

**Deck neden bir sayfa, indirilen bir dosya değil.** Sahip "pitch deck" istedi
ve aynı cümlede uydurma istemedi. İndirilen bir sunum bu ikisini birden
karşılayamaz: indirildikten sonra düzeltilemez ve ürün değiştiği gün elde
kalan, kimsenin geri alamayacağı bir iddia olur. Deck bu yüzden bir sayfa ve
öteki üçüyle **aynı olguları** okuyor — ayrı yazılmış bir deck, ikinci bir
gerçek kaynağı olurdu.

**Dördü de tek dosyadan okur:** `App\Support\Site\InvestorDossier`.

---

## 3. Her iddia nereden geliyor

| Sayfadaki iddia | Kaynağı |
| --- | --- |
| Zincir (altı adım) | `App\Support\Site\HomeStory::CHAIN` → metin kataloğu |
| Parçalar (on iki) | `HomeStory::PARTS` |
| Sınırlar (yedi) | `HomeStory::LIMITS` |
| Her iddianın **dosyası** | `ProductOverviewPage` envanterinin `source` alanı |
| Dosya gerçekten var mı | `file_exists(base_path(...))`, her çizimde |
| Kapı betikleri | `InvestorDossier::GATES`, her biri `file_exists` ile |
| Hizmet seviyesi taahhüdü | `config/sla.php` → `ServiceLevelCommitment` |
| Barındırma ve alt işleyenler | `MeasuredSubprocessors` (yapılandırma + kimlik kasası + ölçüm ayarları) |
| Fiyatlar | `PlanCatalogueSeeder` → `App\Support\Site\PublicPlans` |
| Şirket kimliği | `CompanyProfile` → ortam |

Sayılar **katalog metnine yazılmaz**: katalogda `{parts}`, `{limits}`,
`{sources}`, `{gates}`, `{subprocessors}` yer tutucuları durur ve
`ShowInvestorPageController` onları ölçülen değerle doldurur.

---

## 4. Yazılmayı REDDEDİLEN dört şey

**1. Test sayısı.** İlk taslak "depoda kaç test var" diye sayıyordu. Ölçüldü
ve vazgeçildi: `tests/` ve `docs/` üretim imajına **girmiyor**
(`.dockerignore`). O sayı yerel makinede 367, canlı sitede **0** olurdu — ve
sıfır test iddia eden bir sayfa, uydurulmuş bir sayıdan kötüdür, çünkü yanlış
YÖNDE yalan söyler ve kimse fark etmez. Yerine imajda gerçekten duran iki şey
sayılıyor: kanıt dosyaları ve kapı betikleri.

**2. Çalışma süresi oranı.** `config/sla.php`'nin dört alanı da boş ve bu
bilinçli. Sayfa oranı yazmıyor; **boş bırakılmış anahtarları adıyla** sayıyor
ve `/sla`ya bağlıyor.

**3. Yatırımcıya özel bir e-posta kutusu ve ikinci bir form.** Sitede zaten
çalışan bir iletişim akışı var (doğrulama, bal küpü, hız sınırı, referans
numarası, alındı e-postası). İkinci bir form ikinci bir kuyruk ve bir gün
birinin bakmayı unuttuğu ikinci bir kutu demekti. `/investors/contact` ne
yazılacağını söyler ve `/contact`a gönderir.

**4. Yol haritası ve "yakında".** Bir tarih verilmedi, çünkü verilecek bir
tarih ölçülmedi. Bunun yerine deck'in **son paneli** neyin bilinmediğini
sayıyor: talep, ödeme isteği, ikinci ay kullanımı, ölçekte hizmet maliyeti.

---

## 5. Kapı: `INVESTOR-HONEST-01`

`tests/Feature/PublicSite/InvestorPagesContractTest.php`.

Kapının **asıl** kuralı bir yasak kelime listesi değil:

> Sayfanın gövdesindeki **her rakam**, `InvestorDossier`in ürettiği dizelerde
> de geçmek zorundadır.

İzin verilen küme bir liste değil bir **türev**; yani kapı bir sayıyı beyaz
listeye almıyor, bir **yolu** şart koşuyor: bir sayı sayfaya ancak ölçümden
geçerek girebilir. Katalog metnine yazılmış "1400 restaurants, growing 40 a
month" cümlesi denendi ve kapı ikisini de yakaladı (`1400`, `40`).

Bunun yanında ADIYLA yasaklananlar — hata mesajı ne yapıldığını değil **neden
yasak olduğunu** söylesin diye: referans/testimonial, "trusted by", ödül, "as
seen in", "coming soon", "join thousands", "case study", "backed by", "market
leader", TAM/SAM/SOM ve büyüme kısaltmaları, "1000+ restoran" ailesi, gövdede
yüzde işareti, gövdede `<img>` (müşteri logosu bir görseldir), gövdede
`<script>` ve `<iframe>` (canlı sayaç bir betiktir).

Üç kapı daha aynı dosyada: `INVESTOR-REAL-02` (iddialar envanterden gelir ve
dosyaları gerçekten var), `INVESTOR-SCENE-03` (sahne var, sayfa başına tuval
bir, dar ekran bastırması yok), `INVESTOR-LINK-04` (gezinti/altbilgi/sitemap
zinciri ve çıkan her iç bağlantı gerçekten açılıyor).

---

## 6. Sahne

Dağarcık `docs/146` §4'ten alındı, **yeni bir fikir uydurulmadı**: önsöz bandı
(`orbit` / `grid` / `conduit`), yıldız alanı, nebula + `scene-morph`, tel
kafes, veri hattı, yörünge, ufuk, vinyet, `scene-tilt`, `scene-reveal` +
`--scene-order`, `data-scene-progress`, `data-axis`.

Pazarlığa kapalı olanlar korundu: **sayfa başına tek tuval** (ikinci bir WebGL
bağlamının maliyeti hâlâ ölçülmedi, `docs/146` §11), dekoratif her katman
`.site-stage` içinde ve `aria-hidden`, hareket yalnız
`prefers-reduced-motion: no-preference` arkasında.

**Sahne iki yerde susuyor** ve ikisi de karar: fiyatın okunduğu bölümde ve
`/investors/contact` gövdesinde. Bir fiyatın ve bir muhatap adresinin okunduğu
yer, dikkatin bölünmemesi gereken yerdir.

Ağırlık: kurumsal stil **+115 bayt gzip** (36.405 → 36.520; tavan 40.960).
Betik **0 bayt**: dört sayfanın tamamı motorun var olan dağarcığını kullanıyor
ve tek satır JavaScript eklemedi.

---

## 7. Ölçülenler (2026-09-08)

`scripts/mobile-ux-audit`, statik dışa aktarımın üstünde, dört görünüm:

| Sayfa | 320×480 kullanılabilir genişlik | Yatay taşma | Kırpılma |
| --- | --- | --- | --- |
| `/investors` | 317 / 320 | yok | yok |
| `/investors/product` | 317 / 320 | yok | yok |
| `/investors/deck` | 308 / 320 | yok | yok |
| `/investors/contact` | 308 / 320 | yok | yok |

Tek bulgu `/investors` üzerinde iki `small-target` ve **bu paketten önce de
vardı**: paylaşılan fiyat parçasının cümle içi bağlantıları ("Contact us",
"Ask us"). WCAG 2.2 ölçüt 2.5.8 metin akışındaki hedefleri bu ölçüden muaf
tutuyor ve deponun kendi kuralı da öyle diyor (`docs/146` §7). Yeni yazılan
markup tek bir bulgu üretmedi.

---

## 8. Ölçülemeyenler

1. **iOS Safari.** Bütün ölçümler Chrome'da (`docs/146` §11 madde 2 aynen
   duruyor). Bu makinede iOS Safari yok ve "muhtemelen çalışır" demek bu
   paketin yasakladığı şeyin ta kendisi.
2. **Sahne kapıları CI'da koşmuyor.** `scene-budget-gate`,
   `scene-perf-gate`, `scene-visual-gate` ve `mobile-ux-audit` hâlâ elle
   çalıştırılıyor (`docs/146` §11 madde 4). Bu paket o borcu kapatmadı;
   gizlemesi daha kötü olurdu.
3. **Görsel gerileme tabanı.** `scene-visual.baseline.json` yalnız dört eski
   yüzeyi tanıyor; yatırımcı sayfaları için imza üretilmedi, yani bu dört
   sayfada "hareketsiz olması gereken hâller bire bir sabit mi" sorusu
   **sorulmadı**.
4. **Türkçe.** Çeviri kilidi kapalı (`docs/120` §7). Dört sayfanın metni
   İngilizce kaynak katalogda; `tr` yuvası boş doğdu ve doldurulmadı.
