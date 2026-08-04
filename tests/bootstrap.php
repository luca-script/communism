<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

// __Underlying__ is final in production. Tests need to derive from it in
// order to replace runtime hooks, so load a non-final copy before Composer's
// autoloader can load the production class.
$source = file_get_contents(__DIR__ . '/../src/Communism/__Underlying__.php');

if ($source === false) {
    throw new RuntimeException('Could not read __Underlying__ for tests');
}

$source = str_replace('final class __Underlying__', 'class __Underlying__', $source, $replacements);

if ($replacements !== 1) {
    throw new RuntimeException('Could not prepare __Underlying__ for tests');
}

$openingTag = strpos($source, '<?php');

if ($openingTag === false) {
    throw new RuntimeException('Could not prepare __Underlying__ for tests');
}

eval(substr($source, $openingTag + 5));

// PHPStan otherwise gets really, really mad
eval(<<<'PHP'
class JitBlacklistTestUnderlying extends \Communism\__Underlying__
{
    public static function disableJitForMethod(string $className, string $method): void
    {
        \JitBlacklistDestructorProbe::$blacklistCalls++;
    }
}
PHP);
