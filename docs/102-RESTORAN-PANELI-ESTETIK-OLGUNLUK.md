# 102 — Restoran paneli estetik olgunluğu (kabuk + sayfalar)

**Durum:** Seviye cetveli yazıldı; Faz 1 ✅ (FF-77, 2026-09-04). Sayaç:
**1/4 tamamlandı, 2/4 aktif.**
**Sahibin tespiti (canlı ekran görüntüsü, Home):** "maturity level bir UX
estetiği istedim, yapmadın." Doğru: FF-63…FF-76 yapı ve davranış getirdi;
`docs/99` yalnız superadmin/mühendislik kabuğunu Metronic'ten esinle kurdu.
Restoran panelinde sayfa 2000 px'e yayılmış çıplak bir yüzeydi: kart yok,
ikon yok, tonal derinlik yok, tablo başlığı gövdeyle aynı ton, "Setup" ve
"Home" başlıkları yan yana iki ayrı dünya gibi.
**Kanonik komşular:** görsel formül `docs/06` §10 (Precision Flat 2.0 + Tonal
SaaS Shell + Contextual Cards), dış külliyat `docs/36`, superadmin estetiği
`docs/99`, shell planı `docs/50`, acemi kuralları `docs/101`, 320px `docs/48`.

---

## 1. İlke — aynı dil, iki kabuk

Restoran paneli superadmin kabuğuyla **aynı görsel dili** konuşur
(`docs/99` §2 tablosu); farkı yoğunluk ve ton sıcaklığıdır: operasyon
paneli sıkı ve karşılaştırmalı, restoran paneli ferah ve tek-odaklı
(`docs/101` A1 tek "şimdi").

| Metronic kalıbı | Restoran paneli karşılığı | Token |
| --- | --- | --- |
| Soluk uygulama zemini, üstünde beyaz kartlar | `<main>` zemini `surface-subtle`, kartlar `surface` | `--color-surface-subtle` / `--color-surface` |
| İkonlu, gruplu sol aside | `SidebarNav` gruplar + Phosphor ikon (kayıt: `icon`) | `--space-*`, `--density-*` |
| Kart: başlık satırı + ince ayraç + sağ üst araç | `OpsCard` (ops ile ortak bileşen) | `--radius-md`, `--color-border` |
| Tablo: soluk başlık satırı | `ResponsiveDataTable` thead `surface-subtle`, meta büyük harf | `--color-surface-subtle` |
| Vurgu şeridi | "Şimdi" kartında marka rengi sol şerit (`border-s-brand`) | `--color-brand` |
| Gölge | **Yok** — derinlik tonla (Flat 2.0) | — |

Alınmayanlar `docs/99` §3 ile aynıdır (koyu aside, gölge, piksel sabiti,
Metronic ikon/renk seti, suite rail, Bootstrap).

---

## 2. Olgunluk cetveli — yüzey başına

| Seviye | Tanım | Ölçü |
| --- | --- | --- |
| **L0 Çıplak** | Sayfa = form/liste; kart yok, ikon yok, zemin tek ton | ekran görüntüsü |
| **L1 Yapısal** | Tek `h1`, page header, bölümler, akışkan ızgara, 320 px kapısı | `PublicPageIdentity`, `HOME-FLUID-04`, DS kapıları |
| **L2 Tonal + kart** | Zemin/kart tonu, kart grameri, ikonlu gezinti, tablo başlığı tonlu, tek birincil eylem | `docs/102` §4 kabul |
| **L3 Yoğunluk ve ritim** | 8pt ritmi tutarlı, yoğunluk token'ları (`--density-*`) her kontrolde, boş/yükleniyor/hata durumları tasarlı | template kataloğu (`docs/50` Faz 4) |
| **L4 Kişilik** | Marka ifadesi (sıcak vurgular, illüstrasyon, mikro-hareket), tema uyumu, gerçek kullanıcı testi | `docs/101` Faz 4 ölçümü |

### Bugün (FF-77 sonrası)

| Yüzey | Önce | Şimdi | Sonra |
| --- | --- | --- | --- |
| Kabuk (header/sidebar/main) | L1 | **L2** — main `surface-subtle`, sidebar `surface`, ikonlu gezinti | L3: yoğunluk token'ları header'da |
| Home | L0 | **L2** — tek `h1`, "Şimdi" vurgu kartı, Setup kartı, istatistik kartları, tablo kartı | L3: boş/yükleniyor durumları kartta |
| Media | L1 (FF-76) | L1 → L2 için kart grameri gerekir | FF-78 |
| Menus | L1 | L1 | FF-78: kategori kartları, satır yoğunluğu |
| QR codes / Insights / Locations / Team / Settings | L1 | L1 | FF-78 |

---

## 3. Faz 1 — kabuk + Home (FF-77) ✅

- `AdminShell` main zemini `surface-subtle`; `DesktopChrome` aside `surface`.
- `WorkspaceSectionDescriptor.icon`; sekiz bölümün Phosphor ikonu
  (House, ForkKnife, QrCode, ChartBar, MapPin, Image, Users, Gear); mobil
  çekmece ve omnibox aynı kayıttan okur.
- Home: `WorkspacePageFrame` başlığı "Home" (tek `h1`); "Şimdi" kartı marka
  şeritli; Setup görev listesi kartta; istatistikler `StatCard`; ürün tablosu
  kartta, başlık satırı tonlu.
- Tablo başlığı: `ResponsiveDataTable` thead `surface-subtle` + meta büyük harf.

## 4. Kabul (Faz 1)

- Home'da tek `h1`; Setup ve "Şimdi" `section`/`aria-label` ile adlandırılmış.
- Kabukta breakpoint sınıfı yok; 320 px'te tek sütun.
- Ham piksel/ham palet yok (DS kapıları); gölge sınıfı yok.
- Gezinti öğelerinin her birinde ikon (`aria-hidden`), etiket katalogdan.

## 5. Faz 2-4

- **Faz 2 (FF-78):** Menus/Media/QR/Insights sayfa gövdeleri kart gramerine;
  liste satırı yoğunluğu; boş/yükleniyor/hata durumları `PageState` ile kartta.
- **Faz 3:** header yoğunluk token'ları, omnibox görünümü, account popover.
- **Faz 4:** marka ifadesi (sıcak vurgu, illüstrasyon), tema uyumu, gerçek
  kebapçı testi (`docs/101` Faz 4 ile aynı oturum).

## 6. Kullanıcı yolculuğu

Mehmet Usta Home'u açar: solda ikonlu kısa bir menü, ortada tek büyük
"Şimdi: menünü yayınla" kartı, altında beş adımlık kurulum kartı ve dört
sayı kartı; sayfa çıplak bir tabloya değil bir panele benzer. Neyi
yapacağını okumadan görür.
