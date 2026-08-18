#include <Zend/zend_config.w32.h>
#include <stdio.h>
#include <stdlib.h>

typedef __int64 ssize_t;

#include "zend_compile.h"
#include "zend_API.h"
#include "zend_builtin_functions.h"
#include "zend_execute.h"
#include "zend_exceptions.h"
#include "zend_globals.h"

#include <windows.h>
#include <dbghelp.h>

static char dump_path[MAX_PATH];
static volatile LONG report_started;

static void read_configuration(void)
{
    const char *path = getenv("ZENDFUL_CRASH_DIAGNOSTICS_DUMP_PATH");
    if (path != NULL) {
        snprintf(dump_path, sizeof(dump_path), "%s", path);
    }
}

static void write_php_backtrace(FILE *stream)
{
    zval trace;
    ZVAL_UNDEF(&trace);

    fputs("=== PHP frames ===\n", stream);
    zend_fetch_debug_backtrace(&trace, 1, DEBUG_BACKTRACE_IGNORE_ARGS, 0);

    if (Z_TYPE(trace) == IS_ARRAY) {
        zend_string *text = zend_trace_to_string(Z_ARRVAL(trace), false);
        if (text != NULL) {
            fprintf(stream, "%s", ZSTR_VAL(text));
            zend_string_release(text);
        }
    }

    zval_ptr_dtor(&trace);
}

static void write_dump(EXCEPTION_POINTERS *exception)
{
    if (InterlockedCompareExchange(&report_started, 1, 0) != 0) {
        return;
    }

    const char *path = dump_path[0] == '\0' ? NULL : dump_path;
    FILE *dump = path == NULL ? NULL : fopen(path, "w");
    FILE *stream = dump == NULL ? stderr : dump;
    setvbuf(stream, NULL, _IONBF, 0);

    fputs("=== crash diagnostics ===\n", stream);

    if (exception != NULL && exception->ExceptionRecord != NULL) {
        fprintf(
            stream,
            "exception=0x%08lx address=%p\n",
            exception->ExceptionRecord->ExceptionCode,
            exception->ExceptionRecord->ExceptionAddress
        );
    } else {
        fputs("exception=unknown\n", stream);
    }
    __try {
        write_php_backtrace(stream);
    } __except (EXCEPTION_EXECUTE_HANDLER) {
        fputs("PHP frame walk failed while reading Zend executor context.\n", stream);
    }
    fputs("=== native frames ===\n", stream);
    void *frames[128];
    USHORT frame_count = CaptureStackBackTrace(0, 128, frames, NULL);
    HANDLE process = GetCurrentProcess();
    SymInitialize(process, NULL, TRUE);
    SYMBOL_INFO *symbol = calloc(sizeof(SYMBOL_INFO) + 256, 1);
    symbol->MaxNameLen = 255;
    symbol->SizeOfStruct = sizeof(SYMBOL_INFO);

    for (USHORT index = 0; index < frame_count; index++) {
        DWORD64 displacement = 0;
        if (SymFromAddr(process, (DWORD64) frames[index], &displacement, symbol)) {
            fprintf(stream, "NATIVE[%u] %s+0x%llx\n", index, symbol->Name, displacement);
        } else {
            fprintf(stream, "NATIVE[%u] %p\n", index, frames[index]);
        }
    }

    free(symbol);
    if (dump == NULL) {
        fflush(stderr);
    } else {
        fclose(dump);

        FILE *report = fopen(path, "r");
        if (report != NULL) {
            char buffer[4096];
            size_t bytes;
            while ((bytes = fread(buffer, 1, sizeof(buffer), report)) > 0) {
                fwrite(buffer, 1, bytes, stderr);
            }
            fclose(report);
        }
    }
}

static LONG WINAPI crash_handler(EXCEPTION_POINTERS *exception)
{
    write_dump(exception);
    ExitProcess(128 + (UINT) exception->ExceptionRecord->ExceptionCode);
}

__declspec(dllexport) int zendful_crash_install(void)
{
    if (getenv("TEST_TOKEN") != NULL || getenv("PARATEST") != NULL) {
        return 0;
    }

    read_configuration();

    return AddVectoredExceptionHandler(1, crash_handler) == NULL;
}

__declspec(dllexport) void zendful_crash_now(int kind)
{
    if (kind == 1) {
        RaiseException(EXCEPTION_ILLEGAL_INSTRUCTION, 0, 0, NULL);
    }

    *(volatile int *) 0 = 1;
}
