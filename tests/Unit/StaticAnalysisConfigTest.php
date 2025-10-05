<?php

use Illuminate\Support\Str;

function projectRoot(): string
{
    return dirname(__DIR__, 2);
}

function extractPhpStanIncludes(string $neonContents): array
{
    $includes = [];
    $lines = preg_split('/\R/', $neonContents);
    $withinIncludes = false;

    foreach ($lines as $line) {
        $trimmed = Str::of($line)->trim();

        if ($trimmed->exactly('includes:')) {
            $withinIncludes = true;
            continue;
        }

        if ($withinIncludes) {
            if (! $trimmed->startsWith('-')) {
                break;
            }

            $path = (string) $trimmed
                ->after('-')
                ->trim()
                ->trim("'\"");

            if ($path !== '') {
                $includes[] = $path;
            }
        }
    }

    return $includes;
}

test('phpstan configuration includes only existing files', function () {
    $phpStanPath = projectRoot().DIRECTORY_SEPARATOR.'phpstan.neon';
    expect($phpStanPath)->toBeFile();

    $includes = extractPhpStanIncludes(file_get_contents($phpStanPath));

    $missing = collect($includes)
        ->map(fn (string $relative) => projectRoot().DIRECTORY_SEPARATOR.str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $relative))
        ->filter(fn (string $absolute) => ! file_exists($absolute))
        ->values();

    expect($missing)->toBeEmpty();
});

test('phpstan include parser detects missing files', function () {
    $fixture = <<<'NEON'
includes:
    - vendor/package/present.neon
    - vendor/package/missing.neon
NEON;

    $paths = extractPhpStanIncludes($fixture);

    expect($paths)->toBe(['vendor/package/present.neon', 'vendor/package/missing.neon']);

    $directory = projectRoot().DIRECTORY_SEPARATOR.'vendor/package';
    $existingPath = $directory.DIRECTORY_SEPARATOR.'present.neon';

    if (! is_dir($directory)) {
        mkdir($directory, 0777, true);
    }

    file_put_contents($existingPath, '');

    try {
        $missing = collect($paths)
            ->map(fn (string $relative) => projectRoot().DIRECTORY_SEPARATOR.str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $relative))
            ->filter(fn (string $absolute) => ! file_exists($absolute))
            ->values();

        expect($missing)->toHaveCount(1)
            ->and($missing->first())->toEndWith('missing.neon');
    } finally {
        if (file_exists($existingPath)) {
            unlink($existingPath);
        }

        if (is_dir($directory)) {
            @rmdir($directory);
        }
    }
});
