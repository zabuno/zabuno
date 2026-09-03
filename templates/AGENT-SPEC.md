# AGENT-SPEC template

`agents/<key>.md` dosyaları için kullanılır (`docs/96` "Agents" bölümünün
resmileşmiş hâli, `docs/98` FF-75). Bir agent spesifikasyonu **plan ve
sözleşmedir**; çalışan bir otonom süreç değildir. Kodda "ajan" adıyla bir
sınıf olması gerekmez — çoğu zaman bir kuyruk işi ya da deterministik bir
toplayıcıdır (`docs/adr/ADR-L11`). Spesifikasyon şunu garanti eder: ajan ne
zaman çalışır, neye dokunabilir, neye ASLA dokunamaz, onayı kim verir.

```markdown
# <Agent Name> — `<key>`

**SÖZLEŞME — bu bir agent spesifikasyonudur; kodda karşılığı: <sınıf/iş adı ya da "yok">.**

## Amaç
(Tek cümle: hangi işi, kimin için.)

## Tetikleyici
(Hangi olay/istek başlatır. Zamanlayıcı/daemon ise açıkça yaz — çoğu için YASAK.)

## Hafıza
- Kalıcı: (hangi tablo/dosya; kim yazar)
- Geçici: (kuyruk işi, bellek; ne zaman kaybolur)

## Hesap ve bütçe
(Hangi sağlayıcı/bağlantı sınıfı; `purpose`; dakikalık/parti bütçesi; kim ayarlar.)

## İzin verilen
(allowlist)

## Yasak
(Kesin yasaklar — talimat enjeksiyonuyla genişletilemez.)

## İnsan onayı
(Zorunlu mu, hangi ekranda, ne gösterilir.)

## Kanıt
(Test dosyaları; ölçülen ne.)
```
