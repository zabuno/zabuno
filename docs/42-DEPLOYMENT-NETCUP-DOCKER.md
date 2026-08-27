# 42 — Dağıtım: netcup VPS + Docker + PostgreSQL

Owner kararı (2026-08-27): Faz 2'den önce deploy edilecek. Birincil hedef
**netcup VPS, AMD EPYC, linux/amd64**, veritabanı **PostgreSQL**, yığın
**Docker Compose** ile.

## Ne kuruldu

| Parça | Dosya | Ne yapar |
| --- | --- | --- |
| Üretim imajı | `docker/Dockerfile` | Üç aşama: varlık derleme, bağımlılık çözümü, çalışma zamanı. Derleme araçları son imaja girmez |
| Web sunucusu | `docker/nginx.conf` | php-fpm önünde; varlıklara uzun önbellek, gizli dosyalara ret |
| Süreç yöneticisi | `docker/supervisord.conf` | nginx + php-fpm tek konteynerde, ikisi de yeniden başlar |
| Açılış | `docker/entrypoint.sh` | DB bekler → migrasyon → önbellek ısıtma → servis |
| Yığın | `docker-compose.yml` | `db` + `app` + `proxy`, üç ağ kuralı |
| HTTPS | `docker/Caddyfile` | Sertifikayı Caddy alır ve yeniler |
| Yayın akışı | `.github/workflows/deploy.yml` | **Elle tetiklenir**, GHCR'a iter, SSH ile yayına alır, sağlık kontrolü yapar |

## Neden bu kararlar

**Veritabanı internete açılmıyor.** `db` servisinin yayımlanmış portu yok ve
`internal: true` işaretli bir ağda. Bir portu açmak, parolayı tek savunma
hattı yapar.

**Deploy otomatiktir ama kapılıdır** (owner kararı, 2026-08-27). `main`'e
birleşme yayına döner — sahibi güncellemeyi GitHub'dan izler ve kimsenin
sunucuya bağlanması gerekmez.

Kapı şudur: akış `push` yerine `workflow_run` ile bağlıdır ve CI'ın
`conclusion` değerini kontrol eder. Doğrudan push'a bağlansaydı deploy
testlerle YARIŞIR ve kırık bir sürüm yayına çıkabilirdi. Yayına çıkan
commit, CI'ın geçtiği commit'tir — dalın o anki ucu değil.

**SSH host anahtarı sabitlenir.** Deploy kanalı üretim sunucusuna kök
erişimdir; ilk bağlantıda host anahtarını körlemesine kabul etmek o kanalı
ortadaki adama açar. `DEPLOY_KNOWN_HOSTS` bu yüzden zorunlu.

**`opcache.validate_timestamps=0`.** İmaj değişmezdir; dosya değişikliği
yalnız yeni imajla gelir. Aynı ayarın FTP tabanlı iş akışını sessizce
öldürdüğü `docs/13` §7'de kayıtlı — orada tehlike, burada doğru davranış.

**Sağlık kontrolü uygulamadan geçer.** Konteynerin ayakta olması uygulamanın
çalıştığı anlamına gelmez; yeşil bir deploy işi düşmüş bir siteyi
gizlememeli.

## Sunucuda bir kerelik hazırlık

VPS'te bir kullanıcı ve şu dizin gerekir: `~/zabuno/`. İçine `.env` konur:

```
APP_KEY=base64:...          # `php artisan key:generate --show` çıktısı
APP_URL=https://birinci-alanadi.com
ZABUNO_DOMAINS=birinci-alanadi.com, www.birinci-alanadi.com, ikinci-alanadi.net
URL_TRUSTED_HOSTS=birinci-alanadi.com,www.birinci-alanadi.com,ikinci-alanadi.net
URL_ENFORCE_HOST=false
DB_DATABASE=zabuno
DB_USERNAME=zabuno
DB_PASSWORD=...             # uzun ve rastgele
```

**Bu dosyayı `install.sh` sizin yerinize üretir** (`docs/43`). Elle yazmak
yalnız var olan bir kuruluma dokunurken gerekir.

`ZABUNO_DOMAINS` çoğuldur ve bilinçlidir: bu bir SaaS, aynı yazılım birden
çok alan adında çalışır. `URL_ENFORCE_HOST` kapalı kalmalı — açılırsa ikinci
alan adı birincinin adreslerine yönlendirilir ve kendi kimliğini kaybeder.

`.env` sunucuda kalır ve depoya **girmez**. Compose dosyaları her deploy'da
akış tarafından gönderilir.

## GitHub'a eklenecek secret'lar

`Settings → Secrets and variables → Actions`:

| Ad | Değer |
| --- | --- |
| `DEPLOY_HOST` | VPS'in IP veya alan adı |
| `DEPLOY_USER` | Deploy kullanıcısı |
| `DEPLOY_SSH_KEY` | O kullanıcının özel anahtarı (parolasız) |
| `DEPLOY_KNOWN_HOSTS` | `ssh-keyscan -H <host>` çıktısı |
| `DEPLOY_HEALTH_URL` | `https://alanadin.com/up` |

Bunları **sahibi ekler.** Anahtar ve parolalar bende görünmez ve benim
tarafımdan girilmez.

## Deploy nasıl yapılır

GitHub → `Actions` → `Deploy` → `Run workflow` → kutuya `DEPLOY` yazıp
çalıştır. Akış sırayla: imajı `linux/amd64` için derler, GHCR'a iter, VPS'e
bağlanır, `docker compose pull && up -d` çalıştırır, sonra siteye HTTP isteği
atarak gerçekten cevap verdiğini doğrular.

## Geri alma

Her imaj commit SHA'sı ile etiketlenir. Geri almak, sunucuda `.image.env`
içindeki `ZABUNO_IMAGE` değerini önceki SHA ile değiştirip
`docker compose up -d` çalıştırmaktır. Migrasyonlar geri alınmaz; şema
değişikliği içeren bir sürümden dönüş, ayrı bir karardır.

## Yerelde doğrulandı

İmaj derlendi ve yığın çalıştırıldı: migrasyonlar PostgreSQL 17'de koştu,
`/up` 200 verdi, açılış sayfası 9.276 bayt gerçek içerik döndürdü, CSP ve
`X-Frame-Options` başlıkları yerindeydi, opcache üretim ayarlarıyla açıktı.

İki kusur bu doğrulama sırasında bulundu ve düzeltildi: `composer` imajında
`gd` yoktu (yeni bildirilen `ext-*` sözleşmesi derlemeyi durdurdu — doğru
davranış), ve geliştirme makinesinin `bootstrap/cache` dizini imaja
kopyalanıp `--no-dev` ile var olmayan bir sağlayıcıyı yüklemeye çalıştı.
İkisi de `DeploymentContractTest` ile kilitlendi.

## Henüz yapılmadı

- **Gerçek sunucuda koşum.** Yığın yerelde çalıştı; netcup'ta çalıştığı
  kanıtlanmadı. Exit Gate bu kanıtı bekliyor (`docs/18`).
- **Yedekleme otomasyonu.** `db-backups` hacmi ayrıldı, içine yazan bir iş
  yok.
- **Kuyruk işçisi.** `QUEUE_CONNECTION=database` ayarlı ama kuyruk tüketen
  bir süreç yığında yok; kuyruğa iş bırakan bir özellik eklendiği gün
  gerekir.
