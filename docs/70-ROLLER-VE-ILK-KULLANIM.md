# 70 — "Editör" hiçbir şeyi düzenleyemiyordu

**Kaynak:** AI-first raporu §9.8 (Team), §6.1 (ilk aktivasyon yolculuğu) ·
`docs/61` E1, E5, E6

MVP çerçevesinde iki boşluk kaldı ve ikisi de **adı doğru, davranışı yanlış**
sınıfındaydı.

## 1. Davet edilen editör salt okunurdu

`MembershipRole::Editor` uzun süre yalnız bir ETİKETTİ. İzin listesi `Member`
ile aynıydı:

```php
MembershipRole::Member, MembershipRole::Editor => [
    WorkspaceView, MenuView, QrView, AnalyticsView,
]
```

Yani sahibi birini "Editor" olarak davet ediyor, o kişi giriyor ve **hiçbir
şeyi düzenleyemiyordu**. Rolün adı, yapabildiği şeyi yanlış anlatıyordu.

Enum'daki yorum bunu zaten söylüyordu: *"Editor stays denied WorkspaceManage
and carries no privilege beyond Member."* O gün muhafazakâr bir karardı;
kalıcı hâle geldiğinde bir yalana dönüştü.

Ayrıca **davet edilebilecek tek rol** `editor`'dü (`in:editor`). Sahibin,
faturaya dokunamayan ama günlük operasyonu yürütebilen birini davet etmesinin
yolu yoktu.

### 1.1 Şimdi

Planın tarif ettiği üç rol (§9.8):

| Rol | Yapar | Yapmaz |
| --- | --- | --- |
| Owner | Her şey | — |
| Manager | Menü, şube, karekod, yayınlama; faturayı görür | Fatura yönetimi, güvenlik kanıtı |
| Editor | İçerik düzenler | Yayınlama, şube/marka ayarı, fatura |
| Member | Salt okunur — **yalnız eski kayıtlar** | — |

Yayınlama iznini `Editor`'dan ayırmak kasıtlıdır: içerik düzenlemek geri
alınabilir bir iştir, yayınlamak misafirin gördüğü menüyü değiştirir. İkisini
aynı role vermek, en kolay yetkiyi en geniş sonuçla eşleştirmek olurdu.

`Member` genişletilmedi. Onu da editör yapmak, mevcut kullanıcılara sessiz bir
yetki artışı vermek olurdu.

### 1.2 Sahiplik davet edilemez

`MembershipRole::invitable()` yalnız `Editor` ve `Manager` döner. Sahiplik
davetle verilmez, **devredilir** — ayrı bir akışı ve ayrı bir sonucu vardır.
Liste tek yerde durur: elle yazılmış bir doğrulama listesi, yeni bir rol
eklendiği gün unutulur ve rol ürünün yarısında var yarısında yok olurdu.

### 1.3 Hiçbir test bunu tutmuyordu

Rol izinleri değiştirildikten sonra **1011 testin tamamı geçti**. Yani hiçbir
test editörün menü düzenleyemediğini donduruyordu; bir iznin yokluğu, varlığı
kadar önemli olduğu hâlde ölçülmüyordu.

`RoleBoundariesTest` sekiz sınırı donduruyor — ve yarısı bir iznin
**YOKLUĞUNU** ölçüyor.

### 1.4 Ekranda rolün ne yaptığı yazar

"Editor" kelimesi tek başına yayınlayıp yayınlayamayacağını söylemez. Seçicinin
altında rolün sınırı yazılı; sahibi yanlış kişiye yanlış yetkiyi vermesin.

Varsayılan `editor`'dür, `manager` değil: acele eden bir sahip **en az** yetkiyi
vermiş olur. Tersi, en geniş yetkiyi kazara dağıtmak olurdu.

## 2. İlk kullanım ekranında ölü bağlantılar

Home'daki kurulum bölümü beş satır gösteriyordu ve her satırın etiketi bir
bağlantıydı: `#brand`, `#locations`, `#menu`, `#publication`.

Uygulama adres tabanlı gezintiye geçtiğinden beri bu bağlantılar **hiçbir şey
yapmıyordu**: o kimlikte bir öğe yok, tarayıcı kaymıyor, ekran duruyor.
Kullanıcının gördüğü İLK ekranda beş ölü kontrol vardı — ve bir test onları
`href="#brand"` diye donduruyordu.

### 2.1 Durum listesi değil, görev listesi

Plan §6.1 ilk kullanımda bir **görev listesi** istiyor: hangi adımlar var,
sırası ne, hangisi tamamlandı.

Önceki hâli yalnız DEĞER gösteriyordu — "Publication: Not connected yet". Bu
bir durum bildirimi, yol tarifi değil.

Şimdi her satır tamamlanma işareti taşıyor, sıradaki adım ekran okuyucuya
"Next step" olarak duyuruluyor ve etiket gerçek bölüme götürüyor. QR satırı
artık yayın ekranına değil kendi ekranına gidiyor.

**Menü adımı, menünün varlığıyla tamamlanmış sayılmaz:** içi boş bir menü
yayınlanamaz ve misafire gösterecek bir şeyi yoktur. Adım en az bir ürün
varken tamamlanır.

### 2.2 İşaret renkle verilmiyor

Tamamlanma işareti bir karakter (`✓` / `○`) ve yanında ekran okuyucu için
metin karşılığı var. Renk, yüksek kontrast modunda ve renk körlüğünde
kaybolur.

Sütunun genişliği `ch` ile ölçülüyor, pikselle değil: içindeki şey bir
karakter, ve 320 piksellik ekranda sabit piksel genişliği yazı boyutuyla
ölçeklenmez. 320px-first kapısı bunu `w-4` denemesinde yakaladı.

## 2.3 Bir ölü bağlantı daha

Süpürme kalan tek `href="#"` örneğini de buldu: Home'un boş durumundaki
**tek çıkış yolu**. Menüsü olmayan bir kullanıcının o ekranda yapabileceği tek
şey, hiçbir şey yapmayan bir bağlantıydı.

`PageState`'e çevrildi — çıkış yolu artık tip düzeyinde zorunlu. Sayfanın `h1`
başlığı ayrıca korundu: dolu hâlde `PageHeader` çiziyor, boş hâlde kaybolursa
ekran okuyucunun sayfalar arası gezinme yolu kopardı.

Bu değişiklik bir test kusurunu da açığa çıkardı: dosyanın `beforeEach`'i
adresi `window.location.pathname`'e — yani BİR ÖNCEKİ testin gezindiği yere —
çekiyordu. Gerçekten gezinen bir test eklendiği anda sonraki testler Home
yerine o ekranda başladı ve sebebi görünmedi. Artık köke çekiliyor.

## 3. Kalan

- **Lokasyon bazlı erişim kapsamı** (`docs/50` §9.8): üyelikler bugün çalışma
  alanı geneli. Şube bazlı yetki, yetkilendirme alanında yeni bir boyut
  demektir ve MVP dışıdır — planın kendisi de bunu "location access" olarak
  ayrı bir alan sayıyor.
- Üye listesinde rol değiştirme: davet rolü seçiliyor, sonradan değiştirme yok.
