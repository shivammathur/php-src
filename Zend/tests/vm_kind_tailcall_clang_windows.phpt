--TEST--
Windows x64 Clang selects the VM according to the security flags
--SKIPIF--
<?php
if (PHP_OS_FAMILY !== 'Windows') die('skip Windows only');
if (php_uname('m') !== 'AMD64') die('skip x64 only');

ob_start();
phpinfo(INFO_GENERAL);
$info = ob_get_clean();

if (!preg_match('/Compiler => clang version (\d+)/', $info, $m)) {
    die('skip not compiled with clang');
}

if ((int)$m[1] < 19) {
    die('skip requires clang >= 19');
}
?>
--FILE--
<?php
ob_start();
phpinfo(INFO_GENERAL);
$info = ob_get_clean();
preg_match('/^Configure Command => (.*)$/m', $info, $configure);
preg_match_all('/--(enable|disable)-security-flags(?:=(yes|no))?/', $configure[1], $options, PREG_SET_ORDER);
$security = true;
foreach ($options as $option) {
    $security = $option[1] === 'enable' && ($option[2] ?? 'yes') === 'yes';
}
var_dump(ZEND_VM_KIND === ($security ? 'ZEND_VM_KIND_CALL' : 'ZEND_VM_KIND_TAILCALL'));
?>
--EXPECT--
bool(true)
