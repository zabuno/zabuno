# 13 — i18n / l10n (PO/MO)

**PLANNING ONLY.**

## 1. Gettext PO canonical

**PO (Portable Object)** kanonik kaynak formatıdır. Buradan iki projeksiyon
üretilir (tek kaynak, çoklu çıktı — `docs/07`'deki fingerprint felsefesiyle
tutarlı bir "tek sahip, projeksiyon" deseni):

```
PO (kanonik) → MO (PHP/Laravel runtime) → JSON (React frontend runtime)
```

Modül-owned text domain: her modül kendi çeviri metinlerinin sahibidir (bir
modül disable edilirse onun çeviri domain'i de birlikte devre dışı kalır —
`docs/03` ADR-L05 modül izolasyonu ile tutarlı).

## 2. Varsayılan dil ve katalog planı

**Varsayılan: English** (bkz. `docs/01` §5 — plan dili Türkçe, ürün UI dili
İngilizce). Hazır altı katalog / provisional locale seti:

```
en (default), tr, de, fr, ar, ru
```

**Arabic RTL tasarım ve test zorunludur** — `docs/06` tema token'ları LTR/RTL'i
aynı anda destekler; her yeni bileşen RTL modunda görsel regresyon testinden
geçmeden "tamamlandı" sayılmaz (`docs/27`).

## 2a. Stage 1 / Stage 2 sözleşmesi (kanonik — bu bölüm tek kaynaktır)

Bu sözleşme burada **kanoniktir**; `modules/core-localization.md` ve
`docs/26` buraya link verir, tekrar tanımlamaz.

- **Stage 1 MVP**: yukarıdaki **altı katalogun tamamı** (en/tr/de/fr/ar/ru)
  için dizilim/scaffold, her modülün text-domain wiring'i ve
  **PO→MO→JSON extraction/projection pipeline'ının tamamı** hazır ve
  entegredir — bu, "Stage 1'de yalnız en+tr var, diğer katalog yok" **değildir**;
  pipeline ve altı katalog iskeleti Stage 1'den itibaren çalışır durumdadır.
  **English kaynak katalog complete/default**'tur; ürünün varsayılan UI dili
  budur.
- **Stage 2 Post-MVP**: diğer beş dilin (tr/de/fr/ar/ru) **içerik-
  completeness'i** — kullanıcının/çevirmenin PO üzerinden dolduracağı tam
  çeviri, **plural-form** ve **context (`msgctxt`)** completeness dahil —
  burada tamamlanır. **Arabic RTL görsel completeness** (yukarıdaki RTL
  zorunluluğunun tam kapsamlı regresyon testi) de Stage 2'nin parçasıdır;
  Stage 1'de yalnız RTL **altyapısı** (yön/token desteği) hazırdır, tam görsel
  completeness kanıtı Stage 2'de üretilir.

Kanonik sahiplik zinciri: bu bölüm (`docs/13` §2a) sözleşmenin **kaynağıdır**;
`modules/core-localization.md` §Phase delivery/§Acceptance ve `docs/26` §1
CORE-08 satırı bu sözleşmeyi **uygular**, yeniden tanımlamaz. İzlenebilirlik
`docs/29`'da doğrulanır.

## 3. Katalog bakım süreci

Extract → merge → fuzzy işaretleme → missing-string tespiti → plural-form
kontrolü → context (`msgctxt`) kontrolü. Bu adımlar CI'da otomatikleştirilir
(`skills/i18n-catalog` bu sürecin deterministik spesifikasyonunu taşır).

## 4. Yerel format kuralları

Tarih/saat, para, ölçü birimi, adres, telefon formatları locale'e göre
değişir; bunlar **Money/Ledger** (CORE-12) ve **Taxonomy** (CORE-09) modülleriyle
koordineli çalışır — para birimi formatlaması burada değil, CORE-12'de
tanımlanır (tek kanonik sahip kuralı).

## 5. Kanonik sahiplik

PO/MO/JSON projeksiyon zinciri ve locale planı burada kanoniktir. Uygulama
detayları `modules/core-localization.md`'de, süreç otomasyonu
`skills/i18n-catalog`'da yaşar.
