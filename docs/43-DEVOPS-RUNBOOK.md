# Zabuno — sunucu kurulum yönergesi

**Bu belge kendi kendine yeter.** Uygulamayı bilmenize gerek yok, başka bir
belge okumanız gerekmez, kurulumdan sonra da dönmeniz gerekmez.

## Bu kurulum için

| | |
| --- | --- |
| Sunucu | netcup VPS — `152.53.163.185` |
| Alan adları | `zabuno.com`, `e-menum.net` (+ `www`) |
| A kaydı | **Proje sahibi ekler** — sizin işiniz değil |
| Depo yetkisi | Depo admin'i — GitHub secret'larını eklemek için gerekli |

Depo yetkiniz yoksa proje sahibinden isteyin: GitHub secret eklemek **depo
admin** yetkisi gerektirir, daha azı yetmez.

## Ne yapacaksınız — özet

Bir sunucu hazırlayıp tek komutla projeyi ayağa kaldıracaksınız, sonra
komutun ekrana yazdığı beş secret'ı GitHub'a ekleyeceksiniz. **İşiniz bu kadar.**

Beş secret eklendikten sonra süreç kendi kendine yürür: proje sahibi depoyu
güncellediğinde, testler geçtiyse sunucudaki uygulama kendini günceller.
Kimsenin sunucuya bağlanması gerekmez.

**Alan adlarının A kaydını proje sahibi ekler** — sizin işiniz değil.

Toplam süre: yaklaşık 15 dakika, çoğu imaj derlenmesini beklemekle geçer.

---

## Ne kuruluyor

Tek bir Docker Compose yığını, üç servis:

| Servis | Ne yapar | Dışarı açık |
| --- | --- | --- |
| `proxy` | Caddy. TLS'i sonlandırır, sertifikayı kendisi alır ve yeniler | **Evet** — 80, 443 |
| `app` | Laravel + derlenmiş React, nginx + php-fpm | Hayır |
| `db` | PostgreSQL 17 | Hayır — yalnız iç ağ |

Ön yüz (React/Vite) imaj derlenirken üretilir; **sunucuda Node gerekmez**.

## Sunucu gereksinimleri

- Ubuntu 22.04+ veya Debian 12+, x86_64 (netcup AMD EPYC uygundur)
- En az 2 GB RAM, 20 GB disk
- **80 ve 443 portları açık.** Caddy sertifika doğrulaması için 80'e ihtiyaç
  duyar; kapalıysa HTTPS hiç çalışmaz
- root erişimi

---

## Kurulum — tek komut

Sunucuya root olarak bağlanın:

```bash
git clone https://github.com/zabuno/zabuno.git /opt/zabuno
cd /opt/zabuno
ZABUNO_DOMAINS="zabuno.com, www.zabuno.com, e-menum.net, www.e-menum.net" sudo -E ./install.sh
```

Betik sırayla şunları yapar:

1. Docker'ı resmî apt deposundan kurar (`curl | sh` kullanmaz)
2. `.env` yoksa üretir — uygulama anahtarı ve veritabanı parolası dâhil
3. İmajı derler
4. Yığını başlatır
5. **Uygulamanın gerçekten cevap verdiğini doğrular**
6. Deploy anahtarını üretir ve GitHub'a eklenecek değerleri yazar

Betik yeniden çalıştırılabilir. Var olan `.env` **asla** üzerine yazılmaz.

## GitHub'a beş değer — CI/CD'yi tamamlayan adım

Kurulum bitince ekranda beş secret görürsünüz. Depoda:

**Settings → Secrets and variables → Actions → New repository secret**

| Ad | Nereden |
| --- | --- |
| `DEPLOY_HOST` | ekranda yazıyor (sunucunun IP'si) |
| `DEPLOY_USER` | `root` |
| `DEPLOY_HEALTH_URL` | ekranda yazıyor |
| `DEPLOY_KNOWN_HOSTS` | `cat /opt/zabuno/github-secrets.txt` |
| `DEPLOY_SSH_KEY` | aynı dosyada |

Ekledikten sonra **dosyayı silin** — özel anahtar içerir:

```bash
shred -u /opt/zabuno/github-secrets.txt
```

Bu adım bittiğinde işiniz tamamdır.

## Doğrulama

```bash
curl -I https://zabuno.com/up
curl -I https://e-menum.net/up
```

İkisi de `200` dönmeli.

**DNS henüz yönlenmediyse** bu komutlar sertifika hatası verir. Bu normaldir
ve kurulumun başarısız olduğu anlamına gelmez: Caddy sertifikayı ancak alan
adı sunucuya yönlendikten sonra alabilir. Yönlendikten sonra ilk istekte
sertifika kendiliğinden gelir. Sunucunun kendi içinden kontrol:

```bash
cd /opt/zabuno
docker compose --env-file .env --env-file .image.env exec -T app \
    curl -fsS http://127.0.0.1:8080/up && echo "  uygulama ayakta"
```

---

## Bundan sonrası — otomatik

Proje sahibi `main` dalına bir değişiklik birleştirdiğinde:

1. CI koşar (testler, lint, iki veritabanı motorunda süit)
2. **CI geçerse** deploy akışı kendiliğinden başlar
3. İmaj `linux/amd64` için derlenir ve GHCR'a itilir
4. Sunucuya bağlanılır, yeni imaj yayına alınır, migrasyonlar koşar
5. Siteye HTTP isteği atılarak gerçekten cevap verdiği doğrulanır

**CI geçmezse deploy başlamaz.** Akış `push` yerine `workflow_run` ile
bağlıdır: doğrudan push'a bağlansaydı deploy testlerle yarışır ve kırık bir
sürüm yayına çıkabilirdi.

**Beş secret eklenmeden önce** deploy akışı kırmızı vermez, sessizce atlar ve
"sunucu tanımlı değil" notu bırakır. Her birleşmede kırmızı bir X görmek,
kırmızıyı görmezden gelme alışkanlığı yaratır — o alışkanlık da gerçek
arızaları gizler.

Süreç GitHub → **Actions** sekmesinden izlenir. Elle tetikleme de vardır
(Deploy → Run workflow → kutuya `DEPLOY`); geri alma ve yeniden deneme için.

---

## Referans

### Yeni alan adı eklemek

Yazılım alan adına bağlı değil — bu bir SaaS ve aynı kurulum birden çok alan
adında çalışır. Adresler isteğin **kendi host'undan** üretilir; e-menum.net
ziyaretçisi zabuno.com'a yönlendirilmez.

```bash
cd /opt/zabuno
# .env içinde ZABUNO_DOMAINS ve URL_TRUSTED_HOSTS listelerine ekleyin
docker compose --env-file .env --env-file .image.env up -d proxy
```

Sertifika yeni alan adı için kendiliğinden alınır.

### Günlük işler

```bash
cd /opt/zabuno
docker compose --env-file .env --env-file .image.env ps          # durum
docker compose --env-file .env --env-file .image.env logs -f app # günlük
docker compose --env-file .env --env-file .image.env restart app # yeniden başlat
```

### Geri alma

Her imaj commit SHA'sı ile etiketlenir.

```bash
cd /opt/zabuno
echo "ZABUNO_IMAGE=ghcr.io/zabuno/zabuno:<eski-sha>" > .image.env
docker compose --env-file .env --env-file .image.env up -d app
```

**Uyarı:** migrasyonlar geri alınmaz. Şema değiştiren bir sürümden dönmek
ayrı bir karardır ve önce yedek gerektirir.

### Yedek

```bash
cd /opt/zabuno
docker compose --env-file .env --env-file .image.env exec -T db \
    pg_dump -U zabuno zabuno | gzip > /root/zabuno-$(date +%F).sql.gz
```

`db-backups` adlı bir Docker hacmi ayrılmıştır ama **içine yazan zamanlanmış
bir iş yoktur**. Kurulması ayrı bir iştir ve bu yönergenin dışındadır.

### Yapılmaması gerekenler

| Yapma | Neden |
| --- | --- |
| `.env` dosyasını depoya ekleme | Depo **açık kaynak**; parolalar herkese açılır |
| `APP_KEY` değerini değiştirme | Şifrelenmiş her oturum ve saklı değer okunamaz hâle gelir |
| `db` servisine `ports:` ekleme | Veritabanını internete açar; parola tek savunma hattı kalır |
| `app` servisine port yayımlama | Uygulama vekilin ilettiği başlıklara güveniyor; doğrudan erişim o güveni sömürülebilir yapar |
| Sunucuda elle kod düzenleme | Sonraki deploy üzerine yazar; değişiklik depoya gitmeli |

### Sorun giderme

**HTTPS sertifika hatası.** DNS henüz yönlenmemiş ya da 80 portu kapalı.
Caddy doğrulama için 80'e ihtiyaç duyar.

**502.** `app` konteyneri ayakta değil ya da açılışta migrasyon başarısız.
`logs app` ile bakın; açılış betiği her adımı bildirir.

**Sayfa açılıyor ama adresler `http://`.** Vekil `X-Forwarded-Proto`
göndermiyor. `docker/Caddyfile` içindeki `header_up` satırlarını kontrol edin.

**İkinci alan adı 400 dönüyor.** `.env` içindeki `URL_TRUSTED_HOSTS` o alan
adını içermiyor.

**Yükleme "çok büyük" diyor.** Zincir 50 MB'a ayarlıdır (Caddy → nginx →
PHP → uygulama). Daha büyüğü gerekiyorsa dördünü birden yükseltmek gerekir;
biri düşük kalırsa istek orada ölür.

**Deploy akışı SSH'ta takılıyor.** `DEPLOY_KNOWN_HOSTS` değeri sunucunun
gerçek anahtarıyla eşleşmiyor olabilir — sunucu yeniden kurulduysa yenilenir:
`ssh-keyscan -H <IP>`.

### Güvenlik notu

Sohbet üzerinden paylaşılmış kullanıcı adı/parola varsa **değiştirin**. Ekran
görüntüsü ve iletilen mesaj, parolanın kaç yerde durduğunu bilinemez hâle
getirir. Sunucu erişimi için parola yerine SSH anahtarı kullanılır; deploy
akışı da zaten anahtar bekler.
