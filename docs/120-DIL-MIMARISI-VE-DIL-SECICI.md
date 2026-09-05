# 120 — Dil mimarisi, GILT ve dil değiştirici

**Sahibin kararı (2026-09-05):** *"Ana dili İngilizce, Türkçe opsiyonel, ayrıca
(daha sonra) tercümesi yapılacak diller (bugünden itibaren altyapı çok dili
desteklemeli): İngilizce, Türkçe, Arapça, Rusça, Farsça, Kürtçe, Almanca,
Fransızca, İtalyanca. GILT desteği sağlanmalı. Güçlü bir UX ile dil
değiştirici olmalı, bayrakları da yanında olmalı. Her dil, o dilde yazıldığı
gibi görünmeli — 'Turkish' değil 'Türkçe'."*

Bu belge `docs/118` E4'ün açık varsayımını **kapatır**.

## 1. Karar

| | Karar |
| --- | --- |
| Ana dil (kaynak) | **İngilizce** (`en`) |
| Bugün sunulan | Yalnız `en` (`i18n.shipped_locales`) |
| Altyapının bugün desteklemesi gereken | **Dokuz dil** |
| Çeviri üretimi | **Kilitli** — sahip açmadan tek çeviri yapılmaz |

**Altyapı dokuz dili bugünden tanır, ürün bugün yalnız birini sunar.** İkisi
farklı sorular: *"bu dili tarif edebiliyor muyuz?"* ile *"bu dilde eksiksiz bir
ürün verebiliyor muyuz?"*. Ayrım bugün kuruldu (`NegotiateLocale` artık
`shipped_locales` okuyor) ve bu belge onu genişletiyor, bozmuyor.

## 2. Dokuz dil — kod, endonim, yön, yazı

| Kod | Endonim (kendi dilinde) | Yön | Not |
| --- | --- | --- | --- |
| `en` | English | LTR | Kaynak dil |
| `tr` | Türkçe | LTR | Katalog **zaten tam** (1997 metin) |
| `ar` | العربية | **RTL** | |
| `ru` | Русский | LTR | |
| `fa` | فارسی | **RTL** | Sahibin "İran'ca" dediği dil |
| `ku` | Kurdî | LTR | **Kurmancî**, Latin yazı — §6'ya bak |
| `de` | Deutsch | LTR | |
| `fr` | Français | LTR | |
| `it` | Italiano | LTR | |

**Endonim kuralı — sahibin kararı ve gerekçesi onun kendi cümlesinde:**
*"yabancı dil bilmeyen Türk, kendi dilini kendi dilinde okuyabilsin."* Dil
adı **hiçbir zaman** arayüzün o anki diline çevrilmez; her zaman kendi dilinde
yazılır. Bu, dil değiştiricinin çevrilmeyen tek yeridir ve bu kasıtlıdır: bir
kullanıcı arayüzü anlamadığı için dil değiştirmeye gelir; onu anladığı tek
kelimeden mahrum bırakmak, aracın kendisini bozar.

## 3. GILT — dört harfin bu depodaki karşılığı

| Harf | Ne demek | Bu depoda karşılığı |
| --- | --- | --- |
| **G** — Globalization | Ürünün birden çok pazarda çalışabilir olması | Kiracı başına para birimi, saat dilimi, ülke; iş türü segmentleri; şube bazlı ayarlar |
| **I** — Internationalization | Kodun dile ve bölgeye bağımlı olmaması | Metin kodda gömülü değil katalogda; yön locale'den türer; para/tarih/sayı biçimi biçimlendiriciden; RTL şablonda sabit değil |
| **L** — Localization | Belirli bir pazara uyarlama | Yerel para birimi ve ödeme sağlayıcısı, yerel mevzuat metinleri, yerel örnekler, yerel görsel |
| **T** — Translation | Metnin çevrilmesi | PO/MO katalogları, çeviri kilidi, `stale` işaretleme, alan bazlı geri düşme |

**En sık karıştırılan yer I ile T.** Çeviri kilitliyken bile
uluslararasılaştırma **çalışmak zorundadır**: bir metin kodda gömülüyse o metin
hiçbir zaman çevrilemez ve kilidin açılması bir işe yaramaz. Bu yüzden yeni
metin İngilizce de olsa **katalogdan** gelir.

## 4. Dil seçimi — hangi sinyaller GERÇEKTEN var

Sahip dört sinyal saydı: cihaz dili, tarayıcı dili, **klavye dili**, bölge.
Üçü ölçülebilir, biri ölçülemez ve bunu söylemek zorundayım.

| Sinyal | Var mı | Nasıl |
| --- | --- | --- |
| Tarayıcı / cihaz dili | **Var** | `Accept-Language` (sunucu) ve `navigator.languages` (istemci). İkisi de işletim sistemi dilinden türer |
| Bölge (saat dilimi) | **Var** | `Intl.DateTimeFormat().resolvedOptions().timeZone` — ülke için iyi bir vekil |
| Bölge (IP) | Koşullu | Sağlayıcı gerekir; bugün yok. Eklenirse sunucuda ve onaya tabi |
| Kullanıcının kayıtlı tercihi | **Var** | Oturum açmış kullanıcıda hesap ayarı; anonimde çerez |
| **Klavye dili** | **YOK** | Tarayıcıda klavye düzenini okuyan bir API **yoktur**. Sahibin isteği makul ama web platformu bunu vermiyor |

**Klavye dili neden yok:** düzen bilgisi işletim sisteminde yaşar ve tarayıcı
onu sayfaya açmaz — açsaydı bu bir parmak izi sinyali olurdu. Yazılan
karakterlerden tahmin etmek mümkün ama bu bir **tahmin**dir ve bu deponun
kuralı ölçülmeyen şeyi ölçülmüş gibi göstermemektir. Sinyal listesine
yazılmadı; yazılsaydı hiçbir zaman çalışmayan bir kural olurdu.

### Öncelik sırası

```
1. Kullanıcının AÇIK seçimi      (çerez / hesap ayarı)   — her şeyi yener
2. Adresteki locale segmenti     (/tr/, /en/)            — kurumsal sitede
3. Accept-Language               (tarayıcı/cihaz)
4. Saat dilimi                   (bölge vekili)          — yalnız 3 belirsizse
5. Kaynak dil                    (en)
```

**Açık seçim neden her şeyi yener:** Almanya'da yaşayan bir Türk, tarayıcısı
Almanca olsa da Türkçe okumak isteyebilir. Bir kez seçtiyse, sistem onu bir
daha sorgulamaz — sorgulasaydı her ziyarette kararını geri alırdı.

**Bölge sinyali dili SEÇMEZ, yalnız BELİRSİZLİĞİ ÇÖZER.** İstanbul'daki bir
tarayıcı `en` diyorsa, dil İngilizcedir; saat dilimine bakıp Türkçeye
çevirmek, kullanıcının açık ayarını görmezden gelmektir.

## 5. Dil değiştirici — davranış sözleşmesi

1. **Her dil kendi dilinde yazılır** (§2). Arayüz dili ne olursa olsun.
2. Yanında **bölge işareti** (§6).
3. Aktif dil işaretli — yalnız renkle değil, metinle de.
4. Klavyeyle tam kullanılabilir; odak görünür; `aria-current` doğru.
5. Dokunma hedefi ≥44 px, dar ekranda da (`TOUCH-FIRST-INTERFACE`).
6. **JavaScript olmadan çalışır:** gerçek `<a href>` bağlantıları, her dilin
   kendi adresi. Betikle açılan bir menü, betik yüklenmeden gelen kullanıcıyı
   dilsiz bırakırdı.
7. Dil değiştirmek **aynı sayfada kalır** — `page_key` üzerinden karşılık
   bulunur, ana sayfaya atmaz. Karşılığı yoksa bunu söyler.
8. **Henüz sunulmayan dil gösterilmez** ya da açıkça "henüz hazır değil" der.
   Seçilebilir görünüp yarım çeviri vermek, bugün kapatılan kusurun ta
   kendisidir.
9. Yön değişimi belgeye uygulanır (`<html dir>`), şablona değil.

## 6. Bayrak sorunu — sahibin isteği ve dürüst sınırı

Sahip bayrak istedi. Bayrak bir **ülkeyi** gösterir, dili değil, ve bu üç
dilde gerçek bir soruna yol açar:

| Dil | Sorun | Karar |
| --- | --- | --- |
| `ar` — العربية | Yirmiden fazla ülkenin dili | Tek ülke bayrağı **yok**; nötr bölge işareti |
| `ku` — Kurdî | Devlet bayrağı yok; kullanılan işaretler siyasi | Bayrak **yok**; nötr işaret |
| `en` — English | Birleşik Krallık mı, ABD mi? | Nötr işaret |
| `tr`, `ru`, `fa`, `de`, `fr`, `it` | Dil ile ülke birebir eşleşiyor | Ülke bayrağı |

**Neden böyle:** yanlış bayrak sessiz bir hata değildir — kullanıcıyı kimliği
üzerinden yanlış yerleştirir ve kimi durumda siyasi bir iddia taşır. Bir ürünün
dil menüsü böyle bir iddiada bulunmamalıdır.

**Görsel hiyerarşi:** endonim BİRİNCİL, bayrak ikincil. Bayrak tek başına dil
anlatmaz ve tek başına bırakılırsa renk körü ya da küçük ekran kullanıcısı
için hiçbir şey söylemez.

## 7. Bugün ne yapılır, ne yapılmaz

**Yapılır:**

- Dokuz dilin tanımı: kod, endonim, yön, yazı sistemi, bölge işareti — tek
  kaynakta.
- Dil değiştirici bileşeni ve sözleşme testleri.
- Sinyal zinciri (§4) ve testleri.
- RTL'nin dokuz dilin üçünde gerçekten çalıştığının ölçülmesi.
- Katalog altyapısının dokuz dili taşıyabildiğinin ölçülmesi.

**Yapılmaz:**

- **Tek satır çeviri.** Kilit kapalı; sahip `ÇEVİRİLERE BAŞLA` demeden açılmaz.
- `shipped_locales` genişletilmez. Bir dil ancak katalogu TAM olduğunda
  listeye girer; yarım çeviri çevirisizlikten kötüdür.
- Türkçe katalog tam olsa bile **sunulmaz** — sahibin kararı beklenir.

## 8. Bu belgenin kendi gerekçe süresi

`docs/109` §8.6.

- **Klavye sinyali** bir gün bir platform API'siyle gelirse §4 yeniden yazılır.
- **Kürtçe** bugün Kurmancî (`ku`, Latin, LTR) olarak alındı. Soranî (`ckb`,
  Arap yazısı, RTL) ayrı bir dildir ve gerekirse ayrı eklenir — ikisini tek
  koda sıkıştırmak, birini yanlış yazı sistemiyle göstermek olurdu.
- **Bayrak istisnaları** (§6) sahibin kararıyla değişebilir; değişirse gerekçe
  burada güncellenir.
