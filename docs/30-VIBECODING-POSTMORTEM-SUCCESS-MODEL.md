# 30 — Vibecoding Postmortem & Success Model

**PLANNING ONLY.** Bu doküman, bu plan külliyatının **neden** var olduğunun
kanonik gerekçesidir: önceki iki vibecoding denemesinin (legacy QR-menü
girişimleri) neden başarısız kabul edildiği ve bu külliyatın hangi başarı
modeliyle o hatayı tekrarlamamayı hedeflediği.

## 1. Owner beyanı

Owner'ın kendi değerlendirmesi: legacy Django ve FastAPI tabanlı QR-menü
girişimleri **vibecoding olarak başarısız** oldu. Bu doküman bu beyanı
kanıtlarla (aşağıda) destekler ve kök neden analizini framework seçimine
**indirgemez**.

## 2. Gözlemlenen kanıtlar (sanitize edilmiş, dosya sistemi yolu vermeden)

| Girişim | Gözlem |
|---|---|
| Legacy Django QR-menü denemesi | Yerel yapısal/unit QA "73/73" ve "163 test" başarı beyanı; ama E2E yok, coverage %56–%70 aralığında, çok sayıda placeholder ve ürün gap'i mevcut |
| Legacy FastAPI QR-menü denemesi | Effort ağırlıklı frontend-only/mock-first; backend fiilen yok; ~1202 legacy dosya/kod parçası, ~3 modern parça; 4 eksik handler; E2E CI dışında |
| Ayrı bir denetim raporu | 461 bekleyen (pending) değişiklik; migration doğrulanmamış |
| Genel gözlem | Django ve FastAPI girişimleri **paralel** yürütülmüş — aynı ürünü iki ayrı teknoloji yığınıyla eşzamanlı yeniden yazma riski |

Bu kanıtlar bu külliyatın kendi doğrudan gözlemi değildir — owner'ın ve önceki
denetim turlarının aktarımıdır; birincil kaynak/varsayım ayrımı `docs/27` §1
disiplinine tabidir.

## 3. Kök neden — framework değil, disiplin

**Çıkarım**: Django veya FastAPI seçimi kök neden **değildir**. Aynı disiplin
eksiklikleri farklı bir framework'te de tekrarlanabilirdi. Baskın risk
sınıfları:

1. **Local GREEN ≠ kullanıcı yolculuğu kanıtı.** Structural/unit testlerin
   yeşil olması, gerçek bir kullanıcı akışının (kayıt→menü→yayın→QR→tarama)
   uçtan uca çalıştığının kanıtı değildir (`docs/27` §4 "vibe says done"
   reddi burada doğrudan uygulanır).
2. **Scope/artifact explosion.** Kapsam sürekli genişlemiş, üretilen dosya
   sayısı (1202 legacy parça) gerçek işlevsel yüzeyle orantısız büyümüş.
3. **Mocks vs. real integration.** Frontend-only/mock-first yaklaşım, gerçek
   backend/veritabanı/kimlik doğrulama entegrasyonunun hiç doğrulanmamasına
   yol açmış.
4. **Canonical drift.** "Tek kanonik kaynak" disiplini yokken (`AGENTS.md`
   §2'nin bu külliyatta neden bu kadar sert uygulandığının gerekçesi budur)
   aynı bilginin birden fazla yerde çelişen şekilde tanımlanması.
5. **Paralel rewrite riski.** Django ve FastAPI'nin eşzamanlı sürdürülmesi,
   hiçbirinin gerçekten bitirilememesine yol açmış — kaynak iki yöne
   bölünmüş.
6. **Dirty/unintegrated work.** 461 bekleyen değişiklik ve doğrulanmamış
   migration, "tamamlandı" beyanı ile fiili durum arasındaki makasın somut
   göstergesi.
7. **Acceptance misalignment.** Kabul kriterleri implementasyondan **sonra**
   yazılmış veya hiç yazılmamış görünüyor — bu külliyatın `docs/27` §1
   "acceptance before implementation" kuralının doğrudan tepkisidir.

## 4. Başarı modeli — tek kritik dikey dilim

Bu külliyatın (ve ileride implementasyonun) bağlayıcı başarı modeli:

```
register/verify → tenant/restoran → menu/category/product/media →
preview/publish snapshot → QR create/physical scan → public menu →
first-party analytics
```

Billing/Iyzico sandbox entegrasyonu **MVP exit gate içindedir** (opsiyonel
sonradan-eklenir bir parça değil — `docs/01` §5, `docs/18` MVP Exit Gate).

### Bağlayıcı disiplin maddeleri

- **Acceptance before code**: Kabul kriteri implementasyondan önce yazılır
  (bu külliyatta zaten böyle — `docs/27` §1).
- **Real persistence/auth/tenancy/permissions/UI birlikte**: Hiçbir katman
  izole "mock" olarak "bitti" sayılmaz; kritik dilim gerçek entegrasyonla
  kanıtlanır.
- **Mocks yalnız test sınırında**: Mock kullanımı unit test izolasyonuyla
  sınırlıdır, ürün akışının kendisi mock üzerinden "çalışıyor" sayılmaz.
- **Kritik dilim GREEN olmadan opsiyonel modül genişlemesi yok**: OPT-01..29
  (`docs/04` §4) kritik dilim kanıtla GO almadan başlamaz.
- **One writer**: Bir değişiklik paketinin tek yazarı vardır (kök yönetişim
  talimatı madde 5, 8).
- **Targeted RED**: Implementasyon başlamadan önce başarısız test yazılır.
- **Normal bütçe — iki tam QA**: Bir tam local QA + bir CI/full QA (kök
  yönetişim talimatı madde 9); tekrar gerektiren durum gerekçeli kaydedilir.
- **Independent review**: Yazan kişi kendi paketini review edemez.
- **Stop/go/rollback tanımlı**: Her work package'in bir rollback yolu, önceden
  bilinir (`docs/26`, `templates/MILESTONE-GATE.md`).

## 5. Laravel bir sihirli çözüm değildir

Laravel/React/MVC seçimi (`docs/03` ADR-L01..L09) bu postmortem'in **sonucu**
değil, **bağımsız** bir mimari karardır (kanıt: `docs/28-SOURCE-REGISTER.md`).
Bu doküman Laravel'in "önceki iki denemeden daha iyi sonuç vereceğini" iddia
**etmez** — iddia edilen tek şey, yukarıdaki disiplin maddelerinin (özellikle
madde 4 "acceptance before code" ve madde "tek kritik dikey dilim") framework'
ten bağımsız olarak uygulanacağıdır. Platform seçimi neyi **inşa
edeceğimizi**, disiplin ise **nasıl** inşa edeceğimizi belirler.

## 6. Bu külliyatın kendi durumuna uygulanması

Bu plan korpusunun kendisi (`docs/17` §5, `README.md` §İlerleme) hâlâ
**0/8**'dedir — postmortem'in yazılmış olması bile bir "ilerleme" değildir;
yalnız bir sonraki denemenin aynı hataları tekrarlamaması için bir kısıt
setidir.

## 7. Kanonik sahiplik

Vibecoding postmortem analizi ve başarı modelinin tek kanonik kaynağı
burasıdır. `docs/27` (QA/acceptance disiplini) buraya link verir, postmortem
kanıtlarını tekrar etmez.
