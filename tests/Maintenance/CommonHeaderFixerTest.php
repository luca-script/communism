<?php

declare(strict_types=1);

use Communism\Ext\CommonHeaderFixer;
use PhpCsFixer\Tokenizer\Tokens;

/**
 * @param array{copyright_name?: string, taglines?: array<string, string>, license_name?: string, license_text?: string, header_width?: int, allowed_consumers?: list<string>, rules?: array<string, array{consumer: string, tagline?: string}>} $overrides
 */
function configuredCommonHeaderFixer(array $overrides = []): CommonHeaderFixer
{
    $fixer = new CommonHeaderFixer();
    $licenseText = file_get_contents(__DIR__ . '/../../LICENSE');
    if (false === $licenseText) {
        throw new RuntimeException('Unable to read LICENSE file.');
    }

    $configuration = [
        'copyright_name' => 'Luca Mollema',
        'taglines' => ['Communism' => ':: Communism :: "In comrade PHP, all are public" ::'],
        'license_name' => '0BSD',
        'license_text' => $licenseText,
        'header_width' => 80,
        'allowed_consumers' => ['Users', 'Internal'],
        'rules' => [
            '~src/Communism/Internals/~' => ['consumer' => 'Internal'],
            '~src/Communism(?:_PHPStan)?/~' => ['consumer' => 'Users'],
        ],
    ];

    if (array_key_exists('copyright_name', $overrides)) {
        $configuration['copyright_name'] = $overrides['copyright_name'];
    }

    if (array_key_exists('taglines', $overrides)) {
        $configuration['taglines'] = $overrides['taglines'];
    }

    if (array_key_exists('license_name', $overrides)) {
        $configuration['license_name'] = $overrides['license_name'];
    }

    if (array_key_exists('license_text', $overrides)) {
        $configuration['license_text'] = $overrides['license_text'];
    }

    if (array_key_exists('header_width', $overrides)) {
        $configuration['header_width'] = $overrides['header_width'];
    }

    if (array_key_exists('allowed_consumers', $overrides)) {
        $configuration['allowed_consumers'] = $overrides['allowed_consumers'];
    }

    if (array_key_exists('rules', $overrides)) {
        $configuration['rules'] = $overrides['rules'];
    }

    $fixer->configure($configuration);

    return $fixer;
}

/**
 * @return array<string, string>
 */
function extractCommonHeaderProperties(CommonHeaderFixer $fixer, string $code): array
{
    $method = new ReflectionMethod(CommonHeaderFixer::class, 'extractHeaderProperties');
    $method->setAccessible(true);

    $result = $method->invoke($fixer, $code);
    if (!is_array($result)) {
        throw new RuntimeException('Unexpected reflection result.');
    }

    $properties = [];
    foreach ($result as $key => $value) {
        if (is_string($key) && is_string($value)) {
            $properties[$key] = $value;
        }
    }

    return $properties;
}

it('inserts the common header for Communism source files', function (): void {
    $fixer = configuredCommonHeaderFixer();
    $file = new SplFileInfo(__DIR__ . '/../../src/Communism/Example.php');
    $tokens = Tokens::fromCode(<<<'PHP'
        <?php

        declare(strict_types=1);

        namespace Communism;

        final class Example
        {
        }
        PHP);

    $fixer->fix($file, $tokens);
    $code = $tokens->generateCode();
    $currentYear = date('Y');

    expect($code)->toContain("Copyright (C) {$currentYear} Luca Mollema");
    expect($code)->toContain('Permission to use, copy, modify, and/or distribute');
    expect($code)->toContain('File: Example.php');
    expect($code)->toContain('Consumer: Users');
    expect($code)->toContain('Purpose: Source file for Example.php.');
});

it('assigns the Internal consumer from the configured path rule', function (): void {
    $fixer = configuredCommonHeaderFixer();
    $file = new SplFileInfo(__DIR__ . '/../../src/Communism/Internals/Executor.php');
    $tokens = Tokens::fromCode(<<<'PHP'
        <?php

        declare(strict_types=1);

        namespace Communism\Internals;

        final class Example
        {
        }
        PHP);

    $fixer->fix($file, $tokens);

    expect($tokens->generateCode())->toContain('Consumer: Internal');
});

it('selects a tagline preset from the first matching path rule', function (): void {
    $fixer = configuredCommonHeaderFixer([
        'taglines' => [
            'Communism' => ':: Communism :: "In comrade PHP, all are public" ::',
            'Zendful' => ':: Zendful :: "When PHP doesn\'t provide, we do!" ::',
        ],
        'rules' => [
            '~src/Zendful/~' => ['consumer' => 'Internal', 'tagline' => 'Zendful'],
            '~src/Communism/~' => ['consumer' => 'Users', 'tagline' => 'Communism'],
        ],
    ]);
    $file = new SplFileInfo(__DIR__ . '/../../src/Zendful/Example.php');
    $tokens = Tokens::fromCode(<<<'PHP'
        <?php

        declare(strict_types=1);

        namespace Zendful;

        final class Example
        {
        }
        PHP);

    $fixer->fix($file, $tokens);

    expect($tokens->generateCode())->toContain(':: Zendful :: "When PHP doesn\'t provide, we do!" ::');
    expect($tokens->generateCode())->toContain('Consumer: Internal');
});

it('rejects a Consumer value outside the configured allowlist', function (): void {
    $fixer = configuredCommonHeaderFixer([
        'allowed_consumers' => ['Users'],
    ]);
    $file = new SplFileInfo(__DIR__ . '/../../src/Communism/Example.php');
    $tokens = Tokens::fromCode(<<<'PHP'
        <?php

        /*
        *================================================*
        *------------------------------------------------*
        * File: Example.php                              *
        * Consumer: Internal                             *
        * Purpose: An example source file.               *
        *================================================*/
        declare(strict_types=1);

        final class Example
        {
        }
        PHP);

    expect(function () use ($fixer, $file, $tokens): void {
        $fixer->fix($file, $tokens);
    })->toThrow(LogicException::class);
});

it('honors custom header configuration', function (): void {
    $fixer = configuredCommonHeaderFixer([
        'copyright_name' => 'Example Corp',
        'taglines' => ['Example' => ':: Example :: custom tagline ::'],
        'license_name' => 'Example License',
        'license_text' => "Example license text.\nIt may span multiple lines.",
        'header_width' => 72,
    ]);

    $path = __DIR__ . '/../../src/Communism/Reflect/ReflectionProperty.php';
    $file = new SplFileInfo($path);
    $code = file_get_contents($path);
    if (false === $code) {
        throw new RuntimeException('Unable to read fixture file.');
    }
    $tokens = Tokens::fromCode($code);

    $fixer->fix($file, $tokens);

    $code = $tokens->generateCode();
    $lines = preg_split('/\R/', $code);
    if (false === $lines) {
        throw new RuntimeException('Unable to split generated code.');
    }
    $currentYear = date('Y');

    expect($code)->toContain("SPDX-FileCopyrightText: {$currentYear} Example Corp");
    expect($code)->toContain("Copyright (C) {$currentYear} Example Corp");
    expect($code)->toContain(':: Example :: custom tagline ::');
    expect($code)->toContain('SPDX-License-Identifier: Example License');
    expect(strlen($lines[2]))->toBe(71);
});

it('keeps a compliant common header stable', function (): void {
    $fixer = configuredCommonHeaderFixer();
    $path = __DIR__ . '/../../src/Communism/Reflect/ReflectionClass.php';
    $file = new SplFileInfo($path);
    $code = file_get_contents($path);
    if (false === $code) {
        throw new RuntimeException('Unable to read fixture file.');
    }
    $tokens = Tokens::fromCode($code);
    $normalizeLineEndings = static fn(string $value): string => str_replace(["\r\n", "\r"], "\n", $value);
    $original = $normalizeLineEndings($tokens->generateCode());

    $fixer->fix($file, $tokens);

    expect($normalizeLineEndings($tokens->generateCode()))->toBe($original);
});

it('extracts header properties into an ordered array', function (): void {
    $fixer = configuredCommonHeaderFixer();
    $code = <<<'PHP'
        <?php

        /*
        *------------------------------------------------*
        * File: Foo.php                                  *
        * Consumer: Users                                 *
        * Purpose: Kaz                                   *
        * Bar: Bar                                       *
        *================================================*/
        declare(strict_types=1);

        final class Example
        {
        }
        PHP;

    $properties = extractCommonHeaderProperties($fixer, $code);

    expect($properties)->toBe([
        'File' => 'Foo.php',
        'Consumer' => 'Users',
        'Purpose' => 'Kaz',
        'Bar' => 'Bar',
    ]);
});
