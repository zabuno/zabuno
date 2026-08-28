import { useEffect, useRef } from 'react';

export type ErrorSummaryEntry = {
    /** Alanın DOM kimliği — özetten alana atlamak için. */
    fieldId: string;
    label: string;
    message: string;
};

export type ErrorSummaryProps = {
    entries: ErrorSummaryEntry[];
    title: string;
};

/**
 * Formun başındaki hata özeti.
 *
 * Neden gerekli: alan altındaki hatalar tek başına yetmez. Uzun bir formda
 * kullanıcı gönder'e bastığında sayfanın neresinde durduğunu bilmez; iki
 * hatalı alan ekranın dışında kalmış olabilir ve kullanıcı formun
 * gönderilmediğini bile fark etmeyebilir.
 *
 * Özet üç şeyi birden yapar: kaç hata olduğunu söyler, her birinin ne
 * olduğunu yazar, ve her satırdan ilgili alana ATLATIR. Yalnız "lütfen
 * hataları düzeltin" diyen bir kutu, kullanıcıyı hataları aramaya bırakır.
 *
 * Odak özete taşınır: `role="alert"` mesajı duyurur ama kullanıcıyı oraya
 * GÖTÜRMEZ; klavyeyle gezinen biri hâlâ formun sonundadır.
 */
export function ErrorSummary({ entries, title }: ErrorSummaryProps) {
    const headingRef = useRef<HTMLHeadingElement>(null);

    useEffect(() => {
        if (entries.length > 0) {
            headingRef.current?.focus();
        }
    }, [entries.length]);

    if (entries.length === 0) {
        return null;
    }

    return (
        <div
            role="alert"
            className="flex flex-col gap-2 rounded-lg border border-border-danger bg-surface-danger p-4"
        >
            <h3
                ref={headingRef}
                tabIndex={-1}
                className="text-body font-semibold text-fg-danger outline-none"
            >
                {title}
            </h3>
            <ul className="flex list-inside list-disc flex-col gap-1">
                {entries.map((entry) => (
                    <li key={entry.fieldId} className="text-body text-fg-danger">
                        {/*
                            Gerçek bir bağlantı: klavye, ekran okuyucu ve
                            "bağlantıyı kopyala" kendiliğinden çalışır. Bir
                            `<button>` ile aynı görünürdü ama listeden alana
                            atlamak bir GEZİNTİdir, bir eylem değil.
                        */}
                        <a href={`#${entry.fieldId}`} className="underline">
                            {entry.label}
                        </a>
                        {' — '}
                        {entry.message}
                    </li>
                ))}
            </ul>
        </div>
    );
}

export default ErrorSummary;
