# 149 — Masaüstünün kendi bileşenleri

**Paket:** `FF-256` · **Öncesi:** `docs/54` (adaptive cihaz yükleme), `docs/60`
(bağlam paneli), `docs/115` S4/S5 (sipariş kuyruğu ve mutfak monitörü) ·
**Sonrası:** `docs/151` (2. paket) · **Global kural:**
`TOUCH-FIRST-INTERFACE`

> **§8 ve §9 KISMEN AŞILDI.** `docs/151` (FF-259) §8'in adıyla saydığı M1
> borcunu kapattı (kataloglar artık cihaz tanıyor), §9'un ekran listesinden
> üçünü daha dönüştürdü (`media`, `team`, `ratings`) ve masaüstüne kendi stil
> katmanını verdi. Bu belgenin DESENİ geçerliliğini koruyor; güncel sayılar
> ve kalan ekran listesi `docs/151`'dedir.

Sahibin cümlesi (2026-09-08):

> *"mobile first yaklaşık okey, ayrıca desktop ihtiyaçlarını karşıla, tüm
> components desktop tarafında mobile components var, eğer desktop ise,
> desktop'ın kendi (bağımsız) sadece desktop'da yüklenen, components'leri
> olmalıydı."*

Bu belge o cümleyi ÖLÇÜLEBİLİR bir sözleşmeye çevirir. Bir program başlangıcıdır:
deseni kurar ve **bir** ekranda gerçekten uygular; kalan ekranlar §9'da listelidir.

---

## 1. Bugün ne vardı — ölçüm

`docs/54` cihaz başına ayrı giriş noktası kurdu ve bu gerçekten çalışıyor: seçim
sunucuda (`App\Support\Device\DeviceClass`), tarayıcıda medya sorgusuyla değil.
Ama ayrım **yalnız kabuktaydı**. Bu paketten ÖNCE, kaynak kapanışı ölçüldüğünde:

| | dosya | kaynak bayt |
|---|---:|---:|
| masaüstü kapanışı | 238 | 2.031.261 |
| mobil kapanışı | 231 | 1.998.921 |
| **ortak** | **229** | **1.989.205** |
| yalnız masaüstü | 9 | 42.056 |
| yalnız mobil | 2 | 9.716 |

Yani masaüstü kapanışının **%97,9'u** iki cihazda da aynıydı, ve o ortak kütlenin
1.242.798 baytı (131 dosya) `components/workspace/pages/**` altındaki ekranlardı —
hepsi 320 tabanı için yazılmış, masaüstünde aynen kullanılıyordu. Sahibin gördüğü
şey buydu: ayrım vardı ama **bir parmak kalınlığındaydı**.

Derlenmiş paket ölçüsüyle aynı gerçek:

| | ilk yükleme | tüm parçalar (lazy dâhil) |
|---|---:|---:|
| masaüstü | 663.765 B (193.912 gzip) | 1.590.487 B / 115 parça (428.922 gzip) |
| mobil | 641.224 B (187.062 gzip) | 1.575.440 B / 115 parça (425.153 gzip) |

**Parça SAYISI aynı**: masaüstünün indirdiği her ekran parçası mobilin de indirdiği
parçaydı. Fark 15.047 bayt — masaüstü paketinin **%0,95'i**.

Ters yön ise zaten temizdi: **mobil paket masaüstüne özgü sıfır bayt indiriyordu**
ve `scripts/adaptive-bundle-gate` bunu her koşuda kanıtlıyordu. Kusur "mobile
masaüstü kodu sızıyor" değildi; **masaüstünün kendine ait bir şeyi yoktu**.

---

## 2. Giriş kipi birinci sınıf bir ayrımdır — ekran genişliği değil

Bir bileşenin cihaza özgü olup olmadığına **genişlikle** karar verilmez. Karar
GİRİŞ KİPİYLE verilir, çünkü ayrışan şey düzen değil etkileşim modelidir:

| | işaretleyici (masaüstü) | dokunma |
|---|---|---|
| `hover` / imleç durumu | **var ve anlamlıdır** | **yoktur** |
| sağ tık bağlam menüsü | var | yok |
| çift tık, klavye kısayolu | var | yok |
| aynı anda görünen nesne | çok | bir |
| parmağın ekranı kapatması | yok | **var** |
| uzun basma / kaydırma | pahalı | ucuz |

Bir işi yapmanın yolu bu tabloda değişiyorsa **ayrı kod yolu yazılır**.

**"Tek kod, medya sorgusuyla gizle" bir çözüm DEĞİLDİR.** Gizlenen şey yine
indirilir, yine ayrıştırılır, yine odaklanılabilir ve yine bakım ister. `docs/54`
bunu paket düzeyinde çoktan reddetti; bu belge aynı reddi bileşen düzeyine indirir.

---

## 3. Ayrım bir KOŞUL değil, MODÜL SINIRIDIR

Aşağıdakilerin hiçbiri cihaz ayrımı sayılmaz ve incelemede reddedilir:

```tsx
// HAYIR — kod iki pakete de iner, yalnız çizilmez.
if (deviceClass === 'desktop') { return <DesktopQueue />; }

// HAYIR — aynı şey, bayrakla.
{isDesktop ? <DesktopQueue /> : <MobileQueue />}

// HAYIR — genişlikle cihaz seçimi. Cihaz kararı SUNUCUDA verilir.
<div className="hidden md:block"><DesktopQueue /></div>
```

Tek geçerli biçim, cihaza özgü modülün **yalnız kendi giriş noktasından**
ulaşılabilir olmasıdır. Bileşen değil, bir **çizici** ya da **harita** geçirilir:

```tsx
// workspace.desktop.tsx — mobil giriş bu satırı hiç görmez
<WorkspaceApp pageOverrides={desktopPages} inspectors={desktopInspectors} />
```

Paylaşılan kod cihaza özgü bir dosyayı **adıyla anmaz**. Andığı anda o ad
paylaşılan pakete girer ve ayrımın doğruluğu tek bir `type` kelimesine kalır —
`docs/60`'ta tam bu oldu.

---

## 4. Ortak kalan: iş mantığı. Ayrışan: sunum ve etkileşim

**İki kopya iş mantığı yazmak bu programın amacı değil ve YASAKTIR.** Cihaza göre
ayrışması gereken şey, aynı gerçeğin nasıl gösterildiğidir; gerçeğin kendisi değil.

| paylaşılır | ayrışır |
|---|---|
| veri erişimi (`useOrderFeed`) | yerleşim ve yoğunluk |
| yazma yolu (`changeOrderStatus`) | klavye ve işaretleyici davranışı |
| ürün kararları (`queueEmptyState`) | seçim ve toplu işlem |
| cümleler ve biçimleme (`orderPresentation`) | `hover` ile açığa çıkan eylemler |
| doğrulama, i18n, tasarım jetonları | bağlam menüsü |

Bir cihaz bileşeni içinde **`fetch` görünüyorsa desen yanlış uygulanmıştır**:
ikinci bir okuma yolu, aynı akşam iki ekranın farklı liste göstermesi demektir.

Bu paket bu yüzden `queueEmptyState.ts` ve `FeedStatusLine.tsx` dosyalarını
ortaya çıkardı: boş kuyruğun sebebi bir ÜRÜN kararıdır ve her yüzey kendi başına
karar verseydi, telefonda "bugün sipariş yok", masaüstünde "planında yok"
yazabilirdi.

---

## 5. Mekanizma: cihaza özgü sayfa haritası

`resources/js/components/workspace/pages/pageOverride.ts`

```ts
export type WorkspacePageRenderer = (ctx: WorkspaceSectionRuntimeContext) => ReactNode;
export type WorkspacePageOverrideMap = Record<string, WorkspacePageRenderer>;
```

**Neden bölüm kaydı (`*.section.tsx`) olamaz.** Kayıtlar
`import.meta.glob('../pages/*.section.tsx', { eager: true })` ile TOPLUCA
okunur; yani her kayıt iki pakete birden girer. Cihaza özgü bir ekran yeni bir
kayıt dosyası olsaydı, yazıldığı anda telefona da inerdi.

Ayrım bu yüzden kayıtta değil **giriş noktasında** yapılır. `renderActiveSection`
haritayı üçüncü parametre olarak alır; harita yoksa (telefon) her bölüm kendi
kayıtlı çizimiyle — yani **320 tabanıyla** — çizilir. Mekanizmanın tabana maliyeti
sıfırdır ve bu bir testle donduruldu.

**Harita İZİN KAPISININ ARDINDADIR.** Önce sorulsaydı, aynı hesap masaüstünden
girdiğinde bir ekranı görür, telefondan girdiğinde görmezdi — yetki sınırı cihaza
göre değişirdi. Sunucu her ucu yine doğrular, ama iki paketin AYNI kararı vermesi
kaydın işidir.

Desen üç kademelidir ve üçü de aynı kuralı izler:

| kademe | mekanizma | örnek |
|---|---|---|
| bölümün YANI | `inspectors` | `desktopInspectors` (`docs/60`) |
| bölümün İÇİNDEKİ yüzey | çizici prop | `renderKitchenMonitor`, `renderQueue` |
| bölümün TAMAMI | `pageOverrides` | `desktopPages` (bu paket) |

---

## 6. İlk ekran: sipariş kuyruğu — ve menü neden değil

### Seçilen: `orders` / kuyruk sekmesi

1. **İş gerçekten farklı, ekran değil.** Salondaki garson yürür, elinde tek bir
   sipariş vardır, ekrana bakmadan basar. Kasadaki kişi aynı anda ona bakar,
   klavyesi vardır ve aynı işi arka arkaya yapar — işi **tarama ve toplu
   işlemdir**. İkincisi birincinin geniş hâli değildir.
2. **Sıklık.** Menü haftada bir düzenlenir; kuyruk her servis akşamı, birkaç
   dakikada bir çalışılır.
3. **İş mantığı ZATEN başsız.** `useOrderFeed`, `changeOrderStatus` ve
   `orderPresentation` bugün hem mobil kuyruğu hem mutfak monitörünü besliyor —
   yani depo bu desenin **adı konmamış bir örneğini zaten taşıyor**. Yeni bir veri
   katmanı yazılmadı; yalnız ikinci bir sunum eklendi.

### Masaüstü kuyruğunda olan, telefonda olmayan

- Klavyeyle gezinen liste (roving tabindex; ok tuşları, Home/End)
- Çoklu seçim: Boşluk, Shift+ok aralık, Ctrl/Cmd+A
- Toplu onay ve toplu ret (tek sebeple)
- Sağ tık bağlam menüsü **ve klavye karşılığı**
- Kalıcı ayrıntı bölmesi (telefonda ikinci sütun yoktur)
- `hover` ile açığa çıkan satır eylemleri

Mobil kuyruk **aynen kalmıştır**: kart listesi, 44 px'ten büyük hedefler, her
zaman görünür eylemler.

### Menü neden ilk adım DEĞİL

`MenuCatalogWorkspace.tsx` **4.613 satır / 231.575 bayt** tek bir dosyadır: 79
`useState`, ~40 işleyici, çizim sırasında çalışan ~70 satırlık bir sıfırlama
bloğu, beş yerde kopyalanmış yeniden okuma ve on elle yazılmış iyimser ağaç
güncellemesi — beş bileşen testi ve bir kaynak tarayan koruma testiyle çevrili.

Masaüstü menü ekranının değeri (kategori ağacı + ürün listesi + canlı önizleme
aynı anda) **tam olarak o veri katmanına** bağlıdır. Onu çıkarmadan yazılacak bir
masaüstü menüsü ya iş mantığını ikinci kez yazardı (§4 gereği yasak) ya da mobil
menüyü riske atardı. Menü bu yüzden **paket 2'dir** ve ön koşulu §9'da yazılıdır.

---

## 7. Erişilebilirlik — masaüstü eylemleri klavyeyle de yapılabilir

- **Sağ tıkın klavye karşılığı ŞARTTIR** (WCAG 2.2 AA). Bağlam menüsü `Shift+F10`
  ve `ContextMenu` tuşuyla da açılır; **aynı** menüdür, ikinci bir "klavye menüsü"
  değil — iki menü zamanla ayrışırdı.
- **Sürükle-bırak varsa sürüklemesiz alternatifi ŞARTTIR** (2.5.7). Bu paket
  sürükleme eklemedi.
- `hover` ile açığa çıkan her eylem `focus-within` ile de açığa çıkar; yoksa
  klavyeyle gezen kullanıcı düğmeleri hiç göremez.
- Roving tabindex: listeye `Tab` ile bir kez girilir, içinde oklarla gezinilir.
- **`prefers-reduced-motion` MUTLAK.** Bu ekranda uyulmasının en ucuz yolu
  seçildi: hiç hareket üretilmiyor.

---

## 8. Kapı: kural bir listeye değil, bir KLASÖRE bağlıdır

`scripts/adaptive-bundle-gate` (CI'da koşuyor) iki yeni kural aldı:

```
components/workspace/pages/desktop/**   → yalnız masaüstü paketinden ulaşılabilir
components/workspace/pages/mobile/**    → yalnız mobil paketinden ulaşılabilir
```

Bu bir **konvansiyondur**, bir liste değil: klasöre yarın konan dosya, kapının
listesine yazılmayı beklemeden kilitlenir. Kapı iki şeyi birden kırar — dosya
öteki paketten ulaşılabiliyorsa **ve** kendi paketinden ulaşılamıyorsa (ölü kod).

`pages/mobile/` bugün **bilerek boştur**: 320 tabanı PAYLAŞILAN koddur
(`TOUCH-FIRST-INTERFACE` §1) ve dokunma sürümü ortak dosyalarda yaşar. Boş bir
cihaz klasörü hata sayılmaz — sayılsaydı konvansiyonun kurulması imkânsız olurdu.

Kapı ayrıca **iki paketin ağırlığını ayrı ayrı** raporlar (kaynak baytı; derleme
gerektirmez). Öz-testi (`adaptive-bundle-gate.test.sh`) on senaryo koşar ve üçü bu
paketle eklendi: klasördeki dosyanın öteki pakete sızması, ölü kalması ve ters yön.

### Bilinen kalıntı — dürüstçe

Mobil paket bu paketten sonra **1.155 bayt (355 bayt gzip, %0,08)** büyüdü:

- **833 B** yalnız masaüstünde çizilen 11 dize. `i18n/workspace/*.ts` katalogları
  eager glob ile toplandığı için **kataloglar bugün cihaz tanımıyor**. Kapı
  bileşenleri ayırıyor, dizeleri ayırmıyor.
- **322 B** ortak kod ayrıştırmasının kendisi (yeni modül sınırları).

Cihaza göre bölünmüş dize katalogları i18n çıkarma hattını değiştirmeyi gerektirir
ve bu paketin sınırı dışında bırakıldı; §9'un ilk maddesidir. Sayı küçük ama
**sıfır değil** ve öyle raporlanmamalıdır.

---

## 9. Kalan iş — bu bir programdır, bu paket 1. adımdır

### Önce mekanizma borcu

| # | iş | neden |
|---|---|---|
| M1 | Cihaza göre bölünmüş dize katalogları | Masaüstü dizeleri bugün mobil pakete iniyor (833 B); §8 |
| M2 | `useMenuCatalog` çıkarımı | Masaüstü menüsünün ön koşulu; §6 |
| M3 | Derlenmiş paket ağırlığı için kapı eşiği | Bugün raporlanıyor, dondurulmuyor |

### Ekranlar

`orders` dönüştürüldü. Kalan **14** bölüm, masaüstü değeri tahminiyle:

| bölüm | sayfa | masaüstünde ne kazanır | tahmin |
|---|---|---|---|
| `menu` | `MenuPage` | kategori ağacı + liste + canlı önizleme aynı anda | **yüksek** (M2 gerekli) |
| `media` | `MediaPage` | ızgara + kalıcı önizleme, toplu seçim, sağ tık, klavye gezinme | **yüksek** |
| `team` | `TeamPage` | çok sütunlu üye tablosu, toplu rol değişimi | yüksek |
| `analytics` | `AnalyticsPage` | yan yana grafik, karşılaştırma | orta |
| `qr-codes` | `QrCodesPage` | toplu üretim ve baskı önizlemesi | orta |
| `publication` | `PublicationPage` | sürüm karşılaştırma yan yana | orta |
| `orders` (geçmiş) | `OrderHistoryRegion` | sıralanabilir/filtrelenebilir tablo | orta |
| `ratings` | `RatingsPage` | liste + kalıcı ayrıntı | orta |
| `locations` | `LocationsPage` | liste + harita/ayrıntı yan yana | düşük |
| `dashboard` | `DashboardPage` | daha yoğun kart ızgarası | düşük |
| `settings` | `SettingsPage` | kalıcı alt gezinti | düşük |
| `billing` | `BillingPage` | plan karşılaştırma tablosu | düşük |
| `brand` | `BrandPage` | form + canlı önizleme | düşük |
| `profile` | `ProfilePage` | ayrışma gerekmeyebilir | **yok** |
| `support` | `SupportPage` | ayrışma gerekmeyebilir | **yok** |

**"Yok" gerçek bir cevaptır.** Bir ekranın masaüstünde farkı yoksa cihaz haritasına
yazılmaz: boş bir kayıt, bakılacak ikinci bir dosya yaratıp hiçbir şey kazandırmaz.

---

## 10. İhlal edilemez kalan

- **320×480 TABAN OLMAYA DEVAM EDER.** Bu belge masaüstünü tabanın YERİNE değil,
  ÜSTÜNE koyar. Taban kurallar koşulsuz yazılır; `max-width` bastırması,
  "mobilde gizle" ve genişlikle cihaz seçimi yoktur.
- Cihaz kararı **sunucudadır** (`DeviceClass`), tarayıcıda değil.
- Emoji yok; ikon `@phosphor-icons/react`. CDN yok.
- Panel Flowbite, kurumsal site daisyUI (`dz-`). Karıştırılmaz.
