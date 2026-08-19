# 17 — Waterfall Lifecycle Master

**PLANNING ONLY.** Bu doküman sekiz aşamalı sabit sıranın **tek kanonik
tanımıdır**; her stage dokümanı (`docs/18`–`docs/25`) buraya link verir, sırayı
tekrar tanımlamaz.

## 1. Sabit sıra (değişmez)

```
1. MVP
2. Post-MVP
3. Go-to-Market (GTM)
4. Product-Market Fit (PMF)
5. Growth
6. Enterprise Level
7. Maturity Level
8. Exit Ready
```

Faz atlama, takvimle otomatik terfi ve kanıtsız "tamamlandı" iddiası **yasaktır**.
Bir sonraki stage'e geçiş yalnız önceki stage'in **Exit Gate**'i kanıtla (evidence)
GO/CONDITIONAL-GO alındığında olur.

## 2. Enterprise yönetişim ≠ Stage 6 "Enterprise Level" — kritik ayrım

| | Enterprise sınıfı waterfall yönetişimi | Stage 6 "Enterprise Level" |
|---|---|---|
| Ne | Dokümantasyon disiplini: ADR, requirements→acceptance→test izlenebilirliği, evidence gates | Ürün/operasyon kabiliyeti: SSO/SAML/OIDC, SCIM, data residency, SLA/DR/HA |
| Ne zaman | **Gün 1'den** geçerli | Stage 5 (Growth) tamamlanıp kanıtla GO alındıktan **sonra** |
| Bu iki kavram karıştırılırsa | "Yönetişim disiplinimiz var, o yüzden Enterprise stage'deyiz" gibi **yanlış** bir sonuç çıkar | — |

Bu külliyatın kendisi (ADR'ler, izlenebilirlik matrisi, gap register) Enterprise
sınıfı yönetişimin **bir örneğidir** — ama bu, ürünün Stage 6'da olduğu anlamına
**gelmez**. Ürün hâlâ **0/8**'dedir (bkz. `README.md` §İlerleme).

## 3. Her stage dokümanının zorunlu alanları

Her `docs/18`–`docs/25` dosyası aşağıdaki alanları **eksiksiz** taşır (owner
persona uyumu için Türkçe alan adları korunmuştur):

```
once, simdi, fark, kullaniciYolculugu, kalanEngel, capability_delta,
şu-an-çalıştırılabilir/çalıştırılamaz iddiası,
amaç, scope/non-goals, entry gate, milestone/WP, module increments,
dependency/critical path, acceptance evidence, metrics,
security/a11y/performance/i18n, rollback trigger,
exit GO/NO-GO/CONDITIONAL, next-stage admission
```

## 4. İlerleme sayacı kuralı

Sabit payda **0/8**; sayaç yalnız bir stage'in Exit Gate'i **kanıtla** GO aldığında
artar. Kapsam değişirse önceki sayaç geriye yazılmaz — yeni adlandırılmış
plan/versiyon açılır (örn. "MVP v2 — genişletilmiş kapsam"), eski ilerleme kaydı
korunur.

## 5. Bu külliyatın kendi durumu

Bu plan korpusunun üretilmiş olması **hiçbir stage'i tamamlamaz**. Şu an:

```
0/8 tamamlandı, aktif aşama: plan-onayı (Stage 1 MVP henüz başlamadı)
```

## 6. Kanonik sahiplik

Sekiz aşamalı sıra, stage-doküman şablonu ve ilerleme sayacı kuralı burada
kanoniktir. Her stage'in kendi içeriği (`docs/18`–`docs/25`) bu kuralları
**uygular**, yeniden tanımlamaz.
