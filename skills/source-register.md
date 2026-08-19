# source-register

**PLANNING ONLY — bu bir skill spesifikasyonudur, kurulu bir skill paketi değildir.**

## Trigger
`docs/28-SOURCE-REGISTER.md`'deki bir satırın canlı doğrulanması gerektiğinde
(`docs/16` SRC-01) veya yeni bir kaynak eklendiğinde.

## Inputs
Kaynak URL'si.

## Authority
Salt-okunur fetch — kaynağı okur, `docs/28`'e yazma yetkisi vardır (yalnız bu
dosyaya).

## Permitted tools/actions
URL fetch, erişim tarihi damgalama, içerik özetleme (kararın hâlâ geçerli
olup olmadığını kontrol etmek için).

## Forbidden actions
Erişilemeyen bir kaynağı "muhtemelen aynıdır" diye kanıtlanmış sayma; kaynağın
içeriğini bu külliyata kopyalama (yalnız referans/özet, telif/lisans sınırına
uyum).

## Deterministic outputs / schema
```
{ source, url, previous_class, new_class, access_date, content_changed_since_last_check: boolean }
```

## Evidence
Fetch edilen sayfanın erişim tarihi + ilgili bölüm özeti.

## Human approval
Bir kaynağın sınıfı "koşullu"dan "kanıtlanmış"a yükseltilmesi otomatik olabilir
(gerçek fetch kanıtına dayanıyorsa); "deneysel"den yükseltme Architecture
onayı gerektirir.

## Failure / rollback
Kaynak artık erişilemiyorsa (404/taşınmış) → `docs/16`'ya yeni bir gap kaydı
açılır, karar askıya alınmaz ama "doğrulanamadı" olarak işaretlenir.

## Eval cases
- Bir Laravel doc sayfasının URL'sinin hâlâ geçerli olduğu.
- İçeriğin önemli ölçüde değiştiği (örn. paket deprecated olmuş) durumunun
  tespiti.

## Phase
Bu paketin teslimi sonrası ilk review turu (`docs/16` SRC-01'i kapatmak için)
ve sonrasında periyodik.
