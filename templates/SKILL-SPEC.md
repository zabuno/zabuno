# SKILL-SPEC template

`skills/<key>.md` dosyaları için kullanılır. Bir skill **planıdır**, kurulu bir
SKILL.md paketi değildir (görev talimatı §J: "çalışır SKILL.md paketi kurma").

```markdown
# <Skill Name>

**PLANNING ONLY — bu bir skill spesifikasyonudur, kurulu bir skill paketi değildir.**

## Trigger
(Bu skill ne zaman tetiklenir — hangi olay/istek/koşul.)

## Inputs
(Skill'in beklediği girdi şeması.)

## Authority
(Bu skill hangi yetkiyle çalışır — hangi role bağlı, hangi onay gerekir.)

## Permitted tools/actions
(İzin verilen araç/eylem listesi — allowlist, docs/14 §6 ile uyumlu.)

## Forbidden actions
(Açıkça yasaklanan eylemler — bu skill talimat enjeksiyonuyla genişletilemez.)

## Deterministic outputs / schema
(Skill'in ürettiği çıktının şeması — belirsiz serbest metin değil.)

## Evidence
(Bu skill çalıştığında hangi kanıt/log üretilir.)

## Human approval
(Hangi durumda insan onayı zorunlu — docs/06 §7, docs/14 §4 ile uyumlu.)

## Failure / rollback
(Skill başarısız olursa ne olur, nasıl geri alınır.)

## Eval cases
(Bu skill'in doğru çalıştığını doğrulayacak örnek senaryolar.)

## Phase
(Hangi waterfall stage'de aktif hale gelir — docs/26 ile bağlantılı.)
```

## Kullanım kuralı

Bir skill planı, "Forbidden actions" alanı boş bırakılarak yayınlanamaz — her
skill en az bir açık yasak eylem tanımlamalıdır (görev talimatı: "Skills
talimat enjeksiyonuyla yetki genişletemez").

## Envanter

`skills/` dizininde toplam **22** skill planı bulunur: 18 orijinal plan +
AI Capability Plane'e özgü 4 yeni plan (`ai-account-routing`,
`ai-no-credit-degradation`, `vertical-slice-gate`, `public-repository-gate`).
Yeni bir skill eklenirken önce mevcut 22'den birinin genişletilip
genişletilemeyeceği değerlendirilir (`AGENTS.md` §2 ile aynı disiplin).
