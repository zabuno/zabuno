<?php

declare(strict_types=1);

namespace App\Domain\Content;

/**
 * Bir kurumsal sayfanın yayın durumu — FF-117, yönerge §6.
 *
 * Durum makinesi bir liste değil bir SÖZLEŞMEDİR: bir sayfa taslaktan doğrudan
 * yayına atlayamaz. Atlayabilseydi kalite kapısı (içerik, tasarım, SEO,
 * erişilebilirlik) bir tavsiye olurdu ve 414 sayfalık bir programda tavsiye
 * tutulmaz.
 *
 * `approved` YAYIN DEĞİLDİR. Aradaki farkı silmek, kapıyı atlamanın en kolay
 * yolu olurdu.
 */
enum PagePublicationStatus: string
{
    case Planned = 'planned';
    case Scaffolded = 'scaffolded';
    case ContentDraft = 'content_draft';
    case ContentReview = 'content_review';
    case DesignReview = 'design_review';
    case SeoReview = 'seo_review';
    case Qa = 'qa';
    case Approved = 'approved';
    case Published = 'published';
    case Maintenance = 'maintenance';
    case Retired = 'retired';

    /** Sıradaki adım — mutlu yol. */
    private const array FORWARD = [
        'planned' => 'scaffolded',
        'scaffolded' => 'content_draft',
        'content_draft' => 'content_review',
        'content_review' => 'design_review',
        'design_review' => 'seo_review',
        'seo_review' => 'qa',
        'qa' => 'approved',
        'approved' => 'published',
    ];

    public function canMoveTo(self $target): bool
    {
        if ($this === self::Retired) {
            // Emekli sayfa geri döndürülemez. Geri gelmesi gereken bir sayfa
            // emekli edilmemeliydi; "geri al" düğmesi olan bir emeklilik,
            // emeklilik değildir.
            return false;
        }

        if ($this === self::Published) {
            return $target === self::Maintenance || $target === self::Retired;
        }

        if ($this === self::Maintenance) {
            return $target === self::Published || $target === self::Retired;
        }

        if ((self::FORWARD[$this->value] ?? null) === $target->value) {
            return true;
        }

        // Kalite kapısı tek yönlü değildir: QA ya da SEO kontrolü başarısızsa
        // sayfa önceki bir aşamaya geri döner.
        return $this->isReview() && $target->isBefore($this);
    }

    public function isPublished(): bool
    {
        return $this === self::Published;
    }

    /** Ziyaretçiye gösterilecek cümlenin anahtarı — teknik durum adı DEĞİL. */
    public function translationKey(): string
    {
        return 'site.pageState.'.$this->value;
    }

    private function isReview(): bool
    {
        return in_array($this, [
            self::ContentReview,
            self::DesignReview,
            self::SeoReview,
            self::Qa,
            self::Approved,
        ], true);
    }

    private function isBefore(self $other): bool
    {
        $order = array_keys(self::FORWARD);

        $mine = array_search($this->value, $order, true);
        $theirs = array_search($other->value, $order, true);

        return is_int($mine) && is_int($theirs) && $mine < $theirs;
    }
}
