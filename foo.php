<?php

declare(strict_types=1);

require __DIR__ . '/vendor/autoload.php';

use Communism\Bytecode;

function foo(int $a, int $b): int
{
    print "Hello!";
    return $a + $b;
    // if ($kaz > 100) {
    //     print "Kaz is high";
    // } else {
    //     print "Kaz is low!";
    // }
}

$foo = Bytecode::disassemble("foo");
print $foo;
