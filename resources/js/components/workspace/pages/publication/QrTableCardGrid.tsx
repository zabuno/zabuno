import { useState } from 'react';
import { Scan } from '@phosphor-icons/react';

import { t } from '../../../../i18n/workspace';
import type { QrCodeItem } from './qr-destination/QrCodeListItem';

/**
 * Ekranın bildiği bir kod: liste ucunun kaydı + TARAMA SAYISI.
 *
 * `scanCount` üç ayrı şey söyleyebilir ve üçü de farklıdır:
 *   - bir sayı → o kod o kadar kez okutuldu,
 *   - `0` → okutulmadı (bu bir cevaptır, boşluk değil),
 *   - `null`/tanımsız → ölçüm bize KAPALI (plan bu yeteneği içermiyor).
 *
 * Tip liste satırının kendi tipini genişletir, onu değiştirmez: kart destesi
 * ve satır listesi aynı kaydı okur, ayrışmaları gereken bir gün yoktur.
 */
export type QrScreenCode = QrCodeItem & { scanCount?: number | null };

/**
 * Tek karenin önizlemesi — ve ÜRETİLEMEDİĞİNDE ne olacağı.
 *
 * Görselin ucu `qr.design.manage` yetkisi ister
 * (`ExportQrCodeSvgController`). Yalnız `qr.view` yetkisi olan bir kullanıcı —
 * mutfak rolü gibi — kırk karenin kırkında da tarayıcının kırık resim
 * simgesini görürdü: sayfadaki her şeyin bir hâli varken, sahibin buraya gelme
 * sebebi olan şeyin hâli yoktu.
 *
 * Durum KART BAŞINA tutulur: bir kod için başarısız olan istek, komşusunun
 * çalışan önizlemesini karartmaz.
 */
function QrThumbnail({ workspaceId, qrCodeId }: { workspaceId: number; qrCodeId: number }) {
    const [failed, setFailed] = useState(false);

    return (
        /*
            ZEMİN JETON DEĞİL KÂĞITTIR (`bg-white`): karekod ISO/IEC 18004
            gereği koyu modül / açık zemin olmak zorundadır ve `surface`
            jetonuna bağlansaydı koyu arayüz temasında koyu bir kâğıt
            görünürdü.
        */
        <span className="flex aspect-square items-center justify-center rounded-[var(--radius-md)] border border-border bg-white p-[var(--space-2)]">
            {failed ? (
                <span className="text-center text-meta text-fg-danger">
                    {t('workspace.publication.qrExport.preview.failed')}
                </span>
            ) : (
                <img
                    src={`/api/workspaces/${String(workspaceId)}/qr-codes/${String(qrCodeId)}/export.svg`}
                    alt=""
                    /*
                        Süslemedir, bilgi taşımaz: kodun ne olduğu zaten
                        adında ve adresinde yazıyor. Boş `alt` ekran
                        okuyucuyu kırk kez "QR kod önizlemesi" demekten
                        kurtarır.
                    */
                    aria-hidden="true"
                    loading="lazy"
                    decoding="async"
                    onError={() => setFailed(true)}
                    className="h-full w-full"
                />
            )}
        </span>
    );
}

type QrTableCardGridProps = {
    codes: QrScreenCode[];
    selectedId: number | null;
    onSelect: (qrCodeId: number) => void;
};

/**
 * Kodun İNSAN ADI. Masaya bağlı olmayan kod "giriş kodu"dur; 43 karakterlik
 * token bir ad değildir — sahip onunla hiçbir masayı bulamaz.
 */
function codeName(code: QrScreenCode): string {
    if (code.tableName) {
        return code.areaLabel ? `${code.tableName} · ${code.areaLabel}` : code.tableName;
    }

    return t('workspace.publication.qrDestination.item.entrance');
}

/**
 * MASA KARTLARI IZGARASI — panel v3 kanonik kaynağı (`docs/109` §6.7).
 *
 * Kaynak burada bir liste değil bir IZGARA çiziyor ve bu bir düzen tercihi
 * değil bir iş kararı: kırk masalı bir restoranın sahibi "hangi masa hiç
 * okutulmamış" sorusunu kırk satır okuyarak yanıtlayamaz. Kareler yan yana
 * durunca sıfır olan tek kare göze kendiliğinden çarpar.
 *
 * SIFIR RENKLE ANLATILMAZ. Kaynak sıfırı kırmızıya boyuyor; renk tek başına
 * bir işaret değildir (WCAG 2.2 §1.4.1) ve kırmızı-yeşil ayırt edemeyen bir
 * sahip kırk kare arasında hiçbir fark görmez. İşaret metindir; renk yalnız
 * ona eşlik eder.
 *
 * ÖNİZLEME ZEMİNİ JETON DEĞİL KÂĞITTIR (`bg-white`). Karekod ISO/IEC 18004
 * gereği koyu modül / açık zemin olmak zorundadır; zemini `surface` jetonuna
 * bağlasaydık koyu arayüz temasında ızgara koyu bir kâğıt gösterirdi — sahip
 * eline hiç geçmeyecek bir kartı görürdü.
 */
export function QrTableCardGrid({ codes, selectedId, onSelect }: QrTableCardGridProps) {
    return (
        <section
            aria-label={t('workspace.publication.qrScreen.tables')}
            /*
                `auto-fill` + `minmax` breakpoint kullanmadan uyum sağlar:
                320 piksellik bir telefonda tek sütun, geniş ekranda altı
                sütun. Sabit sütun sayısı ikisinden birini bozardı.
            */
            className="grid gap-[var(--space-3)]"
            style={{ gridTemplateColumns: 'repeat(auto-fill, minmax(9.5rem, 1fr))' }}
        >
            {codes.map((code) => {
                const selected = code.id === selectedId;
                const measured = typeof code.scanCount === 'number';
                const name = codeName(code);

                return (
                    <button
                        key={code.id}
                        type="button"
                        aria-pressed={selected}
                        onClick={() => onSelect(code.id)}
                        className={[
                            'flex min-h-[var(--control-height)] flex-col items-stretch gap-[var(--space-2)]',
                            'rounded-[var(--radius-lg)] border-2 p-[var(--space-3)] text-start',
                            'transition-colors duration-[var(--duration-fast)] ease-[var(--easing-standard)]',
                            'focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-focus',
                            selected
                                ? 'border-action bg-surface'
                                : 'border-border bg-surface hover:bg-surface-hover',
                        ].join(' ')}
                    >
                        <QrThumbnail workspaceId={code.workspaceId} qrCodeId={code.id} />

                        <span className="flex flex-wrap items-center justify-between gap-[var(--space-1)]">
                            <span className="text-body font-medium text-fg">{name}</span>

                            {measured ? (
                                code.scanCount === 0 ? (
                                    <span className="text-meta text-fg-danger">
                                        {t('workspace.publication.qrScreen.neverScanned')}
                                    </span>
                                ) : (
                                    <span className="inline-flex items-center gap-[var(--space-1)] text-meta tabular-nums text-fg-muted">
                                        <Scan size={14} weight="regular" aria-hidden="true" />
                                        {t('workspace.publication.qrScreen.scans', {
                                            count: String(code.scanCount),
                                        })}
                                    </span>
                                )
                            ) : null}
                        </span>
                    </button>
                );
            })}
        </section>
    );
}

export default QrTableCardGrid;
