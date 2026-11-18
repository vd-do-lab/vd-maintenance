<?php
declare(strict_types=1);

namespace Vd\VdMaintenance\Command;

use Symfony\Component\Console\Attribute\AsCommand;

/**
 * Alias auf maintenance:folder-import mit festem Namen "import".
 *
 * Bleibt erhalten, weil dieser Command in Betriebsabläufen und in
 * docs/09-backup-restore/ verankert ist. Entfällt frühestens in einer eigenen,
 * dafür vorgesehenen Phase (siehe docs/0-planning/19-vd-maintenance-paket/).
 */
#[AsCommand(
    name: 'maintenance:import-folder-import',
    description: 'Alias für "maintenance:folder-import import": spielt ein Archiv in das Verzeichnis '
        . '"import" ein.'
)]
final class ImportFolderImportCommand extends FolderImportCommand
{
    protected function fixedName(): string|null
    {
        return 'import';
    }

    /**
     * Ohne konfiguriertes "import" greift der bisherige Pfad <projekt>/import,
     * damit bestehende Installationen ohne Konfigurationsschritt weiterlaufen.
     */
    protected function fallbackRelativePath(): string|null
    {
        return 'import';
    }
}
