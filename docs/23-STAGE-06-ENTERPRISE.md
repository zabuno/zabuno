# 23 — Stage 6: Enterprise Level

**PLANNING ONLY. Şu an çalıştırılamaz.**

> Bu stage bir **ürün/operasyon kabiliyet seviyesidir** — gün 1'den beri
> uygulanan Enterprise sınıfı **waterfall yönetişimi** ile karıştırılmaz
> (`docs/17` §2, bağlayıcı ayrım).

## Owner özeti

- **once**: Ürün küçük/orta ölçekli restoran ve zincirlere hizmet veriyor;
  büyük kurumsal müşterilerin (SSO, SCIM, veri residency, sözleşmeli SLA)
  ihtiyaçları karşılanmıyor.
- **simdi**: (Growth kanıtı olmadan başlamaz.)
- **fark**: SSO/SAML/OIDC, SCIM provisioning, kurumsal audit export, veri
  residency seçenekleri, SLA/DR/HA sözleşmeleri, onay (approval) akışları,
  genişletilmiş API/webhook, özel sözleşme/limit desteği.
- **kullaniciYolculugu**: Büyük bir kurumsal müşterinin IT departmanı kendi
  kimlik sağlayıcısıyla (SSO) çalışanları otomatik provision eder (SCIM) —
  "manuel davet gönder" yerine "merkezi kimlik sisteminden otomatik senkronize
  et" deneyimi.
- **kalanEngel**: Growth kanıtı yok; bu stage'in kendisi henüz planlanma
  aşamasında.
- **capability_delta**: self-service SaaS → kurumsal entegrasyon + sözleşmeli
  garanti seviyesi.
- **Şu-an-çalıştırılabilir/çalıştırılamaz iddiası**: **Çalıştırılamaz.**

## Amaç
Kurumsal müşteri gereksinimlerini modüler monolit mimariyi bozmadan karşılamak.

## Scope / non-goals
**Scope**: SSO/SAML/OIDC, SCIM, enterprise audit export, data residency, SLA/DR/HA,
approval workflows, API/webhooks (genişletilmiş), contracts/custom limits.
**Non-goals**: Mimari mikroservise geçiş — **modular monolith kanıt olmadan
korunur** ("Modular monolith stays unless evidence" — görev talimatının açık
kuralı).

## Entry gate
Growth Exit Gate GO.

## Milestone / WP
`docs/26`.

## Module increments
CORE-03 Authorization'a SSO/SCIM adaptörleri, Integration Hub genişlemesi,
Audit/Event Outbox'a enterprise export formatı.

## Dependency / critical path
Growth'taki CORE-03/CORE-07 → SSO/SCIM adaptör katmanı (mevcut RBAC+ABAC+ReBAC
PDP'nin üzerine eklenir, yeniden yazılmaz).

## Acceptance evidence
SSO/SCIM entegrasyon testi, kurumsal audit export'un sözleşme gereksinimini
karşıladığının doğrulaması.

## Metrics
Kurumsal müşteri sayısı, SLA uyum oranı, SSO/SCIM entegrasyon süresi.

## Security / a11y / performance / i18n
Data residency seçeneklerinin altyapı karşılığı (hangi bölgede barındırma)
netleştirilir; bu Growth'taki opsiyonel S3/Redis katmanının bölgesel
genişlemesi olabilir.

## Rollback trigger
SSO/SCIM entegrasyon hatası mevcut self-service kullanıcıları etkilerse →
enterprise adaptör katmanı izole edilip geri alınır (core auth akışı bozulmaz).

## Exit GO/NO-GO/CONDITIONAL
Henüz değerlendirilmedi.

## Next-stage admission
Maturity Level'a geçiş, **dış ürün kabiliyeti** eklemekten çok **iç operasyonel
sağlamlaştırmaya** odaklanan bir faz değişimini gerektirir (`docs/24`).
