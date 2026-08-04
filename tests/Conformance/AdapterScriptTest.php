<?php

namespace Yarunoka\Tests\Conformance;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Verifies the adapter entry script the runner starts: one request read
 * from stdin, one JSON answer on stdout, exit status 0 — including for
 * an invalid answer, which the protocol makes a normal answer. Breakage
 * (an emit request) exits non-zero with the reason on stderr.
 */
class AdapterScriptTest extends TestCase
{
    #[Test]
    public function answers_a_request_on_stdout_and_exits_zero(): void
    {
        $run = $this->run_adapter([
            'action' => 'eval',
            'document' => $this->document(),
            'query' => ['type' => 'point', 'at' => '2026-07-25T10:00:00+09:00'],
        ]);

        $this->assertSame(0, $run['status'], $run['stderr']);
        $this->assertSame(['result' => true], json_decode($run['stdout'], associative: true));
    }

    #[Test]
    public function answers_invalid_as_a_normal_answer_with_exit_status_zero(): void
    {
        $document = $this->document();
        $document['version'] = '9.9';

        $run = $this->run_adapter([
            'action' => 'eval',
            'document' => $document,
            'query' => ['type' => 'point', 'at' => '2026-07-25T10:00:00+09:00'],
        ]);

        $this->assertSame(0, $run['status'], $run['stderr']);
        $this->assertSame(['invalid' => true], json_decode($run['stdout'], associative: true));
    }

    #[Test]
    public function fails_an_emit_request_with_the_reason_on_stderr(): void
    {
        $run = $this->run_adapter([
            'action' => 'emit',
            'document' => $this->document(),
        ]);

        $this->assertSame(1, $run['status']);
        $this->assertSame('', $run['stdout']);
        $this->assertStringContainsString('emit is not supported', $run['stderr']);
    }

    #[Test]
    public function fails_a_request_that_is_not_a_json_object(): void
    {
        $run = $this->run_adapter_raw('not json');

        $this->assertSame(1, $run['status']);
        $this->assertSame('', $run['stdout']);
        $this->assertNotSame('', $run['stderr']);
    }

    /**
     * @param  array<string, mixed>  $request
     * @return array{status: int, stdout: string, stderr: string}
     */
    private function run_adapter(array $request): array
    {
        return $this->run_adapter_raw(json_encode($request, JSON_THROW_ON_ERROR));
    }

    /**
     * @return array{status: int, stdout: string, stderr: string}
     */
    private function run_adapter_raw(string $input): array
    {
        $process = proc_open(
            [PHP_BINARY, __DIR__ . '/bin/adapter.php'],
            [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
        );

        $this->assertIsResource($process);

        fwrite($pipes[0], $input);
        fclose($pipes[0]);

        $stdout = (string) stream_get_contents($pipes[1]);
        $stderr = (string) stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);

        return ['status' => proc_close($process), 'stdout' => $stdout, 'stderr' => $stderr];
    }

    /**
     * @return array<string, mixed>
     */
    private function document(): array
    {
        return [
            'version' => '1.0',
            'timezone' => 'Asia/Tokyo',
            'schedules' => [['days' => [25], 'times' => ['10:00']]],
        ];
    }
}
