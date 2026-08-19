# vertical-slice-gate

**PLANNING ONLY — bu bir skill spesifikasyonudur, kurulu bir skill paketi değildir.**

## Trigger
Bir work package'in "MVP kritik dikey dilim" kapsamında olup olmadığının
kontrol edilmesi gerektiğinde (`docs/30` §4, implementasyon başlamadan önce
her PR/work package için).

## Inputs
Work package tanımı + `docs/30` §4'teki kritik dilim listesi (register/verify
→ tenant/restoran → menu/category/product/media → preview/publish snapshot →
QR create/physical scan → public menu → first-party analytics; billing/Iyzico
sandbox dahil).

## Authority
Salt-okunur kapsam kontrolcüsü — bir work package'i **onaylamaz/reddetmez**,
yalnız "kritik dilimde mi, opsiyonel genişleme mi" sınıflandırması üretir;
nihai kabul insan (independent reviewer) kararıdır.

## Permitted tools/actions
Work package açıklamasını `docs/30` §4 listesiyle karşılaştırma, OPT-XX
kataloğuyla (`docs/04` §4) çapraz kontrol, "kritik dilim GREEN değilken
opsiyonel modül genişlemesi" ihlalini işaretleme.

## Forbidden actions
Bir work package'i otomatik olarak GO/NO-GO'ya sokmak; kritik dilim
tanımını genişletmek/daraltmak (bu tanım yalnız `docs/30`'da değişir, bu
skill onu **okur**, değiştirmez).

## Deterministic outputs / schema
```
{ work_package_id, in_critical_slice: boolean,
  violates_no-optional-before-green: boolean, note }
```

## Evidence
Her kontrol sonucu ilgili work package'in `docs/26` matris satırına not
olarak eklenir (uygulama başladığında).

## Human approval
Bu skill'in "opsiyonel genişleme, kritik dilim henüz GREEN değil" uyarısı
insan (Architecture/Owner) tarafından override edilebilir ama **sessizce
geçilemez** — override gerekçeli kayıt gerektirir.

## Failure / rollback
Skill kararsız kalırsa (work package açıklaması belirsizse) varsayılan sonuç
**"kritik dilim dışı, insan review gerekli"**dir — belirsizlikte asla
otomatik "kritik dilimde" varsayımı yapılmaz.

## Eval cases
- Ordering/reservation/loyalty gibi bir OPT-XX'in kritik dilim GREEN olmadan
  başlatılmaya çalışıldığı senaryoda uyarı üretilmesi.
- Billing/Iyzico sandbox'ın kritik dilimin **içinde** doğru sınıflandığının
  testi (dışında değil).

## Phase
Stage 1 MVP'den itibaren — implementasyon başladığı andan itibaren her work
package için zorunlu ön kontrol.
