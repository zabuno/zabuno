# 25 — Stage 8: Exit Ready

**PLANNING ONLY. Şu an çalıştırılamaz.**

## Owner özeti

- **once**: Platform olgun ve iç disiplini var, ama dış bir işlem (satış,
  yatırım, birleşme) için gereken kanıt paketi henüz derlenmiş değil.
- **simdi**: (Maturity Level kanıtı olmadan başlamaz.)
- **fark**: Data room hazır, IP/lisans/SBOM envanteri tam, metrik/finansal
  lineage izlenebilir, müşteri/vendor concentration analizi yapılmış,
  sözleşme/change-of-control maddeleri gözden geçirilmiş, güvenlik/gizlilik
  kanıtı sunulabilir, deploy/restore tekrarlanabilir (reproducible), mimari/
  veri envanteri güncel, key-person riski azaltılmış, transition playbook yazılı.
- **kullaniciYolculugu**: Bir due-diligence ekibi (alıcı/yatırımcı) "bu sistemi
  yeniden kurabilir miyiz, veri kimin, kilit kişi kim" sorularını data room'daki
  belgelerle — ürün ekibine bağımlı olmadan — yanıtlayabilir.
- **kalanEngel**: Maturity Level kanıtı yok; bu stage başlamadı.
- **capability_delta**: iç operasyonel olgunluk → dış işlem hazırlığı.
- **Şu-an-çalıştırılabilir/çalıştırılamaz iddiası**: **Çalıştırılamaz.**

## Amaç
Dış due diligence sürecini ürün ekibinin sürekli müdahalesi olmadan
geçirebilecek bir kanıt/envanter paketi oluşturmak.

## Scope / non-goals
**Scope**: data room, IP/license/SBOM, metric/financial lineage, customer/vendor
concentration, contracts/change-of-control, security/privacy proof, reproducible
deploy/restore, architecture/data inventory, key-person mitigation, transition
playbook.
**Non-goals**: Yeni ürün özelliği (bu stage tamamen envanter/kanıt derlemedir).

## Entry gate
Maturity Level Exit Gate GO.

## Milestone / WP
`docs/26`.

## Module increments
Yok — bu stage mevcut tüm modüllerin **belge/kanıt envanterini** derler
(`docs/28-SOURCE-REGISTER.md` ve `docs/29-TRACEABILITY-MATRIX.md` bu envanterin
teknik omurgasıdır).

## Dependency / critical path
Tüm önceki stage'lerin audit/observability/finansal kayıtları → data room
derlemesi.

## Acceptance evidence
Bağımsız bir üçüncü tarafın (veya simüle edilmiş bir due-diligence checklist'inin)
data room'u eksiksiz bulması.

### Devir kalemi — depo dışı tasarım külliyatı (zorunlu)

Bu depo PUBLIC'tir ve bir `git clone` **tasarım külliyatını getirmez.**
Ayrıntılı tasarım külliyatı ile çalışan referans implementasyonu (tam DTCG
token pipeline'ı, foundations CSS katmanları, AEP renderer pattern'leri, dalga
testleri, ve bu depoda karşılığı olmayan iki sözleşme —
`10-frontend-katman-mimarisi.md` ve `13-foundation-contract.md`) owner
makinesinde yaşar.

Envanteri ve kanonik kararları: [`docs/36-EXTERNAL-DESIGN-CORPUS.md`](36-EXTERNAL-DESIGN-CORPUS.md).

**Devir/exit bu varlık ayrıca aktarılmadan tamamlanmış sayılmaz.** Aktarılmazsa
alıcı taraf ürünün tasarım *gerekçesini* ve çalışan referans implementasyonunu
kaybeder; `docs/06` ve `docs/35` yalnız sonucu taşır. Bu, data room tamlık
yüzdesine dahil edilir ve key-person bağımlılık skorunu doğrudan etkiler —
külliyat aktarılmadığı sürece tasarım sistemi tek kişiye bağlıdır.

## Metrics
Data room tamlık yüzdesi, key-person bağımlılık skoru (kaç kritik süreç tek
kişiye bağlı), reproducible deploy başarı oranı.

## Security / a11y / performance / i18n
Bu noktada bunların hepsi "kanıtlanmış" olarak data room'da belgelenir —
yeniden değerlendirilmez, önceki stage'lerin kanıtı toplanır.

## Rollback trigger
Data room'da kritik bir boşluk (örn. lisans belirsizliği) bulunursa → Exit
Ready GO ertelenir, boşluk kapatılır (örn. `docs/16` LIC-01 gibi maddeler
buradan önce kapanmalı).

## Exit GO/NO-GO/CONDITIONAL
Henüz değerlendirilmedi.

## Next-stage admission
Yok — bu son stage'dir. Sekiz aşamalı sıra burada tamamlanır.
