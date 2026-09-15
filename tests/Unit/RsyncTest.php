<?php

namespace Gaambo\DeployerUtils\Tests\Unit;

use Gaambo\DeployerUtils\Rsync;

class RsyncTest extends UnitTestCase
{
    private string $fixturesDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->fixturesDir = __FIXTURES__ . '/rsync';
    }

    public function testBuildExcludesWithExistingFile(): void
    {
        $file = $this->fixturesDir . '/exclude.txt';
        $result = Rsync::buildExcludes(['*.log', '*.tmp'], $file);

        $this->assertSame(['--exclude=*.log', '--exclude=*.tmp', '--exclude-from=' . $file], $result);
    }

    public function testBuildExcludesIgnoresMissingFile(): void
    {
        $result = Rsync::buildExcludes(['*.log'], '/missing/exclude.txt');

        $this->assertSame(['--exclude=*.log'], $result);
    }

    public function testBuildIncludesWithExistingFile(): void
    {
        $file = $this->fixturesDir . '/include.txt';
        $result = Rsync::buildIncludes(['*.php', '*.js'], $file);

        $this->assertSame(['--include=*.php', '--include=*.js', '--include-from=' . $file], $result);
    }

    public function testBuildIncludesIgnoresMissingFile(): void
    {
        $result = Rsync::buildIncludes(['*.php'], '/missing/include.txt');

        $this->assertSame(['--include=*.php'], $result);
    }

    public function testBuildFilterWithFiles(): void
    {
        $file = $this->fixturesDir . '/filter.txt';
        $result = Rsync::buildFilter(['+ /public/'], $file, '.deployfilter');

        $this->assertSame([
            '--filter=+ /public/',
            '--filter=merge ' . $file,
            '--filter=dir-merge .deployfilter',
        ], $result);
    }

    public function testBuildFilterIgnoresMissingFile(): void
    {
        $result = Rsync::buildFilter(['- /cache/'], '/missing/filter.txt', '.deployfilter');

        $this->assertSame(['--filter=- /cache/', '--filter=dir-merge .deployfilter'], $result);
    }

    public function testEmptyListsProduceNoOptions(): void
    {
        $this->assertSame([], Rsync::buildOptions([]));
        $this->assertSame([], Rsync::buildIncludes([]));
        $this->assertSame([], Rsync::buildExcludes([]));
        $this->assertSame([], Rsync::buildFilter([]));
    }
}
