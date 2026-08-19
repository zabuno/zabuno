# shared-host-capability

**PLANNING ONLY — bu bir skill spesifikasyonudur, kurulu bir skill paketi değildir.**

## Trigger
Yeni bir host'a deploy öncesi veya kapasite matrisi (`docs/15` §4)
doğrulanması gerektiğinde.

## Inputs
Hedef host bağlantı bilgisi (yalnız probe amaçlı, kalıcı erişim değil).

## Authority
Salt-okunur probe — host'a kalıcı değişiklik yapmaz, yalnız yetenek testi
çalıştırır ve siler.

## Permitted tools/actions
Imagick/ffmpeg varlık kontrolü, `exec`/`proc_open` erişilebilirlik testi,
symlink oluşturma denemesi (test dosyasıyla, sonra silinir), PHP memory/
upload/timeout limiti okuma.

## Forbidden actions
Probe sonucunu host sağlayıcısına veya üçüncü tarafa ifşa etme; test
dosyalarını temizlemeden bırakma.

## Deterministic outputs / schema
```
{ imagick: boolean, ffmpeg: boolean, exec_enabled: boolean, symlink_supported: boolean,
  php_memory_limit, upload_max_filesize, execution_timeout, redis_available: boolean }
```

## Evidence
Her yetenek testinin çıktı log'u.

## Human approval
Gerekmez (otomatik probe), sonuç Engineering'e raporlanır.

## Failure / rollback
Bir yetenek yoksa graceful-degradation planı (`docs/07` §8, `docs/15` §4)
otomatik devreye girer — hard-fail yasak.

## Eval cases
- Imagick yoksa medya pipeline'ının Intervention'ın GD fallback'ine
  düştüğünün testi.
- `exec` kapalıysa mPDF'in halen çalıştığının testi (mPDF exec gerektirmez).

## Phase
İlk deploy öncesi ve her host değişikliğinde (`docs/16` MED-01'i kapatan test).
