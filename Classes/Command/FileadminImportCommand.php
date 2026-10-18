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
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Console\Style\SymfonyStyle;
use TYPO3\CMS\Core\Core\Environment;
use Vd\VdMaintenance\Service\BackupPaths;
use Vd\VdMaintenance\Service\DirectoryArchiver;

#[AsCommand(
    name: 'maintenance:fileadmin-import',
    description: 'Spielt ein mit maintenance:fileadmin-export erzeugtes .tar.gz-Archiv in webapp/public/fileadmin/ ein.'
)]
final class FileadminImportCommand extends Command
{
    public function __construct(
        private readonly DirectoryArchiver $archiver,
        private readonly BackupPaths $backupPaths
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument(
            'file',
            InputArgument::REQUIRED,
            'Pfad zur .tar.gz-Archivdatei (ein reiner Dateiname ohne Pfad wird zusätzlich im '
                . 'Standard-Backup-Verzeichnis gesucht)'
        );
        $this->addOption(
            'yes',
            null,
            InputOption::VALUE_NONE,
            'Sicherheitsabfrage überspringen (z. B. für Automatisierung)'
        );
        $this->setHelp(
            'Entpackt das Archiv nach webapp/public/fileadmin/ - im Archiv enthaltene Dateien überschreiben '
                . 'gleichnamige vorhandene Dateien, bereits vorhandene Dateien, die nicht im Archiv enthalten '
                . 'sind, bleiben unangetastet (kein Löschen des gesamten Verzeichnisses vorab).'
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $file = $input->getArgument('file');

        $resolvedFile = is_string($file) ? $this->backupPaths->resolveExistingFile($file) : null;
        if ($resolvedFile === null) {
            $io->error("Archivdatei nicht gefunden: {$file}");
            return Command::FAILURE;
        }

        $targetDir = Environment::getPublicPath() . '/fileadmin';

        if (!$input->getOption('yes')) {
            $question = new ConfirmationQuestion(
                "Der Inhalt von '{$resolvedFile}' wird nach '{$targetDir}' entpackt (gleichnamige vorhandene "
                    . "Dateien werden überschrieben). Fortfahren? [y/N] ",
                false
            );
            if (!$io->askQuestion($question)) {
                $io->writeln('Abgebrochen.');
                return Command::SUCCESS;
            }
        }

        try {
            $this->archiver->restore($resolvedFile, $targetDir);
        } catch (RuntimeException $exception) {
            $io->error($exception->getMessage());
            return Command::FAILURE;
        }

        $io->success("Archiv eingespielt nach: {$targetDir}");
        return Command::SUCCESS;
    }
}
