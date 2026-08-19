# ADR template

`docs/03-ARCHITECTURE-DECISIONS.md`'deki özet kararlardan biri tam ADR'ye
genişletilmek istendiğinde bu şablon kullanılır (ayrı dosya olarak
`docs/03-adr/ADR-LNN-<slug>.md` — yalnız gerektiğinde, `AGENTS.md` §2 tek
kanonik sahiplik kuralına göre önce mevcut özetin yeterli olup olmadığı
değerlendirilir).

```markdown
# ADR-LNN: <Başlık>

**Durum**: Taslak | Kabul edildi | Reddedildi | Değiştirildi (→ ADR-LMM)
**Sınıf**: kanıtlanmış | koşullu | deneysel
**Tarih**: <YYYY-MM-DD>

## Bağlam
(Bu kararı gerektiren durum nedir — hangi problem çözülüyor.)

## Değerlendirilen alternatifler
1. <Alternatif A> — artı/eksi
2. <Alternatif B> — artı/eksi
3. <Seçilen> — artı/eksi

## Karar
(Net, tek cümlelik karar ifadesi.)

## Gerekçe
(Neden bu alternatif — hangi ilke/kısıt bu seçimi zorunlu kıldı.)

## Sonuçlar
(Bu kararın ne yapmayı kolaylaştırdığı, ne yapmayı zorlaştırdığı.)

## Kanıt
(docs/28-SOURCE-REGISTER.md içindeki ilgili satıra link.)

## İlişkili
(Hangi modül/stage dokümanları bu karara bağlı — docs/29 traceability'ye link.)

## Geri alma
(Bu karar yanlış çıkarsa geri alma maliyeti ve yolu ne.)
```
