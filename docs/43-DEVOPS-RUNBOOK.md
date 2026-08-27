# 43 — DevOps yönergesi (netcup VPS + Docker)

Hedef kitle: sunucuyu kuracak ve işletecek mühendis. Uygulamayı bilmenize
gerek yok; bu belge kendi kendine yeter.

## Ne kuruyorsunuz

Tek bir Docker Compose yığını, üç servis:

| Servis | Ne yapar | Dışarı açık mı |
| --- | --- | --- |
| `proxy` | Caddy. TLS'i sonlandırır, sertifikayı kendisi alır ve yeniler | **Evet** — 80, 443 |
| `app` | Laravel + derlenmiş React, nginx + php-fpm | Hayır |
| `db` | PostgreSQL 17 | Hayır — yalnız iç ağ |

Uygulama imajı ön yüzü (React/Vite) derleme aşamasında üretir; sunucuda
Node yoktur ve gerekmez.

## Tek komut

Sıfırdan bir Ubuntu/Debian sunucuda, root olarak:

```bash
git clone https://github.com/zabuno/zabuno.git /opt/zabuno
cd /opt/zabuno
ZABUNO_DOMAINS="zabuno.com, www.zabuno.com, e-menum.net, www.e-menum.net" sudo -E ./install.sh
```

`install.sh` sırayla: Docker'ı resmî apt deposundan kurar (`curl | sh`
kullanmaz), `.env` yoksa üretir, uygulama anahtarı ve veritabanı parolası
üretir, imajı derler, yığını başlatır ve **uygulamanın gerçekten cevap
verdiğini doğrular**.

Yeniden çalıştırılabilir. Var olan `.env` ASLA üzerine yazılmaz.

## DNS

Alan adlarının A kaydı sunucunun IP'sine yönlendirilir. **Bunu proje sahibi
yapar.**

Caddy sertifikayı ancak DNS yönlendikten sonra alabilir. Yönlenmeden önce
`https://` hata verir; bu normaldir ve kurulumun başarısız olduğu anlamına
gelmez. Yönlendikten sonra ilk istekte sertifika otomatik gelir.

## İki alan adı — ve neden yazılım alan adına bağlı değil

Bu bir SaaS. Aynı yazılım **birden çok alan adında ve birden çok sunucuda**
çalışır. Altyapı buna göre denetlendi (`MultiDomainTest`):

- Mutlak adresler isteğin **kendi host'undan** üretilir. `e-menum.net`'ten
  gelen ziyaretçi `e-menum.net` adresleri görür; `zabuno.com`'a
  yönlendirilmez.
- Kanonik host zorlaması **kapalıdır** (`URL_ENFORCE_HOST=false`). Açılırsa
  ikinci alan adı birincinin gölgesi olur.
- Kodun hiçbir yerinde alan adı gömülü değildir; bir test bunu zorlar.

Yeni bir alan adı eklemek iki satırlık iştir:

```bash
cd /opt/zabuno
# .env içinde ZABUNO_DOMAINS ve URL_TRUSTED_HOSTS listelerine ekleyin
docker compose --env-file .env --env-file .image.env up -d proxy
```

Sertifika yeni alan adı için kendiliğinden alınır.

## Günlük işler

```bash
cd /opt/zabuno

# Durum
docker compose --env-file .env --env-file .image.env ps

# Günlükler (app / proxy / db)
docker compose --env-file .env --env-file .image.env logs -f app

# Yeniden başlat
docker compose --env-file .env --env-file .image.env restart app
```

## Güncelleme

İki yol var; ikisi de aynı sonucu verir.

**GitHub Actions ile (tercih edilen).** `Actions → Deploy → Run workflow`,
kutuya `DEPLOY` yazılır. Akış imajı `linux/amd64` için derler, GHCR'a iter,
sunucuya bağlanır, yeni imajı yayına alır ve siteye HTTP isteği atarak
gerçekten cevap verdiğini doğrular. Bunun için depoya beş gizli değer
eklenmiş olmalı: `DEPLOY_HOST`, `DEPLOY_USER`, `DEPLOY_SSH_KEY`,
`DEPLOY_KNOWN_HOSTS`, `DEPLOY_HEALTH_URL`.

**Sunucudan elle.**

```bash
cd /opt/zabuno && sudo ./install.sh
```

## Geri alma

Her imaj commit SHA'sı ile etiketlenir.

```bash
cd /opt/zabuno
echo "ZABUNO_IMAGE=ghcr.io/zabuno/zabuno:<eski-sha>" > .image.env
docker compose --env-file .env --env-file .image.env up -d app
```

**Uyarı:** migrasyonlar geri alınmaz. Şema değiştiren bir sürümden dönmek
ayrı bir karardır ve önce yedek gerektirir.

## Yedek

```bash
cd /opt/zabuno
docker compose --env-file .env --env-file .image.env exec -T db \
    pg_dump -U zabuno zabuno | gzip > /backups/zabuno-$(date +%F).sql.gz
```

`db-backups` adlı bir Docker hacmi ayrılmıştır ama **içine yazan bir iş
henüz yoktur**. Zamanlanmış yedek kurmak bu yönergenin dışındadır ve açık
bir iştir.

## Yapılmaması gerekenler

| Yapma | Neden |
| --- | --- |
| `.env` dosyasını depoya ekleme | Depo **açık kaynak**; parola herkese açılır |
| `APP_KEY` değerini değiştirme | Şifrelenmiş her oturum ve saklı değer okunamaz hâle gelir |
| `db` servisine `ports:` ekleme | Veritabanını internete açar; parola tek savunma hattı kalır |
| Uygulamaya doğrudan port yayımlama | Uygulama vekilin ilettiği başlıklara güveniyor; doğrudan erişim o güveni sömürülebilir yapar |
| Otomatik deploy açma | Yayına çıkmak sahibinin kararı; her birleşme yayına dönerse yazım düzeltmesi ile davranış değişikliği aynı riski taşır |

## Sorun giderme

**`https://` sertifika hatası veriyor.** DNS henüz yönlenmemiştir ya da 80
portu kapalıdır. Caddy doğrulama için 80'e ihtiyaç duyar; güvenlik duvarında
hem 80 hem 443 açık olmalı.

**Site 502 veriyor.** `app` konteyneri ayakta değil ya da açılışta migrasyon
başarısız. `logs app` ile bakılır; açılış betiği her adımı yüksek sesle
bildirir.

**Sayfa açılıyor ama adresler `http://`.** Vekil `X-Forwarded-Proto`
göndermiyordur. Caddyfile'daki `header_up` satırları yerinde mi bakılır.

**Uygulama açılmıyor, "Class ... not found".** İmaj eski bir katmandan
derlenmiş olabilir. `docker build --no-cache` ile yeniden derleyin.

## Güvenlik notu

Sohbet üzerinden paylaşılan kullanıcı adı/parola bilgileri **döndürülmeli**
(değiştirilmeli). Ekran görüntüsü, iletilen mesaj ve yedeklenen sohbet
geçmişi, parolanın kaç yerde durduğunu bilinemez hâle getirir. Sunucu
erişimi için parola yerine SSH anahtarı kullanılması, deploy akışının da
beklediği yöntemdir.
