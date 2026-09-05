<?php

declare(strict_types=1);

namespace App\Infrastructure\Content\Pages;

use App\Domain\Content\Block\BlockEntry;
use App\Domain\Content\Block\BlockType;
use App\Domain\Content\Block\ContentBlock;
use App\Domain\Content\PageContent;
use App\Domain\Content\PageMetadata;

/**
 * `/urun/gorsel-ve-medya/` — görsel ve medya yönetimi sayfası (P0).
 *
 * BİLEREK YAZILMAYANLAR: video (hiçbir biçimde), otomatik arka plan silme,
 * yapay zekâ ile görsel üretme ya da rötuş, filigran, odak noktası seçimi,
 * büyütme, etiket, görsel sitemap'i, CDN, PDF önizleme kapağı ve orijinal
 * dosyadan tam EXIF temizliği. Ölçüm 2026-09-06: bunların hiçbiri depoda yok.
 * Bir medya sayfasının en kolay uydurduğu şeyler tam olarak bunlardır ve
 * ikisi — video ile arka plan silme — site haritasında P2 satır olarak
 * duruyor; P2 bir söz değil, bir sıradır.
 */
final class ImagesAndMediaPage
{
    public static function content(): PageContent
    {
        return new PageContent(
            pageKey: 'urun.gorsel-ve-medya',
            locale: 'en',
            metadata: new PageMetadata(
                seoTitle: 'Menu photos and the media library',
                metaDescription: 'Upload a photo once, and the guest gets a small, fast, correctly sized copy. Scanned on the way in, and never deleted from under a live menu.',
                h1: 'Images and media',
                breadcrumbTitle: 'Images and media',
            ),
            blocks: [
                new ContentBlock(BlockType::DirectAnswer, null, [
                    new BlockEntry(
                        text: 'You upload the photo your phone took. Zabuno checks it for malware, makes the smaller copies a phone on a slow connection can actually load, and keeps your original untouched so the copies can always be made again.',
                    ),
                ]),

                new ContentBlock(BlockType::Problem, 'The photo from the phone is the wrong photo', [
                    new BlockEntry(
                        text: 'A picture straight from a phone is several megabytes wide enough to print. Sent to a guest on a crowded restaurant connection, it is a menu that loads after the waiter has already come back.',
                    ),
                    new BlockEntry(
                        text: 'The usual fixes are worse. Resizing by hand means every photo is a slightly different size. Uploading whatever fits means the guest pays for it, and the guest is the one person who cannot complain to you about it.',
                    ),
                ]),

                new ContentBlock(BlockType::Solution, 'One upload, several sizes, one original', [
                    new BlockEntry(
                        text: 'Every image is stored twice over: the original exactly as you sent it, and the delivered copies made from it. Because the original is kept, a copy can be rebuilt later without asking you to find the photo again.',
                    ),
                    new BlockEntry(
                        text: 'What the product cannot do, it refuses out loud. A file it cannot decode, a file that will not scan, a drawing that hides a script - each is rejected with the reason, rather than accepted and quietly broken on the guest\'s screen.',
                    ),
                ]),

                new ContentBlock(BlockType::HowItWorks, 'From your phone to the table', [
                    new BlockEntry(
                        term: 'The file is read, not trusted',
                        text: 'The first bytes decide what a file really is. A photo renamed to look like something else is caught here, because the extension is never the answer.',
                        source: 'app/Http/Requests/Media/StoreMediaRequest.php',
                    ),
                    new BlockEntry(
                        term: 'Held apart and scanned',
                        text: 'The upload waits in quarantine until a malware scan clears it. If the scan cannot run, the file is held rather than let through - the safe answer is the closed one.',
                        source: 'app/Application/Media/UseCase/ScanQuarantinedMediaAsset.php',
                    ),
                    new BlockEntry(
                        term: 'Smaller copies, made once',
                        text: 'The delivered copies are generated at the widths that slot needs, cropped to its shape, and encoded as WebP where the server supports it. Nothing is ever enlarged past the original.',
                        source: 'app/Infrastructure/Media/Processing/GdMediaAssetProcessor.php',
                    ),
                    new BlockEntry(
                        term: 'Served with a fingerprint',
                        text: 'Each copy has an address containing a fingerprint of its bytes, so browsers may cache it for a year. Change the image and the address changes; nobody has to clear a cache.',
                        source: 'app/Support/Media/RenditionUrl.php',
                    ),
                    new BlockEntry(
                        term: 'Attached to a dish, at a version',
                        text: 'A photo is bound to a menu item at a specific version, and a published menu keeps showing the version it was published with.',
                        source: 'app/Http/Controllers/MenuCatalog/BindMenuItemImageController.php',
                    ),
                ]),

                new ContentBlock(BlockType::Capabilities, 'What the media library does', [
                    new BlockEntry(
                        term: 'Photos on dishes and a logo on the brand',
                        text: 'A picture for a menu item and a logo for the business. The logo also travels into the printed QR cards, so the table card is yours and not a generic square.',
                        source: 'app/Http/Controllers/QrDestination/ExportQrCardController.php',
                    ),
                    new BlockEntry(
                        term: 'Alt text is asked for, not optional',
                        text: 'Every upload carries a description. A guest using a screen reader hears what the dish looks like instead of hearing a file name.',
                        source: 'app/Http/Requests/Media/StoreMediaRequest.php',
                    ),
                    new BlockEntry(
                        term: 'Crop before you upload, and a photo made smaller in the browser',
                        text: 'The picture is cropped and shrunk on your own device before it is sent, so a slow upload from a phone in the restaurant is a short one.',
                        source: 'resources/js/components/workspace/pages/media/clientDownscale.ts',
                    ),
                    new BlockEntry(
                        term: 'A photo from an iPhone is handled',
                        text: 'The HEIC format phones save in is converted to JPEG in the browser before it is sent, because the server cannot read HEIC and says so plainly instead of failing.',
                        source: 'app/Infrastructure/Media/Processing/GdMediaAssetProcessor.php',
                    ),
                    new BlockEntry(
                        term: 'Folders, and a filter that follows them',
                        text: 'Two levels of folders to keep starters apart from desserts. There is no third level, because a library nobody can navigate is not tidier.',
                        source: 'app/Domain/Media/FolderNesting.php',
                    ),
                    new BlockEntry(
                        term: 'Convert an image to another format',
                        text: 'An existing image can be converted to WebP, AVIF or JPEG where the server supports that format. The original is kept and a new version is opened rather than overwritten.',
                        source: 'app/Application/Media/UseCase/ConvertMediaAssetsToFormat.php',
                    ),
                    new BlockEntry(
                        term: 'Versions, and a way back',
                        text: 'Replacing a photo opens a new version. The old one is still there and can be restored, so a bad swap on a busy evening is not permanent.',
                        source: 'app/Http/Controllers/Media/RestoreMediaVersionController.php',
                    ),
                    new BlockEntry(
                        term: '"Where is this used?" before it is deleted',
                        text: 'Deleting shows the dishes an image is attached to first. It then goes to the trash, not out of existence, and can be restored.',
                        source: 'app/Http/Controllers/Media/ShowMediaUsagesController.php',
                    ),
                    new BlockEntry(
                        term: 'Nothing a live menu shows is purged',
                        text: 'The permanent clear-out skips any file a published menu still displays. A guest holding a printed code does not watch a photo disappear.',
                        source: 'app/Console/Commands/PurgeMediaTrashCommand.php',
                    ),
                    new BlockEntry(
                        term: 'A record of who changed what',
                        text: 'Uploads, replacements and deletions are written down with the person and the time.',
                        source: 'app/Http/Controllers/Media/ListMediaAuditsController.php',
                    ),
                ]),

                new ContentBlock(BlockType::Requirements, 'What you need', [
                    new BlockEntry(
                        term: 'Permission to manage media',
                        text: 'Uploading and deleting belong to the media permission. A team member without it can read the menu but cannot change its pictures.',
                        source: 'app/Domain/Authorization/RolePermissions.php',
                    ),
                    new BlockEntry(
                        term: 'A file the product accepts',
                        text: 'Photographs as JPEG, PNG, GIF or WebP; logos as SVG; documents as PDF. Anything else is refused at the door with the reason.',
                        source: 'config/media-slots.php',
                    ),
                    new BlockEntry(
                        term: 'A file inside the size limits',
                        text: 'Twenty-five megabytes for a photograph, two for a logo drawing, forty-five for a document, and no file above forty megapixels.',
                        source: 'config/media-slots.php',
                    ),
                    new BlockEntry(
                        term: 'Room inside your plan',
                        text: 'Storage, the number of files and the monthly upload count depend on the plan. Running out blocks new uploads only; the menu already published keeps being served.',
                        source: 'config/media-quota.php',
                    ),
                    new BlockEntry(
                        term: 'A malware scanner on the server',
                        text: 'Scanning is done by a scanner installed alongside the product. Where it is missing, uploads are held rather than waved through.',
                        source: 'config/media.php',
                    ),
                ]),

                new ContentBlock(BlockType::Limitations, 'What it does not do', [
                    new BlockEntry(
                        term: 'No video',
                        text: 'There is no video pipeline at all. A video file is recognised by its container and refused, rather than accepted and left unplayable.',
                        source: 'app/Infrastructure/Media/Processing/RuntimeMediaFormatSupport.php',
                    ),
                    new BlockEntry(
                        term: 'No background removal and no generated pictures',
                        text: 'Photos are resized, cropped to the slot and re-encoded. They are not retouched, cut out from their background or invented.',
                        source: 'app/Infrastructure/Media/Processing/GdMediaAssetProcessor.php',
                    ),
                    new BlockEntry(
                        term: 'No watermark',
                        text: 'Images are delivered as they are. Nothing is stamped over them.',
                        source: 'app/Http/Controllers/Media/ShowMediaSettingsController.php',
                    ),
                    new BlockEntry(
                        term: 'No focal point, no enlarging',
                        text: 'Where a slot has a fixed shape the crop is taken from the centre; you cannot mark the part that must survive. An image smaller than the target is never blown up.',
                        source: 'app/Infrastructure/Media/Processing/GdMediaAssetProcessor.php',
                    ),
                    new BlockEntry(
                        term: 'No animation',
                        text: 'A moving image is reduced to a single frame. An animated menu photo is not a supported thing.',
                        source: 'config/media-slots.php',
                    ),
                    new BlockEntry(
                        term: 'The original keeps its camera data',
                        text: 'The copies a guest downloads are re-encoded and carry no camera metadata. The original you uploaded is stored exactly as it arrived, location tag and all.',
                        source: 'app/Http/Controllers/Media/ShowMediaSettingsController.php',
                    ),
                    new BlockEntry(
                        term: 'No search box in the library',
                        text: 'You can filter by folder and sort by date, name or size. There is no text search across file names.',
                        source: 'app/Http/Controllers/Media/ListMediaController.php',
                    ),
                    new BlockEntry(
                        term: 'No cover picture for a PDF',
                        text: 'A document is stored and can be read in the panel, but no thumbnail is generated for it. An invented cover would be a picture of nothing.',
                        source: 'app/Infrastructure/Media/Processing/PdfMediaAssetProcessor.php',
                    ),
                    new BlockEntry(
                        term: 'No pictures on categories',
                        text: 'A photograph attaches to a dish or a brand logo. A section heading on the menu does not carry an image.',
                        source: 'app/Infrastructure/Media/Persistence/EloquentMenuMedia.php',
                    ),
                    new BlockEntry(
                        term: 'Files are served by the product, not a delivery network',
                        text: 'Images come from the same server the menu does. There is no separate content network in front of them.',
                        source: 'app/Infrastructure/Media/Persistence/EloquentMediaRepository.php',
                    ),
                ]),

                new ContentBlock(BlockType::Faq, 'Questions owners ask', [
                    new BlockEntry(
                        term: 'Can I upload the photo straight from my phone?',
                        text: 'Yes, including the HEIC files an iPhone saves. It is converted and shrunk in your browser before it is sent, so the upload is short even on the restaurant connection.',
                    ),
                    new BlockEntry(
                        term: 'Will my guests download a huge file?',
                        text: 'No. The guest gets a copy made for the size it is shown at, encoded small, and cached by their browser afterwards.',
                    ),
                    new BlockEntry(
                        term: 'Can I add a video of the kitchen?',
                        text: 'No. There is no video support of any kind, and a video file is refused at upload rather than accepted and left broken.',
                    ),
                    new BlockEntry(
                        term: 'Can Zabuno remove the background from my photo?',
                        text: 'No. Images are resized and cropped, not edited.',
                    ),
                    new BlockEntry(
                        term: 'What happens if I delete an image that is on the menu?',
                        text: 'You are shown where it is used first. It then goes to the trash and can be restored, and the permanent clear-out refuses to touch anything a published menu still shows.',
                    ),
                    new BlockEntry(
                        term: 'Do I have to write alt text?',
                        text: 'Yes, and it is deliberate. It is what a guest using a screen reader hears, and it is a sentence only you can write.',
                    ),
                ]),

                new ContentBlock(BlockType::Cta, 'Storage grows with the plan', [
                    new BlockEntry(
                        text: 'See how much room each plan gives, and what stays open without one.',
                        href: '/pricing',
                        term: 'See the plans',
                    ),
                ]),

                new ContentBlock(BlockType::Related, 'Related pages', [
                    new BlockEntry(text: 'Menu management', pageKey: 'urun.menu-yonetimi'),
                    new BlockEntry(text: 'QR menu', pageKey: 'urun.qr-menu'),
                ]),
            ],
        );
    }
}
