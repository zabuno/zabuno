<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * YEDEK TATBİKATI ÜRETİM MOTORUNA VE MEDYAYA GENİŞLİYOR (docs/124).
 *
 * `backup_restore_evidence` bugüne kadar tek bir koşucunun (SQLite,
 * geliştirici makinesi) kaydını taşıyordu. Üretim PostgreSQL; kayıt artık
 * hangi sürücünün koştuğunu (`driver`), yedek boyutunu ve iki aşamanın
 * ölçülmüş süresini taşır. Medya (`storage/app` altındaki fotoğraflar)
 * tatbikata hiç girmiyordu; kendi tablosu açılır.
 *
 * ESKİ SATIRLAR. Bütünlük özeti artık yeni alanları da kapsar; bu
 * migrasyondan önce yazılmış satırların özeti yeni kanonik biçimde
 * DOĞRULANMAZ. Kayıtlar yalnız-eklemeli olduğu için elle düzeltilmez:
 * bir sonraki tatbikat koşusu (günlük zamanlama ya da elle komut) "son
 * kayıt" olur ve uç nokta onu sunar. Aradaki pencerede uç nokta eski
 * satır için 500 döner — bu, doğrulanamayan bir kaydın "geçti" diye
 * sunulmamasıdır, bir arıza değil.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('backup_restore_evidence', function (Blueprint $table) {
            $table->string('driver', 16)->default('sqlite')->after('runner');
            $table->unsignedBigInteger('backup_bytes')->default(0)->after('restored_row_count');
            $table->unsignedInteger('backup_ms')->default(0)->after('backup_bytes');
            $table->unsignedInteger('restore_ms')->default(0)->after('backup_ms');
        });

        Schema::create('media_backup_restore_evidence', function (Blueprint $table) {
            $table->id();
            $table->string('key')->index();
            $table->string('status');
            $table->string('scope');
            $table->string('runner');
            $table->timestampTz('ran_at');
            $table->unsignedInteger('duration_ms');
            $table->integer('exit_code');
            $table->char('git_sha', 40);
            $table->boolean('git_dirty');
            $table->char('source_snapshot_sha256', 64);
            $table->char('suite_manifest_sha256', 64);
            $table->char('archive_sha256', 64);
            $table->unsignedBigInteger('archive_bytes');
            $table->char('source_manifest_sha256', 64);
            $table->char('restored_manifest_sha256', 64);
            $table->unsignedInteger('source_file_count');
            $table->unsignedInteger('restored_file_count');
            $table->unsignedBigInteger('source_bytes');
            $table->unsignedBigInteger('restored_bytes');
            $table->char('output_sha256', 64);
            $table->char('integrity_sha256', 64);
            $table->text('claim');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media_backup_restore_evidence');

        Schema::table('backup_restore_evidence', function (Blueprint $table) {
            $table->dropColumn(['driver', 'backup_bytes', 'backup_ms', 'restore_ms']);
        });
    }
};
