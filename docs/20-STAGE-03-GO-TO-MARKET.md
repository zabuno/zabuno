# 20 — Stage 3: Go-to-Market (GTM)

**PLANNING ONLY. Şu an çalıştırılamaz.**

## Owner özeti

- **once**: Sistem stabil ama yalnız sandbox ödeme ile, gerçek müşteri
  onboarding runbook'u yok.
- **simdi**: (Post-MVP tamamlanmadan başlamaz.)
- **fark**: Canlı Iyzico ödeme akışı, consent/legal metinleri yayında, SEO/
  frontpages canlı, onboarding/support runbook'ları hazır, monitoring/backup
  restore/prod yük ve güvenlik/release kapıları geçilmiş.
- **kullaniciYolculugu**: İlk gerçek ödeyen müşteri kayıt olur, kredi kartıyla
  ödeme yapar (Iyzico canlı), fatura alır — "submit → save → reject → retry"
  döngüsünün ödeme sürümü: kart reddedilirse retry akışı, başarılıysa anlık
  entitlement güncellemesi.
- **kalanEngel**: Post-MVP tamamlanmadı.
- **capability_delta**: sandbox ödeme → canlı ödeme + yasal/SEO/operasyon hazırlığı.
- **Şu-an-çalıştırılabilir/çalıştırılamaz iddiası**: **Çalıştırılamaz.**

## Amaç
Gerçek para hareket ettiren ilk müşterilere güvenle açılmak.

## Scope / non-goals
**Scope**: canlı Iyzico, consent/legal, SEO/frontpages canlı, onboarding/support
runbook, monitoring, backup restore doğrulaması, prod load/security/release
gate'leri.
**Non-goals**: PMF metrik toplama derinliği (Stage 4), multi-location (Stage 5).

## Entry gate
Post-MVP Exit Gate GO.

## Milestone / WP
`docs/26`.

## Module increments
Iyzico Payment (live), SEO/Search & Discovery (temel technical+local facet'ler
canlı), Content/Frontpages (tam), Legal Records (tam).

## URL/SEO ek planı (`URL-SEO-v1` Faz 3)

Özel alan adı (tenant kendi alan adını bağlar) bu stage'e eşlenir ve
`docs/39-URL-SEO-ROADMAP.md`'de sahiplenilir.

**Owner kararı gerekir:** özel alan adı hangi pakette ve hangi barındırma
profilinde sunulacak. Sertifika otomasyonu paylaşımlı barındırmada teknik
olarak MÜMKÜN DEĞİLDİR (`docs/15` §4b); veremeyeceğimiz bir sözü satmamak
için karar önden alınmalıdır.

## Dependency / critical path
Post-MVP CORE-12 Money/Ledger → Iyzico live → reconciliation job'ları canlı.

## Acceptance evidence
Sandbox→live geçiş kapısı geçildi kaydı, webhook imza doğrulama testi, restore
drill (production benzeri ortamda).

## Metrics
İlk N ödeyen müşteri, ödeme başarı oranı, destek SLA uyumu.

## Security / a11y / performance / i18n
PCI kapsamını genişletmeme kontrolü (kart saklanmıyor doğrulaması), production
yük testi, WCAG AA production doğrulaması.

## Rollback trigger
Ödeme/webhook güvenlik açığı → live entegrasyon anında sandbox'a geri alınır.

## Exit GO/NO-GO/CONDITIONAL
Henüz değerlendirilmedi.

## Next-stage admission
PMF Stage'e geçiş, aktivasyon/retention kanıtı biriktirmeye başlamayı gerektirir.
