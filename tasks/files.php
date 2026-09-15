<?php

namespace Gaambo\DeployerUtils\Tasks;

use Gaambo\DeployerUtils\Files;
use Gaambo\DeployerUtils\Localhost;

use function Deployer\download;
use function Deployer\task;

task('files:backup:remote', function () {
    $backupFile = Files::zipFiles(
        '{{release_or_current_path}}/',
        '{{backup_path}}',
        'backup_files'
    );
    $localBackupPath = Localhost::getConfig('backup_path');
    download($backupFile, "$localBackupPath/");
})->desc('Backup remote files and download locally');

task('files:backup:local', function () {
    $localPath = Localhost::getConfig('current_path');
    $localBackupPath = Localhost::getConfig('backup_path');
    Files::zipFiles(
        "$localPath/",
        $localBackupPath,
        'backup_files'
    );
})->once()->desc('Backup local files');
