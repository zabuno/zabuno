# Zabuno — Enterprise Plan Külliyatı

> **DURUM: PLANNING ONLY.** Bu depo (`zabuno/zabuno`, public) bir doküman ve
> araştırma korpusudur. Çalışan bir Laravel SaaS içermez, hiçbir kurulu
> bağımlılık, migration edilmiş veritabanı veya tamamlanmış entegrasyon iddia
> etmez.
>
> **Ürün adı: Zabuno.** Bu külliyatın bir kısmı, tarihsel olarak ayrı bir dış
> arşivde tutulan **legacy** bir QR-menü projesinin/denemesinden süzülmüş
> ürün felsefesi/journey/iş kuralı dersleri taşır — o legacy projenin eski
> adı, owner talimatı gereği bu külliyatın hiçbir yerinde — tarihsel bağlamda
> bile — yazılmaz (`docs/30` postmortem, `docs/31` §7); yeni mimari/
> isimlendirme kararlarına taşınmamıştır. Bu depo, tamamlandığında değil —
> **şu an itibarıyla zaten** — public GitHub deposu **`zabuno/zabuno`**'nun
> kökü olarak yayındadır (`docs/31-PUBLIC-REPOSITORY-GOVERNANCE.md`).

## Ana yol haritası — buradan başlayın

**[`docs/17-WATERFALL-LIFECYCLE-MASTER.md`](docs/17-WATERFALL-LIFECYCLE-MASTER.md)**
— sekiz aşamalı sabit sıranın **master/navigasyon görünümü**: her stage'in
amacı, entry/exit gate özeti ve **38 WP'nin kısa, sıralı indeksi**. Sıra:

```
MVP → Post-MVP → Go-to-Market → Product-Market Fit → Growth →
Enterprise Level → Maturity Level → Exit Ready
```

WP dağılımı (stage başına, toplam 38): S1=7, S2=6, S3=4, S4=4, S5=4, S6=3,
S7=4, S8=6. Kanonik sahiplik üç ayrı dosyaya bölünür, birbirini tekrar etmez:
**`docs/17`** yalnız yukarıdaki master/navigasyon görünümünü taşır;
**[`docs/26-MILESTONE-WORK-PACKAGE-MATRIX.md`](docs/26-MILESTONE-WORK-PACKAGE-MATRIX.md)**
her WP'nin kimliği/sırası/outcome/scope/predecessor/owner/acceptance-evidence
bağı/status ayrıntısının tek kanonik sahibidir; **`docs/18`–`docs/25`**
(stage-detail dokümanları) her stage'in journey/acceptance/stage
ayrıntılarının tek kanonik sahibidir.

## İlerleme

```
0/8 tamamlandı, aktif aşama: plan-onayı (waterfall Stage 1 — MVP başlamadı)
```

Bu plan korpusunun üretilmiş/genişletilmiş olması **hiçbir aşamayı
tamamlamaz** — sayaç yalnız kanıtlanmış ürün ilerlemesiyle artar.

Kritik ayrım: **Enterprise sınıfı waterfall yönetişimi** (dokümantasyon
disiplini, ADR'ler, requirements→acceptance→test izlenebilirliği) ilk günden
geçerlidir; ayrı bir kavram olan **Stage 6 "Enterprise Level"**
([`docs/23-STAGE-06-ENTERPRISE.md`](docs/23-STAGE-06-ENTERPRISE.md)) ise
SSO/SCIM/data-residency gibi çok daha sonraki bir ürün/operasyon kabiliyet
seviyesidir. Bu iki kavram bu külliyatın hiçbir yerinde birbirine karıştırılmaz.

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
4. **Waterfall aşamaları (ana yol haritası)**: [`docs/17-WATERFALL-LIFECYCLE-MASTER.md`](docs/17-WATERFALL-LIFECYCLE-MASTER.md) → `docs/18`…`docs/25`
5. **Bilinmeyenler**: [`docs/16-GAP-UNKNOWN-UNKNOWNS.md`](docs/16-GAP-UNKNOWN-UNKNOWNS.md)
6. **Kaynaklar**: [`docs/28-SOURCE-REGISTER.md`](docs/28-SOURCE-REGISTER.md)
7. **İzlenebilirlik**: [`docs/29-TRACEABILITY-MATRIX.md`](docs/29-TRACEABILITY-MATRIX.md) — kullanıcı talebinin her maddesi buradan doğrulanabilir.
8. **AI Capability Plane**: [`docs/32-AI-CAPABILITY-MANIFEST-MATRIX.md`](docs/32-AI-CAPABILITY-MANIFEST-MATRIX.md) → [`modules/ai-platform.md`](modules/ai-platform.md) + [`modules/ai-provider-account-vault.md`](modules/ai-provider-account-vault.md)
9. **Vibecoding postmortem**: [`docs/30-VIBECODING-POSTMORTEM-SUCCESS-MODEL.md`](docs/30-VIBECODING-POSTMORTEM-SUCCESS-MODEL.md)
10. **Public repo yönetişimi**: [`docs/31-PUBLIC-REPOSITORY-GOVERNANCE.md`](docs/31-PUBLIC-REPOSITORY-GOVERNANCE.md)

## Dizin yapısı (güncel — bu deponun kendi kökü)

```
zabuno/                          ← bu deponun kökü (public zabuno/zabuno)
├── README.md                    ← bu dosya (kanonik indeks)
├── AGENTS.md                    ← AI/insan katkı sağlayıcıları için çalışma kuralları
├── CLAUDE.md                    ← Claude'a özel yazım/kapsam kuralları
├── .gitignore                   ← public zabuno/zabuno .gitignore sözleşmesi (docs/31 §5)
├── docs/                        ← 00–32 numaralı kanonik doküman seti (33 dosya)
├── modules/                     ← 62 modül, her biri MODULE-SPEC.md + AI Capability Manifest
├── skills/                      ← 22 skill planı (18 orijinal + 4 AI Capability Plane)
├── templates/                   ← MODULE-SPEC / ADR / MILESTONE-GATE / SKILL-SPEC / AI-CAPABILITY-MANIFEST şablonları
├── research/upstream/           ← dış kaynak anlık görüntüleri + UPSTREAM.md provenance
└── evidence/                    ← Part A arşivleme kanıtları (git/stat/verification, yalnız yerel) + public PUBLIC-ARCHIVE-ATTESTATION.md
```

Bu depoda güncel kökte bir `old/` dizini **yoktur**. Bu külliyatın süzüldüğü
tarihsel legacy QR-menü projesi/denemesi tamamen bu deponun **dışında**, ayrı
bir dış arşivde tutulur — bu depodan o arşive hiçbir Git ilişkisi yoktur
(`docs/00` §6, `AGENTS.md` §6a). `worktrees/` (varsa) yalnız standart Git
worktree mekanizmasının çalışma kopyalarını ifade eder, ayrı bir arşiv
değildir; bu nedenle `.gitignore`'da dışlanır (public depoya dahil edilmez)
ama bir "eski proje köküyle" karıştırılmaz.

## Kaynak dokümanlar (bu korpusun girdisi)

- Codex ana kapsam metni — orkestratör oturum eki üzerinden okundu (dosya
  adı/UUID public dokümanda taşınmaz, `docs/31` §7)
- Tarihsel arşivleme öncesi (pre-publication) kök `AGENTS.md` (eski QR Menü
  SaaS kapsam dokümanı) — **[legacy, dış arşivde]**, bu depoda karşılığı yok
- Tarihsel arşivleme öncesi kök `CLAUDE.md` (legacy QR-menü projesinin Django
  tabanlı denemesine ait referans dokümanı) — **[legacy, dış arşivde]**, yeni
  ürün adı Zabuno'dur

Donmuş kapsam kanıtları (kaynak metin SHA-256 değerleri, arşivleme öncesi kök
HEAD taahhüdü) ve sekiz aşamalı sabit sıra, görevi veren Codex Desktop MASTER
talimatında verildiği şekliyle doğrulanmıştır; bu değerlerin kendisi yalnız
yerel ham kanıt kayıtlarında (`evidence/`, yalnız yerel — public depoya
taşınmaz) tutulur, bu public doküman değerleri tekrar basmaz.

## Çalıştırılabilirlik iddiası

**Hiçbir dosya, hiçbir modül spec'i, hiçbir stage dokümanı "şu an çalıştırılabilir"
iddiası taşımaz.** Her stage dokümanı açıkça "şu an çalıştırılamaz / runtime yok"
notunu taşır (bkz. `docs/18`…`docs/25`, alan: *şu-an-çalıştırılabilir/çalıştırılamaz
iddiası*). Bu külliyat tamamlandığında bile — yani tüm 33 `docs/` dosyası, 62 modül
ve 22 skill planı yazıldığında bile — ürün hâlâ **0/8**'dedir; plan üretimi bir
MVP teslimatı değildir. AI Capability Plane'in (`docs/32`) mimari olarak
Stage 0'dan itibaren pre-wired olması da bu sayacı **değiştirmez** — mimari
hazırlık, çalışan bir implementasyon değildir.
