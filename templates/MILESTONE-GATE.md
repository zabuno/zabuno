# MILESTONE-GATE template

Bir stage'in (`docs/18`–`docs/25`) Exit Gate değerlendirmesi gerçek
implementasyon sonrası bu şablonla doldurulur. Şablonun kendisi **boş kanıt**
içermez — implementasyon olmadan bu form doldurulmaz.

```markdown
# Milestone Gate: <Stage adı> — <tarih>

## Girdi
- Entry gate karşılandı mı: Evet/Hayır + kanıt linki
- İlgili docs/16 açık maddeleri: kapatıldı / containment ile ilerleniyor / açık

## Kanıt özeti
| Acceptance kriteri | Kanıt (test/ölçüm/review linki) | Sonuç |
|---|---|---|
| ... | ... | Geçti/Kaldı |

## Metrikler
| Metrik | Hedef | Gerçekleşen | Yorum |
|---|---|---|---|

## Güvenlik/a11y/performans/i18n doğrulaması
(docs/27 §5 zorunlu test kategorilerinden hangileri çalıştı, sonuç)

## Rollback tetikleyicisi tetiklendi mi
Evet/Hayır — tetiklendiyse ne yapıldı

## Karar
GO | NO-GO | CONDITIONAL-GO (koşullar: ...)

## Bir sonraki stage admission
(docs/17 §3 next-stage admission alanına referansla, karşılandı mı)

## İmza
(Owner + ilgili roller — dört gözlü onay gerekiyorsa, örn. pricing publish gibi)
```
