<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * ŞEMA EVRİMİ: bir sağlayıcı → N bağlantı (`docs/95` Faz 3).
 *
 * `platform_credentials.provider` UNIQUE idi — bir sağlayıcının kasada
 * yalnız bir satırı olabiliyordu. Sahibin istediği "N tane hesap ekle"
 * düğmesi bu kısıt kalkmadan çalışamaz; ve kısıt yalnız bir kolaylık
 * meselesi değil: `docs/96` Faz 3, aynı modelin toplu içe aktarma için
 * AYRI bir hesapta çalışmasını istiyor — izolasyonun amacı paylaşılan
 * kotayı korumak.
 *
 * GÖÇ VERİYİ TAŞIR, ATMAZ. Var olan her satır "Varsayılan" etiketli bir
 * platform bağlantısına dönüşür; sır ŞİFRELİ HÂLİYLE kopyalanır — bu
 * migration hiçbir sırrı çözmez, çözmesi de gerekmez. `down()` aynı yolu
 * geriye yürür: sağlayıcı başına en eski bağlantı eski tabloya döner.
 *
 * `tenant_id` yerine `workspace_id`: bu depoda tenant'ın adı workspace'tir
 * (`docs/95` metni "tenant" diyor, kod "workspace" — aynı şey, tek ad
 * kullanılıyor ki sorgular birbirine benzesin).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_credential_connections', function (Blueprint $table): void {
            $table->id();
            // UNIQUE DEĞİL — bütün göç bunun için.
            $table->string('provider');
            // Superadmin'in verdiği ad: "OpenAI — Toplu İçe Aktarma". Sır
            // görünmediği için panelde iki kartı ayırt eden tek şey budur.
            $table->string('label');
            // platform_owned | tenant_byok
            $table->string('scope')->default('platform_owned');
            // Yalnız BYOK'ta dolu. Tenant silinirse bağlantısı da gider:
            // sahibi olmayan bir tenant anahtarının kime hizmet edeceği
            // tanımsız olurdu.
            $table->foreignId('workspace_id')->nullable()->constrained('workspaces')->cascadeOnDelete();

            $table->json('plain_fields')->nullable();
            $table->text('secret_ciphertext')->nullable();
            $table->json('secret_hints')->nullable();

            // active | disabled. Kapalı bir kayıt çözülmez, silinmez.
            $table->string('state')->default('active');
            // unknown | healthy | unhealthy — bilinmeyen sağlıklı SAYILMAZ
            // ama aday havuzunda kalır (sınanma şansı olsun).
            $table->string('health_status')->default('unknown');
            $table->timestamp('last_health_check_at')->nullable();
            $table->timestamp('last_rotated_at')->nullable();
            $table->foreignId('set_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['provider', 'scope', 'state']);
            $table->index(['workspace_id', 'provider']);
        });

        // Denetim izi artık HANGİ bağlantı olduğunu da söyler. Nullable:
        // göçten önceki satırlar bir bağlantıya ait değildi ve geriye dönük
        // bir kimlik uydurmak, denetim izini kurgu hâline getirirdi.
        Schema::table('platform_credential_audits', function (Blueprint $table): void {
            $table->unsignedBigInteger('connection_id')->nullable()->after('provider');
            $table->index('connection_id');
        });

        if (Schema::hasTable('platform_credentials')) {
            foreach (DB::table('platform_credentials')->orderBy('id')->get() as $row) {
                DB::table('platform_credential_connections')->insert([
                    'provider' => $row->provider,
                    'label' => 'Varsayılan',
                    'scope' => 'platform_owned',
                    'workspace_id' => null,
                    'plain_fields' => $row->plain_fields,
                    // Şifreli metin OLDUĞU GİBİ taşınır — göç sırrı çözmez.
                    'secret_ciphertext' => $row->secret_ciphertext,
                    'secret_hints' => $row->secret_hints,
                    'state' => $row->state,
                    'health_status' => 'unknown',
                    'last_health_check_at' => null,
                    'last_rotated_at' => $row->last_rotated_at,
                    'set_by_user_id' => $row->set_by_user_id,
                    'created_at' => $row->created_at,
                    'updated_at' => $row->updated_at,
                ]);
            }

            Schema::drop('platform_credentials');
        }
    }

    public function down(): void
    {
        Schema::create('platform_credentials', function (Blueprint $table): void {
            $table->id();
            $table->string('provider')->unique();
            $table->json('plain_fields')->nullable();
            $table->text('secret_ciphertext')->nullable();
            $table->json('secret_hints')->nullable();
            $table->string('state')->default('active');
            $table->timestamp('last_rotated_at')->nullable();
            $table->foreignId('set_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        // Geri dönüşte sağlayıcı başına EN ESKİ platform bağlantısı taşınır:
        // eski şemada ikincisine yer yok ve hangisinin "asıl" olduğunu
        // uydurmak yerine, ilk yazılanı seçmek belirlenebilir bir kuraldır.
        $seen = [];
        $rows = DB::table('platform_credential_connections')
            ->where('scope', 'platform_owned')
            ->orderBy('id')
            ->get();

        foreach ($rows as $row) {
            if (isset($seen[$row->provider])) {
                continue;
            }
            $seen[$row->provider] = true;

            DB::table('platform_credentials')->insert([
                'provider' => $row->provider,
                'plain_fields' => $row->plain_fields,
                'secret_ciphertext' => $row->secret_ciphertext,
                'secret_hints' => $row->secret_hints,
                'state' => $row->state,
                'last_rotated_at' => $row->last_rotated_at,
                'set_by_user_id' => $row->set_by_user_id,
                'created_at' => $row->created_at,
                'updated_at' => $row->updated_at,
            ]);
        }

        Schema::table('platform_credential_audits', function (Blueprint $table): void {
            $table->dropIndex(['connection_id']);
            $table->dropColumn('connection_id');
        });

        Schema::dropIfExists('platform_credential_connections');
    }
};
