<?php

declare(strict_types=1);

namespace Communism\Ext;

use PhpCsFixer\AbstractFixer;
use PhpCsFixer\Fixer\ConfigurableFixerInterface;
use PhpCsFixer\Fixer\ConfigurableFixerTrait;
use PhpCsFixer\Fixer\WhitespacesAwareFixerInterface;
use PhpCsFixer\FixerConfiguration\FixerConfigurationResolver;
use PhpCsFixer\FixerConfiguration\FixerConfigurationResolverInterface;
use PhpCsFixer\FixerConfiguration\FixerOptionBuilder;
use PhpCsFixer\FixerDefinition\CodeSample;
use PhpCsFixer\FixerDefinition\FixerDefinition;
use PhpCsFixer\FixerDefinition\FixerDefinitionInterface;
use PhpCsFixer\Tokenizer\Tokens;
use Symfony\Component\OptionsResolver\Options;

use function array_filter;
use function ltrim;
use function mb_strlen;
use function mb_strwidth;
use function max;
use function preg_split;
use function sprintf;
use function str_replace;
use function str_starts_with;
use function str_ends_with;
use function strlen;
use function str_repeat;
use function substr;
use function trim;

/**
 * @implements ConfigurableFixerInterface<array{copyright_name: string, tagline: string, license_name: string, license_text: string, header_width: int}, array{copyright_name: string, tagline: string, license_name: string, license_text: string, header_width: int}>
 */
final class CommonHeaderFixer extends AbstractFixer implements ConfigurableFixerInterface, WhitespacesAwareFixerInterface
{
    /** @use ConfigurableFixerTrait<array{copyright_name: string, tagline: string, license_name: string, license_text: string, header_width: int}, array{copyright_name: string, tagline: string, license_name: string, license_text: string, header_width: int}> */
    use ConfigurableFixerTrait;

    private const MIN_HEADER_WIDTH = 20;

    public function getName(): string
    {
        return 'Communism/common_header';
    }

    public function getDefinition(): FixerDefinitionInterface
    {
        return new FixerDefinition(
            'Normalize a common header with current year, file name, and a sufficiently detailed purpose.',
            [
                new CodeSample(
                    <<<'PHP'
                        <?php

                        declare(strict_types=1);

                        namespace Example;

                        final class Example
                        {
                        }

                        PHP,
                ),
            ],
        );
    }

    protected function createConfigurationDefinition(): FixerConfigurationResolverInterface
    {
        $fixerName = $this->getName();

        return new FixerConfigurationResolver([
            (new FixerOptionBuilder('copyright_name', 'Name used in copyright notices.'))
                ->setAllowedTypes(['string'])
                ->setNormalizer(static function (Options $options, string $value) use ($fixerName): string {
                    $value = trim($value);
                    if ('' === $value) {
                        throw new \PhpCsFixer\ConfigurationException\InvalidFixerConfigurationException($fixerName, 'Copyright name must not be empty.');
                    }

                    return $value;
                })
                ->getOption(),
            (new FixerOptionBuilder('tagline', 'Tagline line written in the header.'))
                ->setAllowedTypes(['string'])
                ->setNormalizer(static function (Options $options, string $value) use ($fixerName): string {
                    $value = trim($value);
                    if ('' === $value) {
                        throw new \PhpCsFixer\ConfigurationException\InvalidFixerConfigurationException($fixerName, 'Tagline must not be empty.');
                    }

                    return $value;
                })
                ->getOption(),
            (new FixerOptionBuilder('license_name', 'License identifier used in the SPDX header line.'))
                ->setAllowedTypes(['string'])
                ->setNormalizer(static function (Options $options, string $value) use ($fixerName): string {
                    $value = trim($value);
                    if ('' === $value) {
                        throw new \PhpCsFixer\ConfigurationException\InvalidFixerConfigurationException($fixerName, 'License name must not be empty.');
                    }

                    return $value;
                })
                ->getOption(),
            (new FixerOptionBuilder('license_text', 'License text written into the common header.'))
                ->setAllowedTypes(['string'])
                ->setNormalizer(static function (Options $options, string $value) use ($fixerName): string {
                    $value = trim($value);
                    if ('' === $value) {
                        throw new \PhpCsFixer\ConfigurationException\InvalidFixerConfigurationException($fixerName, 'License text must not be empty.');
                    }

                    return $value;
                })
                ->getOption(),
            (new FixerOptionBuilder('header_width', 'Total width of the common header.'))
                ->setAllowedTypes(['int'])
                ->setNormalizer(static function (Options $options, int $value) use ($fixerName): int {
                    if ($value < self::MIN_HEADER_WIDTH) {
                        throw new \PhpCsFixer\ConfigurationException\InvalidFixerConfigurationException($fixerName, sprintf('Header width must be at least %d.', self::MIN_HEADER_WIDTH));
                    }

                    return $value;
                })
                ->getOption(),
        ]);
    }

    public function isRisky(): bool
    {
        return true;
    }

    public function isCandidate(Tokens $tokens): bool
    {
        return $tokens->isMonolithicPhp() && !$tokens->isTokenKindFound(\T_OPEN_TAG_WITH_ECHO);
    }

    public function supports(\SplFileInfo $file): bool
    {
        return 'php' === strtolower($file->getExtension());
    }

    protected function applyFix(\SplFileInfo $file, Tokens $tokens): void
    {
        $code = $tokens->generateCode();
        $eol = $this->detectLineEnding($code);
        $purpose = $this->extractPurpose($code);

        if (null === $purpose || mb_strlen(trim($purpose)) <= 15) {
            $purpose = sprintf('Source file for %s.', $file->getBasename());
        }

        $normalized = $this->buildHeader($file->getBasename(), $purpose, $eol) . $this->extractBody($code);
        $tokens->setCode($normalized);
    }

    private function detectLineEnding(string $code): string
    {
        $crlfPosition = strpos($code, "\r\n");
        $lfPosition = strpos($code, "\n");

        if (false !== $crlfPosition && (false === $lfPosition || $crlfPosition === $lfPosition - 1)) {
            return "\r\n";
        }

        return "\n";
    }

    private function buildHeader(string $fileName, string $purpose, string $eol): string
    {
        $year = (new \DateTimeImmutable('now'))->format('Y');

        return implode($eol, [
            '<?php',
            '',
            $this->topBorder(),
            $this->formatHeaderLine(sprintf('SPDX-License-Identifier: %s', $this->licenseName())),
            $this->formatHeaderLine(sprintf('SPDX-FileCopyrightText: %s %s', $year, $this->copyrightName())),
            $this->formatHeaderLine(sprintf('Copyright (C) %s %s', $year, $this->copyrightName())),
            $this->formatHeaderLine(''),
            ...$this->formatLicenseLines(),
            $this->middleBorder(),
            $this->formatHeaderLine($this->tagline()),
            $this->ruleBorder(),
            $this->formatHeaderLine(sprintf('File: %s', $fileName)),
            ...$this->formatPurposeLines($purpose),
            $this->closeBorder(),
            '',
            'declare(strict_types=1);',
            '',
        ]) . $eol;
    }

    /**
     * @return list<string>
     */
    private function formatLicenseLines(): array
    {
        $license = $this->licenseText();
        $paragraphs = $this->normalizeLicenseParagraphs($license);

        if ([] === $paragraphs) {
            return [];
        }

        $output = [];
        foreach ($paragraphs as $paragraph) {
            if ('' === $paragraph) {
                $output[] = $this->formatHeaderLine('');
                continue;
            }

            foreach ($this->wrapText($paragraph, $this->headerContentWidth()) as $wrappedLine) {
                $output[] = $this->formatHeaderLine($wrappedLine);
            }
        }

        return $output;
    }

    /**
     * @return list<string>
     */
    private function normalizeLicenseParagraphs(string $license): array
    {
        $lines = preg_split('/\R/u', trim($license));
        if (false === $lines) {
            return [];
        }

        $paragraphs = [];
        $buffer = [];

        foreach ($lines as $line) {
            $line = trim($line);

            if ('' === $line) {
                if ([] !== $buffer) {
                    $paragraphs[] = implode(' ', $buffer);
                    $buffer = [];
                }

                $paragraphs[] = '';
                continue;
            }

            $buffer[] = $line;

            if (str_ends_with($line, '.')) {
                $paragraphs[] = implode(' ', $buffer);
                $buffer = [];
            }
        }

        if ([] !== $buffer) {
            $paragraphs[] = implode(' ', $buffer);
        }

        while ([] !== $paragraphs && '' === end($paragraphs)) {
            array_pop($paragraphs);
        }

        return $paragraphs;
    }

    private function licenseName(): string
    {
        return $this->configured()['license_name'];
    }

    private function licenseText(): string
    {
        return $this->configured()['license_text'];
    }

    private function copyrightName(): string
    {
        return $this->configured()['copyright_name'];
    }

    private function tagline(): string
    {
        return $this->configured()['tagline'];
    }

    private function headerWidth(): int
    {
        return $this->configured()['header_width'];
    }

    private function headerContentWidth(): int
    {
        return $this->headerWidth() - 6;
    }

    private function topBorder(): string
    {
        return '/*' . str_repeat('=', $this->headerWidth() - 4) . '*';
    }

    private function middleBorder(): string
    {
        return ' *' . str_repeat('=', $this->headerWidth() - 4) . '*';
    }

    private function ruleBorder(): string
    {
        return ' *' . str_repeat('-', $this->headerWidth() - 4) . '*';
    }

    private function closeBorder(): string
    {
        return ' *' . str_repeat('=', $this->headerWidth() - 4) . '*/';
    }

    /**
     * @return list<string>
     */
    private function formatPurposeLines(string $purpose): array
    {
        $purpose = trim($purpose);
        $firstLabel = 'Purpose: ';
        $continuationPrefix = '         ';
        $lines = $this->wrapText($purpose, $this->headerContentWidth() - mb_strwidth($firstLabel, 'UTF-8'));
        $first = array_shift($lines);
        $output = [
            $this->formatHeaderLine(sprintf('%s%s', $firstLabel, (string) $first)),
        ];

        foreach (array_filter($lines, static fn(string $line): bool => '' !== trim($line)) as $line) {
            $output[] = $this->formatHeaderLine($continuationPrefix . trim($line));
        }

        return $output;
    }

    private function formatHeaderLine(string $content): string
    {
        $padding = $this->headerContentWidth() - mb_strwidth($content, 'UTF-8');
        if ($padding > 0) {
            $content .= str_repeat(' ', $padding);
        }

        return sprintf(' * %s *', $content);
    }

    /**
     * @return list<string>
     */
    private function wrapText(string $text, int $width): array
    {
        $words = preg_split('/\s+/u', trim($text));
        if (false === $words || [] === $words) {
            return [];
        }

        $lines = [];
        $current = '';

        foreach ($words as $word) {
            if ('' === $word) {
                continue;
            }

            $candidate = '' === $current ? $word : $current . ' ' . $word;
            if ('' !== $current && mb_strwidth($candidate, 'UTF-8') > $width) {
                $lines[] = $current;
                $current = $word;
                continue;
            }

            $current = $candidate;
        }

        if ('' !== $current) {
            $lines[] = $current;
        }

        return $lines;
    }

    private function extractBody(string $code): string
    {
        $declare = 'declare(strict_types=1);';
        $position = strpos($code, $declare);

        if (false === $position) {
            return '';
        }

        $body = substr($code, $position + strlen($declare));

        return ltrim($body, "\r\n");
    }

    /**
     * @return array<string, string>
     */
    private function extractHeaderProperties(string $code): array
    {
        $declare = 'declare(strict_types=1);';
        $declarePosition = strpos($code, $declare);

        if (false === $declarePosition) {
            return [];
        }

        $header = substr($code, 0, $declarePosition);

        $header = ltrim($header);
        if (!str_starts_with($header, '<?php')) {
            return [];
        }

        $lines = preg_split('/\R/', $header);
        if (false === $lines) {
            return [];
        }

        while ([] !== $lines && '' === trim($lines[0])) {
            array_shift($lines);
        }

        $firstLine = array_shift($lines);
        if ([] === $lines || '<?php' !== trim($firstLine ?? '')) {
            return [];
        }

        $state = 'searching';
        /** @var array<string, string> $properties */
        $properties = [];
        $currentKey = null;

        foreach ($lines as $line) {
            $content = $this->extractCommentLineContent($line);
            if (null === $content) {
                if ('capturing' === $state) {
                    break;
                }

                continue;
            }

            if ($this->isEqualBorderLine($content)) {
                if ('capturing' === $state) {
                    if (null !== $currentKey) {
                        $properties[$currentKey] = trim($properties[$currentKey]);
                    }

                    break;
                }

                continue;
            }

            if ('searching' === $state) {
                if ($this->isDashBorderLine($content)) {
                    $state = 'capturing';
                }

                continue;
            }

            if ($this->isDashBorderLine($content)) {
                continue;
            }

            $propertyLine = $this->parsePropertyLine($content);
            if (null !== $propertyLine) {
                [$key, $value] = $propertyLine;
                if (null !== $currentKey) {
                    $properties[$currentKey] = trim($properties[$currentKey]);
                }

                $currentKey = $key;
                $properties[$currentKey] = $value;
                continue;
            }

            if (null !== $currentKey && '' !== trim($content)) {
                $properties[$currentKey] .= ' ' . trim($content);
            }
        }

        if (null !== $currentKey && array_key_exists($currentKey, $properties)) {
            $properties[$currentKey] = trim($properties[$currentKey]);
        }

        return $properties;
    }

    private function extractPurpose(string $code): ?string
    {
        $properties = $this->extractHeaderProperties($code);
        if (!array_key_exists('Purpose', $properties)) {
            return null;
        }

        $purpose = trim($properties['Purpose']);

        return '' === $purpose ? null : $purpose;
    }

    private function isDashBorderLine(string $content): bool
    {
        return '' !== $content && str_replace('-', '', $content) === '';
    }

    private function isEqualBorderLine(string $content): bool
    {
        return '' !== $content && str_replace('=', '', $content) === '';
    }

    /**
     * @return array{string, string}|null
     */
    private function parsePropertyLine(string $content): ?array
    {
        $separator = strpos($content, ':');
        if (false === $separator) {
            return null;
        }

        $parsedKey = trim(substr($content, 0, $separator));
        $parsedValue = ltrim(substr($content, $separator + 1));

        if ('' === $parsedKey) {
            return null;
        }

        return [$parsedKey, $parsedValue];
    }

    private function extractCommentLineContent(string $line): ?string
    {
        $trimmed = trim($line);
        if ('' === $trimmed || !str_starts_with($trimmed, '*')) {
            return null;
        }

        if (str_ends_with($trimmed, '*/')) {
            $content = substr($trimmed, 1, -2);
        } elseif (str_ends_with($trimmed, '*')) {
            $content = substr($trimmed, 1, -1);
        } else {
            return null;
        }

        return rtrim($content, "\t ");
    }

    /**
     * @return array{copyright_name: string, tagline: string, license_name: string, license_text: string, header_width: int}
     */
    private function configured(): array
    {
        if (null === $this->configuration) {
            throw new \LogicException('CommonHeaderFixer must be configured before use.');
        }

        return $this->configuration;
    }
}
