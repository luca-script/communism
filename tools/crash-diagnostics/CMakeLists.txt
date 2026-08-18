cmake_minimum_required(VERSION 3.20)
project(zendful_crash_diagnostics C)

set(PHP_SOURCE_DIR "${CMAKE_CURRENT_SOURCE_DIR}/../../php-src")

set(CMAKE_C_STANDARD 17)
set(CMAKE_C_STANDARD_REQUIRED ON)

if(WIN32)
    add_library(zendful_crash_diagnostics SHARED windows.c)
    target_link_libraries(zendful_crash_diagnostics PRIVATE dbghelp)
else()
    add_library(zendful_crash_diagnostics SHARED linux.c)
endif()

target_include_directories(zendful_crash_diagnostics PRIVATE
    "${PHP_SOURCE_DIR}/main"
    "${PHP_SOURCE_DIR}/Zend"
)

target_compile_definitions(zendful_crash_diagnostics PRIVATE ZENDFUL_CRASH_BUILD)
