<?php
$mode = $argv[1];
$placement = $argv[2] ?? 'fixed';
$callconv = PHP_INT_SIZE === 4 ? '__stdcall' : '';
$kernel32 = FFI::cdef(<<<C
    void *GetCurrentThread(void);
    unsigned long QueueUserAPC(void *callback, void *thread, uintptr_t data);
    unsigned long SleepEx(unsigned long milliseconds, int alertable);
C, 'kernel32.dll');
$header = <<<C
    typedef void ($callconv *PAPCFUNC)(uintptr_t);
    typedef void *($callconv *GetCurrentThread_t)(void);
    typedef unsigned long ($callconv *QueueUserAPC_t)(PAPCFUNC callback, void *thread, uintptr_t data);
    typedef unsigned long ($callconv *SleepEx_t)(unsigned long milliseconds, int alertable);
    typedef void $callconv ExpectedAPC(uintptr_t);
C;
if ($placement === 'fixed') {
    $header = str_replace("($callconv *", "$callconv (*", $header);
}
$ffi = FFI::cdef($header);
$expectedABI = $ffi->type('ExpectedAPC *')->getPointerType()->getFuncABI();
foreach (['PAPCFUNC', 'GetCurrentThread_t', 'QueueUserAPC_t', 'SleepEx_t'] as $name) {
    $actualABI = $ffi->type($name)->getPointerType()->getFuncABI();
    echo "$placement $name ABI=$actualABI expected=$expectedABI\n";
    if ($placement === 'fixed' && $actualABI !== $expectedABI) {
        throw new Exception("Wrong ABI for $name");
    }
}
$GetCurrentThread = $ffi->cast('GetCurrentThread_t', $kernel32->GetCurrentThread);
$QueueUserAPC = $ffi->cast('QueueUserAPC_t', $kernel32->QueueUserAPC);
$SleepEx = $ffi->cast('SleepEx_t', $kernel32->SleepEx);
$called = 0;
$callback = static function (int $data) use (&$called, $mode): void {
    echo "callback entered: $data\n";
    $called = $data;
    if (str_contains($mode, 'nested')) {
        usleep(300000);
        echo "nested sleep returned\n";
    }
    echo "callback returning\n";
};
echo "mode=$mode int_size=", PHP_INT_SIZE, " zts=", PHP_ZTS, "\n";
if ($mode === 'direct') {
    $holder = $ffi->new('PAPCFUNC[1]');
    $holder[0] = $callback;
    $holder[0](123);
} else {
    var_dump($QueueUserAPC($callback, $GetCurrentThread(), 123));
    echo "queued\n";
    if (str_starts_with($mode, 'sleepex')) {
        var_dump($SleepEx(1000, 1));
    } else {
        var_dump(usleep(1000000));
    }
}
echo "returned, called=$called\n";
exit($called === 123 ? 0 : 1);
