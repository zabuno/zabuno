<!--
    KARAR VE ÖLÇÜM BELGESİ — FF-237, 2026-09-08.

    Ölçüm önce yapıldı, karar sonra verildi. Belgedeki her sayı gerçekten
    koşturulmuş bir komuttan gelir; ölçülemeyenler §8'de AÇIKÇA "bilinmiyor"
    diye ayrılmıştır. Uydurulmuş sayfa, bağlantı ya da metrik yoktur.
-->

# 141 — Masterpage'in katmanları: altı satırlık altbilgi ve olgunlaşan üst çubuk

## 1. Sahibin isteği ve FF-232'nin bıraktığı eksik

Sahip aynı isteği tekrar tekrar söyledi (2026-09-08):

> *"header+footer = masterPage'den başla, ama MVP değil, maturity level bir UX
> estetiği planla."*
>
> *"Çoook zengin bir footer olmalı ve çok katmanlı olmalı ve çok row olmalı ve
> çok menu grubu olmalı, pSEO için footer üzerinde content menus."*
>
> *"Üst çubuk da olgunlaşsın: marka, gezinti, hesap eylemleri."*

Sonra bugünkü hâli gördü ve **iki sütunluk bir altbilgi** buldu. Haklıydı.

**FF-232 isteğin YARISINI karşılamıştı ve o yarı DOĞRUYDU:** altbilgideki
bağlantılar elle yazılmıyor, sayfa kütüğünden türüyor; boş bir grup hiç
çizilmiyor; kapı tek cümle — *altbilgideki her bağlantı 200 döner*
(`docs/136` §6). Bu paket o kararların **hiçbirine dokunmadı**.

Karşılanmayan yarı **yapıydı**. Ölçülen hâl (FF-232 teslimi, `origin/main`):

| Ne | Ölçülen |
| --- | --- |
| Altbilgideki satır | 2 (bağlantı ızgarası + marka bloğu, aynı satırın içinde) |
| Altbilgideki grup | **2** — `Ürün`, `Yasal` |
| Altbilgideki bağlantı | **12** |
| Altbilgide görünen yasal belge | **8** — kütüphanede **13** var |
| Üst çubuktaki hesap eylemi | **0** (ikisi de menünün içinde) |
| Altbilgide satıcı kimliği | **yok** |

Son üç satır tek başına birer kusurdu ve üçü de aynı türden: yazılmış,
çalışan ve aranan bir şey, onu arayan kişinin bakacağı yerde YOKTU.

---

## 2. Ölçüm nasıl yapıldı

| Ne ölçüldü | Nasıl |
| --- | --- |
| Kabuğun geometrisi | `php artisan site:export-static` + gerçek Chrome (CDP), 320×480 · 320×568 · 1280×800 |
| Taşma, kırpılma, dokunma hedefi, yoğunluk | `node scripts/mobile-ux-audit <dizin> --width … --height …` |
| Altbilginin ekran boyu | Aynı Chrome oturumunda `footer.getBoundingClientRect()`; katlanmış ve tamamen açık, iki ayrı sayı |
| Bağlantı ve grup sayısı | Üretilen HTML'den `<a href>` ve `data-nav-group` sayımı |
| Yasal belge sayısı | `LegalLibraryPort::KEYS` (kodun kendisi), rotalar `routes/web.php` |
| CSS ağırlığı | `view:clear` + `rm -rf public/build` + `npm run build`, sonra `wc -c` ve `gzip -9` |
| Kapılar | `pint --test`, `artisan test`, `vitest run resources/js`, `prettier --check`, `i18n:check`, `mobile-ux-audit`, `logical-direction-gate` |

Karşılaştırmanın "önce" tarafı aynı komutlarla, aynı makinede, `origin/main`
temelinden alındı (`339b0be3` sonrası main). Tek bir sayı tek başına bir şey
söylemez.

---

## 3. Altbilgi ARTIK ALTI SATIR — her satırın bir işi var

Süs olsun diye satır eklenmedi. Her satır ya bir soruyu yanıtlıyor ya bir
yükümlülüğü karşılıyor.

| # | Satır | İşi | Bugün doluyor mu |
| --- | --- | --- | --- |
| 1 | **Marka** | Kim olduğumuz ve ürünün ne olduğu — tek cümle | Evet |
| 2 | **Bağlantı grupları** | Ürün · Şirket · Hesap | Evet — 3 grup, 6 bağlantı |
| 3 | **pSEO içerik menüleri** | Kütükten türeyen geniş bağlantı ızgarası | **Hayır** — kütükte yayınlanmış sayfa yok, bu yüzden HİÇ çizilmiyor |
| 4 | **Yasal** | On üç belge, ayrı ve bulunur | Evet — 13 bağlantı |
| 5 | **Kurumsal kimlik** | Satıcının kim olduğu (`CompanyIdentity`) | Evet — 7 alan; girilmemişse "girilmedi" YAZAR |
| 6 | **Alt satır** | Telif ve gezintiye dönüş yolu | Evet |

Bugün **beş** satır çiziliyor; altıncısı sahip bir sayfayı yayına aldığı gün,
**tek bir Blade satırı değişmeden** beliriyor. Bir kapı bunu ölçüyor
(`FOOTER-LAYER-01`, ikinci test).

### 3.1 Grup sayısı neden 3 ve neden daha fazlası değil

Sahibin saydığı gruplar: *ürün, çözümler, işletme türleri, kaynaklar,
şirket, yatırımcı, destek.* Bunların çoğunun **bugün canlıda bir sayfası
yok** — içeriği yazılmış on altı sayfanın hiçbiri yayında değil
(`docs/137` §1.3). Elle yazılmış bir "Çözümler" başlığı bugün 404'e giden
bir başlık olurdu.

Yapılan şey, **var olanı doğru başlığın altına yazmak**: bugün 200 dönen dört
adres zaten üç farklı soruya cevap veriyor.

| Grup | Bağlantı | Yanıtladığı soru |
| --- | --- | --- |
| `Ürün` | `/pricing`, `/help` | Ne kadar, nasıl kullanırım |
| `Şirket` | `/about`, `/contact` | Kimden alıyorum, nasıl ulaşırım |
| `Hesap` | `/login`, `/register` | Nasıl başlarım |

FF-232'de dördü de tek bir `Ürün` başlığının altındaydı — "hakkımızda" bir
ürün değildir ve ödeme kuruluşunun üye iş yeri incelemesi onu o adla arar.

**Zenginliğin geri kalanı hâlâ kütükten gelir.** 3. satır (pSEO bandı) sahip
yayın kararını verdiği gün grupları ve bağlantılarıyla birlikte doğar; grup
başlığı sayfanın kendi `parent_key` hiyerarşisinden, etiket sayfanın kendi
başlığından çıkar (`docs/136` §6.2).

### 3.2 Yasal satır: sekiz değil **on üç**

`LegalLibraryPort::KEYS` on üç belge sayıyor; on üçünün de rotası var, metni
yazılmış ve adresi 200 dönüyor. Altbilgide **sekizi** vardı.

Görünmeyen beşi: ticari ileti izni (`/marketing-consent`), veri işleme
(`/data-processing`), hizmet seviyesi (`/sla`), kabul edilebilir kullanım
(`/acceptable-use`), üçüncü taraf lisansları (`/third-party-licenses`).

Son dördü FF-228'de **tam olarak bir kurumsal alıcının satın alma ya da hukuk
birimi için** yazılmıştı (`docs/140`). O birim onları sitede arar; sitede
yoklarsa, o birim için **yoktur**.

Ticari ileti izni FF-232'de bilerek dışarıdaydı ve gerekçe *"onu okuyacak
kişi kayıt ekranındadır"* idi. O gerekçe kayıt ekranı için doğru, **altbilgi
için değil**: bir metnin bir yerden bağlantılı olması, başka bir yerden
bulunamamasını gerektirmez.

**Kapı listeyi elle saymıyor** (`FOOTER-LAYER-02`): `LegalLibraryPort::KEYS`
ile karşılaştırıyor. "Bugün on üç tane var" diye sabitlenmiş bir sayı, on
dördüncü belge eklendiği gün yine sessizce kaybolmasına izin verirdi.

### 3.3 Kurumsal kimlik satırı

`/about` ve `/contact` satıcı kimliğini **gövdesinde** gösteriyordu; artık her
kurumsal adres **altbilgisinde** de gösteriyor. Liste aynı
`CompanyIdentity::rows()` çağrısından çıkıyor, dolayısıyla ikisi ayrışamaz.

**Girilmemiş alan atlanmaz, "girilmedi" diye yazılır.** Atlanan bir satır o
alanın hiç istenmediği izlenimi verirdi; uydurma bir değer ise sözleşmenin
tarafını yanlış gösterirdi. Bugün yedi alanın hepsi girilmemiş ve altbilgi
bunu yedi kez söylüyor — bu bir kusur değil, **bekleyen bir sahip kararı**.

Veritabanına dokunmaz: kimlik `.env` → `config/legal.php` yolundan gelir
(`CompanyIdentity` sınıf başlığındaki üç gerekçe), dolayısıyla altbilgiye
kimlik koymak kurumsal sayfalara bir veritabanı bağımlılığı **eklemedi**.

### 3.4 Alt satırda olmayan üç şey

| Yok | Neden |
| --- | --- |
| Sürüm | Kayıt sayısı ziyaretçiye değil kayıt sözleşmesine hitap eder ve bir `<meta>` olarak kalır (`MP-04`). Bir kebapçının "16/16 modules" okuması FF-190'da zaten kaldırılmıştı |
| Dil seçici | `config/i18n.php` `shipped_locales` bugün **tek** dil taşıyor (`['en']`). Tek seçenekli bir seçici, olmayan bir seçimin sözünü vermektir (`docs/120` §5.8) |
| Durum sayfası | `status.zabuno.com` henüz yayında değil (`docs/136` §9) |

Yerine konan şey ölçüm sonucudur: altbilgi 320×480'de bir ekrandan uzun,
dolayısıyla sonuna varan kişiye gezintiye dönüş yolu gerekiyor. Çıpa
`#main-content` — atlama bağlantısının zaten kullandığı hedef; ikinci bir
kimlik icat edilmedi.

---

## 4. Üst çubuk — ÖLÇÜM bir kararı değiştirdi

Sahibin istediği üç şey: marka, gezinti, hesap eylemleri.

İlk yazım ikisini de çubuğa koydu. **320×480'de ölçüldü:**

| Öğe | Ölçülen genişlik |
| --- | --- |
| Marka (`Zabuno`) | 62 px |
| İki hesap düğmesi (`Log in` + `Create account`) | 211 px |
| Menü düğmesi | 95 px |
| Boşluklar | 16 px |
| **Toplam** | **384 px** · kullanılabilir genişlik **296 px** |

Sonuç: çubuk **üç satıra** çıktı ve **157 piksel** yer kapladı — 480 piksel
boyundaki bir ekranın **üçte biri**, içerikten ÖNCE.

**Verilen karar:** çubuğa **bir** eylem çıkar (`/register`), ikincisi
(`/login`) bölmede kalır; düğme dolgusu `--space-3`ten `--space-2`ye iner.
Hangisinin çubukta kalacağı bir zevk değil bir **sıra** sorusu:
`zabuno.com`u ilk kez açan kişinin henüz hesabı yoktur.

**Ölçülen sonuç:**

| Genişlik 320 | `origin/main` | Bu paket |
| --- | --- | --- |
| Üst çubuk yüksekliği | **65 px** | **65 px** |
| Çubuktaki hesap eylemi | 0 | **1** |
| Marka / düğme / menü | 62 / — / 95 | 62 / **125** / **87** |

Yani çubuk bir dönüşüm eylemi kazandı ve **bir piksel bile uzamadı**.
Küçülen şey hedef değil **ölü alan**: yükseklik 44 pikselde kaldı, genişlik
44'ün çok üstünde.

Bölme de olgunlaştı: grup adları artık **görünür** bir `<h2>`. FF-232'de ad
yalnız `aria-label`daydı — ekran okuyucu iki bölge duyuyordu, gözle bakan biri
sekiz bağlantıyı tek bir yığın olarak görüyordu. Aynı bilgiyi iki duyuya
birden vermek, ikisinden birini seçmekten dürüsttür.

---

## 5. Katlama kuralı — gizlemek DEĞİL

Çok satırlı bir altbilgi dar ekranın en büyük riskidir. Yasal satırın tek
başına on üç bağlantısı var; her satır 44 piksel yüksek olduğuna göre
**572 piksel** — 320×480'de bir ekrandan uzun.

**Kural tek bir sayıdır** (`SiteNavigation::OPEN_ITEM_CEILING = 4`):

> Dörtten çok maddesi olan grup **kapalı** başlar.

- Karar **genişliğe değil grubun kendi boyuna** bağlıdır (`MP-05`,
  `docs/118` E1). Bir medya sorgusu "geniş ekranda yer boldur" varsayımını
  kurala çevirirdi ve o varsayım hiç ölçülmedi. Aynı grup her genişlikte aynı
  davranır; değişen tek şey kaç sütun sığdığıdır.
- Dört, çünkü iki maddelik bir grubu katlamak bir dokunuşu iki dokunuşa
  çevirip hiçbir yer kazandırmaz (2 madde = 88 px, katlanmış hâli 44 px).
- Katlama bir `<details>`tir: **betiksiz** çalışır, klavyeyle açılır, durumunu
  ekran okuyucuya söyler.
- **İçerik belgede DURUR.** Kapı bunu ölçüyor (`FOOTER-LAYER-06`): on üç yasal
  adresin hepsi, betik gövdeleri atıldıktan sonra da HTML'de.

**Bu pakette tek bir `max-*` bastırması, tek bir "mobilde gizle" ve tek bir
kırılma noktası jetonu yok.** Bir kapı bunu da donduruyor
(`FOOTER-LAYER-04`): `site-shell.css` içindeki hiçbir `@media` sorgusu
`max-width` taşımıyor. `min-width` serbest ve aynı sebeple — taban koşulsuz
yazılır, zenginleştirme üstüne eklenir.

---

## 6. ÖLÇÜLEN SONUÇLAR

### 6.1 Altbilgi 320×480'de kaç ekran boyu

Gerçek Chrome, 320×480, on sekiz sayfanın hepsinde aynı (kabuk tek kaynak):

| | `origin/main` | Bu paket |
| --- | --- | --- |
| Altbilgi, açılış hâli | **797 px · 1,66 ekran** | **761 px · 1,59 ekran** |
| Altbilgi, her grup elle açılmış | 797 px · 1,66 ekran | 1.860 px · 3,88 ekran |
| Satır | 2 | **6** (bugün 5 çiziliyor) |
| Grup | 2 | **4** (+ kütükten gelenler) |
| Bağlantı | 12 | **20** |

**Altbilgi zenginleşirken KISALDI.** Sekiz bağlantı, bir kimlik satırı ve bir
dönüş yolu eklendi; açılış boyu 36 piksel **azaldı**. Sebep katlama: on üç
yasal bağlantı 572 piksel yerine 44 piksel yer kaplıyor ve isteyen açıyor.

Satır dağılımı (320 px, katlanmış):
marka 100 · gruplar 453 · yasal 69 · kimlik 69 · alt satır 69.

### 6.2 Dar ve geniş ekran denetimi

`php artisan site:export-static` ile üretilen **18 canlı sayfa**, gerçek
Chrome, `scripts/mobile-ux-audit`:

| Çerçeve | `origin/main` | Bu paket |
| --- | --- | --- |
| **320×480** | ölçülemiyordu (bayrak yoktu) | **3/18** · `small-target` 7 · `tight-gap` **0** |
| **320×568** | 3/18 · `small-target` 7 · `tight-gap` 0 | **3/18** · `small-target` 7 · `tight-gap` **0** |
| **1280×800** | 5/18 · `small-target` 9 · `tight-gap` **9** | **3/18** · `small-target` **7** · `tight-gap` **0** |

Yatay taşma, kenardan kırpılma ve metin kırpılması **üç çerçevede de sıfır**.

**Geniş ekranda dokuz `tight-gap` bulgusu kapandı.** Hepsi kabuktaydı ve
sebebi aynıydı: bitişik iki bağlantı, içeriği kadar daralınca komşusuyla
arasında 0 piksel kalıyordu (`a "Help" ↔ a "Contact" → 0px`). Satırlar artık
tam genişlikte ve gruplar kendi kaplarında.

**Kalan yedi bulgu kabukta DEĞİL, sayfa gövdelerinde** ve hepsi `origin/main`
ölçümünde de var: ana sayfanın metin akışı içindeki bağlantılar ("Contact
us", "Ask us", "Pricing") ve yardım sayfasının "Write to us" bağlantısı.
WCAG 2.2'nin 2.5.8 ölçütü metin akışındaki hedefleri 44 pikselden muaf tutar.
Bu paket sayfa gövdelerine dokunmadı.

### 6.3 `--height` bayrağı — neden eklendi

`scripts/mobile-ux-audit` yüksekliği koda sabitlemişti (telefonda 568).
Global kural (`TOUCH-FIRST-INTERFACE`) tabanı **320×480**'e indirdi ve o iki
sayı ayrılamaz: genişlik taşmayı ve hedef boyunu belirler, **yükseklik ise
"ilk ekranda ne var" sorusunun tek ölçüsüdür**.

Varsayılan **değişmedi** ve sıra değişmedi: dar ekran hâlâ taban, CI kapısı
hâlâ 320×568'de koşuyor. Bayrak yalnız ikinci bir soru sormayı mümkün kılar —
ve ilk sorduğunda üst çubuğun 157 piksel olduğunu buldu (§4).

### 6.4 CSS ağırlığı

`php artisan view:clear && rm -rf public/build && npm run build`, sonra
`public/build/assets/app-*.css`. İki ölçüm aynı koşulda alındı.

| Aşama | Ham | gzip |
| --- | --- | --- |
| `origin/main` (FF-232 teslimi) | 161.175 B | 27.726 B |
| **Bu paketin teslimi** | **162.583 B** | **27.849 B** |
| **Fark** | **+1.408 B (%0,87)** | **+123 B (%0,44)** |

Altı satır, dört grup, katlanabilir bir `<details>` deseni ve bir kimlik
satırı için ödenen bedel sıkıştırılmış CSS'te **123 bayt**. Sebep: yeni bir
bileşen ailesi yüklenmedi; daisyUI'nin zaten emilmiş sınıfları ve var olan
jetonlar kullanıldı. React paketi değişmedi — kurumsal sayfalar hâlâ **sıfır
React** yüklüyor.

---

## 7. Kapılar — hepsi, rakamla

| Kapı | Sonuç |
| --- | --- |
| `./vendor/bin/pint --test` | **PASS** |
| `php -d memory_limit=-1 artisan test` | **PASS** · 2.870 test, 2.867 geçti, 3 atlandı, 21.771 doğrulama, 0 başarısız |
| `npx vitest run resources/js` | **PASS** · 284 dosya, 2.153 test |
| `npx prettier --check .` | **PASS** |
| `npm run i18n:check` | **PASS** — projeksiyonlar PO ile aynı |
| `scripts/mobile-ux-audit` 320×480 · 320×568 · 1280×800 | **PASS** · taşma 0 · yeni bulgu 0 · `tight-gap` 9 → 0 |
| `scripts/logical-direction-gate` | **PASS** · 1.818 dosya · yeni ihlal 0 |

Yeni kapılar (bu pakette yazıldı): `FOOTER-LAYER-01…06`,
`HEADER-ACTIONS-01…02` (`tests/Feature/PublicSite/ShellLayersTest.php`) — 9 test,
103 doğrulama.

**Bir kapı bu pakette KIRILDI ve düzeltildi.** `SAAS-DOMAIN`
(`MultiDomainTest`) alan adının koda gömülmesini yasaklıyor; ilk yazımda üç
dosyanın YORUM satırlarında alan adı geçiyordu. Yorum da koddur: bir gün
kopyalanır. Yeniden yazıldı.

Korunan kapılar bir tek satırı bile değişmeden geçiyor: `MP-01…MP-06`,
`SHELL-SINGLE-SOURCE-01…04`, `NAV-REGISTRY-01…05`, `FOOTER-CONTENT-01…05`,
`ICON-01…04`, `DAISY-THEME-01…05`.

### 7.1 Çeviri yapılmadı

Yedi yeni katalog dizesi eklendi ve hepsi **İngilizce kaynak** olarak
`resources/js/i18n/site.ts` içinde yaşıyor. `npm run i18n:extract` altı
locale'in PO dosyasına boş `msgstr` yazdı — yani hiçbir dile **tek kelime
çevrilmedi** ve çevrilmemiş anahtar kaynağa düşüyor. Sahibin çeviri kilidi
yerinde (`docs/118` E4).

---

## 8. Bu paketin ölçemedikleri — açıkça

1. **Üretimde bugün altbilginin nasıl göründüğü bilinmiyor.** Üretim
   sunucusuna erişim yok. "pSEO bandı bugün çizilmiyor" cümlesi, temiz bir
   veritabanına `site:import-map` koşturulmuş yerel ölçümden gelir. Üretimde
   bir sayfa yayına alınmışsa bant orada zaten doludur.
2. **Estetik ölçülmedi, geometri ölçüldü.** `mobile-ux-audit` taşma,
   kırpılma, dokunma hedefi, hedefler arası ayrım ve yoğunluk ölçer;
   hiyerarşi, kelime seçimi ve "güzel mi" insan kararıdır ve bu araç onları
   ölçtüğünü iddia etmiyor.
3. **iOS Safari doğrulanmadı.** Ölçüm Chrome'da yapılıyor. Altı `<details>`
   üzerine kurulu bir altbilginin iOS'taki davranışı belgelenmiş davranışa
   dayanıyor, gerçek cihazda ölçülmedi.
4. **Katlanmış bir grubun gerçekten açılıp açılmadığı** bir tarayıcıda elle
   denenmedi; ölçüm HTML'in ve CSS'in ne dediğini okuyor. `<details>`
   tarayıcının kendi davranışıdır ve bu paket ona bir betik eklemedi.
5. **Bir kişinin altbilgide aradığını bulup bulmadığı** ölçülmedi. Ölçülen
   şey, aradığının orada OLDUĞU.

---

## 9. Sonraki ajanlara talimat

**Serbest:**

- Altbilgiye yeni bir SATIR eklemek — o satırın bir işi olması ve
  320×480'de ölçülmesi koşuluyla.
- Kabuğun üstüne JavaScript eklemek (mega menü, arama, hareket) — taban
  `<a href>`ler ve `<details>`ler yerinde kaldığı sürece (`docs/118` E8).
- Yeni bir Phosphor glifi eklemek — paketten kopyalayarak, `ICON-02`yi
  geçirerek.

**Yasak:**

- Altbilgiye elle bir kütük bağlantısı yazmak. Zenginlik yayın kararından
  gelir, Blade'den değil (`docs/136` §6, korunuyor).
- `max-width` medya sorgusu ya da "mobilde gizle". `FOOTER-LAYER-04` kırar.
- Kırılma noktası jetonu (`sm:` …). `MP-05` kırar.
- Yasal listeyi elle saymak. Liste `LegalLibraryPort::KEYS`ten okunur.
- Katlanmış bir grubun içeriğini betiğe bağlamak: `<details>`in içi sunucu
  HTML'inde durur ve `FOOTER-LAYER-06` bunu ölçer.
- Alt satıra `status.zabuno.com`, sürüm numarası ya da tek dilli bir dil
  seçici koymak (§3.4).
- Emoji. Her yerde (`docs/118` E6).

---

## 10. Rapor alanları

- **once:** Altbilgi iki gruptu ve on iki bağlantı taşıyordu; on üç yasal
  belgenin **beşi** — aralarında veri işleme sözleşmesi ve hizmet seviyesi
  taahhüdü — yazılmış, adresi 200 dönüyor ama sitede hiçbir yerden
  bulunamıyordu. Satıcının kim olduğu yalnız iki sayfada yazıyordu. Üst
  çubukta hiçbir hesap eylemi yoktu: kaydolmaya karar vermiş biri önce bir
  menü açmak zorundaydı. Geniş ekranda kabuktaki dokuz bağlantı komşusuyla
  sıfır piksel arayla duruyordu.
- **simdi:** Altbilgi altı satır: marka, üç bağlantı grubu, kütükten türeyen
  pSEO bandı, on üç yasal belge, satıcı kimliği ve alt satır. On üç belgenin
  hepsi orada ve liste kütüphaneden okunuyor, elle sayılmıyor. Dörtten uzun
  her grup katlanıyor — betiksiz, gizlemeden, her adres HTML'de duruyor. Üst
  çubuk marka, gezinti ve birincil hesap eylemini taşıyor.
- **fark:** Bağlantı 12 → **20**, grup 2 → **4**, satır 2 → **6**. Altbilginin
  açılış boyu 320×480'de **797 px → 761 px**: zenginleşirken **kısaldı**. Üst
  çubuk bir dönüşüm eylemi kazandı ve yüksekliği **65 px'de kaldı**. Geniş
  ekranda `tight-gap` **9 → 0**. Sıkıştırılmış CSS **+123 bayt (%0,44)**.
- **kullaniciYolculugu:** Bir zincirin satın alma birimi Zabuno'yu
  değerlendiriyor ve "Data Processing Agreement"ı arıyor. Dün o belge
  yazılmıştı, adresi 200 dönüyordu ve sitede hiçbir yerden bağlantısı yoktu —
  yani o birim için **yoktu**. Bugün altbilgideki "Legal" başlığını açıyor, on
  üç belgeyi birlikte görüyor, telefonundan dokunuyor: hedef 44 piksel, yatay
  kayma yok. Aynı sayfanın altında satıcının kim olduğunu da görüyor — bugün
  yedi alan da "girilmedi" diyor ve bu, ürünün ona söylediği ilk dürüst
  cümle.
- **kalanEngel:** Şirket kimliğinin yedi alanı `.env`'e girilmedi — bir kod
  işi değil, bekleyen bir **sahip kararı**; girilene kadar altbilgi, `/about`,
  `/contact` ve dört yasal sayfa "girilmedi" demeye devam eder ve o dört sayfa
  sitemap'e girmez. Kütükte yayınlanmış sayfa yok, dolayısıyla pSEO bandı
  bugün hiç çizilmiyor. Sayfa gövdeleri ve hareket dağarcığı ayrı paketlerin
  işi; bu paket `resources/views/public/home.blade.php` dosyasına dokunmadı.
- **capability_delta:** Kurumsal sitenin kabuğu artık bir **yapı** taşıyor:
  yeni bir satır, yeni bir grup ya da yeni bir yasal belge, tek bir yere
  yazılıp her kurumsal adreste beliriyor; uzunluk sorunu gizlemeyle değil
  katlamayla çözülüyor ve katlanan hiçbir şey aramaya kapanmıyor.
- **Çalışabilen:** On sekiz kurumsal adresin hepsi altı satırlık altbilgiyi ve
  birincil eylemi taşıyan üst çubuğu giyiyor; on üç yasal belge bulunabiliyor;
  satıcı kimliği her sayfada; her şey betik çalışmadan geziliyor; 320×480'de
  yatay taşma yok.
- **Çalışamayan:** Sahibin saydığı "çözümler, işletme türleri, kaynaklar,
  yatırımcı" grupları bugün **çizilmiyor** — arkalarındaki sayfalar yayında
  değil. Yapı hazır; onları dolduracak olan bir yayın kararı, bir Blade satırı
  değil.
