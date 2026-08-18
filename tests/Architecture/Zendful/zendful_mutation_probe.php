<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';

use Zendful\Zendful;

final class ZendfulMutationProbeTarget
{
    public int $value = 0;

    public function invoke(): string
    {
        return 'invoke';
    }
}

function requireCondition(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$method = Zendful::method(ZendfulMutationProbeTarget::class, 'invoke');
$methodReflection = new ReflectionMethod(ZendfulMutationProbeTarget::class, 'invoke');

requireCondition($methodReflection->isPublic(), 'The method must start public.');

$nestedResult = $method->withVisibility('private', static function () use ($method, $methodReflection): array {
    requireCondition($methodReflection->isPrivate(), 'The outer method mutation was not applied.');

    $result = $method->withVisibility('protected', static function () use ($methodReflection): array {
        requireCondition($methodReflection->isProtected(), 'The nested method mutation was not applied.');

        return ['nested' => true];
    });

    requireCondition($methodReflection->isPrivate(), 'The outer method mutation was not restored after re-entry.');
    if (!is_array($result)) {
        throw new RuntimeException('Nested method callback result must be an array.');
    }

    return $result;
});

requireCondition($nestedResult === ['nested' => true], 'Nested callback results must be preserved.');
requireCondition($methodReflection->isPublic(), 'The method was not restored after successful re-entry.');

try {
    $method->withVisibility('private', static function () use ($method, $methodReflection): never {
        requireCondition($methodReflection->isPrivate(), 'The throwing method mutation was not applied.');

        $method->withVisibility('protected', static function () use ($methodReflection): null {
            requireCondition($methodReflection->isProtected(), 'The throwing nested mutation was not applied.');

            return null;
        });

        throw new RuntimeException('expected method callback failure');
    });
} catch (RuntimeException $exception) {
    requireCondition($exception->getMessage() === 'expected method callback failure', 'The original method exception was not preserved.');
}

requireCondition($methodReflection->isPublic(), 'The method was not restored after an exception.');

$property = Zendful::property(ZendfulMutationProbeTarget::class, 'value');
$propertyReflection = new ReflectionProperty(ZendfulMutationProbeTarget::class, 'value');

requireCondition($propertyReflection->isPublic(), 'The property must start public.');

$object = new stdClass();
$propertyResult = $property->withVisibility('private', static function () use ($property, $propertyReflection, $object): object {
    requireCondition($propertyReflection->isPrivate(), 'The property mutation was not applied.');

    $nestedResult = $property->withVisibility('protected', static function () use ($propertyReflection): string {
        requireCondition($propertyReflection->isProtected(), 'The nested property mutation was not applied.');

        return 'nested property';
    });

    requireCondition($propertyReflection->isPrivate(), 'The outer property mutation was not restored after re-entry.');
    requireCondition($nestedResult === 'nested property', 'Nested property callback results must be preserved.');

    return $object;
});

requireCondition($propertyResult === $object, 'Object callback results must preserve identity.');
requireCondition($propertyReflection->isPublic(), 'The property was not restored after successful re-entry.');

try {
    $property->withVisibility('private', static function () use ($propertyReflection): never {
        requireCondition($propertyReflection->isPrivate(), 'The throwing property mutation was not applied.');

        throw new RuntimeException('expected property callback failure');
    });
} catch (RuntimeException $exception) {
    requireCondition($exception->getMessage() === 'expected property callback failure', 'The original property exception was not preserved.');
}

requireCondition($propertyReflection->isPublic(), 'The property was not restored after an exception.');
