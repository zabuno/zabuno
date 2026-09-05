<?php

namespace Tests\Unit\Infrastructure;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

class ComposerValidateContractTest extends TestCase
{
    private function repoRoot(): string
    {
        return dirname(__DIR__, 3);
    }

    private function runComposerValidate(bool $strict): Process
    {
        $command = ['composer', 'validate'];
        if ($strict) {
            $command[] = '--strict';
        }

        $process = new Process($command, $this->repoRoot(), [
            'COMPOSER_DISABLE_NETWORK' => '1',
            'COMPOSER_NO_INTERACTION' => '1',
        ]);
        $process->run();

        return $process;
    }

    public function test_plain_composer_validate_exits_zero_on_current_composer_files(): void
    {
        $process = $this->runComposerValidate(strict: false);

        $this->assertSame(
            0,
            $process->getExitCode(),
            "composer validate (non-strict) must exit 0.\nSTDOUT:\n{$process->getOutput()}\nSTDERR:\n{$process->getErrorOutput()}"
        );
    }

    public function test_strict_composer_validate_exits_nonzero_with_only_known_advisory_classes(): void
    {
        $process = $this->runComposerValidate(strict: true);

        $this->assertNotSame(
            0,
            $process->getExitCode(),
            "composer validate --strict is expected to fail while advisory warnings remain outstanding.\nSTDOUT:\n{$process->getOutput()}\nSTDERR:\n{$process->getErrorOutput()}"
        );

        $output = $process->getOutput().$process->getErrorOutput();

        $this->assertMatchesRegularExpression(
            '/No license specified/i',
            $output,
            "Expected the missing-license advisory to remain present.\nOutput:\n{$output}"
        );

        $exactConstraintLines = array_values(array_filter(
            preg_split('/\r?\n/', $output),
            static fn (string $line): bool => str_contains($line, 'exact version constraints')
        ));

        $this->assertNotEmpty(
            $exactConstraintLines,
            "Expected at least one exact-version-constraint advisory to remain present.\nOutput:\n{$output}"
        );

        foreach ($exactConstraintLines as $line) {
            $this->assertMatchesRegularExpression(
                '/^- require\.[a-z0-9_\-\/]+ : exact version constraints \([^)]+\) should be avoided if the package follows semantic versioning$/',
                trim($line),
                "Unexpected exact-version-constraint advisory shape.\nLine:\n{$line}"
            );
        }
    }

    public function test_ci_workflow_composer_validate_step_does_not_use_strict(): void
    {
        $runCommands = $this->workflowRunCommands();

        $validateCommand = null;
        foreach ($runCommands as $runCommand) {
            if (preg_match('/\bcomposer\s+validate\b/', $runCommand)) {
                $validateCommand = $runCommand;
                break;
            }
        }

        $this->assertNotNull(
            $validateCommand,
            'No workflow "run:" step invoking "composer validate" was found in .github/workflows/ci.yml.'
        );

        $this->assertDoesNotMatchRegularExpression(
            '/\bcomposer\s+validate\b[^\n]*--strict/',
            $validateCommand,
            "The CI composer validate step must not use --strict.\nrun: {$validateCommand}"
        );
    }

    public function test_ci_workflow_composer_validate_step_precedes_composer_install(): void
    {
        $runCommands = $this->workflowRunCommands();

        $validateIndex = null;
        $installIndex = null;

        foreach ($runCommands as $index => $runCommand) {
            if ($validateIndex === null && preg_match('/\bcomposer\s+validate\b/', $runCommand)) {
                $validateIndex = $index;
            }

            if ($installIndex === null && preg_match('/\bcomposer\s+install\b/', $runCommand)) {
                $installIndex = $index;
            }
        }

        $this->assertNotNull($validateIndex, 'No "composer validate" step found in .github/workflows/ci.yml.');
        $this->assertNotNull($installIndex, 'No "composer install" step found in .github/workflows/ci.yml.');

        $this->assertLessThan(
            $installIndex,
            $validateIndex,
            'The composer validate step must appear before the composer install step in .github/workflows/ci.yml.'
        );
    }

    public function test_ci_workflow_build_step_precedes_laravel_test_step(): void
    {
        $runCommands = $this->workflowRunCommands();

        $buildIndex = null;
        $laravelTestIndex = null;
        $buildCount = 0;

        foreach ($runCommands as $index => $runCommand) {
            /*
                DESEN TAM ADI EŞLEŞTİRİR — "build" ile BAŞLAYAN her betiği değil.

                `\bnpm\s+run\s+build\b` deseni `npm run build-storybook`
                komutunu da yakalıyordu, çünkü kelime sınırı "build" ile tire
                arasında da geçerli. Sonuç: mobil denetim adımı eklendiğinde bu
                test "iki derleme adımı var" diye kırıldı — oysa ikinci komut
                bambaşka bir betikti.

                Testin ölçtüğü kural değişmedi: ÖN YÜZ derlemesi tam bir kez
                koşar. Değişen şey, kuralın doğru ifade edilmesi.
            */
            if (preg_match('/\bnpm\s+run\s+build(?![\w-])/', $runCommand)) {
                $buildCount++;

                if ($buildIndex === null) {
                    $buildIndex = $index;
                }
            }

            if ($laravelTestIndex === null && preg_match('/\bphp\s+artisan\s+test\b/', $runCommand)) {
                $laravelTestIndex = $index;
            }
        }

        $this->assertSame(
            1,
            $buildCount,
            'Expected exactly one "npm run build" step in .github/workflows/ci.yml.'
        );

        $this->assertNotNull($laravelTestIndex, 'No "php artisan test" step found in .github/workflows/ci.yml.');

        $this->assertLessThan(
            $laravelTestIndex,
            $buildIndex,
            'The "npm run build" step must appear before the "php artisan test" step in .github/workflows/ci.yml.'
        );
    }

    /**
     * @return array<int, string> the "run:" command bodies for each workflow step, in file order
     */
    private function workflowRunCommands(): array
    {
        $workflowPath = $this->repoRoot().'/.github/workflows/ci.yml';
        $this->assertFileExists($workflowPath, "CI workflow file is missing at {$workflowPath}");

        $lines = preg_split('/\r?\n/', file_get_contents($workflowPath));

        $runCommands = [];
        $count = count($lines);

        for ($i = 0; $i < $count; $i++) {
            if (preg_match('/^\s*run:\s*(.*)$/', $lines[$i], $matches) !== 1) {
                continue;
            }

            $inlineValue = trim($matches[1]);

            if ($inlineValue === '|' || $inlineValue === '|-' || $inlineValue === '>' || $inlineValue === '') {
                $blockLines = [];
                $baseIndent = strlen($lines[$i]) - strlen(ltrim($lines[$i]));

                for ($j = $i + 1; $j < $count; $j++) {
                    if (trim($lines[$j]) === '') {
                        $blockLines[] = '';

                        continue;
                    }

                    $lineIndent = strlen($lines[$j]) - strlen(ltrim($lines[$j]));
                    if ($lineIndent <= $baseIndent) {
                        break;
                    }

                    $blockLines[] = $lines[$j];
                }

                $runCommands[] = implode("\n", $blockLines);
            } else {
                $runCommands[] = trim($inlineValue, "'\"");
            }
        }

        return $runCommands;
    }
}
