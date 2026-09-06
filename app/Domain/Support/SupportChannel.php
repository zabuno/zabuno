<?php

declare(strict_types=1);

namespace App\Domain\Support;

/**
 * Talebin nereden geldiği — FF-201 (`docs/125`).
 *
 * İki kanal, iki ayrı kimlik kaynağı: kamu formunda ad ve e-posta
 * ziyaretçinin yazdığıdır; panelde ikisi de HESAPTAN gelir ve talep bir
 * çalışma alanına bağlanır. Kanalı satıra yazmak, süperadminin "bu kişi
 * müşterimiz mi, yoksa fiyat soran biri mi" sorusunu tek bakışta
 * cevaplamasını sağlar.
 */
enum SupportChannel: string
{
    case PublicContact = 'public_contact';
    case Panel = 'panel';
}
