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
(derivative pipeline tam), OPT-01..12 (M1 opsiyonel katalog) için modül registry
temeli.

**Mini CRM ve Helpdesk/Tickets bu stage'den ÇIKARILDI** (owner kararı,
2026-08-27) ve `docs/20` GTM stage'ine taşındı. Gerekçe: aynı gün
yeni-kullanıcı yolunda iki ölümcül kusur bulundu ve ikisinde de testler
geçiyordu. Çekirdek yolculuk gerçek sunucuda kanıtlanmadan yeni modül
eklemek, sağlamlaştırılmamış bir tabanın üstüne kat çıkmaktır. Ayrıca ikisi
de ECA motoruna olay kaydeder, yani ECA'nın tam bitmesini bekler.

## URL/SEO ek planı (`URL-SEO-v1` Faz 2)

Bu stage'e eşlenen URL/SEO maddeleri `docs/39-URL-SEO-ROADMAP.md`'de
sahiplenilir: slug geçmişi + yönlendirme tablosu, admin sorgu parametresi
allowlist'i, tenant başına indeks tercihi, CSP ihlal raporu uç noktası.

Hiçbiri bugün acil değildir ve sebebi kayıtlıdır: menü adresi `key` kimliği
sayesinde kendini onarır, admin filtresi henüz URL'de değildir. Tetikleyici
gerçekleştiğinde zorunlu olurlar.

## i18n çalışma zamanı ek planı (`I18N-RUNTIME-v1`, Faz 1–4)

Bu stage'e eşlenen i18n maddeleri `docs/40-I18N-RUNTIME-ROADMAP.md`'de
sahiplenilir: PO'nun çalışma zamanında okunması, tarayıcı katalogunun ağdan
gelmesi, sunucu tarafındaki 71 çevrilemez dizenin kataloğa taşınması, ve
bozuk PO'ya karşı güvenlik ağı.

Tetikleyici koşullu değildir. Owner çeviriyi olgunluk sonrasında PO
dosyalarından kendisi yapacağını bildirdi (`docs/13` §6); bugünkü boru hattı
derleme adımına bağlı olduğu için FTP ile yüklenen PO hiçbir şey yapmaz.
Yetenek, sahibinin doldurma işine oturmasından ÖNCE hazır olmalıdır —
yoksa doldurma işi boşa gider.

Bu stage'in "i18n çok-dilli hâle gelir" hedefi bu plan olmadan
karşılanamaz: katalog altı dile açılsa bile sahibi onları dolduramaz.

## Tasarım sistemi ek planı (`DESIGN-2030-v1`, Faz 3–6)

Bu stage'e eşlenen tasarım maddeleri `docs/41-DESIGN-SYSTEM-ROADMAP.md`'de
sahiplenilir: katman sözleşmesi R1–R8, Storybook zinciri, frontpage'lerin
aynı token zincirine alınması ve 2030 görev-uyarlamalı arayüz ufku.

Faz 1–2 (token kökü ve ekranda görünen kusurlar) Stage 1 kalanına eşlenir;
owner paneli bugün kullanıyor ve o iki faz bugünkü ekranı düzeltir.

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
