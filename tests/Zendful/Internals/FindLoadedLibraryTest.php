<?php

declare(strict_types=1);

namespace {

    use Zendful\Internals\FindLoadedLibrary as ZendfulFindLoadedLibrary;
    use Zendful\Internals\LinuxFfi;
    use Zendful\Internals\NativeLinuxFfi;
    use Zendful\Internals\NativeWindowsFfi;
    use Zendful\Internals\WindowsFfi;
    use Zendful\Zendful;

    beforeAll(function (): void {
        class_exists(NativeLinuxFfi::class);
        class_exists(NativeWindowsFfi::class);
        $GLOBALS['findLoadedLibraryFilesystemMode'] = 'real';
        Zendful::function('Zendful\\Internals\\is_file')->swapWith(
            Zendful::function('Zendful\\Internals\\is_file_dispatch'),
        );
        Zendful::function('Zendful\\Internals\\file')->swapWith(
            Zendful::function('Zendful\\Internals\\file_dispatch'),
        );
    });
    it('finds loaded PHP libraries through the operating system', function (): void {
        $libraries = ZendfulFindLoadedLibrary::php();

        expect($libraries)->toBeArray();

        // A statically linked Linux CLI has no libphp*.so mapping. In that case
        // Zend falls through to RTLD_DEFAULT, which is covered by the bytecode
        // tests. Windows PHP always exposes php*.dll through ToolHelp.
        if (PHP_OS_FAMILY === 'Windows') {
            expect(count($libraries))->toBeGreaterThan(0);
        }

        foreach ($libraries as $library) {
            expect($library)->toBeString();
            expect(strtolower($library))->toContain('php');
            if (PHP_OS_FAMILY === 'Linux') {
                expect(ZendfulFindLoadedLibrary::isLoaded($library))->toBeTrue();
            }
        }
    });

    it('fails closed for a library that cannot be loaded', function (): void {
        expect(ZendfulFindLoadedLibrary::isLoaded('/definitely/not-a-loaded-php-library.so'))
            ->toBeFalse();
    });

    it('enumerates PHP shared objects through the Linux dynamic loader', function (): void {
        $method = new ReflectionMethod(ZendfulFindLoadedLibrary::class, 'phpFromDlIteratePhdr');

        expect($method->isPrivate())->toBeTrue();

        if (PHP_OS_FAMILY !== 'Linux') {
            return;
        }

        /** @var list<string> $libraries */
        $libraries = $method->invoke(null);

        expect($libraries)->toBeArray();
        foreach ($libraries as $library) {
            expect($library)->toContain('php');
        }
    });

    it('parses normal and deleted Linux library mappings', function (): void {
        $method = new ReflectionMethod(ZendfulFindLoadedLibrary::class, 'phpFromLinuxMaps');

        /** @var list<string> $libraries */
        $libraries = $method->invoke(null, [
            '7f0000000000-7f0000100000 r-xp 00000000 08:01 123 /usr/lib/libc.so.6',
            '7f0000100000-7f0000200000 r-xp 00000000 08:01 124 /tmp/libphp8.5.so',
            '7f0000200000-7f0000300000 r-xp 00000000 08:01 125 /tmp/libphp8.5.so.1 (deleted)',
            '7f0000300000-7f0000400000 r-xp 00000000 08:01 126 /tmp/libother.so (deleted)',
        ]);

        expect($libraries)->toBe([
            '/tmp/libphp8.5.so',
            '/tmp/libphp8.5.so.1',
        ]);

        /** @var list<string> $empty */
        $empty = $method->invoke(null, [
            'not a mapping',
            '7f0000000000-7f0000100000 r-xp 00000000 08:01 123 /tmp/libother.so',
            '7f0000000000-7f0000100000 r-xp 00000000 08:01 123 /tmp/libphp8.5.so (deleted) trailing',
        ]);

        expect($empty)->toBe([]);
    });

    it('exercises each platform loader path and fails closed when unavailable', function (): void {
        $invoke = static function (string $name, mixed ...$arguments): mixed {
            $method = new ReflectionMethod(ZendfulFindLoadedLibrary::class, $name);
            $method->setAccessible(true);

            return $method->invoke(null, ...$arguments);
        };

        expect($invoke('isLoadedOnLinux', '/definitely/not-a-loaded-php-library.so'))->toBeFalse()
            ->and($invoke('phpOnWindows'))->toBeArray();
    });

    it('covers every platform dispatch through injected loaders', function (): void {
        $isLoaded = new ReflectionMethod(ZendfulFindLoadedLibrary::class, 'isLoadedForPlatform');
        $php = new ReflectionMethod(ZendfulFindLoadedLibrary::class, 'phpForPlatform');
        $linux = new FindLoadedLibraryLinuxFfiMock();
        $windows = new FindLoadedLibraryWindowsFfiMock();

        expect($isLoaded->invoke(null, 'Linux', '/usr/lib/libphp8.5.so', $linux, null))->toBeTrue()
            ->and($isLoaded->invoke(null, 'Windows', 'C:\\php\\php8.dll', null, $windows))->toBeTrue()
            ->and($isLoaded->invoke(null, 'Other', 'php8.dll', null, null))->toBeFalse()
            ->and($php->invoke(null, 'Linux', $linux, null))->toContain('/usr/lib/libphp8.5.so')
            ->and($php->invoke(null, 'Windows', null, $windows))->toContain('C:\\php\\php8.dll')
            ->and($php->invoke(null, 'Other', null, null))->toBe([]);
    });

    it('fails closed for an unavailable Windows process id', function (): void {
        $method = new ReflectionMethod(ZendfulFindLoadedLibrary::class, 'phpOnWindows');
        $mock = new FindLoadedLibraryWindowsFfiMock();
        expect($method->invoke(null, $mock, static fn(): false => false))->toBe([]);
    });

    it('fails closed for unavailable and throwing Windows snapshots', function (): void {
        $method = new ReflectionMethod(ZendfulFindLoadedLibrary::class, 'phpOnWindows');

        expect($method->invoke(null, new FindLoadedLibraryNullSnapshotWindowsFfiMock()))->toBe([])
            ->and($method->invoke(null, new FindLoadedLibraryThrowingWindowsFfiMock()))->toBe([]);
    });

    it('replaces filesystem functions through Zendful and covers Linux map branches', function (): void {
        $method = new ReflectionMethod(ZendfulFindLoadedLibrary::class, 'phpOnLinux');
        $GLOBALS['findLoadedLibraryFilesystemMode'] = 'missing';
        expect($method->invoke(null))->toBeArray();

        $GLOBALS['findLoadedLibraryFilesystemMode'] = 'unreadable';
        expect($method->invoke(null))->toBeArray();

        $GLOBALS['findLoadedLibraryFilesystemMode'] = 'maps';
        $linuxFfi = new FindLoadedLibraryLinuxFfiMock();
        /** @var list<string> $libraries */
        $libraries = $method->invoke(null, $linuxFfi);
        $GLOBALS['findLoadedLibraryFilesystemMode'] = 'real';

        expect($libraries)->toBeArray();
        expect($libraries)->toContain('/usr/lib/libphp8.5.so', '/tmp/libphp8.5.so', '/tmp/libphp8.5.so.1')
            ->and($linuxFfi->isLoaded('/usr/lib/libphp8.5.so'))->toBeTrue()
            ->and($linuxFfi->isLoaded('/usr/lib/libother.so'))->toBeFalse();
    });

    it('uses the injected Linux loader for loaded-library checks', function (): void {
        $method = new ReflectionMethod(ZendfulFindLoadedLibrary::class, 'isLoadedOnLinux');
        $mock = new FindLoadedLibraryLinuxFfiMock();

        expect($method->invoke(null, '/usr/lib/libphp8.5.so', $mock))->toBeTrue()
            ->and($method->invoke(null, '/usr/lib/libother.so', $mock))->toBeFalse();
    });

    it('enumerates mocked Windows modules through the FFI-shaped boundary', function (): void {
        $method = new ReflectionMethod(ZendfulFindLoadedLibrary::class, 'phpOnWindows');
        $mock = new FindLoadedLibraryWindowsFfiMock();

        /** @var list<string> $libraries */
        $libraries = $method->invoke(null, $mock);

        expect($libraries)->toBe([
            'C:\\php\\php8.dll',
            'php-cli.dll',
        ])
            ->and($mock->closed)->toBeTrue();
    });

    it('runs the native Linux adapter through Zendful-replaced system calls', function (): void {
        $adapter = new NativeLinuxFfiTestDouble();

        expect($adapter->phpLibraries())->toBe(['/usr/lib/libphp8.5.so'])
            ->and($adapter->isLoaded('/usr/lib/libphp8.5.so'))->toBeTrue()
            ->and($adapter->isLoaded('/usr/lib/libother.so'))->toBeFalse();
    });

    it('covers the native Linux FFI primitive hooks with local CData', function (): void {
        $reflection = new ReflectionClass(NativeLinuxFfi::class);
        $adapter = $reflection->newInstanceWithoutConstructor();

        $cdef = $reflection->getMethod('cdef');
        $isNull = $reflection->getMethod('isNull');
        $string = $reflection->getMethod('string');

        $cdef->invoke($adapter, 'typedef int zendful_test_integer;');
        $ffi = FFI::cdef('typedef void *zendful_test_pointer; typedef char zendful_test_string[4];');
        $pointer = $ffi->new('zendful_test_pointer');
        /** @var \FFI\CData $buffer */
        $buffer = $ffi->new('zendful_test_string');
        $buffer[0] = 'p';
        $buffer[1] = 'h';
        $buffer[2] = 'p';
        $buffer[3] = "\0";

        expect($isNull->invoke($adapter, $pointer))->toBeTrue()
            ->and($string->invoke($adapter, $buffer))->toBe('php');
    });

    it('runs the native Windows adapter through Zendful-replaced system calls', function (): void {
        $adapter = new NativeWindowsFfiTestDouble();
        $snapshot = $adapter->createSnapshot(8, 0);
        $module = $adapter->newModule();

        $adapter->setModuleSize($module);

        expect($adapter->isNull($snapshot))->toBeFalse()
            ->and($adapter->moduleFirst($snapshot, $module))->toBeTrue()
            ->and($adapter->moduleName($module))->toBe('php8.dll')
            ->and($adapter->modulePath($module))->toBe('C:\\php\\php8.dll')
            ->and($adapter->moduleNext($snapshot, $module))->toBeFalse();

        $adapter->closeHandle($snapshot);

        expect(NativeWindowsFfiTestDouble::$ffi->closed)->toBeTrue();
    });

    final class FindLoadedLibraryWindowsFfiMock implements WindowsFfi
    {
        public bool $closed = false;

        private int $index = -1;

        /** @var list<array{name: string, path: string}> */
        private array $modules = [
            ['name' => 'php8.dll', 'path' => 'C:\\php\\php8.dll'],
            ['name' => 'php-cli.dll', 'path' => ''],
            ['name' => 'kernel32.dll', 'path' => 'C:\\Windows\\kernel32.dll'],
            ['name' => 'php8.dll', 'path' => 'C:\\php\\php8.dll'],
        ];

        public function createSnapshot(int $flags, int $processId): object
        {
            return new stdClass();
        }

        public function isNull(object $value): bool
        {
            return false;
        }

        /** @phpstan-return \Zendful_FFI\MODULEENTRY32A */
        public function newModule(): object
        {
            return FFI::cdef('typedef struct { int dwSize; char szModule[256]; char szExePath[260]; } MODULEENTRY32A;')
                ->new('MODULEENTRY32A');
        }

        public function setModuleSize(object $module): void {}

        public function moduleFirst(object $snapshot, object $module): bool
        {
            $this->index = 0;

            return true;
        }

        public function moduleNext(object $snapshot, object $module): bool
        {
            $this->index++;

            return isset($this->modules[$this->index]);
        }

        public function moduleName(object $module): string
        {
            return $this->modules[$this->index]['name'];
        }

        public function modulePath(object $module): string
        {
            return $this->modules[$this->index]['path'];
        }

        public function closeHandle(object $snapshot): void
        {
            $this->closed = true;
        }
    }

    final class FindLoadedLibraryNullSnapshotWindowsFfiMock implements WindowsFfi
    {
        public function createSnapshot(int $flags, int $processId): object
        {
            return new stdClass();
        }

        public function isNull(object $value): bool
        {
            return true;
        }

        public function newModule(): object
        {
            throw new LogicException('A null snapshot must not allocate a module.');
        }

        public function setModuleSize(object $module): void
        {
            throw new LogicException('A null snapshot must not initialize a module.');
        }

        public function moduleFirst(object $snapshot, object $module): bool
        {
            throw new LogicException('A null snapshot must not enumerate modules.');
        }

        public function moduleNext(object $snapshot, object $module): bool
        {
            throw new LogicException('A null snapshot must not enumerate modules.');
        }

        public function moduleName(object $module): string
        {
            throw new LogicException('A null snapshot must not read modules.');
        }

        public function modulePath(object $module): string
        {
            throw new LogicException('A null snapshot must not read modules.');
        }

        public function closeHandle(object $snapshot): void
        {
            throw new LogicException('A null snapshot must not close a handle.');
        }
    }

    final class FindLoadedLibraryThrowingWindowsFfiMock implements WindowsFfi
    {
        public function createSnapshot(int $flags, int $processId): object
        {
            throw new RuntimeException('Synthetic snapshot failure.');
        }

        public function isNull(object $value): bool
        {
            return false;
        }
        /** @phpstan-return \Zendful_FFI\MODULEENTRY32A */
        public function newModule(): object
        {
            return FFI::cdef('typedef struct { int dwSize; char szModule[256]; char szExePath[260]; } MODULEENTRY32A;')
                ->new('MODULEENTRY32A');
        }
        public function setModuleSize(object $module): void {}
        public function moduleFirst(object $snapshot, object $module): bool
        {
            return false;
        }
        public function moduleNext(object $snapshot, object $module): bool
        {
            return false;
        }
        public function moduleName(object $module): string
        {
            return '';
        }
        public function modulePath(object $module): string
        {
            return '';
        }
        public function closeHandle(object $snapshot): void {}
    }

    final class FindLoadedLibraryLinuxFfiMock implements LinuxFfi
    {
        public function phpLibraries(): array
        {
            return ['/usr/lib/libphp8.5.so'];
        }

        public function isLoaded(string $library): bool
        {
            return $library === '/usr/lib/libphp8.5.so';
        }
    }

    final class NativeLinuxFfiPointerMock
    {
        public function __construct(public readonly ?string $value) {}
    }

    final class NativeLinuxFfiApiMock
    {
        public int $closed = 0;

        public function dl_iterate_phdr(callable $callback, mixed $data): int
        {
            foreach ([
                new NativeLinuxFfiPointerMock('/usr/lib/libphp8.5.so'),
                new NativeLinuxFfiPointerMock(null),
                new NativeLinuxFfiPointerMock('/usr/lib/libother.so'),
            ] as $name) {
                $callback((object) ['dlpi_name' => $name], 0, $data);
            }

            return 0;
        }

        public function dlopen(string $library, int $flags): ?object
        {
            return $library === '/usr/lib/libphp8.5.so' ? new stdClass() : null;
        }

        public function dlclose(object $handle): int
        {
            $this->closed++;

            return 0;
        }
    }

    final class NativeWindowsFfiPointerMock
    {
        public function __construct(public readonly string $value) {}
    }

    final class NativeWindowsFfiModuleMock
    {
        public int $dwSize = 0;
        public NativeWindowsFfiPointerMock $szModule;
        public NativeWindowsFfiPointerMock $szExePath;

        public function __construct()
        {
            $this->szModule = new NativeWindowsFfiPointerMock('php8.dll');
            $this->szExePath = new NativeWindowsFfiPointerMock('C:\\php\\php8.dll');
        }
    }

    final class NativeWindowsFfiApiMock
    {
        public bool $closed = false;

        public function CreateToolhelp32Snapshot(int $flags, int $processId): object
        {
            return new stdClass();
        }

        public function Module32First(object $snapshot, object $module): int
        {
            return 1;
        }

        public function Module32Next(object $snapshot, object $module): int
        {
            return 0;
        }

        public function CloseHandle(object $snapshot): int
        {
            $this->closed = true;

            return 1;
        }
    }

    final class NativeLinuxFfiTestDouble extends NativeLinuxFfi
    {
        public static NativeLinuxFfiApiMock $ffi;

        protected function cdef(string $code, ?string $library = null): object
        {
            return self::$ffi ??= new NativeLinuxFfiApiMock();
        }

        protected function isNull(object $value): bool
        {
            return $value instanceof NativeLinuxFfiPointerMock && $value->value === null;
        }

        protected function string(object $value): string
        {
            /** @var NativeLinuxFfiPointerMock $value */
            return (string) $value->value;
        }
    }

    final class NativeWindowsFfiTestDouble extends NativeWindowsFfi
    {
        public static NativeWindowsFfiApiMock $ffi;

        protected function cdef(string $code, ?string $library = null): object
        {
            return self::$ffi ??= new NativeWindowsFfiApiMock();
        }

        protected function ffiIsNull(object $value): bool
        {
            return false;
        }

        protected function newModuleValue(object $ffi, string $type): object
        {
            return new NativeWindowsFfiModuleMock();
        }

        protected function sizeOf(object $value): int
        {
            return 512;
        }

        protected function addressOf(object $value): object
        {
            return $value;
        }

        protected function string(object $value): string
        {
            /** @var NativeWindowsFfiPointerMock $value */
            return $value->value;
        }
    }

}
