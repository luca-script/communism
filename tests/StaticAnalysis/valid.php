<?php

declare(strict_types=1);

use Communism\Mixin\At;
use Communism\Mixin\Accessor;
use Communism\Mixin\Inject;
use Communism\Mixin\Invoker;
use Communism\Mixin\Mixin;
use Communism\Mixin\Overwrite;
use Communism\Mixin\Shadow;

class StaticAnalysisBaseTarget
{
    public function inheritedRun(): string
    {
        return 'inherited';
    }
}

final class StaticAnalysisTarget extends StaticAnalysisBaseTarget
{
    private int $value = 0;

    private function calculate(): int
    {
        return $this->value;
    }

    public function run(): int
    {
        return $this->calculate();
    }
}

#[Mixin(StaticAnalysisTarget::class)]
final class ValidStaticAnalysisMixin
{
    private function __construct() {}
    #[Shadow]
    public int $value;

    #[Shadow]
    public function calculate(): int
    {
        return 0;
    }

    #[Inject('run', new At('HEAD'))]
    public function beforeRun(): void {}

    #[Inject('inheritedRun', new At('HEAD'))]
    public function beforeInheritedRun(): void {}

    #[Overwrite]
    public function run(): int
    {
        return 1;
    }

    #[Accessor('value')]
    public function getValue(): int
    {
        return 0;
    }

    #[Invoker('calculate')]
    public function callCalculate(): int
    {
        return 0;
    }
}

final class ValidStaticAnalysisApplication {}
