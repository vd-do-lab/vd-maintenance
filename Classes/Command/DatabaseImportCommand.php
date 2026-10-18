<?php
declare(strict_types=1);

namespace Vd\VdMaintenance\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Process\Process;
use TYPO3\CMS\Core\Database\ConnectionPool;
use Vd\VdMaintenance\Service\BackupPaths;
use Vd\VdMaintenance\Service\MysqlCredentialsFile;

#[AsCommand(
    name: 'maintenance:database-import',
    description: 'Importiert einen mit maintenance:database-export erzeugten Dump in die aktuell konfigurierte Datenbank.'
)]
final class DatabaseImportCommand extends Command
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
        $this->addArgument(
            'file',
            InputArgument::REQUIRED,
            'Pfad zur .sql- oder .sql.gz-Dump-Datei (ein reiner Dateiname ohne Pfad wird zusätzlich im '
                . 'Standard-Backup-Verzeichnis gesucht)'
        );
        $this->addOption(
            'yes',
            null,
            InputOption::VALUE_NONE,
            'Sicherheitsabfrage überspringen (z. B. für Automatisierung)'
        );
        $this->setHelp(
            'Erwartet einen mit maintenance:database-export erzeugten Dump (enthält dank mysqldumps '
                . 'Standardverhalten bereits "DROP TABLE IF EXISTS" pro Tabelle) - die aktuell konfigurierte '
                . 'Datenbank wird dabei NICHT komplett gelöscht/neu angelegt (anders als docker/scripts/'
                . 'import-dump.sh), sondern Tabelle für Tabelle ersetzt. Ein Dump ohne dieses Verhalten '
                . '(z. B. ein roher phpMyAdmin-Export mit abweichenden Optionen) kann zu Konflikten mit '
                . 'bereits vorhandenen Tabellen führen.'
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $file = $input->getArgument('file');

        $resolvedFile = is_string($file) ? $this->backupPaths->resolveExistingFile($file) : null;
        if ($resolvedFile === null) {
            $io->error("Dump-Datei nicht gefunden: {$file}");
            return Command::FAILURE;
        }
        $file = $resolvedFile;

        $params = $this->connectionPool
            ->getConnectionByName(ConnectionPool::DEFAULT_CONNECTION_NAME)
            ->getParams();
        $dbName = $params['dbname'] ?? null;

        if (!is_string($dbName) || $dbName === '') {
            $io->error('Datenbankname konnte nicht aus der TYPO3-Verbindungskonfiguration gelesen werden.');
            return Command::FAILURE;
        }

        if (!$input->getOption('yes')) {
            $question = new ConfirmationQuestion(
                "Alle Tabellen der Datenbank '{$dbName}' werden durch den Inhalt von '{$file}' ersetzt "
                    . "(bestehende Tabellen werden dabei gelöscht und neu angelegt, siehe Hinweis in der Hilfe "
                    . "zu diesem Befehl). Fortfahren? [y/N] ",
                false
            );
            if (!$io->askQuestion($question)) {
                $io->writeln('Abgebrochen.');
                return Command::SUCCESS;
            }
        }

        $credentialsFile = $this->credentialsFile->write($params);

        try {
            $io->text("Importiere {$file} nach '{$dbName}' (je nach Dateigröße kann das einige Minuten dauern)...");

            $isGzipped = str_ends_with($file, '.gz');
            $importCommand = $isGzipped
                ? sprintf(
                    'gunzip -c %s | mysql --defaults-extra-file=%s %s',
                    escapeshellarg($file),
                    escapeshellarg($credentialsFile),
                    escapeshellarg($dbName)
                )
                : sprintf(
                    'mysql --defaults-extra-file=%s %s < %s',
                    escapeshellarg($credentialsFile),
                    escapeshellarg($dbName),
                    escapeshellarg($file)
                );

            $process = Process::fromShellCommandline($importCommand);
            $process->setTimeout(3600);
            $process->run();

            if (!$process->isSuccessful()) {
                $io->error('Import fehlgeschlagen: ' . $process->getErrorOutput());
                return Command::FAILURE;
            }
        } finally {
            unlink($credentialsFile);
        }

        $tableCount = count(
            $this->connectionPool
                ->getConnectionByName(ConnectionPool::DEFAULT_CONNECTION_NAME)
                ->createSchemaManager()
                ->listTableNames()
        );

        $io->success("Fertig: {$tableCount} Tabellen in '{$dbName}' aus {$file} importiert.");
        return Command::SUCCESS;
    }
}
