export type RankBarRow = {
    id: string | number;
    label: string;
    value: number;
};

export type RankBarListProps = {
    rows: RankBarRow[];
    /** Kaç satır çizilir; verilmezse hepsi. */
    limit?: number;
    /** Sayının ne olduğunu söyleyen ek — ekran okuyucu için. */
    valueLabel: string;
};

/**
 * Compound: oranlı sıralama listesi — `docs/109` §1 (Insights, "masaya göre
 * ilk 5").
 *
 * Sahibin sorusu "hangi masa çekiyor?" değil, "hangi masanın karekodu
 * çalışmıyor?"dur. Çıplak bir sayı listesinde 31 ile 12 arasındaki fark
 * okunur ama HİSSEDİLMEZ; yan yana duran iki çubukta ilk bakışta görünür.
 */
export function RankBarList({ rows, limit, valueLabel }: RankBarListProps) {
    if (rows.length === 0) {
        return null;
    }

    const visible = limit === undefined ? rows : rows.slice(0, limit);
    const max = Math.max(...visible.map((row) => row.value), 0);

    return (
        <ul className="flex flex-col">
            {visible.map((row) => {
                // Ölçek sıfıra bölünmez: basılmış ama hiç okutulmamış
                // karekodlar gerçektir ve 0/0 çubuğu `NaN` genişlikle çizerdi.
                const share = max === 0 ? 0 : (row.value / max) * 100;

                return (
                    <li
                        key={row.id}
                        className="flex min-h-[var(--density-row-height)] flex-wrap items-center gap-x-[var(--space-3)] gap-y-[var(--space-1)] border-t border-border px-[var(--space-5)] py-[var(--space-2)] text-body first:border-t-0"
                    >
                        <span className="font-medium text-fg">{row.label}</span>

                        {/*
                            Çubuk DEKORATİFTİR ve ekran okuyucudan gizlenir:
                            sayının kendisi hemen yanında duruyor. İki kez
                            okunan bir bilgi, listenin tamamını iki kat uzun
                            dinletir.
                        */}
                        <span
                            aria-hidden="true"
                            className="ms-auto block h-[var(--space-2)] w-[8rem] max-w-full overflow-hidden rounded-pill bg-surface-subtle"
                        >
                            <span
                                data-role="rank-bar"
                                className="block h-full rounded-pill"
                                style={{
                                    inlineSize: `${share}%`,
                                    background: 'var(--color-brand)',
                                }}
                            />
                        </span>

                        {/*
                            `tabular-nums`: oransal rakamlarda "1" diğerlerinden
                            dardır; alt alta duran sayılar farklı genişlikte
                            görünür ve sütun kayar. Karşılaştırma için var olan
                            bir listede bu, listenin tek işini bozar.
                        */}
                        <span className="w-[3rem] text-end tabular-nums text-fg-secondary">
                            {row.value}
                            <span className="sr-only"> {valueLabel}</span>
                        </span>
                    </li>
                );
            })}
        </ul>
    );
}

export default RankBarList;
