<?php

declare(strict_types=1);

use Communism\Mixin\Accessor;
use Communism\Mixin\At;
use Communism\Mixin\Inject;
use Communism\Mixin\Invoker;
use Communism\Mixin\Mixin;
use Communism\Mixin\Overwrite;
use Communism\Mixin\Shadow;

// This must never run. The fixture is intentionally inspected only by PHPStan.
throw new RuntimeException('Static-analysis fixture was executed');

#[Mixin(StaticAnalysisInvalidTarget::class)]
trait LegacyStaticAnalysisMixin {}

final class StaticAnalysisInvalidTarget
{
    use LegacyStaticAnalysisMixin;

    public function run(): string
    {
        return 'run';
    }
}

#[Mixin(StaticAnalysisInvalidTarget::class)]
final class InvalidStaticAnalysisMixin
{
    private function __construct() {}
    #[Inject('missingMethod', new At('HEAD'))]
    public function injectMissing(): void {}

    #[Inject('run', new At('INVOKE', 'strtolower'))]
    public function injectMissingInvocation(): void {}

    #[Shadow]
    public int $missingProperty;

    #[Shadow]
    public function missingMethod(): void {}

    #[Overwrite]
    public function missingOverwrite(): void {}

    #[Accessor('missingProperty')]
    public function getMissingProperty(): int
    {
        return 0;
    }

    #[Invoker('missingMethod')]
    public function callMissingMethod(): void {}
}

final class InvalidStaticAnalysisApplication {}
