# 22 — Stage 5: Growth

**PLANNING ONLY. Şu an çalıştırılamaz.**

## Owner özeti

- **once**: Tek restoran/tek şube kullanım deseni kanıtlı; zincir/çok-şubeli
  müşteri talebi henüz sistematik karşılanmıyor.
- **simdi**: (PMF kanıtı olmadan başlamaz.)
- **fark**: Multi-location/chain desteği (`docs/02` §3 hiyerarşisinin M1
  genişlemesi), otomasyon/referral/add-on/partner ekosistemi, kapasite/yük
  planlaması, opsiyonel Redis/S3/Metabase katmanları.
- **kullaniciYolculugu**: Bir restoran zinciri sahibi artık tek panelden birden
  fazla şubeyi yönetir; bir şubedeki fiyat değişikliği diğerini otomatik
  etkilemez (izole ama tek hesap altında) — "tek form, çoklu şube onayı" akışı.
- **kalanEngel**: PMF kanıtı yok.
- **capability_delta**: tek-şube → çok-şube/zincir + ölçek altyapısı.
- **Şu-an-çalıştırılabilir/çalıştırılamaz iddiası**: **Çalıştırılamaz.**

## Amaç
Ölçülmüş ihtiyaca göre kapasite ve çok-şube desteğini genişletmek.

## Scope / non-goals
**Scope**: OPT-10 Multi-branch Management, OPT-19/20 marketing automation/
campaign, OPT-27 Marketplace (erken), Redis/S3/Metabase **opsiyonel** aktivasyonu.
**Non-goals**: shared-host'tan container/VPS migrasyonu — **yalnız ölçülmüş
ihtiyaçla** yapılır, varsayılan olarak yapılmaz (`docs/03` ADR-L08 korunur).

## Entry gate
PMF Exit Gate GO + owner-onaylı retention/use kanıtı (bkz. `docs/21`).

## Milestone / WP
`docs/26`.

## Module increments
OPT-10 Multi-branch, OPT-17 Loyalty, OPT-18 CRM (genişletilmiş), OPT-19/20,
OPT-27 Marketplace (erken aşama), OPT-28 Metabase Embed, Analytics/Consent/
Tagging (GA4/Yandex Metrica inbound reporting adaptörü, salt-okunur,
tenant-authorized — `docs/12` §5a, `docs/16` ANL-03).

## AI entegrasyonu ek planı (`AI-INTEGRATION-v1` Faz 5)

Bu stage'e eşlenen AI maddeleri `docs/95-AI-ENTEGRASYON-YOL-HARITASI.md`'de
sahiplenilir: ölçekte weighted/cost/latency routing politikası, trend/anomali
içgörü anlatımı (`opt-06-advanced-analytics`) ve görsel gömme.

Tetikleyici: ölçülen aylık AI çağrı hacminin tek-bağlantı-yeterli eşiğini
aşması.

## Dependency / critical path
PMF analytics altyapısı → capacity planlaması → multi-branch veri modeli
genişlemesi (zaten `docs/05`'te 1:N olarak hazırlanmıştı).

## Acceptance evidence
Yük testi sonuçları, multi-branch tenant-isolation testi (bir şubenin verisi
diğer şubeye sızmıyor).

## Metrics
Şube başına aktif kullanım, referral dönüşüm oranı, altyapı maliyet/kullanıcı.

## Security / a11y / performance / i18n
Ölçek altında tenant isolation yeniden doğrulanır; Redis/S3 aktifse ek güvenlik
kontrol listesi (`docs/15`) uygulanır.

## Rollback trigger
Multi-branch veri sızıntısı bulgusu → özellik hemen kısıtlanır.

## Exit GO/NO-GO/CONDITIONAL
Henüz değerlendirilmedi.

## Next-stage admission
Enterprise Level'a geçiş, Growth'un kanıtla tamamlanmasını gerektirir — bu,
Enterprise sınıfı **yönetişimin** (zaten gün 1'den beri uygulanan) Stage 6
**ürün kabiliyetinden** ayrı olduğunun hatırlatması (`docs/17` §2).
