# 27 — QA, Acceptance & Vibecoding Discipline

**PLANNING ONLY. Bu paket için runtime test yok — docs-only yapısal kabul.**
Bu doküman kanıt/kabul **disiplinini** tanımlar; kendisi bir test raporu değildir.

## 1. Waterfall QA disiplini

- **Baseline/change control**: Her değişiklik bir ADR veya requirement ID'sine
  bağlanır.
- **Requirements ID**: Her gereksinim `docs/29-TRACEABILITY-MATRIX.md`'de bir
  kimliğe sahiptir.
- **Acceptance before implementation**: Kabul kriteri, implementasyondan
  **önce** yazılır (bu külliyatta zaten böyle — modül spec'leri implementasyon
  yokken yazıldı).
- **Source/assumption/evidence ayrımı**: Her iddia şu üçünden biri olarak
  etiketlenir: **source** (birincil kaynağa dayalı, `docs/28`), **assumption**
  (varsayım, `docs/16`'da kayıtlı), **evidence** (gerçek test/ölçüm sonucu —
  bu külliyatta henüz yok, çünkü implementasyon yok).

## 2. İzlenebilirlik zinciri

```
Requirements → Module → Journey → Stage → Work Package → Acceptance → Test → Rollback
```

Çift yönlü: bir test'ten geriye doğru hangi requirement'ı doğruladığı, bir
requirement'tan ileriye doğru hangi test'in onu doğrulayacağı izlenebilir
(`docs/29`).

## 3. AI-generated change disiplini (implementasyon başladığında geçerli olacak)

- Scoped prompt (kapsamı net tanımlı).
- Allowlist (yalnız izin verilen dosya/eylem).
- No secret (prompt'a secret sızdırılmaz).
- Single writer (bir değişiklik paketinin tek yazarı).
- Deterministic diff (aynı girdi aynı çıktıyı üretir).
- Targeted RED before implementation (önce başarısız test yazılır).
- **Bütçe**: bir writer'ın bir değişiklik paketi için bir tam local QA
  çalıştırması + bir CI/full QA (normal bütçe, tekrar gerektiren durum
  gerekçeli kaydedilir — bkz. kök yönetişim talimatı madde 9).
- Independent review (yazan kişi kendi paketini review edemez).
- Visual/a11y/security/tenant/payment/restore kapıları implementasyon
  başladığında zorunlu.

## 4. "Vibe says done" reddi

Bir worker'ın "tamamladım" beyanı **kanıt değildir**. Kanıt: çalışan test
çıktısı, ölçülmüş metrik, bağımsız review sonucu. Bu külliyatın kendisi de bu
kurala tabidir — `laravelv01/README.md`'nin "0/8" sayacı bu yüzden plan
üretiminden etkilenmez.

## 5. Zorunlu test kategorileri (implementasyon başladığında)

Threat model, migration/fixture testleri, contract testleri, **property-based
money testleri** (`docs/09` deterministik para politikasının otomatik
doğrulaması), QR fiziksel scan matrisi (`docs/08` §4), medya golden-file
testleri (`docs/07`), ECA recursion/cycle guard testi (`docs/10` §3), auth
policy matrix testi (`docs/05` §2), tenant escape testi (`docs/05` §6), webhook
replay/idempotency testi (`docs/09` §5), i18n pseudolocale/RTL testi
(`docs/13`), erişilebilirlik testi (`docs/15` §6), yük/degradation testi
(`docs/15` §4), backup restore testi (`docs/15` §4, `docs/16` DR-02).

## 6. Bu paketin kendi durumu

Bu paket (laravelv01 dokümantasyon külliyatı) **yalnız docs-only yapısal
kabul**e tabidir: dosyaların var olup olmadığı, thin/placeholder olup olmadığı,
link'lerin çalışıp çalışmadığı, sekiz aşamalı sıranın doğru olup olmadığı
(bkz. `README.md` self-check, madde M). Runtime test **N/A**'dır çünkü runtime
kod yoktur — bu bir eksiklik değil, paketin doğası gereği beklenen durumdur.

## 7. Kanonik sahiplik

QA/acceptance/vibecoding disiplininin tek kanonik sahibi bu dosyadır. İzlenebilirlik
matrisinin kendisi `docs/29`'da yaşar; bu dosya yalnız disiplini tanımlar.
