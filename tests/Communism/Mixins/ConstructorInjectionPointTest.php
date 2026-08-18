<?php

declare(strict_types=1);

use Communism\Mixin\At;
use Communism\Mixin\CallbackInfo;
use Communism\Mixin\Inject;
use Communism\Mixin\Mixin;
use Communism\Reflect\ReflectionClass;

final class ConstructorHeadEvents
{
    /** @var list<string> */
    public static array $events = [];
}

function recordConstructorHeadEvent(): void
{
    ConstructorHeadEvents::$events[] = 'head';
}

class ConstructorHeadParent
{
    public function __construct()
    {
        ConstructorHeadEvents::$events[] = 'parent';
    }
}

#[Mixin(ConstructorHeadChild::class)]
final class ConstructorHeadChildMixin
{
    private function __construct() {}
    #[Inject('__construct', new At('CONSTRUCTOR_HEAD'))]
    public function recordHead(CallbackInfo $info): void
    {
        recordConstructorHeadEvent();
    }
}

final class ConstructorHeadChild extends ConstructorHeadParent
{
    public function __construct()
    {
        parent::__construct();
        ConstructorHeadEvents::$events[] = 'child';
    }
}


it('places CONSTRUCTOR_HEAD after a parent constructor call', function (): void {
    ConstructorHeadEvents::$events = [];
    (new ReflectionClass(ConstructorHeadChild::class))->inject(ConstructorHeadChildMixin::class);

    new ConstructorHeadChild();

    expect(ConstructorHeadEvents::$events)->toBe(['parent', 'head', 'child']);
});

#[Mixin(ConstructorHeadOnly::class)]
final class ConstructorHeadOnlyMixin
{
    private function __construct() {}
    #[Inject('__construct', new At('CONSTRUCTOR_HEAD'))]
    public function recordHead(CallbackInfo $info): void
    {
        recordConstructorHeadEvent();
    }
}

final class ConstructorHeadOnly
{
    public function __construct()
    {
        ConstructorHeadEvents::$events[] = 'body';
    }
}


it('places CONSTRUCTOR_HEAD at the body start without a parent call', function (): void {
    ConstructorHeadEvents::$events = [];
    (new ReflectionClass(ConstructorHeadOnly::class))->inject(ConstructorHeadOnlyMixin::class);

    new ConstructorHeadOnly();

    expect(ConstructorHeadEvents::$events)->toBe(['head', 'body']);
});
