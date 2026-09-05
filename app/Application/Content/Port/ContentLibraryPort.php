<?php

declare(strict_types=1);

namespace App\Application\Content\Port;

use App\Domain\Content\PageContent;

/**
 * Kurumsal sayfa içeriğinin kaynağı — FF-191.
 *
 * Bir port, çünkü içerik bugün kodda yaşıyor ama orada kalmak zorunda değil.
 * `docs/105` §2.2(2) editoryal içeriğin bir gün veritabanında dile göre
 * saklanacağını söylüyor; o gün geldiğinde değişecek tek şey bu portun
 * arkasıdır. Denetleyici, şablon, şema üreticisi ve testler yerinde kalır.
 */
interface ContentLibraryPort
{
    /** İçerik yoksa `null` — ve bu bir hata değil, bir DURUMDUR. */
    public function find(string $pageKey, string $locale): ?PageContent;

    /** @return list<PageContent> */
    public function all(): array;
}
