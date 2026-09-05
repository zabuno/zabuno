<?php

declare(strict_types=1);

namespace App\Domain\Publication;

/**
 * Zamanlanmış yayının hâlleri.
 *
 * `Publishing` ara hâli ÜRÜNSEL bir gereklilikten doğar, teknik bir
 * süslemeden değil: kuyruk işçisi dakikada bir çalışır ve bir koşu uzarsa
 * ikincisi aynı kaydı görebilir. Kayıt yalnız `Pending` iken sahiplenilir;
 * sahiplenme tek bir `UPDATE ... WHERE state = 'pending'`tir ve etkilenen
 * satır sayısı 1 değilse o kayıt başkasınındır. Bu olmadan restoranın
 * menüsü aynı gece iki kez yayınlanır, iki sürüm numarası yakar ve sahip
 * "ben bir kere bastım" derken geçmişte iki satır görürdü.
 */
enum ScheduledPublicationState: string
{
    case Pending = 'pending';
    case Publishing = 'publishing';
    case Published = 'published';
    case Cancelled = 'cancelled';
    case Failed = 'failed';
}
