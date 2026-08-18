<?php

declare(strict_types=1);

$autoload = __DIR__ . '/vendor/autoload.php';
if (is_file($autoload)) {
    require_once $autoload;
}

$finder = PhpCsFixer\Finder::create()
    ->files()
    ->name('*.php')
    ->in(__DIR__)
    ->in(__DIR__ . '/tests')
    ->exclude('php-src')
    ->exclude('vendor')
    ->ignoreDotFiles(true)
    ->ignoreVCS(true);

return (new PhpCsFixer\Config())
    ->setRiskyAllowed(true)
    ->registerCustomFixers([
        new Communism\Ext\CommonHeaderFixer(),
    ])
    ->setRuleCustomisationPolicy(new class() implements PhpCsFixer\Config\RuleCustomisationPolicyInterface {
        public function getPolicyVersionForCache(): string
        {
            return hash_file('sha256', __FILE__) ?: 'common-header-policy';
        }

        public function getRuleCustomisers(): array
        {
            return [
                'Communism/common_header' => static function (SplFileInfo $file): bool {
                    $path = str_replace('\\', '/', $file->getPathname());

                    return str_contains($path, 'src/Zendful/') || str_contains($path, 'src/Communism/') || str_contains($path, 'src/Communism_PHPStan/');
                },
            ];
        }
    })
    ->setRules([
        '@PER-CS' => true,
        'declare_strict_types' => true,
        'Communism/common_header' => [
            'copyright_name' => 'Luca Mollema',
            'taglines' => [
                'Communism' => ':: Communism :: "In comrade PHP, all are public" ::',
                'Zendful' => ':: Zendful :: "When PHP doesn\'t provide it, we do!" ::'
            ],
            'license_name' => '0BSD',
            'license_text' => file_get_contents(__DIR__ . '/LICENSE'),
            'header_width' => 80,
            'allowed_consumers' => ['Users', 'Internal'],
            'rules' => [
                '~src/Communism/Internals/~' => ['consumer' => 'Internal', 'tagline' => 'Communism'],
                '~src/Communism(?:_PHPStan)?/~' => ['consumer' => 'Users', 'tagline' => 'Communism'],
                '~src/Zendful/~' => ['consumer' => 'Internal', 'tagline' => 'Zendful'],
            ],
        ],
    ])
    ->setFinder($finder);
