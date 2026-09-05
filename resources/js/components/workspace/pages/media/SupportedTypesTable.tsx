import { formatBytes } from './mediaFormat';
import { SUPPORTED_TYPE_GROUPS, type SupportedTypeGroup } from './supportedTypes';
import { maxBytesForKind, type UploadLimits } from './uploadSizeLimits';
import { wizardText } from './uploadWizardCopy';

type SupportedTypesTableProps = {
    /** `<input accept>` değeri; hangi grupların çizileceğini bu belirler. */
    accept: string;
    /**
     * Sunucunun bildirdiği sınırlar; henüz gelmediyse `null`.
     *
     * FF-158'e kadar burada TEK bir sayı vardı ve tablonun her satırında
     * aynı değer yazıyordu. Sunucu artık türe göre sınır uyguluyor; tek
     * sayı yazmaya devam etmek, satırların en az birini yalan yapardı.
     */
    limits: UploadLimits | null;
};

/** `image/*` → `image/` ile başlayan gruplar. */
function acceptsGroup(accept: string, group: SupportedTypeGroup): boolean {
    return accept
        .split(',')
        .map((entry) => entry.trim())
        .some((entry) => entry === '*/*' || entry.startsWith(group.mimePrefix));
}

/**
 * Desteklenen türler tablosu (kanonik kaynak: "Yükle" ekranı, 1. adım).
 *
 * Dört sütun birlikte anlam taşır: TÜR neyi yükleyebileceğini, AZAMİ BOYUT
 * neyin reddedileceğini, UZANTILAR telefondaki dosyanın hangisi olduğunu,
 * NOT ise dosyaya ne olacağını söyler ("HEIC telefonda JPEG'e çevrilir").
 * Notu atmak, sahibin iPhone fotoğrafının neden adının değiştiğini
 * anlamamasına yol açar.
 *
 * Azami boyut SUNUCUDAN gelir ve TÜRE göredir (FF-158): görselin sınırıyla
 * bir SVG'nin sınırı aynı değildir. Sabit yazılsaydı — ya da tek bir sayı
 * bütün satırlara dağıtılsaydı — ekrandaki söz ile kapının uyguladığı kural
 * ayrışır ve sahip bunu ancak reddedildikten sonra öğrenirdi.
 */
export function SupportedTypesTable({ accept, limits }: SupportedTypesTableProps) {
    const groups = SUPPORTED_TYPE_GROUPS.filter((group) => acceptsGroup(accept, group));

    /*
        Sunucunun bu tür için sınırı yoksa (`limitKind: null`) ya da cevap
        henüz gelmediyse kaynağın broşür değeri yazılır. Mutlak tavana
        DÜŞÜLMEZ: tanınmayan bir türe tavanı yazmak, kabul edilmeyen bir tür
        için sınır ilan etmek olurdu.
    */
    const largestFor = (group: SupportedTypeGroup): number =>
        limits !== null && group.limitKind !== null
            ? maxBytesForKind(limits, group.limitKind)
            : group.fallbackMaxBytes;

    if (groups.length === 0) {
        return null;
    }

    return (
        <div className="overflow-x-auto rounded-[var(--radius-lg)] border border-border">
            <table
                aria-label={wizardText('workspace.media.upload.supported.heading')}
                className="w-full border-collapse text-start"
            >
                <thead>
                    <tr className="bg-surface-subtle">
                        <th scope="col" className="p-[var(--space-2)] text-body font-bold text-fg">
                            {wizardText('workspace.media.upload.supported.column.type')}
                        </th>
                        <th scope="col" className="p-[var(--space-2)] text-body font-bold text-fg">
                            {wizardText('workspace.media.upload.supported.column.max')}
                        </th>
                        <th scope="col" className="p-[var(--space-2)] text-body font-bold text-fg">
                            {wizardText('workspace.media.upload.supported.column.extensions')}
                        </th>
                        <th scope="col" className="p-[var(--space-2)] text-body font-bold text-fg">
                            {wizardText('workspace.media.upload.supported.column.note')}
                        </th>
                    </tr>
                </thead>
                <tbody>
                    {groups.map((group) => (
                        <tr key={group.key} className="border-t border-border align-top">
                            <th
                                scope="row"
                                className="p-[var(--space-2)] text-body font-medium text-fg"
                            >
                                {wizardText(`workspace.media.upload.supported.${group.key}`)}
                            </th>
                            {/* Bir ÖLÇÜ: `text-meta` ve hizalı rakam tam olarak bunun içindir. */}
                            <td className="p-[var(--space-2)] text-meta text-fg-muted tabular-nums">
                                {formatBytes(largestFor(group))}
                            </td>
                            <td className="p-[var(--space-2)]">
                                <ul className="flex flex-wrap gap-[var(--space-1)]">
                                    {group.extensions.map((extension) => (
                                        <li
                                            key={extension}
                                            className="rounded-pill border border-border px-[var(--space-2)] text-meta text-fg-secondary"
                                        >
                                            {extension}
                                        </li>
                                    ))}
                                </ul>
                            </td>
                            <td className="p-[var(--space-2)] text-body text-fg-muted">
                                {wizardText(`workspace.media.upload.supported.${group.key}.note`)}
                            </td>
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    );
}

export default SupportedTypesTable;
