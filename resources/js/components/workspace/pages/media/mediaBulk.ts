import type { t } from '../../../../i18n/workspace';

/**
 * TOPLU İŞLEM SİHİRBAZININ SAF KURALLARI — kanonik kaynak
 * `docs/reference/panel-v3/MedyaModulu.dc.html`, "Toplu işlem"
 * (plan: `docs/109-PANEL-V3.md` §2).
 *
 * Kararlar çizimden AYRI durur: "kelime doğru mu", "bu sebep hangi
 * cümle", "sonuç listesi nasıl sayılır". Bileşene gömülmüş olsalardı her
 * birini sınamak için bir ekran çizmek gerekirdi.
 *
 * Sunucu SEBEP KODU verir, ürün CÜMLEYİ yazar (`docs/37`). Bu dosya o iki
 * dünyanın buluştuğu yerdir ve tek yönlüdür: koddan cümleye.
 */

export type MediaBulkActionKey = 'optimize' | 'convert' | 'move' | 'trash' | 'purge';

export type MediaBulkScopeKind = 'selected' | 'workspace' | 'folder';

export type MediaBulkStep = 'scope' | 'action' | 'config' | 'preview' | 'run';

export type MediaBulkPlan = {
    action: MediaBulkActionKey;
    /** Bu kullanıcı bu eylemi ÇALIŞTIRABİLİR mi? Planı yine de okur. */
    allowed: boolean;
    /** Çalıştıramıyorsa hangi izin gerekir — kilit gizlenmez, sebebi yazılır. */
    requiredPermission: string | null;
    scope: { kind: MediaBulkScopeKind; count: number; totalBytes: number };
    /** DONDURULMUŞ liste: çalıştırma bunu aynen geri gönderir. */
    snapshot: { assetIds: number[] };
    applyCount: number;
    batchLimit: number;
    remaining: number;
    skips: { reason: string; count: number }[];
    skippedAssets: { id: number; name: string; reason: string }[];
    impact: {
        reversible: boolean;
        undoWindowDays: number | null;
        newVersion: boolean;
        quotaBytesUsed: number;
        quotaBytesLimit: number;
        /** Yalnız kalıcı silmede dolu: diğerlerinde tahmin yazmayız. */
        quotaBytesFreed: number | null;
    };
    confirmation: { required: boolean; word: string | null };
};

export type MediaBulkResult = {
    id: number;
    name: string;
    status: 'ok' | 'skip' | 'error';
    reason: string | null;
};

export type MediaBulkRunReport = {
    operationKey: string;
    action: MediaBulkActionKey;
    replayed: boolean;
    applied: number;
    skipped: number;
    failed: number;
    remaining: number;
    results: MediaBulkResult[];
};

type TranslationKey = Parameters<typeof t>[0];

export type MediaBulkActionMeta = {
    action: MediaBulkActionKey;
    labelKey: TranslationKey;
    descriptionKey: TranslationKey;
    /** Geri alınabilir mi — sunucudaki `MediaBulkAction::isReversible()` ile aynı. */
    reversible: boolean;
};

export type MediaBulkActionGroup = {
    key: 'improve' | 'organize' | 'remove';
    labelKey: TranslationKey;
    actions: MediaBulkActionMeta[];
};

/**
 * Kaynağın ÜÇ başlığı korunur ("Dosyayı iyileştir", "Düzenle ve taşı",
 * "Kaldır"), içleri ise yalnız GERÇEKTEN yapılabilen eylemleri taşır.
 *
 * Kaynakta olup burada olmayanlar ve sebepleri sunucudaki
 * `App\Domain\Media\MediaBulkAction` başlığında tek tek yazılıdır:
 * `regen` (optimize ile aynı iş), `alt` (üretici yok), `tag` (etiket
 * kavramı yok), `access` (görünürlük hiçbir yerde okunmuyor), `archive`
 * (arşiv hiçbir listelemeyi süzmüyor). Beşi de kaynağa geri dönebilir;
 * her biri kendi veri katmanını getirdiğinde.
 */
export const BULK_ACTION_GROUPS: MediaBulkActionGroup[] = [
    {
        key: 'improve',
        labelKey: 'workspace.media.bulk.group.improve',
        actions: [
            {
                action: 'optimize',
                labelKey: 'workspace.media.bulk.action.optimize',
                descriptionKey: 'workspace.media.bulk.action.optimize.description',
                reversible: true,
            },
            {
                action: 'convert',
                labelKey: 'workspace.media.bulk.action.convert',
                descriptionKey: 'workspace.media.bulk.action.convert.description',
                reversible: true,
            },
        ],
    },
    {
        key: 'organize',
        labelKey: 'workspace.media.bulk.group.organize',
        actions: [
            {
                action: 'move',
                labelKey: 'workspace.media.bulk.action.move',
                descriptionKey: 'workspace.media.bulk.action.move.description',
                reversible: true,
            },
        ],
    },
    {
        key: 'remove',
        labelKey: 'workspace.media.bulk.group.remove',
        actions: [
            {
                action: 'trash',
                labelKey: 'workspace.media.bulk.action.trash',
                descriptionKey: 'workspace.media.bulk.action.trash.description',
                reversible: true,
            },
            {
                action: 'purge',
                labelKey: 'workspace.media.bulk.action.purge',
                descriptionKey: 'workspace.media.bulk.action.purge.description',
                reversible: false,
            },
        ],
    },
];

const SKIP_REASON_KEYS: Record<string, TranslationKey> = {
    quarantine: 'workspace.media.bulk.skip.quarantine',
    'legal-hold': 'workspace.media.bulk.skip.legalHold',
    'published-usage': 'workspace.media.bulk.skip.publishedUsage',
    'unsupported-format': 'workspace.media.bulk.skip.unsupportedFormat',
    'already-done': 'workspace.media.bulk.skip.alreadyDone',
    'not-in-trash': 'workspace.media.bulk.skip.notInTrash',
};

/**
 * Sunucunun sebep kodundan sahibin okuyacağı cümleye.
 *
 * Tanınmayan bir kod SESSİZ kalmaz: sunucu bir gün yeni bir sebep
 * eklediğinde ekran boş bir satır değil, "sunucu bir sebep bildirdi ama
 * bu sürüm onu tanımıyor" diyen dürüst bir yedek gösterir.
 */
export function skipReasonKey(reason: string): TranslationKey {
    return SKIP_REASON_KEYS[reason] ?? 'workspace.media.bulk.skip.unknown';
}

const FAILURE_REASON_KEYS: Record<string, TranslationKey> = {
    'reprocess-failed': 'workspace.media.bulk.failure.reprocess',
    'convert-failed': 'workspace.media.bulk.failure.convert',
    'move-failed': 'workspace.media.bulk.failure.move',
};

export function failureReasonKey(reason: string | null): TranslationKey {
    return FAILURE_REASON_KEYS[reason ?? ''] ?? 'workspace.media.bulk.failure.unknown';
}

/**
 * İş çalıştırılabilir mi?
 *
 * İki kapı: uygulanacak EN AZ BİR dosya (sıfır dosyalık bir işi
 * başlatmak, sahibi "çalıştı mı?" diye ekranın önünde bekletirdi) ve —
 * gerekiyorsa — TAM YAZILMIŞ onay kelimesi.
 *
 * Karşılaştırma harf duyarlıdır. Esneklik tanımak, "kalıcı sil" yazan
 * birinin bin dosyayı kaybetmesi demek olurdu; kelimeyi büyük harfe
 * çevirerek "yardım etmek" ise Türkçede zaten çalışmaz (`'i'` büyük hâli
 * `'İ'` değil `'I'` olur).
 */
export function confirmSatisfied(plan: MediaBulkPlan, typed: string): boolean {
    if (plan.applyCount === 0) {
        return false;
    }

    if (!plan.confirmation.required) {
        return true;
    }

    return typed.trim() === plan.confirmation.word;
}

/**
 * Sonuç sayaçları ve "yalnız hatalıları yeniden dene" için kimlikler.
 *
 * Başarılı olanlara ikinci kez dokunmak yeni bir sürüm daha açardı; o
 * yüzden yeniden deneme HATALI kimliklerle sınırlıdır.
 */
export function groupResults(results: MediaBulkResult[]): {
    all: number;
    ok: number;
    skip: number;
    error: number;
    errorIds: number[];
} {
    return {
        all: results.length,
        ok: results.filter((one) => one.status === 'ok').length,
        skip: results.filter((one) => one.status === 'skip').length,
        error: results.filter((one) => one.status === 'error').length,
        errorIds: results.filter((one) => one.status === 'error').map((one) => one.id),
    };
}

/**
 * Sonuç raporunun CSV hâli — kaynağın "CSV indir" düğmesi.
 *
 * Ayraç NOKTALI VİRGÜLDÜR ve bu Türkçe bir zorunluluktur: Excel'in
 * Türkçe yerelinde liste ayracı `;`dir ve virgülle üretilen dosya tek
 * sütuna yapışık açılır. Ondalık ayracı virgül olan bir yerelde virgülü
 * ayraç yapmak, dosyayı ilk açan kişinin sorunudur.
 */
export function resultsToCsv(header: string[], results: MediaBulkResult[]): string {
    const escape = (value: string): string =>
        /[";\n]/.test(value) ? `"${value.replace(/"/g, '""')}"` : value;

    const lines = [header.map(escape).join(';')];

    for (const row of results) {
        lines.push(
            [String(row.id), escape(row.name), row.status, escape(row.reason ?? '')].join(';'),
        );
    }

    return lines.join('\n');
}
