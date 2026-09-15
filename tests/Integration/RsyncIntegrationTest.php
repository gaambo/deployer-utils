<?php

namespace Gaambo\DeployerUtils\Tests\Integration;

use Gaambo\DeployerUtils\Rsync;

class RsyncIntegrationTest extends IntegrationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->host->set('rsync', Rsync::DEFAULT_CONFIG);
    }

    public function testBuildOptionsPrefixesValues(): void
    {
        $this->assertSame(
            ['--delete-after', '--recursive', '--verbose=true'],
            Rsync::buildOptions(['delete-after', 'recursive', 'verbose=true'])
        );
    }

    public function testBuildOptionsArrayUsesConfiguredDefaults(): void
    {
        $result = Rsync::buildOptionsArray();

        $this->assertContains('--delete-after', $result);
        $this->assertContains('--exclude=.git', $result);
        $this->assertContains('--exclude=deploy.php', $result);
    }

    public function testBuildOptionsArrayUsesHardcodedDefaultsForInvalidConfig(): void
    {
        foreach ([null, 'invalid'] as $config) {
            $this->host->set('rsync', $config);
            $this->assertSame(
                ['--delete-after', '--exclude=.git', '--exclude=deploy.php'],
                Rsync::buildOptionsArray()
            );
        }
    }

    public function testBuildOptionsArrayCombinesCustomConfiguration(): void
    {
        $fixtures = __FIXTURES__ . '/rsync';
        $result = Rsync::buildOptionsArray([
            'exclude' => ['*.log'],
            'exclude-file' => "$fixtures/exclude.txt",
            'include' => ['*.php'],
            'include-file' => "$fixtures/include.txt",
            'filter' => ['+ /public/'],
            'filter-file' => "$fixtures/filter.txt",
            'filter-perdir' => '.deployfilter',
            'options' => ['recursive'],
        ]);

        $this->assertSame([
            '--recursive',
            '--include=*.php',
            "--include-from=$fixtures/include.txt",
            '--exclude=*.log',
            "--exclude-from=$fixtures/exclude.txt",
            '--filter=+ /public/',
            "--filter=merge $fixtures/filter.txt",
            '--filter=dir-merge .deployfilter',
        ], $result);
    }

    public function testCustomEmptyListsReplaceDefaults(): void
    {
        $this->assertSame([], Rsync::buildOptionsArray([
            'exclude' => [],
            'include' => [],
            'filter' => [],
            'options' => [],
        ]));
    }

    public function testPartialConfigUsesOtherConfiguredDefaults(): void
    {
        $result = Rsync::buildOptionsArray(['exclude' => ['custom.txt']]);

        $this->assertContains('--exclude=custom.txt', $result);
        $this->assertContains('--delete-after', $result);
        $this->assertNotContains('--exclude=.git', $result);
    }

    public function testInvalidValuesAreFilteredWithoutChangingStringValues(): void
    {
        $result = Rsync::buildOptionsArray([
            'exclude' => ['path with spaces', null, 2, ''],
            'include' => ['~/home', false],
            'filter' => ['+ ../relative', true],
            'options' => ['recursive', null],
            'exclude-file' => ['not-a-string'],
        ]);

        $this->assertSame([
            '--recursive',
            '--include=~/home',
            '--exclude=path with spaces',
            '--filter=+ ../relative',
        ], $result);
    }
}
