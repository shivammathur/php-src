--TEST--
FFI rejects conflicting calling conventions in nested declarations
--EXTENSIONS--
ffi
--INI--
ffi.enable=1
--FILE--
<?php
foreach ([
    'typedef void __cdecl (__stdcall *callback)(int);',
    'typedef void __cdecl ((__stdcall *callback))(int);',
    'void accept(void __cdecl (__stdcall *callback)(int));',
    'void accept(void __cdecl (__stdcall *)(int));',
    'typedef void *__cdecl (__stdcall *callback)(int);',
] as $declaration) {
    try {
        FFI::cdef($declaration);
    } catch (FFI\ParserException $e) {
        echo $e->getMessage(), "\n";
    }
}
try {
    $ffi = FFI::cdef();
    $ffi->type('void __cdecl (__stdcall *)(int)');
} catch (FFI\ParserException $e) {
    echo $e->getMessage(), "\n";
}
?>
--EXPECT--
Multiple calling convention specifiers at line 1
Multiple calling convention specifiers at line 1
Multiple calling convention specifiers at line 1
Multiple calling convention specifiers at line 1
Multiple calling convention specifiers at line 1
Multiple calling convention specifiers at line 1
