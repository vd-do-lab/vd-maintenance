<?php
declare(strict_types=1);

namespace Vd\VdMaintenance\Service;

use TYPO3\CMS\Core\Core\Environment;

/**
 * Zentrale Formel für das Geschwisterverzeichnis "backup/" neben der
 * TYPO3-Installation (siehe docs/09-backup-restore/README.md) - von allen
 * Export-/Import-Commands dieser Extension genutzt, damit Default-Pfad und
 * Datei-Auflösung an genau einer Stelle gepflegt werden.
 */
final class BackupPaths
{
    public function defaultDirectory(): string
    {
        return dirname(Environment::getProjectPath()) . '/backup';
    }

    /**
     * Löst einen reinen Dateinamen ohne Pfad (z. B. per Copy-Paste aus einer
     * Export-Ausgabe) zusätzlich gegen das Standard-Backup-Verzeichnis auf,
     * falls der gegebene Pfad nicht direkt existiert.
     */
    public function resolveExistingFile(string $file): ?string
    {
        if (is_file($file)) {
            return $file;
        }

        if (dirname($file) === '.') {
            $fallback = $this->defaultDirectory() . '/' . basename($file);
            if (is_file($fallback)) {
                return $fallback;
            }
        }

        return null;
    }
}
