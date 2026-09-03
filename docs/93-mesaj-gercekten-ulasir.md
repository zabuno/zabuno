# 93 — Mesaj gerçekten ulaşır (P0-06)

Sahip Mailgun'u seçti ve anahtarını gönderdi. **Anahtarı kullanmadım ve
kullanamam** — API anahtarlarını hiçbir alana ben giremem. Bu paket her şeyi
kurar; anahtarı sahibi sunucunun kendi `.env`'ine ve GitHub secret'ına
koyar.

Depoya hiçbir gizli değer girmez ve bir test bunu kontrol eder.

## Önce ne oluyordu

`docs/88` mesajı **saklıyordu** ve bu doğru bir başlangıçtı: saklanan bir
mesaj kaybolmaz. Ama sahibin onu görmesi için panele bakması gerekiyordu —
yani mesaj **ulaşmış olmuyordu, duruyordu.**

## Saklama önce gelir, gönderim sonra

Gönderim başarısız olsa bile mesaj durur ve **sebebi kayda geçer**: sağlayıcı
bir gün cevap vermediğinde kaybolan bir talep olmamalı. Ziyaretçi bunu
görmez; gönderim bizim iç meselemiz ve onun mesajı kaybolmadı.

Adres yapılandırılmamışsa **hiç gönderilmez ve "gönderildi" damgası
atılmaz.** Sağlayıcı yokken damga atmak, sahibin gelmeyen bir e-postayı
beklemesine yol açardı.

## Neden SMTP değil, API

Bir sunucuda giden 25/587 portları sıklıkla kapalıdır; HTTPS her yerde
açıktır. Mailgun API taşıyıcısı ayrıca hatayı okunabilir biçimde döndürür —
SMTP'de aynı hata bir zaman aşımı olarak görünürdü.

`MAILGUN_ENDPOINT` açıkça yapılandırılabilir: ABD ve AB uçları ayrıdır
(`api.mailgun.net` / `api.eu.mailgun.net`) ve yanlış uç, **"kimlik
doğrulanamadı" gibi görünen bir bölge hatası** verir. Bu, saatlerce yanlış
yerde aranan bir hatadır.

## Bildirim sahibe gider, gönderene değil

Gönderen ekranda zaten teyit aldı. Ayrıca:

**Cevap adresi gönderenin adresidir, `from` değil.** `from` alanına
ziyaretçinin adresini yazmak, kendi alan adımızın adına başkasının adresinden
posta göndermek olurdu — SPF ve DMARC bunu reddeder ve bildirim hiç
ulaşmazdı.

## Kum havuzu alanının sınırı — bunu bilmeden ilerlenmez

Sahibin verdiği alan bir **kum havuzu** alanı
(`sandbox….mailgun.org`). Mailgun kum havuzu alanları **yalnız panelde
yetkilendirilmiş alıcılara** teslim eder (en fazla beş adres).

Sonuçları:

| Yol | Kum havuzuyla çalışır mı |
| --- | --- |
| İletişim mesajının **sahibe** bildirimi | ✅ — sahibin adresi yetkili alıcı olarak eklenirse |
| **Kayıt doğrulama** e-postası | ❌ — rastgele bir restoran sahibine ulaşmaz |
| **Şifre sıfırlama** | ❌ — aynı sebep |
| **Ekip daveti** | ❌ — aynı sebep |

Yani kum havuzu anahtarı **kaydolmayı açmaz.** Onun için Mailgun'da
doğrulanmış bir alan adı gerekir: `zabuno.com` (ya da `mail.zabuno.com`)
eklenip DNS'e SPF, DKIM ve dönüş yolu kayıtları girilmeli.

Bu, P0-06'yı **kapatmaz** — yarısını açar. Belgede böyle duruyor; "e-posta
çalışıyor" demek bugün yanlış olurdu.

## Yol boyunca iki kez aynı ders

Yazdığım "depoya sır girmesin" muhafızı **iki kez** yanlış şey ölçtü:

1. **Yorumu sır sandı.** Kum havuzu kısıtını *açıklayan* cümledeki
   `sandbox….mailgun.org` ifadesini ihlal saydı — yani kısıtı belgelemeyi
   cezalandırdı. Bu depoda aynı ders daha önce üç kapıda öğrenilmişti
   (`docs/82`, `docs/85`); yorumlar artık burada da önce düşüyor.

2. **`\s` satır sonunu yuttu.** `MAILGUN_SECRET\s*=\s*\S+` deseni, boş bir
   `MAILGUN_SECRET=` satırından sonra gelen **sonraki satırı** değer sandı.
   Aynı tuzağa bu oturumda odak CSS'inde de düşülmüştü: aranan şey **aynı
   satırda** olmalı.

Muhafız iki mutasyonla doğrulandı: sızan bir anahtar da, sızan bir alan adı
da kapıyı kırmızıya çeviriyor.

## Sahibin yapması gerekenler

1. **Bu anahtarı yenile.** Sohbet dökümünden geçti; artık bilinen sayılmalı.
2. Yeni değerleri **sunucunun `.env` dosyasına** yaz:
   `MAILGUN_DOMAIN`, `MAILGUN_SECRET`, `MAILGUN_ENDPOINT`,
   `CONTACT_NOTIFICATION_ADDRESS`, ve `MAIL_MAILER=mailgun`.
3. Kendi adresini Mailgun panelinde **yetkili alıcı** olarak ekle — yoksa
   kum havuzu bildirimi de gitmez.
4. Kaydolmayı açmak istediğinde **gerçek alan adını doğrula**.

## Kanıt

`ContactDeliveryTest` (4)

| Requirement | Ne donduruluyor |
| --- | --- |
| `CONTACT-DELIVERED-01` | Yapılandırıldığında bildirim gider ve damgalanır |
| `CONTACT-DELIVERY-FAILURE-KEPT-01` | Gönderim düşse de mesaj durur, sebebi kayda geçer |
| `CONTACT-DELIVERY-OFF-IS-NOT-AN-ERROR-01` | Sağlayıcı yokken damga atılmaz |
| `CONTACT-DELIVERY-NO-SECRET-01` | Depoda gizli değer yok |

## Ürün iddiası

Çalışır: sahip anahtarı koyduğu an gelen mesajlar e-postayla ona ulaşır ve
gönderim durumu kayda geçer.
Çalışmaz: **kayıt doğrulama, şifre sıfırlama ve ekip daveti** — kum havuzu
alanı rastgele alıcıya teslim etmez; doğrulanmış bir alan adı gerekiyor.
