<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ÖLÇÜM, SİLİNEN BİR MENÜYLE BİRLİKTE ÖLMEZ.
 *
 * Çoklu menüyle birlikte menü SİLME akışı doğdu (`docs/109` §7.1). Silinen
 * bir menünün `analytics_events` satırları vardır ve üç seçenekten ikisi
 * kabul edilemezdi:
 *
 * 1. Olayları da silmek — sahibin geçen ayki tarama sayısı, bir menüyü
 *    kaldırdığı için geriye dönük olarak değişirdi. Ölçüm geçmişi
 *    yeniden yazılamaz.
 * 2. Olayları hayatta kalan menüye taşımak — kahvaltı menüsünün
 *    açılışları akşam menüsünün hanesine yazılırdı. Bu, silmekten daha
 *    kötü: veri var ama YANLIŞ.
 * 3. Menü bağını KOPARMAK, olayı bırakmak. Olay hâlâ kiracıya, şubeye ve
 *    karekoda bağlıdır; yalnız artık var olmayan menüye işaret etmez.
 *
 * Üçüncüsü seçildi. Şube ve karekod kırılımları bozulmadan çalışmaya devam
 * eder; menü kırılımında o satırlar "menüsü silinmiş" olarak görünür ve bu
 * doğrudur.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('analytics_events', function (Blueprint $table) {
            $table->dropForeign(['menu_id']);
        });

        Schema::table('analytics_events', function (Blueprint $table) {
            $table->unsignedBigInteger('menu_id')->nullable()->change();
        });

        Schema::table('analytics_events', function (Blueprint $table) {
            $table->foreign('menu_id')->references('id')->on('menus')->nullOnDelete();
        });
    }

    public function down(): void
    {
        /*
            Geri alırken menüsü kalmamış olaylar SİLİNMEZ — bir göç geri
            alması ölçüm geçmişini yok edemez. Sütun zorunlu hâle
            getirilemiyorsa göç patlar ve bu doğru davranıştır.
        */
        Schema::table('analytics_events', function (Blueprint $table) {
            $table->dropForeign(['menu_id']);
        });

        Schema::table('analytics_events', function (Blueprint $table) {
            $table->unsignedBigInteger('menu_id')->nullable(false)->change();
        });

        Schema::table('analytics_events', function (Blueprint $table) {
            $table->foreign('menu_id')->references('id')->on('menus');
        });
    }
};
