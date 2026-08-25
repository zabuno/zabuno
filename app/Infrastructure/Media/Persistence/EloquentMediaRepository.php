<?php

declare(strict_types=1);

namespace App\Infrastructure\Media\Persistence;

use App\Application\Media\Dto\MediaAssetSummary;
use App\Application\Media\Dto\MediaIntake;
use App\Application\Media\Dto\ScannableMediaAsset;
use App\Application\Media\Port\MediaRepositoryPort;
use App\Domain\Media\MediaAssetStatus;
use App\Models\MediaAsset;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

final class EloquentMediaRepository implements MediaRepositoryPort
{
    private const QUARANTINE_DISK = 'local';

    public function intakeToQuarantine(int $workspaceId, MediaIntake $intake): MediaAssetSummary
    {
        $quarantineName = Str::uuid()->toString();
        $diskPath = "quarantine/{$workspaceId}/{$quarantineName}";

        $stream = fopen($intake->temporaryPath, 'rb');

        if ($stream === false) {
            throw new RuntimeException("Unable to open temporary media file at [{$intake->temporaryPath}].");
        }

        try {
            $put = Storage::disk(self::QUARANTINE_DISK)->put($diskPath, $stream);
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }

        if ($put === false) {
            throw new RuntimeException("Unable to write media file to quarantine at [{$diskPath}].");
        }

        try {
            $asset = MediaAsset::query()->create([
                'workspace_id' => $workspaceId,
                'disk_path' => $diskPath,
                'original_name' => $intake->originalName,
                'mime_type' => $intake->detectedMimeType,
                'size_bytes' => $intake->sizeBytes,
                'alt_text' => $intake->altText,
                'slot' => $intake->slot,
                'status' => MediaAssetStatus::Quarantined->value,
            ]);
        } catch (Throwable $exception) {
            Storage::disk(self::QUARANTINE_DISK)->delete($diskPath);

            throw $exception;
        }

        return $this->toSummary($asset);
    }

    public function listForWorkspace(int $workspaceId): array
    {
        return MediaAsset::query()
            ->where('workspace_id', $workspaceId)
            ->orderBy('id')
            ->get()
            ->map(fn (MediaAsset $asset) => $this->toSummary($asset))
            ->all();
    }

    public function find(int $id): ?MediaAssetSummary
    {
        $asset = MediaAsset::query()->find($id);

        return $asset === null ? null : $this->toSummary($asset);
    }

    public function delete(int $id): void
    {
        $asset = MediaAsset::query()->find($id);

        if ($asset === null) {
            return;
        }

        $deleted = Storage::disk(self::QUARANTINE_DISK)->delete((string) $asset->disk_path);

        if ($deleted === false) {
            throw new RuntimeException("Unable to delete media file from disk at [{$asset->disk_path}].");
        }

        $asset->delete();
    }

    public function claimQuarantinedForScanning(int $workspaceId, int $assetId): ?ScannableMediaAsset
    {
        $asset = MediaAsset::query()
            ->where('id', $assetId)
            ->where('workspace_id', $workspaceId)
            ->first();

        if ($asset === null) {
            return null;
        }

        $claimed = MediaAsset::query()
            ->where('id', $assetId)
            ->where('workspace_id', $workspaceId)
            ->where('status', MediaAssetStatus::Quarantined->value)
            ->update(['status' => MediaAssetStatus::Scanning->value]);

        if ($claimed === 0) {
            return null;
        }

        return new ScannableMediaAsset(
            id: (int) $asset->getKey(),
            workspaceId: (int) $asset->workspace_id,
            diskPath: (string) $asset->disk_path,
        );
    }

    public function markRejectedIfScanning(int $workspaceId, int $assetId): void
    {
        MediaAsset::query()
            ->where('id', $assetId)
            ->where('workspace_id', $workspaceId)
            ->where('status', MediaAssetStatus::Scanning->value)
            ->update(['status' => MediaAssetStatus::Rejected->value]);
    }

    private function toSummary(MediaAsset $asset): MediaAssetSummary
    {
        return new MediaAssetSummary(
            id: (int) $asset->getKey(),
            workspaceId: (int) $asset->workspace_id,
            altText: (string) $asset->alt_text,
            slot: (string) $asset->slot,
            status: (string) $asset->status,
        );
    }
}
