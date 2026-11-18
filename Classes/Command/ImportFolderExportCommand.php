<?php
declare(strict_types=1);

namespace Vd\VdMaintenance\Command;

use Symfony\Component\Console\Attribute\AsCommand;

/**
 * Alias auf maintenance:folder-export mit festem Namen "import".
 *
 * Bleibt erhalten, weil dieser Command in Betriebsabläufen und in
 * docs/09-backup-restore/ verankert ist. Entfällt frühestens in einer eigenen,
 * dafür vorgesehenen Phase (siehe docs/0-planning/19-vd-maintenance-paket/).
 */
#[AsCommand(
    name: 'maintenance:import-folder-export',
    description: 'Alias für "maintenance:folder-export import": packt das Verzeichnis "import" nach backup/.'
)]
final class ImportFolderExportCommand extends FolderExportCommand
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
