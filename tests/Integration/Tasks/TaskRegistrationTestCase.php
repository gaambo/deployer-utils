<?php

namespace Gaambo\DeployerUtils\Tests\Integration\Tasks;

use Deployer\Task\GroupTask;
use Gaambo\DeployerUtils\Tests\Integration\IntegrationTestCase;

abstract class TaskRegistrationTestCase extends IntegrationTestCase
{
    abstract protected static function loadTasks(): void;

    protected function setUp(): void
    {
        parent::setUp();
        static::loadTasks();
    }

    protected function assertTaskExists(string $taskName): void
    {
        $this->assertTrue(
            $this->deployer->tasks->has($taskName),
            "Task '$taskName' should be registered"
        );
    }

    /** @param string[] $expectedDependencies */
    protected function assertTaskDependencies(string $taskName, array $expectedDependencies): void
    {
        $task = $this->deployer->tasks->get($taskName);
        $this->assertInstanceOf(GroupTask::class, $task);
        $this->assertEquals($expectedDependencies, $task->getGroup());
    }
}
