set shell := ["bash", "-uc"]
set windows-shell := ["pwsh", "-NoLogo", "-Command"]

default:
    @just --list

install:
    composer install

install-node:
    npm install

validate:
    composer validate --strict

format:
    composer run-script format
    npx prettier --write docs

format-check:
    composer run-script format:check
    npx prettier --check docs

prettier:
    npx prettier --write .

prettier-check:
    npx prettier --check .

analyse:
    composer run-script analyse

analyze: analyse

test:
    composer run-script test

test-communism:
    composer run-script test:communism

test-zendful:
    composer run-script test:zendful

test-mixin:
    composer run-script test:mixin

test-reflect:
    composer run-script test:reflect

test-bytecode:
    composer run-script test:bytecode

test-internals:
    composer run-script test:internals

test-maintenance:
    composer run-script test:maintenance

test-zendful-api:
    composer run-script test:zendful:api

test-zendful-internals:
    composer run-script test:zendful:internals

test-zendful-architecture:
    composer run-script test:zendful:architecture

paratest:
    composer run-script paratest

paratest-communism:
    composer run-script paratest:communism

paratest-zendful:
    composer run-script paratest:zendful

coverage: coverage-zendful coverage-communism

coverage-zendful:
    composer run-script coverage:zendful

coverage-communism:
    composer run-script coverage:communism

[unix]
crash-diagnostics-zendful:
    ./tools/crash-diagnostics/run.sh coverage.php zendful

[unix]
crash-diagnostics-communism:
    ./tools/crash-diagnostics/run.sh coverage.php communism

[windows]
crash-diagnostics-zendful:
    ./tools/crash-diagnostics/run.ps1 coverage.php zendful

[windows]
crash-diagnostics-communism:
    ./tools/crash-diagnostics/run.ps1 coverage.php communism
