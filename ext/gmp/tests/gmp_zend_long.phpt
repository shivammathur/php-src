--TEST--
GMP conversions preserve the zend_long range
--EXTENSIONS--
gmp
--FILE--
<?php

foreach ([PHP_INT_MIN, PHP_INT_MAX] as $value) {
    $number = gmp_init($value);
    var_dump(gmp_strval($number) === (string) $value);
    var_dump(gmp_intval($number) === $value);
    var_dump((int) $number === $value);
}

var_dump(gmp_intval(gmp_init('9223372036854775808')) === 0);

?>
--EXPECT--
bool(true)
bool(true)
bool(true)
bool(true)
bool(true)
bool(true)
bool(true)
