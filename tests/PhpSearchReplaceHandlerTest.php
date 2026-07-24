<?php

// phpcs:ignoreFile

declare(strict_types=1);

namespace WP_CLI\Search_Replace_File\Tests;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use WP_CLI\Search_Replace_File\PhpSearchReplaceHandler;

class PhpSearchReplaceHandlerTest extends TestCase
{
    /**
     * @var PhpSearchReplaceHandler
     */
    private $handler;

    protected function setUp(): void
    {
        parent::setUp();
        $this->handler = new PhpSearchReplaceHandler();
    }

	public function testProcessLineMatchesGoImplementation(): void {
		foreach ( $this->provideSerializedFixtures() as $label => $fixture ) {
			list( $inputFixture, $expectedFixture, $replacements ) = $fixture;

			$input = file_get_contents( $inputFixture );
			$expected = file_get_contents( $expectedFixture );

			self::assertNotFalse( $input, $label . ': input fixture could not be read.' );
			self::assertNotFalse( $expected, $label . ': expected fixture could not be read.' );

			self::assertSame( $expected, $this->handler->process_line( $input, $replacements ), $label );
		}
	}

    /**
     * @return array<string, array{string,string,array<int, array{from:string,to:string}>}>
     */
    public static function provideSerializedFixtures(): array
    {
        $base = __DIR__ . '/Fixtures/serialized';
        $doubleEncodedFrom = <<<'TXT'
        http:\\/\\/example\\.com
        TXT;
        $doubleEncodedTo = <<<'TXT'
        http:\\/\\/example2\\.com
        TXT;
        $doubleEncodedSerializedFrom = <<<'TXT'
        \\s=\\shttp_get(\'http:\\/\\/example\\.com
        TXT;
        $doubleEncodedSerializedTo = <<<'TXT'
        \\s=\\shttp_get(\'http:\\/\\/example2\\.com
        TXT;
        $heavyEscapingFrom = <<<'TXT'
        \\c\\d\\e
        TXT;
        $heavyEscapingTo = <<<'TXT'
        \\x
        TXT;

        return [
            'http to https' => [
                $base . '/http-to-https.input.sql',
                $base . '/http-to-https.expected.sql',
                [['from' => 'http://automattic.com', 'to' => 'https://automattic.com']],
            ],
            'multiple occurrences on line' => [
                $base . '/multiple-occurrences.input.sql',
                $base . '/multiple-occurrences.expected.sql',
                [['from' => 'http://automattic.com', 'to' => 'https://automattic.com']],
            ],
            'emoji from' => [
                $base . '/emoji-from.input.sql',
                $base . '/emoji-from.expected.sql',
                [['from' => 'http://🖖.com', 'to' => 'https://spock.com']],
            ],
            'emoji to' => [
                $base . '/emoji-to.input.sql',
                $base . '/emoji-to.expected.sql',
                [['from' => 'https://spock.com', 'to' => 'http://🖖.com']],
            ],
            'null characters' => [
                $base . '/null-bytes.input.sql',
                $base . '/null-bytes.expected.sql',
                [['from' => 'EnvironmentObject', 'to' => 'Yeehaw']],
            ],
            'different lengths' => [
                $base . '/different-lengths.input.sql',
                $base . '/different-lengths.expected.sql',
                [['from' => 'hello', 'to' => 'goodbye']],
            ],
            'longer replacements' => [
                $base . '/long-different-lengths.input.sql',
                $base . '/long-different-lengths.expected.sql',
                [['from' => 'bbbbbbbbbb', 'to' => 'ccccccccccccccc']],
            ],
            'serialized css' => [
                $base . '/serialized-css.input.sql',
                $base . '/serialized-css.expected.sql',
                [['from' => 'https://uss-enterprise.com', 'to' => 'https://ncc-1701-d.space']],
            ],
            'double encoded string' => [
                $base . '/double-encoded.input.sql',
                $base . '/double-encoded.expected.sql',
                [['from' => $doubleEncodedFrom, 'to' => $doubleEncodedTo]],
            ],
            'non serialized section with serialized data' => [
                $base . '/non-serialized-mixed.input.sql',
                $base . '/non-serialized-mixed.expected.sql',
                [['from' => 'example', 'to' => 'example2'], ['from' => $doubleEncodedFrom, 'to' => $doubleEncodedTo]],
            ],
            'heavy escaping' => [
                $base . '/heavy-escaping.input.sql',
                $base . '/heavy-escaping.expected.sql',
                [['from' => $heavyEscapingFrom, 'to' => $heavyEscapingTo]],
            ],
            'escaped delimiters' => [
                $base . '/escaped-delimiters.input.sql',
                $base . '/escaped-delimiters.expected.sql',
                [['from' => 'hello', 'to' => 'helloworld']],
            ],
            'mydumper delimiters' => [
                $base . '/mydumper-delimiters.input.sql',
                $base . '/mydumper-delimiters.expected.sql',
                [['from' => 'hello', 'to' => 'helloworld']],
            ],
            'overlapping replacements without serialization' => [
                $base . '/overlapping-non-serialized.input.sql',
                $base . '/overlapping-non-serialized.expected.sql',
                [['from' => 'http:', 'to' => 'https:'], ['from' => '//automattic.com', 'to' => '//automattic.org']],
            ],
        ];
    }

    public function testProcessLineWithEmptyReplacementsReturnsOriginal(): void
    {
        $line = 'plain text line';
        self::assertSame($line, $this->handler->process_line($line, []));
    }

    public function testProcessLineRejectsInvalidReplacements(): void
    {
        $this->expectException(RuntimeException::class);
        $this->handler->process_line('anything', [['from' => 'only-from']]);
    }

    public function testProcessLineRejectsMalformedSerializedData(): void
    {
        $input = file_get_contents(__DIR__ . '/Fixtures/serialized/skip-updated.input.sql');
        self::assertNotFalse($input);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('faulty serialized data');

        $this->handler->process_line(
            $input,
            [['from' => 'http://automattic.com', 'to' => 'https://automattic.com']]
        );
    }

    public function testProcessLinePreservesEscapedSequenceAtEndOfSerializedValue(): void
    {
        $line = 's:2:\"a\\n\";';

        self::assertSame($line, $this->handler->process_line($line, [['from' => 'missing', 'to' => 'replacement']]));
    }

    public function testReplaceInFileProcessesEntireContents(): void
    {
        $input = tempnam(sys_get_temp_dir(), 'sql-src-');
        $output = tempnam(sys_get_temp_dir(), 'sql-out-');

        self::assertNotFalse($input);
        self::assertNotFalse($output);

        $fixture = <<<'SQL'
        s:21:\"http://automattic.com\";
        http://example.com

        SQL;

        file_put_contents($input, $fixture);

        $this->handler->replace_in_file($input, $output, [
            ['from' => 'http://automattic.com', 'to' => 'https://automattic.com'],
            ['from' => 'http://example.com', 'to' => 'https://example.com'],
        ]);

        $result = file_get_contents($output);

        $expected = <<<'SQL'
        s:22:\"https://automattic.com\";
        https://example.com

        SQL;

        self::assertSame($expected, $result);

        @unlink($input);
        @unlink($output);
    }

    public function testReplaceInFileThrowsWhenWritingFails(): void
    {
        if (!file_exists('/dev/full')) {
            self::markTestSkipped('/dev/full is required to simulate a write failure.');
        }

        $input = tempnam(sys_get_temp_dir(), 'sql-src-');
        self::assertNotFalse($input);

        file_put_contents($input, "old text\n");

        try {
            $this->expectException(RuntimeException::class);
            $this->expectExceptionMessage('Unable to replace "/dev/full".');

            $this->handler->replace_in_file(
                $input,
                '/dev/full',
                [['from' => 'old', 'to' => 'new']]
            );
        } finally {
            @unlink($input);
        }
    }

}
