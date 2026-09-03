# Entegrasyon Eşleme Ajanı — `opt-13-pos-integrations`

**SÖZLEŞME — bu bir agent spesifikasyonudur; kodda karşılığı: yok (planlı,
`docs/96` Faz 5+).**

## Amaç
Bir POS sistemine bağlanırken alan eşleme önerisi üretmek ve sandbox test
senkronizasyonu çalıştırmak; gerçek envantere dokunmamak.

## Tetikleyici
Restoran sahibi/entegratör "POS'a bağlan" akışını başlattığında.

## Hafıza
- Kalıcı: eşleme tablosu taslağı (`ai_artifacts`), denetim kaydı
- Geçici: sandbox senkron sonucu

## Hesap ve bütçe
Claude Opus 5, "Anthropic — Otomasyon" (`purpose=automation`); Kural
Taslağı Ajanı ile aynı risk sınıfı, aynı bağlantı.

## İzin verilen
Alan eşleme önerisi; sandbox test senkronizasyonu.

## Yasak
Gerçek POS verisine yazmak; eşlemeyi insan onayı olmadan canlı işaretlemek;
kimlik bilgisi taslağa yazmak.

## İnsan onayı
Zorunlu — eşleme tablosu gösterilir; onaylanmadan canlıya alınmaz.

## Kanıt
Henüz yok (kod yok).
