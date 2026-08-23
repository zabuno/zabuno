<?php

declare(strict_types=1);

namespace Tests\Feature\Media;

use App\Application\Media\Dto\MediaIntake;
use App\Infrastructure\Media\Persistence\EloquentMediaRepository;
use App\Models\MediaAsset;
use App\Models\User;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;

/**
 * RED: EloquentMediaRepository currently ignores a `false` return from the
 * "local" disk's put()/delete() calls (see EloquentMediaRepository::
 * intakeToQuarantine and ::delete, which never check the boolean the
 * Illuminate\Contracts\Filesystem\Filesystem contract returns). Both tests
 * below prove that silent-failure gap directly against the repository,
 * bypassing HTTP, with a genuine mock disk that returns false and does not
 * leak across tests (Storage::fake() is reset per test by the framework;
 * the shouldReceive() expectation is scoped to the single Storage::disk()
 * call each test makes).
 *
 * Requirement IDs: MEDIA-STORAGE-PUT-FAILURE-01, MEDIA-STORAGE-DELETE-FAILURE-01.
 */
final class MediaStorageFailureTest extends TestCase
{
    use RefreshDatabase;

    private function persistedWorkspaceId(string $slugSeed): int
    {
        $owner = User::factory()->create(['email_verified_at' => now()]);

        return (int) DB::table('workspaces')->insertGetId([
            'name' => 'Zeytin Restoranları',
            'slug' => $slugSeed,
            'state' => 'active',
            'created_by' => $owner->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_intake_to_quarantine_throws_and_persists_no_metadata_row_when_storage_put_returns_false(): void
    {
        $workspaceId = $this->persistedWorkspaceId('zeytin-media-storage-put-failure');
        $temporaryPath = tempnam(sys_get_temp_dir(), 'media-storage-failure-');
        self::assertNotFalse($temporaryPath, 'Precondition: a real temporary file must be creatable.');
        file_put_contents($temporaryPath, 'not-actually-persisted-bytes');

        $diskMock = $this->createMock(Filesystem::class);
        $diskMock->expects(self::once())
            ->method('put')
            ->willReturn(false);
        $diskMock->expects(self::never())->method('delete');

        Storage::shouldReceive('disk')
            ->once()
            ->with('local')
            ->andReturn($diskMock);

        $repository = new EloquentMediaRepository();

        $intake = new MediaIntake(
            temporaryPath: $temporaryPath,
            originalName: 'logo.jpg',
            detectedMimeType: 'image/jpeg',
            sizeBytes: 29,
            altText: 'Zeytin Restoranı logosu',
            slot: 'menu',
        );

        try {
            $threw = false;

            try {
                $repository->intakeToQuarantine($workspaceId, $intake);
            } catch (RuntimeException) {
                $threw = true;
            }

            self::assertTrue(
                $threw,
                'MEDIA-STORAGE-PUT-FAILURE-01: intakeToQuarantine Storage::put() false döndürdüğünde RuntimeException fırlatmalı.'
            );

            self::assertSame(
                0,
                DB::table('media_assets')->count(),
                'MEDIA-STORAGE-PUT-FAILURE-01: disk yazımı başarısız olduğunda media_assets tablosunda hiçbir satır oluşmamalı.'
            );
        } finally {
            @unlink($temporaryPath);
        }
    }

    public function test_delete_throws_and_leaves_metadata_row_not_soft_deleted_when_storage_delete_returns_false(): void
    {
        $workspaceId = $this->persistedWorkspaceId('zeytin-media-storage-delete-failure');

        $asset = MediaAsset::query()->create([
            'workspace_id' => $workspaceId,
            'disk_path' => 'quarantine/1/existing-asset',
            'original_name' => 'logo.jpg',
            'mime_type' => 'image/jpeg',
            'size_bytes' => 29,
            'alt_text' => 'Zeytin Restoranı logosu',
            'slot' => 'menu',
            'status' => 'quarantined',
        ]);

        $diskMock = $this->createMock(Filesystem::class);
        $diskMock->expects(self::once())
            ->method('delete')
            ->with('quarantine/1/existing-asset')
            ->willReturn(false);
        $diskMock->expects(self::never())->method('put');

        Storage::shouldReceive('disk')
            ->once()
            ->with('local')
            ->andReturn($diskMock);

        $repository = new EloquentMediaRepository();

        $threw = false;

        try {
            $repository->delete((int) $asset->getKey());
        } catch (RuntimeException) {
            $threw = true;
        }

        self::assertTrue(
            $threw,
            'MEDIA-STORAGE-DELETE-FAILURE-01: delete() Storage::delete() false döndürdüğünde RuntimeException fırlatmalı.'
        );

        self::assertNull(
            DB::table('media_assets')->where('id', $asset->getKey())->value('deleted_at'),
            'MEDIA-STORAGE-DELETE-FAILURE-01: disk silme başarısız olduğunda metadata satırı soft-delete edilmemeli.'
        );
    }
}
