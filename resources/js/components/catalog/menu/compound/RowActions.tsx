import type { ReactNode } from 'react';
import { ArrowDown, ArrowUp, DotsThree, Trash } from '@phosphor-icons/react';
import { ActionMenu } from '../../overlays/compound/ActionMenu';
import { TooltipHint } from '../../overlays/compound/TooltipHint';

export type RowActionsExtraItem = {
    key: string;
    label: string;
    onSelect: () => void;
    icon?: ReactNode;
    disabled?: boolean;
};

export type RowActionsProps = {
    onDelete: () => void;
    onMoveUp: () => void;
    onMoveDown: () => void;
    deleteLabel: string;
    upLabel: string;
    downLabel: string;
    /** Taşma menüsünün erişilebilir adı: "Kaymakam için diğer işlemler". */
    moreLabel: string;
    /** Menüdeki silme satırının görünen metni. */
    deleteText: string;
    /**
     * Silmeden ÖNCE gelen seyrek işler (alerjen, fotoğraf, gizle).
     *
     * Satırda kalıcı düğme olarak durduklarında dokuz kontrollük bir duvar
     * oluşuyordu; menüde ADLARIYLA duruyorlar ve acemi kullanıcı için bir
     * simgeyi tahmin etmekten iyisi budur.
     */
    extraItems?: RowActionsExtraItem[];
};

/**
 * Bir menü satırının işletme eylemleri — `docs/73` (P0-01).
 *
 * 2026-09-04'te yeniden düzenlendi (sahibin bildirimi: "burası atıl kalmış").
 * Üç şey değişti ve üçünün de gerekçesi aynı satırda görülebiliyordu:
 *
 *   1. **Simgeler artık METİN KARAKTERİ değil.** `↑ ↓ ✎ ✕` yazı tipinin
 *      içinden geliyordu: her sistemde başka boyutta, başka kalınlıkta ve
 *      başka temel çizgide çiziliyor, düğmeler hizasız görünüyordu.
 *   2. **Yeniden adlandırma buradan kalktı.** Ad artık durduğu yerde
 *      düzenleniyor (`InlineRename`); ayrı bir kalem düğmesi, aynı işi
 *      ikinci kez sunmak olurdu.
 *   3. **SİLME, TAŞIMANIN YANINDAN ALINDI.** "Aşağı taşı" ile "sil" yan yana
 *      duran iki küçük hedefti; yanlış tıklama geri alınamaz bir kayıptı.
 *      Silme artık taşma menüsünde ve iki adım uzakta.
 *
 * Sürükle-bırak yerine yukarı/aşağı: sürükleme dokunmatik ekranda ve
 * klavyeyle güvenilir değildir ve ayrı bir erişilebilirlik sözleşmesi ister.
 *
 * KATMAN: bileşen `micro`'dan `compound`'a taşındı, çünkü artık bir compound
 * (`ActionMenu`) besteliyor. Micro'nun compound import etmesi yukarı doğru
 * bir bağımlılıktır ve katman kuralı onu yasaklar — kural haklı: taban
 * parçalar üstlerini tanımaya başlarsa katmanlar birbirine karışır.
 */
export function RowActions({
    onDelete,
    onMoveUp,
    onMoveDown,
    deleteLabel,
    upLabel,
    downLabel,
    moreLabel,
    deleteText,
    extraItems,
}: RowActionsProps) {
    /*
        Simge düğmeleri METİN TAŞIR (`aria-label`): bir ok simgesi neyin
        taşındığını söylemez ve ekran okuyucu kullanan biri listedeki beş
        "yukarı" düğmesini birbirinden ayırt edemez.
    */
    const base =
        'flex min-h-[var(--density-hit-area-min)] min-w-[var(--density-hit-area-min)] items-center justify-center rounded-[var(--radius-md)] text-fg-muted hover:bg-surface-hover hover:text-fg focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-focus';

    return (
        /*
            SAĞA YASLI. Dar ekranda satır kırılıyor ve eylem kümesi kendi
            satırına düşüyor; sola yaslı kaldığında adın altında başıboş
            duruyor ve hangi satıra ait olduğu okunmuyordu. Sağ kenara
            yaslanınca kırılma kasıtlı görünür ve küme hep aynı yerde durur.
        */
        <span className="ms-auto flex shrink-0 items-center gap-[var(--space-1)]">
            {/*
                İPUCU, ekran okuyucunun zaten duyduğu şeyi GÖZE de söyler.

                `aria-label` doğruydu ama yalnız ekran okuyucuya ulaşıyordu;
                `docs/101`'in acemi kullanıcısı için bir ok simgesi hâlâ
                tahmin gerektiriyordu. Tooltip üzerine gelince ve klavye
                odağında görünür — ikisi de aynı sözü verir.
            */}
            <TooltipHint content={upLabel}>
                <button type="button" aria-label={upLabel} className={base} onClick={onMoveUp}>
                    <ArrowUp size={16} weight="bold" aria-hidden="true" />
                </button>
            </TooltipHint>
            <TooltipHint content={downLabel}>
                <button type="button" aria-label={downLabel} className={base} onClick={onMoveDown}>
                    <ArrowDown size={16} weight="bold" aria-hidden="true" />
                </button>
            </TooltipHint>
            <ActionMenu
                label={moreLabel}
                tone="quiet"
                className={base}
                triggerContent={<DotsThree size={18} weight="bold" aria-hidden="true" />}
                items={[
                    ...(extraItems ?? []).map((item) => ({
                        key: item.key,
                        label: item.label,
                        icon: item.icon,
                        disabled: item.disabled,
                        onSelect: item.onSelect,
                    })),
                    {
                        key: 'delete',
                        label: deleteText,
                        icon: <Trash size={18} />,
                        destructive: true,
                        onSelect: onDelete,
                    },
                ]}
            />
            {/*
                Silmenin TAM adı (hangi ürün) menü satırında değil, burada
                duyurulur: menü satırı listede kısa kalmalı, ekran okuyucu ise
                neyi sildiğini bilmeli.
            */}
            <span className="sr-only">{deleteLabel}</span>
        </span>
    );
}

export default RowActions;
