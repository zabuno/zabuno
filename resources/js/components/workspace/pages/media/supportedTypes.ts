/**
 * Desteklenen tür grupları — VERİ, bileşen değil.
 *
 * Ayrı dosyada duruyor çünkü bir modülün hem bileşen hem sabit yayımlaması
 * hızlı yenilemeyi (fast refresh) bozar; ayrıca bu liste kanonik kaynağın
 * kendi tablosudur ve bileşenden bağımsız okunabilmelidir.
 */
export type SupportedTypeGroup = {
    key: 'images' | 'vector' | 'video' | 'documents' | 'audio';
    /** Bu grubun `accept` karşılığı — hangi grubun gerçekten kabul edildiğini seçer. */
    mimePrefix: string;
    /**
     * Sunucunun tür sözlüğündeki karşılığı (`limits.maxBytesByKind`).
     *
     * `null` demek "sunucunun bu tür için bir sınırı YOK" demektir — çünkü
     * o türü hiç kabul etmiyor. O satır çizilirse broşür değeriyle çizilir
     * ve zaten `accept` onu bugün ekrana hiç çıkarmaz.
     */
    limitKind: 'image' | 'vector' | 'document' | null;
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
        limitKind: 'image',
        fallbackMaxBytes: 25 * 1024 * 1024,
        extensions: ['.jpg', '.png', '.heic', '.heif', '.webp', '.avif', '.tiff'],
    },
    /*
        VEKTÖR KENDİ SATIRIDIR (FF-158).

        2026-09-05'e kadar `.svg` "Görseller" satırının uzantı listesindeydi
        ve o satırın azami boyutunu paylaşıyordu. Sunucu artık ikisine AYRI
        sınır uyguluyor — ve aradaki fark küçük değil: bir SVG kabul
        edilmeden önce temizleyici gövdesinin tamamını ayrıştırır, yani
        sınır orada bir kolaylık değil güvenlik kısıtıdır. Tek satırda
        gösterilseydi tabloda yazan sayı SVG için yanlış olurdu ve sahip
        bunu ancak reddedildikten sonra öğrenirdi.

        `mimePrefix` yine `image/`: SVG bir `image/svg+xml`'dir ve
        `accept="image/*"` onu seçtirir. Ayrım tabloda, kabulde değil.
    */
    {
        key: 'vector',
        mimePrefix: 'image/',
        limitKind: 'vector',
        fallbackMaxBytes: 2 * 1024 * 1024,
        extensions: ['.svg'],
    },
    {
        key: 'video',
        // Sunucuda karşılığı YOK — ve bu bilinçli. Video hattı hiç yok
        // (`docs/109` §8.2); olmayan bir yetenek için sayı uydurulmaz.
        limitKind: null,
        mimePrefix: 'video/',
        fallbackMaxBytes: 200 * 1024 * 1024,
        extensions: ['.mp4', '.webm', '.mov', '.m4v'],
    },
    {
        key: 'documents',
        mimePrefix: 'application/',
        limitKind: 'document',
        fallbackMaxBytes: 25 * 1024 * 1024,
        extensions: ['.pdf', '.csv', '.xlsx', '.docx'],
    },
    {
        key: 'audio',
        mimePrefix: 'audio/',
        limitKind: null,
        fallbackMaxBytes: 50 * 1024 * 1024,
        extensions: ['.mp3', '.m4a', '.wav'],
    },
];
