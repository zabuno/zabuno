# 118 — Master kararlar: yönergenin hangi maddesi ezildi ve neden

**Sahibin kararı (2026-09-05):** *"Bu dosyaları, bugün verdiğimiz kararları
master (değişmez) karar sayarak ezeceğiz, güncelleyeceğiz. Kararlarımızı bu
dosyaların değiştirmesine izin vermeyeceğiz."*

**Öncelik sırası, çelişkide:**

```
bugünün master kararları (bu belge)
  > docs/105 (kurumsal site kararları)
  > docs/119 (yönerge girdisi)  ·  docs/106 (site haritası girdisi)
```

Girdi dosyaları **düzenlenmez**. Bir girdiyi sonradan düzeltmek, kararın hangi
girdiden çıktığını gizlemek olurdu; ezilen madde burada adıyla yazılır.

## 0. Önce doğrusu: yönerge unutulmadı

Sahip haklı olarak sordu. Ölçüm:

| Girdi | Depodaki karşılığı | Durum |
| --- | --- | --- |
| `zabuno-com-tam-site-haritasi.md` | `docs/106` (kaynak) | Kopyalanmış |
| `zabuno-frontend-claude-uygulama-yonergesi.md` | `docs/119` (kaynak) | **Bugün kopyalandı** — eksikti |
| Yönergenin kararları | `docs/105` | Uygulanmış |
| Sayfa kütüğü | `content_pages`, **386 kayıt** | Çalışıyor |
| PageGate, hazırlanıyor sayfası, çeviri kilidi | Kod + testler | Çalışıyor |

Yani yönerge atıl kalmadı; kararlara dönüştü ve uygulandı. Eksik olan tek şey
girdinin kendisinin depoya alınmamış olmasıydı — ikiz girdilerden yalnız biri
kopyalanmıştı. Bu bugün kapandı.

## 1. Ezilen maddeler

### E1 — Sıralama: "masaüstü, tablet, mobil" → **dar ekran TABAN**

**Yönerge §20 (Tasarım):** *"Desktop, tablet ve mobil kontrol edildi."*

**Master karar:** global `CLAUDE.md` → `TOUCH-FIRST-INTERFACE`. Dar ekran
tabandır, geniş ekran ilerleyici zenginleştirmedir. Bir sayfanın tanımlı
bitişi, **en dar desteklenen genişlikte ölçülmüş** olmasını içerir.

**Neden ezildi:** sıralama bir üslup tercihi değil, kusur kaynağı. Aynı gün
kabuk düzeni üç kez üst üste sahibin ekranında kırıldı ve üçünde de bütün
testler yeşildi (`docs/117` §0). Geniş ekranda alınmış her karar dar ekranda
borç olarak geri döndü.

**Zorlayıcı karşılığı:** `scripts/mobile-ux-audit` — gerçek Chrome, 320×568,
her hikâye. Yeni ihlal kapıyı kırar.

### E2 — Giriş kipi ayrımı: yönergede **yok**, artık zorunlu

**Yönergede karşılığı yok.** §18 yalnız "minimum WCAG 2.2 AA" diyor.

**Master karar:** dokunma ile işaretleyici ayrı etkileşim modelleridir. `hover`
ile anlatılan hiçbir bilgi dokunmalı cihazda var olmaz; parmak ekranı kapatır;
uzun basma ve kaydırma dokunmada ucuz, işaretleyicide pahalıdır. Etkileşim
modeli farklıysa **ayrı kod yolu** yazılır.

**Neden eklendi:** "tek kod, medya sorgusuyla gizle" çözüm değil — gizlenen şey
yine indirilir, yine odaklanılabilir, yine bakım ister.

### E3 — Yoğunluk: **büyük hedef + SIKI boşluk**

**Yönergede karşılığı yok.** §18 yalnız "rastgele spacing kullanılmamalıdır"
diyor.

**Master karar (sahibin ifadesiyle):** *"buton boyutları büyük olması
dokunabilirlik açısından güzel ama grid gap ve grid margin mobil cihazlarda
fazla çalıyor ekrandan. Data-sensitive bir yaklaşımla planlarsan daha efektif
olacaktır mobile için. Desktop için değil."*

Dokunma hedefi ≥44 px kalır; **boşluk ölçeği dar ekranda daralır**. İç içe
kapların dolgusu birikmez. Font küçülmez, hedef küçülmez; küçülen tek şey ölü
alandır.

**Ölçülmüş sonuç:** boşluk ölçeğinin alt sınırları masaüstü ölçüsündeydi ve
320 pikselde zaten devredeydi — dar ekran taban değil, kırpılmış masaüstüydü.
Tabanlar indirildi, tavanlar korundu (`docs/117` M4).

### E4 — Ürün arayüzünün dili: **İngilizce**, kurumsal sitenin dili ayrı

**Yönerge §1 madde 1-3:** *"Ana ve kaynak dil Türkçedir… İngilizce içerik
yazma."*

**Master karar (2026-09-05):** *"Ben söylemedikçe tercüme çeviri yapma.
İngilizce kalsın."* `i18n.shipped_locales` yalnız `['en']` ve artık
`NegotiateLocale` **o listeyi** okuyor.

**Ayrım net ve ikisi birden doğru:**

| Yüzey | Dil kaynağı | Nasıl seçilir |
| --- | --- | --- |
| Ürün arayüzü (panel, misafir menüsü, kimlik) | `i18n.shipped_locales` = `['en']` | Tarayıcıyla pazarlık |
| Kurumsal site (`/tr/`, `/en/`) | Adresteki locale segmenti | Pazarlık **yok** |

Bu ayrım bugüne kadar **kazayla** doğruydu: şablon `lang`'i `$page->locale`'den
türetiyordu. Kazayla doğru olan bir şey bir gün kazayla yanlış olur; artık
`CORP-LOCALE-FROM-PATH-01` testi kilitliyor.

**Açık varsayım — sahibin kararını bekleyen tek nokta.** Kurumsal sitenin İLK
içerik dili: yönerge Türkçe diyor, bugünün master kararı ürün için İngilizce
diyor. İkisi farklı yüzey olduğu için çelişmiyor, ama **ilk yazılacak içeriğin
dili bir ürün/marka kararıdır** ve sahibindir.

Varsayılan olarak alınan yol, hangi karar verilirse verilsin **hiçbir emeği
çöpe atmıyor**: sayfa kütüğü, kabuk, şablonlar ve blok yapısı dilden
bağımsızdır; içerik ayrı bir katmanda yaşar. Karar geldiği gün yalnız içerik
yazılır, mimari değişmez.

### E5 — Efekt kütüphaneleri: yönerge **yasaklıyordu**, sahip **istiyor**

**Yönerge §2.3:** *"React tabanlı SmoothUI, Animate UI, Magic UI, Aceternity ve
React Bits bileşenlerini projeye sokma"* — çalışan bir Blade sitesi varsa.

**Master karar (2026-09-05):** sahip GSAP animasyon deneyimi, parallax ve bu
kütüphanelerden MCP ile hareketli bileşenler istiyor.

**Ezildi, ama yönergenin gerekçesi ölçüldü ve bir kısmı korunuyor.** Gerekçe
gerçekti: aynı projede ikinci bir temel tasarım sistemi kurmak. Bugünkü kamu
sayfaları **hiç React yüklemiyor** (`docs/105` §1) ve bu bir başarı, kaybedilecek
bir şey.

**Uygulanacak biçim:**

1. **GSAP birinci sıradadır** ve çatıdan bağımsızdır. Sahibin istediği hareket
   kalitesini, sıfır-React bir yüzeye ikinci bir bileşen sistemi sokmadan verir.
2. Parallax ve arka plan hareketi **CSS + GSAP** ile yapılır.
3. React adacığı yalnız bir bileşen **gerçekten** etkileşim gerektirdiğinde
   açılır; süs için açılmaz.
4. MCP'den gelen her bileşen `docs/119` §17.4 kabul kapısından geçer: lisans,
   bağımlılık, jetonlara bağlama, ikon temizliği, klavye/ekran okuyucu,
   `prefers-reduced-motion`, paket etkisi, mobil ölçüm, manifest kaydı.
5. **Gerçek kapı ölçümdür:** `scripts/mobile-ux-audit` + paket bütçesi. Kapıyı
   geçmeyen efekt statik CSS varyantına düşer.
6. Sayfa başına en fazla bir baskın hareketli arka plan; aynı görüntü alanında
   ikinci bir WebGL yok (`docs/119` §17.5 korunuyor).

### E6 — İkon yasağı: **korunuyor**, ama kapsamı yazıldı

**Yönerge §1 madde 10:** *"İkon kullanılmayacaktır."*

**Karar: korunuyor — yalnız KURUMSAL SİTE için.** Ürün panelinde ikon
kullanılıyor ve sahibin kendi kuralı bunu düzenliyor (emoji yasak, Phosphor
ilk). İki yüzey iki ayrı görsel dil; kapsam yazılmadığı için bugüne kadar
çelişki gibi görünüyordu.

### E7 — Stok görsel kaynağı: **Unsplash değil**

**Master karar (2026-09-05):** *"unsplash değil, diğer stok foto sitelerinden
free lisanslı fotolar ekle."*

Kullanılabilir kaynaklar: Pexels, Pixabay, Openverse, Wikimedia Commons,
Burst, StockSnap, Kaboompics, Life of Pix, Picjumbo, Reshot ve muadilleri.

**Her görsel için kaydedilir:** kaynak, doğrudan adres, lisans adı, atıf
gerekliliği, indirme tarihi. Lisansı doğrulanamayan görsel **kullanılmaz** —
"muhtemelen serbest" bir lisans değildir.

## 2. Değişmeden korunan kararlar

Yönergenin şu maddeleri bugünkü kararlarla çelişmiyor ve aynen geçerli:

- Tek canonical URL, kopya landing page yok, doorway yok.
- Header/footer/mega menü yeni sayfa yaratmaz; aynı canonical'e bağlanır.
- Sayfa kütüğü + durum makinesi + merkezî `PageGate`; yüzlerce kopya sayfa
  bileşeni üretilmez.
- Yayınlanmamış sayfa production'da **404 + noindex**; soft-404 üretilmez.
- Çeviri kilidi dört katmanda kapalı; `ÇEVİRİLERE BAŞLA` denmeden açılmaz.
- Black-hat, negative, parasite SEO ve yanıltıcı structured data yasak.
- Structured data görünmeyen bilgi taşımaz; sahte rating üretilmez.
- Ürünün gerçekten desteklemediği özellik veya entegrasyon yayınlanmaz.
- Programmatic sayfa gerçek veri taşımak zorundadır; şehir adı değiştirilmiş
  kopya yayınlanmaz.
- Sayfa yayın kapısı (`docs/119` §20) — E1 ile güncellenmiş sıralamayla.

## 3. Bu belgenin kendi gerekçe süresi

`docs/109` §8.6. Özellikle E4'ün açık varsayımı (kurumsal sitenin ilk içerik
dili) sahibin kararıyla kapanır ve o gün bu belge güncellenir. E5'in biçimi de
ölçüme bağlıdır: GSAP ile istenen kalite alınamıyorsa React adacığı kararı
yeniden tartışılır — ama ölçüm sonrası, tahminle değil.
