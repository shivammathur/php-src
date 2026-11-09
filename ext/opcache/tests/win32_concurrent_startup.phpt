--TEST--
Concurrent Windows OPcache startup initializes shared globals and JIT before reattachment
--EXTENSIONS--
opcache
--SKIPIF--
<?php
if (PHP_OS_FAMILY !== 'Windows') die('skip Windows only');
if (!function_exists('proc_open')) die('skip proc_open() unavailable');
if (!array_key_exists('opcache.jit', opcache_get_configuration()['directives'])) {
    die('skip JIT unavailable');
}
?>
--FILE--
<?php
$ext = ini_get('extension_dir');
$command = [PHP_BINARY, '-n', '-d', "extension_dir=$ext"];
if (file_exists($ext . '/php_opcache.dll')) {
    array_push($command, '-d', 'zend_extension=opcache');
}
array_push($command, '-d', 'opcache.enable_cli=1');
foreach (['disable', 'tracing'] as $jit) {
    for ($batch = 0; $batch < 4; $batch++) {
        $args = array_merge($command, [
            '-d', 'opcache.cache_id=' . uniqid('startup_', true),
            '-d', "opcache.jit=$jit",
            '-d', 'opcache.jit_buffer_size=' . ($jit === 'tracing' ? '16M' : '0'),
            '-r', <<<'PHP'
                $status = opcache_get_status(false);
                if (!$status || !$status['opcache_enabled']) exit(1);
                if (ini_get('opcache.jit') === 'tracing' && !$status['jit']['on']) exit(2);
                usleep(100000);
                echo 'OK';
                PHP,
        ]);
        $children = [];
        for ($i = 0; $i < 16; $i++) {
            $process = proc_open($args, [1 => ['pipe', 'w'], 2 => ['redirect', 1]], $pipes);
            $children[] = [$process, $pipes[1]];
        }
        foreach ($children as [$process, $pipe]) {
            $output = stream_get_contents($pipe);
            fclose($pipe);
            $exit = proc_close($process);
            if ($exit !== 0 || $output !== 'OK') {
                echo "Unexpected child result: $exit ", var_export($output, true), "\n";
            }
        }
    }
    echo "$jit: done\n";
}
?>
--EXPECT--
disable: done
tracing: done
