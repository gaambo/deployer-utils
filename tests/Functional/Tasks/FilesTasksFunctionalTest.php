<?php

namespace Gaambo\DeployerUtils\Tests\Functional\Tasks;

use Gaambo\DeployerUtils\Tests\Functional\FunctionalTestCase;

class FilesTasksFunctionalTest extends FunctionalTestCase
{
    public function testListShowsSharedBackupTasks(): void
    {
        $this->assertSame(0, $this->dep('list', null));

        $output = $this->tester->getDisplay();
        $this->assertStringContainsString('files:backup:remote', $output);
        $this->assertStringContainsString('files:backup:local', $output);
        $this->assertStringNotContainsString('files:push', $output);
    }

    public function testRemoteBackupCreatesAndDownloadsArchive(): void
    {
        file_put_contents($this->remoteCurrentDir . '/app.txt', 'remote');

        $this->assertSame(0, $this->dep('files:backup:remote'));

        $remoteArchives = glob($this->remoteDir . '/backups/backup_files_*.zip');
        $localArchives = glob($this->localDir . '/backups/backup_files_*.zip');
        $this->assertCount(1, $remoteArchives);
        $this->assertCount(1, $localArchives);
        $this->assertSame(basename($remoteArchives[0]), basename($localArchives[0]));
    }

    public function testLocalBackupCreatesArchiveFromLocalCurrentPath(): void
    {
        file_put_contents($this->localCurrentDir . '/app.txt', 'local');

        $this->assertSame(0, $this->dep('files:backup:local'));

        $this->assertCount(1, glob($this->localDir . '/backups/backup_files_*.zip'));
    }
}
