--TEST--
FFI preserves calling conventions in nested declarations
--EXTENSIONS--
ffi
--INI--
ffi.enable=1
--FILE--
<?php
function pointerAbi(FFI\CType $type): int {
    return $type->getPointerType()->getFuncABI();
}

foreach (['__cdecl', '__stdcall', '__fastcall', '__thiscall', '__vectorcall',
          '__attribute__((ms_abi))', '__attribute__((sysv_abi))'] as $cc) {
    try {
        $ffi = FFI::cdef("typedef void $cc (*reference)(int);");
    } catch (FFI\ParserException $e) {
        continue; // This calling convention is not supported by this libffi build.
    }
    $expected = pointerAbi($ffi->type('reference'));
    $ffi = FFI::cdef("
        typedef void ($cc *callback)(int);
        typedef void (*accept)(void ($cc *callback)(int), void ($cc *)(int));
        typedef void ($cc (*parenthesized_pointer))(int);
        typedef void (($cc *parenthesized_declaration))(int);
        typedef void ($cc (*wrapped_pointer)(int));
        typedef void (__cdecl *($cc *factory)(void))(int);
        typedef void (__cdecl (*($cc *wrapped_factory)(void)))(int);
        typedef void ($cc *(*callback_factory)(void))(int);
        typedef void ($cc (*(*wrapped_callback_factory)(void))(int));
    ");
    $cases = [
        'typedef' => $ffi->type('callback'),
        'abstract' => $ffi->type("void ($cc *)(int)"),
        'parameter' => $ffi->type('accept')->getPointerType()->getFuncParameterType(0),
        'abstract parameter' => $ffi->type('accept')->getPointerType()->getFuncParameterType(1),
        'parenthesized pointer' => $ffi->type('parenthesized_pointer'),
        'parenthesized declaration' => $ffi->type('parenthesized_declaration'),
        'wrapped pointer' => $ffi->type('wrapped_pointer'),
        'factory' => $ffi->type('factory'),
        'wrapped factory' => $ffi->type('wrapped_factory'),
        'return type' => $ffi->type('callback_factory')->getPointerType()->getFuncReturnType(),
        'wrapped return type' => $ffi->type('wrapped_callback_factory')->getPointerType()->getFuncReturnType(),
    ];
    foreach ($cases as $name => $type) {
        $actual = pointerAbi($type);
        if ($actual !== $expected) {
            echo "$cc, $name: ";
            var_dump($expected, $actual);
        }
    }
    // A function and the callback it returns have independent conventions.
    foreach (['factory', 'wrapped_factory'] as $name) {
        $returned = $ffi->type($name)->getPointerType()->getFuncReturnType();
        if (pointerAbi($returned) !== pointerAbi($ffi->type('void __cdecl (*)(int)'))) {
            echo "$cc, $name: incorrect return convention\n";
        }
    }
}
echo "Done\n";
?>
--EXPECT--
Done
