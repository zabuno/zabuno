<?php

declare(strict_types=1);

namespace App\Infrastructure\QrDestination\Rendering;

use App\Application\QrDestination\Dto\QrRenderedImage;
use App\Application\QrDestination\Port\QrCodeImageExportPort;
use App\Domain\QrDestination\QrLayout;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Color\Color;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\RoundBlockSizeMode;
use Endroid\QrCode\Writer\PngWriter;
use Endroid\QrCode\Writer\Result\ResultInterface;
use Endroid\QrCode\Writer\SvgWriter;
use RuntimeException;
use Throwable;
use Zxing\QrReader;

final class EndroidQrCodeImageExportAdapter implements QrCodeImageExportPort
{
    /**
     * The decode-back check below asks the reader to try hard.
     *
     * Without this hint Zxing's finder-pattern search only samples every
     * `(3 * height) / (4 * 57)`-th row of the image — for our profiles that
     * is every 6th row — and a QR whose finder patterns happen to fall
     * between two sampled rows is reported as unreadable even though it is
     * perfectly valid. Which payloads land badly depends on the QR version,
     * so this is deterministic per payload, not intermittent — the same
     * token fails every time, and only the randomness of tokens made it look
     * like a flake.
     *
     * Measured on 2026-08-26 over 16 000 random tokens, without the hint:
     * 7.3% rejected on the first profile and 8 rejected on all four at once
     * (1 in 2000), each of which the controllers turned into an HTTP 500.
     * The same 16 000 with the hint: zero rejections.
     *
     * This does not soften the check: the decoded text must still equal the
     * exact payload byte for byte. It only stops a sampling shortcut in the
     * verifier from condemning an image that is fine.
     */
    private const array DECODER_HINTS = ['TRY_HARDER' => true];

    /**
     * Fixed, deterministic candidate profiles, tried in order. The first one
     * that both renders and passes the real decode-back check wins — never a
     * random retry, and the same input data always yields the same
     * first-passing profile (and therefore byte-identical output). A
     * candidate that fails to render, or that cannot be decoded back to the
     * exact payload, is skipped and the next one is tried.
     *
     * @var list<array{size: int, margin: int, errorCorrectionLevel: ErrorCorrectionLevel}>
     */
    private const array PROFILES = [
        ['size' => 480, 'margin' => 16, 'errorCorrectionLevel' => ErrorCorrectionLevel::High],
        ['size' => 600, 'margin' => 24, 'errorCorrectionLevel' => ErrorCorrectionLevel::Medium],
        ['size' => 720, 'margin' => 32, 'errorCorrectionLevel' => ErrorCorrectionLevel::Low],
        ['size' => 400, 'margin' => 40, 'errorCorrectionLevel' => ErrorCorrectionLevel::Low],
    ];

    public function renderPng(string $data, ?QrLayout $layout = null): QrRenderedImage
    {
        [, $result] = $this->validatePngAndPickProfile($data, $layout);

        return new QrRenderedImage(
            bytes: $result->getString(),
            mimeType: $result->getMimeType(),
        );
    }

    /**
     * Endroid's SvgWriter cannot be decode-validated: the payload only exists
     * as path geometry, and there is no SVG rasteriser here to read it back.
     * Instead the exact same $data is first proven through the real PNG
     * decode-back path to pick a deterministic, provably correct profile, and
     * only then is that same profile rendered again through SvgWriter — so
     * the returned SVG is never emitted without a real decode-back proof for
     * its underlying data.
     */
    public function renderSvg(string $data, ?QrLayout $layout = null): QrRenderedImage
    {
        [$profile] = $this->validatePngAndPickProfile($data, $layout);
        [$foreground, $background] = $this->colorsFor($layout);

        $result = (new Builder(
            writer: new SvgWriter,
            validateResult: false,
            data: $data,
            encoding: new Encoding('ISO-8859-1'),
            errorCorrectionLevel: $profile['errorCorrectionLevel'],
            size: $profile['size'],
            margin: $profile['margin'],
            roundBlockSizeMode: RoundBlockSizeMode::Margin,
            foregroundColor: $foreground,
            backgroundColor: $background,
        ))->build();

        return new QrRenderedImage(
            bytes: $result->getString(),
            mimeType: $result->getMimeType(),
        );
    }

    /**
     * @return array{0: array{size: int, margin: int, errorCorrectionLevel: ErrorCorrectionLevel}, 1: ResultInterface}
     */
    private function validatePngAndPickProfile(string $data, ?QrLayout $layout): array
    {
        [$foreground, $background] = $this->colorsFor($layout);
        $lastFailure = null;

        foreach (self::PROFILES as $profile) {
            $margin = $layout?->quietZonePixels ?? $profile['margin'];

            try {
                $result = (new Builder(
                    writer: new PngWriter,
                    // Endroid's own validateResult() would run the same
                    // reader without the try-harder hint, so the check is
                    // done here instead — see decodesBackTo().
                    validateResult: false,
                    data: $data,
                    encoding: new Encoding('ISO-8859-1'),
                    errorCorrectionLevel: $profile['errorCorrectionLevel'],
                    size: $profile['size'],
                    margin: $margin,
                    roundBlockSizeMode: RoundBlockSizeMode::Margin,
                    foregroundColor: $foreground,
                    backgroundColor: $background,
                ))->build();

                if (! $this->decodesBackTo($result->getString(), $data)) {
                    continue;
                }
            } catch (Throwable $exception) {
                // Keep the first real reason: without it the exhausted-profiles
                // exception below says only that everything failed, which is
                // exactly the blind 500 this adapter used to produce.
                $lastFailure ??= $exception;

                continue;
            }

            return [
                ['size' => $profile['size'], 'margin' => $margin, 'errorCorrectionLevel' => $profile['errorCorrectionLevel']],
                $result,
            ];
        }

        throw new RuntimeException(
            'QR rendering failed PNG decode-back validation for every candidate profile.',
            previous: $lastFailure,
        );
    }

    /**
     * Reads the rendered PNG back with the real decoder and requires it to
     * yield the exact payload. Any reader failure — an unreadable image, or
     * the DivisionByZeroError the perspective transform can throw on some
     * candidates — counts as "this candidate is not provably correct", never
     * as a rendering error, so the next profile gets its turn.
     */
    private function decodesBackTo(string $pngBytes, string $data): bool
    {
        try {
            return (new QrReader($pngBytes, QrReader::SOURCE_TYPE_BLOB))->text(self::DECODER_HINTS) === $data;
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * @return array{0: Color, 1: Color}
     */
    private function colorsFor(?QrLayout $layout): array
    {
        if ($layout === null) {
            return [new Color(0, 0, 0), new Color(255, 255, 255)];
        }

        return [$this->color($layout->foregroundRgb), $this->color($layout->backgroundRgb)];
    }

    private function color(string $hex): Color
    {
        return new Color(
            hexdec(substr($hex, 0, 2)),
            hexdec(substr($hex, 2, 2)),
            hexdec(substr($hex, 4, 2)),
        );
    }
}
