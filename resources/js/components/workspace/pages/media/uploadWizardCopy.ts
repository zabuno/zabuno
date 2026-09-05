import { t, workspaceTranslations, type WorkspaceTranslationKey } from '../../../../i18n/workspace';

/**
 * Yükleme sihirbazının METİNLERİ — GEÇİCİ bir köprü.
 *
 * `resources/js/i18n/workspace/media.ts` bu pakette BAŞKA bir yazarın
 * dosyasıdır ve buradan düzenlenmez. Sihirbazın ihtiyacı olan anahtarlar o
 * dosyanın sahibine ayrıca bildirildi.
 *
 * O anahtarlar gelene kadar iki kötü seçenek vardı:
 *
 *   1. `t()` ile olmayan anahtarı çağırmak — `t()` bulamadığı anahtarı OLDUĞU
 *      GİBİ döndürür, yani ekranda `workspace.media.upload.step.pick` yazardı.
 *      Kullanıcıya iç anahtar adı göstermek, bu deponun kelime dağarcığı
 *      muhafızının tam olarak kapattığı arızadır.
 *   2. Metinleri bileşenlere gömmek — çeviri zinciri kalıcı olarak kopardı.
 *
 * Bu köprü üçüncü yolu açar ve KENDİ KENDİNİ SİLER: anahtar katalogda varsa
 * `t()` kazanır, yoksa buradaki İngilizce taban kullanılır. Katalog sahibi
 * anahtarları eklediği an bu tablodaki satırlar ölü koda dönüşür ve
 * silinebilir; ekranda hiçbir şey değişmez. `uploadWizardCopy.test.ts` tam
 * bu devri sınar.
 */
const PENDING: Record<string, string> = {
    // --- Adım göstergesi ---------------------------------------------------
    'workspace.media.upload.steps.label': 'Upload steps',
    'workspace.media.upload.step.pick': 'Choose',
    'workspace.media.upload.step.optimize': 'Shrink',
    'workspace.media.upload.step.frame': 'Frame',
    /*
        Dördüncü adımın etiketi "Send", düğmenin metni "Upload".

        İkisi aynı olsaydı ekranda iki ayrı işi yapan iki ayrı düğme aynı adı
        taşırdı: biri adıma GİDER, öteki dosyayı GÖNDERİR. Ekran okuyucuda bu
        ayrım tamamen kaybolurdu.
    */
    'workspace.media.upload.step.send': 'Send',
    'workspace.media.upload.step.position': 'Step {step} of {total}',
    'workspace.media.upload.step.done': 'Done',
    'workspace.media.upload.next': 'Continue',
    'workspace.media.upload.back': 'Back',

    // --- 1. Seç ------------------------------------------------------------
    'workspace.media.upload.pick.camera': 'Take a photo',
    'workspace.media.upload.pick.lead': 'Take one with your phone, or pick one from your gallery.',

    // --- Desteklenen türler ------------------------------------------------
    'workspace.media.upload.supported.heading': 'Supported types',
    'workspace.media.upload.supported.column.type': 'Type',
    'workspace.media.upload.supported.column.max': 'Largest size',
    'workspace.media.upload.supported.column.extensions': 'Extensions',
    'workspace.media.upload.supported.column.note': 'Note',
    'workspace.media.upload.supported.images': 'Images',
    'workspace.media.upload.supported.images.note':
        'HEIC and HEIF are converted to JPEG on your phone; AVIF and WebP are taken as they are.',
    /*
        FF-158. `.svg` 2026-09-05'e kadar "Images" satırının uzantı
        listesindeydi ve o satırın azami boyutunu paylaşıyordu. Sunucu ikisine
        ayrı sınır uyguluyor: bir SVG kabul edilmeden önce gövdesinin tamamı
        ayrıştırılır, yani oradaki sınır bir kolaylık değil güvenlik
        kısıtıdır. Tek satırda kalsaydı tabloda yazan sayı SVG için yanlış
        olurdu.
    */
    'workspace.media.upload.supported.vector': 'Vector (SVG)',
    'workspace.media.upload.supported.vector.note':
        'A logo stays sharp at any size, including in print. Every line is checked before it is accepted, so the size limit is much smaller than for photos.',
    'workspace.media.upload.supported.video': 'Video',
    'workspace.media.upload.supported.video.note':
        'MOV and MP4 can be converted to WebM after upload; the first frame becomes the cover.',
    'workspace.media.upload.supported.documents': 'Documents',
    'workspace.media.upload.supported.documents.note':
        'A PDF is read page by page in the panel; the first page becomes the cover image.',
    'workspace.media.upload.supported.audio': 'Audio',
    'workspace.media.upload.supported.audio.note':
        'For spoken menu descriptions. It plays inside the panel.',

    // --- 2. İstemci optimizasyonu -----------------------------------------
    'workspace.media.upload.optimize.heading': 'Shrunk on your phone',
    'workspace.media.upload.optimize.saved': '{percent}% smaller',
    'workspace.media.upload.optimize.savedNote':
        '{after} will be sent instead of {before}. That is data you do not pay for twice.',
    'workspace.media.upload.optimize.none':
        'This photo is already small enough. It will be sent unchanged.',
    'workspace.media.upload.optimize.working': 'Shrinking on this device…',
    'workspace.media.upload.optimize.longEdge': 'Longest side',
    'workspace.media.upload.optimize.longEdge.value': '{pixels} px',
    'workspace.media.upload.optimize.result': '{width} × {height} pixels will be sent.',
    'workspace.media.upload.optimize.floor':
        'It stops here: this place needs at least {width} × {height} pixels, and cropping cannot add pixels back.',
    'workspace.media.upload.optimize.privacy':
        'The file is shrunk on your own device. Nothing leaves it until you press Upload.',
};

function fill(template: string, vars?: Record<string, string>): string {
    if (!vars) return template;

    return Object.entries(vars).reduce<string>(
        (result, [name, value]) => result.replaceAll(`{${name}}`, value),
        template,
    );
}

/**
 * Katalog KAZANIR.
 *
 * Sıra bilerek böyle: anahtar `media.ts` içine eklendiği an çeviri zinciri
 * (locale geçersiz kılmaları dahil) devreye girer ve buradaki taban artık
 * okunmaz. Ters sırada olsaydı, eklenen çeviri sessizce görmezden gelinirdi.
 */
export function wizardText(key: string, vars?: Record<string, string>): string {
    if (key in workspaceTranslations) {
        return t(key as WorkspaceTranslationKey, vars);
    }

    return fill(PENDING[key] ?? key, vars);
}

/** Kataloğa taşınmayı bekleyen anahtarların listesi — rapor ve test için. */
export function pendingCopyKeys(): string[] {
    return Object.keys(PENDING);
}
