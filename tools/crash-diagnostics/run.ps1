param(
    [Parameter(Mandatory = $true, Position = 0, ValueFromRemainingArguments = $true)]
    [string[]] $PhpArgument
)

$projectRoot = (Resolve-Path (Join-Path $PSScriptRoot '..\..')).Path
$buildDirectory = Join-Path $projectRoot 'tmp\crash-diagnostics'
New-Item -ItemType Directory -Force -Path $buildDirectory | Out-Null

$vswhereCandidates = @(
    (Join-Path ${env:ProgramFiles(x86)} 'Microsoft Visual Studio\Installer\vswhere.exe'),
    (Join-Path $env:ProgramFiles 'Microsoft Visual Studio\Installer\vswhere.exe')
)
$vswhere = $vswhereCandidates | Where-Object { Test-Path -LiteralPath $_ } | Select-Object -First 1
if ($null -eq $vswhere) {
    throw 'Visual Studio installation discovery tool (vswhere.exe) was not found.'
}

$visualStudioRoot = (& $vswhere -latest -products '*' -requires Microsoft.VisualStudio.Component.VC.Tools.x86.x64 -property installationPath | Select-Object -First 1).Trim()
if ([string]::IsNullOrWhiteSpace($visualStudioRoot)) {
    throw 'A Visual Studio installation with the C++ x64 toolchain was not found.'
}

$vcvars = Join-Path $visualStudioRoot 'VC\Auxiliary\Build\vcvars64.bat'
$source = Join-Path $projectRoot 'tools\crash-diagnostics\windows.c'
$library = Join-Path $buildDirectory 'zendful_crash_diagnostics.dll'

if (!(Test-Path -LiteralPath $vcvars)) {
    throw "Visual Studio environment script was not found: $vcvars"
}

$objectFile = Join-Path $buildDirectory 'windows.obj'
$importLibrary = Join-Path $buildDirectory 'zendful_crash_diagnostics.lib'
$phpDirectory = Split-Path (Get-Command php).Source
$phpInclude = Join-Path $phpDirectory 'include'
$phpImportLibrary = Join-Path $phpDirectory 'php8embed.lib'
if (!(Test-Path -LiteralPath $phpImportLibrary)) {
    throw "PHP import library was not found: $phpImportLibrary"
}
if (!(Test-Path -LiteralPath (Join-Path $phpInclude 'Zend\zend_config.w32.h'))) {
    throw "PHP development headers were not found in the PHP installation: $phpInclude"
}
if (!(Test-Path -LiteralPath $library) -or (Get-Item -LiteralPath $source).LastWriteTimeUtc -gt (Get-Item -LiteralPath $library).LastWriteTimeUtc) {
    cmd /c "call `"$vcvars`" && cl /nologo /LD /DZEND_WIN32 /DZEND_DEBUG=0 /DZEND_MM_ALIGNMENT=8 /DZEND_MM_ALIGNMENT_LOG2=3 /I`"$phpInclude`" /I`"$phpInclude\main`" /I`"$phpInclude\Zend`" /I`"$phpInclude\TSRM`" /Fo`"$objectFile`" `"$source`" /link /OUT:`"$library`" /IMPLIB:`"$importLibrary`" `"$phpImportLibrary`" dbghelp.lib"
    if ($LASTEXITCODE -ne 0) {
        exit $LASTEXITCODE
    }
}

$env:ZENDFUL_CRASH_DIAGNOSTICS_LIBRARY = $library
$preload = Join-Path $projectRoot 'tools\crash-diagnostics\preload.php'
& php '-d' "auto_prepend_file=$preload" @PhpArgument
exit $LASTEXITCODE
