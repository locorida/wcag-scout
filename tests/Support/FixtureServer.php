<?php

declare(strict_types=1);

namespace WcagScout\Tests\Support;

use RuntimeException;
use Symfony\Component\Process\Process;

/**
 * Serves tests/fixtures/site with PHP's built-in web server on a free local port.
 */
final class FixtureServer
{
    private Process $process;

    public readonly string $baseUrl;

    public readonly string $sinkLog;

    public function __construct()
    {
        $fixtures = \dirname(__DIR__) . '/fixtures';
        $port = self::freePort();
        $this->baseUrl = 'http://127.0.0.1:' . $port;
        $this->sinkLog = sys_get_temp_dir() . '/wcag-scout-sink-' . $port . '.log';
        @unlink($this->sinkLog);

        $this->process = new Process(
            [PHP_BINARY, '-S', '127.0.0.1:' . $port, '-t', $fixtures . '/site', $fixtures . '/router.php'],
            env: ['WCAG_SCOUT_SINK_LOG' => $this->sinkLog],
        );
        $this->process->start();

        $deadline = microtime(true) + 5;
        while (@fsockopen('127.0.0.1', $port) === false) {
            if (microtime(true) > $deadline || !$this->process->isRunning()) {
                throw new RuntimeException('Fixture server did not start: ' . $this->process->getErrorOutput());
            }
            usleep(50_000);
        }
    }

    public function url(string $path): string
    {
        return $this->baseUrl . $path;
    }

    /** @return list<string> HTTP methods that reached post-sink.php */
    public function sinkRequests(): array
    {
        $log = is_file($this->sinkLog) ? file($this->sinkLog, FILE_IGNORE_NEW_LINES) : [];

        return $log === false ? [] : $log;
    }

    /** @return list<string> request lines of the server log, e.g. "GET /index.html" */
    public function requestLog(): array
    {
        preg_match_all('/\[\d{3}\]: ([A-Z]+ \S+)/', $this->process->getErrorOutput(), $m);

        return $m[1];
    }

    public function stop(): void
    {
        $this->process->stop(1);
        @unlink($this->sinkLog);
    }

    private static function freePort(): int
    {
        $socket = stream_socket_server('tcp://127.0.0.1:0');
        if ($socket === false) {
            throw new RuntimeException('No free port.');
        }
        $name = (string) stream_socket_get_name($socket, false);
        fclose($socket);

        return (int) substr($name, (int) strrpos($name, ':') + 1);
    }
}
