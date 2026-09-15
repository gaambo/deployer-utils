<?php

namespace Gaambo\DeployerUtils\Tests\Integration\Tasks;

class FilesTasksRegistrationTest extends TaskRegistrationTestCase
{
    protected static function loadTasks(): void
    {
        require __DIR__ . '/../../../tasks/files.php';
    }

    public function testOnlySharedBackupTasksAreRegistered(): void
    {
        $this->assertTaskExists('files:backup:remote');
        $this->assertTaskExists('files:backup:local');
        $this->assertFalse($this->deployer->tasks->has('files:push'));
        $this->assertFalse($this->deployer->tasks->has('files:pull'));
    }
}
