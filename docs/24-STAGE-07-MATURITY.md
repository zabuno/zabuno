# 24 — Stage 7: Maturity Level

**PLANNING ONLY. Şu an çalıştırılamaz.**

## Owner özeti

- **once**: Ürün kurumsal müşterilere hizmet veriyor ama iç operasyonel
  disiplin (SRE, maliyet yönetimi, uyumluluk programı) ad-hoc.
- **simdi**: (Enterprise Level kanıtı olmadan başlamaz.)
- **fark**: SRE/SLI/SLO tanımlı, DORA metrikleri izleniyor, unit economics
  şeffaf, deprecation/compatibility politikası var, incident/DR tatbikatları
  düzenli, privacy/security programı kurumsallaşmış, vendor yönetimi disiplinli,
  observability operasyonel mükemmeliyet seviyesinde.
- **kullaniciYolculugu**: Bir incident yaşandığında (örn. ödeme sağlayıcısı
  kesintisi) ekip önceden tatbik edilmiş bir runbook'u izler; müşteriye "ne
  oldu, ne zaman düzelir" bilgisi SLA'ya bağlı otomatik iletişimle gider —
  "sessiz hata" yerine "öngörülmüş, tatbik edilmiş yanıt" deneyimi.
- **kalanEngel**: Enterprise Level kanıtı yok.
- **capability_delta**: kurumsal entegrasyon → iç operasyonel sağlamlaştırma.
- **Şu-an-çalıştırılabilir/çalıştırılamaz iddiası**: **Çalıştırılamaz.**

## Amaç
Dışa dönük özellik eklemeden, platformun kendi operasyonel dayanıklılığını
kurumsallaştırmak.

## Scope / non-goals
**Scope**: SRE/SLI/SLO, DORA metrikleri, cost/unit economics, deprecation
politikası, incident/DR tatbikatları, privacy/security programı, vendor
yönetimi, observability operasyonel mükemmeliyet.
**Non-goals**: Yeni müşteri-yüzü özellik geliştirme (bu stage'in odağı içe
dönüktür).

## Entry gate
Enterprise Level Exit Gate GO.

## Milestone / WP
`docs/26`.

## Module increments
Yeni bir "modül" değil — mevcut `docs/15` §6'daki yatay observability standardı
(OpenTelemetry, SLI/SLO), CORE-07 Audit/Event Outbox ve CORE-15 Data Lifecycle
üzerine operasyonel süreç/runbook katmanı (kod değişikliği minimal, süreç/kanıt
disiplini maksimal). CORE-14 burada Notifications'tır (`docs/04`), bu stage'in
konusu değildir.

## AI entegrasyonu ek planı (`AI-INTEGRATION-v1` Faz 7)

Bu stage'e eşlenen AI maddeleri `docs/95-AI-ENTEGRASYON-YOL-HARITASI.md`'de
sahiplenilir: bugüne kadar eksik kalan işletim katmanı (kuyruk-işçisi,
dead-letter, devre kesici, idempotency) ve otomatikleştirilmiş prompt-
injection/kalite eval seti.

Bu stage'in kendi deseniyle aynı: kod değişikliği minimal, süreç/kanıt
disiplini maksimal.

## Dependency / critical path
Tüm önceki stage'lerin observability/audit altyapısı → SRE süreçlerinin bu
veriyi tüketmesi.

## Acceptance evidence
Gerçekleştirilmiş DR tatbikatı raporu, tanımlı SLO'lara karşı gerçek uyum
verisi, vendor risk değerlendirme kaydı.

## Metrics
DORA dörtlüsü (deployment frequency, lead time, change failure rate, MTTR),
SLO uyum yüzdesi, incident sayısı/trendi.

## Security / a11y / performance / i18n
Privacy/security programının resmi bir sahibi ve periyodik review takvimi
olur; a11y/performance/i18n bu noktada "özellik" değil "sürekli izlenen SLI"
haline gelir.

## Rollback trigger
Bu stage'de "rollback" kavramı özellikten çok süreç kalitesine dairdir — bir
runbook'un tatbikatta başarısız olması, o runbook'un revize edilmesini
tetikler (ürün rollback'i değil).

## Exit GO/NO-GO/CONDITIONAL
Henüz değerlendirilmedi.

## Next-stage admission
Exit Ready'e geçiş, dış (üçüncü taraf) due diligence'a hazır olacak kanıt
kalitesini gerektirir (`docs/25`).
