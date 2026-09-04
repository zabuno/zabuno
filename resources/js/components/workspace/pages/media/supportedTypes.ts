/**
 * Desteklenen tür grupları — VERİ, bileşen değil.
 *
 * Ayrı dosyada duruyor çünkü bir modülün hem bileşen hem sabit yayımlaması
 * hızlı yenilemeyi (fast refresh) bozar; ayrıca bu liste kanonik kaynağın
 * kendi tablosudur ve bileşenden bağımsız okunabilmelidir.
 */
export type SupportedTypeGroup = {
    key: 'images' | 'video' | 'documents' | 'audio';
    /** Bu grubun `accept` karşılığı — hangi grubun gerçekten kabul edildiğini seçer. */
    mimePrefix: string;
    /** Kaynağın broşür değeri; sunucu gerçek sınırı bildirdiğinde YERİNE geçilir. */
    fallbackMaxBytes: number;
    extensions: readonly string[];
};

/**
 * Kaynağın dört grubu (`docs/reference/media-manager/…`, "Desteklenen türler").
 *
 * Dördü de burada duruyor ama ekrana YALNIZ gerçekten kabul edileni çıkar.
 * Bugün yükleyici `image/*` kabul ediyor; video, belge ve ses için ne yükleme
 * yolu ne de işleme hattı var. Kabul edilmeyen bir türü "desteklenen" diye
 * listelemek, sahibi mutfakta bir MP4 ile baş başa bırakır — ekran ona
 * yapamayacağı bir şeyi vaat etmiş olur.
 *
 * Liste yine de tam tutuluyor: hat açıldığında eklenecek şey veri değil,
 * yalnız `accept` değeridir.
 */
export const SUPPORTED_TYPE_GROUPS: readonly SupportedTypeGroup[] = [
    {
        key: 'images',
        mimePrefix: 'image/',
        fallbackMaxBytes: 25 * 1024 * 1024,
        extensions: ['.jpg', '.png', '.heic', '.heif', '.webp', '.avif', '.tiff', '.svg'],
    },
    {
        key: 'video',
        mimePrefix: 'video/',
        fallbackMaxBytes: 200 * 1024 * 1024,
        extensions: ['.mp4', '.webm', '.mov', '.m4v'],
    },
    {
        key: 'documents',
        mimePrefix: 'application/',
        fallbackMaxBytes: 25 * 1024 * 1024,
        extensions: ['.pdf', '.csv', '.xlsx', '.docx'],
    },
    {
        key: 'audio',
        mimePrefix: 'audio/',
        fallbackMaxBytes: 50 * 1024 * 1024,
        extensions: ['.mp3', '.m4a', '.wav'],
    },
];
