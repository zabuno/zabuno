# 47 — Form ve Akış Standardı (dış eleştirinin külliyatla ikinci uzlaştırılması)

**Durum:** Standart yazıldı; referans uygulama menü kataloğunda tamamlandı.
**Requirement ID:** `FORM-ONE-OUTCOME`
**İlgili:** `docs/36` (külliyat, MASTER), `docs/44` (birinci uzlaştırma),
`docs/41` (DESIGN-2030-v1), `docs/38`/`docs/46` (adres ve ölçüm),
`docs/48` (320px-first)

---

## 0. Sahibinin tespiti

> "category name girdikten sonra product name girerim. ama category ekleme
> bilgileri ile ürün ekleme bilgileri aynı formda olmaz."

Ölçünce durum tarif edilenden ağırdı. Menü ekranında bir ürün eklemek **dört
ayrı form** ve **üç ayrı sunucu turu** gerektiriyordu:

```
[Kategori adı]  → Kaydet
[Ürün adı]      → Kaydet     (ancak bundan sonra beliriyordu)
[Fiyat]         → Kaydet     (ancak bundan sonra beliriyordu)
[Alerjenler]    → Kaydet     (ancak bundan sonra beliriyordu)
```

Dördü de aynı ekranda, alt alta, aynı görsel ağırlıkta duruyordu. Hangi
kutunun hangi işe ait olduğu yalnız etiketi okuyarak anlaşılıyordu.

Bu, külliyatın **öncelik sırasının ilk maddesini** — *görev tamamlama* —
doğrudan çiğniyordu.

---

## 1. Dış rapor ile külliyatın karşılaştırması

Sahibinin sorusu: *"repo'da ve yönergelerde ve faz planlarında daha iyisi
yoksa şunlara göre bir yaklaşım sergilemeli."* Cevap madde madde:

### 1.1 Külliyat zaten söylüyor — rapor yeni karar getirmiyor

| Raporun maddesi | Külliyattaki karşılığı |
| --- | --- |
| "Journey before module" | `docs/36` §5 öncelik sırası + 2030 vizyonu |
| "Outcome before configuration" | Öncelik sırası: görev tamamlama ilk |
| "One page, one primary outcome" | Flat 2.0 affordance disiplini, `docs/44` §1 |
| "Deterministic before AI" | `docs/36` §5.7 — *AI katılımcıdır, otorite değil* |
| "AI embedded, not appended" | `docs/36` §5.7 — *AI slot'lar üzerinden çalışır* |
| "Aesthetic means signal-to-noise" | Öncelik sırası: estetik **en son** |
| "State is design" | `docs/44` §2 (sayfa durum envanteri) |
| "Boş durum standardı" | `docs/44` §2 (4-parça standardı) |
| "Rozet standardı" | `docs/44` §2 |
| "Devre dışı kontrol standardı" | `docs/44` §2 |
| "Hash yerine gerçek route" | `docs/38` §4 — ve 2026-08-27'de **uygulandı** (`docs/46`) |
| "Tenant/platform/engineering ayrımı" | `docs/44` §4 — sahibinin kararına bağlı |
| Sabit piksel sayfa genişlikleri | **Reddedildi**, `docs/44` §3: `--container-*` token'ları |

Bunların hiçbiri yeni bir karar değil. Yapılmamış olanlar **uygulama
borcudur** ve `docs/41` fazlarında zaten adlandırılmıştır.

### 1.2 Rapor gerçekten ekliyor — külliyata alınır

| Raporun getirdiği | Neden değerli |
| --- | --- |
| **Form ayrıştırma kuralı** (bu belgenin §2'si) | Külliyat "tek birincil sonuç" der ama FORMUN kendisi için bir kural vermez. Sahibinin tespiti tam buraya düşüyor |
| **AI öneri anatomisi** (niyet → yorum → bağlam → değişiklik → etkilenen kayıt → önizleme → onay → geri al → denetim) | `docs/14`'te AI duruşu var, ama bir AI aksiyonunun ANATOMİSİ yok |
| **Olay taksonomisi** (ürün analitiği olayları) | `docs/46` ölçüm dikişini kurdu; hangi olayların ölçüleceği listesi yoktu |
| **Yolculuk haritaları** | `docs/44` §2'de zaten "külliyatın ince olduğu yer" olarak kayıtlı; rapor somut yolculuk metinleri veriyor |

### 1.3 Rapor ile külliyat çatışıyor — külliyat kazanır

Raporun **§11.2**'si sayfa genişliklerini sabit piksel verir. `docs/44` §3
bunu zaten reddetti ve gerekçesini dondurdu: bileşen ham geometri bilmez,
kapsayıcı sorgusu önceliklidir. **Karar değişmedi.**

Raporun **§9.5**'i medya için kart yoğun bir grid önerir. Külliyatın kuralı:
*her bilgi grubunu karta sokmak yasak*. Galeri bağlamsal bir karttır ve
serbesttir; form bölümleri, durum satırları ve liste öğeleri karta sokulmaz.

### 1.4 Sahibinin alanı — tasarım kararı değil

Analytics'in MVP kapsamı, Billing/Ledger'ın hangi yüzeye ait olduğu, Launch
readiness'ın taşınması: bunlar ürün kapsamı kararlarıdır (`docs/44` §4).
Launch readiness için sahibi kararını verdi (geliştirici paneline taşınacak);
o iş ayrı bir pakettir.

---

## 2. FORM STANDARDI (yeni, bağlayıcı)

### Kural 1 — Bir form, bir sonuç

Bir formun **tek bir cümlelik sonucu** olmalıdır: "menüye bir ürün ekledim",
"markamı güncelledim", "ekibime birini davet ettim".

Farklı bir **nesne** yaratmak, farklı bir sonuçtur ve farklı bir formdur.
Kategori bir nesnedir, ürün başka bir nesnedir; ikisi aynı formda olmaz.

### Kural 2 — Tek sonuç, tek gönderim

Kullanıcının kafasındaki tek iş, **tek bir kaydetmeyle** bitmelidir.

Sunucuda üç kayıt yaratılması bir uygulama detayıdır; kullanıcıya
yansıtılmaz. Yansıtılırsa yalnız tıklama sayısı artmaz — **yarım kalmış
durumlar** üretilir: ikinci adım düşünce hiçbir yerde görünmeyen, dolayısıyla
temizlenemeyen artıklar kalır.

Uygulama: çok adımlı yazma **tek işlemde** yapılır (`addMenuEntry`
deposundaki gibi), tek uç noktadan.

### Kural 3 — Seçmek ile yaratmak ayrı ağırlıktadır

Bir formun içinde bir nesneye **atıfta bulunmak** (kategori seç) normaldir.
O nesneyi **yaratmak** aynı yerde, aynı ağırlıkta duramaz.

Nadir yapılan iş (kategori eklemek) kapalı durur; sürekli yapılan iş (ürün
eklemek) açık durur.

### Kural 4 — Zorunlu olan açık, isteğe bağlı olan kapalı

Zorunlu alanlar görünür; isteğe bağlı olanlar `progressive disclosure`
arkasındadır — ama **gizlenmez**: varlığı görünür kalır (alerjen bir yasal
yükümlülüktür; kullanıcı olduğunu bilmeli, ilk üründe tıkanmamalı).

### Kural 5 — Doğrulama toptan, odak ilk hataya

Tüm alanlar **aynı anda** doğrulanır. Tek tek doğrulamak kullanıcıyı aynı
formu birkaç kez göndermeye zorlar. Odak ilk hatalı alana taşınır
(`focusFirstInvalidField`).

### Kural 6 — Red yazılanı silmez

Sunucu reddettiğinde kullanıcının yazdığı **hiçbir şey kaybolmaz**. Tek forma
geçmenin bedeli budur: artık tek bir alan değil, birkaç alan birden
yazılmıştır.

### Kural 7 — Başarı formun yanında görünür

Arka arkaya yapılan işlerde (menü doldurmak gibi) form temizlenir ve onay
formun yanında belirir. Kullanıcı "gitti mi?" diye listeye bakmak zorunda
kalmamalı.

### Kural 8 — Aynı ekranda iki kontrol aynı ismi taşıyamaz

Satır içi düzenleyici, ait olduğu kaydın adını taşır (`Price — Mercimek
Çorbası`). Aynı erişilebilir ismi taşıyan iki kontrol, ekran okuyucu kullanan
birine hangisinde olduğunu söylemez.

### Kural 9 — Düzenleme, düzenlenen şeyin yanında olur

Bir satırın alanını düzenlemek için açılan form, o **satırın içinde** açılır;
sayfanın dibinde değil. Sayfanın dibindeki tek bir paylaşılan düzenleyici,
"hangi kaydı düzenliyorum" sorusunu cevapsız bırakır ve yanlış kayda yazma
riskini üretir.

### Kural 10 — AI formu değiştirmez, doldurur

AI-first burada "forma sohbet kutusu koymak" değildir. AI, formun **aynı
alanlarını** doldurmayı önerir; kullanıcı görür, düzenler, onaylar. AI kapalı
olduğunda form aynen çalışır (`docs/36` §5.7).

---

## 3. Referans uygulama: menü kataloğu

| | Önce | Şimdi |
| --- | --- | --- |
| Bir ürün eklemek | 4 form, 4 kaydetme, 3 sunucu turu | **1 form, 1 kaydetme, 1 sunucu turu** |
| Kategori ekleme | Ürün formunun yanında, eşit ağırlıkta | Kendi eylemi, kapalı başlar |
| Kategori seçimi | Yalnız 1'den fazla kategori varsa | Her zaman — ürün bir kategoriye ait olmak zorunda |
| Alerjen | Ayrı bir form, zorunlu bir adım gibi | Aynı formda, kapalı, isteğe bağlı |
| Alerjen düzenleme | Sayfanın dibinde, paylaşılan tek form | Satırın içinde, kaydın adıyla |
| Kategori değiştirme | Yazılanı **siler** | Yazılanı **korur** |
| İkinci adım düşerse | Hiçbir menüde görünmeyen öksüz ürün | İşlem geri alınır, artık kalmaz |

Yeni uç nokta: `POST /api/workspaces/{workspace}/menu-categories/{category}/menu-entries`

Eski üç uç nokta **kaldırılmadı**: menüde zaten olan bir ürünü başka bir
kategoriye eklemek gibi işler onları kullanır. Kaldırılan şey, kullanıcıdan
onları sırayla çalıştırmasını istemekti.

---

## 4. Kalan formlar — fazlanmış plan

Depoda 15 dosyada 19 form var. Standarda göre sıradaki işler:

**2026-08-27: sahibinin talimatıyla platform ve `/app/*` formlarının tamamı
standarda çekildi.** Bulgular ve yapılanlar:

| # | Form | Yüzey | Bulunan | Yapılan |
| --- | --- | --- | --- | --- |
| 4.1 | `MenuCatalogWorkspace` | /app | 4 form, 3 sunucu turu, öksüz ürün riski | Tek form, tek işlem (§3) |
| 4.2 | `PlanForm` | platform | **Ölü devre dışı düğme**: geçerli olana kadar kapalı, hangi alanın eksik olduğu SÖYLENMİYOR | Toptan doğrulama, alan başına hata, odak ilk hataya; düğme yalnız gönderim sırasında kapalı |
| 4.3 | `ManualPaymentForm` | platform | **Sessiz düğme**: bitiş tarihi boşken basmak hiçbir şey yapmıyordu — para hareketi kaydeden bir formda | Alan başına hata + odak; onay penceresi açılmıyor; depo `Button` primitifi |
| 4.4 | `MediaUploadRegion` | /app | Sessiz düğme; **kalıcı devre dışı iki alan** (haklar/lisans, son kullanma); `Asset slot` iç kavramı kullanıcıya sızıyor | Hatalar + odak; ölü alanlar KALDIRILDI; etiket "Where will this image be used?"; alternatif metin ipucu eklendi |
| 4.5 | `BrandOnboardingForm` | /app | **Sıralı doğrulama**: iki hatalı alan = iki tur | Toptan doğrulama |
| 4.6 | `LocationOnboardingForm` | /app | Formun tepesinde tek genel cümle, odak taşınmıyor | Alan başına hata + odak; isteğe bağlı alanlar etiketinde |
| 4.7 | `BrandEditForm` / `LocationEditForm` | /app | Kural 5/6 zaten sağlanıyordu (sunucu hataları alan bazlı) | İsteğe bağlı alanlar etiketinde işaretlendi |
| 4.8 | Auth formları (4 adet) | ne platform ne /app | Kapsam dışı; kapı yine de üstünden geçiyor | — |

### 4.9 Zorlayıcı kapı

`resources/js/components/forms.guard.test.ts`, standardın iki maddesini
kaynak metinden zorlar:

- **Kural 5** — bir metin alanının boşluğunu sınayıp hiçbir şey söylemeden
  geri dönen dal yasaktır.
- **Kural 4** — koşulsuz `disabled` taşıyan kontrol yasaktır.

Kapı, üç gerçek kusur geri konarak **sınandı ve üçünü de yakaladı**. İlk iki
denemesi yakalayamamıştı: biri hata belirleyicilerinin adına bakıyordu
(`setEntrySubmitError` gibi adlar kaçıyordu), diğerinin koşul deseni
`trim()` parantezine takılıyordu. Yakaladığını gösteremeyen bir kapı, kapı
değildir.

---

## 5. Kabul ölçütü

Bir form ancak şunlar sağlandığında standarda uygundur:

1. Tek cümlelik sonucu var ve o sonuç tek gönderimle oluşuyor.
2. Sunucu tarafında çok adımlıysa, adımlar tek işlemde — yarım kalmış durum
   üretmiyor.
3. Reddedilen istek yazılanı silmiyor.
4. Tüm alanlar aynı anda doğrulanıyor, odak ilk hataya gidiyor.
5. Ekranda hiçbir iki kontrol aynı erişilebilir ismi taşımıyor.
6. Boş, yükleniyor, hata ve başarı durumları ayrı ayrı tasarlanmış.
7. AI kapalıyken form aynen çalışıyor.
