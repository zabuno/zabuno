# 123 — Devir teslim envanteri (2026-09-06)

Bu belge yeni bir oturuma devretmek içindir. **Ölçüldü, tahmin edilmedi.**

## 1. Uçan işler — şu anda çalışan üç ajan

Bu üçü yarım. Yeni oturum başlarken **muhtemelen bitmiş** olacaklar; işleri
kendi çalışma ağaçlarında commit edilmiş hâlde durur, `main`'e alınmaları
gerekir.

| Ağaç | Dal | İş |
| --- | --- | --- |
| `scratchpad/wt-superadmin` | `ff-19x-superadmin` | Süperadmin: kiracı ayrıntısı, kullanıcı yönetimi, denetim günlüğü ekranı (salt okunur) |
| `scratchpad/wt-panel` | `ff-19x-panel` | Panelde puanlama ekranı + misafir menüsünde favoriler (cihazda) |
| `scratchpad/wt-icerik2` | `ff-19x-icerik2` | Kurumsal içerik dalgası 2: görsel/medya, çoklu dil, çoklu şube, çözümler, fiyatlandırma |

**İlk iş:** `git -C <ağaç> log --oneline origin/main..HEAD` ile commit var mı
bak; varsa `main`'den yeni bir dal aç, `cherry-pick` et, kapıları koştur, PR aç.

## 2. Açık PR'lar — CI'da ya da merge bekliyor

| PR | Dal | İş |
| --- | --- | --- |
| #279 | `ff-190-shell-main` | Kurumsal sitede tek kabuk + statik önizleme |
| #281 | `ff-193-dil-main` | Dokuz dilin altyapısı, ağırlıklı dil pazarlığı, dil değiştirici |
| #282 | `ff-195-font` | Roboto depoda barındırılıyor (tabanı #279) |

Ayrıca `scratchpad/wt-plan` (`ff-19x-plan`) **PR'sız ve commit'li**: plan
kataloğu (`ordering.basic`, `menu.rich-media`) + modüller ekranı ölçümü.
Kapıları geçmiş, yalnız PR açılmamış.

## 3. Yerel dallar — atıl mı, değil mi

Çoğu dal `main`'e göre "commit'i var" görünür ama içeriği **squash-merge ile
zaten girmiştir**. Gerçek atıl iş yalnız ikisi:

| Dal | Ne | Karar önerisi |
| --- | --- | --- |
| `FF-51-product-description-ui` | Ürün açıklaması için AI öneri arayüzü, WIP | AI sağlayıcı anahtarı olmadan bitirilemez. Ya sıraya alınır ya silinir |
| `zabuno-mvp-customer-value-gaps-doc-v1` | Tek belge commit'i | İçeriği `docs/110`a taşındı; dal silinebilir |

**`ff-19x-*` ve `ff-19[0-5]-*` dalları uçan işlerdir**, atıl değil.

Çalışma ağaçlarının çoğu boştur ve `git worktree prune` ile temizlenebilir;
yalnız §1'deki üçüne dokunma.

## 4. Yol haritasında planlı ama YAPILMAMIŞ

`docs/107` sayacı: **0/10 faz tamamlandı, 1/10 aktif.** Faz 1 "ilk parayı
almadan önce" ve **hiçbir maddesi bitmedi**:

| # | Madde | Durum |
| --- | --- | --- |
| 1.1 | **Gerçek ödeme alma** | ❌ Yalnız sandbox var; sandbox para tahsil etmez |
| 1.2 | **Yasal metinler** | ❌ `/terms`, `/privacy`, `/kvkk` yer tutucu |
| 1.3 | Abonelik yaşam döngüsü | ◐ İptal, yükseltme, askıya alma yok |
| 1.4 | Fatura | ❌ e-arşiv/e-fatura yolu yok |
| 1.5 | Yedekten geri yükleme **tatbikatı** | ◐ Denenmemiş bir yedek, yedek değildir |
| 1.6 | Destek kanalı ve yanıt taahhüdü | ◐ |
| 1.7 | Ürün içi ilk 15 dakika rehberi | ◐ |

**Bu tablo bugünün en önemli gerçeği.** Sipariş hattı, puanlama, mobil denetim
ve kurumsal site ilerledi; ama **ürün hâlâ para tahsil edemiyor.**

## 5. Planlanmış, sırası gelmemiş dalgalar

| Kaynak | Kalan |
| --- | --- |
| `docs/117` M5–M9 | Profil formu bitişik hedefler, karekod liste satırı, menü kataloğu 12 px düğme, ısı ızgarası taşması, sayfa başlığı dikey ritmi |
| `docs/122` Y6–Y8 | Zengin görsel misafir yüzeyi, kiracı olarak bakma (denetimli, zor), kalan mobil borç |
| `docs/116` P7–P8 | `external_references` eşleme tablosu, ilk dış adaptör (Zomato/Google/Swarm) |
| `docs/114` Dalga 6 | Zengin görsel — hak tanımlı, yüzey yok |
| `docs/111` adım 3–5 | Modül durum rozeti, `modules/` `contexts:` eşlemesi, yaşam döngüsü |
| `docs/121` | Sahte-yerelleştirmenin bulduğu **15 kırık** (TrendChart, DashboardOverview, MenuScreenActions, DesktopSidebar, PageHeader) |
| Dil paketi | Dil değiştirici **hiçbir şablona bağlanmadı**; `zbn_language` çerezini yazan taraf yok |
| Yazı tipi paketi | Arap yazısı barındırılmıyor; `▲`/`▼` glifleri işletim sisteminden |
| Kütük paketi | 380 sayfanın kaynak dil satırı yok (adresleri yazılmadığı için, bilinçli) |
| Kurumsal site | Yaşayan `/pricing` vb. adreslerin `/tr/` diline göçü |

## 6. Dışarıya bağlı — kod bekleyen değil, insan bekleyen

| İş | Kimde | Durum |
| --- | --- | --- |
| ClamAV kurulumu, HEIC/libvips sorusu, `media:rescan-held` | Hüseyin (DevOps) | **Belge henüz İLETİLMEDİ** |
| DNS, SPF/DKIM, GTM/GA hesapları | Ozan | Belge iletildi, **cevap bekleniyor** |
| AI sağlayıcı anahtarı, ödeme sağlayıcısı | Sahip | Kasadan girilecek |

**Ozan'ın cevabı geldiğinde sahibe Hüseyin'in belgesini güncellemeyi
hatırlat** — sahibin açık isteği.

## 7. Sahibin bağlayıcı kuralları (yeni oturumun uyması gerekenler)

1. **Çeviri yapma.** Sahip `ÇEVİRİLERE BAŞLA` demeden tek satır çeviri yok,
   kilit açılmaz, `shipped_locales` genişlemez.
2. **Dar ekran taban.** `TOUCH-FIRST-INTERFACE` (global `CLAUDE.md`). Büyük
   hedef + sıkı boşluk. Doğrulama gerçek düzen motorunda, 320 pikselde.
3. **Bugünün kararları master** (`docs/118`); girdi belgeleri onlara uydurulur.
4. **Emoji yasak**; panelde `@phosphor-icons/react`, **kurumsal sitede ikon yok.**
5. **Uydurma yok:** desteklenmeyen özellik, uydurma fiyat, uydurma müşteri,
   uydurma AI doğruluk oranı yazılmaz — "yakında" diye bile.
6. **Ölçmeden "geçti" denmez.** Ölçüm yapılmadıysa sonuç "bilinmiyor"dur.
7. Soru sorma, karar ver; durmadan geliştir.

## 8. Bu depoda öğrenilen tuzaklar

- **Kabuk cwd'si komşu bir depoya kayıyor.** Her git komutunda `git -C <yol>`.
- **Yeni çalışma ağacında `vendor` sembolik bağ OLMAZ**: Laravel base path'i
  ana ağaca kayar ve testler yanlış dosyaları koşar. `cp -Rl` ile gerçek
  kopya. `node_modules` ve `public/build` de aynı.
- **Prettier CI'da depo KÖKÜNÜ tarar** (`npx prettier --check .`).
  `--check resources/` hiçbir dosya eşleştirmeden "temiz" der — sessiz yeşil.
- **Squash-merge sonrası dal yeniden temellendirilmeli**, yoksa çakışır.
  Yığılı dallarda `cherry-pick`.
- **CI hem SQLite hem PostgreSQL koşar**; PostgreSQL bugüne kadar dört kusuru
  yakaladı.
- **Yazı tipi ortamdan ortama değişiyordu** — depoda barındırılınca ölçüm
  oynaklığı 109 hikâyeden 5'e düştü.

## 9. Nereden başlanmalı

Sıra, **engel kaldırma** ve **para** eksenine göre:

1. §1'deki üç ajanın işini `main`'e al, §2'deki dört PR'ı kapat.
2. **Faz 1.1 ve 1.2** — ödeme ve yasal metinler. Ürün bugün para alamıyor.
3. `docs/122` Y6–Y8, `docs/117` M5–M9, `docs/121`in 15 kırığı.
4. Dil değiştiriciyi şablona bağla, çerezi yazan tarafı ekle.
5. İçerik dalgası 3 ve sonrası (`docs/119` §21 Faz 6 sırası).
