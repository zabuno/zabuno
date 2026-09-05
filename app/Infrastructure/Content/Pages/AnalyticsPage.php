<?php

declare(strict_types=1);

namespace App\Infrastructure\Content\Pages;

use App\Domain\Content\Block\BlockEntry;
use App\Domain\Content\Block\BlockType;
use App\Domain\Content\Block\ContentBlock;
use App\Domain\Content\PageContent;
use App\Domain\Content\PageMetadata;

/**
 * `/urun/analitik/` — analitik ve raporlama sayfası (P0).
 *
 * BİLEREK YAZILMAYANLAR: ciro/satış raporu, rapor dışa aktarımı (CSV/PDF),
 * şube karşılaştırma ekranı, misafir demografisi, "önerilen aksiyonlar"
 * merkezi. Ölçüm 2026-09-05: bunlar depoda yok. Ölçülen beş olay dışında bir
 * şey ölçüldüğü de iddia edilmiyor.
 */
final class AnalyticsPage
{
    public static function content(): PageContent
    {
        return new PageContent(
            pageKey: 'urun.analitik',
            locale: 'en',
            metadata: new PageMetadata(
                seoTitle: 'Menu analytics and reporting',
                metaDescription: 'See how many people scanned, how many opened the menu, which dishes they looked at, and what they searched for and did not find.',
                h1: 'Analytics',
                breadcrumbTitle: 'Analytics',
            ),
            blocks: [
                new ContentBlock(BlockType::DirectAnswer, null, [
                    new BlockEntry(
                        text: 'Zabuno counts five things a digital menu can honestly know: codes scanned, menus opened, dishes looked at, searches that found nothing, and orders sent from the table. Reports are built from those counts and from nothing else.',
                    ),
                ]),

                new ContentBlock(BlockType::Problem, 'A paper menu tells you nothing', [
                    new BlockEntry(
                        text: 'You can count plates leaving the kitchen. You cannot count the guests who read the dessert page and ordered nothing, or the ones who searched for a vegetarian dish you do not serve and put the phone down.',
                    ),
                    new BlockEntry(
                        text: 'The demand you never see is the demand you never meet, and a menu is redesigned on a feeling because there was nothing else to redesign it on.',
                    ),
                ]),

                new ContentBlock(BlockType::Solution, 'Counted where the menu is served', [
                    new BlockEntry(
                        text: 'The counting happens on Zabuno\'s side, as the menu is served. Your guests are not handed to an advertising network in order for you to learn that forty people opened the menu.',
                    ),
                    new BlockEntry(
                        text: 'The reports are also allowed to say "not yet". The dish report and the trend view withhold their figures below a minimum number of distinct visitors rather than dress up three people as a pattern, because a report that is wrong once is never read again.',
                    ),
                ]),

                new ContentBlock(BlockType::HowItWorks, 'How it works', [
                    new BlockEntry(
                        term: 'Five events, named once',
                        text: 'Scan, menu opened, dish viewed, search with no result, order sent. The list is fixed in code, so a typo cannot quietly split a report in two.',
                        source: 'app/Domain/Analytics/AnalyticsEventType.php',
                    ),
                    new BlockEntry(
                        term: 'Recorded by the server',
                        text: 'Guest events are posted to a rate-limited endpoint on your own domain and written to your workspace\'s rows.',
                        source: 'app/Http/Controllers/Analytics/StoreGuestMenuEventsController.php',
                    ),
                    new BlockEntry(
                        term: 'Visitors are summarised, not identified',
                        text: 'A visitor is represented by a derived key that cannot be turned back into a person. Six opens from one table are not six guests.',
                        source: 'database/migrations/2026_08_28_000200_add_visitor_key_to_analytics_events.php',
                    ),
                    new BlockEntry(
                        term: 'Read as a summary or a shape',
                        text: 'One request answers "how much in this period", another answers "what shape did the period have" - by day, by hour, and against the period before it.',
                        source: 'app/Application/Analytics/Dto/AnalyticsTimeSeries.php',
                    ),
                    new BlockEntry(
                        term: 'Turned into menu decisions',
                        text: 'The menu report ranks dishes by how many different people opened them and lists the ones nobody opened at all.',
                        source: 'app/Http/Controllers/Analytics/ShowMenuEngineeringController.php',
                    ),
                ]),

                new ContentBlock(BlockType::Capabilities, 'What the reports answer', [
                    new BlockEntry(
                        term: 'How many scanned, how many opened',
                        text: 'Both counts, plus the ratio between them, so a code that is scanned but never opens tells you something is wrong with the code or the connection.',
                        source: 'app/Application/Analytics/Dto/AnalyticsSummary.php',
                    ),
                    new BlockEntry(
                        term: 'Roughly how many people',
                        text: 'An approximate count of distinct visitors, which is a different question from how many times the menu was opened.',
                        source: 'app/Application/Analytics/Dto/AnalyticsSummary.php',
                    ),
                    new BlockEntry(
                        term: 'Which branch and which code',
                        text: 'The same period broken down by location and by individual QR code, so a quiet corner of the room is visible as a quiet corner.',
                        source: 'app/Application/Analytics/Dto/AnalyticsBreakdownRow.php',
                    ),
                    new BlockEntry(
                        term: 'Day by day, and against last time',
                        text: 'Daily buckets across the range and a comparison with the previous period of the same length.',
                        source: 'app/Application/Analytics/Dto/AnalyticsComparison.php',
                    ),
                    new BlockEntry(
                        term: 'Which hours are busy',
                        text: 'An hour-by-hour view of when menus are opened. Cells with too few visitors to be meaningful are withheld and counted, not shown as zero.',
                        source: 'app/Application/Analytics/Dto/AnalyticsHourCell.php',
                    ),
                    new BlockEntry(
                        term: 'Which dishes people actually look at',
                        text: 'Dishes ranked by distinct viewers, and the dishes nobody opened, so the menu can be shortened on evidence instead of instinct.',
                        source: 'app/Http/Controllers/Analytics/ShowMenuEngineeringController.php',
                    ),
                    new BlockEntry(
                        term: 'What guests looked for and did not find',
                        text: 'Search terms that returned nothing, counted by how many different people searched them. This is the only demand a menu cannot show you any other way.',
                        source: 'app/Application/Analytics/Port/AnalyticsRepositoryPort.php',
                    ),
                ]),

                new ContentBlock(BlockType::Requirements, 'What you need', [
                    new BlockEntry(
                        term: 'A plan that includes reporting',
                        text: 'Analytics belongs to a paid plan. Without it the menu, the codes and the guest page keep working; only the reports are closed.',
                        source: 'app/Domain/Entitlement/Entitlement.php',
                    ),
                    new BlockEntry(
                        term: 'Permission inside the workspace',
                        text: 'A team member needs the analytics permission. Without it the report is not merely empty, it is not there.',
                        source: 'app/Http/Controllers/Analytics/ShowAnalyticsSummaryController.php',
                    ),
                    new BlockEntry(
                        term: 'Enough visitors for the menu report',
                        text: 'The dish report needs at least five distinct visitors in the range before it shows figures, and says so instead of showing zeros.',
                        source: 'app/Http/Controllers/Analytics/ShowMenuEngineeringController.php',
                    ),
                    new BlockEntry(
                        term: 'Scripts, for part of the picture',
                        text: 'Scans and menu opens are recorded by the server. Dish views and searches are reported by the guest\'s browser, so a guest who blocks scripts still reads the menu but leaves no dish or search row.',
                        source: 'app/Http/Controllers/Analytics/StoreGuestMenuEventsController.php',
                    ),
                ]),

                new ContentBlock(BlockType::Limitations, 'What it does not do', [
                    new BlockEntry(
                        term: 'Three ranges only',
                        text: 'Today, the last seven days and the last thirty days. There is no custom date range and no month-by-month history.',
                        source: 'app/Http/Controllers/Analytics/ShowAnalyticsSummaryController.php',
                    ),
                    new BlockEntry(
                        term: 'No revenue or sales reporting',
                        text: 'Reports count attention, not money. There is no turnover figure, no average spend and no best-selling list.',
                        source: 'app/Application/Analytics/Port/AnalyticsRepositoryPort.php',
                    ),
                    new BlockEntry(
                        term: 'No export',
                        text: 'Reports are read on screen. There is no spreadsheet or document download.',
                        source: 'resources/js/components/workspace/pages/AnalyticsPage.tsx',
                    ),
                    new BlockEntry(
                        term: 'Nothing about who the guest is',
                        text: 'No age, no gender, no location, no returning-visitor history. A visitor is a derived key inside one workspace and nothing more.',
                        source: 'database/migrations/2026_08_22_000008_create_analytics_events_table.php',
                    ),
                    new BlockEntry(
                        term: 'No automatic recommendations',
                        text: 'The reports show what happened. Deciding what to do about it is still your job.',
                        source: 'app/Http/Controllers/Analytics/ShowMenuEngineeringController.php',
                    ),
                ]),

                new ContentBlock(BlockType::Faq, 'Questions owners ask', [
                    new BlockEntry(
                        term: 'How far back can I look?',
                        text: 'Today, the last seven days, or the last thirty. Those are the three ranges the reports accept.',
                    ),
                    new BlockEntry(
                        term: 'Why does the report say there is not enough data?',
                        text: 'Because fewer than five different people opened dishes in that range. Three visitors are not a pattern, and a number built on them would send you rearranging the menu for nothing.',
                    ),
                    new BlockEntry(
                        term: 'Can I see how much money the menu made?',
                        text: 'No. Analytics counts scans, opens, dish views, searches and orders sent. It does not report turnover.',
                    ),
                    new BlockEntry(
                        term: 'What is the most useful report?',
                        text: 'Usually the searches that returned nothing. It is the only place a menu can tell you about a dish you do not serve.',
                    ),
                    new BlockEntry(
                        term: 'Are my guests tracked by an advertising network?',
                        text: 'These counts are recorded by Zabuno on your own domain and stay inside your workspace. A visitor is a derived key, not a person.',
                    ),
                    new BlockEntry(
                        term: 'Can I download the numbers?',
                        text: 'Not today. Reports are read on screen; there is no export.',
                    ),
                ]),

                new ContentBlock(BlockType::Cta, 'Reporting is on the paid plans', [
                    new BlockEntry(
                        text: 'See which plan opens the reports, and what stays open without one.',
                        href: '/pricing',
                        term: 'See the plans',
                    ),
                ]),

                new ContentBlock(BlockType::Related, 'Related pages', [
                    new BlockEntry(text: 'Menu management', pageKey: 'urun.menu-yonetimi'),
                    new BlockEntry(text: 'Tables and QR codes', pageKey: 'urun.masa-ve-qr-yonetimi'),
                ]),
            ],
        );
    }
}
