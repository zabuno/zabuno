import { TextAa } from '@phosphor-icons/react';

import { t } from '../../../../i18n/workspace';
import { TextInput } from '../../../catalog/forms/micro/TextInput';
import { QrSelectedMark } from './QrPrintControls';
import { QrStepSection } from './QrStepSection';
import { QR_CARD_THEMES, type QrPrintPlan } from './qrPrintPlan';

/**
 * Şeritteki maketin renkleri — `App\Support\QrDestination\QrCardSvg` ile aynı
 * kararlar, kaynağın kendi değerleri.
 *
 * Bunlar TEMA JETONU DEĞİL, BASKI GERÇEĞİDİR ve o yüzden ham yazılırlar: kart
 * beyaz kâğıda basılır, koyu tasarımın zemini `#0D0A24`'tür ve karekod ISO/IEC
 * 18004 gereği her tasarımda koyu modül / açık zemindir. Jetona bağlansalardı
 * koyu arayüz temasında maket, sahibin eline hiç geçmeyecek bir kartı
 * gösterirdi.
 *
 * "Markalı" ve "Tabela" markanın rengini kullanır; renk yoksa maket de onu
 * uydurmaz ve mürekkep siyahına düşer — sunucunun `accentColor` kuralının
 * aynısı.
 */
const SWATCH_INK = '#080616';
const SWATCH_PAPER = '#FFFFFF';
const SWATCH_DARK = '#0D0A24';
const SWATCH_FALLBACK = '#111111';

type QrPrintLookStepProps = {
    plan: QrPrintPlan;
    onChange: (patch: Partial<QrPrintPlan>) => void;
    /** Markanın ana rengi; yoksa maket mürekkep siyahına düşer. */
    brandPrimaryColor?: string | null;
    /** Marka rengini düzeltmenin yolu: Ayarlar'ın marka sekmesi. */
    onEditBrand?: () => void;
};

/**
 * 3 · NASIL GÖRÜNSÜN? — panel v3.1 kanonik kaynağı.
 *
 * Kaynağın beş tasarımı: Sade · Çerçeve · Markalı · Koyu · Tabela. İlk üçü
 * üründe zaten vardı; son ikisi bu pakette doğdu (`CardTheme::Dark`,
 * `CardTheme::Signage`).
 *
 * KOYU BİR KEZ REDDEDİLMİŞTİ ve red doğruydu: eski kaynak KODUN KENDİSİNİ ters
 * çeviriyordu (beyaz modül / siyah zemin) ve ters basılan bir kod birçok
 * telefonda hiç okunmaz. Panel v3.1 o kusuru kendi düzeltti — `koyu` ve
 * `tabela` temalarının kod çifti hâlâ koyu modül / açık zemin. Koyulaşan şey
 * kartın zemini. Reddin sebebi ortadan kalktığı için tasarımlar doğdu.
 */
export function QrPrintLookStep({
    plan,
    onChange,
    brandPrimaryColor = null,
    onEditBrand,
}: QrPrintLookStepProps) {
    const brand = brandPrimaryColor ?? SWATCH_FALLBACK;

    function swatch(theme: (typeof QR_CARD_THEMES)[number]['key']) {
        switch (theme) {
            case 'dark':
                return { ground: SWATCH_DARK, ink: SWATCH_PAPER, band: null };
            case 'signage':
                return { ground: brand, ink: SWATCH_PAPER, band: null };
            case 'banner':
                return { ground: SWATCH_PAPER, ink: SWATCH_INK, band: brand };
            default:
                return { ground: SWATCH_PAPER, ink: SWATCH_INK, band: null };
        }
    }

    return (
        <QrStepSection
            step={3}
            title={t('workspace.publication.qrScreen.step3')}
            aside={t('workspace.publication.qrScreen.brandNote')}
        >
            {/*
                YATAY KAYAN ŞERİT. Beş maket 320 pikselde alt alta düşseydi adım
                bir ekran boyu uzardı ve sahip üçüncü adımı hiç görmezdi.
            */}
            <div className="flex gap-[var(--space-2)] overflow-x-auto pb-[var(--space-1)]">
                {QR_CARD_THEMES.map((theme) => {
                    const selected = plan.theme === theme.key;
                    const colors = swatch(theme.key);

                    return (
                        <button
                            key={theme.key}
                            type="button"
                            aria-pressed={selected}
                            onClick={() => onChange({ theme: theme.key })}
                            className={[
                                'flex w-[7rem] shrink-0 flex-col items-center gap-[var(--space-2)]',
                                'rounded-[var(--radius-lg)] border-2 p-[var(--space-2)]',
                                'transition-colors duration-[var(--duration-fast)] ease-[var(--easing-standard)]',
                                'focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-focus',
                                selected ? 'border-action' : 'border-border hover:bg-surface-hover',
                            ].join(' ')}
                        >
                            {/*
                                MAKET, ÖNİZLEME DEĞİLDİR. Gerçek kart sağdaki
                                panelde sunucudan geliyor; buradaki kutu yalnız
                                "hangisi hangisi" sorusunu yanıtlar. Bir sayı ya
                                da ölçü YAZMAZ — yazsaydı ikinci bir gerçek
                                kaynağı olurdu.
                            */}
                            <span
                                aria-hidden="true"
                                className="relative flex w-full flex-col items-center justify-center gap-[2px] overflow-hidden rounded-[var(--radius-sm)] border border-border p-[var(--space-2)]"
                                style={{ aspectRatio: '3 / 4', background: colors.ground }}
                            >
                                {colors.band ? (
                                    <span
                                        className="absolute inset-x-0 top-0 h-[16%]"
                                        style={{ background: colors.band }}
                                    />
                                ) : null}
                                {/* Kod her tasarımda koyu modül / açık zemin. */}
                                <span
                                    className="w-[46%] rounded-[2px]"
                                    style={{
                                        aspectRatio: '1',
                                        backgroundColor: SWATCH_PAPER,
                                        backgroundImage: `linear-gradient(90deg, ${SWATCH_INK} 1px, transparent 1px), linear-gradient(${SWATCH_INK} 1px, transparent 1px)`,
                                        backgroundSize: '4px 4px',
                                    }}
                                />
                                <span
                                    className="h-[3px] w-[58%] rounded-[2px] opacity-50"
                                    style={{ background: colors.ink }}
                                />
                                <span
                                    className="h-[3px] w-[36%] rounded-[2px] opacity-30"
                                    style={{ background: colors.ink }}
                                />
                            </span>

                            <span className="flex items-center gap-[var(--space-1)]">
                                <span
                                    className={
                                        selected
                                            ? 'text-body font-bold text-fg'
                                            : 'text-body font-medium text-fg'
                                    }
                                >
                                    {t(theme.labelKey)}
                                </span>
                                <QrSelectedMark selected={selected} />
                            </span>
                        </button>
                    );
                })}
            </div>

            {/*
                MARKA RENGİ YOKSA SÖYLENİR. "Markalı" ve "Tabela" seçili olduğu
                hâlde renk kurulmamışsa sunucu sessizce mürekkep siyahına düşer;
                sessizlik, sahibin markalı bir kart beklerken siyah bir kart
                indirmesi ve bunu bir hata sanması demek olurdu.
            */}
            {(plan.theme === 'banner' || plan.theme === 'signage') && brandPrimaryColor === null ? (
                <p
                    role="status"
                    className="flex flex-col items-start gap-[var(--space-1)] text-meta text-fg-secondary"
                >
                    {t('workspace.publication.qrExport.themes.brandMissing')}
                    {onEditBrand ? (
                        <button
                            type="button"
                            onClick={onEditBrand}
                            className="min-h-[var(--density-hit-area-min)] text-meta text-fg-link underline underline-offset-2"
                        >
                            {t('workspace.publication.qrExport.themes.editBrand')}
                        </button>
                    ) : null}
                </p>
            ) : null}

            <details className="rounded-[var(--radius-md)] border border-border">
                <summary className="flex min-h-[var(--density-hit-area-min)] cursor-pointer list-none items-center gap-[var(--space-2)] px-[var(--space-3)] text-body font-medium text-fg-secondary">
                    <TextAa size={18} weight="regular" aria-hidden="true" />
                    {t('workspace.publication.qrScreen.wording')}
                </summary>
                <div className="flex flex-col gap-[var(--space-2)] px-[var(--space-3)] pb-[var(--space-3)]">
                    <label className="flex flex-col gap-[var(--space-1)] text-body font-medium text-fg-secondary">
                        {t('workspace.publication.qrScreen.headline.label')}
                        {/*
                            Kartın cümlesi RESTORANIN cümlesidir. Boş
                            bırakılırsa sunucu misafir alanındaki hazır cümleyi
                            basar — uydurulmuş bir yer tutucu değil. Sınır 60
                            karakter, çünkü sunucunun doğrulaması o
                            (`ExportQrCardController`); ekranda başka bir sayı
                            yazsaydı sahip 61. karakteri yazar ve isteği ancak
                            indirme anında reddedilirdi.
                        */}
                        <TextInput
                            type="text"
                            maxLength={60}
                            value={plan.headline}
                            placeholder={t('workspace.publication.qrCard.headline.default')}
                            onChange={(event) => onChange({ headline: event.target.value })}
                        />
                        <span className="text-meta text-fg-muted">
                            {t('workspace.publication.qrScreen.headline.help')}
                        </span>
                    </label>

                    {/*
                        ÇİZİLMEYEN AÇMA/KAPAMALAR. Kaynak burada üç anahtar
                        gösteriyor (logo, masa numarası, adresi de yaz).
                        Sunucunun kart bestecisinde bu üçünün AYARI yok: logo
                        marka logosu varsa basılır, masa adı artık her kartta
                        basılır, adres hiç basılmaz. Var olmayan bir anahtarı
                        çizmek, kapatınca hiçbir şey değişmeyen bir düğme
                        vermek olurdu.
                    */}
                    <p className="text-meta text-fg-muted">
                        {t('workspace.publication.qrScreen.tableNumberPrinted')}
                    </p>
                    <p className="text-meta text-fg-muted">
                        {t('workspace.publication.qrScreen.wording.scope')}
                    </p>
                </div>
            </details>
        </QrStepSection>
    );
}
