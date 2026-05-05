--TEST--
Observer: Recorded error buffer is detached for every replay iteration
--EXTENSIONS--
zend_test
--INI--
zend_test.observer.enabled=1
zend_test.observer.show_output=1
zend_test.observer.observe_errors=1
error_reporting=0
--SKIPIF--
<?php
if (getenv('SKIP_PRELOAD')) die('skip Recorded errors are emitted during preloading');
?>
--FILE--
<?php
use Foo;
use Bar;
echo "done\n";
?>
--EXPECTF--
<!-- error: type=2, recorded_errors=0 -->
<!-- error: type=2, recorded_errors=0 -->
<!-- init '%s' -->
done
