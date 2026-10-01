<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Startet pro Testklasse einen lokalen PHP-Server (wie dev/router.php)
 * mit eigener Test-DB und schickt echte HTTP-Requests.
 */
abstract class HttpTestCase extends TestCase
{
    /** @var resource|null */
    private static $server = null;
    private static string $serverDir = '';
    protected static string $baseUrl = '';

    public static function setUpBeforeClass(): void
    {
        self::$serverDir = getenv('CONF_FORM_TEST_DIR') . '/http-' . bin2hex(random_bytes(4));
        mkdir(self::$serverDir, 0700);

        $port = self::freePort();
        self::$baseUrl = "http://127.0.0.1:$port";

        $env = getenv();
        $env['CONF_FORM_CONFIG'] = ROOT_DIR . '/tests/config.test.php';
        $env['CONF_FORM_TEST_DIR'] = self::$serverDir;

        $log = self::$serverDir . '/server.log';
        self::$server = proc_open(
            [PHP_BINARY, '-S', "127.0.0.1:$port", '-t', ROOT_DIR . '/web', ROOT_DIR . '/dev/router.php'],
            [0 => ['file', '/dev/null', 'r'], 1 => ['file', $log, 'a'], 2 => ['file', $log, 'a']],
            $pipes,
            ROOT_DIR,
            $env
        );

        $deadline = microtime(true) + 5;
        while (!@fsockopen('127.0.0.1', $port)) {
            if (microtime(true) > $deadline) {
                self::fail('PHP-Server startet nicht: ' . file_get_contents($log));
            }
            usleep(50_000);
        }
    }

    public static function tearDownAfterClass(): void
    {
        if (self::$server !== null) {
            proc_terminate(self::$server);
            proc_close(self::$server);
            self::$server = null;
        }
        remove_dir(self::$serverDir);
    }

    /**
     * @return array{status: int, headers: array<string, string>, body: string}
     */
    protected function get(string $path): array
    {
        return $this->request('GET', $path);
    }

    /**
     * @param array<string, string> $data
     * @return array{status: int, headers: array<string, string>, body: string}
     */
    protected function post(string $path, array $data): array
    {
        return $this->request('POST', $path, http_build_query($data));
    }

    /**
     * @return array{status: int, headers: array<string, string>, body: string}
     */
    private function request(string $method, string $path, string $body = ''): array
    {
        $context = stream_context_create(['http' => [
            'method' => $method,
            'header' => $method === 'POST' ? "Content-Type: application/x-www-form-urlencoded\r\n" : '',
            'content' => $body,
            'ignore_errors' => true,
            'follow_location' => 0,
            'timeout' => 10,
        ]]);
        $responseBody = file_get_contents(self::$baseUrl . $path, false, $context);
        // $http_response_header wird von file_get_contents() lokal gesetzt
        $rawHeaders = $http_response_header ?? [];

        preg_match('#^HTTP/\S+ (\d{3})#', $rawHeaders[0] ?? '', $m);
        $headers = [];
        foreach (array_slice($rawHeaders, 1) as $line) {
            [$name, $value] = array_pad(explode(':', $line, 2), 2, '');
            $headers[strtolower(trim($name))] = trim($value);
        }

        return ['status' => (int) ($m[1] ?? 0), 'headers' => $headers, 'body' => (string) $responseBody];
    }

    private static function freePort(): int
    {
        $socket = stream_socket_server('tcp://127.0.0.1:0');
        $name = stream_socket_get_name($socket, false);
        fclose($socket);
        return (int) substr($name, strrpos($name, ':') + 1);
    }
}
