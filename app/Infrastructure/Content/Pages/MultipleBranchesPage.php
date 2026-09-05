<?php

declare(strict_types=1);

namespace App\Infrastructure\Content\Pages;

use App\Domain\Content\Block\BlockEntry;
use App\Domain\Content\Block\BlockType;
use App\Domain\Content\Block\ContentBlock;
use App\Domain\Content\PageContent;
use App\Domain\Content\PageMetadata;

/**
 * `/urun/coklu-sube/` — çoklu şube sayfası (P0).
 *
 * BİLEREK YAZILMAYANLAR: merkezi menü yönetimi ("hepsine yayınla"), şube
 * karşılaştırma raporu, şubeler arası ciro raporu, franchise standartları,
 * şubeye bağlı personel yetkisi, tek hesapta birden çok marka, şube arşivleme
 * ve şube silme. Ölçüm 2026-09-06: bunların hiçbiri depoda yok — ve site
 * haritası bunların birkaçını P1/P2 satır olarak taşıyor. Bir sıra, bir söz
 * değildir.
 *
 * Sayfanın en çok yanıltabileceği yer "merkezi yönetim" cümlesidir: bugün
 * şubeler AYRI çalışır ve bu bir kusur değil bir karardır — ama "merkezden
 * yönet" diye yazılsaydı, zincir sahibi tam olarak var olmayan şeyi satın
 * alırdı.
 */
final class MultipleBranchesPage
{
    public static function content(): PageContent
    {
        return new PageContent(
            pageKey: 'urun.coklu-sube',
            locale: 'en',
            metadata: new PageMetadata(
                seoTitle: 'Running several branches in one account',
                metaDescription: 'Every branch keeps its own menus, prices, codes and opening hours under one business, and switching between them is one click in the top bar.',
                h1: 'Multiple branches',
                breadcrumbTitle: 'Multiple branches',
            ),
            blocks: [
                new ContentBlock(BlockType::DirectAnswer, null, [
                    new BlockEntry(
                        text: 'One business can hold as many branches as it needs, on any plan including the free one. Each branch has its own menus, its own prices, its own codes and its own opening hours, and you move between them from the top bar.',
                    ),
                ]),

                new ContentBlock(BlockType::Problem, 'A second branch is not a second copy', [
                    new BlockEntry(
                        text: 'The second location opens and nothing lines up. The lunch menu runs an hour later, two dishes are not served there at all, and the prices are not the prices of the first branch.',
                    ),
                    new BlockEntry(
                        text: 'Forced to share one menu, the answer becomes a second account: a second login, a second logo upload, a second everything, and no way to see the two together.',
                    ),
                ]),

                new ContentBlock(BlockType::Solution, 'One business, branches that stand on their own', [
                    new BlockEntry(
                        text: 'The business is one: one brand, one logo, one team, one plan. Below it, a branch is a real thing with its own address, its own time zone and its own week of opening hours.',
                    ),
                    new BlockEntry(
                        text: 'Independence is the point rather than a shortcoming. A price corrected in one branch cannot reach another by accident, because the two menus were never the same rows.',
                    ),
                ]),

                new ContentBlock(BlockType::HowItWorks, 'How branches fit together', [
                    new BlockEntry(
                        term: 'One business, many branches',
                        text: 'A workspace holds one brand, and that brand holds as many branches as you add. A second brand needs a second workspace.',
                        source: 'database/migrations/2026_08_19_000001_create_brands_and_locations_tables.php',
                    ),
                    new BlockEntry(
                        term: 'The branch owns its own clock',
                        text: 'The time zone belongs to the branch, not to the business. A branch in another city closes on its own local evening.',
                        source: 'database/migrations/2026_08_28_000100_move_timezone_ownership_to_locations.php',
                    ),
                    new BlockEntry(
                        term: 'Opening hours, day by day',
                        text: 'Each branch carries a week of opening hours, and a day that is closed is stored differently from a day nobody has filled in yet.',
                        source: 'database/migrations/2026_09_06_000100_create_location_opening_hours_table.php',
                    ),
                    new BlockEntry(
                        term: 'Menus belong to a branch',
                        text: 'A menu is written for one branch. Several menus can live in the same branch and hand over to each other by the hour, in that branch\'s own time.',
                        source: 'database/migrations/2026_09_05_000400_allow_many_menus_per_location.php',
                    ),
                    new BlockEntry(
                        term: 'Each branch publishes for itself',
                        text: 'Publishing writes a version for that branch. Fixing tomorrow\'s menu in one branch does not reprint the other branch\'s evening.',
                        source: 'database/migrations/2026_08_22_000004_create_menu_publications_table.php',
                    ),
                    new BlockEntry(
                        term: 'Switching is one control',
                        text: 'The branch you are working in is chosen in the top bar. With a single branch the control is not shown at all, because a choice of one is not a choice.',
                        source: 'resources/js/components/workspace/shell/WorkspaceContextControls.tsx',
                    ),
                ]),

                new ContentBlock(BlockType::Capabilities, 'What you can do across branches', [
                    new BlockEntry(
                        term: 'Add a branch without asking anyone',
                        text: 'A branch is created from the branches screen with its address and time zone. There is no branch limit on any plan, and the free plan is not the exception.',
                        source: 'app/Http/Controllers/Tenancy/StoreLocationController.php',
                    ),
                    new BlockEntry(
                        term: 'Prices that differ by branch',
                        text: 'The dish is the same product across the business; what it costs is written on the branch\'s own menu. Airport prices and high-street prices live side by side without a workaround.',
                        source: 'database/migrations/2026_08_20_000002_create_menu_catalog_tables.php',
                    ),
                    new BlockEntry(
                        term: 'Codes and tables per branch',
                        text: 'Each branch has its own codes and its own table plan, so a code printed for one room never opens another branch\'s menu.',
                        source: 'database/migrations/2026_08_22_000005_create_qr_destination_tables.php',
                    ),
                    new BlockEntry(
                        term: 'Ordering switched on branch by branch',
                        text: 'Whether guests can send an order from the table is decided for each branch, and read live, so closing it at midnight takes effect at midnight.',
                        source: 'database/migrations/2026_09_06_000700_add_ordering_switch_and_frozen_plan.php',
                    ),
                    new BlockEntry(
                        term: 'Every scan knows which branch it came from',
                        text: 'Guest events are recorded against the branch, so the reports can answer for one branch instead of averaging the business into a single number.',
                        source: 'database/migrations/2026_08_22_000008_create_analytics_events_table.php',
                    ),
                    new BlockEntry(
                        term: 'Each branch\'s share of the business',
                        text: 'Where there is more than one branch, the reports show how the scans divide between them and this week\'s figure sits on each branch card.',
                        source: 'app/Application/Analytics/Dto/AnalyticsBreakdownRow.php',
                    ),
                    new BlockEntry(
                        term: 'One team over all the branches',
                        text: 'A manager or an editor is invited once and works in every branch. There is no separate login per location.',
                        source: 'app/Domain/Authorization/RolePermissions.php',
                    ),
                ]),

                new ContentBlock(BlockType::Requirements, 'What you need', [
                    new BlockEntry(
                        term: 'Permission to manage the business',
                        text: 'Adding and editing branches belongs to the owner and to managers. An editor works inside a branch without being able to create one.',
                        source: 'app/Domain/Tenancy/MembershipRole.php',
                    ),
                    new BlockEntry(
                        term: 'An address and a time zone for each branch',
                        text: 'A branch is asked for where it is and what time it is there, because opening hours and menu handover mean nothing without it.',
                        source: 'app/Models/Location.php',
                    ),
                    new BlockEntry(
                        term: 'A plan, only for the reports and the team',
                        text: 'Branches themselves are not sold. Reading the reports needs a plan with analytics, and inviting a colleague needs the plan that opens invitations.',
                        source: 'app/Http/Controllers/Team/StoreTeamInvitationController.php',
                    ),
                ]),

                new ContentBlock(BlockType::Limitations, 'What it does not do', [
                    new BlockEntry(
                        term: 'No central menu pushed to every branch',
                        text: 'There is no "publish to all branches" and no copying a menu from one branch into another. A second branch\'s menu is written in that branch.',
                        source: 'database/migrations/2026_08_22_000004_create_menu_publications_table.php',
                    ),
                    new BlockEntry(
                        term: 'No branch comparison report',
                        text: 'You can read one branch at a time, and see how the scans split between branches. There is no table that puts the branches side by side on every measure.',
                        source: 'app/Application/Analytics/Dto/AnalyticsSummary.php',
                    ),
                    new BlockEntry(
                        term: 'Nothing about money, in any branch',
                        text: 'The reports count attention: scans, opens, dish views. There is no turnover, no takings and therefore no cross-branch revenue report.',
                        source: 'app/Application/Analytics/Port/AnalyticsRepositoryPort.php',
                    ),
                    new BlockEntry(
                        term: 'A role is not limited to one branch',
                        text: 'A manager is a manager everywhere in the business. You cannot give someone the keys to a single branch.',
                        source: 'database/migrations/2026_08_19_000000_create_workspaces_and_workspace_memberships_tables.php',
                    ),
                    new BlockEntry(
                        term: 'One brand per account',
                        text: 'Branches share one name and one logo. Two different brands need two separate accounts.',
                        source: 'database/migrations/2026_08_19_000001_create_brands_and_locations_tables.php',
                    ),
                    new BlockEntry(
                        term: 'No franchise standards or approvals',
                        text: 'There is nothing that forces a branch to keep head office\'s wording, prices or photographs, and nothing that reviews a branch before it publishes.',
                        source: 'app/Domain/Authorization/Permission.php',
                    ),
                    new BlockEntry(
                        term: 'A branch cannot be closed or deleted',
                        text: 'Branches are created and edited. Taking one out of the account is not something the screens do today.',
                        source: 'routes/api/tenancy.php',
                    ),
                ]),

                new ContentBlock(BlockType::Faq, 'Questions owners ask', [
                    new BlockEntry(
                        term: 'How many branches can I add?',
                        text: 'As many as you have. There is no branch limit on any plan, and adding one does not change what the plan costs.',
                    ),
                    new BlockEntry(
                        term: 'Can two branches have different prices?',
                        text: 'Yes, and it needs no workaround. The price is written on the branch\'s own menu, so the two cannot drift into each other.',
                    ),
                    new BlockEntry(
                        term: 'Can I write the menu once and send it to every branch?',
                        text: 'No. Each branch\'s menu is written in that branch. This is the honest answer today, and the one worth knowing before you open the fourth location.',
                    ),
                    new BlockEntry(
                        term: 'Can I compare my branches?',
                        text: 'You can see how the scans divide between them and this week\'s figure on each branch card. There is no full branch-by-branch comparison table.',
                    ),
                    new BlockEntry(
                        term: 'Can I give a branch manager access to only their branch?',
                        text: 'No. Roles apply across the whole business; a manager can see every branch in it.',
                    ),
                    new BlockEntry(
                        term: 'Do my branches share a logo?',
                        text: 'Yes. One account carries one brand. Two different brands need two accounts.',
                    ),
                ]),

                new ContentBlock(BlockType::Cta, 'Branches cost nothing extra', [
                    new BlockEntry(
                        text: 'See what the plans change, and what stays open without one.',
                        href: '/pricing',
                        term: 'See the plans',
                    ),
                ]),

                new ContentBlock(BlockType::Related, 'Related pages', [
                    new BlockEntry(text: 'Tables and QR codes', pageKey: 'urun.masa-ve-qr-yonetimi'),
                    new BlockEntry(text: 'Analytics', pageKey: 'urun.analitik'),
                ]),
            ],
        );
    }
}
