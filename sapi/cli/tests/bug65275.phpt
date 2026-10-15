--TEST--
Bug #65275: Calling exit() in a shutdown function does not change the exit value in CLI
--FILE--
<?php

$php = getenv('TEST_PHP_EXECUTABLE_ESCAPED');
// Use a separate OPcache cache for the child on Windows.
$args = getenv('TEST_PHP_EXTRA_ARGS') . ' -d opcache.cache_id=bug65275_' . getmypid();
exec($php . ' ' . $args . ' ' . escapeshellarg(__DIR__ . '/bug65275.inc'), $output, $exit_status);
var_dump($exit_status);

?>
--EXPECT--
int(111)
