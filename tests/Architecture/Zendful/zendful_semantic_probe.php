<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';

use Zendful\Zendful;

class ZendfulSemanticProbeTarget
{
    public int $value = 0;

    public function value(): string
    {
        return 'semantic';
    }
}

$class = Zendful::class(ZendfulSemanticProbeTarget::class);
$method = Zendful::method(ZendfulSemanticProbeTarget::class, 'value');
$property = Zendful::property(ZendfulSemanticProbeTarget::class, 'value');

$class->setAbstract(false);
$class->setReadonly(false);
$class->setAnonymous(false);
$class->setKind(null);
$method->setVisibility('public');
$method->clearVisibility();
$method->setVisibility('public');
$method->setStatic(false);
$method->setFinal(false);
$method->withVisibility('public', static fn(): string => 'visible');
$property->setVisibility('public');
$property->clearVisibility();
$property->setVisibility('public');
$property->setReadonly(false);
try {
    $property->setSetVisibility('public', false);
    exit(1);
} catch (\InvalidArgumentException) {
}
$property->withVisibility('public', static fn(): string => 'visible');
