<?php

declare(strict_types=1);

namespace App\Infrastructure\Content\Pages;

use App\Domain\Content\Block\BlockEntry;
use App\Domain\Content\Block\BlockType;
use App\Domain\Content\Block\ContentBlock;
use App\Domain\Content\PageContent;
use App\Domain\Content\PageMetadata;

/**
 * `/urun/zabuno-ai/` — Zabuno AI genel bakış sayfası (P0).
 *
 * Bu sayfa paketin en riskli sayfasıydı ve ölçüm onu küçülttü. Depoda BUGÜN
 * uçtan uca çalışan üç yetenek var: fotoğraftan menü çıkarma, ürün AÇIKLAMASI
 * taslağı ve kopya ürün adı TESPİTİ.
 *
 * BİLEREK YAZILMAYANLAR: PDF'ten menü aktarımı (her iki görüntü adaptörü de
 * dosyayı image MIME'ına zorluyor), sıfırdan menü üretimi, ürün ADI asistanı,
 * çeviri asistanı, görsel asistanı, beslenme/alerjen asistanı, raporlama
 * asistanı, pazarlama asistanı, kopya ürünleri BİRLEŞTİRME, otomatik PII
 * temizliği, parasal maliyet tavanı, taslakların otomatik silinmesi. Ayrıca
 * hiçbir doğruluk/başarım oranı yazılmadı: hiçbir adaptör gerçek bir sağlayıcı
 * API'sine karşı doğrulanmadı ve testler `Http::fake` kullanıyor.
 */
final class ZabunoAiPage
{
    public static function content(): PageContent
    {
        return new PageContent(
            pageKey: 'urun.zabuno-ai',
            locale: 'en',
            metadata: new PageMetadata(
                seoTitle: 'Zabuno AI',
                metaDescription: 'Turn a photo of your menu into a draft, get a first draft of a dish description, and find products you have typed in twice.',
                h1: 'Zabuno AI',
                breadcrumbTitle: 'Zabuno AI',
            ),
            blocks: [
                new ContentBlock(BlockType::DirectAnswer, null, [
                    new BlockEntry(
                        text: 'Zabuno AI does three things today: it reads a photo of a menu into a draft, it drafts a description for a dish, and it points out products you have entered twice. Nothing it produces reaches a guest until a person reads it and applies it.',
                    ),
                ]),

                new ContentBlock(BlockType::Problem, 'The first evening is the hard one', [
                    new BlockEntry(
                        text: 'The work that stops a restaurant from starting is typing. Ninety dishes, their categories and their prices, entered by hand on a phone, after service, by someone who has been on their feet since morning.',
                    ),
                    new BlockEntry(
                        text: 'The menu already exists. It is printed, laminated and sitting on the table. What is missing is a way to get it out of the paper and into a screen.',
                    ),
                ]),

                new ContentBlock(BlockType::Solution, 'A draft, never a publication', [
                    new BlockEntry(
                        text: 'Photograph the menu and Zabuno turns it into a draft you can correct. It is a first pass, not a final answer, and the product is built around that distinction.',
                    ),
                    new BlockEntry(
                        text: 'Nothing generated is written anywhere until a person applies it, and even then it lands in your working draft rather than on a guest\'s table. A model that misreads a price would otherwise put that price on a menu.',
                    ),
                ]),

                new ContentBlock(BlockType::HowItWorks, 'How it works', [
                    new BlockEntry(
                        term: 'Photograph the menu',
                        text: 'Send one photo, up to ten in one go, or queue a stack of pages to be read in the background.',
                        source: 'app/Http/Controllers/Ai/StoreMenuAiBatchController.php',
                    ),
                    new BlockEntry(
                        term: 'The reply is checked against a schema',
                        text: 'A reply that does not match the expected shape is rejected at the door. It is never stored and never shown to you.',
                        source: 'app/Infrastructure/Ai/ArtifactSchemaValidator.php',
                    ),
                    new BlockEntry(
                        term: 'It waits as a draft',
                        text: 'The result is filed as an unapplied draft with the model and prompt version that produced it, so you can tell later what read what.',
                        source: 'app/Application/Ai/UseCase/ExtractMenuFromImage.php',
                    ),
                    new BlockEntry(
                        term: 'You read it and apply it',
                        text: 'Applying is a separate, deliberate action by a person with permission to manage the menu, and it can only be done once.',
                        source: 'app/Application/Ai/UseCase/ApplyMenuArtifact.php',
                    ),
                    new BlockEntry(
                        term: 'You publish, or you do not',
                        text: 'Applying writes to your draft menu. Guests still see nothing until you publish.',
                        source: 'app/Http/Controllers/Ai/ApplyMenuAiImportController.php',
                    ),
                ]),

                new ContentBlock(BlockType::Capabilities, 'What it does today', [
                    new BlockEntry(
                        term: 'A photo becomes a menu draft',
                        text: 'Categories, dish names, prices and currency are read out of a photograph into rows you can correct before applying.',
                        source: 'app/Application/Ai/UseCase/ExtractMenuFromImage.php',
                    ),
                    new BlockEntry(
                        term: 'A stack of pages at once',
                        text: 'A multi-page menu can be queued and read page by page in the background, with a per-restaurant limit so one large import cannot crowd out everyone else.',
                        source: 'app/Jobs/ExtractMenuBatchPageJob.php',
                    ),
                    new BlockEntry(
                        term: 'A first draft of a description',
                        text: 'A dish description is drafted for you to edit. The draft is applied only after you approve it, and it replaces the description alone.',
                        source: 'app/Application/Ai/UseCase/ApplyProductDescriptionDraft.php',
                    ),
                    new BlockEntry(
                        term: 'The same dish, typed twice',
                        text: 'Products with near-identical names are listed as candidates so you can see the duplicates a long menu hides.',
                        source: 'app/Application/Ai/UseCase/DetectDuplicateProductNames.php',
                    ),
                    new BlockEntry(
                        term: 'Instructions and menu text are kept apart',
                        text: 'Your menu text is sent as data, never as instructions, so a line inside a menu cannot tell the model what to do.',
                        source: 'app/Infrastructure/Ai/GeminiVisionProvider.php',
                    ),
                    new BlockEntry(
                        term: 'Allergens are never invented',
                        text: 'Generated text is forbidden to make allergen claims, and an imported dish arrives with no allergens at all. You declare them yourself.',
                        source: 'app/Application/Ai/UseCase/ApplyMenuArtifact.php',
                    ),
                    new BlockEntry(
                        term: 'It says when it is off',
                        text: 'Each capability reports whether it is available and why not, so a button that cannot work is not offered as though it could.',
                        source: 'app/Http/Controllers/Ai/ShowAiAvailabilityController.php',
                    ),
                    new BlockEntry(
                        term: 'One restaurant\'s drafts stay its own',
                        text: 'Every AI record is bound to the workspace that created it, and disappears with it.',
                        source: 'database/migrations/2026_08_27_000500_create_ai_plane_tables.php',
                    ),
                ]),

                new ContentBlock(BlockType::Requirements, 'What you need', [
                    new BlockEntry(
                        term: 'AI has to be switched on',
                        text: 'AI ships switched off. Until it is enabled and a provider is configured, every AI action reports that it is unavailable and refuses rather than pretending.',
                        source: 'config/ai.php',
                    ),
                    new BlockEntry(
                        term: 'A configured provider',
                        text: 'Provider keys are entered once in the encrypted platform vault. They are not read from environment files.',
                        source: 'app/Infrastructure/Platform/Credential/EloquentPlatformCredentialStore.php',
                    ),
                    new BlockEntry(
                        term: 'A photograph, not a document',
                        text: 'The importer reads image files. A menu that exists only as a PDF has to be photographed or turned into images first.',
                        source: 'app/Infrastructure/Ai/OpenAiVisionProvider.php',
                    ),
                    new BlockEntry(
                        term: 'Permission to manage the menu',
                        text: 'Applying a draft is checked against the same permission as editing the menu by hand, because that is what it does.',
                        source: 'app/Http/Controllers/Ai/ApplyMenuAiImportController.php',
                    ),
                ]),

                new ContentBlock(BlockType::Limitations, 'What it does not do', [
                    new BlockEntry(
                        term: 'It does not write your menu',
                        text: 'There is no dish invention. It reads a menu you already have; it does not propose one you do not.',
                        source: 'app/Domain/Ai/Capability.php',
                    ),
                    new BlockEntry(
                        term: 'It does not translate',
                        text: 'There is no translation assistant. Your menu stays in the language you wrote it in.',
                        source: 'app/Domain/Ai/Capability.php',
                    ),
                    new BlockEntry(
                        term: 'It does not touch photos, nutrition or reports',
                        text: 'No image generation or retouching, no calorie or nutrition estimates, no written summary of your analytics, no campaign copy.',
                        source: 'app/Domain/Ai/Capability.php',
                    ),
                    new BlockEntry(
                        term: 'It does not rename products',
                        text: 'Descriptions are drafted; names are left exactly as you typed them.',
                        source: 'app/Application/Ai/UseCase/ApplyProductDescriptionDraft.php',
                    ),
                    new BlockEntry(
                        term: 'It finds duplicates but does not merge them',
                        text: 'The duplicate list is something to read. Merging two products is still done by hand.',
                        source: 'app/Application/Ai/UseCase/DetectDuplicateProductNames.php',
                    ),
                    new BlockEntry(
                        term: 'We publish no accuracy figures',
                        text: 'The provider adapters have been tested against recorded responses, not against a live provider. Any percentage we printed here would be a number we had not measured.',
                        source: 'app/Infrastructure/Ai/OpenAiVisionProvider.php',
                    ),
                    new BlockEntry(
                        term: 'Drafts are kept until you remove them',
                        text: 'Imported drafts and their records stay until the workspace is deleted. There is no automatic expiry.',
                        source: 'database/migrations/2026_08_27_000500_create_ai_plane_tables.php',
                    ),
                ]),

                new ContentBlock(BlockType::Faq, 'Questions owners ask', [
                    new BlockEntry(
                        term: 'Can it read my menu from a photo?',
                        text: 'Yes. One photo, ten at once, or a queued stack of pages. The result is a draft you correct before applying.',
                    ),
                    new BlockEntry(
                        term: 'Can it read a PDF?',
                        text: 'No. The importer reads images. A PDF menu has to be photographed or converted to images first.',
                    ),
                    new BlockEntry(
                        term: 'Can it put a wrong price in front of a guest?',
                        text: 'Not on its own. Nothing is written until you apply it, and applying writes to your draft. Guests see it only after you publish.',
                    ),
                    new BlockEntry(
                        term: 'Will it guess the allergens?',
                        text: 'No, and that is deliberate. Generated text may not make allergen claims and imported dishes arrive with none. Allergens are yours to declare.',
                    ),
                    new BlockEntry(
                        term: 'How accurate is it?',
                        text: 'We do not publish a figure, because we have not measured one against a live provider. Treat every import as a draft to be checked.',
                    ),
                    new BlockEntry(
                        term: 'Is it on by default?',
                        text: 'No. AI ships switched off, and each capability reports whether it is available and why not.',
                    ),
                ]),

                new ContentBlock(BlockType::Cta, 'Start with the menu, not the AI', [
                    new BlockEntry(
                        text: 'Building and publishing a menu by hand works on the free plan, with or without AI.',
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
