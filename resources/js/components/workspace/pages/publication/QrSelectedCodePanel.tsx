import { useState } from 'react';
import { Copy, Check, Info } from '@phosphor-icons/react';

import { t } from '../../../../i18n/workspace';
import { currentLocale } from '../../../../i18n/locales';
import { qrContrastRatio } from '../../../../lib/qrContrast';
import { SegmentedControl } from '../../../catalog/forms/compound/SegmentedControl';
import { ActionLink } from '../../../catalog/navigation/micro/ActionLink';
import { CARD_SIZE_MM } from './QrCardWizard';
import type { QrScreenCode } from './QrTableCardGrid';

/**
 * Kartın TASARIMLARI — `App\Domain\QrDestination\CardTheme` ile aynı dört
 * değer.
 *
 * Kaynak burada üç seçenek gösteriyor: "Klasik · Markalı · Koyu". İlk ikisi
 * üründe gerçekten var; üçüncüsü YOK ve olmaması bir eksiklik değil bir
 * karardır. Kaynağın "Koyu"su karekodu beyaz modül / siyah zemin çiziyor;
 * tarayıcılar koyu-üstüne-açık varsayar (ISO/IEC 18004) ve ters basılan bir
 * kod birçok telefonda hiç okunmaz. "Bazı telefonlarda çalışır" bir özellik
 * değil, bir destek talebidir — ve bunu ilk fark eden kişi telefonunu kartın
 * üstünde sallayan misafirdir.
 *
 * Marka rengi kaybolmuyor: kartın ŞERİDİNE ve ÇERÇEVESİNE uygulanıyor
 * (`ExportQrCardController`: "Kodun kendisi HER ZAMAN klasik"). Yani "Markalı"
 * gerçekten markalıdır, yalnız markayı taranabilirliğin önüne koymaz.
 */
const CARD_THEMES = ['classic', 'minimal', 'banner', 'frame'] as const;

type CardThemeKey = (typeof CARD_THEMES)[number];

const THEME_LABEL_KEYS: Record<CardThemeKey, Parameters<typeof t>[0]> = {
    classic: 'workspace.publication.qrCard.theme.classic',
    minimal: 'workspace.publication.qrCard.theme.minimal',
    banner: 'workspace.publication.qrCard.theme.banner',
    frame: 'workspace.publication.qrCard.theme.frame',
};

/**
 * Bu ekranın ÖLÇÜ listesi — masaya konan kartın ölçüleri.
 *
 * Kaynak "5×5 cm · 8×8 cm · A6 masa kartı" diyor. İlk ikisi karekodun kendi
 * kenarıdır, kartın değil — ve o ölçü bu üründe bir AYAR DEĞİLDİR: baskı
 * sayfası kodu 45 mm çizer (`QrPrintSheet::QR_SIZE_MM`), çünkü 10:1 kuralıyla
 * ~40 cm okuma mesafesi masadaki bir kartın tam mesafesidir. Küçültülebilen
 * bir ölçü, bir gün okunmayan bir kart demektir.
 *
 * Bu yüzden liste KARTIN ölçüsüdür ve üçü de gerçek `CardSize` değerleridir.
 * Afiş boyları (A3/A4/B*) burada değil, gelişmiş baskıdaki kart sihirbazında
 * durur: masaya A3 koyan restoran yoktur.
 */
const SIZES = [
    { key: 'A6', labelKey: 'workspace.publication.qrScreen.sizeTableCard' },
    { key: 'A5', labelKey: 'workspace.publication.qrScreen.sizeTableCard' },
    { key: '1:2', labelKey: 'workspace.publication.qrScreen.sizeStand' },
] as const;

type SizeKey = (typeof SIZES)[number]['key'];

/**
 * KARTIN BASTIĞI RENK ÇİFTİ — ve bu bir seçenek değil bir sabittir.
 *
 * `ExportQrCardController` kodu her zaman `QrTheme::Classic` ile çizer: siyah
 * modül, beyaz zemin. Ekranda yazan oran bu çiftin GERÇEK hesabıdır; elle
 * yazılmış bir sabit, renk bir gün değiştiğinde sessizce yalan söylerdi.
 */
const CARD_QR_FOREGROUND = '000000';
const CARD_QR_BACKGROUND = 'FFFFFF';

type QrSelectedCodePanelProps = {
    code: QrScreenCode;
};

function codeName(code: QrScreenCode): string {
    if (code.tableName) {
        return code.areaLabel ? `${code.tableName} · ${code.areaLabel}` : code.tableName;
    }

    return t('workspace.publication.qrDestination.item.entrance');
}

/**
 * Durum rozeti — ÖLÇÜLEN şeyi söyler, temenni etmez.
 *
 * Kaynak taraması olan masaya "Çalışıyor", olmayana "Hiç taranmadı" diyor ve
 * bu ayrım doğru: bir kodun çalıştığını ancak biri okuttuysa bilirsiniz.
 * Ölçüm kapalıysa üçüncü bir hâl var — "Etkin": kod açıktır ama okunup
 * okunmadığını bilmiyoruz ve bunu bilirmiş gibi yapmıyoruz.
 */
function stateBadge(code: QrScreenCode): { className: string; label: string } {
    /*
        Rozet `catalog/.../Badge` DEĞİL, elle çizilen bir jeton pili.

        Flowbite rozeti ölçekte olmayan bir ağırlık (600) ve ham Tailwind
        palet sınıfları basıyor: AEP ağırlık ölçeğinde 600 yoktur ve ham renk
        tema değiştiğinde jetonlarla birlikte değişmez. O bileşenin borcu
        başka bir paketin işi; bu ekran onu miras almaz.
    */
    /*
        Dokunma hedefi ölçüsü (`--control-height`) BURAYA konmaz: rozet
        tıklanmaz. 44 piksellik bir etiket, yanındaki başlıktan daha çok yer
        kaplar ve okuyucuya "bu bir düğme" der.
    */
    const shell =
        'inline-flex items-center rounded-pill border px-[var(--space-2)] py-[var(--space-1)] text-meta font-medium';

    if (code.state !== 'active') {
        return {
            className: `${shell} border-warning bg-surface-warning text-fg-warning`,
            label: t('workspace.publication.qrScreen.state.disabled'),
        };
    }

    if (code.scanCount === 0) {
        return {
            className: `${shell} border-warning bg-surface-warning text-fg-warning`,
            label: t('workspace.publication.qrScreen.neverScanned'),
        };
    }

    if (typeof code.scanCount === 'number') {
        return {
            className: `${shell} border-success bg-surface-success text-fg-success`,
            label: t('workspace.publication.qrScreen.state.working'),
        };
    }

    return {
        className: `${shell} border-info bg-surface-info text-fg-secondary`,
        label: t('workspace.publication.qrScreen.state.active'),
    };
}

/**
 * SEÇİLİ KODUN PANELİ — panel v3 kanonik kaynağı (`docs/109` §6.7).
 *
 * Izgara "hangi masa" sorusunu yanıtlar; bu panel ikinci soruyu yanıtlar:
 * "bunu nasıl basacağım". Önizleme her ayar değişiminde yeniden çizilir,
 * çünkü yazdırma deneyiminin temel kuralı ayarların bir önizlemeye BAĞLI
 * olmasıdır — sahip "A5 yatay" seçip sonucu ancak yazıcıdan kâğıt çıkınca
 * öğrenmemeli.
 */
export function QrSelectedCodePanel({ code }: QrSelectedCodePanelProps) {
    const [theme, setTheme] = useState<CardThemeKey>('classic');
    const [size, setSize] = useState<SizeKey>('A6');
    const [copied, setCopied] = useState(false);
    const [previewFailed, setPreviewFailed] = useState(false);

    const [width, height] = CARD_SIZE_MM[size];
    const badge = stateBadge(code);
    const name = codeName(code);

    function cardUrl(format: 'svg' | 'pdf', download: boolean): string {
        const params = new URLSearchParams();
        params.set('cardTheme', theme);
        params.set('size', size);
        params.set('orientation', 'portrait');
        if (download) params.set('download', '1');

        return `/api/workspaces/${String(code.workspaceId)}/qr-codes/${String(code.id)}/card.${format}?${params.toString()}`;
    }

    /*
        ORAN HESAPLANIR. `qrContrastRatio` sunucudaki `QrContrast` ile aynı
        WCAG formülünü uygular; siyah/beyaz çifti tam 21,0 verir. Hesap
        yapılamayan bir çift olsaydı satır HİÇ çizilmezdi — ölçülmemiş bir
        oranı yazmak, sahibin kırk kart bastırmasını sağlayan cümledir.
    */
    const ratio = qrContrastRatio(CARD_QR_FOREGROUND, CARD_QR_BACKGROUND);
    const ratioLabel = new Intl.NumberFormat(currentLocale(), {
        minimumFractionDigits: 1,
        maximumFractionDigits: 1,
    }).format(ratio);

    async function copyAddress() {
        try {
            await navigator.clipboard.writeText(code.resolverUrl);
            setCopied(true);
        } catch {
            /*
                Pano API'si olmayan ya da izin vermeyen bir ortamda düğme
                sessizce başarısız olur ve adres zaten yanında seçilebilir
                hâlde durur — kullanıcı elle kopyalayabilir.
            */
            setCopied(false);
        }
    }

    return (
        <aside
            role="region"
            aria-label={t('workspace.publication.qrScreen.selected')}
            className="flex flex-col gap-[var(--space-4)] rounded-[var(--radius-lg)] border border-border bg-surface p-[var(--space-4)]"
        >
            <div className="flex flex-wrap items-center justify-between gap-[var(--space-2)]">
                <h3 className="text-body font-bold text-fg">{name}</h3>
                <span className={badge.className}>{badge.label}</span>
            </div>

            {/*
                ZEMİN JETON DEĞİL, KÂĞITTIR (`bg-white`).

                Kart beyaz kâğıda basılır ve karekod ISO/IEC 18004 gereği koyu
                modül / açık zemin olmak zorundadır. Zemini `surface` jetonuna
                bağlasaydık koyu arayüz temasında önizleme KOYU BİR KÂĞIT
                gösterirdi: sahip eline hiç geçmeyecek bir kartı görür ve
                kontrast kararını yanlış veri üstünden verirdi.
            */}
            {previewFailed ? (
                /*
                    TESLİMATIN DA BİR DURUMU OLMALI. Görselin ucu
                    `qr.design.manage` yetkisi ister (`ExportQrCardController`);
                    yetkisi olmayan bir kullanıcıda burada tarayıcının kırık
                    resim simgesi kalırdı. İndirme bağlantıları durur: görsel
                    üretilememesi, dosyanın üretilemeyeceği anlamına gelmez.
                */
                <p
                    role="status"
                    className="flex max-h-[18rem] items-center rounded-[var(--radius-md)] border border-border bg-surface-subtle p-[var(--space-4)] text-meta text-fg-danger"
                >
                    {t('workspace.publication.qrExport.preview.failed')}
                </p>
            ) : (
                <img
                    key={cardUrl('svg', false)}
                    src={cardUrl('svg', false)}
                    alt={t('workspace.publication.qrScreen.previewAlt', { name })}
                    onError={() => setPreviewFailed(true)}
                    className="max-h-[18rem] w-auto self-start rounded-[var(--radius-md)] border border-border bg-white"
                    style={{ aspectRatio: `${String(width)} / ${String(height)}` }}
                />
            )}

            {/*
                TAM ADRES. Kısaltılmış hâli listede yeterlidir ama burada sahip
                adresi bir yere YAPIŞTIRMAK için bakar — sosyal medya
                biyografisine, tabelaya, menü tasarımcısına. Kesilmiş bir adres
                o işi görmez.
            */}
            <div className="flex flex-wrap items-center gap-[var(--space-2)]">
                <code className="min-w-0 flex-1 break-all rounded-[var(--radius-md)] bg-surface-subtle px-[var(--space-2)] py-[var(--space-1)] text-meta text-fg-secondary">
                    {code.resolverUrl}
                </code>
                <button
                    type="button"
                    onClick={() => void copyAddress()}
                    className="inline-flex min-h-[var(--control-height)] items-center gap-[var(--space-1)] rounded-[var(--radius-md)] border border-border px-[var(--space-2)] text-meta text-fg-secondary hover:bg-surface-hover hover:text-fg focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-focus"
                >
                    {copied ? (
                        <Check size={16} weight="regular" aria-hidden="true" />
                    ) : (
                        <Copy size={16} weight="regular" aria-hidden="true" />
                    )}
                    {t(
                        copied
                            ? 'workspace.publication.qrScreen.copied'
                            : 'workspace.publication.qrScreen.copy',
                    )}
                </button>
            </div>

            <div className="flex flex-col gap-[var(--space-2)]">
                {/*
                    Bölüm ETİKETİ gövde ölçeğindedir. `text-meta` bu sistemde
                    zaman damgası, sayaç ve ölçü içindir; bir kontrolün adı
                    küçüldüğünde sahip neyi seçtiğini okuyamaz.
                */}
                <span className="text-body font-medium text-fg-secondary">
                    {t('workspace.publication.qrScreen.theme')}
                </span>
                <SegmentedControl
                    label={t('workspace.publication.qrScreen.theme')}
                    value={theme}
                    options={CARD_THEMES.map((key) => ({
                        value: key,
                        label: t(THEME_LABEL_KEYS[key]),
                    }))}
                    onChange={setTheme}
                />
            </div>

            <div className="flex flex-col gap-[var(--space-2)]">
                <span className="text-body font-medium text-fg-secondary">
                    {t('workspace.publication.qrScreen.size')}
                </span>
                {/*
                    MİLİMETRE EKRANDA YAZAR. "A6" bir restoran sahibine hiçbir
                    şey anlatmaz; "105 × 148 mm" pleksiglas standın içine girip
                    girmeyeceğini anlatır.
                */}
                <SegmentedControl
                    label={t('workspace.publication.qrScreen.size')}
                    value={size}
                    options={SIZES.map((option) => {
                        const [optionWidth, optionHeight] = CARD_SIZE_MM[option.key];

                        return {
                            value: option.key,
                            label: t('workspace.publication.qrScreen.sizeOption', {
                                name: t(option.labelKey),
                                width: String(optionWidth),
                                height: String(optionHeight),
                            }),
                        };
                    })}
                    onChange={setSize}
                />
            </div>

            <div className="flex flex-wrap gap-[var(--space-2)]">
                <ActionLink href={cardUrl('pdf', true)}>
                    {t('workspace.publication.qrScreen.downloadPdf')}
                </ActionLink>
                <ActionLink
                    variant="secondary"
                    href={cardUrl('pdf', false)}
                    target="_blank"
                    rel="noopener noreferrer"
                >
                    {t('workspace.publication.qrScreen.print')}
                </ActionLink>
            </div>

            <p className="flex items-start gap-[var(--space-1)] text-meta text-fg-muted">
                <Info size={16} weight="regular" aria-hidden="true" className="shrink-0" />
                <span className="tabular-nums">
                    {t('workspace.publication.qrScreen.contrast', { ratio: ratioLabel })}
                </span>
            </p>

            {/*
                ÇİZİLMEYEN SEÇENEĞİN SEBEBİ YAZILIR. Sessizce eksiltmek,
                kullanıcıya ürünün eksik olduğunu düşündürür; sebebi yazmak
                bir kararı görünür kılar.
            */}
            <p className="text-meta text-fg-muted">
                {t('workspace.publication.qrScreen.noDarkTheme')}
            </p>
        </aside>
    );
}

export default QrSelectedCodePanel;
