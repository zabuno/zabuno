# 99 — Superadmin estetiği: Metronic'ten ne alındı, ne alınmadı

**Durum:** teslim edildi (FF-66/67, `docs/98`). Bu belge kararın kaydıdır.
**İlgili:** `docs/06` §10 (Operational Hospitality), `docs/36` §5 (külliyat
kararları), `docs/50` (shell ailesi), `docs/48` (320px).

## 1. Soru

Sahip: "superadmin UI estetiği nasıl olmalı? Metronic'ten ilham al." Metronic
(Keenthemes) yıllardır en çok satan yönetim şablonu; iyi olduğu şey **operasyon
yoğunluğu**: çok veriyi az yerde, tarama hızıyla okutmak.

## 2. Alınan — DÜZEN

| Metronic kalıbı | Zabuno karşılığı | Neden |
| --- | --- | --- |
| Soluk uygulama zemini, üstünde beyaz kartlar | `OpsShell` zemini `--color-surface-subtle`, kartlar `--color-surface` | Derinlik tonla kurulur, gölgeyle değil (Flat 2.0, `docs/06` §10) |
| Gruplu, ikonlu, dar sol "aside" | `SidebarNav` grup başlıkları + Phosphor ikonları, `flex-basis 16rem` | `docs/50` §4: tek primary sidebar, 248–272 px bandı |
| "Toolbar" satırı: başlık + breadcrumb solda, eylemler sağda | `OpsPageHeader` | `docs/50` §9.2 page header sözleşmesi |
| Kart: başlık satırı, ince ayraç, sağ üstte kartın araçları | `OpsCard` (`title`, `toolbar`, `padded`) | Kart yalnız sınırı anlam taşıdığında (`docs/36` §5.2) |
| Tablo: soluk başlık satırı, ince satır ayracı, üstte kart başlığı | `AiAuditPage` tabloları (`thead` `--color-surface-subtle`) | Operasyon modüllerinde karşılaştırılabilirlik öne geçer (`docs/36` §5.1) |
| "Light" rozetler (badge-light-success) | mevcut `Badge` (Flowbite, token'lı) | Yeni rozet dili icat edilmedi |
| İki kabuk aynı dili konuşur | `OpsShell` platform ve mühendislik için ORTAK gövde | Kopyalanmış iki kabuk bir hafta sonra iki farklı panel olurdu |

## 3. Alınmayan — ve neden

| Metronic kalıbı | Karar | Neden |
| --- | --- | --- |
| Koyu (dark) aside | **Alınmadı** | Tema token seviyesinde çözülür (`docs/36` §5.8); kabuğa koyu bir yüzey gömmek, açık/koyu temayı ikiye bölerdi. Kullanıcı Account → Appearance'tan koyu temayı seçince ray da koyu olur — ayrıca değil |
| Gölgeli kartlar, çok katmanlı elevation | **Alınmadı** | Flat 2.0: derinlik tonla |
| Piksel sabitleri (`230px` aside, `65px` header) | **Alınmadı** | Bileşen ham piksel bilmez (`docs/36` §5.4); `rem`/token |
| Metronic'in kendi ikon/renk seti | **Alınmadı** | Phosphor + Zabuno semantic renkler; palet yasağı kapısı zaten var (`DS-RAW-PALETTE-BANNED-01`) |
| Suite rail (ürünler arası ikinci ray) | **Alınmadı** | `docs/50` §4: Zabuno tek ürün |
| Bootstrap/jQuery bağımlılığı | **Alınmadı** | Teknoloji sınırı: React + Flowbite/Radix (`docs/37`) |

## 4. Kabuk ayrımı

`/platform` (plan, abonelik, anahtarlar — ticari) ve `/engineering`
(readiness, denetim izi — kanıt) artık iki kabuk. Aynı gövde, farklı bölüm
listesi; üst çubuktan birbirine ve `/app`'e geçiş. `docs/69` madde 3 böylece
🔶'den ✅'ya geçer.

## 5. Ölçülebilir kabul

- Her iki kabukta `sm:`/`md:` breakpoint sınıfı yok; 320 CSS px'te ray
  çekmeceye döner (`docs/48`).
- Kabuk seviyesinde tek `h1` (sayfa başlığı); kabuk kimliği breadcrumb ve
  marka işaretinde.
- Ham piksel/ham palet yok — mevcut tasarım sistemi kapıları geçer.
- Tüm metinler `platform.ts` kataloğundan.

## 6. Sonraki

Plans/Subscriptions sayfalarının iç yüzeyleri (`PlanList`, `CurrentSubscriptionCard`)
`OpsCard`'a taşınmadı — dış çerçeve değişti, iç gövde aynı. Bu, ölçülüp
(karşılaştırma hızı, satır yoğunluğu) sonra yapılacak; estetik için kod
kırmak `docs/36` §5.1'in öncelik sırasına aykırı.
