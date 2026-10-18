<?php

namespace Tests\Unit;

use App\Support\LogReader;
use PHPUnit\Framework\TestCase;

class LogReaderTest extends TestCase
{
    private string $path;

    protected function setUp(): void
    {
        parent::setUp();

        $this->path = tempnam(sys_get_temp_dir(), 'log').'.log';
        file_put_contents($this->path, implode("\n", [
            '[2026-10-18 10:00:00] local.INFO: First message',
            '[2026-10-18 10:01:00] local.ERROR: Something broke {"exception":"x"}',
            '[stacktrace]',
            '#0 /app/Foo.php(12): bar()',
            '#1 {main}',
            '"} ',
            '[2026-10-18 10:02:00] production.WARNING: Slow racing source',
            '',
        ]));
    }

    protected function tearDown(): void
    {
        @unlink($this->path);

        parent::tearDown();
    }

    public function test_it_reads_entries_newest_first_with_their_details(): void
    {
        $entries = LogReader::tail($this->path, 10)['entries'];

        $this->assertSame(['warning', 'error', 'info'], array_column($entries, 'level'));
        $this->assertSame('production', $entries[0]['env']);
        $this->assertSame('Something broke {"exception":"x"}', $entries[1]['message']);
        $this->assertStringContainsString('#0 /app/Foo.php(12): bar()', $entries[1]['details']);
        $this->assertSame('', $entries[2]['details']);
    }

    public function test_it_limits_and_filters(): void
    {
        $this->assertCount(1, LogReader::tail($this->path, 1)['entries']);
        $this->assertSame(['error'], array_column(LogReader::tail($this->path, 10, 'error')['entries'], 'level'));
        $this->assertSame(['error'], array_column(LogReader::tail($this->path, 10, null, 'FOO.PHP')['entries'], 'level'));
        $this->assertSame([], LogReader::tail($this->path, 10, 'debug')['entries']);
    }

    public function test_entries_split_across_chunks_are_read_whole(): void
    {
        $lines = [];
        for ($i = 0; $i < 5000; $i++) {
            $lines[] = "[2026-10-18 10:00:00] local.INFO: Entry {$i} ".str_repeat('x', 100);
        }
        file_put_contents($this->path, implode("\n", $lines)."\n");

        $entries = LogReader::tail($this->path, 5000)['entries'];

        $this->assertCount(5000, $entries);
        $this->assertStringStartsWith('Entry 4999 ', $entries[0]['message']);
        $this->assertStringStartsWith('Entry 0 ', $entries[4999]['message']);
    }

    public function test_a_missing_file_gives_no_entries(): void
    {
        $this->assertSame([], LogReader::tail($this->path.'.missing')['entries']);
    }
}
