export type RowActionsProps = {
    onRename: () => void;
    onDelete: () => void;
    onMoveUp: () => void;
    onMoveDown: () => void;
    renameLabel: string;
    deleteLabel: string;
    upLabel: string;
    downLabel: string;
};

/**
 * Bir menü satırının işletme eylemleri — `docs/73` (P0-01).
 *
 * Dördü de METİN taşır (`aria-label`), yalnız simge değil: bir çöp kutusu
 * simgesi neyin silineceğini söylemez ve ekran okuyucu kullanıcısı listedeki
 * beş "sil" düğmesini birbirinden ayırt edemez.
 *
 * Sürükle-bırak yerine yukarı/aşağı: sürükleme dokunmatik ekranda ve
 * klavyeyle güvenilir değildir ve ayrı bir erişilebilirlik sözleşmesi ister.
 */
export function RowActions({
    onRename,
    onDelete,
    onMoveUp,
    onMoveDown,
    renameLabel,
    deleteLabel,
    upLabel,
    downLabel,
}: RowActionsProps) {
    const base =
        'min-h-[var(--density-hit-area-min)] min-w-[var(--density-hit-area-min)] rounded-md px-2 text-meta text-fg-secondary hover:bg-surface-hover';

    return (
        <span className="flex shrink-0 items-center gap-1">
            <button type="button" aria-label={upLabel} className={base} onClick={onMoveUp}>
                ↑
            </button>
            <button type="button" aria-label={downLabel} className={base} onClick={onMoveDown}>
                ↓
            </button>
            <button type="button" aria-label={renameLabel} className={base} onClick={onRename}>
                ✎
            </button>
            <button
                type="button"
                aria-label={deleteLabel}
                className={`${base} hover:text-fg-danger`}
                onClick={onDelete}
            >
                ✕
            </button>
        </span>
    );
}

export default RowActions;
