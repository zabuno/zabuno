# 103 — Menü ekranı: altı döngülük UX incelemesi

**Sahibin talimatı (2026-09-04):** "Düzenle butonu, tıklayınca böyle browser
dialog açılıyor. Neden UI component yok? Burası, bu sayfa, atıl kalmış. 6
döngüye al, her defasında şunu sorgula: UX olarak user journey workflow neden
kötü? Her defasında derin analiz ve web search ile derin araştır. Burası çok
çok iyi olmamalı, mükemmel olmalı."

Bu belge o altı döngünün kaydıdır. Her döngü aynı soruyu farklı bir mercekle
sorar, bulguyu yazar, kararı ve gerekçesini bırakır. Döngüler kapanmaz:
sonraki tur bu belgeden devam eder.

Ölçülen ekran: `/app/{ws}/menu` — `MenuCatalogWorkspace`.

---

## Döngü 1 — Etkileşim ilkelleri: ürünün dışına düşmek

**Bulgu.** Üç yerde tarayıcının kendi diyaloğu kullanılıyordu: adı düzeltmek
için `window.prompt`, ürün ve kategori silmek için `window.confirm`.

Sahibin ekran görüntüsündeki kutu şunu yazıyor: *"zabuno.com web sitesinin
mesajı"*. Bu, tarayıcının ürüne ait olmayan içerik için kullandığı çerçevedir
— dolandırıcılık uyarılarıyla aynı görsel dil. Ürün bir anda kendi
yüzeyinden çıkıp tarayıcının içine düşüyor.

Somut zararlar:

1. **Bağlam yok olur.** Diyalog açıkken düzenlenen satır görünmez; "hangi
   ürünün adını yazıyorum?" sorusu ekranda cevapsızdır.
2. **Sessizce ölür.** Tarayıcı "bu sayfanın başka diyalog oluşturmasını
   engelle" kutusunu sunar. Kullanıcı bir kez işaretlerse `prompt` `null`,
   `confirm` `false` döner — yani düzenle düğmesi o oturum boyunca çalışır
   görünüp HİÇBİR ŞEY yapmaz ve hata da vermez.
3. **Doğrulama gösteremez.** Boş ad girildiğinde diyalog çoktan kapanmıştır;
   uyarı, yazılan yerden uzakta belirir.
4. **Sayfayı dondurur.** İkisi de eşzamanlıdır: açık oldukları sürece hiçbir
   şey çizilmez, hiçbir istek işlenmez.
5. **Sonucu anlatamaz.** `confirm` tek bir cümle ve iki tarayıcı düğmesidir;
   "yayınlanmış sürümler etkilenmez ama taslak satır geri gelmez" bilgisi
   oraya sığmaz.

**Araştırma.** Satır içi düzenleme, tablo/liste bağlamında en az sürtünmeli
yoldur: kullanıcı komşu satırları görmeye devam eder ve düzeltme birkaç tuşla
biter ([Pencil & Paper, data table
patterns](https://www.pencilandpaper.io/articles/ux-pattern-analysis-enterprise-data-tables)).
Modal ise yalnız tam dikkat gerektiren ve sonucu anlatılması gereken işler
için ayrılır ([LogRocket, modal UX
patterns](https://blog.logrocket.com/ux-design/modal-ux-design-patterns-examples-best-practices/)).
Yani ad düzeltmek satır içine, geri alınamaz silme diyaloğa aittir.

**Karar ve uygulama (FF-101).**

- `InlineRename`: ad, durduğu yerde bir alana dönüşür. `Enter` kaydeder,
  `Escape` vazgeçer, **odak kaybı kaydetmez** (sekme değiştiren kullanıcı
  farkında olmadan göndermemeli). Hata, alanın hemen altında durur.
- Silme, ürünün kendi `ConfirmDialog`'una taşındı — bu bileşen katalogda
  ZATEN vardı ve bu ekran onu görmezden geliyordu. Diyalog neyi sildiğini
  adıyla söyler ve sonucunu yazar.

---

## Döngü 2 — Satırın grameri: dokuz eşit ağırlıklı kontrol

**Bulgu.** Bir ürün satırı şunu taşıyordu:

```
Adana │ 1 │ ↑ ↓ ✎ ✕ │ 250.00 TRY │ ☑ │ Allergens │ Price │ Sold out │ Photo & text
```

Dokuz kontrol, hepsi aynı görsel ağırlıkta. Üç ayrı sorun:

1. **`↑ ↓ ✎ ✕` simge değil, YAZI KARAKTERİYDİ.** Yazı tipinden geliyorlardı:
   her işletim sisteminde başka boyut, başka kalınlık, başka temel çizgi —
   düğmeler hizasız görünüyordu.
2. **Yıkıcı eylem, taşımanın yanındaydı.** "Aşağı taşı" (`↓`) ile "sil" (`✕`)
   iki küçük komşu hedefti. Yanlış tıklama geri alınamaz bir kayıptır;
   sıralama ise gün içinde defalarca yapılan bir iştir.
3. **Giriş noktası yoktu.** Her şey ikincil olunca göz nereye bakacağını
   bilmez.

**Karar ve uygulama (FF-101).**

- Oklar gerçek ikon (Phosphor `ArrowUp`/`ArrowDown`), hizalı ve ölçeklenir.
- Kalem düğmesi KALKTI: ad zaten kendisi düzenleniyor, ikinci bir yol
  aynı işi iki kez sunmaktı.
- Silme, taşma menüsüne (`⋯`) taşındı ve kırmızı/çöp kutusu ile işaretlendi:
  iki adım uzakta, yanlışlıkla erişilemez.
- `RowActions` `micro`'dan `compound`'a taşındı, çünkü artık bir compound
  (`ActionMenu`) besteliyor — katman kuralı yukarı bağımlılığı yasaklar.

**İkinci tur (FF-102) — kalan beş kontrol.**

Bir restoran sahibinin bu satırda gün içinde defalarca yaptığı TEK iş
"tükendi"dir. Fiyat ara sıra, fotoğraf kurulumda, alerjen nadiren, görünürlük
nadiren değişir. Buna göre:

| Kontrol | Karar | Gerekçe |
| --- | --- | --- |
| Tükendi | Satırda kalır | Günlük iş; iki adım uzağa konamaz |
| Fiyat | Fiyatın KENDİSİ tıklanır | Yanında ayrı bir "Price" düğmesi durması, aynı bilgiyi iki kez göstermekti — biri okunacak, diğeri tıklanacak |
| Fotoğraf ve metin | Taşma menüsü | Kurulumda yapılır, her gün değil |
| Alerjenler | Taşma menüsü | Nadiren değişir |
| Görünürlük | Taşma menüsü, CÜMLEYLE | Etiketsiz bir kutu, yanındaki "tükendi" düğmesiyle karışıyordu: ikisi de "misafir bunu görmüyor" demek ama biri bugünlük, diğeri kalıcı |

Satır **dokuz kontrolden beşe** indi: sıra, ad, fiyat, tükendi, eylemler.

---

## Döngü 3 — Yolculuk: ekranın sırası kullanıcının sırası değil

**Bulgu — ve bir DÜZELTME.** İlk okumada "toplu aktarım kutusu ekranın
tepesini işgal ediyor" diye yazmıştım. Koda bakınca yanlış olduğu görüldü:
kutu `<details open={!menuHasItems}>` — yani menüde ürün varken KAPALI
geliyor, yalnız boş menüde açılıyor. Sahibin ekran görüntüsünde açık
görünmesinin sebebi kutuyu kendisinin açmış olması. Karar zaten doğruydu ve
gerekçesi kodda yazılı.

**Gerçek bulgu.** "Add product" ve "Add category" düğmeleri dikey bir yığının
içinde `inline-flex` ile çiziliyordu ve esneyip TAM GENİŞLİK kutulara
dönüşüyorlardı: ekranda boş bir metin alanı gibi duruyorlardı. Bir düğmenin
metin alanı gibi görünmesi, kullanıcıyı içine yazmaya davet eder.

**Uygulanan (FF-102).** Ekleme düğmeleri kendi sınıfını aldı: içeriği kadar
geniş, başında bir artı işareti, kesikli kenarlık ("burası bir şey ekler").

**Kalan:** "ürün ekle"nin kategori başlığının yanında da bulunması — uzun bir
menüde her seferinde kategorinin sonuna inmek gerekiyor.

---

## Döngü 4 — Geri bildirim ve geri alma

**Bulgu.** Silme geri alınamaz ve tek savunma bir onay kutusudur. Araştırma,
geri alınabilir işler için onay yerine **geri alma** önerir; geri alınamaz
işler için ise sonucun açıkça yazılmasını.

Bugünkü durumda silme gerçekten geri alınamaz (taslak satır gider). Bu yüzden
bu turda doğru hamle onayı DÜRÜSTLEŞTİRMEKTİ: diyalog artık "yayınlanmış
sürümler etkilenmez, taslak satır geri gelmez" cümlesini taşıyor.

**Sıradaki döngü:** silmeyi çöp kutusuna almak (medyada olduğu gibi) ve
onayı geri almayla değiştirmek — bu bir arka uç kararıdır ve ayrı bir paket
ister.

---

## Döngü 5 — Yoğunluk, ritim, para birimi

**Bulgu (FF-102'de düzeltildi).** Satırdaki dizilim `ad → sıra → EYLEMLER →
fiyat → durum` idi: göz addan fiyata giderken üç düğmenin üstünden atlamak
zorundaydı ve iki ürünün fiyatı hizalanmadığı için karşılaştırılamıyordu.

Yeni dizilim `sıra → ad → fiyat → tükendi → eylemler`; kategori başlığı da
aynı grameri kullanır. İçerik solda, sayı sağda, eylemler en sağda — iki
farklı sıralama, gözü her satırda yeniden eğitmek olurdu.

**Bulgu (açık).** Fiyat `250.00 TRY` olarak yazılıyor. Türkçe yazım `250,00 ₺`dir:
ondalık ayırıcı virgül, simge sonda. Türk bir restoran sahibi için bu, her
satırda görünen küçük bir yabancılık.

**Bu turda YAPILMADI:** para biçimlendirme `docs/Money` sözleşmesine ve
mevcut testlere bağlı; değiştirmek ayrı bir karar ve ayrı bir pakettir.
Sıradaki döngüde ölçülecek.

---

## Döngü 6 — Acemi kullanıcı ve erişilebilirlik

**Bulgu.** `docs/101`'in personası (Adana'dan gelmiş kebapçı) için simge-only
düğmeler öğrenilmesi gereken bir dildir. `aria-label`'lar doğruydu — yani
ekran okuyucu kullanan biri iyi durumdaydı — ama GÖZLE bakan acemi için `✎`
ile `✕` arasındaki fark bir tahmindir.

**Uygulanan:** yıkıcı eylem artık metinle ("Kaldır") ve renkle işaretli bir
menü satırıdır; tahmin gerektirmez. Ad düzenleme ise simge bile istemez —
ada tıklanır.

**Sıradaki döngü:** oklara üzerine-gelince ipucu (`TooltipHint` katalogda
var) ve dokunmatikte 44 px hedef doğrulaması.

---

## Kapanmayan liste (sıradaki tur)

| # | İş | Döngü | Durum |
| --- | --- | --- | --- |
| 1 | Satırdaki beş ikincil kontrolün yeniden dizilmesi | 2 | ✅ FF-102 |
| 2 | Satır ve başlık grameri (sıra → ad → fiyat → durum → eylem) | 5 | ✅ FF-102 |
| 3 | Ekleme düğmelerinin düğme gibi görünmesi | 3 | ✅ FF-102 |
| 4 | "Ürün ekle"nin kategori başlığına da taşınması | 3 | ⬜ |
| 5 | Silmeyi çöpe alma + geri alma (arka uç kararı) | 4 | ⬜ |
| 6 | Türkçe para biçimi (`250,00 ₺`) | 5 | ⬜ |
| 7 | Ok düğmelerine ipucu, dokunmatik hedef ölçümü | 6 | ⬜ |

Her madde ayrı bir pakettir ve kendi kanıtıyla kapanır.
