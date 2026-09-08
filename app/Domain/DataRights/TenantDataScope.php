<?php

declare(strict_types=1);

namespace App\Domain\DataRights;

/**
 * KAPSAM — "bu çalışma alanının verisi tam olarak nedir?" (FF-226, `docs/138`).
 *
 * Bu sınıf bir liste değil bir KARAR KÜTÜĞÜDÜR. Şemadaki her tablo ya
 * kiracıya aittir (ve o zaman nasıl bulunacağı, dışa aktarılıp
 * aktarılmayacağı, silinip silinmeyeceği yazılıdır) ya da açıkça kiracıya
 * ait DEĞİLDİR ve sebebi yazılıdır. Üçüncü bir hâl — "sınıflandırılmamış" —
 * yoktur ve bir test bunu her koşuda ölçer
 * (`TenantDataScopeCoversTheSchemaTest`).
 *
 * NEDEN BÖYLE: kapsamı bir belgeye yazmak yetmez. Yarın biri yeni bir tablo
 * ekler, belge güncellenmez ve dışa aktarma o tabloyu sessizce atlar; ya da
 * daha kötüsü, silme onu atlar ve "sildik" dediğimiz veri durmaya devam
 * eder. Karar kodda olmalı ki test onu şemaya karşı ölçebilsin.
 *
 * SIRA ÖNEMLİ: liste EBEVEYNDEN ÇOCUĞA doğrudur. Satırlar bu sırayla
 * bulunur (çocuk ebeveynin kimliklerine ihtiyaç duyar) ve TERS sırayla
 * silinir (yabancı anahtar önce çocuğu ister).
 */
final class TenantDataScope
{
    /**
     * Kiracıya ait tablolar, ebeveynden çocuğa.
     *
     * @return list<TenantTable>
     */
    public static function tables(): array
    {
        return [
            // ——— Kimlik ve ekip ———
            TenantTable::direct('workspace_memberships'),
            TenantTable::direct('team_invitations'),

            // ——— Marka, şube, salon ———
            TenantTable::direct('brands'),
            TenantTable::direct('locations'),
            TenantTable::direct('location_opening_hours'),
            TenantTable::direct('dining_areas'),
            TenantTable::direct('dining_tables'),

            // ——— Menü ———
            TenantTable::direct('products'),
            TenantTable::child('product_allergens', 'products', 'product_id'),
            TenantTable::direct('menus'),
            TenantTable::child('menu_categories', 'menus', 'menu_id'),
            TenantTable::child('menu_items', 'menu_categories', 'category_id'),
            TenantTable::child('menu_service_switches', 'menus', 'menu_id'),
            TenantTable::direct('menu_publications'),
            TenantTable::child('menu_publication_current_pointers', 'menus', 'menu_id'),
            TenantTable::direct('menu_publication_schedules'),
            TenantTable::direct('menu_audits'),

            // ——— Karekod ———
            TenantTable::direct('qr_codes'),
            TenantTable::child('qr_destinations', 'qr_codes', 'qr_code_id'),
            TenantTable::child('qr_code_current_destinations', 'qr_codes', 'qr_code_id'),

            // ——— Misafirin bıraktığı iz ———
            TenantTable::direct('analytics_events'),
            TenantTable::direct('orders'),
            TenantTable::child('order_items', 'orders', 'order_id'),
            TenantTable::direct('rating_signals'),
            TenantTable::direct('rating_scores'),
            TenantTable::direct('rating_replies'),

            // ——— Medya ———
            TenantTable::direct('media_assets'),
            TenantTable::direct('media_blobs'),
            TenantTable::child('media_versions', 'media_assets', 'media_asset_id'),
            TenantTable::child('media_renditions', 'media_versions', 'media_version_id'),
            TenantTable::direct('media_folders'),
            TenantTable::direct('media_usages'),
            TenantTable::direct('media_audits'),
            TenantTable::direct('media_bulk_operations'),
            TenantTable::direct(
                'media_processing_jobs',
                exported: false,
                exclusionReason: 'İç kuyruk durumu: bir dosyanın işlenip işlenmediğini söyler, kiracının kendi içeriğini değil.',
            ),

            // ——— Yapay zekâ ———
            /*
                ÇAĞRI ÖNCE, ÜRÜN SONRA. `ai_artifacts.ai_invocation_id`
                çağrıya işaret eder; sıra ters olsaydı silme, çağrıları
                hâlâ onlara bakan ürünlerden ÖNCE silmeye çalışır ve
                PostgreSQL'de yabancı anahtar kısıtında düşerdi.
                `ErasureOrderRespectsForeignKeysTest` bunu şemadan ölçüyor
                ve bu satırın sırasını ilk koşuda yakaladı.
            */
            TenantTable::direct('ai_invocations'),
            TenantTable::direct('ai_artifacts'),
            TenantTable::direct('ai_batches'),
            TenantTable::direct('ai_batch_pages'),
            TenantTable::direct(
                'ai_connection_assignments',
                exported: false,
                exclusionReason: 'Platformun sağlayıcı kasasındaki bir bağlantıya işaret eder; kiracının içeriğini taşımaz (`docs/94`).',
            ),

            // ——— Dış sistem eşlemesi ———
            TenantTable::direct('external_references'),

            // ——— Abonelik ve ödeme ———
            TenantTable::direct('subscriptions'),
            TenantTable::direct('billing_profiles'),
            TenantTable::direct('iyzico_sandbox_transactions'),
            TenantTable::direct(
                'payment_transactions',
                erased: false,
                retentionReason: 'Tahsilatın kendi kaydı; kesilen faturanın dayanağıdır ve ticari/mali kayıt saklama yükümlülüğüne tabidir.',
            ),
            TenantTable::direct(
                'manual_payments',
                erased: false,
                retentionReason: 'Elden alınan ödemenin kaydı; aynı ticari/mali saklama yükümlülüğüne tabidir.',
            ),
            TenantTable::direct(
                'invoices',
                erased: false,
                retentionReason: 'Kesilmiş fatura yasal bir belgedir; ayrıca numara serisi boşluksuzdur (`docs/130`) ve bir satırın silinmesi seride kanıtlanabilir bir boşluk açardı.',
            ),
            TenantTable::direct(
                'ledger_entries',
                erased: false,
                retentionReason: 'Defter kaydı; faturanın muhasebe karşılığıdır ve tek başına silinemez.',
            ),

            // ——— Kanıt kayıtları ———
            TenantTable::direct(
                'consent_records',
                erased: false,
                retentionReason: 'Onayın kanıtı; en çok hesabın artık olmadığı gün gerekir (`docs/124` §3). Ad ve e-posta zaten kopyalanmaz.',
            ),
            TenantTable::direct(
                'support_requests',
                erased: false,
                retentionReason: 'Destek yazışmasının kaydı; referans numarası müşterinin elindedir ve talebin kendisi bir sözleşme ilişkisinin kanıtıdır.',
            ),
            TenantTable::direct(
                'workspace_data_requests',
                erased: false,
                retentionReason: 'Silme talebinin kendi kaydı; silinirse "kim ne zaman ne istedi" sorusunun cevabı da silinirdi.',
            ),
        ];
    }

    /**
     * Kiracıya ait OLMAYAN tablolar ve sebepleri.
     *
     * Bu liste bir savunmadır: "neden benim verimde yok?" sorusunun cevabı
     * burada, tablo adıyla birlikte durur. Boş bir liste bırakmak, sınıfın
     * "geri kalan her şey" diye sessizce genellemesi olurdu.
     *
     * @return array<string, string>
     */
    public static function outOfScope(): array
    {
        return [
            'users' => 'Kişi bir çalışma alanının malı değildir ve birden fazla çalışma alanında üye olabilir; hesabın kendisi ayrı bir haktır.',
            'sessions' => 'Kişinin oturumu; çalışma alanına değil kullanıcıya aittir.',
            'password_reset_tokens' => 'Kişinin kimlik akışı; çalışma alanına ait değildir.',
            'plans' => 'Platformun plan kataloğu; her kiracıya aynı gelir.',
            'features' => 'Platformun özellik anahtarları.',
            'taxonomy_terms' => 'Paylaşılan alerjen sözlüğü; tek bir kiracının verisi değildir.',
            'content_pages' => 'Tanıtım sitesinin sayfa kütüğü.',
            'contact_messages' => 'Tanıtım sitesinin iletişim formu; bir çalışma alanına bağlı değildir.',
            'platform_audits' => 'Platformun kendi denetim kaydı — kiracının verisi değil, PLATFORMUN kaydıdır; kiracı denetim izi ayrıdır (Ayarlar → Denetim izi).',
            'platform_settings' => 'Platform ayarları.',
            'platform_role_assignments' => 'Platform rolleri; kiracı üyeliği değildir.',
            'platform_credentials' => 'Sağlayıcı kasası (`docs/94`) — sırlar kiracıya ait değildir ve hiçbir dışa aktarmaya girmez.',
            'platform_credential_connections' => 'Sağlayıcı bağlantıları; `workspace_id` alanı bir ATAMA kapsamıdır, kiracının içeriği değil, ve şifreli sır taşır.',
            'platform_credential_audits' => 'Kasanın denetim kaydı.',
            'invoice_number_sequences' => 'Fatura numarası sayacı; seri platform genelindedir.',
            'backup_restore_evidence' => 'Yedek tatbikatı kanıtı; platformun kaydı.',
            'media_backup_restore_evidence' => 'Medya yedek tatbikatı kanıtı; platformun kaydı.',
            'host_capability_evidence' => 'Sunucu yetenek kanıtı.',
            'tenant_isolation_evidence' => 'İzolasyon kanıtı; tek bir kiracıya ait değildir.',
            'release_attestations' => 'Sürüm beyanı.',
            'cache' => 'Geçici önbellek.',
            'cache_locks' => 'Geçici kilit.',
            'jobs' => 'Kuyruk.',
            'job_batches' => 'Kuyruk partileri.',
            'failed_jobs' => 'Düşen kuyruk işleri.',
            'migrations' => 'Göç kütüğü.',
            'sqlite_sequence' => 'SQLite iç sayacı.',
        ];
    }

    /**
     * Dışa aktarılan bölümler.
     *
     * @return list<TenantTable>
     */
    public static function exportedTables(): array
    {
        return array_values(array_filter(self::tables(), static fn (TenantTable $table): bool => $table->exported));
    }

    /**
     * Silinen tablolar, SİLME SIRASINDA (çocuktan ebeveyne).
     *
     * @return list<TenantTable>
     */
    public static function erasedTablesInDeletionOrder(): array
    {
        return array_values(array_reverse(array_filter(self::tables(), static fn (TenantTable $table): bool => $table->erased)));
    }

    /**
     * Silinmeyenler ve sebepleri — ekranda ve belgede aynı kaynaktan okunur.
     *
     * @return array<string, string>
     */
    public static function retained(): array
    {
        $retained = [];

        foreach (self::tables() as $table) {
            if (! $table->erased) {
                $retained[$table->name] = (string) $table->retentionReason;
            }
        }

        return $retained;
    }
}
