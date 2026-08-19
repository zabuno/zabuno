# 21 — Stage 4: Product-Market Fit (PMF)

**PLANNING ONLY. Şu an çalıştırılamaz.**

## Owner özeti

- **once**: Gerçek ödeyen müşteriler var ama tutunma/kullanım kanıtı sistematik
  toplanmıyor.
- **simdi**: (GTM tamamlanmadan başlamaz.)
- **fark**: Activation/retention/cohort/churn/conversion verisi düzenli ölçülür;
  ürün kararları kanıtla (owner-onaylı baseline'a göre) alınır, keyfi sayı
  uydurulmaz.
- **kullaniciYolculugu**: Bir müşterinin "kayıt → aktif kullanım → 30 gün sonra
  hâlâ kullanıyor mu" yolculuğu artık bir kohort raporunda izlenebilir; bu,
  "form submit sonrası sessizce kaybolma" riskini görünür kılar.
- **kalanEngel**: GTM tamamlanmadı; owner-onaylı retention baseline'ı henüz yok.
- **capability_delta**: canlı ödeme → sistematik davranış/tutunma ölçümü.
- **Şu-an-çalıştırılabilir/çalıştırılamaz iddiası**: **Çalıştırılamaz.**

## Amaç
Retention/use kanıtı olmadan Growth'a geçilmeyeceğini garanti eden bir ölçüm
disiplini kurmak.

## Scope / non-goals
**Scope**: cohort analizi, churn/retention dashboard, feedback/deneyim toplama,
experiment altyapısı (A/B için temel).
**Non-goals**: multi-location capacity planlaması (Growth), Enterprise auth
genişletmeleri.

## Entry gate
GTM Exit Gate GO + minimum ödeyen müşteri sayısı (owner tarafından belirlenir,
`docs/16` BIZ-03/04 ile ilişkili).

## Milestone / WP
`docs/26`.

## Module increments
Analytics/Consent/Tagging (cohort/retention görünümleri, first-party ledger
üzerinden — GA4/Yandex Metrica **inbound** reporting adaptörü bu stage'de
**değil**, Growth Stage'de eklenir, `docs/12` §5a, `docs/22`), Feedback/NPS
(OPT-25).

## Dependency / critical path
GTM'deki first-party event ledger → cohort/retention hesaplama job'ları.

## Acceptance evidence
Owner-onaylı retention baseline dokümanı; **owner onayı olmadan keyfi sayı
uydurma yasaktır** (görev talimatının açık kuralı).

## Metrics
Activation rate, D7/D30 retention, churn rate, trial→paid conversion.

## Security / a11y / performance / i18n
Değişiklik yok (GTM seviyesi korunur, ek yük analytics job'larının performans
etkisi izlenir).

## Rollback trigger
Retention verisi güvenilmez/manipüle edilebilir bulunursa (örn. bot trafiği
karışıyor) → ölçüm metodolojisi düzeltilmeden PMF kanıtı kabul edilmez.

## Exit GO/NO-GO/CONDITIONAL
Henüz değerlendirilmedi.

## Next-stage admission
Retention/use kanıtı **yoksa Growth yok** (görev talimatının açık kuralı,
`docs/22` entry gate'i budur).
