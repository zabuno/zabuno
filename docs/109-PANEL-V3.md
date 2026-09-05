# 109 — Panel v3: KANONİK kaynak ve yenileme planı

**Kaynak:** `docs/reference/panel-v3/` — sahibin 2026-09-05'te verdiği
`Zabuno Paneli-v3.html` paketinden çıkarılmış iki dosya:

- `panel.dc.html` — panel kabuğu ve on ekran
- `MedyaModulu.dc.html` — medya modülü ve on bir bölümü

**Sahibin cümlesi:** *"burası master data. eski UI tamamen silinebilir (eğer
gerekli ise). bir tercih yapmak gerektiğinde buradaki data esastır. bu
görevin amacı mevcut repo daki tasarımı güncellemek değil YENİLEMEKTİR.
benzetmek değil DEĞİŞTİRMEKTİR."*

## 0. Bu belge neden var

`docs/108` bir önceki sürümün planıydı ve o sürüm **eksik uygulandı** —
sahip yeni sürümü tam da bu yüzden gönderdi. Bu belge onun yerine geçmez,
üstüne yazar: v3 kanoniktir, `docs/108` tarihsel kayıttır.

Bir önceki turda yapılan hata şuydu: ekranlar "benzetildi". Var olan bileşen
korunup üstüne jeton sürüldü; kaynağın GETİRDİĞİ yeni yetenekler (toplu
işlem, yönetişim, AI önerileri, menü hapları, CSV, fotoğraftan aktar) hiç
doğmadı. Bu turda ölçü şudur: **kaynakta olan ve üründe olmayan her bölüm,
ya doğar ya da neden doğmadığı yazılır.**

> **§1 VE §2 TABLOLARI, İŞE BAŞLARKEN ALINAN ÖLÇÜMDÜR — GÜNCEL DURUM §5'TEDİR.**
>
> Aşağıdaki iki tablodaki "YOK / kısmi" satırları bu paket BAŞLAMADAN önce
> çıkarıldı ve iş bitince güncellenmedi. Sonuç: tablolar, aynı belgenin §5
> sayacıyla ve kodla çelişiyor — örneğin "çoklu menü YOK" diyor, oysa
> `menu_service_switches` göçü ve menü hapları depoda.
>
> Bu, `docs/109`'un kendi §8.6'sında uyardığı tuzağın belgenin İÇİNDE
> gerçekleşmiş hâlidir: gerekçe eskidi, cümle kaldı. Bir tablo satırı
> okunurken sorulacak soru şudur — **kod ne diyor?** (Çelişki denetimi
> FF-161, 2026-09-05.)

## 1. Panel — on ekran

| Ekran | Kaynakta ne var | Depoda durum |
| --- | --- | --- |
| Home | Karşılama, "Şimdi" kartı, **AI önerileri (3, onaylı)**, **4 hızlı eylem**, sparkline'lı sayaçlar, Kurulum 3/5, **Bugün en çok bakılanlar** | ✅ öneriler, hızlı eylemler, en çok bakılanlar doğdu; **sparkline zaman serisi ucu gelince** |
| Menüler | **Menü hapları** (Ana menü yayında · Kahvaltı 07–11 · Ramazan kapalı), kategori rayı + sayaç, ürün satırı, **Fotoğraftan aktar**, **CSV**, **Önizle** | kısmi — haplar, çoklu menü, CSV, fotoğraftan aktar YOK |
| QR kodlar | Masa kartları ızgarası + tarama sayısı, sağ panel (tema/boyut/PDF/yazdır), **ölçülmüş kontrast**, toplu kod üretimi | kısmi |
| Insights | KPI kartları, **çubuk+çizgi grafik**, **saat ısı haritası**, **şube halkası**, masa sıralaması, **aranıp bulunamayanlar + Ekle** | kısmi — grafikler ve "Ekle" eylemi YOK |
| Yayınlama | **Taslak→Önizleme→Yayında adım çizgisi**, değişiklik listesi, hazırlık kontrolü, sürümler + **Geri al**, **Planla**, **Telefonda önizle** | kısmi — adım çizgisi, planla, telefonda önizle YOK |
| Şubeler | Şube kartları (durum, masa, tarama/hafta, saat), Masalar/Düzenle | kısmi |
| Medya | Panelin bir MODÜLÜ; kendi alt gezintisi | aşağıda |
| Takım | Üyeler + rol seçici, bekleyen davetler, **roller ne yapabilir** açıklaması | kısmi |
| Ayarlar | — | var |
| Profil | — | var |

## 2. Medya modülü — on bir bölüm

| Bölüm | Depoda durum |
| --- | --- |
| Kütüphane | var |
| **Toplu işlem** | ✅ doğdu (FF-137) — beş adım, gerçek kuru çalışma, KALICI SİL yazımı |
| Yükle | var |
| Dönüştür | var |
| Görüntüle | var (video oynatıcı YOK) |
| Boyut motoru | var |
| Kuyruk | var |
| Kota ve çöp | var (CDN kartı ve dağıtım ağı anahtarı YOK) |
| **Yönetişim** | ✅ doğdu (FF-137) — gerçek rol matrisi, yasal saklama gerçek kilit |
| Ayarlar | var |
| Olgunluk | **YOK** |

## 3. Kaynağın değişmez kuralları (kendi cümleleri)

- **"Öneri yapar, sen onaylarsın. Onaysız hiçbir şey değişmez."** AI hiçbir
  şeyi kendiliğinden uygulamaz.
- **"Basılı kod hiç değişmez; menüyü istediğin kadar güncelle."**
- **"Geri alma da bir yayındır: yeni sürüm numarası alır, QR aynı kalır."**
- **"İş başladığı anda liste dondurulur."** Toplu işlem çalışırken yüklenen
  yeni dosya o işe girmez.
- **"Herkes sadece işine yeteni görür."**
- **"Silinen dosya 30 gün burada bekler, sonra kalıcı silinir."** —
  _kaynağın cümlesi böyle; ÜRÜNDE süre plana bağlıdır (7 / 30 / 90 gün,
  `docs/98` §7 ve `config/media-quota.php`). Kaynak tek bir sayı varsayıyor,
  depo plana göre farklılaştırmayı seçti; ekran süreyi dinamik yazar._

## 4. Yenileme kuralı — benzetme değil değiştirme

1. Bir ekran kaynakta neyse ODUR. Var olan bileşen kaynağın düzenini
   taşıyamıyorsa **değiştirilir**, korunmaz.
2. Kaynakta olmayan bir bölüm üründe duruyorsa kaldırılır; kaldırılamıyorsa
   (veri/gizlilik/güvenlik) sebebi yazılır.
3. Kaynakta olan ve üründe VERİSİ OLMAYAN bir bölüm **uydurulmaz**: ya
   gerçek veriye bağlanır ya da hiç çizilmez ve burada sebebi yazılır.
4. Güvenlik değişmezleri kaynağın üstündedir ve sahibe SORULUR: bir önceki
   turda SVG, PDF, tarama anahtarı ve "aslını sakla" böyle karara bağlandı
   (`docs/108`). O kararlar geçerlidir.

## 5. Sayaç

11/11 tamamlandı, 1 iş aktif.

Panel v3 kapsamı bitti. Bundan sonrası kaynağın çizdiği ekranları uygulamak
değil, **o ekranların söylediği şeyi gerçekten yapabilmektir** — aşağıdaki
§8.2 tablosu bunun listesidir ve satırlar veri geldikçe düşer.

**Tamamlanan:** Home · Menüler (haplar hariç) · QR · Insights (+ zaman
serisi) · Yayınlama (+ planla, telefonda önizle) · Şubeler · Takım ·
Medya/Toplu işlem · Medya/Yönetişim · Medya/Olgunluk · Medya/kalan farklar ·
Ayarlar + Profil.

**Aktif:** menü denetim izinin ekranı.

**Sırada:** —

Bölümler: Home · Menüler · QR · Insights · Yayınlama · Şubeler · Takım ·
Medya/Toplu işlem · Medya/Yönetişim · Medya/Olgunluk · Medya/kalan farklar.

## 6. Kaynağın somut verisi ve verilmiş kararlar

Aşağıdakiler `panel.dc.html` içindeki gerçek veri tanımlarından alınmıştır.
Ajanlar bunları yeniden türetmemeli.

### 6.1 Home — AI önerileri (kaynağın üç örneği)

| Başlık | Gerekçe satırı | Eylem |
| --- | --- | --- |
| "Vejetaryen" 14 kez arandı, menüde yok | Bulunamayan aramalar · son 7 gün | Kategori öner |
| Beyti Sarma en çok bakılan 5'te ama fotoğrafı yok | Fotoğraflı ürünlere 2,3× daha çok bakılıyor | Fotoğraf ekle |
| Tavuk Şiş 30 gündür hiç açılmadı | Menü mühendisliği · hiç bakılmayan | Gizlemeyi öner |

Üçü de GERÇEK ölçümden doğuyor: arama kayıtları, ürün görüntülenme ve
fotoğraf varlığı. Yani bunlar "AI" değil, ÖLÇÜMDEN çıkan öneriler —
sağlayıcı bağlı olmasa da üretilebilirler. Kaynağın kuralı: *"Öneri yapar,
sen onaylarsın. Onaysız hiçbir şey değişmez."*

### 6.2 Home — hızlı eylemler ve kurulum

Hızlı eylemler: Fiyat değiştir → Menüler · Ürünü gizle/bitti → Menüler ·
QR indir → QR · Fotoğraf ekle → Medya.

Kurulum beş adım: İşletme bilgileri · İlk şube ve masalar · Menü yüklendi
(N ürün) · Menüyü yayınla · İlk QR'ı test et. Biten adımın üstü çizili.

### 6.3 Menüler — çoklu menü

`menus: [['Ana menü','yayında'],['Kahvaltı','07–11'],['Ramazan','kapalı']]`
Yani bir şubede birden çok menü var ve her birinin bir DURUMU (yayında /
saat aralığı / kapalı) var.

Kategoriler: Kebaplar 12 · Pideler 8 · Dürümler 6 · Çorbalar 4 · Tatlılar 9
· İçecekler 7.

### 6.4 Takım — rollerin cümlesi (birebir)

- **Editör** — Ürün, fiyat, fotoğraf. Yayınlayamaz.
- **Yönetici** — Menü, QR, yayın. Faturaya dokunamaz.
- **Mutfak** — Alerjen ve "bugün bitti". Başka bir şey görmez.
- **Sahip** — her şey: fatura, takım, yayın. Sahiplik davetle verilmez,
  DEVREDİLİR.

### 6.5 Insights — GRAFİK KÜTÜPHANESİ KARARI

Kaynak ECharts'ı CDN'den yüklüyor (`heatRef`, `pieRef` referansları).
**Depoya ECharts EKLENMEZ** ve sebebi ölçülebilir: paket ~300 KB gzip'tir,
depo bütçesi ise giriş başına 200 KB (`DS-BUNDLE-BUDGET-07`). Tek bir ekran
için bütçeyi ikiye katlamak, telefonla bakan bir restoran sahibinin her
sayfa açılışını yavaşlatırdı.

Aynı düzen **elle yazılmış SVG** ile çizilir: çubuk+çizgi, saat ısı
haritası, şube halkası. Renkler jetondan okunur. Bu bir sapma değil, aynı
TASARIMIN başka bir teslim biçimidir — zip tasarımın kaynağıdır, bağımlılık
listesinin değil.

### 6.6 Yayınlama

Adım çizgisi: Taslak (N değişiklik) → Önizleme (telefonda kontrol) →
Yayında (v14 · 2 gün önce). Hazırlık kontrolü beş madde; eksik olanın
yanında "Düzelt" düğmesi. Sürümlerde "Geri al" ve kaynağın kuralı:
*"Geri alma da bir yayındır: yeni sürüm numarası alır, QR aynı kalır."*

### 6.7 QR

12 masa kartı + tarama sayısı; sağ panelde tema (Klasik/Markalı/Koyu),
boyut (5×5 · 8×8 · A6 masa kartı), PDF indir / Yazdır ve **ölçülmüş
kontrast** ("18,7:1 · tarayıcı testi geçti"). Yeni masalar için toplu kod
üretimi ve ileri ayarlar (bölge, koltuk, ad öneki).

## 7. Sahibin kararları — 2026-09-05 (açıkça soruldu)

### 7.1 Çoklu menü: YAPILACAK, saat bazlı geçişli

Kaynak üç menü hapı gösteriyor (Ana menü yayında · Kahvaltı 07–11 · Ramazan
kapalı). **Kaynakta bile bu haplar tıklanınca hiçbir şey yapmıyor** — yalnız
bir bildirim gösteriyor, kategori ve ürün listesi değişmiyor. Yani kaynak
görsel bir imkân veriyor, bir veri modeli değil.

Depoda ise şube başına TEK menü kilidi var: `menus.location_id` UNIQUE, bir
uygulama katmanı istisnası ve iki adlandırılmış test
(`MENU-PERSIST-ONE-PER-LOCATION-01`, `-NO-PARTIAL-WRITE-01`).

**Sahip "yap" dedi ve saat bazlı geçişi seçti.** Bu bir ürün kapsamı
kararıdır ve şu sonuçları doğurur:

- Şube başına birden çok menü; her menünün adı, durumu ve **saat aralığı**
  olur.
- **QR AYNI KALIR.** Misafir aynı kodu okutur; saate göre doğru menü açılır.
  Bu, "basılı kod hiç değişmez" kuralının doğal devamıdır.
- Yayınlama ve QR çözümü menü başına çalışmak zorunda: bugün ikisi de
  şubeden tek menüye gidiyor.
- Kilit gevşetilirken var olan davranış bozulmamalı: tek menülü bir şube
  bugünkü gibi çalışmaya devam eder.
- Aralıklar ÇAKIŞAMAZ ve boşluk bırakılamaz: gün 24 saattir ve hiçbir saatte
  "hangi menü" sorusu cevapsız kalamaz. Cevapsız kalırsa misafir boş bir
  sayfa görür.

### 7.2 Yayınlama: Planla ve Telefonda önizle — İKİSİ DE YAPILACAK

- **Planla:** zamanlanmış yayın. Zamanlanmış yayın da bir YAYINDIR — yeni
  sürüm numarası alır, QR aynı kalır. İptal edilebilir, iki kez çalışmaz,
  zaman dilimi `Europe/Istanbul` ve hangi saatte çıkacağı ekranda yazılı.
- **Telefonda önizle:** taslağı gösteren kısa ömürlü, imzalı adres. Misafirin
  gördüğü adres DEĞİŞMEZ; önizleme süresi dolunca çalışmaz, `noindex` ve
  ekranda "bu bir önizleme, misafirler henüz bunu görmüyor" yazar.

## 8. Uygulama sırasında verilen kararlar

### 8.1 Ayarlar sekmeleri yeniden adlandı VE içerik taşındı

Depoda ikinci sekmenin adı "Hesap"tı ve içinde kişisel ad/şifre formu
duruyordu — **aynı form Profil ekranında da vardı**. Bir ayarın iki evi.
Deponun kendi kuralı bunu zaten yasaklıyordu ama uygulanmamıştı.

Yalnız sekmeyi yeniden adlandırmak yetmezdi: kullanıcı "Çalışma alanı"
yazan yerde kendi adını bulurdu. Ad ile içerik BİRLİKTE değişti — kişisel
olan her şey Profil'e, çalışma alanına ait olan Ayarlar'da.

### 8.2 Çizilmeyenler ve sebepleri

| Kaynakta | Çizilmedi çünkü |
| --- | --- |
| QR "Koyu" teması | ISO/IEC 18004 koyu-üstüne-açık şart koşar; ürün böyle bir kod üretemez |
| QR "5×5 / 8×8 cm" | Karekod kenarı bu üründe ayar değil, 10:1 okuma mesafesinden gelen 45 mm sabit |
| QR "tarayıcı testi geçti" | Ürün hiçbir telefonda tarama testi çalıştırmıyor |
| Video oynatıcı | Depo video kabul etmiyor; eksik olan ffmpeg değil, video hattı hiç yok |
| CDN kartı / dağıtım ağı | Depoda CDN yok ve ölçülmüyor |
| Dönüştürme ve CDN kotası | Ne sayaç ne sınır var |
| ~~Mutfak rolü~~ | YAPILDI (FF-138): rol, izinleri, sunucu sınırı ve menü ekranındaki daraltma |
| ~~Şube "Açık" rozeti~~ | GEREKÇE DÜŞTÜ (FF-141): çalışma saati verisi var, misafir yüzeyi onu okuyor; rozet artık uydurma değil — FF-148 |
| ~~Menü satırında "kim ve ne zaman"~~ | KAYIT KURULDU (FF-154): on üç olay, kim + ne zaman + öncesi/sonrası. Ekran ayrı paket — veri olmadan çizilmezdi, artık veri var |
| Çalışma alanı: diller, özel alan adı, tehlikeli bölge | Çeviri deposu ve DNS akışı yok; tehlikeli bölge geri döndürülemez ve sahibin kararını ister |

### 8.3 Sayı gösterilmeyen yerlerde "0" yazılmıyor

Tekrar eden bir karar: veri yoksa **boş bırakılıyor**, sıfır yazılmıyor.
QR tarama sayısı (analitik ücretli), şube tarama/hafta (plan ya da yetki
yoksa), iş kuyruğu ilerlemesi (kayıtlı yüzde yok). Sıfır "hiç olmadı" der
ve bu, bilinmeyen için yanlış bir cevaptır.

### 8.4 Mutfak rolü — ekranda daraltma, sunucuda sınır (FF-138)

Rol `docs/109` §6.4'ün kendi cümlesiyle doğdu: *"Alerjen ve 'bugün bitti'.
Başka bir şey görmez."* Üç katmanda karşılığı var ve üçü de aynı şeyi
söylemek zorunda:

1. **Sunucu**: `RolePermissions::for(Kitchen)` = `workspace.view`,
   `menu.view`, `menu.allergens.manage`, `menu.stock.manage`. Yapamadığı her
   şey uçlarda 403/404 ile durur — sınır burasıdır.
2. **Kabuk**: kenar çubuğu izne göre süzülür; aşçı yalnız Menüler'i görür.
3. **Menü ekranı**: fiyat düğmesi, görünürlük anahtarı, yeniden adlandırma,
   satır taşma menüsü, kategori ekleme/sıralama, menü açma/düzenleme, CSV,
   fotoğraftan aktarma, ürün ekleme ve yayınlama ÇİZİLMEZ.

Kalanlar aşçının işidir: "bugünlük tükendi" ve alerjen — ikisi de satırda.
Menü hapları da kalır; hangi menüye baktığını seçmek okumaktır, değiştirmek
değil.

**Neden devre dışı değil, hiç yok.** Devre dışı bir düğme bir söz verir:
"bir gün, bir şekilde". Aşçının rolü değişmedikçe o gün gelmeyecek. Kural
`docs/98` FF-74'te konmuştu ve burada tekrar edildi.

**Fiyat bunun istisnasıdır ve kasıtlı.** Fiyat hem bir eylem hem bir
BİLGİDİR. Düğme gider, metin kalır: aşçı doğru satıra baktığını üründen
olduğu kadar fiyattan da doğrular. Yetkiyi kaldırmak, bilgiyi karartmak
değildir.

### 8.5 Okunamayan sunucu gövdesi ekranı çökertmiyordu — artık hiç çökertmiyor

`as MenuTree` derleyiciye bir söz verir, sunucuya değil. `categories`
taşımayan bir 200 geldiğinde menü ekranı çiziminin ortasında patlıyor ve
sahibin gördüğü şey boş beyaz bir sayfa oluyordu — hatanın kendisi bile
yazılmıyordu. CI bunu işlenmemiş hata olarak yakaladı.

Gövde artık sınırda doğrulanır. Geçersizse ekran **uydurmaz**: `categories:
[]` yazmak boş bir menü göstermek olurdu ve sahip ürünlerinin silindiğini
sanırdı. Bunun yerine cevabı reddeder, kendi yükleme hatasını gösterir ve
tazeleme yollarında ekranda duran ağacı ezmez.

### 8.6 "Veri yok" bir kader değil, o günün durumudur

§8.2 tablosu bu belgenin en çok yanlış okunan yeri oldu, o yüzden kuralı
açıkça yazıyorum: oradaki her satır **o gün için** doğrudur ve satırın
kendisi bir gerekçe taşır. Gerekçe düştüğünde satır da düşer.

İki kez oldu ve ikisi de aynı biçimde:

- **Mutfak rolü** "depoda karşılığı yok" diye çizilmemişti. Rol yazıldı,
  satır düştü (FF-138).
- **Şube "Açık" rozeti** "iddiayı destekleyen alan yok" diye çizilmemişti.
  Oysa alan vardı: `location_opening_hours` doluydu, sahip panelden
  giriyordu ve şube kartı bile okuyordu — yalnız kimse "şu an açık mı"
  sorusunu sormamıştı. Misafir tarafı o soruyu sorunca (FF-141) gerekçe
  kendiliğinden düştü (FF-148).

Aradaki fark önemli: birincisinde yetenek YOKTU, ikincisinde yetenek VARDI
ve kimse fark etmemişti. İkincisi daha tehlikelidir, çünkü "veri yok"
cümlesi bir kez yazıldıktan sonra kimse geri dönüp bakmaz.

Bu yüzden tablodaki bir satırı okuyan herkes şunu sormalı: *gerekçe bugün
hâlâ doğru mu?* — "çizilmedi" bir karar değil, o günkü verinin sonucudur.

### 8.7 "Çalışıyor ama söylediğini ölçmüyor" — tekrar eden kusur ailesi

§8.6 "veri yok" gerekçesinin eskiyebileceğini anlatıyor. Bu bölüm daha
sinsi olanını: **veri VAR, ekran onu gösteriyor, ama gösterdiği şey
söylediği şey değil.** Hiçbiri çökmüyor, hiçbiri test kırmıyor, hepsi
ekranda makul görünüyor.

Bu turda beş örneği çıktı ve beşi de aynı biçimde bulundu — iddiayı
kaynağına kadar izleyerek:

| İddia | Gerçekte ölçülen |
| --- | --- |
| "{count} kez arandı" | `COUNT(DISTINCT visitor_key)` — yani KAÇ KİŞİ. Beş kez arayan tek misafir orada 1 |
| "Bu ürün son 30 günde bir kez bile açılmadı" | Menü henüz ÖLÇÜLMEMİŞTİ; eşik kişi değil, ürün başına sayıların toplamını sayıyordu |
| Panodaki ölçüm blokları | Ücretli kapıdan hiç geçmiyordu; abonesiz alan analitik ekranında 402 alırken panosunda aynı veriyi görüyordu |
| "İşlenmesi bitince yeniden deneyin" | Tarayıcı yokken işleme ASLA bitmiyor |
| "Her görsel taranır ve bir kişi kontrol eder" | O ortamda hiçbir şey taranmıyor |

**Ortak nokta:** cümle bir zamanlar doğruydu ya da doğru olması
tasarlanmıştı; altındaki ölçüm sonradan değişti veya hiç kurulmadı. Kimse
geri dönüp cümleyi ölçümle karşılaştırmadı.

**İki tanesinin kanıtı ürünün kendi içindeydi** ve bu, aramanın en ucuz
biçimini gösteriyor: aynı sayı analitik ekranında zaten "{count} ziyaretçi"
diye okunuyordu; menü mühendisliği ekranının boş-durum metni zaten
"{observed}/{eşik} ziyaretçi" diyordu. Aynı ölçümün iki cümlesi varsa,
biri yanlıştır.

**Kural:** bir sayının yanına cümle yazmadan önce o sayının SQL'ine bak.
`COUNT(DISTINCT x)` "kaç kez" değildir. `array_sum(per_item_counts)` kişi
sayısı değildir. Ve bir ekran, kardeşinin sorduğu izni sormuyorsa bunun
bir sebebi olmalıdır — çoğu zaman yoktur.
