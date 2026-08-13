<?php

declare(strict_types=1);

// OPcache keeps user classes and their method tables in persistent storage.
// The internals tests rewrite those tables, so reset it before Composer loads
// any test classes. This also disables OPcache for the current request.
if (function_exists('opcache_reset')) {
    opcache_reset();
}

require_once __DIR__ . '/../vendor/autoload.php';

// Zend is final in production. Tests need to derive from it in
// order to replace runtime hooks, so load a non-final copy before Composer's
// autoloader can load the production class.
$source = file_get_contents(__DIR__ . '/../src/Communism/Internals/Zend.php');

if ($source === false) {
    throw new RuntimeException('Could not read Zend for tests');
}

$source = str_replace('final class Zend', 'class Zend', $source, $replacements);

if ($replacements !== 1) {
    throw new RuntimeException('Could not prepare Zend for tests');
}

$openingTag = strpos($source, '<?php');

if ($openingTag === false) {
    throw new RuntimeException('Could not prepare Zend for tests');
}

eval(substr($source, $openingTag + 5));

// PHPStan otherwise gets really, really mad
eval(<<<'PHP'
class JitBlacklistTestZend extends \Communism\Internals\Zend
{
    public static function disableJitForMethod(string $className, string $method): void
    {
        \JitBlacklistDestructorProbe::$blacklistCalls++;
    }
}
PHP);
