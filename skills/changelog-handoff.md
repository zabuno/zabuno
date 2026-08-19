# changelog-handoff

**PLANNING ONLY — bu bir skill spesifikasyonudur, kurulu bir skill paketi değildir.**

## Trigger
Bir çalışma oturumu/paket tamamlandığında, bir sonraki writer'a (insan veya
AI) devir gerektiğinde.

## Inputs
Oturumda değişen dosya listesi + kararlar.

## Authority
Yazma yetkisi yalnız bir handoff/changelog notuna (bu külliyatın kendi
dosyalarına değil — o dosyalar `module-spec` gibi ilgili skill'lerin
yetkisinde).

## Permitted tools/actions
Diff özetleme, `docs/16` açık maddeleriyle çapraz referans, sonraki adım
önerisi.

## Forbidden actions
"Tamamlandı" diye özetleyip kanıtsız iddia üretme (`docs/27` §4 vibe-says-done
reddi burada da geçerli).

## Deterministic outputs / schema
```
{ session_id, files_changed: [string], decisions_made: [string], open_items_for_docs16: [string], next_recommended_step }
```

## Evidence
Değişen dosyaların diff özeti.

## Human approval
Gerekmez (bilgilendirme amaçlı), ama önemli bir mimari karar içeriyorsa
Architecture'a bildirilir.

## Failure / rollback
N/A (bu skill üretici değil, özetleyicidir).

## Eval cases
- Bir oturumda 5 modül spec'i yazıldıysa, hepsinin handoff notunda
  listelendiği.
- Açık bırakılan bir karar `docs/16`'ya gerçekten eklenmiş mi kontrolü.

## Phase
Her çalışma oturumu sonunda.
