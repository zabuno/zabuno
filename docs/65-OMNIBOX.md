# 65 — Omnibox, ve bağlı olmayan bir AI merkezinin kaldırılması

**Tur:** 4/6 · **Kaynak:** SaaS panel kabuk mimarisi §11, §17, §18 ·
AI-first raporu §10 · `docs/61` B1–B5, G2

## 1. Ne vardı

Üst çubukta "AI komut merkezi" açan bir düğme vardı. Açtığı çekmecede:

- kalıcı olarak **devre dışı** bir komut kutusu,
- "Etkilenen kayıtlar: yok",
- "Son komutlar: yok",
- **devre dışı** bir "Onayla" düğmesi,
- ve gerçekten çalışan dört gezinti kısayolu.

Beş testi vardı ve beşi bu davranışı "dürüst yer tutucu" olarak donduruyordu.

Dürüstlük iyiydi: hiçbir şey uydurulmuyordu. Ama plan başka bir şey söylüyor:

> AI sağlayıcısı bağlı değilse AI girişi gizlenebilir. Bu yüzey kullanıcıya
> değer değil, geliştirilmemiş özellik gösterir. — `docs/50` §17

Ve `docs/61` bu maddeyi (G2) **✅ olarak işaretliyordu**. Envanter yanlıştı;
bu turda düzeltildi. Boş AI kartları sayfalardan kaldırılmıştı, ama kabuk
seviyesindeki merkez duruyordu.

## 2. Ne geldi

Deterministik bir omnibox. Üç grup, üçü de gerçek:

| Grup | İçerik |
| --- | --- |
| **Go to** | Çalışma alanının ekranları |
| **Create** | Ön koşulu sağlanan oluşturma hedefleri (`docs/64`) |
| **In this workspace** | Şubeler, menü kategorileri ve ürünler |

`Cmd/Ctrl + K` her yerden açar. Tetikleyici üst çubukta durur.

### 2.1 Varsayılan mod deterministiktir

Kullanıcının yazdığı metin **sessizce bir AI istemine dönüşmez**. Ne aradığını
bilen biri, cevabın nereden geldiğini de bilmelidir.

### 2.2 Kapsam görünürdür

Diyaloğun tepesinde çalışma alanı ve seçili şube yazar. Kullanıcı, seçtiği
şeyin hangi kiracı ve hangi şube üzerinde iş göreceğini tahmin etmek zorunda
kalmaz (`docs/50` §11).

### 2.3 Sorgu boşken kayıt gösterilmez

Bir çalışma alanındaki bütün ürünleri listelemek bir cevap değil, ikinci bir
liste ekranıdır. Boş hâlde yalnız gidilecek yerler ve oluşturulabilecek şeyler
durur — ikisi de kısa ve sabittir.

### 2.4 Hiçbir ağ isteği yapılmaz

Arama, ZATEN YÜKLENMİŞ veriden yapılır: şubeler ve seçili şubenin menü ağacı.
Sunucuda bir arama uç noktası yok. Olmayan bir aramayı varmış gibi göstermek,
boş dönen her sorguda kullanıcıya "bu kayıt yok" dedirtirdi — oysa doğrusu
"burada aranmadı" olurdu.

Kapsamın sınırı bu yüzden grup başlığında yazılı: **In this workspace**.

## 3. AI modu neden yok

Plan omnibox'ta beş mod tarif ediyor: Search, Go to, Create, Run command, Ask
Zabuno. İlk üçü uygulandı.

**Ask Zabuno yok**, çünkü bağlı bir AI sağlayıcısı yok — arka uçta ne bir
sağlayıcı yapılandırması, ne bir kapı, ne bir uç nokta var. Modu göstermek,
planın kendi kuralını çiğnerdi.

**Run command yok**, çünkü çalıştırılacak deterministik bir komut kümesi
tanımlı değil. Plan'ın riskli komut listesi (publish, delete, toplu fiyat
değişimi, rol değişimi) bir inceleme yüzeyi ister; o yüzey de yok. Yarısı
yapılmış bir komut modu, en tehlikeli işleri en hızlı yola koyardı.

İkisi de eklendiğinde omnibox'a dördüncü ve beşinci grup olarak girer.

## 4. Silinen test dosyası

`WorkspaceApp.aiCommand.test.tsx` kaldırıldı ve yerine
`WorkspaceApp.omnibox.test.tsx` geldi. Silinen testler, silinen bir yüzeyin
davranışını donduruyordu; onları yeşil tutmak için yüzeyi yaşatmak, testin
kuyruğun köpeği sallaması olurdu.

Yeni dosya sekiz şey donduruyor: tek tetikleyici, görünür kapsam, deterministik
gruplar, **AI girişinin bulunmaması**, boş sorguda kayıt gösterilmemesi, boş
sonucun söylenmesi, hiç ağ isteği yapılmaması, `Cmd/Ctrl+K`, ve Escape'te
odağın tetikleyiciye dönmesi.

## 5. Bir iddia adı değil kuralı ölçüyor artık

`shellContext` testinde "üst çubukta adında *search* geçen düğme olmasın"
deniyordu. Gerekçesi doğruydu: orada yalnız devre dışı durmak için var olan bir
arama yer tutucusu vardı.

Artık **çalışan** bir arama girişi var ve adı doğal olarak "Search, go to, or
create". Adı yasaklamak, kuralı değil kuralın eski belirtisini korumak olurdu.
İddia kuralın kendisini ölçüyor: üst çubukta yalnız devre dışı olmak için var
olan bir kontrol bulunmaz — ve arama girişi etkin durumdadır.

## 6. Kalan

- Klavye ile sonuçlar arasında ok tuşlarıyla dolaşım (bugün her sonuç ayrı bir
  düğme; sekme ile erişilebilir, ama liste dolaşımı yok).
- Sunucu tarafı arama: kapsam bugün yüklü veriyle sınırlı.
- AI ve komut modları, sağlayıcı ve inceleme yüzeyi geldiğinde.
