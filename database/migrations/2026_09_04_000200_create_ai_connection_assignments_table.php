<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * TENANT → BAĞLANTI YAPIŞKAN EŞLEMESİ — `docs/14` §2a, `docs/95` Faz 3.
 *
 * Bir tenant'ın ilk isteği hangi bağlantıya giderse sonrakiler de oraya
 * gider. Bu bir tercih değil, maliyet gerçeği: prompt önbelleği ve oturum
 * bağlamı hesaba bağlıdır, istekleri iki hesap arasında dağıtmak her
 * seferinde soğuk önbellekle çalışmak demektir.
 *
 * Sağlayıcı BAŞINA bir eşleme: bir tenant OpenAI'da bir hesaba, Gemini'de
 * başkasına yapışabilir — bunlar bağımsız kararlardır.
 *
 * İki kayıt da `cascadeOnDelete`: eşleme kendi başına bir gerçek değil,
 * iki tarafın da yaşadığı sürece anlamlı bir bağdır. Workspace ya da
 * bağlantı giderse eşleme de gider — "silinmiş bir hesaba yapışmış tenant"
 * diye bir durum kalmasın.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_connection_assignments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('workspace_id')->constrained('workspaces')->cascadeOnDelete();
            $table->string('provider');
            $table->foreignId('connection_id')
                ->constrained('platform_credential_connections')
                ->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['workspace_id', 'provider']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_connection_assignments');
    }
};
