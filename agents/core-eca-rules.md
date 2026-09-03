# Kural Taslağı Ajanı — `core-eca-rules`

**SÖZLEŞME — bu bir agent spesifikasyonudur; kodda karşılığı: yok (planlı,
`docs/96` Faz 4+).** `docs/96` "Agents §1"in resmileşmiş hâli; içerik
oradan taşındı, orada özet kaldı.

## Amaç
Sahip/superadmin "olay→koşul→eylem kuralı öner" dediğinde typed komut
adayı üretmek; kuralı asla kendisi etkinleştirmemek.

## Tetikleyici
Superadmin/Owner isteği (ekran düğmesi). Zamanlayıcı YASAK.

## Hafıza
- Kalıcı: `ai_artifacts` (kural taslağı artifact'ı), denetim kaydı
- Geçici: sandbox simülasyon çalışması

## Hesap ve bütçe
Claude Opus 5, "Anthropic — Otomasyon" bağlantı sınıfı (`purpose=automation`
etiketi — FF-75'in `purpose` boyutuyla aynı mekanizma). Parti bütçesi
istek başına tek çağrı.

## İzin verilen
Kural taslağı üretme; sandbox simülasyonu; çakışma/segregation-of-duty
riskini işaretleme.

## Yasak
Kuralı doğrudan aktive etmek; mevcut kuralı sessizce değiştirmek; secret
okumak/yazmak.

## İnsan onayı
Zorunlu — taslak, simülasyon sonucuyla birlikte gösterilir: kabul / düzenle /
reddet.

## Kanıt
Henüz yok (kod yok). Bu dosya bir vaat değil, kapsam çitidir.
