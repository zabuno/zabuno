<?php

declare(strict_types=1);

namespace App\Infrastructure\Content\Pages;

use App\Domain\Content\Block\BlockEntry;
use App\Domain\Content\Block\BlockType;
use App\Domain\Content\Block\ContentBlock;
use App\Domain\Content\PageContent;
use App\Domain\Content\PageMetadata;
use Database\Seeders\PlanCatalogueSeeder;

/**
 * `/urun/menu-yonetimi/menu-versiyonlari/` — yayın, sürümler ve geri alma
 * (P1). FF-229.
 *
 * ═══ NEDEN BU SAYFA VAR ═══
 *
 * `docs/137` §5: yayın ve geri alma ÇALIŞIYOR — telefonda önizleme,
 * zamanlanmış yayın, sürüm geçmişi, tek adımda geri dönüş — ama sitede
 * yalnız `/help` içinde bir paragraf olarak geçiyordu. Kendi adresi, kendi
 * başlığı ve kendi arama karşılığı yoktu. Oysa *"yanlış fiyat listesini
 * yayınlarsam ne olur"* bir yardım sorusu değil, bir SATIN ALMA sorusudur:
 * kırk masaya kod bastıran bir restoran sahibi kararı bunun cevabına göre
 * verir.
 *
 * `docs/81`in iki panik anından birincisi tam olarak burada anlatılıyor;
 * ikincisi (basılı kodun hedefini değiştirmek) `urun.masa-ve-qr-yonetimi`
 * sayfasının işidir ve orada anlatılıyor. Aynı soruyu iki sayfada sormamak
 * yönergenin kuralı ve `ProductPageLibraryTest` onu ölçüyor.
 *
 * ═══ EBEVEYNİN KOPYASI DEĞİL ═══
 *
 * `urun.menu-yonetimi` menüyü KURMAYI anlatır ve yayına bir adımda değinir.
 * Bu sayfa o tek adımın kendi sorusudur: sürüm nedir, canlı olan hangisi,
 * yanlış olandan nasıl dönülür, plan gece yayınlanmazsa ne olur.
 *
 * ═══ BİLEREK YAZILMAYANLAR ═══
 *
 * İki sürüm arasındaki FARKI gösteren bir ekran, onay/inceleme kuyruğu,
 * tekrarlayan (haftalık) plan, yayın olduğunda gönderilen e-posta ya da
 * bildirim, yayını başkasının onaylaması. Ölçüm 2026-09-08: hiçbirinin
 * depoda karşılığı yok, ve dördü de bir yayın ekranından en çok beklenen
 * şeylerdir — bu yüzden yokluğu yazılır.
 */
final class MenuVersionsPage
{
    public static function content(): PageContent
    {
        return new PageContent(
            pageKey: 'urun.menu-yonetimi.menu-versiyonlari',
            locale: 'en',
            metadata: new PageMetadata(
                seoTitle: 'Menu versions, scheduled publishing and rollback',
                metaDescription: 'Every publish is a numbered version behind the same printed code. Preview it first, publish it at a chosen hour, and put an earlier one back.',
                h1: 'Versions and going back',
                breadcrumbTitle: 'Versions and rollback',
            ),
            blocks: [
                new ContentBlock(BlockType::DirectAnswer, null, [
                    new BlockEntry(
                        text: 'Each time you publish, the menu is frozen as a numbered version and guests start reading it. You can look at it on a phone before it goes out, have it go out at an hour you choose, and put an earlier version back in one step. The printed code never changes.',
                    ),
                ]),

                new ContentBlock(BlockType::Problem, 'The wrong price list is already on forty tables', [
                    new BlockEntry(
                        text: 'A menu goes out with a whole column wrong, and guests are reading it right now. Paper cannot be recalled and the evening cannot be paused while somebody retypes the prices.',
                    ),
                    new BlockEntry(
                        text: 'Correcting it under that pressure is the slowest possible way to work and the likeliest to produce a second mistake. The question an owner really wants answered before buying is not "can I edit the menu" but "what happens on the night I get it wrong".',
                    ),
                ]),

                new ContentBlock(BlockType::Solution, 'The paper is not the menu; the version behind it is', [
                    new BlockEntry(
                        text: 'The card on the table points at a permanent address. What that address shows is the version you published last, so changing the menu never means changing the paper.',
                    ),
                    new BlockEntry(
                        text: 'Because every publish is kept, going back is a single step rather than a retyping exercise. The bad evening becomes a minute of trouble, and the record still says what was live and when.',
                    ),
                ]),

                new ContentBlock(BlockType::HowItWorks, 'How it works', [
                    new BlockEntry(
                        term: 'Look at the draft on a real phone first',
                        text: 'A preview link opens the draft the way a guest would see it and expires by itself a quarter of an hour later, so checking no longer means publishing first.',
                        source: 'app/Http/Controllers/Publication/CreateDraftPreviewLinkController.php',
                    ),
                    new BlockEntry(
                        term: 'Publish, and a numbered version is frozen',
                        text: 'Publishing takes a copy of the draft with its photographs, your logo and your colours, gives it the next version number and hands it to guests.',
                        source: 'app/Http/Controllers/Publication/StorePublicationController.php',
                    ),
                    new BlockEntry(
                        term: 'Or choose an hour instead',
                        text: 'The offered hours are worked out on the branch\'s own clock, not the clock of the computer you are sitting at, and stored as absolute moments so a daylight-saving night cannot be ambiguous.',
                        source: 'app/Application/Publication/UseCase/BuildScheduleOptions.php',
                    ),
                    new BlockEntry(
                        term: 'What you approved is what goes out',
                        text: 'A scheduled publish freezes the menu when you set it, and refuses a draft that is not ready there and then. Work you leave half finished afterwards cannot reach a guest at three in the morning.',
                        source: 'app/Http/Controllers/Publication/StorePublicationScheduleController.php',
                    ),
                    new BlockEntry(
                        term: 'The server carries it out on its own',
                        text: 'A scheduled publish is an ordinary publish: the same history, the next version number, the same printed code. It happens whether or not anyone is logged in, and it cannot happen twice.',
                        source: 'app/Console/Commands/PublishScheduledMenusCommand.php',
                    ),
                    new BlockEntry(
                        term: 'See which version is live',
                        text: 'The history lists the versions newest first and marks the one guests are reading now, because the owner arguing with a guest about a price is looking for exactly that line.',
                        source: 'app/Http/Controllers/Publication/ListPublicationsController.php',
                    ),
                    new BlockEntry(
                        term: 'Put an earlier one back',
                        text: 'Restoring publishes the old copy again as a new version. Going back to version one produces version three, so the history keeps saying what was live and when.',
                        source: 'app/Http/Controllers/Publication/RestorePublicationController.php',
                    ),
                ]),

                new ContentBlock(BlockType::Capabilities, 'What you can do', [
                    new BlockEntry(
                        term: 'Publish without touching the printed card',
                        text: 'A menu\'s public address carries an identifier that never changes. Rename the business, move the branch or rewrite the menu, and the card on the table keeps working.',
                        source: 'app/Domain/Publication/MenuPublicAddress.php',
                    ),
                    new BlockEntry(
                        term: 'Guests read a frozen copy, not your draft',
                        text: 'A published version holds the menu as it was at that moment. Editing tonight changes nothing on any table until you publish again.',
                        source: 'database/migrations/2026_08_22_000004_create_menu_publications_table.php',
                    ),
                    new BlockEntry(
                        term: 'The plan is frozen into the version too',
                        text: 'The rights that were in force are recorded with the publication, so a lapsed plan does not cut the page under a guest who is halfway through a meal. The change lands on your next publish.',
                        source: 'database/migrations/2026_09_06_000700_add_ordering_switch_and_frozen_plan.php',
                    ),
                    new BlockEntry(
                        term: 'Going back does not touch your draft',
                        text: 'Rollback corrects what guests read, not what you are working on. Half-finished edits in the draft survive it untouched.',
                        source: 'app/Application/Publication/UseCase/AssembleDraftSnapshot.php',
                    ),
                    new BlockEntry(
                        term: 'A schedule can be called off',
                        text: 'A publish set for tonight can be cancelled before it runs. The plan is kept as a cancelled record rather than erased, because "what happened that night" gets asked eventually.',
                        source: 'app/Http/Controllers/Publication/CancelPublicationScheduleController.php',
                    ),
                    new BlockEntry(
                        term: 'A publish that did not go out is said out loud',
                        text: 'Overdue, interrupted and failed are three separate answers, and in all three the menu did not change: guests are still reading the version before it.',
                        source: 'app/Domain/Publication/ScheduledPublicationOutcome.php',
                    ),
                    new BlockEntry(
                        term: 'A month is as far ahead as it goes',
                        text: 'A publish can be set up to thirty days ahead. Beyond that a frozen copy stops being a decision you remember making.',
                        source: 'app/Application/Publication/UseCase/BuildScheduleOptions.php',
                    ),
                    new BlockEntry(
                        term: 'An unready draft is refused at the button',
                        text: 'A menu that cannot be published is refused when you press publish or set the schedule, not silently in the middle of the night.',
                        source: 'app/Application/Publication/Exception/UnreadyDraftException.php',
                    ),
                    new BlockEntry(
                        term: 'Sold out does not wait for a publish',
                        text: 'Marking a dish as finished for today reaches the next guest who scans, with no version and no publish in between. It is the one change that skips this whole page.',
                        source: 'app/Http/Controllers/MenuCatalog/UpdateMenuStockController.php',
                    ),
                ]),

                new ContentBlock(BlockType::Requirements, 'What you need', [
                    new BlockEntry(
                        term: 'Permission to publish',
                        text: 'Going back is publishing and asks for the same permission. A read-only member can read the history and see which version is live, and cannot change it.',
                        source: 'app/Domain/Authorization/RolePermissions.php',
                    ),
                    new BlockEntry(
                        term: 'No paid plan',
                        text: self::freePlanSentence(),
                        source: 'database/seeders/PlanCatalogueSeeder.php',
                    ),
                    new BlockEntry(
                        term: 'A branch whose time zone is recognised',
                        text: 'Scheduled hours are worked out on the branch\'s clock. Where that clock cannot be read, no hours are offered rather than a guessed one, and publishing now stays open.',
                        source: 'app/Application/Publication/UseCase/BuildScheduleOptions.php',
                    ),
                    new BlockEntry(
                        term: 'A server that runs its scheduled work',
                        text: 'The publish set for three in the morning is carried out by a task that runs every minute on the server. Scheduling is the one part of this page that needs something running while you sleep.',
                        source: 'routes/console.php',
                    ),
                ]),

                new ContentBlock(BlockType::Limitations, 'What it does not do', [
                    new BlockEntry(
                        term: 'It is not an undo for your editing',
                        text: 'Rollback changes what guests read. It does not take back a price you typed in the draft, and it will not bring back a dish you deleted there.',
                        source: 'app/Http/Controllers/Publication/RestorePublicationController.php',
                    ),
                    new BlockEntry(
                        term: 'No comparison between two versions',
                        text: 'The history shows version numbers, times and which one is live. It does not show what changed between two of them, so the version you go back to has to be one you recognise.',
                        source: 'app/Http/Controllers/Publication/ListPublicationsController.php',
                    ),
                    new BlockEntry(
                        term: 'No approval step and no second pair of eyes',
                        text: 'Anyone allowed to publish publishes immediately. There is no review queue, no draft sent for approval and no way to require a second person.',
                        source: 'routes/api/publication.php',
                    ),
                    new BlockEntry(
                        term: 'A failed scheduled publish is not retried',
                        text: 'It is marked and left for you. Trying again quietly through the night would mean the menu could change without anybody deciding it should.',
                        source: 'app/Console/Commands/PublishScheduledMenusCommand.php',
                    ),
                    new BlockEntry(
                        term: 'No repeating schedule',
                        text: 'A schedule is one moment, not a rule. A menu that changes every Monday is set again every week.',
                        source: 'database/migrations/2026_09_05_000300_create_menu_publication_schedules_table.php',
                    ),
                    new BlockEntry(
                        term: 'Nothing is emailed when a version goes live',
                        text: 'No message is sent when a publish succeeds or fails. The answer lives on the screen, which means somebody has to look at it the morning after.',
                        source: 'app/Application/Publication/Dto/ScheduledPublicationRecord.php',
                    ),
                    new BlockEntry(
                        term: 'The preview link is deliberately short-lived',
                        text: 'It cannot be used as a permanent address to send around. It stops working a quarter of an hour later, so a draft forwarded to a group chat is dead by the next day.',
                        source: 'app/Http/Controllers/Publication/ShowDraftPreviewController.php',
                    ),
                ]),

                new ContentBlock(BlockType::Faq, 'Questions owners ask about publishing', [
                    new BlockEntry(
                        term: 'I published the wrong price list. What do I do now?',
                        text: 'Open the history, find the version that was right and restore it. Guests reading the menu see the corrected one on their next scan, and no card has to be reprinted.',
                    ),
                    new BlockEntry(
                        term: 'Does going back delete the version I am leaving?',
                        text: 'No. Restoring writes the old copy as a new version, so going back to version one produces version three. Nothing is removed from the record.',
                    ),
                    new BlockEntry(
                        term: 'Will I lose the edits I was in the middle of?',
                        text: 'No. Rollback only changes what guests read. Your draft is exactly where you left it.',
                    ),
                    new BlockEntry(
                        term: 'Does the address on the card change when I roll back?',
                        text: 'No. The public address of a menu carries an identifier that never changes, whatever you publish behind it.',
                    ),
                    new BlockEntry(
                        term: 'What happens if the publish I set for the night does not go out?',
                        text: 'You are told, and it is named: overdue, interrupted or failed. In all three the menu did not change, so guests are still reading the version before it.',
                    ),
                    new BlockEntry(
                        term: 'Can I show the draft to somebody before it goes live?',
                        text: 'Yes, with a preview link that opens the draft on a real phone and expires by itself a quarter of an hour later.',
                    ),
                    new BlockEntry(
                        term: 'Can I set a change to repeat every week?',
                        text: 'No. A schedule is a single moment, so a weekly change is set again each week.',
                    ),
                ]),

                new ContentBlock(BlockType::Cta, 'Publishing is not behind a plan', [
                    new BlockEntry(
                        text: 'Building a menu, publishing it and going back to an earlier version cost nothing.',
                        href: '/pricing',
                        term: 'See the plans',
                    ),
                ]),

                new ContentBlock(BlockType::Related, 'Related pages', [
                    new BlockEntry(text: 'Menu management', pageKey: 'urun.menu-yonetimi'),
                    new BlockEntry(text: 'Tables and QR codes', pageKey: 'urun.masa-ve-qr-yonetimi'),
                    new BlockEntry(text: 'QR menu', pageKey: 'urun.qr-menu'),
                ]),
            ],
        );
    }

    /**
     * Ücretsiz kademenin adı — KATALOGDAN, elle değil.
     *
     * "Starter" diye yazmak bugün doğru olurdu ve kademe adı değiştiği gün
     * sessizce eskirdi. Ücretsiz olanı TUTARDAN buluyoruz, adından değil:
     * bir gün başka bir ad seçilse bile "ödemeden yapılabilen" iddiası
     * rakamın kendisine dayanmalı.
     */
    private static function freePlanSentence(): string
    {
        foreach (PlanCatalogueSeeder::catalogue() as $plan) {
            if ($plan['amount_minor'] === 0) {
                return 'Publishing, previewing, scheduling and going back are all on the '.$plan['name']
                    .' plan, which costs nothing. No part of this page is opened by paying.';
            }
        }

        return 'Publishing, previewing, scheduling and going back are not behind any paid plan.';
    }
}
