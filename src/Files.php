<?php

namespace Gaambo\DeployerUtils;

use function Deployer\download;
use function Deployer\get;
use function Deployer\run;
use function Deployer\runLocally;
use function Deployer\upload;

/**
 * @phpstan-import-type RsyncOptions from Rsync
 */
class Files
{
    public static function resolvePath(string $path, string $basePath): string
    {
        if (str_starts_with($path, '/') || str_starts_with($path, '~')) {
            return $path;
        }

        return rtrim($basePath, '/') . '/' . $path;
    }

    /** @param RsyncOptions $rsyncOptions */
    public static function pushFiles(string $localPath, string $remotePath, array $rsyncOptions = []): void
    {
        $localPath = self::resolvePath($localPath, Localhost::getConfig('current_path'));
        $remotePath = self::resolvePath($remotePath, get('release_or_current_path'));

        run("mkdir -p $remotePath");
        upload($localPath . '/', $remotePath . '/', ['options' => $rsyncOptions]);
    }

    /** @param RsyncOptions $rsyncOptions */
    public static function pullFiles(string $remotePath, string $localPath, array $rsyncOptions = []): void
    {
        $localPath = self::resolvePath($localPath, Localhost::getConfig('current_path'));
        $remotePath = self::resolvePath($remotePath, get('release_or_current_path'));

        runLocally("mkdir -p $localPath");
        download($remotePath . '/', $localPath . '/', ['options' => $rsyncOptions]);
    }

    /** @param RsyncOptions $rsyncOptions */
    public static function pushFile(string $localPath, string $remotePath, array $rsyncOptions = []): void
    {
        $localPath = self::resolvePath($localPath, Localhost::getConfig('current_path'));
        $remotePath = self::resolvePath($remotePath, get('release_or_current_path'));

        run('mkdir -p ' . dirname($remotePath));
        upload($localPath, $remotePath, ['options' => $rsyncOptions]);
    }

    /** @param RsyncOptions $rsyncOptions */
    public static function pullFile(string $remotePath, string $localPath, array $rsyncOptions = []): void
    {
        $localPath = self::resolvePath($localPath, Localhost::getConfig('current_path'));
        $remotePath = self::resolvePath($remotePath, get('release_or_current_path'));

        runLocally('mkdir -p ' . dirname($localPath));
        download($remotePath, $localPath, ['options' => $rsyncOptions]);
    }

    public static function zipFiles(string $dir, string $backupDir, string $filename): string
    {
        $backupFilename = $filename . '_' . date('Y-m-d_H-i-s') . '.zip';
        $backupPath = "$backupDir/$backupFilename";
        run("mkdir -p $backupDir");

        if (str_ends_with($dir, '/')) {
            run("cd $dir && zip -r $backupFilename . {{zip_options}} && mv $backupFilename $backupPath");
        } else {
            $parentDir = dirname($dir);
            $dir = basename($dir);
            run("cd $parentDir && zip -r $backupFilename $dir {{zip_options}} && mv $backupFilename $backupPath");
        }

        return $backupPath;
    }
}
