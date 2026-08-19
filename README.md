# laravelv01 — Zabuno Enterprise Plan Külliyatı

> **DURUM: PLANNING ONLY.** Bu dizin bir doküman ve araştırma korpusudur. Çalışan
> bir Laravel SaaS içermez, hiçbir kurulu bağımlılık, migration edilmiş veritabanı
> veya tamamlanmış entegrasyon iddia etmez. `old/` altındaki arşivlenmiş eski proje
> kökü ile bu dizin arasında runtime bağı yoktur.
>
> **Ürün adı: Zabuno.** `old/` altında arşivlenmiş **legacy** Django/FastAPI
> QR-menü projesinin/denemesinin eski adı, owner talimatı gereği bu külliyatın
> hiçbir yerinde — tarihsel bağlamda bile — yazılmaz (`docs/30` postmortem,
> `docs/31` §7); yeni mimari/isimlendirme kararlarına taşınmamıştır. Bu
> külliyat, tamamlandığında `zabuno/zabuno` adlı tek bir public GitHub deposu
> olacaktır (`docs/31-PUBLIC-REPOSITORY-GOVERNANCE.md`).

## İlerleme

```
0/8 tamamlandı, aktif aşama: plan-onayı (waterfall Stage 1 — MVP başlamadı)
```

Sekiz aşamalı sabit sıra ([`docs/17-WATERFALL-LIFECYCLE-MASTER.md`](docs/17-WATERFALL-LIFECYCLE-MASTER.md)):
MVP → Post-MVP → Go-to-Market → Product-Market Fit → Growth → Enterprise Level →
Maturity Level → Exit Ready. Bu plan korpusunun üretilmiş olması **hiçbir aşamayı
tamamlamaz** — sayaç yalnız kanıtlanmış ürün ilerlemesiyle artar.

Kritik ayrım: **Enterprise sınıfı waterfall yönetişimi** (dokümantasyon disiplini,
ADR'ler, requirements→acceptance→test izlenebilirliği) ilk günden geçerlidir; ayrı
bir kavram olan **Stage 6 "Enterprise Level"** ([`docs/23-STAGE-06-ENTERPRISE.md`](docs/23-STAGE-06-ENTERPRISE.md))
ise SSO/SCIM/data-residency gibi çok daha sonraki bir ürün/operasyon kabiliyet
seviyesidir. Bu iki kavram bu korpusun hiçbir yerinde birbirine karıştırılmaz.

## Bu korpus nedir, ne değildir

| Bu korpus... | ...değildir |
|---|---|
| Modül-modül, faz-faz, milestone-milestone bir plan külliyatı | Çalışan bir Laravel kurulumu |
| Mimari kararlar (ADR) ve gerekçeleri | Tamamlanmış bir entegrasyon seti |
| Gap / unknown-unknown analiz kayıtları | Nihai, değişmez bir spesifikasyon |
| Eski projeden alınmış ürün felsefesi/journey/iş kuralı dersleri | Eski teknoloji seçimlerinin yeni karar olarak taşınması |
| Upstream araştırma anlık görüntüsü (provenance kayıtlı) | Herhangi bir upstream kodun portlanmış hali |

## Nasıl gezinilir

1. **Başlangıç noktası — kapsam ve felsefe**: [`docs/01-PRODUCT-CHARTER-SCOPE.md`](docs/01-PRODUCT-CHARTER-SCOPE.md)
2. **Mimari kararlar**: [`docs/03-ARCHITECTURE-DECISIONS.md`](docs/03-ARCHITECTURE-DECISIONS.md)
3. **Modül kataloğu**: [`docs/04-MODULAR-MONOLITH-CORE-MODULES.md`](docs/04-MODULAR-MONOLITH-CORE-MODULES.md) → tek tek [`modules/`](modules/)
4. **Waterfall aşamaları**: [`docs/17-WATERFALL-LIFECYCLE-MASTER.md`](docs/17-WATERFALL-LIFECYCLE-MASTER.md) → `docs/18`…`docs/25`
5. **Bilinmeyenler**: [`docs/16-GAP-UNKNOWN-UNKNOWNS.md`](docs/16-GAP-UNKNOWN-UNKNOWNS.md)
6. **Kaynaklar**: [`docs/28-SOURCE-REGISTER.md`](docs/28-SOURCE-REGISTER.md)
7. **İzlenebilirlik**: [`docs/29-TRACEABILITY-MATRIX.md`](docs/29-TRACEABILITY-MATRIX.md) — kullanıcı talebinin her maddesi buradan doğrulanabilir.
8. **AI Capability Plane**: [`docs/32-AI-CAPABILITY-MANIFEST-MATRIX.md`](docs/32-AI-CAPABILITY-MANIFEST-MATRIX.md) → [`modules/ai-platform.md`](modules/ai-platform.md) + [`modules/ai-provider-account-vault.md`](modules/ai-provider-account-vault.md)
9. **Vibecoding postmortem**: [`docs/30-VIBECODING-POSTMORTEM-SUCCESS-MODEL.md`](docs/30-VIBECODING-POSTMORTEM-SUCCESS-MODEL.md)
10. **Public repo yönetişimi**: [`docs/31-PUBLIC-REPOSITORY-GOVERNANCE.md`](docs/31-PUBLIC-REPOSITORY-GOVERNANCE.md)

## Dizin yapısı

```
laravelv01/
├── README.md                    ← bu dosya (kanonik indeks)
├── AGENTS.md                    ← AI/insan katkı sağlayıcıları için çalışma kuralları
├── CLAUDE.md                    ← Claude'a özel yazım/kapsam kuralları
├── .gitignore                   ← public zabuno/zabuno depo hazırlığı (docs/31 §5)
├── docs/                        ← 00–32 numaralı kanonik doküman seti (33 dosya)
├── modules/                     ← 62 modül (61 mevcut + ai-provider-account-vault), her biri MODULE-SPEC.md + AI Capability Manifest
├── skills/                      ← 22 skill planı (18 orijinal + 4 AI Capability Plane)
├── templates/                   ← MODULE-SPEC / ADR / MILESTONE-GATE / SKILL-SPEC / AI-CAPABILITY-MANIFEST şablonları
├── research/upstream/           ← dış kaynak anlık görüntüleri + UPSTREAM.md provenance
└── evidence/                    ← Part A arşivleme kanıtları (git/stat/verification, yalnız yerel) + public PUBLIC-ARCHIVE-ATTESTATION.md
```

## Kaynak dokümanlar (bu korpusun girdisi)

- Codex ana kapsam metni — orkestratör oturum eki üzerinden okundu (dosya
  adı/UUID public dokümanda taşınmaz, `docs/31` §7)
- Kök `AGENTS.md` (eski QR Menü SaaS kapsam dokümanı, artık `old/AGENTS.md`) — **[legacy]**
- Kök `CLAUDE.md` (legacy QR-menü projesinin Django tabanlı denemesine ait referans dokümanı, artık `old/CLAUDE.md`) — **[legacy]**, yeni ürün adı Zabuno'dur

Donmuş kapsam kanıtları (kaynak metin SHA-256 değerleri, arşivleme öncesi kök
HEAD taahhüdü) ve sekiz aşamalı sabit sıra, görevi veren Codex Desktop MASTER
talimatında verildiği şekliyle doğrulanmıştır; bu değerlerin kendisi yalnız
yerel ham kanıt kayıtlarında (`evidence/`, yalnız yerel — public depoya
taşınmaz) tutulur, bu public doküman değerleri tekrar basmaz.

## Çalıştırılabilirlik iddiası

**Hiçbir dosya, hiçbir modül spec'i, hiçbir stage dokümanı "şu an çalıştırılabilir"
iddiası taşımaz.** Her stage dokümanı açıkça "şu an çalıştırılamaz / runtime yok"
notunu taşır (bkz. `docs/18`…`docs/25`, alan: *şu-an-çalıştırılabilir/çalıştırılamaz
iddiası*). Bu korpus tamamlandığında bile — yani tüm 33 `docs/` dosyası, 62 modül
ve 22 skill planı yazıldığında bile — ürün hâlâ **0/8**'dedir; plan üretimi bir
MVP teslimatı değildir. AI Capability Plane'in (`docs/32`) mimari olarak
Stage 0'dan itibaren pre-wired olması da bu sayacı **değiştirmez** — mimari
hazırlık, çalışan bir implementasyon değildir.
