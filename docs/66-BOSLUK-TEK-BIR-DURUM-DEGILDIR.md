# 66 — "Veri yok" tek bir durum değildir

**Tur:** 5/6 · **Kaynak:** AI-first raporu §3.3, §11.7 · SaaS kabuk raporu §21
· `docs/61` D2, E4

## 1. Analitik ekranı sıfır gösteriyordu

Menüsü olmayan, menüsü yayınlanmamış, yayınlanmış ama hiç taranmamış ve
seçtiği aralıkta etkinlik olmayan dört kullanıcı **aynı ekranı** görüyordu:

```text
QR resolves    0
Menu opens     0
```

Teknik olarak dürüsttü — hiçbir şey uydurulmuyordu. Ama kullanıcıya hiçbir şey
söylemiyordu: **neden** sıfır olduğunu ve **şimdi ne yapacağını** değil.

Dördünün çıkış yolu farklıdır. Yayınlanmamış bir menüsü olan kişiye "QR kodunu
yazdır" demek, atlayamayacağı bir adımı atlamasını istemektir.

## 2. Dört ayrı boşluk

| Durum | Ne söylenir | Çıkış yolu |
| --- | --- | --- |
| Menü yok | Analitik ilk menüyle başlar | Menüyü kur |
| Menü var, yayınlanmamış | Yayında olmayan menüyü müşteri açamaz | Önizle ve yayınla |
| Yayında, hiç taranmamış | İlk tarama bekleniyor | QR kodlarını gör |
| Seçili aralıkta yok | Bu dönemde etkinlik yok | Son 30 günü göster |

Sıra önemlidir: **en erken engel önce gelir**, çünkü kullanıcıya gösterilecek
çıkış yolu odur.

Son satır bir ayrıntı taşıyor: çıkış yolu **bu sayfanın içindedir**. Aralığı
genişletmek için başka bir ekrana gitmek gerekmiyor; cevabın burada olduğu bir
soruda kullanıcıyı yolculuğa çıkarmak gereksiz sürtünmedir.

### 2.1 "Bu aralıkta yok" ile "hiç yok" nasıl ayrılıyor

Sunucudan yalnız seçili aralık isteniyor; ikinci bir istek atmadan "hiç yok"
bilinemez. Ayrım şöyle yapılıyor: aralık **30 gün** ise ve sonuç sıfırsa, bu
"henüz hiç taranmamış"tır; daha dar bir aralıksa "bu aralıkta yok"tur ve
genişletmek önerilir.

Bu bir tahmin değil, ölçülebilir bir kural: kullanıcı önerilen aralığa
geçtiğinde cevabın hangisi olduğunu görür.

### 2.2 Yeni bir uç nokta eklenmedi

Yayın durumu zaten yüklü (`useCurrentPublication`), menü ağacı zaten yüklü.
Ayrımı yapmak için ek bir istek gerekmedi.

## 3. Üç yeni sayfa durumu

`PageState` üç kind daha tanıyor (`docs/61` D2):

- **`partial`** — veri geldi ama bir parçası yüklenemedi. `error` değildir,
  çünkü ekranda kullanılabilir bir şey var; `empty` de değildir, çünkü boş
  değil. İkisinden birine yuvarlamak ya var olan veriyi gizler ya da eksiği
  görünmez kılar.
- **`degraded`** — sistem çalışıyor ama tam kapasitede değil. Kullanıcı için
  anlamı "bekle ve yeniden dene" değil, "gördüğün şey güncel olmayabilir"dir.
  Hata olarak sunmak, düzeltilecek bir şey varmış izlenimi verir.
- **`success`** — tamamlanmış bir iş. Tek görsel farkı var ve gerekli: kesikli
  çerçeve "burada bir şey eksik" der ve tamamlanmış bir iş için yanlış
  sinyaldir. `partial` ve `degraded` kesikli kalır — ikisinde de gerçekten
  eksik bir şey vardır.

Hiçbiri `role="alert"` değildir. Yalnız `error` arızadır.

## 4. Değiştirilen üç test

Üç test "sıfırlardan oluşan bir ızgara" bekliyordu. Onları yeşil tutmak için
ızgarayı yaşatmak, testin kuyruğun köpeği sallaması olurdu. Üçü de artık ÇIKIŞ
YOLUNU ölçüyor.

Bir test de gezinti verilmediğinde ne olduğunu donduruyor: durum eylemsiz
kalmaz, **neden** eylem sunulamadığını söyler ve kullanıcıyı kenar çubuğuna
yönlendirir. Bu cümle açıklamanın tekrarı değildir — ilk denemede öyleydi ve
test "aynı metin iki kez" diyerek yakaladı.

## 5. Kalan

- `partial` ve `degraded` durumları tanımlandı ama henüz hiçbir ekran
  kullanmıyor. Kullanılacakları yer belli: kısmî yüklenen medya kütüphanesi ve
  gecikmiş ölçüm kuyruğu. İkisi de bugün yok.
- Şablon kataloğu (`docs/61` D3) bu turda yapılmadı; sayfalar zaten
  `WorkspacePageFrame` ve `PageState` üzerinden ortak bir iskelet paylaşıyor.
  Katalogu bir soyutlama olarak çıkarmak, tekrarın ölçüldüğü bir turda
  yapılmalı — henüz ölçülmedi.
