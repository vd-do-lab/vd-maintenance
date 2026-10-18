<?php
declare(strict_types=1);

namespace Vd\VdMaintenance\Command;

use RuntimeException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use TYPO3\CMS\Core\Core\Environment;
use Vd\VdMaintenance\Service\BackupPaths;
use Vd\VdMaintenance\Service\DirectoryArchiver;

#[AsCommand(
    name: 'maintenance:import-folder-export',
    description: 'Packt webapp/import/ (vd_budgets Kontoauszug-Rohdaten) als datiertes .tar.gz nach backup/.'
)]
final class ImportFolderExportCommand extends Command
{
    public function __construct(
        private readonly DirectoryArchiver $archiver,
        private readonly BackupPaths $backupPaths
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption(
            'output-dir',
            'o',
            InputOption::VALUE_OPTIONAL,
            'Zielverzeichnis für das Archiv (Standard: backup/ als Geschwisterverzeichnis der TYPO3-Installation)'
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $sourceDir = Environment::getProjectPath() . '/import';
        $outputDir = $input->getOption('output-dir') ?? $this->backupPaths->defaultDirectory();

        try {
            $targetFile = $this->archiver->archive($sourceDir, $outputDir, 'import-folder');
        } catch (RuntimeException $exception) {
            $io->error($exception->getMessage());
            return Command::FAILURE;
        }

        $io->success("Import-Verzeichnis archiviert: {$targetFile}");
        return Command::SUCCESS;
    }
}
