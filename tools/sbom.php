<?php

declare(strict_types=1);

/**
 * CycloneDX 1.5 software bill of materials from composer.lock (Spec V4.3.3 §32).
 *
 * Runtime packages are `required`; development tooling is `excluded`, because
 * it never ships with the library.
 *
 * Usage: php tools/sbom.php [output]   (default: build/sbom.cdx.json)
 */

$root = dirname(__DIR__);
$read = static fn(string $file): array => json_decode((string) file_get_contents($root . '/' . $file), true, 512, JSON_THROW_ON_ERROR);

if (!is_file($root . '/composer.lock')) {
    fwrite(STDERR, "composer.lock not found: run composer install first\n");
    exit(1);
}

$lock = $read('composer.lock');
$composer = $read('composer.json');

$licenses = static fn(array $package): array => array_map(
    static fn(string $license): array => preg_match('/^[A-Za-z0-9.+-]+$/', $license) === 1
        ? ['license' => ['id' => $license]]
        : ['license' => ['name' => $license]],
    $package['license'] ?? [],
);
$component = static fn(array $package, string $scope): array => [
    'type' => 'library',
    'bom-ref' => "pkg:composer/{$package['name']}@{$package['version']}",
    'name' => $package['name'],
    'version' => $package['version'],
    'purl' => "pkg:composer/{$package['name']}@{$package['version']}",
    'scope' => $scope,
    'licenses' => $licenses($package),
];

$hex = bin2hex(random_bytes(16));
$hex[12] = '4';                          // RFC 9562 version 4
$hex[16] = '89ab'[random_int(0, 3)];     // RFC 9562 variant
$uuid = sprintf('%s-%s-%s-%s-%s', substr($hex, 0, 8), substr($hex, 8, 4), substr($hex, 12, 4), substr($hex, 16, 4), substr($hex, 20, 12));

$bom = [
    'bomFormat' => 'CycloneDX',
    'specVersion' => '1.5',
    'serialNumber' => 'urn:uuid:' . $uuid,
    'version' => 1,
    'metadata' => [
        'timestamp' => gmdate('Y-m-d\TH:i:s\Z'),
        'component' => [
            'type' => 'library',
            'bom-ref' => "pkg:composer/{$composer['name']}",
            'name' => $composer['name'],
            'purl' => "pkg:composer/{$composer['name']}",
            'licenses' => [['license' => ['id' => $composer['license']]]],
        ],
    ],
    'components' => [
        ...array_map(static fn(array $p): array => $component($p, 'required'), $lock['packages'] ?? []),
        ...array_map(static fn(array $p): array => $component($p, 'excluded'), $lock['packages-dev'] ?? []),
    ],
];

$output = $argv[1] ?? $root . '/build/sbom.cdx.json';
@mkdir(dirname($output), 0o755, true);
file_put_contents($output, json_encode($bom, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n");

$required = array_filter($bom['components'], static fn(array $c): bool => $c['scope'] === 'required');
printf("SBOM: %s (CycloneDX 1.5, %d runtime, %d excluded dev components)\n", $output, count($required), count($bom['components']) - count($required));
