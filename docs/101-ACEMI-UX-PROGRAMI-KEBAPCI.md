# 101 — Acemi-UX programı: "Adana'dan gelmiş kebapçı" için tasarım

**Durum:** Faz 1 ✅ (FF-73), Faz 2 ✅ (FF-81 Y3 hatırlatması, FF-82 yayın
dili). Sayaç: **2/4 tamamlandı, 3/4 aktif.**
**Sahibin sözü:** "Adana'dan, Urfa'dan gelmiş kebapçı hedef kitle… aptallar
için tasarım." Buradaki "aptal" bir hakaret değil, bir **tasarım kısıtıdır**:
kullanıcı yazılım bilmez, bilmek de istemez; işi kebap satmaktır. Ekran ona
bir şey öğretmeye çalışırsa kaybetmiş demektir.
**Kanonik komşular:** form standardı `docs/47`, 320px-first `docs/48`, roller ve
ilk kullanım `docs/70`, yayın `docs/81`, tükendi `docs/82`, yardım `docs/89`,
shell `docs/50`, program sayacı `docs/98`.

---

## 1. Persona — Mehmet Usta

| | |
| --- | --- |
| Kim | 47 yaşında, Adana'da 12 masalı kebapçı; telefon Android, ekran çatlak, veri paketi kısıtlı |
| Ne bilir | WhatsApp, Instagram, banka uygulaması. "Kaydet", "gönder", "sil" — bu kadar |
| Ne bilmez | "Taslak", "yayın", "snapshot", "rendition", "slot", "workspace", "publication" |
| Ne ister | Menüsü masada QR olsun; fiyatı değiştirdiğinde masada değişsin; bitince "bitti" desin |
| Ne korkar | Yanlış tuşa basıp menüyü bozmaktan; müşterinin eski fiyatı görmesinden; "hata" kelimesinden |
| Kim yardım eder | Oğlu (akşam), komşu dükkânın çırağı (hafta sonu). Yani: **tek başına ve gündüz** |

Tasarım kararı: Mehmet Usta'nın yapabildiği şeyi Kadıköy'deki üçüncü nesil
kafeci de yapabilir; tersi doğru değil. Bu yüzden **acemi baz alınır**, uzman
için "ileri" kapılar açılır (`docs/47` Kural 3-4: nadir iş kapalı durur).

---

## 2. Beş çekirdek yolculuk — her adımda TEK karar, TEK ekran

Ölçü: her yolculuk 320×480'de, tek elle, dokunma hedefi ≥ `--density-hit-area-min`,
hiçbir adımda ikiden fazla seçenek yok.

### Y1 — Menüyü kur (ilk 15 dakika)
1. Home → "Şimdi: restoranının adını yaz" (tek büyük düğme)
2. Marka adı → Kaydet
3. Home → "Şimdi: şubeni ekle" → ad, şehir → Kaydet
4. Home → "Şimdi: ilk ürününü ekle" → Menü ekranı; menü boşsa **"Fotoğraftan al / CSV"**
   kutusu AÇIK gelir (60 ürünü tek tek yazma), doluysa KAPALI durur
**Ölçü:** Home'daki büyük düğme her zaman bitmemiş ilk adımı gösterir; hepsi
bittiğinde "Her şey hazır, müşteriler okutabilir" der. Test:
`DashboardSetupJourney.novice.test.tsx`.

### Y2 — Ürün ekle
Kategori seç (varsa), ad, fiyat → Kaydet. Alerjen kapalı ama görünür
(`docs/47` Kural 4). Tek form, tek kaydetme, tek sunucu turu (`docs/47` §3).
**Ölçü:** `MenuCatalogWorkspace.test.tsx` (tek işlem); yeni ürün formun yanında
"eklendi" der (Kural 7).

### Y3 — Fiyat değiştir ✅ (FF-81, 2026-09-04)
Satırda "Fiyat" → yeni rakam → Kaydet → **"Masada görünmesi için yayınla"**
hatırlatması. Unutulan adım budur (`resources/help/tr/first-15-minutes`).
**Ölçü:** fiyat düzenleyici satırın içinde, kaydın adıyla (Kural 8-9);
kaydettikten sonra marka şeritli bir satır çıkar: "Kaydedildi. Misafirler
hâlâ son yayınlanan menüyü görüyor." yanında "Şimdi yayınla" düğmesiyle
(`MenuCatalogWorkspace.publishReminder.test`).

### Y4 — Yayınla ✅ (FF-82, 2026-09-04)
Menü → "Önizle ve yayınla" (tek düğme, `docs/50` §5: yayın menüye aittir) →
Yayınla. Yanlış yayınlandıysa **geri al** (`docs/81`) — "hata" değil, "geri al".
**Ölçü:** `PublicationPage` tek birincil eylem; geri alma bir tıklama.

### Y5 — QR bas
Yayın varken → "Karekod oluştur" → masa sayısı → PDF indir → matbaa.
Basılı kod ölmez: menü değişse de aynı kod çalışır (`docs/81`).
**Ölçü:** toplu QR `docs/08`; fiziksel tarama kanıtı `platform:evidence:attest`.

---

## 3. Acemi kuralları (bağlayıcı — her yeni ekran bunlarla ölçülür)

| # | Kural | Neden | Nasıl ölçülür |
| --- | --- | --- | --- |
| A1 | **Ekranda tek "şimdi"** | İki büyük düğme = "hangisi?" = donma | Home'da tek birincil düğme (`docs/70` §2.1 görev listesi + bu paket) |
| A2 | **Sesli dil** — "Yayınla" değil "Masada göster"? HAYIR: kelime tek olsun ama açıklaması yanında olsun | Terim değiştirmek yardımı ve çeviriyi kırar | Her teknik kelimenin yanında tek cümle ("Yayınla — müşteri bundan sonra yeni fiyatı görür") |
| A3 | **Hata yerine geri alma** | "Hata" korkutur, "geri al" güven verir | Silme çöpe (`docs/49` Faz 5), yayın geri alınır (`docs/81`), fiyat düzenlemesi yazılanı silmez (`docs/47` K6) |
| A4 | **Büyük hedefler** | Çatlak ekran, kalın parmak | `min-h-[var(--density-hit-area-min)]`, `docs/48` kapısı |
| A5 | **Nadir iş kapalı, sık iş açık** | Uzman araçları ilk ekranı kalabalıklaştırır | AI/CSV içe aktarma `<details>`: menü boşken açık, doluyken kapalı |
| A6 | **Durum değil görev** | "Not connected" ne yapacağını söylemez | Her satır "yap" fiiliyle: "ilk ürününü ekle" |
| A7 | **Renk tek başına anlam taşımaz** | Güneşte ekran, renk körlüğü | `✓`/`○` + ekran okuyucu metni (`docs/70` §2.2) |
| A8 | **Öğretme, yaptır** | Turlar/ipuçları okunmaz | Boş durumun kendisi ilk adımı barındırır (boş menüde içe aktarma açık) |

---

## 4. Ölçüm — bugün nerede

| Yolculuk | 320×480 | Tek karar/ekran | Sesli dil | Geri alma | Durum |
| --- | --- | --- | --- | --- | --- |
| Y1 Menü kur | ✅ | ✅ (FF-73 Home "şimdi") | 🔶 "Brand/Location" hâlâ terim | — | Faz 1 ✅ |
| Y2 Ürün ekle | ✅ | ✅ (`docs/47` §3) | 🔶 "Allergens" | ✅ K6 | ✅ |
| Y3 Fiyat değiştir | ✅ | ✅ satır içi | ✅ yayın hatırlatması (FF-81) | ✅ | Faz 2 ✅ |
| Y4 Yayınla | ✅ | ✅ tek düğme | ✅ sesli dil (FF-82) | ✅ `docs/81` | Faz 2 ✅ |
| Y5 QR bas | ✅ | 🔶 tema/boyut seçenekleri önde | 🔶 | ✅ kod ölmez | Faz 3 |

---

## 5. Uygulama planı — fazlar

### Faz 1 — Home "şimdi" + menü sadeleştirme ✅ (FF-73)
- `DashboardSetupJourney`: listenin üstünde tek büyük "Şimdi: …" düğmesi;
  bitmemiş ilk adımın **fiiliyle** ("ilk ürününü ekle"); hepsi bitince "Her
  şey hazır" ve karekod ekranına bağlantı.
- `MenuCatalogWorkspace`: fotoğraftan/CSV içe aktarma tek bir `<details>`
  içinde; menüde ürün yokken açık, varken kapalı; boş menüde tek cümlelik
  yol tarifi.
- Testler: `DashboardSetupJourney.novice.test.tsx`, `MenuCatalogWorkspace.novice.test.tsx`.

### Faz 2 — Sesli dil ve hatırlatmalar ✅ (FF-81, FF-82)
- Fiyat kaydedilince "Masada görünmesi için yayınla" satır içi hatırlatma +
  yayın ekranına düğme.
- Yayın ekranındaki "snapshot/immediate/schedule" metinleri tek cümleye:
  "Yayınla — müşteri bundan sonra bunu görür."
- Terim sözlüğü tek yerde (`i18n` katalog yorumu): terim + tek cümle.

### Faz 3 — QR'ı iki tıka indir
- "Masa sayısı" tek soru; tema/boyut "ileri" arkasında; PDF ilk çıktı.

### Faz 4 — Ölçülü acemi testi
- 3 gerçek acemi (sahibin çevresi), 5 yolculuk, 320 px telefon; ölçü:
  yardım almadan tamamlama oranı ve süre. Sonuç `docs/101`'e yazılır; bu
  belge bir tahminle değil o ölçümle kapanır.

---

## 6. Bu plan neyi YAPMAZ

- Terimleri değiştirmez ("Publication" → "Masada göster" gibi): yardım
  makalesi, çeviri kataloğu ve testler o kelimeyi taşır; kelimeyi değil
  **yanındaki cümleyi** ekler.
- Sihirbaz (wizard) kurmaz: sihirbaz adım atlanınca geri dönülemez; görev
  listesi her adımda geri dönüşlüdür (`docs/70` §2.1).
- Uzman araçlarını silmez: kapatır.

## 7. Kullanıcı yolculuğu (kabul)

Mehmet Usta ilk kez giriş yapar → Home'da tek büyük düğme: "Restoranının adını
yaz" → yazar → Home: "Şubeni ekle" → ekler → Home: "İlk ürününü ekle" → Menü
ekranı boş; en üstte açık bir kutu: "Fotoğraftan al" — menünün fotoğrafını
yükler, 40 ürün gelir, onaylar → Home: "Menünü yayınla" → yayınlar → Home:
"Karekodlarını bas" → 12 masa → PDF → matbaa. Home: "Her şey hazır." Hiçbir
adımda "hata" kelimesi görmedi; yanlış yaptığı iki şeyi geri aldı.
