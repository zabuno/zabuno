# 61 — Plan uygulama durumu

**Kaynak:** sahibin verdiği dört planlama belgesi — marka formu UX raporu, AI-first
SaaS UX/yolculuk/kabuk raporu, SaaS panel kabuk mimarisi, dosya/medya yöneticisi
kapsam raporu.

Bu belge **her turda güncellenir**. Amacı tek: hangi maddenin gerçekten yapıldığını,
hangisinin yapılmadığını ve hangisinin bilerek dışarıda bırakıldığını ayırmak.

## Durum sözlüğü

| İşaret | Anlamı |
| --- | --- |
| ✅ | Kodda var ve testle korunuyor |
| 🔶 | Kısmen var — hangi parçanın eksik olduğu satırda yazılı |
| ⬜ | Yok |
| ⛔ | Bilerek yapılmıyor — gerekçe satırda |

**Kural:** bir madde ancak testi varsa ✅ olur. "Kodu yazdım" yeterli değildir;
FF-03a'da yazılmış ve çalışan bir panel, testi olmadığı için ekranda yok sanılmıştı.

---

## A. Kabuk ve navigasyon

| # | Madde | Durum |
| --- | --- | --- |
| A1 | Hash navigation yerine gerçek adresler | ✅ `sectionHref(slug, section)`, `replaceState` |
| A2 | Tenant / platform / engineering kabuk ayrımı | 🔶 tenant ayrı; `/platform` ayrı kabuk; engineering içeriği (readiness, kanıtlar) platform kabuğunda yaşıyor, kendi kabuğu yok — `docs/98` FF-66 |
| A3 | Üç kalıcı sol rail YOK | ✅ tek sidebar |
| A4 | Sidebar görev odaklı gruplandırma (primary/management/utility) | ✅ `group` alanı |
| A5 | Sidebar üstünde workspace switcher | ✅ `WorkspaceSwitcherTrigger` |
| A6 | Sidebar altında account trigger + popover | ✅ masaüstünde dipte, dar ekranda üst çubukta (`docs/63`) |
| A7 | Sağ context inspector | ✅ menü, marka, şube (`docs/60`) |
| A8 | Inspector mobilde ayrı sheet/route | ⛔ mobil pakette panel HİÇ yok (`docs/54`); sheet gerekirse ayrı karar |
| A9 | Global header + page header iki katman | ✅ `topBarCenter`/`topBarEnd` + `PageHeader` (`docs/64` §1) |
| A10 | Header'da location context | ✅ `WorkspaceContextControls` |
| A11 | Global Create düğmesi | ✅ ön koşullu hedefler (`docs/64`) |
| A12 | Help merkezi | ✅ (FF-72) `/help` arkasında gerçek makale var (`resources/help/{en,tr}/first-15-minutes`, `docs/89`); kamu masterpage gezintisinde ve altbilgide (`docs/100`). Uygulama içi Help menüsü hâlâ yok — orada da ilk gerçek içerikle gelir (`docs/64` §4) |
| A13 | Çalışmayan search/notifications gösterilmiyor | ✅ kaldırıldı |
| A14 | Tenant kabuğunda kalıcı footer yok | ✅ |
| A15 | Sabit tema seçici kaldırıldı, account'a taşındı | ✅ `menuitemradio` olarak menüde (`docs/63`) |
| A16 | Adaptive cihaz paketleri | ✅ `docs/54` + `adaptive-bundle-gate` |
| A17 | Skip link, landmark, focus, klavye | ✅ |

## B. Omnibox ve komut merkezi

| # | Madde | Durum |
| --- | --- | --- |
| B1 | Tek omnibox, açık modlar | 🔶 Search/Go to/Create var; Command ve Ask sağlayıcı ve inceleme yüzeyi olmadığı için yok (`docs/65` §3) |
| B2 | Varsayılan mod DETERMİNİSTİK | ✅ AI modu hiç yok; yazılan metin isteme dönüşmez |
| B3 | Görünür kapsam | ✅ çalışma alanı + şube; arama kapsamı grup başlığında yazılı |
| B4 | Riskli komutlar review yüzeyine gider | ⛔ komut modu yok; yarısı yapılmış bir mod en tehlikeli işi en hızlı yola koyardı |
| B5 | `Cmd/Ctrl+K` | ✅ |

## C. Formlar ve alan sahipliği

| # | Madde | Durum |
| --- | --- | --- |
| C1 | Marka formundan `timezone` çıkar → şubeye | ✅ `locations.timezone` + geri doldurma (`docs/62`) |
| C2 | Marka formundan `currency` çıkar → fiyat listesine | ⬜ fiyat listesi nesnesi yok |
| C3 | Genel `locale` alanı parçalanır (ui / content / supported) | ⬜ |
| C4 | Serbest metin yerine allowlist seçim | ✅ marka VE şube formunda; ülke etiketi `Country` |
| C5 | Saat dilimi listeden, `Europe/Istanbul` saklar | ✅ combobox yerine ülkeye göre daraltılmış liste — gerekçe `docs/62` §4 |
| C6 | Para birimi ISO 4217 combobox | ⬜ |
| C7 | Alan anatomisi: label + description + control + error | ✅ `docs/56` |
| C8 | Hata özeti + ilk hatalı alana odak | ✅ `ErrorSummary` + `focusFirstInvalidField`; dört formda (`docs/67`) |
| C9 | `aria-invalid`, `aria-describedby`, canlı bölge | 🔶 kısmî |
| C10 | 422 / 409 / bağlantı / 5xx ayrı ele alınır | ✅ altı arıza sınıfı, altı ayrı cümle (`docs/67`) |
| C11 | Idempotent submit | 🔶 çift tıklama korumalı; sunucu tarafı idempotency arka uç değişikliği ister (`docs/67` §6) |
| C12 | Sabit `form_id` / `field_id` / `error_code` | ⬜ olay şeması önce iş sorularından türetilmeli (`docs/47`) |
| C13 | Sayfa genişliği standardı | ✅ `--container-page-*` |
| C14 | Kontrol kontrastı ≥ 3:1 | ✅ `--border-control` |

## D. Sayfa durumları ve şablonlar

| # | Madde | Durum |
| --- | --- | --- |
| D1 | Durum sözlüğü (loading/empty/error/permission/prerequisite/plan) | ✅ `docs/59` `PageState` |
| D2 | `partial`, `success`, `degraded` durumları | ✅ tanımlı; `success` kesikli çerçeve kullanmaz (`docs/66` §3) |
| D3 | Şablon kataloğu | 🔶 ortak iskelet var (`WorkspacePageFrame` + `PageState`); katalog soyutlaması tekrar ölçülmeden çıkarılmayacak (`docs/66` §5) |
| D4 | Her empty state sonraki eyleme yönlendirir | ✅ sayfa düzeyi boşluklar `PageState` üzerinden; liste içi metinler bilerek düz kaldı (`docs/69`) |
| D5 | Disabled kontrol nedenini açıklar | ✅ `whyNoAction` tip düzeyinde zorunlu |

## E. Ekranlar

| # | Madde | Durum |
| --- | --- | --- |
| E1 | Home: onboarding görev listesi | ✅ tamamlanma durumu, sıradaki adım ve gerçek gezinti (`docs/70`) |
| E2 | Menus: liste + detay sekmeleri (Overview/Content/Design/Languages/Publish/QR/Activity) | ⬜ tek düzey |
| E3 | Publication: checkbox yerine otomatik preflight | 🔶 `isDraftReady` otomatik; ayrıntılı liste eksik |
| E4 | Analytics: ayrı boş durumlar | ✅ dört boşluk ayrıldı, her birinin çıkış yolu farklı (`docs/66`) |
| E5 | Team: rol seçen davet | ✅ Editor/Manager, sınırı yazılı; lokasyon kapsamı MVP dışı (`docs/70` §3) |
| E6 | Team: üye tablosu | 🔶 rol görünür; kapsam ve son etkinlik yok |
| E7 | Billing: yalnız tenant yüzeyi | ✅ ledger/manuel ödeme ayrıldı |
| E8 | Launch readiness tenant kabuğundan çıktı | ✅ |
| E9 | Media: grid/list, filtre, upload drawer | ⬜ düz liste; sürükle-bırak yükleme var (`MediaDropzone`), ızgara/filtre/arama yok — `docs/98` FF-70 |
| E10 | Media: teknik iç süreçler kullanıcıdan gizli | 🔶 durum rozeti sahibin diliyle (`MediaAssetStatusBadge`); işleme kuyruğu/rendition'lar görünmüyor — bu DOĞRU, ama "neden hazır değil" sebebi de görünmüyor |

## F. Medya / DAM

| # | Madde | Durum |
| --- | --- | --- |
| F1 | asset / blob / version / rendition / usage / job tabloları | ✅ göç mevcut |
| F2 | Üç durum ekseni (processing / lifecycle / visibility) | ✅ `ProcessingStatus` (9), `LifecycleStatus` (5), `Visibility` (4) + `media_assets` üç sütun — envanter eskimişti, `docs/49` Faz 1 bunu 2026-08-27'de kapatmıştı |
| F3 | Slot bazlı medya politikası | ✅ `config/media-slots.php` 17 slot + `SlotPolicy` (min ölçü, oran, format, şeffaflık, rendition, alt zorunluluğu); yükleme ekranı slot listesini API'den çeker |
| F4 | Upload session, resumable, idempotency | ⬜ |
| F5 | Sunucu tarafı doğrulama + karantina + AV | 🔶 magic-byte (`StoreMediaRequest`, decode etmeden), karantina→tarama→işleme zinciri, ClamAV (yoksa fail-closed) ✅; **decoder ile yeniden encode, SVG reddi, piksel-bombası sınırı, `fixtures/malicious` CI kapısı ⬜** — `docs/98` FF-68 |
| F6 | Responsive rendition seti + `srcset` | ✅ `GdMediaAssetProcessor` slot politikasındaki genişliklerde `{w}w` profilleri üretir (upscale yok, INV-01); misafir menüsü `srcset` yazar (`public-menu.blade.php`) |
| F7 | Immutable/versioned URL | ✅ `/media/r/{rendition}-{fingerprint}.{format}` + `Cache-Control: public, max-age=31536000, immutable` (`ServeRenditionController`); `ETag` ⬜ |
| F8 | Use mapping'e göre silme etki analizi | ⬜ |
| F9 | Tenant kotası kalemleri | ⬜ |
| F10 | Yayın snapshot'ı asset version'a bağlı | ✅ `recordPublicationUsages` her yayında `media_version_id`'yi dondurur; snapshot görselleri `docs/77` ile yayına yazılır — fotoğrafı düzenlemek canlı menüyü değiştirmez |

## G. AI-first

| # | Madde | Durum |
| --- | --- | --- |
| G1 | Boş AI assistant kartları kaldırıldı | ✅ |
| G2 | Sağlayıcı yokken AI girişi gösterilmez | ✅ kabuk seviyesindeki AI merkezi de kaldırıldı (`docs/65`) — envanterin ilk hâli bunu yanlışlıkla ✅ sayıyordu |
| G3 | Deterministik yol AI kapalıyken çalışır | ✅ |
| G4 | AI önerisi: kapsam + etkilenen kayıt + diff + onay + undo + audit | 🔶 kapsam+etkilenen kayıt+onay+audit ✅ (`docs/97` Yolculuk A/B/C, FF-51…53; `ai_artifacts.applied_at`, `ai_invocations`); **diff görünümü ve undo ⬜** |
| G5 | Bağlamsal AI aksiyonları (ürün açıklaması, çeviri, alt metin) | 🔶 ürün açıklaması ✅ (FF-51), fotoğraftan menü ✅ (FF-53/61), yinelenen ürün ✅ (FF-52); çeviri ⛔ OPT-04 yok (`docs/95`); alt metin ⬜ (`docs/49` Faz 9) |
| G6 | AI işareti yalnız gerçek AI içeriğinde | ✅ |

## H. Ölçüm

| # | Madde | Durum |
| --- | --- | --- |
| H1 | `qr_resolved` ve `public_menu_open_confirmed` ayrımı | ✅ |
| H1b | MVP metrikleri: yaklaşık benzersiz, açılma oranı, lokasyon ve QR kırılımı | ✅ (`docs/68`) |
| H2 | Ürün analitiği olay taksonomisi | ⬜ |
| H3 | Form olayları (`form_viewed`…`form_succeeded`) | ⬜ |
| H4 | Tenant bazında ölçülebilirlik | 🔶 |

## I. Altyapı

| # | Madde | Durum |
| --- | --- | --- |
| I1 | Navigation registry (permission/entitlement/featureFlag) | 🔶 `group`, `path`, `labelKey` var; yetki/flag alanları yok |
| I2 | Feature flag sistemi | ⬜ Pennant kurulu değil |
| I3 | Laravel policy/gate ile nihai yetki | ✅ |
| I4 | Flowbite/Radix görev ayrımı | 🔶 |
| I5 | i18n: bütün metinler katalogdan | ✅ mühürlü katalog |

---

## Tur günlüğü

Her tur hangi maddeleri kapattığını buraya yazar.

### Tur 1 — tamamlandı
- Envanter kuruldu (bu belge).
- **C1** saat dilimi markadan şubeye taşındı; `locations.timezone` eklendi ve
  markadan geri dolduruldu. `brands.timezone` bilerek yerinde bırakıldı.
- **C4** şube formunda ülke serbest metin olmaktan çıktı, listeden seçiliyor.
- **C5** saat dilimi ülkeye göre daraltılmış listeden seçiliyor.
- Yol boyunca iki kusur bulundu ve kapatıldı: liste gelmediğinde kayıtlı
  değerin kaybolması, ve önerilen saat diliminin gösterilip gönderilmemesi.
- Belge: `docs/62`.

### Tur 2 — tamamlandı
- **A6** hesap menüsü masaüstünde kenar çubuğunun dibine taşındı (plan §7);
  dar ekranda üst çubukta kalır. Önceki "her zaman üst çubuk" kararından
  bilinçli geri dönüş, gerekçesiyle birlikte yazıldı.
- **A15** görünüm tercihi sayfanın dibinden menüye girdi; `menuitemradio`
  olarak, renk dışı seçim işaretiyle.
- `ThemeRoot` artık hiçbir kontrol çizmez, yalnız tercihi tutar ve uygular.
- Silinmiş bir deseni doğrulayan test dosyası, sahip olduğumuz sözleşmeyi
  donduran bir dosyayla değiştirildi.
- Kararsız bir klavye iddiası kaldırıldı (dörtte iki kırmızı veriyordu).
- Belge: `docs/63`.

### Tur 3 — tamamlandı
- **A9/A10** ölçüldü: header'ın iki katmanı ve lokasyon bağlamı zaten vardı.
- **A11** Global Create eklendi; yalnız ön koşulu sağlanan hedefler listelenir
  ve hiçbiri uygun değilse menü hiç çizilmez.
- **A12** Help bilerek yapılmadı — arkasında hiçbir içerik yok.
- Kusur bulundu ve kapatıldı: adres kanonikleştirmesi bölüm içi yolu siliyordu;
  `/settings/billing` yenilemede kayboluyordu. Gerileme testi, düzeltme geri
  alınarak kırıldığı doğrulanarak yazıldı.
- `ThemeRoot`'un bileşen olmayan dışa aktarımları ayrı modüle taşındı.
- Belge: `docs/64`.

### Tur 4 — tamamlandı
- **B1–B3, B5** omnibox eklendi: Go to / Create / kayıt araması, görünür
  kapsam, `Cmd/Ctrl+K`, hiç ağ isteği yapmadan.
- Bağlı olmayan AI komut merkezi **kaldırıldı** — devre dışı bir komut kutusu
  ve devre dışı bir onay düğmesiyle birlikte. Envanterin G2 satırı bunu
  yanlışlıkla tamamlanmış sayıyordu; düzeltildi.
- **B4** komut modu bilerek yapılmadı: inceleme yüzeyi olmadan riskli komutlar
  en hızlı yola girerdi.
- Bir iddia adı değil kuralı ölçecek şekilde düzeltildi.
- Belge: `docs/65`.

### Tur 5 — tamamlandı
- **E4** analitiğin tek "0 / 0" ızgarası dört ayrı boş duruma bölündü; her
  birinin çıkış yolu farklı ve en erken engel önce gelir.
- **D2** `partial`, `degraded` ve `success` durumları tanımlandı.
- Üç test "sıfır ızgarası" yerine çıkış yolunu ölçecek şekilde değiştirildi.
- **D3** bilerek ertelendi: ortak iskelet zaten var, katalog soyutlaması
  tekrarın ölçüldüğü bir turda çıkarılmalı.
- Belge: `docs/66`.

### Tur 6 — tamamlandı
- Ölçüm önce: arıza taksonomisi, hata özeti ve odak taşıma **zaten vardı** ama
  yalnız tek formda kullanılıyordu. Yazdığım kopya silindi.
- **C10** altı arıza sınıfı üç forma daha yayıldı; "tekrar deneyin" artık
  yalnız denemenin işe yarayabileceği yerde yazıyor.
- **C8** hata özeti ve odak taşıma dört formda.
- `messageForFailure` ortak modüle çıkarıldı.
- Kusur bulundu: testlerdeki sahte `Response`'ların `headers`'ı yoktu; başlık
  okuyan her yol istisna fırlatıp ağ hatası gibi görünüyordu. 44 fikstür
  düzeltildi.
- Belge: `docs/67`.

### MVP kapanışı
- Plan MVP için dokuz metrik sayıyordu; üçü yoktu. Yaklaşık benzersiz
  ziyaretçi (günlük dönen tuzla türetilmiş, geri çevrilemez anahtar), açılma
  oranı ve iki kırılım eklendi.
- Şube kapsamı isteğe bağlı oldu: marka bütünü tek istekte görünüyor.
- Belge: `docs/68`.
- Altın yolculuk planın BİTİŞ ÖLÇÜTÜNE kadar uzatıldı: tarama artık
  yöneticinin Analytics ekranına yansıdığı için test ediliyor
  (`CRIT-JOURNEY-ANALYTICS-01`). Kaydedici devre dışı bırakılarak kırıldığı
  doğrulandı.
- Definition of Done'ın 19 maddesi kanıtlarıyla `docs/69`'da.
- **Roller düzeltildi**: davet edilen "editör" hiçbir şeyi düzenleyemiyordu;
  planın üç rolü (Owner/Manager/Editor) gerçek izin listeleriyle tanımlandı ve
  sekiz sınır testle donduruldu. Değişiklikten sonra 1011 testin tamamı
  geçmişti — yani hiçbiri bu kusuru tutmuyordu.
- **İlk kullanım ekranındaki beş ölü bağlantı** (`#brand`, `#locations`…)
  gerçek gezintiye çevrildi ve durum listesi görev listesine dönüştü.
- Belge: `docs/70`.

### Müşteri faydası eksikleri (Masaüstü raporu `71-MVP-MUSTERI-FAYDASI-EKSIKLERI.md`)

| ID | Başlık | Durum |
| --- | --- | --- |
| P0-01 | Menü CRUD: silme, ad düzeltme, sıralama | ✅ `docs/73` |
| P0-02 | Varsayılan gizli ürün | ✅ `docs/74` |
| P0-03 | Misafir menüsünde restoran kimliği | ✅ `docs/75` + logo `docs/77` |
| P0-04 | Açıklama + görsel yayın snapshot'ında | ✅ `docs/77` + panel `docs/78` |
| P0-05 | Foto/PDF/CSV aktarma | ✅ CSV `docs/80`; foto: Gemini→OpenAI canlı zinciri (FF-45/49), inceleme ekranı (FF-53), toplu okuma (FF-61); **anahtar sahipte** — adaptörler gerçek API'ye karşı doğrulanmadı (`docs/94`) |
| P0-06 | Gerçek e-posta | 🔶 Mailgun kurulu `docs/93`; kum havuzu alanı kaydolmayı açmaz |
| P0-07 | Canlı dağıtım kanıtı | ✅ zabuno.com canlı (Hüseyin); iki üretim kusuru `docs/87` |
| P0-08 | Medya işleme güvenilirliği | ✅ `docs/76` |
| P0-09 | Veri dışa aktarımı | ✅ `docs/80` |
| P1-01 | Fiyat/deneme/destek yüzeyi | ✅ `docs/88` + `docs/89` (e-posta gönderimi P0-06'ya bağlı) |
| P1-02 | Gerçek ödeme | ⛔ Iyzico anahtarları sahibinde |
| P1-03 | QR hedefi değiştirme + yeniden etkinleştirme | ✅ `docs/81` arka uç; **hedef değiştirme EKRANI FF-64'te geldi** (`docs/98`) — envanter arka ucu ekran sanıyordu |
| P1-04 | "Tükendi" durumu | ✅ `docs/82` |
| P1-05 | Yayın geri alma | ✅ `docs/81` |
| P1-06 | Misafir dil seçimi | ✅ `docs/85` (içerik çevirisi kapsam dışı) |
| P1-07 | Profil/şifre/rol bakımı | ✅ `docs/83` |
| P1-08 | Ürün seviyesi analitik | ✅ `docs/84` |

### Tur 7 — 2026-09-04, `docs/98` SURFACE-CLOSE-v1 (FF-63…FF-65)
- **Envanter üç yerde eskimişti ve düzeltildi:** F2/F3/F6/F7/F10 aslında
  `docs/49` Faz 1'de (2026-08-27) kapanmıştı; G4/G5 FF-51…53 ile büyük ölçüde
  kapandı; P1-03 arka ucu ekran sanılıyordu. "Kanıtı olmayan ✅ olmaz"
  kuralının tersi de geçerli: kanıtı olan ⬜ kalmamalı.
- **FF-63** readiness'ın altı maddesi gerçek kayıttan okunur; makine kanıtı ile
  insan tanıklığı ayrı etiketlenir.
- **FF-64** üç gerçek boşluk: marka logosu bağlama ekranı, QR kodunu başka
  şubeye taşıma ekranı, kategori geneli tükendi.
- Programın kalanı `docs/98` §5'te sayılıyor (13 paket).

## Altı tur bitti

Kalanlar `docs/61`'de ⬜ ve ⛔ olarak duruyor; hiçbiri unutulmuş değil, her
birinin ya bir arka uç ön koşulu ya da yazılı bir gerekçesi var.
