# 116 — Puanlama algoritması: sürümlü kural dosyası ve dış kaynaklar

**Sahibin kararı (2026-09-05):** *"Puanlamanın KPI'ları, OKR'ları bir
algoritma dosyasına bağlıdır. Bu algoritma mevcut verilerle zamanla
geliştirilebilir. Daha sonra Zomato + Swarm + Google Maps + sosyal medya
uygulaması geliştirilecek ve buraya bağlanacak, gereken önlemleri al."*

Bu belge `docs/114` §3 Dalga 4'ün yerine geçer.

## 0. Kararın anlamı — puan bir alan değil, bir çıktı

"Beş yıldızın ortalaması" bir hesap değildir, bir **varsayımdır**: her oyun
eşit ağırlıkta olduğunu, zamanın önemsiz olduğunu ve kaynağın fark
etmediğini varsayar. Sahibin kararı bu varsayımı reddediyor.

Sonuç: puan bir **sütun değil**, sürümlü bir kuralın çıktısıdır. Bu ayrım
şimdi yapılmazsa sonradan yapılamaz — çünkü ortalamayı bir sütuna yazan bir
sistem, ham sinyalleri saklamayı gereksiz görür ve o sinyaller bir daha
geri gelmez.

## 1. Şimdi alınmazsa sonradan alınamayacak dört önlem

Bunlar bu belgenin var oluş sebebidir. Dördü de **ilk göçte** olmak
zorunda; sonradan eklemek "geçmiş satırlara ne yazacağız?" sorusunu doğurur
ve o sorunun her cevabı bir uydurmadır.

### Ö1 — Her sinyal KAYNAĞINI taşır

`source` alanı ilk günden: `guest_scan` (masadan, karekodla),
`external_zomato`, `external_swarm`, `external_google`, `social_app`.

Bugün tek kaynak var. Ama alan yoksa, ikinci kaynak geldiğinde eski
satırların hepsi "kaynağı bilinmiyor" olur ve ağırlıklandırma o günden
öncesini kapsayamaz.

### Ö2 — Ham sinyal ile türetilmiş puan AYRI yaşar

`rating_signals` **değişmez** (append-only): kim (takma), ne zaman, hangi
ürün, kaç puan, hangi kaynak, hangi bağlam.
`rating_scores` **türetilmiştir** ve yeniden hesaplanabilir.

Algoritma değiştiğinde ham veri yeniden işlenir. Ortalamayı satır üstüne
yazan bir sistemde bu imkânsızdır: eski ortalamanın hangi girdilerden
geldiği kaybolmuştur.

### Ö3 — Her türetilmiş puan ALGORİTMA SÜRÜMÜNÜ taşır

`algorithm_version` alanı. Yoksa "bu ürünün puanı neden düştü?" sorusunun
cevabı yoktur — kural mı değişti, oy mu geldi, ayırt edilemez.

Sürüm damgası aynı zamanda **geriye dönük dürüstlüğün** aracıdır: eski bir
puanı yeni kuralla yeniden hesaplayıp "hep böyleydi" demek, geçmişi
yeniden yazmaktır.

### Ö4 — Dış kaynak kimliği EŞLEME TABLOSUNDA yaşar

`external_references`: bizim varlığımız (şube ya da ürün) ↔ dış sistemdeki
kimlik. Eşleme **kesin değildir** ve öyle davranılmaz: her satır bir
**güven düzeyi** ve **kim eşledi** taşır (otomatik mi, sahip mi
doğruladı).

Bu tablo olmadan dış veri ancak isim benzerliğiyle bağlanır — ve "Lezzet
Sarayı" adında üç restoran vardır. Yanlış eşleme, başkasının puanını bizim
restoranımızda göstermektir.

## 2. Algoritma dosyası

**Konum:** `config/rating-algorithm/v{N}.php` — depoda, sürümlü, gözden
geçirilebilir, testli.

**Çalışma zamanında düzenlenebilir bir blob DEĞİL.** Sebebi bu deponun
kendi kuralı: ölçüm ve para etkileyen bir kural, gözden geçirme ve testten
geçmeden değişmemeli. "Panelden ayarlanabilir algoritma", ilk yanlış
değerde sessizce her ürünün puanını değiştirir.

**Dosya neyi taşır:**

| Bölüm | Ne | Neden dosyada |
| --- | --- | --- |
| `kpi` | Neyi iyileştirmeye çalışıyoruz (ör. *"misafirin gerçekten yediği ürünü doğru sıralamak"*) | Optimize edilen şey yazılı olmazsa, algoritma kimsenin kabul etmediği bir hedefe doğru kayar |
| `okr` | Ölçülebilir hedef ve mevcut değer | Hedef yoksa "iyileşti" denemez |
| `weights` | Kaynak başına ağırlık | En sık değişecek yer |
| `recency` | Zaman sönümü | Üç yıllık oy bugünkü tabağı anlatmaz |
| `thresholds` | Gösterim eşiği, güven aralığı | §3 |
| `abuse` | Kötüye kullanım kuralları | §4 |

**KPI ve OKR dosyada yaşar, kodda değil** — sahibin kararı bu. Algoritma
değiştiğinde neyin iyileştirilmeye çalışıldığı da aynı dosyada değişir ve
aynı gözden geçirmeden geçer.

**Sürüm yükseltmek bir PAKETTİR:** yeni dosya, yeni sürüm numarası, eski
dosya **silinmez**. Eski puanlar eski sürümle açıklanabilir kalır.

## 3. Gösterim eşiği — `docs/114`'ten devralınan kural, korunuyor

**Eşik altında puan gösterilmez.** Üç kişinin verdiği beş yıldız bir bilgi
değildir; gösterilirse yeni ürün her zaman en iyi görünür.

Eşik **sayı değil, güven** meselesidir ve algoritma dosyasında yaşar.
Eşiğin altında ekran "henüz yeterli değerlendirme yok" der — sıfır yıldız
**değil**, çünkü sıfır bir ölçümdür ve bilinmeyenin yerine geçemez.

## 4. Kötüye kullanım — ilk günden

- Ziyaretçi anahtarı + ürün başına tek oy.
- **Oy vermek için o masadan karekod okutmuş olmak gerekir.** Bu, ürünün
  elindeki en güçlü sinyaldir ve rakip bir platformun sahip olmadığı
  şeydir: masadan gelen oy, dışarıdan gelen oydan daha ağırdır çünkü o kişi
  gerçekten oradaydı.
- Hız sınırı ve ani yığılma tespiti.
- **Sahip puanı silemez** (`docs/114`'ten devralındı). Yanıt verebilir,
  kaldıramaz — silebiliyorsa ortalama bir pazarlama sayısıdır.

Bir sinyal kötüye kullanım sayıldığında **silinmez**, işaretlenir:
`rating_signals` değişmezdir. Algoritma onu ağırlıklandırmada dışarıda
bırakır. Silmek, yanlış işaretlemenin geri dönüşünü de silerdi.

## 5. Dış kaynaklar — Zomato · Swarm · Google Maps · sosyal uygulama

### Mimarî: adaptör, çekirdek değil

Her kaynak bir **adaptör**tür ve tek bir porta konuşur. Çekirdek hiçbir
sağlayıcının adını bilmez. Bu depoda aynı desen zaten var (AI sağlayıcıları,
posta taşıyıcısı, virüs tarayıcı) — ikinci bir desen kurulmaz.

Yeni bir kaynak eklemek: bir adaptör + `source` enum'una bir değer +
algoritma dosyasına bir ağırlık. Çekirdek değişmez.

### Beş önlem

**D1 — Dış puan BİZİM puanımız değildir ve öyle gösterilmez.** Ekranda
kaynağı yazılı olur. Kaynağını gizleyip tek bir sayıda eritmek, misafire
bizim ölçmediğimiz bir şeyi bizim ölçtüğümüz gibi sunmaktır.

**D2 — Hukuk ve kullanım şartları kapsam dışı DEĞİL, ön koşuldur.** Her
platformun veri kullanım şartı vardır ve bazıları kazımayı (scraping) açıkça
yasaklar. **Resmî API'si olmayan kaynağa adaptör yazılmaz.** Bu bir
mühendislik tercihi değil: şartları ihlal eden bir bağlantı, kapatıldığı gün
ürünün puanını da götürür.

**D3 — Dış veri ÖNBELLEKLENİR, sahiplenilmez.** Saklama süresi ve yeniden
çekme aralığı algoritma dosyasında; kaynak bağlantısı kesilirse veri
**yaşlanır ve düşer**, donup kalmaz. Ekran "şu tarihte alındı" der.

**D4 — Eşleme sahibin onayından geçer.** Otomatik eşleme bir *öneri*dir.
Sahip "bu benim restoranım değil" diyebilmelidir; diyemezse başkasının
puanını taşıyoruz demektir (§1 Ö4).

**D5 — Kişisel veri sınırı dış kaynakta da geçerlidir.** Yorum yazarının
adı, fotoğrafı ve profili çekilmez. Alınan şey puan, tarih ve — varsa —
anonimleştirilmiş metindir.

### Sosyal uygulama — ayrı bir ürün

"Social media app" bir kaynak değil, **ayrı bir üründür**: kimlik, içerik
moderasyonu, bildirim ve kendi hukuki yüzeyi vardır. Buraya bir *adaptör*
olarak bağlanır; bu belge onun tasarımını kapsamaz ve kapsamamalıdır.

Bugün alınacak tek önlem: `source` enum'unda yeri açık, eşleme tablosu onu
da taşıyabilir.

## 6. Görev sırası

| # | İş | Neden bu sırada |
| --- | --- | --- |
| P1 | `rating_signals` (değişmez) + `source` + bağlam | Ham veri olmadan hiçbir şey hesaplanamaz |
| P2 | Algoritma dosyası v1 + KPI/OKR + testler | Kural, ilk puandan önce yazılı olmalı |
| P3 | `rating_scores` + sürüm damgası + yeniden hesaplama komutu | Yeniden hesaplanamayan puan, düzeltilemeyen puandır |
| P4 | Misafir oy verme ucu (karekod bağlamı şart) + kötüye kullanım | Ö1 ve §4 |
| P5 | Gösterim (eşikle) — misafir ve panel | Eşik altında çizilmez |
| P6 | Sahip yanıtı | Silme yok |
| P7 | `external_references` + eşleme onayı | Dış kaynaktan ÖNCE |
| P8 | İlk dış adaptör (resmî API'si olan) | D2 |

**P2 P4'ten önce gelir ve bu kasıtlı:** ilk oy toplanmadan kuralın yazılı
olması gerekir, yoksa kural toplanan veriye göre şekillenir ve ölçtüğü şeyi
doğrulamış olur.

## 7. Bu belgenin kendi gerekçe süresi

`docs/109` §8.6: yukarıdaki her karar bugünün ölçümüdür. Özellikle iki
tanesi yeniden bakılmayı hak eder:

- **Ağırlıkların dosyada olması**, sahip onları gerçekten değiştirmek
  isterse dar ve denetlenen bir panel yüzeyine dönüşebilir — ama o gün
  geldiğinde bile değişiklik bir kayıt bırakmalı.
- **Dış puanın ayrı gösterilmesi**, kaynak sayısı arttığında ekranı
  kalabalıklaştırabilir; o zaman birleştirme tartışılabilir ama kaynak
  bilgisi kaybolamaz.
