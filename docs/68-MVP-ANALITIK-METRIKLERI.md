# 68 — MVP analitik metrikleri: toplam sayı neyi gizler

**Kaynak:** AI-first raporu §3.1 (MVP metrik tablosu), §12.2 · `docs/61` H1

## 1. Ölçüm: üç zorunlu metrik yoktu

Plan MVP için dokuz metrik sayıyor. Yedisi vardı, üçü yoktu:

| Metrik | Plan | Önceki durum |
| --- | --- | --- |
| Toplam QR çözümleme | Zorunlu | ✅ |
| **Yaklaşık benzersiz tarama** | **Zorunlu** | **⬜** |
| Bugün / 7 gün / 30 gün | Zorunlu | ✅ |
| Onaylanmış menü açılışı | Zorunlu | ✅ |
| Çözümleme → açılış oranı | Önerilen | ⬜ |
| **Lokasyona göre kırılım** | **Birden fazla lokasyonda zorunlu** | **⬜** |
| **Karekoda göre kırılım** | **Birden fazla QR'da zorunlu** | **⬜** |

Eksiklerin ortak sonucu şuydu: **toplam sayı, iki şubesi olan bir işletmede
birinin hiç taranmadığını gizler.** Ekranda "15 tarama" yazıyordu ve on ikisi
tek şubeden geliyorsa bu bilgi hiçbir yerde görünmüyordu.

## 2. Yaklaşık benzersiz ziyaretçi

Aynı masadaki bir müşterinin menüyü altı kez açması altı müşteri demek
değildir. Ham sayaç bu iki durumu ayırt edemez.

Olay satırına türetilmiş bir `visitor_key` eklendi. Taşımadığı şeyler
kasıtlıdır:

- **Ham IP ve tarayıcı bilgisi saklanmaz.** Yalnız HMAC-SHA256 özeti yazılır.
- **Tuz her gün döner.** Dünün anahtarı bugünün anahtarıyla eşleşmez; bir
  ziyaretçi günler boyunca izlenemez. Bedeli, benzersiz sayımın gün sınırında
  sıfırlanmasıdır — ve bu bir kusur değil, ödenen bedeldir.
- **Kiracıya göre ayrılır.** Aynı kişi iki farklı restoranın menüsünü açtığında
  iki farklı anahtar üretilir; markalar arası eşleştirme yapılamaz.
- **Tuzun temeli uygulama anahtarıdır.** Özet tabloyu ele geçiren biri bile
  IP'yi geri hesaplayamaz.

Sonuç bir kimlik değil, bir SAYIM aracıdır. "Yaklaşık" kelimesi ekranda da
yazar: proxy arkasındaki iki müşteri tek görünebilir, tarayıcısını değiştiren
bir kişi iki görünebilir. Kesinmiş gibi sunulan bir tahmin yanlış kararlara
temel olur.

### 2.1 Eski olaylar sayılmaz

Sütun nullable ve öyle kalacak: bu ölçümden önce yazılmış her olayın anahtarı
yoktur ve olamaz. Onları "bir kişi" saymak bilinmeyeni bilinen gibi göstermek
olurdu. Geriye dönük bir değer de uydurulmadı.

### 2.2 PII testi neden hâlâ geçiyor

`ANALYTICS-LEDGER-NO-PII-01` şu sütunları yasaklıyor: `ip_address`, `ip`,
`user_agent`, `fingerprint`, `session_id`, `visitor_id`. `visitor_key` listede
yok ve olmamalı — yasak HAM tanımlayıcılara karşıdır. Ham IP saklamakla özet
saklamak arasındaki fark, ilkinin bir kişiyi işaret etmesi, ikincisinin yalnız
"aynı gün aynı cihaz" diyebilmesidir.

## 3. Açılma oranı: yokluk sıfır değildir

Karekodu tarayanların kaçı menüyü gerçekten açtı. İki olay aynı şey değildir:
istek sunucuya ulaşmış olabilir ama sayfa müşterinin cihazında açılmamış
olabilir.

**Tarama yoksa oran YOKTUR.** Sıfır döndürmek "kimse açmadı" der; oysa doğrusu
"kimse taramadı"dır ve ikisi farklı sorunlardır — biri bağlantı ya da yükleme
sorunu, diğeri kodun hiç görülmemesi.

## 4. Kırılımlar

Lokasyon ve karekod kırılımı, en çok taranan önce sıralı.

**Tek satırlık kırılım çizilmez.** Tek şubesi olan bir işletmede "Kadıköy: 12"
satırı, hemen üstündeki toplamın kelimesi kelimesine tekrarıdır. Kırılımın
değeri KARŞILAŞTIRMADIR; karşılaştıracak ikinci bir şey yoksa değeri de yoktur.

Karekodun insan adı yok: `qr_codes` yalnız jeton taşıyor ve kod adlandırma
henüz bir ürün özelliği değil. Etiket olarak jeton kullanılıyor, çünkü basılı
kodun adresinde de o geçiyor — kullanıcı eşleştirebilir. Uydurulmuş bir "QR #3"
etiketi hiçbir basılı kodla eşleşmezdi.

## 5. Markanın tamamı

Şube kapsamı artık isteğe bağlı: `/api/workspaces/{workspace}/analytics/summary`
markanın bütününü döner.

Öncesinde iki şubesi olan bir işletme toplamı görmek için şubeleri tek tek
gezip kafadan toplamak zorundaydı. Üst çubuktaki "All locations" bağlamının
analitikte karşılığı yoktu.

## 6. Değişen bir iddia

`ANALYTICS-SUMMARY-SCOPED-01` "yanıt yalnız bu dört alanı içermeli,
unique/visitor iddiası EKLENMEMELİ" diyordu ve haklıydı: ölçüm yoktu,
dolayısıyla böyle bir alan uydurma olurdu.

Kural değişmedi — alan ancak gerçekten ölçüldüğü için var. Yasak uydurmaya
karşıydı, ölçüme değil.

## 7. Kalan

- Cihaz, işletim sistemi, tarayıcı, ülke, şehir, referrer, saatlik yoğunluk:
  plan bunları **post-MVP** sayıyor ve öyle bırakıldı.
- En popüler ürün ve kategori: ürün etkileşimi ölçülmüyor; olmayan bir olaydan
  metrik türetilemez.
- Ürün analitiği olay taksonomisi (`docs/61` H2) ayrı bir pakettir ve ölçüm
  sorularının önce tanımlanmasını ister.
