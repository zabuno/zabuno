<?php

declare(strict_types=1);

namespace App\Infrastructure\Platform\Persistence;

use App\Application\Platform\Port\PlatformWorkspaceQueryPort;
use Illuminate\Support\Facades\DB;

final class EloquentPlatformWorkspaceQuery implements PlatformWorkspaceQueryPort
{
    public function search(string $query): array
    {
        $builder = DB::table('workspaces')
            ->select(['id', 'name', 'slug', 'state'])
            ->orderBy('id');

        $trimmed = trim($query);

        if ($trimmed !== '') {
            $needle = '%'.strtolower($trimmed).'%';

            $builder->where(function ($inner) use ($needle) {
                $inner->whereRaw('LOWER(name) LIKE ?', [$needle])
                    ->orWhereRaw('LOWER(slug) LIKE ?', [$needle]);
            });
        }

        return $builder->get()->map(fn (object $row): array => [
            'id' => (int) $row->id,
            'name' => (string) $row->name,
            'slug' => (string) $row->slug,
            'state' => (string) $row->state,
        ])->all();
    }
}
