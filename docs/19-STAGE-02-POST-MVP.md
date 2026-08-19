# 19 — Stage 2: Post-MVP

**PLANNING ONLY. Şu an çalıştırılamaz.**

## Owner özeti

- **once**: MVP'nin dikey kritik yolu çalışıyor ama kırılgan — gelişmiş medya,
  otomasyon (ECA), CRM/helpdesk, çoklu dil ve production-hardening eksik.
- **simdi**: (Henüz MVP tamamlanmadığı için bu stage başlamadı — plan aşamasında.)
- **fark**: Sistem stabilize olur; medya pipeline'ı tam devrededir (derivative
  fingerprint, WebP/AVIF), ECA otomasyonu (örn. "stok bitince gizle") aktif olur,
  CRM/Helpdesk temel akışları çalışır, i18n çok-dilli hale gelir.
- **kullaniciYolculugu**: Restoran yöneticisi artık manuel yapılan tekrarlayan
  işleri (stok bitince ürünü tek tek gizlemek gibi) bir kural tanımlayarak
  otomatikleştirebilir — "form submit" yerine "kural tanımla, sistem senin
  yerine tekrar tekrar uygulasın" deneyimi.
- **kalanEngel**: MVP'nin kendisi henüz tamamlanmadı.
- **capability_delta**: MVP dikey yol → +medya olgunluğu +ECA +CRM/Helpdesk
  +çoklu dil +production hardening.
- **Şu-an-çalıştırılabilir/çalıştırılamaz iddiası**: **Çalıştırılamaz.**

## Amaç
MVP'nin kırılgan noktalarını gidermek ve opsiyonel-modül temelini hazırlamak.

## Scope / non-goals
**Scope**: gelişmiş medya/ECA/CRM/helpdesk/i18n/connector'lar, UX/polish,
optional module foundation (registry/manifest tam işlevsel), production-hardening
gap'lerinin kapatılması.
**Non-goals**: canlı ödeme (GTM'e kadar sandbox kalabilir), pSEO ölçeklendirme
(Growth), SSO/SCIM (Enterprise).

## Entry gate
MVP Exit Gate GO/CONDITIONAL-GO almış olmalı.

## Milestone / WP
`docs/26`'da sahiplenilir.

## Module increments
CORE-09 Taxonomy (tam), CORE-10 Workflow (tam), CORE-11 ECA (tam), CORE-13 Media
(derivative pipeline tam), Mini CRM, Helpdesk/Tickets, OPT-01..12 (M1 opsiyonel
katalog) için modül registry temeli.

## Dependency / critical path
MVP CORE modülleri → ECA engine → CRM/Helpdesk (ECA'ya event register eder).

## Acceptance evidence
`docs/27` genel disiplini + medya golden-file testleri + ECA recursion/cycle
guard testi.

## Metrics
Destek talebi çözüm süresi, otomatikleştirilen tekrarlayan işlem sayısı, medya
işleme hata oranı.

## Security / a11y / performance / i18n
Tam çok-dilli katalog (en/tr/de/fr/ar/ru), RTL tam kapsam, medya güvenlik
pipeline'ı (decompression bomb, SVG sanitize) production-hardened.

## Rollback trigger
ECA motorunda sonsuz döngü/veri bütünlüğü ihlali → motor devre dışı bırakılabilir
olmalı (kill switch benzeri).

## Exit GO/NO-GO/CONDITIONAL
Henüz değerlendirilmedi.

## Next-stage admission
GTM Stage'e geçiş için canlı ödeme + consent/legal + SEO/frontpages hazırlığı
gerekir (bkz. `docs/20`).
