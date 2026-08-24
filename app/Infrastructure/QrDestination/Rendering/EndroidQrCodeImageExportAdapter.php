<?php

declare(strict_types=1);

namespace App\Infrastructure\QrDestination\Rendering;

use App\Application\QrDestination\Dto\QrRenderedImage;
use App\Application\QrDestination\Port\QrCodeImageExportPort;
use App\Domain\QrDestination\QrLayout;
use DivisionByZeroError;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Color\Color;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\Exception\ValidationException;
use Endroid\QrCode\RoundBlockSizeMode;
use Endroid\QrCode\Writer\PngWriter;
use Endroid\QrCode\Writer\Result\ResultInterface;
use Endroid\QrCode\Writer\SvgWriter;
use RuntimeException;

final class EndroidQrCodeImageExportAdapter implements QrCodeImageExportPort
{
    /**
     * Fixed, deterministic candidate profiles, tried in order. The first
     * one that both renders and passes the writer's own real
     * validateResult() decode-back check wins — never a random retry, and
     * the same input data always yields the same first-passing profile
     * (and therefore byte-identical output). A ValidationException or a
     * DivisionByZeroError from the decode-back path (the latter can be
     * thrown by the underlying Zxing perspective transform on some
     * candidates) are both treated as a decode-validation miss for that
     * candidate.
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
     * Endroid's SvgWriter does not support validateResult() (it throws
     * ValidationException unconditionally), so the SVG payload cannot be
     * decode-validated directly. Instead the exact same $data is first
     * validated through the real PngWriter validateResult() decode-back
     * path (the already-proven PNG path) to pick a deterministic, provably
     * correct profile, and only then is that same profile rendered again
     * through SvgWriter — so the returned SVG is never emitted without a
     * real decode-back proof for its underlying data.
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

        foreach (self::PROFILES as $profile) {
            $margin = $layout?->quietZonePixels ?? $profile['margin'];

            try {
                $result = (new Builder(
                    writer: new PngWriter,
                    validateResult: true,
                    data: $data,
                    encoding: new Encoding('ISO-8859-1'),
                    errorCorrectionLevel: $profile['errorCorrectionLevel'],
                    size: $profile['size'],
                    margin: $margin,
                    roundBlockSizeMode: RoundBlockSizeMode::Margin,
                    foregroundColor: $foreground,
                    backgroundColor: $background,
                ))->build();
            } catch (ValidationException|DivisionByZeroError) {
                continue;
            }

            return [
                ['size' => $profile['size'], 'margin' => $margin, 'errorCorrectionLevel' => $profile['errorCorrectionLevel']],
                $result,
            ];
        }

        throw new RuntimeException('QR rendering failed PNG decode-back validation for every candidate profile.');
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
