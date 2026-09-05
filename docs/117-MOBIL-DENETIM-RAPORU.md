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
