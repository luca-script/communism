<?php

declare(strict_types=1);

use Communism\Mixin\At;
use Communism\Mixin\Mixin;
use Communism\Mixin\Redirect;
use Communism\Reflect\ReflectionClass;

describe('Redirect', function (): void {
    covers([Redirect::class, ...COMMUNISM_INJECTOR_COVERAGE_CLASSES]);

    #[Mixin(ArrayReadRedirectTarget::class)]
    final class ArrayReadRedirectMixin
    {
        private function __construct() {}
        /** @param array<int, int> $values */
        #[Redirect('read', new At('FIELD', '[]'))]
        public function redirectArrayRead(array $values, int $key): int
        {
            return $values[$key] + 10;
        }
    }


    final class ArrayReadRedirectTarget
    {
        /** @param array<int, int> $values */
        public function read(array $values, int $key): int
        {
            return $values[$key];
        }
    }

    it('redirects array element reads through a FIELD array target', function (): void {
        (new ReflectionClass(ArrayReadRedirectTarget::class))->inject(ArrayReadRedirectMixin::class);

        expect((new ArrayReadRedirectTarget())->read([2 => 5], 2))->toBe(15);
    });

    function captureArrayRedirectWrite(int $value): void
    {
        $GLOBALS['array_redirect_write'] = $value;
    }

    function capturedArrayRedirectWrite(): int
    {
        $value = $GLOBALS['array_redirect_write'] ?? -1;

        return is_int($value) ? $value : -1;
    }

    #[Mixin(ArrayWriteRedirectTarget::class)]
    final class ArrayWriteRedirectMixin
    {
        private function __construct() {}
        /** @param array<int, int> $values */
        #[Redirect('write', new At('FIELD', '[]'))]
        public function redirectArrayWrite(array $values, int $key, int $value): void
        {
            captureArrayRedirectWrite($value);
        }
    }


    final class ArrayWriteRedirectTarget
    {
        /** @param array<int, int> $values */
        public function write(array &$values, int $key, int $value): void
        {
            $values[$key] = $value;
        }
    }

    it('redirects array element writes with array, key, and value operands', function (): void {
        (new ReflectionClass(ArrayWriteRedirectTarget::class))->inject(ArrayWriteRedirectMixin::class);

        $values = [2 => 1];
        (new ArrayWriteRedirectTarget())->write($values, 2, 7);

        expect($values)->toBe([2 => 1])
            ->and(capturedArrayRedirectWrite())->toBe(7);
    });

    #[Mixin(ArrayRedirectMissingTarget::class)]
    final class ArrayRedirectMissingMixin
    {
        private function __construct() {}
        #[Redirect('read', new At('FIELD', '[]'))]
        public function redirectArray(): int
        {
            return 1;
        }
    }


    final class ArrayRedirectMissingTarget
    {
        public function read(int $value): int
        {
            return $value;
        }
    }

    it('rejects an array redirect when the target has no array operation', function (): void {
        expect(static function (): void {
            (new ReflectionClass(ArrayRedirectMissingTarget::class))->inject(ArrayRedirectMissingMixin::class);
        })->toThrow(InvalidArgumentException::class, 'did not match');

        expect((new ArrayRedirectMissingTarget())->read(3))->toBe(3);
    });
});
