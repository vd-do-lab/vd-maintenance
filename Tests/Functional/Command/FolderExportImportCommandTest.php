<?php
declare(strict_types=1);

namespace Vd\VdMaintenance\Tests\Functional\Command;

use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Console\Tester\CommandTester;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;
use Vd\VdMaintenance\Command\FolderExportCommand;
use Vd\VdMaintenance\Command\FolderImportCommand;
use Vd\VdMaintenance\Command\ImportFolderExportCommand;

final class FolderExportImportCommandTest extends FunctionalTestCase
{
    protected array $testExtensionsToLoad = [
        'typo3conf/ext/vd_maintenance',
    ];

    protected array $configurationToUseInTestInstance = [
        'EXTENSIONS' => [
            'vd_maintenance' => [
                'directories' => 'scans:var/scans, import:var/eigenes-import',
            ],
        ],
    ];

    private string $scansDir;
    private string $outputDir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->scansDir = Environment::getProjectPath() . '/var/scans';
        $this->outputDir = sys_get_temp_dir() . '/vd-maintenance-folder-test-' . uniqid();

        mkdir($this->scansDir . '/2026', 0777, true);
        file_put_contents($this->scansDir . '/2026/beleg.pdf', '%PDF-1.4 scan content');
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->scansDir);
        $this->removeDirectory($this->outputDir);

        parent::tearDown();
    }

    #[Test]
    public function exportsAndRestoresAConfiguredDirectory(): void
    {
        $exportTester = new CommandTester($this->get(FolderExportCommand::class));
        $exitCode = $exportTester->execute(['name' => 'scans', '--output-dir' => $this->outputDir]);
        self::assertSame(0, $exitCode, $exportTester->getDisplay());

        $archives = glob($this->outputDir . '/scans-folder-*.tar.gz');
        self::assertNotEmpty($archives);

        $this->removeDirectory($this->scansDir);
        self::assertDirectoryDoesNotExist($this->scansDir);

        $importTester = new CommandTester($this->get(FolderImportCommand::class));
        $importExitCode = $importTester->execute([
            'name' => 'scans',
            'file' => $archives[0],
            '--yes' => true,
        ]);

        self::assertSame(0, $importExitCode, $importTester->getDisplay());
        self::assertSame('%PDF-1.4 scan content', file_get_contents($this->scansDir . '/2026/beleg.pdf'));
    }

    #[Test]
    public function unknownNameFailsAndNamesTheConfiguredDirectories(): void
    {
        $tester = new CommandTester($this->get(FolderExportCommand::class));
        $exitCode = $tester->execute(['name' => 'gibtesnicht', '--output-dir' => $this->outputDir]);

        self::assertSame(1, $exitCode);
        self::assertStringContainsString('Unbekanntes Verzeichnis', $tester->getDisplay());
        self::assertStringContainsString('scans', $tester->getDisplay());
    }

    #[Test]
    public function missingNameFailsWithoutTouchingTheFilesystem(): void
    {
        $tester = new CommandTester($this->get(FolderExportCommand::class));
        $exitCode = $tester->execute(['--output-dir' => $this->outputDir]);

        self::assertSame(1, $exitCode);
        self::assertDirectoryDoesNotExist($this->outputDir);
    }

    #[Test]
    public function listShowsTheConfiguredDirectories(): void
    {
        $tester = new CommandTester($this->get(FolderExportCommand::class));
        $exitCode = $tester->execute(['--list' => true]);

        self::assertSame(0, $exitCode);
        self::assertStringContainsString('scans', $tester->getDisplay());
        self::assertStringContainsString($this->scansDir, $tester->getDisplay());
    }

    /**
     * Der Alias behält Name und Archiv-Präfix, folgt aber der Konfiguration,
     * wenn "import" dort hinterlegt ist - sein fest eingebauter Rückfallpfad
     * <projekt>/import darf die Konfiguration nicht überstimmen.
     */
    #[Test]
    public function aliasFollowsTheConfiguredPathInsteadOfItsFallback(): void
    {
        $configuredImportDir = Environment::getProjectPath() . '/var/eigenes-import';
        mkdir($configuredImportDir, 0777, true);
        file_put_contents($configuredImportDir . '/kontoauszug.pdf', '%PDF-1.4 alias content');

        $command = $this->get(ImportFolderExportCommand::class);
        self::assertSame('maintenance:import-folder-export', $command->getName());

        $tester = new CommandTester($command);
        $exitCode = $tester->execute(['--output-dir' => $this->outputDir]);

        self::assertSame(0, $exitCode, $tester->getDisplay());
        self::assertNotEmpty(glob($this->outputDir . '/import-folder-*.tar.gz'));
        self::assertDirectoryDoesNotExist(Environment::getProjectPath() . '/import');

        $this->removeDirectory($configuredImportDir);
    }

    private function removeDirectory(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }

        $items = scandir($directory);
        if ($items === false) {
            return;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $path = $directory . '/' . $item;
            if (is_dir($path)) {
                $this->removeDirectory($path);
            } else {
                unlink($path);
            }
        }

        rmdir($directory);
    }
}
