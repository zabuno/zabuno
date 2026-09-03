/**
 * Kütüphane ekranının küçük biçimleyicileri (`docs/49` Faz 4).
 *
 * Sahip "2 384 512" değil "2,3 MB" okur; tarih de ham ISO değil yerel
 * kısa tarih olur. Yerel ayar tarayıcınındır — sunucu locale'i buraya
 * taşınmaz (`docs/37` i18n: metin çeviri, biçim tarayıcı).
 */
export function formatBytes(bytes: number | undefined): string {
    if (bytes === undefined || !Number.isFinite(bytes) || bytes < 0) {
        return '';
    }

    if (bytes < 1024) {
        return `${bytes} B`;
    }

    const kb = bytes / 1024;
    if (kb < 1024) {
        return `${kb.toFixed(kb < 10 ? 1 : 0)} KB`;
    }

    const mb = kb / 1024;
    return `${mb.toFixed(mb < 10 ? 1 : 0)} MB`;
}

export function formatDate(iso: string | null | undefined): string {
    if (!iso) {
        return '';
    }

    const date = new Date(iso);
    if (Number.isNaN(date.getTime())) {
        return '';
    }

    return date.toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: 'numeric' });
}
