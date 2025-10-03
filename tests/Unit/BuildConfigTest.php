<?php

function assertModuleTypeForDependency(array $manifest, string $dependency): void
{
    $requiresModule = array_key_exists($dependency, $manifest['devDependencies'] ?? [])
        || array_key_exists($dependency, $manifest['dependencies'] ?? []);

    if ($requiresModule && ($manifest['type'] ?? null) !== 'module') {
        throw new InvalidArgumentException("{$dependency} requires package.json to declare type \"module\".");
    }
}

it('keeps laravel vite plugin usable by running vite config in esm mode', function () {
    $manifestPath = dirname(__DIR__, 2).DIRECTORY_SEPARATOR.'package.json';

    $manifest = json_decode(
        file_get_contents($manifestPath),
        true,
        flags: JSON_THROW_ON_ERROR,
    );

    assertModuleTypeForDependency($manifest, 'laravel-vite-plugin');

    expect($manifest)
        ->toHaveKey('type', 'module');
});

it('throws a helpful exception when laravel vite plugin is configured without module type', function () {
    $invalidManifest = [
        'devDependencies' => [
            'laravel-vite-plugin' => '^1.0.0',
        ],
    ];

    expect(fn () => assertModuleTypeForDependency($invalidManifest, 'laravel-vite-plugin'))
        ->toThrow(InvalidArgumentException::class, 'requires package.json to declare type "module"');
});
