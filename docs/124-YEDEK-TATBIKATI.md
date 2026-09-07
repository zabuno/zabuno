# 124 — Yedek tatbikatı: koşucular, zamanlama, kanıt ve üretimde ilk gerçek tatbikat

> **Bu belge bir runbook'tur.** Ne yapıldığını ölçülmüş hâliyle, ne
> yapılmadığını açıkça söyler. "Yedek alınıyor" gibi bir cümle burada
> ancak kanıt tablosunda bir satır varsa yazılır — bugün üretimde o satır
> **yoktur**. Yol haritası satırı `docs/107` Faz 1.5; gap kaydı `docs/16`
> DR-01/DR-02; dağıtım yığını `docs/42`.

**Sahibin cümlesiyle:** *"Denenmemiş bir yedek, yedek değildir."*
Restoranın menüsünü kaybettik; geri getirebiliyor muyuz? Bu belge o
soruya bugün verilebilen dürüst cevabı ve cevabı "evet"e çevirmek için
kalan adımları taşır.

## 0. Ölçülmüş durum (2026-09-06)

| Ne | Durum | Nerede ölçüldü |
| --- | --- | --- |
| SQLite veritabanı tatbikatı (geliştirici makinesi) | Koşuyor, `passed` üretiyor | `tests/Unit/Infrastructure/Security/Execution/SqliteBackupRestoreDrillRunnerTest.php` |
| PostgreSQL veritabanı tatbikatı (üretim motoru) | Kod var; sunucusuz sözleşme yerelde ölçüldü; gerçek `pg_dump`/`pg_restore` turu **yalnız CI'da** ölçülür | `PostgresBackupRestoreDrillRunnerTest` (yerel), `tests/Feature/Security/PostgresBackupRestoreDrillTest.php` (CI, `DB_CONNECTION=pgsql`) |
| Medya tatbikatı (`storage/app` medya kökü) | Koşuyor; sahte dosyalarla eşleşme ve bozuk dosyada başarısızlık ölçüldü | `TarMediaBackupRestoreDrillRunnerTest` |
| Koşucu seçimi (bağlantıya göre `sqlite`/`pgsql`) | Ölçüldü | `BackupRestoreDrillRunnerFactoryTest` |
| Günlük zamanlama tanımı | Tanımlı ve testle kilitli; **çalıştığı iddia edilmiyor** | `BackupRestoreDrillIsScheduledTest` |
| Kanıt ucu (`GET /api/workspaces/{w}/security/evidence/backup-restore`) | Son kaydı, sürücüyü ve medya kaydını dönüyor | `BackupRestoreEvidenceApiTest` |
| **Üretim sunucusunda tatbikat** | **Yapılmadı.** Üretimde hiçbir kanıt satırı yok | — |
| `db-backups` hacmine düzenli yazan yedek işi | **Yok** (`docs/42` "Henüz yapılmadı") | — |

Yerelde PostgreSQL ve `pg_dump` yoktur; PostgreSQL turu için bu makinede
sonuç **bilinmiyor**dur ("geçti" değil). CI işi (`.github/workflows/ci.yml`,
"Laravel test suite (PostgreSQL, deployment target)") istemciyi sunucuyla
aynı majöre (17) çeker ve `PostgresBackupRestoreDrillTest` orada `CI=true`
iken eksik aracı skip değil **hata** sayar.

## 1. Tatbikat ne yapar

Üç sonuç vardır ve üçü de kayda geçer:

- **`passed`** — yedek alındı, izole yere geri yüklendi, karşılaştırma tuttu.
- **`failed`** — tatbikat denendi ve bir adım kırıldı ya da karşılaştırma
  tutmadı.
- **`unknown`** — tatbikat **hiç denenemedi**: `pg_dump`/`pg_restore`/`tar`
  yok, istemci sunucudan eski, kaynak okunamıyor. Bu "başarısız yedek"
  değildir; yedek hakkında hiçbir şey söylenememiştir. Sıfır çıkış koduyla
  "bilinmiyor" çelişkidir ve alan kaydı onu reddeder.

### 1.1 Veritabanı — PostgreSQL koşucusu (`PostgresBackupRestoreDrillRunner`)

1. Dondurulmuş manifest (`users`, `workspaces`, `workspace_memberships`,
   `menus`) için REPEATABLE READ bir işlem açar, `pg_export_snapshot()` ile
   anlık görüntüyü dışa aktarır; satır sayısını ve satır içeriği özetini o
   görüntüden okur.
2. `pg_dump --format=custom --snapshot=<aynı görüntü> --table=public.<…>`
   ile aynı görüntüyü arşivler. Sayım ile döküm aynı anı görür; canlı bir
   sunucuda araya giren bir INSERT tatbikatı yanlış yere "başarısız"
   göstermez.
3. Aynı sunucuda `zabuno_drill_<16 hex>` adlı **geçici** bir veritabanı
   açar, `pg_restore --single-transaction --section=pre-data --section=data`
   ile arşivi oraya yükler.
4. Geçici veritabanında tablo listesini, satır sayısını ve içerik özetini
   kaynakla karşılaştırır; sonra geçici veritabanını düşürür ve arşivi siler.
5. Kayda yazılan: `driver=pgsql`, satır sayıları, iki içerik özeti
   (`backup_sha256` = yedeklenen içerik, `restored_db_sha256` = geri
   yüklenen içerik), arşiv boyutu (`backup_bytes`), `backup_ms`, `restore_ms`.

**Neden yalnız pre-data + data.** Manifest bir alt kümedir: `menus.location_id`
manifest dışındaki `locations` tablosuna başvurur. Post-data bölümü (yabancı
anahtarlar, indeksler) boş bir geçici veritabanında o tabloyu bulamaz ve
geri yükleme kırılırdı. Tatbikatın ölçtüğü şey **satırların geri gelmesidir**;
kısıtların geri gelmesi tam veritabanı dökümünün (§5) işidir. İddia metni
bunu açıkça söyler.

**Kaynağa yazılmaz.** Kaynak veritabanına tek bir INSERT/UPDATE/DELETE yoktur;
geçici veritabanı yalnız koşucunun ürettiği adla ve yalnız
`zabuno_drill_[0-9a-f]{16}` desenine uyuyorsa düşürülür.

**Gereken yetki:** `CREATE DATABASE` (docker yığınında `DB_USERNAME`
`POSTGRES_USER`'dır ve süper kullanıcıdır; başka bir kurulumda `CREATEDB`
verilmelidir).

### 1.2 Veritabanı — SQLite koşucusu (`SqliteBackupRestoreDrillRunner`)

Davranış değişmedi (çevrimiçi yedek + izole dosya kopyası + `integrity_check`);
kayda artık `driver=sqlite`, `backup_bytes`, `backup_ms`, `restore_ms` de
yazılır ve okunamayan kaynak `failed` değil `unknown` olur.

### 1.3 Medya (`TarMediaBackupRestoreDrillRunner`)

Medya kökü, `config/filesystems.php`'deki `local` diskinin köküdür
(`storage/app/private`; medya modülü `quarantine/{workspace}/…` ve
`renditions/{workspace}/{asset}/…` altına yazar). Koşucu:

1. Kökü `tar -cf` ile arşivler, arşivi `storage/app/backup-restore-drill`
   altındaki izole bir dizine `tar -xf` ile açar.
2. Kaynak ve kopya için dosya sayısı, toplam bayt ve dosya başına SHA-256'dan
   türeyen manifest özetini karşılaştırır; tek bir bozuk bayt tatbikatı
   düşürür (testte ölçüldü).
3. Arşivi ve kopyayı siler. Kayda: `archive_sha256`, `archive_bytes`,
   dosya sayıları, bayt toplamları, iki manifest özeti, süre.

Boş medya kökü `passed` (0 dosya) olur — kaybedilecek bir şey yoktur; **yok**
medya kökü `unknown` olur. Çalışma kökü medya kökünün içindeyse tatbikat
kendini yutmamak için `unknown` der. Tatbikat sırasında disk, medya kökünün
boyutu kadar ek yer ister (arşiv + kopya); §6'da bekleyen iştir.

## 2. Komut

```text
php artisan security:evidence:backup-restore            # veritabanı + medya
php artisan security:evidence:backup-restore --database # yalnız veritabanı
php artisan security:evidence:backup-restore --media    # yalnız medya
php artisan security:evidence:backup-restore --json     # eklenen kayıtları JSON basar
```

- Koşucu bağlantıya göre seçilir (`config('database.default')`: `sqlite`
  ya da `pgsql`; başka bir bağlantı açık bir hatayla reddedilir).
- Her tatbikat kendi tablosuna **bir** satır ekler: `backup_restore_evidence`
  ve `media_backup_restore_evidence`. Kayıtlar yalnız-eklemelidir; güncelleme
  ve silme yolu yoktur.
- Çıkış kodu yalnız seçilen her tatbikat `passed` ise 0'dır. `unknown` ve
  `failed` sıfır olmayan kodla döner; zamanlayıcı ve operatör bunu görür.
- Tanımsız bir seçenek (`--status`, `--table`, `--path`, …) komutu düşürür;
  sonuç hiçbir girdiden etkilenmez.

`--json` çıktısı `{ "passed": bool, "database": {...}|null, "media": {...}|null }`
biçimindedir; kayıt alanlarını olduğu gibi taşır, ham çıktı ve yol içermez.

## 3. Zamanlama

`routes/console.php`: her gün 03:40'ta (çöp boşaltımından sonra),
`withoutOverlapping` ile. Bu tanım testle kilitlidir.

**Bu tanım "çalışıyor" demez.** Çalışıp çalışmadığı yalnız kanıt kaydından
okunur (§4). Üretim uygulama imajında bugün `pg_dump` **yoktur**
(`docker/Dockerfile` yalnız `nginx supervisor curl clamdscan` kurar); yani
zamanlama üretimde çalışsa bile veritabanı kaydı `unknown`, medya kaydı ise
gerçek bir ölçüm olacaktır. Bu da kayıttır ve doğrudur; §6 madde 2 bunun
kapanışıdır.

## 4. Kanıt nerede görünür

`GET /api/workspaces/{workspace}/security/evidence/backup-restore` — çalışma
alanı sahibi (`SecurityEvidenceView` izni), son kayıtlar:

```json
{
  "data":  { "key": "backup_restore", "status": "passed|failed|unknown", "driver": "sqlite|pgsql",
             "source_row_count": 0, "restored_row_count": 0, "backup_bytes": 0,
             "backup_ms": 0, "restore_ms": 0, "claim": "…", "integrity_sha256": "…" },
  "media": { "key": "media_backup_restore", "status": "…", "source_file_count": 0,
             "restored_file_count": 0, "source_bytes": 0, "archive_bytes": 0, "claim": "…" }
}
```

- `media` hiç kayıt yoksa `null`dır. Veritabanı kaydı yoksa uç 404 döner.
- Kurcalanmış bir satır (özet tutmuyor) 500 döner; hiçbir şey sunulmaz.
- **Eski satırlar.** 2026-09-06 migrasyonundan önce yazılmış satırların
  özeti yeni alanları kapsamaz ve doğrulanmaz; ilk yeni koşu "son kayıt"
  olur. Aradaki pencerede uç 500 döner — doğrulanamayan kaydın "geçti" diye
  sunulmamasıdır.
- Süperadmin ekranı ve panel yüzeyi bu paketin dışındadır; okuma ucu
  değişmemiş, alanları genişlemiştir.

## 5. Üretim sunucusunda ilk gerçek tatbikat — adım adım

Sunucuda, `docker-compose.yml`'in bulunduğu dizinde ve `.env` ile. Aşağıdaki
adımlar **kaynağa yazmaz**; §5.6 dışında hiçbiri geri döndürülemez bir iş
yapmaz. Komutlar `db` konteynerinin **kendi** `pg_dump`'ını kullanır; sürüm
sunucuyla aynıdır (postgres:17).

### 5.1 Ön kontrol

```bash
docker compose --env-file .env ps
docker compose --env-file .env exec db pg_isready -U "$DB_USERNAME"
docker compose --env-file .env exec db df -h /backups
```

`/backups`, `db-backups` hacmidir; konteynerle birlikte silinmez.

### 5.2 Tam veritabanı yedeği (manifest değil, TÜM veritabanı)

```bash
docker compose --env-file .env exec db sh -c \
  'pg_dump -U "$POSTGRES_USER" -d "$POSTGRES_DB" --format=custom \
     --file=/backups/zabuno-$(date +%Y%m%d-%H%M%S).dump'
docker compose --env-file .env exec db sh -c 'ls -l /backups && sha256sum /backups/*.dump'
```

Boyutu ve SHA-256'yı not alın; kanıtın parçasıdır. Uygulama içi tatbikat
dört tabloyu ölçer; gerçek yedek **tüm** veritabanıdır ve kısıtları da
taşır.

### 5.3 İzole geri yükleme (geçici veritabanı)

```bash
docker compose --env-file .env exec db sh -c \
  'createdb -U "$POSTGRES_USER" -T template0 zabuno_drill_manual && \
   time pg_restore -U "$POSTGRES_USER" --dbname=zabuno_drill_manual \
     --no-owner --no-privileges --exit-on-error /backups/<5.2-deki dosya>'
```

`time` çıktısı **ölçülmüş** geri yükleme süresidir — `docs/16` DR-01'in
RTO hedefi (4 saat) ilk kez bir sayıyla karşılaştırılır. Sonucu §6'ya
yazın.

### 5.4 Satır sayılarını karşılaştır

```bash
docker compose --env-file .env exec db sh -c '
for t in users workspaces workspace_memberships menus locations brands media_assets; do
  src=$(psql -U "$POSTGRES_USER" -d "$POSTGRES_DB" -tAc "select count(*) from $t")
  dst=$(psql -U "$POSTGRES_USER" -d zabuno_drill_manual -tAc "select count(*) from $t")
  echo "$t: $src / $dst"
done'
```

Her satırda iki sayı eşit olmalıdır. Eşit değilse tatbikat **başarısızdır**;
sebep bulunmadan §5.6'ya geçilmez.

### 5.5 Geçici veritabanını düşür

```bash
docker compose --env-file .env exec db sh -c 'dropdb -U "$POSTGRES_USER" zabuno_drill_manual'
docker compose --env-file .env exec db sh -c \
  'psql -U "$POSTGRES_USER" -d "$POSTGRES_DB" -tAc "select datname from pg_database where datname like '"'"'zabuno_drill_%'"'"'"'
```

İkinci komut boş dönmelidir. Uygulama içi tatbikatın düşüremediği bir
`zabuno_drill_<hex>` kalmışsa aynı `dropdb` ile temizlenir.

### 5.6 Medya yedeği ve izole geri açma

```bash
docker compose --env-file .env exec app sh -c \
  'tar -cf /tmp/zabuno-media-$(date +%Y%m%d).tar -C /var/www/html/storage/app/private . && ls -l /tmp/zabuno-media-*.tar'
docker compose --env-file .env exec app sh -c \
  'mkdir -p /tmp/media-restore && tar -xf /tmp/zabuno-media-*.tar -C /tmp/media-restore && \
   echo "kaynak: $(find /var/www/html/storage/app/private -type f | wc -l) dosya" && \
   echo "kopya:  $(find /tmp/media-restore -type f | wc -l) dosya"'
docker compose --env-file .env cp app:/tmp/zabuno-media-$(date +%Y%m%d).tar ./  # sunucu dışına çıkarmak için ilk adım
docker compose --env-file .env exec app sh -c 'rm -rf /tmp/media-restore /tmp/zabuno-media-*.tar'
```

Bu, uygulama içi medya tatbikatının elle karşılığıdır; `storage/app` hacmi
`app-storage`'dır ve imajla birlikte silinmez.

### 5.7 Uygulama içi tatbikat ve kanıt satırı

```bash
docker compose --env-file .env exec app php artisan security:evidence:backup-restore --json
```

- Medya kaydı gerçek bir ölçümdür.
- Veritabanı kaydı, imajda `pg_dump` yokken `"status": "unknown"`,
  `"exit_code": 127` döner. Bu beklenen ve doğru çıktıdır; §6 madde 2
  kapanana kadar böyle kalır.

Kanıt: `GET /api/workspaces/{w}/security/evidence/backup-restore` (sahip
oturumuyla) ya da doğrudan tablo:

```bash
docker compose --env-file .env exec db sh -c \
  'psql -U "$POSTGRES_USER" -d "$POSTGRES_DB" -c "select id, status, driver, source_row_count, restored_row_count, backup_bytes, ran_at from backup_restore_evidence order by id desc limit 3" \
   -c "select id, status, source_file_count, restored_file_count, source_bytes, ran_at from media_backup_restore_evidence order by id desc limit 3"'
```

### 5.8 Tatbikat raporu

Tek bir yere yazılır (`docs/16` DR-02 satırına tarihli bir güncelleme):
tarih, `pg_dump` dosya boyutu ve SHA-256, `pg_restore` süresi, satır
sayısı tablosu, medya dosya sayısı ve toplam bayt, `--json` çıktısındaki
iki `status`. Ölçülmeyen bir alan boş bırakılmaz; "ölçülmedi" yazılır.

## 6. Geri yükleme kararı kimde

- **Tatbikat hiçbir koşulda üretimin üstüne geri yüklemez.** Geçici
  veritabanı ve izole dizin dışına çıkmaz.
- **Üretimin üstüne geri yükleme** (bir kaybı gerçekten geri almak) geri
  döndürülemez bir iştir: karar **sahibindir**, uygulama **DevOps'undur**
  (Hüseyin). Karar verilmeden `pg_restore --dbname=$POSTGRES_DB` hiçbir
  koşulda çalıştırılmaz; önce §5.2 ile o anın yedeği alınır.
- RPO/RTO hedefleri (`docs/16` DR-01: 24 sa / 4 sa) **hedeftir**; §5.3'teki
  `time` ilk ölçümdür ve hedefi doğrulayana kadar taahhüt olarak yazılmaz.

## 7. Hüseyin (DevOps) için bekleyen işler

Bağlantı: `docs/42` "Henüz yapılmadı" (yedekleme otomasyonu), `docs/87`
(üretimde görünen kusurlar), `docs/61` P0-07 (canlı dağıtım kanıtı).

1. **§5'i bir kez koşmak** ve §5.8 raporunu `docs/16` DR-02'ye yazmak. Bu
   satır kapanmadan `docs/107` Faz 1.5 "bitti" olmaz.
2. **Uygulama imajına `postgresql-client-17`** (PGDG deposundan; Debian
   bookworm'un kendi paketi 15'tir ve 17 sunucuyu reddeder) — böylece
   günlük tatbikatın veritabanı ayağı `unknown` yerine gerçek ölçüm yazar.
   `docker/Dockerfile` bu pakette **değiştirilmedi**: yerelde imaj derlenip
   ölçülmedi, ölçülmemiş bir Dockerfile değişikliği deploy'u kırabilirdi.
3. **`db-backups` hacmine düzenli yazan bir yedek işi** (`docs/42`). Bu
   paket onu yazmadı; tatbikat, var olmayan bir yedeği doğrulayamaz.
4. **Sunucu dışı kopya.** `/backups` ve medya arşivi sunucuyla birlikte
   kaybolur; sunucu dışına kopya olmadan RPO sonsuzdur (`docs/98` düzeltmesi).
5. **Disk payı.** Günlük medya tatbikatı, medya kökü kadar ek yer ister
   (arşiv + kopya). `df -h` ile pay ölçülür; yetmiyorsa tatbikat `failed`
   yazar, sessizce geçmez.
6. **Saklama süresi.** `/backups` altındaki dökümlerin kaç gün tutulacağı
   sahip kararıdır (dış maliyet: disk); karar verilene kadar dosyalar
   silinmez.

## 8. Sınırlar ve bilinmeyenler

- Tatbikat aynı sunucudadır; sunucunun kendisi kaybolduğunda geri
  dönülebileceğini **göstermez** (madde 7.4).
- Veritabanı manifesti dört tablodur; medya-satır ilişkisi (`media_assets`),
  QR hedefleri, abonelikler manifestte değildir. Tam yedek §5.2'dir.
- Nokta-zaman kurtarma (WAL arşivi) yoktur; RPO en iyi hâlde son döküm
  anıdır.
- PostgreSQL turu için bu makinede sonuç bilinmiyor; CI'daki ilk koşu bu
  paketin PR'ında ölçülür. Kırılırsa kayıt burada güncellenir, "geçti"
  yazılmaz.
