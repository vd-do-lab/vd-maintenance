<?php
declare(strict_types=1);

namespace Vd\VdMaintenance\Command;

use RuntimeException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Vd\VdMaintenance\Service\BackupPaths;
use Vd\VdMaintenance\Service\DirectoryArchiver;
use Vd\VdMaintenance\Service\MaintenanceDirectories;

#[AsCommand(
    name: 'maintenance:folder-export',
    description: 'Packt ein konfiguriertes Verzeichnis als datiertes .tar.gz nach backup/.'
)]
class FolderExportCommand extends Command
{
    public function __construct(
        private readonly DirectoryArchiver $archiver,
        private readonly BackupPaths $backupPaths,
        private readonly MaintenanceDirectories $directories
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument(
            'name',
            InputArgument::OPTIONAL,
            'Name des zu sichernden Verzeichnisses aus der Extension-Konfiguration "directories"'
        );
        $this->addOption(
            'output-dir',
            'o',
            InputOption::VALUE_OPTIONAL,
            'Zielverzeichnis für das Archiv (Standard: backup/ als Geschwisterverzeichnis der TYPO3-Installation)'
        );
        $this->addOption(
            'list',
            null,
            InputOption::VALUE_NONE,
            'Zeigt die konfigurierten Verzeichnisse an, ohne etwas zu sichern'
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        try {
            if ($input->getOption('list')) {
                return $this->listDirectories($io);
            }

            $name = $this->resolveName($input, $io);
            if ($name === null) {
                return Command::FAILURE;
            }

            $sourceDir = $this->directories->path($name, $this->fallbackRelativePath());
            $outputDir = $input->getOption('output-dir') ?? $this->backupPaths->defaultDirectory();

            $targetFile = $this->archiver->archive(
                $sourceDir,
                $outputDir,
                $this->directories->archivePrefix($name)
            );
        } catch (RuntimeException $exception) {
            $io->error($exception->getMessage());
            return Command::FAILURE;
        }

        $io->success("Verzeichnis '{$name}' archiviert: {$targetFile}");
        return Command::SUCCESS;
    }

    /**
     * Von den Alias-Commands überschrieben, die einen festen Namen mitbringen.
     */
    protected function fixedName(): string|null
    {
        return null;
    }

    /**
     * Rückfallpfad, falls der feste Name nicht konfiguriert ist - nur für die
     * Alias-Commands relevant, die ohne Konfiguration weiterlaufen müssen.
     */
    protected function fallbackRelativePath(): string|null
    {
        return null;
    }

    private function resolveName(InputInterface $input, SymfonyStyle $io): string|null
    {
        $fixedName = $this->fixedName();
        if ($fixedName !== null) {
            return $fixedName;
        }

        $name = $input->getArgument('name');
        if (is_string($name) && $name !== '') {
            return $name;
        }

        $io->error('Bitte den Namen des Verzeichnisses angeben - verfügbare Namen zeigt --list.');
        return null;
    }

    private function listDirectories(SymfonyStyle $io): int
    {
        $directories = $this->directories->all();

        if ($directories === []) {
            $io->warning(
                'Es ist kein Verzeichnis konfiguriert. Extension-Konfiguration "vd_maintenance" -> '
                    . 'directories, z. B.: import:import'
            );
            return Command::SUCCESS;
        }

        $io->table(
            ['Name', 'Pfad', 'vorhanden'],
            array_map(
                static fn (string $name, string $path): array => [
                    $name,
                    $path,
                    is_dir($path) ? 'ja' : 'nein',
                ],
                array_keys($directories),
                $directories
            )
        );

        return Command::SUCCESS;
    }
}
