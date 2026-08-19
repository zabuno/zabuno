# MODULE-SPEC template

Bu şablon `modules/<key>.md` dosyaları için kullanılır. **PLANNING ONLY** başlığı
her modül spec'inin ilk satırıdır. Değer zinciri (`docs/01` §4) yedi seviyesi
zorunlu alan olarak taşınır.

```markdown
# <Module Name>

**PLANNING ONLY. Şu an çalıştırılamaz.**

## Amaç
(Bir cümle — bu modül hangi Product Capability'yi karşılıyor.)

## Bounded context
(Bu modülün veri/kural sınırı nerede biter, hangi modüle devrolur.)

## Owner
(bkz. docs/02 rol tablosu)

## Sınıf
Core | Required product | Optional (OPT-XX)

## Bağımlılıklar
(diğer modüller — docs/26 §3 dependency graph ile tutarlı olmalı)

## Public contracts / events
(interface/port isimleri, yayınladığı/dinlediği domain event'ler — docs/03 ADR-L05)

## Tenant isolation
(bu modülün tenant-scope kuralı — docs/05 ile tutarlı)

## Permissions
(bu modülün ürettiği izin string'leri — docs/02 §2 permission örnekleri deseniyle)

## Entitlement / quota
(bu modülün hangi plan limitlerine tabi olduğu — docs/09 §4 ile bağlantılı)

## ECA hooks
(bu modülün register ettiği event/action'lar — docs/10 §3)

## AI-off / AI-on davranışı
(AI kapalıyken bu modül nasıl çalışır — docs/01 §3, docs/14 §1)

## UX one-click journey
(bu modülün en sık kullanılan akışı, ölçülebilir tık hedefi — docs/06 §3)

## States
(varsa durum makinesi — docs/10 §2 deseniyle)

## Data retention / export
(docs/04 CORE-15 ile uyum)

## Observability
(bu modülün ürettiği log/metric/health-check)

## Security / privacy
(docs/15 ile uyum — modüle özgü ek riskler)

## Accessibility / i18n
(docs/06 §8, docs/13 ile uyum)

## Phase delivery
(hangi stage'de hangi seviyede — docs/26 §1 matrisine link)

## Acceptance
(kabul kriterleri — implementasyon başlamadan yazılır, docs/27 §1)

## Rollback
(bu modül devre dışı bırakılırsa/geri alınırsa ne olur — disable≠purge kuralı)

## Open questions
(docs/16'ya bağlı açık maddeler, varsa ID referansı)
```

## Kullanım kuralı

Her alan doldurulur; doldurulamıyorsa "bilinmiyor / karar gerekiyor" yazılır ve
`docs/16-GAP-UNKNOWN-UNKNOWNS.md`'ye bir kayıt açılır (`AGENTS.md` §5).

## AI Capability Manifest bölümü — zorunlu ek

Yukarıdaki şablonun **sonuna**, her `modules/*.md` dosyasında ayrıca bir
"## AI Capability Manifest" bölümü eklenir (şema:
`templates/AI-CAPABILITY-MANIFEST.md`, kanonik matris: `docs/32-AI-CAPABILITY-MANIFEST-MATRIX.md`).
Bu bölüm "AI-off / AI-on davranışı" alanının **yerine geçmez** — o alan
modülün genel AI duruşunu, bu bölüm ise `deterministic_baseline` +
`ai_posture` iki-eksenli sözleşmesini (provider/credit/eval detayıyla)
tanımlar. 62/62 modül dosyası (61 mevcut modül + `ai-provider-account-vault.md`)
bu bölümü taşır; hiçbiri boş bırakılamaz.
