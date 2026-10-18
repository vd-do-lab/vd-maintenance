<?php
declare(strict_types=1);

namespace Vd\VdMaintenance\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Process\Process;
use TYPO3\CMS\Core\Database\ConnectionPool;
use Vd\VdMaintenance\Service\BackupPaths;
use Vd\VdMaintenance\Service\MysqlCredentialsFile;

#[AsCommand(
    name: 'maintenance:database-export',
    description: 'Exportiert die komplette Datenbank als datierten, komprimierten mysqldump nach backup/.'
)]
final class DatabaseExportCommand extends Command
{
    public function __construct(
        private readonly ConnectionPool $connectionPool,
        private readonly MysqlCredentialsFile $credentialsFile,
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
            'Zielverzeichnis für den Dump (Standard: backup/ als Geschwisterverzeichnis der TYPO3-Installation)'
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $outputDir = $input->getOption('output-dir') ?? $this->backupPaths->defaultDirectory();
        $outputDir = rtrim($outputDir, '/');

        if (!is_dir($outputDir) && !mkdir($outputDir, 0775, true) && !is_dir($outputDir)) {
            $io->error("Zielverzeichnis konnte nicht erstellt werden: {$outputDir}");
            return Command::FAILURE;
        }

        $params = $this->connectionPool
            ->getConnectionByName(ConnectionPool::DEFAULT_CONNECTION_NAME)
            ->getParams();
        $dbName = $params['dbname'] ?? null;

        if (!is_string($dbName) || $dbName === '') {
            $io->error('Datenbankname konnte nicht aus der TYPO3-Verbindungskonfiguration gelesen werden.');
            return Command::FAILURE;
        }

        $credentialsFile = $this->credentialsFile->write($params);
        $targetFile = sprintf('%s/db-%s.sql.gz', $outputDir, date('Y-m-d_His'));

        try {
            $process = Process::fromShellCommandline(
                sprintf(
                    'mysqldump --defaults-extra-file=%s %s | gzip > %s',
                    escapeshellarg($credentialsFile),
                    escapeshellarg($dbName),
                    escapeshellarg($targetFile)
                )
            );
            $process->setTimeout(3600);
            $process->run();

            if (!$process->isSuccessful()) {
                $io->error('mysqldump fehlgeschlagen: ' . $process->getErrorOutput());
                return Command::FAILURE;
            }
        } finally {
            unlink($credentialsFile);
        }

        $io->success("Datenbank-Export erstellt: {$targetFile}");
        return Command::SUCCESS;
    }
}
