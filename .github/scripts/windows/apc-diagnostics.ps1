$ErrorActionPreference = 'Stop'
$PSNativeCommandUseErrorActionPreference = $false
$build = if ($env:THREAD_SAFE -eq '1') { 'C:\obj\Release_TS' } else { 'C:\obj\Release' }
$php = "$build\php.exe"
$deps = "C:\build-cache\deps-8.4-vs17-$env:PLATFORM"
Copy-Item "$deps\bin\*.dll" $build -Force
$results = New-Item -ItemType Directory -Force results
$flags = @('-n', '-d', "extension_dir=$build", '-d', 'extension=ffi', '-d', 'ffi.enable=1')
& $php @flags -v
& $php @flags --ri ffi
$env:REPORT_EXIT_STATUS = '1'
$env:TEST_PHP_JUNIT = "$results\phpt.xml"
& $php -n run-tests.php -p $php @flags -q --offline --no-progress --show-diff ext/standard/tests/general_functions/sleep_alertable_win32.phpt ext/ffi/tests 2>&1 | Tee-Object "$results\phpt.log"
$testExit = $LASTEXITCODE
Copy-Item 'ext/standard/tests/general_functions/sleep_alertable_win32.*' $results -Force
$debugRoot = "${env:ProgramFiles(x86)}\Windows Kits\10\Debuggers\$env:PLATFORM"
$cdb = "$debugRoot\cdb.exe"
if (-not (Test-Path $cdb)) {
    Invoke-WebRequest 'https://download.visualstudio.microsoft.com/download/pr/e690ed5c-a2c2-42aa-8cbb-4f3ea5f87edf/8184cebe60d18b62a5cfd99bd6551eff/winsdksetup.exe' -OutFile "$env:TEMP\winsdksetup.exe"
    if ((Get-FileHash "$env:TEMP\winsdksetup.exe").Hash -ne '9B447E73C9ADA8D912F1633662334927B3F4A0E6DFAAB787ABFB150A69A0225A') { throw 'Unexpected SDK installer hash' }
    $setup = Start-Process "$env:TEMP\winsdksetup.exe" -ArgumentList '/features','OptionId.WindowsDesktopDebuggers','/quiet','/norestart' -Wait -PassThru
    if ($setup.ExitCode -notin 0,3010) { throw "Debugger install failed: $($setup.ExitCode)" }
}
if (-not (Test-Path $cdb)) { throw 'CDB is missing' }
$symbols = "$build;$deps\lib;srv*C:\symbols*https://msdl.microsoft.com/download/symbols"
$summary = @()
foreach ($mode in 'direct','sleepex','sleepex-nested','usleep','usleep-nested') {
    & $php @flags .github/scripts/windows/apc-probe.php $mode 2>&1 | Tee-Object "$results\$mode.log"
    $code = $LASTEXITCODE
    $summary += @{mode=$mode;exitCode=$code}
    $commands = @"
sxe -c ".echo ACCESS_VIOLATION; .exr -1; .ecxr; kv 60; r; u @eip-20 @eip+30; dps @esp L30; lm; .dump /ma $results/$mode.dmp; q" av
g
q
"@
    $commands | Set-Content "$results\$mode.commands"
    & $cdb -G -y $symbols -cf "$results\$mode.commands" $php @flags .github/scripts/windows/apc-probe.php $mode 2>&1 | Tee-Object "$results\$mode-debugger.log"
}
@{testExit=$testExit;probes=$summary} | ConvertTo-Json -Depth 5 | Tee-Object "$results\summary.json"
exit $testExit
