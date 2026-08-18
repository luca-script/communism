#define _GNU_SOURCE

#include <execinfo.h>
#include <signal.h>
#include <stddef.h>
#include <stdio.h>
#include <stdlib.h>
#include <string.h>
#include <unistd.h>

#include "zend_compile.h"
#include "zend_API.h"
#include "zend_builtin_functions.h"
#include "zend_exceptions.h"
#include "zend_globals.h"

static char dump_path[1024];

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

static void crash_handler(int signal_number, siginfo_t *info, void *context)
{
    (void) context;

    const char *path = dump_path[0] == '\0' ? NULL : dump_path;
    FILE *dump = path == NULL ? NULL : fopen(path, "w");
    FILE *stream = dump == NULL ? stderr : dump;
    {
        fprintf(stream, "signal=%d address=%p\n", signal_number, info->si_addr);
        write_php_backtrace(stream);
        fputs("=== native frames ===\n", stream);

        void *frames[128];
        int frame_count = backtrace(frames, 128);
        backtrace_symbols_fd(frames, frame_count, fileno(stream));
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

    _exit(128 + signal_number);
}

int zendful_crash_install(void)
{
    if (getenv("TEST_TOKEN") != NULL || getenv("PARATEST") != NULL) {
        return 0;
    }

    read_configuration();

    struct sigaction action = {
        .sa_sigaction = crash_handler,
        .sa_flags = SA_SIGINFO | SA_RESETHAND,
    };
    sigemptyset(&action.sa_mask);

    return sigaction(SIGSEGV, &action, NULL)
        || sigaction(SIGABRT, &action, NULL)
        || sigaction(SIGBUS, &action, NULL);
}

void zendful_crash_now(int kind)
{
    if (kind == 1) {
        abort();
    }

    *(volatile int *) 0 = 1;
}
