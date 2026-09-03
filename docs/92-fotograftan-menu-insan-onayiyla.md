# 92 — Fotoğraftan menü, insan onayıyla (P0-05 foto yolu)

Sahip sağlayıcıyı seçti: **ChatGPT (OpenAI)**. Anahtar onda kalıyor.

Bu paket **sağlayıcı gerektirmeyen** yarıyı kuruyor — ve o yarı büyük olan.

## Neden önce bu yarı

Sağlayıcı adaptörü anahtar olmadan **gerçek API'ye karşı doğrulanamaz**.
Yazılabilir, ama yalnız sahtesine karşı test edilmiş olur ve "çalışıyor"
denemez.

Onay hattı ise bugün tamamen kanıtlanabilir: artifact taslakta durur, insan
inceler, onaylanınca **taslağa** yazılır ve yayına hâlâ dokunulmaz. Anahtar
geldiği gün değişecek yer **tek bir bağlama satırı**.

## Makinenin okuduğu doğrudan menüye girmez

Bir fiyatı yanlış okuyan model, aksi hâlde misafirin gördüğü menüye yanlış
fiyat yazardı.

| Adım | Ne olur |
| --- | --- |
| Okuma | `ai_artifacts` satırı, `applied_at` **boş** |
| İnceleme | Model kimliği, prompt sürümü, **alan başına** güven ve belirsizlik |
| Onay | **Taslağa** yazılır, `applied_at` dolar |
| Yayın | Ayrı bir eylem — sahip "Yayınla"ya basana kadar misafir eskisini görür |

**İkinci onay hiçbir şey yapmaz.** Ekran tazelenir, düğmeye ikrar kez basılır,
istek tekrarlanır — menü iki katına çıkmamalı. `applied_at` bu sorunun cevabı
ve kilit veritabanında, ekranda değil.

## Fiyatı okunamayan satır yazılmaz

Uydurma bir fiyat yazmak, sahibin görmediği bir yanlışı menüye gömerdi; sıfır
yazmak ise yayını kıran bir satır bırakırdı.

O satırlar **CSV yolundaki dille** geri raporlanır — sahip zaten o dili
biliyor — ve elle tamamlanır. Kaybolan bir şey yok.

Okunabilen ama **belirsiz** işaretli değerler yazılır ve inceleme ekranında
işaretli kalır: inceleyen kişi nereye bakacağını bilmeli, altmış satırı tek
tek okumak zorunda kalmamalı.

## Kapalıyken dürüst

Yetenek kapalıysa cevap **503** ve **sebebi** ile birlikte gelir. 500 vermek
sahibi "ürün bozuldu" sanmaya iterdi; sessizce boş dönmek daha kötü olurdu.
Kapalı bir anahtar ile tükenmiş bir bütçe farklı şeylerdir ve çıkış yolları
farklıdır.

## Yol boyunca çıkan gerçek kusur — ve testler onu gizliyordu

`ConfiguredAvailability` şunu okuyordu:

```php
config("ai.capabilities.{$capability->value}.candidates", [])
```

Yetenek adlarının **dördü de nokta içeriyor** (`menu.extract`,
`ocr.document`, …) ve `config()` noktayı **iç içe anahtar** sanıyor. Çağrı
`capabilities → menu → extract → candidates` yolunu arıyordu; gerçek anahtar
ise `'menu.extract'` düz metni.

Sonuç: sağlayıcı tam yapılandırılmış, anahtar girilmiş olsa bile cevap **her
zaman "rota yok"** olurdu. Kusur görünmüyordu çünkü AI kapalı ve gerçek
sağlayıcı yok — ilk kez **anahtar girildiği gün**, "para ödedik ama
çalışmıyor" olarak ortaya çıkardı.

**Ve mevcut testler bunu gizliyordu.** Test yapılandırmayı kodla **aynı
yanlış şekilde** yazıyordu:

```php
'ai.capabilities.menu.extract.candidates' => ['local:fake:m']
```

Noktalı yazıcı da iç içe bir yapı kuruyordu; okuyucu ve yazıcı **birlikte**
yanlış olduğu için test geçiyordu. Test, kusurun **tutarlı** olduğunu
kanıtlıyordu — doğru olduğunu değil.

Okuyucu düz anahtarla indeksliyor artık, testler `config/ai.php` içindeki
gerçek şekli yazıyor, ve iki yeni test hem bulunmayı hem bulunmamayı
donduruyor.

## Bütçe sıfırsa yetenek kapalıdır

Bu, zaten kurulu olan ve **doğru** olan bir davranış; belgeye geçiyorum çünkü
anahtar girildiğinde şaşırtıcı olabilir: **aylık bütçe konmadan hiçbir çağrı
gitmez.** Tavansız harcamayı varsayılan yapmak, bir betiğin faturayı
uçurmasına açık kapı bırakırdı.

## Kanıt

`MenuImportApprovalTest` (6), `AiAvailabilityConfigTest` (3)

| Requirement | Ne donduruluyor |
| --- | --- |
| `AI-IMPORT-ARTIFACT-UNAPPLIED-01` | Okuma menüye yazmaz; `applied_at` boş doğar |
| `AI-IMPORT-REVIEW-SHOWS-SOURCE-01` | Model, prompt sürümü ve alan başına güven |
| `AI-IMPORT-APPLY-ONCE-01` | İkinci onay hiçbir şey yapmaz |
| `AI-IMPORT-NEVER-TOUCHES-PUBLICATION-01` | Yayın snapshot'ı değişmez |
| `AI-IMPORT-OFF-IS-HONEST-01` | Kapalıyken 503 + sebep |
| `AI-IMPORT-TENANT-01` | Başkasının artifact'ı okunamaz, uygulanamaz |

## Sırada ne var

**OpenAI adaptörü.** `VisionExtractionPort`'u gerçekten çağıran uygulama —
bugün bağlama tek satır ve sahte sağlayıcıya işaret ediyor.

Onu yazdığımda **gerçek API'ye karşı doğrulanmamış** olacağını baştan
söyleyeceğim: ilk gerçek çağrı, anahtarı olan kişinin tek sayfalık bir
denemesiyle yapılmalı.

## Ürün iddiası

Çalışır: bir menü fotoğrafı okunur, sonucu insan inceler, onaylayınca taslağa
girer ve misafir hâlâ eski menüyü görür.
Çalışmaz: okuma bugün **sahte** sağlayıcıdan geliyor — gerçek OpenAI adaptörü
ve sahibin anahtarı gerekiyor.
