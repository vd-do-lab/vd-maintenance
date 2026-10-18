<?php
declare(strict_types=1);

namespace Vd\VdMaintenance\Service;

use RuntimeException;
use Symfony\Component\Process\Process;

/**
 * Packt/entpackt ein Verzeichnis als .tar.gz - gemeinsame Basis für
 * maintenance:import-folder-export/-import und maintenance:fileadmin-
 * export/-import (siehe docs/09-backup-restore/README.md).
 */
final class DirectoryArchiver
{
    /**
     * @throws RuntimeException
     */
    public function archive(string $sourceDir, string $outputDir, string $archiveNamePrefix): string
    {
        if (!is_dir($sourceDir)) {
            throw new RuntimeException("Verzeichnis nicht gefunden: {$sourceDir}");
        }

        if (!is_dir($outputDir) && !mkdir($outputDir, 0775, true) && !is_dir($outputDir)) {
            throw new RuntimeException("Zielverzeichnis konnte nicht erstellt werden: {$outputDir}");
        }

        $targetFile = sprintf(
            '%s/%s-%s.tar.gz',
            rtrim($outputDir, '/'),
            $archiveNamePrefix,
            date('Y-m-d_His')
        );

        $process = Process::fromShellCommandline(
            sprintf(
                'tar czf %s -C %s %s',
                escapeshellarg($targetFile),
                escapeshellarg(dirname($sourceDir)),
                escapeshellarg(basename($sourceDir))
            )
        );
        $process->setTimeout(3600);
        $process->run();

        if (!$process->isSuccessful()) {
            throw new RuntimeException('Archivieren fehlgeschlagen: ' . $process->getErrorOutput());
        }

        return $targetFile;
    }

    /**
     * Überschreibt nur gleichnamige Dateien aus dem Archiv, löscht vorab
     * nichts - bereits vorhandene, nicht im Archiv enthaltene Dateien
     * bleiben erhalten (bewusst konservativ für nicht reproduzierbare Daten).
     *
     * @throws RuntimeException
     */
    public function restore(string $archiveFile, string $targetDir): void
    {
        if (!is_dir($targetDir) && !mkdir($targetDir, 0775, true) && !is_dir($targetDir)) {
            throw new RuntimeException("Zielverzeichnis konnte nicht erstellt werden: {$targetDir}");
        }

        // --no-same-owner: tar versucht als root sonst, den ursprünglichen
        // Datei-Besitzer (uid/gid zum Archivierungszeitpunkt) wiederherzustellen -
        // schlägt auf macOS-Docker-Bind-Mounts und ggf. auf Shared-Hosting fehl
        // ("Cannot change ownership ... Permission denied"), bricht den Restore
        // ab und lässt bereits entpackte Dateien in einem inkonsistenten
        // Zwischenzustand zurück. Ownership ist für dieses Projekt ohnehin
        // irrelevant (gleiches Verhalten wie deploy.ymls --no-perms --no-times
        // beim rsync, siehe docs/07-deployment/README.md).
        $process = Process::fromShellCommandline(
            sprintf(
                'tar xzf %s -C %s --strip-components=1 --no-same-owner',
                escapeshellarg($archiveFile),
                escapeshellarg($targetDir)
            )
        );
        $process->setTimeout(3600);
        $process->run();

        if (!$process->isSuccessful()) {
            throw new RuntimeException('Entpacken fehlgeschlagen: ' . $process->getErrorOutput());
        }
    }
}
