<?php

// TODO: Move address/port info in here?
class CliServerInfo {
    public function __construct(
        public string $docRoot,
        public $processHandle,
    ) {}
}

function php_cli_server_startup_failure(
    string $reason,
    array $cmd,
    string $doc_root,
    string $output_file,
    $handle = null,
    ?array $status = null,
): never {
    if (is_resource($handle) && $status === null) {
        $status = proc_get_status($handle);
    }

    $output = file_get_contents($output_file);
    if ($output === false) {
        $output = '<unable to read server output file>';
    }

    echo "\n================ PHP CLI SERVER STARTUP FAILURE ================\n";
    printf("Reason: %s\n", $reason);
    printf("PHP version: %s\n", PHP_VERSION);
    printf("PHP binary: %s\n", PHP_BINARY);
    printf("OS: %s\n", PHP_OS);
    printf("Working directory: %s\n", $doc_root);
    printf("Working directory exists: %s\n", is_dir($doc_root) ? 'yes' : 'no');
    printf("Server executable exists: %s\n", is_file($cmd[0]) ? 'yes' : 'no');
    echo "Command arguments:\n";
    foreach ($cmd as $index => $argument) {
        printf("  [%d] %s\n", $index, var_export($argument, true));
    }
    if (PHP_OS_FAMILY === 'Windows'
            && isset($status['exitcode'])
            && $status['exitcode'] < 0
            && $status['exitcode'] !== -1) {
        $windows_exit_code = substr(sprintf('%08X', $status['exitcode']), -8);
        $windows_exit_description = $windows_exit_code === 'C0000005'
            ? 'access violation'
            : 'NTSTATUS failure';
        printf("Windows exit status: 0x%s (%s)\n", $windows_exit_code, $windows_exit_description);
    }
    printf("Process status: %s\n", $status === null ? '<unavailable>' : var_export($status, true));
    printf("Captured server output (%d bytes):\n%s\n", strlen($output), $output);
    echo "============== END PHP CLI SERVER STARTUP FAILURE ==============\n";

    if (is_resource($handle) && ($status['running'] ?? false)) {
        proc_terminate($handle);
    }
    exit(1);
}

function php_cli_server_start(
    ?string $code = 'echo "Hello world";',
    ?string $router = 'index.php',
    array $cmd_args = []
): CliServerInfo {
    $php_executable = getenv('TEST_PHP_EXECUTABLE') ?: PHP_BINARY;
    $error = null;

    // Create dedicated doc root to avoid index.php clashes between processes.
    $doc_root = __DIR__ . DIRECTORY_SEPARATOR . basename($_SERVER['PHP_SELF'], '.php') . '-' . getmypid();
    @mkdir($doc_root);

    if ($code) {
        file_put_contents($doc_root . '/' . ($router ?: 'index.php'), '<?php ' . $code . ' ?>');
    }

    // XXX: This should ideally use the same INI overrides as run-tests
    $cmd = [$php_executable, '-d', 'error_include_args=0', '-t', $doc_root, '-n', ...$cmd_args, '-S', 'localhost:0'];
    if (!is_null($router)) {
        $cmd[] = $router;
    }

    $output_file = tempnam(sys_get_temp_dir(), 'cli_server_output');
    $output_file_fd = fopen($output_file, 'ab');
    if ($output_file_fd === false) {
        die(sprintf("Failed opening output file %s\n", $output_file));
    }
    register_shutdown_function(function () use ($output_file) {
        unlink($output_file);
    });

    $descriptorspec = array(
        0 => STDIN,
        1 => $output_file_fd,
        2 => $output_file_fd,
    );
    $handle = proc_open($cmd, $descriptorspec, $pipes, $doc_root, null, array("suppress_errors" => true));
    if ($handle === false) {
        php_cli_server_startup_failure(
            'proc_open() failed to create the server process',
            $cmd,
            $doc_root,
            $output_file,
        );
    }

    // First, wait for the dev server to declare itself ready.
    $bound = null;
    for ($i = 0; $i < 60; $i++) {
        usleep(50000); // 50ms per try
        $status = proc_get_status($handle);
        if (empty($status['running'])) {
            php_cli_server_startup_failure(
                'Server process exited before announcing that it was ready',
                $cmd,
                $doc_root,
                $output_file,
                $handle,
                $status,
            );
        }

        $output = file_get_contents($output_file);
        if (preg_match('@PHP \S* Development Server \(https?://(.*?:\d+)\) started@', $output, $matches)) {
            $bound = $matches[1];
            break;
        }
    }
    if ($bound === null) {
        php_cli_server_startup_failure(
            'Server did not output its startup message within 3 seconds',
            $cmd,
            $doc_root,
            $output_file,
            $handle,
        );
    }

    // Now wait for a connection to succeed.
    // note: even when server prints 'Listening on localhost:8964...Press Ctrl-C to quit.'
    //       it might not be listening yet...need to wait until fsockopen() call returns
    $error = 'Unable to connect to server within 3 seconds';
    for ($i=0; $i < 60; $i++) {
        usleep(50000); // 50ms per try
        $status = proc_get_status($handle);
        $fp = @fsockopen("tcp://$bound");
        // Failure, the server is no longer running
        if (!($status && $status['running'])) {
            $error = 'Server process stopped before accepting a connection';
            break;
        }
        // Success, Connected to servers
        if ($fp) {
            $error = '';
            break;
        }
    }

    if ($error) {
        php_cli_server_startup_failure(
            $error,
            $cmd,
            $doc_root,
            $output_file,
            $handle,
            $status,
        );
    }

    register_shutdown_function(
        function($handle) use($router, $doc_root, $output_file) {
            proc_terminate($handle);
            $status = proc_get_status($handle);
            if ($status['exitcode'] !== -1 && $status['exitcode'] !== 0
                    && !($status['exitcode'] === 255 && PHP_OS_FAMILY == 'Windows')) {
                printf("Server exited with non-zero status: %d\n", $status['exitcode']);
                printf("Server output:\n%s\n", file_get_contents($output_file));
            }
            @unlink(__DIR__ . "/{$router}");
            remove_directory($doc_root);
        },
        $handle
    );

    // Define the same "constants" we previously did.
    $port = (int) substr($bound, strrpos($bound, ':') + 1);
    define("PHP_CLI_SERVER_HOSTNAME", "localhost");
    define("PHP_CLI_SERVER_PORT", $port);
    define("PHP_CLI_SERVER_ADDRESS", PHP_CLI_SERVER_HOSTNAME.":".PHP_CLI_SERVER_PORT);

    return new CliServerInfo($doc_root, $handle);
}

function php_cli_server_connect() {
    $timeout = 1.0;
    $fp = fsockopen(PHP_CLI_SERVER_HOSTNAME, PHP_CLI_SERVER_PORT, $errno, $errstr, $timeout);
    if (!$fp) {
        die("connect failed");
    }
    return $fp;
}

function remove_directory($dir) {
    if (is_dir($dir) === false) {
        return;
    }
    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($files as $fileinfo) {
        $todo = ($fileinfo->isDir() ? 'rmdir' : 'unlink');
        $todo($fileinfo->getRealPath());
    }
    @rmdir($dir);
}

?>
