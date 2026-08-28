# 64 — Global oluştur, ve adresin unuttuğu şey

**Tur:** 3/6 · **Kaynak:** SaaS panel kabuk mimarisi §9, §10, §19 ·
`docs/61` A9–A12

## 1. Önce ölçüm: header'ın çoğu zaten vardı

Plan iki katmanlı bir header tarif ediyor. Ölçtüğümde çoğu yerindeydi:

| Plan | Durum |
| --- | --- |
| Global header + page header ayrımı | Vardı — `topBarCenter`/`topBarEnd` ve `PageHeader` |
| Workspace bağlamı | Vardı — `WorkspaceContextControls` |
| Lokasyon bağlamı | Vardı — aynı kontrolde |
| Çalışmayan search/notifications gizli | Vardı |
| Global Create | **Yoktu** |
| Help merkezi | Yoktu — ve bilerek yapılmadı, §4 |

Bu turda eklenen tek yeni yüzey **Global Create**.

## 2. Global Create: üç kural

1. **Yalnız GERÇEK hedefler.** Her madde, o şeyin gerçekten oluşturulduğu
   ekrana götürür.
2. **Ön koşulu olmayan madde listelenmez.** Menü bir şubeye aittir; şubesiz
   bir çalışma alanında "Menü" maddesi kullanıcıyı çıkışsız bir ekrana
   götürürdü. QR kod bir menüyü işaret eder; menüsüz bir QR, gösterecek şeyi
   olmayan bir kod olurdu.
3. **Sayfanın birincil eylemini kopyalamaz.** Menü ekranında "Ürün ekle" zaten
   görünürken buraya ikinci bir kopya koymak, aynı işi iki yerde arattırır.

Hiçbir hedef uygun değilse menü **hiç çizilmez**. Boş bir "Oluştur" düğmesi,
tıklandığında hiçbir şey sunmayan bir vaattir.

### 2.1 Sıralama bağlama göre DEĞİL

Plan bağlama göre sıralama öneriyor (§10). Uygulanmadı: dört maddelik bir
listede sıra değiştirmek kullanıcının kas hafızasını her sayfada bozar. Öneri
listenin uzun olduğu ürünler içindir. Liste büyüdüğünde karar yeniden
verilebilir.

### 2.2 Hedefe VARMAK

"Şube" maddesi listeye götürseydi kullanıcı tıkladığı şeyi ekranda ayrıca
aramak zorunda kalırdı. Adres `locations/new` olduğu için form **açık** gelir.

Bu, form durumunun bileşenden ADRESE taşınması demekti. Kazancı yalnız Create
menüsü değil: adres artık paylaşılabilir ve yenilemeye dayanır.

## 3. Yol boyunca bulunan kusur: kanonikleştirme alt yolu siliyordu

Çalışma alanı yüklendiğinde adres "kanonik" hâline çekiliyordu ve bu işlem
**bölüm içi yolu düşürüyordu**.

Sonucu bugün de vardı ve fark edilmemişti: `/settings/billing` adresinden giren
kullanıcı `/settings` adresine çekiliyordu. Ekran doğru açılıyordu — çünkü
durum bileşende yaşıyordu — ama **adres yalan söylüyordu** ve bir sonraki
yenilemede faturalama sekmesi kayboluyordu.

Kusur ancak `locations/new` eklenince görünür oldu: orada durum yalnız adreste
yaşıyor, dolayısıyla silinen alt yol formu da kapatıyordu.

Kanonikleştirmenin işi çalışma alanının adını adrese yazmaktır; kullanıcının
ekran içindeki yerini unutturmak değil. Gerileme testi yazıldı ve düzeltme
geri alınarak kırıldığı doğrulandı.

## 4. Help merkezi bilerek YAPILMADI

Plan §19 bir Help alanı tarif ediyor: sayfaya özel yardım, dokümantasyon
araması, klavye kısayolları, "yenilikler", destek talebi, sistem durumu.

Bunların **hiçbiri ürün olarak yok**. Yardım içeriği yok, destek kanalı yok,
durum sayfası yok, kısayol listesi yok.

Altı ölü bağlantı taşıyan bir Help menüsü, planın kendi kuralını çiğnerdi:
*"Çalışmayan özellik ana navigasyonda gösterilmez"* ve *"Available before
visible"*. Menü, ürünün yapılmamış tarafını kullanıcıya taşırdı.

Help, arkasında en az bir gerçek içerik olduğu gün eklenecek. `docs/61` A12
bu yüzden ⛔ olarak işaretlendi — unutulduğu için değil, karar verildiği için.

## 5. Ayrıca

`ThemeRoot` bileşen olmayan dışa aktarımları ayrı bir modüle taşındı
(`themeControl.ts`). Bir bileşen dosyasından sabit ve kanca dışa aktarmak Fast
Refresh'i bozar: düzenlemede tüm modül yeniden yüklenir ve durum sıfırlanır.
Lint bunu üç uyarıyla söylüyordu.
