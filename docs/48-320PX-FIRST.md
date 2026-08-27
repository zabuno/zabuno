# 48 — 320 px Önce (iPhone 4) — bağlayıcı kural

**Durum:** Kural yazıldı, kapı kuruldu, depo temiz (sıfır kırılma noktası).
**Requirement ID:** `VIEWPORT-320-FIRST`
**İlgili:** `docs/36` §5.6 (külliyat: *320px gerçek başlangıç noktasıdır*),
`docs/44` §3, `docs/47`

---

## 1. Kural

Sahibinin sözleri:

> "aı first bir panele yakışır biçimde, 320px (iphone 4) first olsun… sadece
> media query değil. gerçek '320 px iphone 4 safari first coding' yapılacak.
> diğer cihazlar ve tarayıcılar da adaptive fluid uyumlu olacak."

Külliyat bunu zaten söylüyordu (`docs/36` §5.6: *320px gerçek başlangıç
noktasıdır; container-query öncelikli, logical property tabanlı, RTL-native*).
Eksik olan karar değil, **zorlayıcı kapıydı**.

---

## 2. Ne ölçüldü

| | Ölçüm |
| --- | --- |
| Kırılma noktası öneki kullanan üretim dosyası | **3** (ikisi aynı gün eklenmişti) |
| Toplam kırılma noktası kullanımı | 19 (çoğu test ve story dosyalarında) |
| 320'ye atıf yapan test dosyası | 27 |

Depo iddiasının büyük kısmını gerçekten karşılıyordu. Üç dosya düzeltildi ve
kural kapıya bağlandı.

---

## 3. Araç sırası — kırılma noktası EN SONDUR

1. **İçsel (intrinsic) düzen.** `flex-wrap` + `flex-basis`, grid
   `repeat(auto-fit, minmax(…))`. Öğeler kendi taban genişliklerini bilir ve
   yer kalmayınca kendiliğinden alt satıra düşer.
2. **Kapsayıcı sorgusu.** Düzen gerçekten bir eşiğe ihtiyaç duyuyorsa,
   eşiği EKRAN değil KAPSAYICI belirler.
3. **Kırılma noktası (`sm:`, `md:`…).** Yalnız gerçekten ekranın kendisine
   ait bir karar için — ve gerekçesi yazılarak.

### Neden `sm:` yanlış cevap

`sm:flex-row` yazmak kolaydır ve 320'de doğru görünür. Ama **ekranı**
dinler: kenar çubuğu açık bir masaüstünde, dar bir sütunun içindeki aynı
bileşen de "geniş" davranır ve orada yanlıştır.

Somut örnek — menü ürün formundaki ad + fiyat alanları:

```
önce:  flex-col sm:flex-row      → ekran ≥640px ise yan yana
sonra: flex-wrap + flex-[1_1_16ch] / flex-[0_1_12ch]
                                  → YER varsa yan yana
```

İkincisi 320px'te de doğrudur, dar bir sütunun içinde de.

---

## 4. Kapı

`resources/js/components/viewport.guard.test.ts` iki şeyi yasaklar:

- **Kırılma noktası önekli sınıf** üretim bileşenlerinde (yorumlar hariç).
- **320 pikselden geniş sabit genişlik** (`w-[400px]`, `width: '600px'`).

Kapı, iki gerçek ihlal geri konarak **sınandı ve ikisini de yakaladı**.

---

## 5. Tarayıcı motoru: sahibinin kararı gereken nokta

Sahibi "iphone 4 safari" dedi. İki farklı şey olabilir ve ikisinin bedeli
çok farklıdır:

**(a) 320 px genişlik önce** — iPhone 4'ün ekran genişliği. **Yapıldı.**
Bugün panel 320×480'de yatay kaydırma olmadan çalışıyor ve taşan tek bir
öğe yok (yerel tarayıcıda ölçüldü).

**(b) iPhone 4'ün tarayıcı MOTORU** (iOS 7 Safari, 2013) — bu ayrı bir
karardır ve şunları **kaybetmeyi** gerektirir:

| Bugün kullanılan | iOS 7 Safari'de |
| --- | --- |
| CSS custom properties (`--color-*`, `--space-*`) | **Yok** — tasarım sisteminin tamamı buna dayanıyor |
| `flex` `gap` | **Yok** — her boşluk margin'e dönüşür |
| Container query | **Yok** |
| `clamp()`, `min()`, `max()` | **Yok** — akışkan tipografi ölçeği buna dayanıyor |
| ES2015+ / modern React | Derleme hedefi çok geriye çekilir, paket büyür |

Yani (b), token zincirinin (`docs/36` §5.4 — külliyatın kendi *en önemli*
maddesi) terk edilmesi demektir.

**Öneri:** (a) uygulanır — yapıldı. (b) için gerçek bir talep gelmeden
gidilmez; iPhone 4 kullanan bir restoran müşterisi menüyü QR ile açar ve o
sayfa zaten sunucuda üretilen sade HTML'dir (React yüklemez). Panel ise
işletme sahibinin çalışma aracıdır.

Bu bir karar noktasıdır ve sahibinindir.

---

## 6. Kabul ölçütü

1. 320×480'de yatay kaydırma yok, taşan öğe yok.
2. Üretim bileşenlerinde kırılma noktası öneki yok.
3. 320 pikselden geniş sabit genişlik yok.
4. Düzen kararları kapsayıcıyı dinler, ekranı değil.
5. Hiçbir kontrol içeriğin üstüne kalıcı olarak binmez.
