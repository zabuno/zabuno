# Entegrasyon Webhook Ajanı — `integration-hub`

**SÖZLEŞME — bu bir agent spesifikasyonudur; kodda karşılığı: yok (planlı,
`docs/96` Faz 6).**

## Amaç
Enterprise kiracı kendi webhook/API entegrasyonunu tanımlarken payload/şema
taslağı üretmek ve test uç noktasına sandbox çağrısı yapmak.

## Tetikleyici
Enterprise kiracı isteği (entegrasyon ekranı).

## Hafıza
- Kalıcı: şema taslağı (`ai_artifacts`), denetim kaydı
- Geçici: sandbox çağrı sonucu

## Hesap ve bütçe
Claude Opus 5, "Anthropic — Otomasyon" (`purpose=automation`); Stage 6'da
kurumsal SLA gerektirebilir.

## İzin verilen
Webhook payload/şema taslağı; sandbox test çağrısı.

## Yasak
Üretim webhook'una gerçek veri göndermek; secret taslağa yazmak.

## İnsan onayı
Zorunlu.

## Kanıt
Henüz yok (kod yok).
