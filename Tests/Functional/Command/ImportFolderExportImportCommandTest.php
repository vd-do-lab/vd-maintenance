<?php
declare(strict_types=1);

namespace Vd\VdMaintenance\Tests\Functional\Command;

use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Console\Tester\CommandTester;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;
use Vd\VdMaintenance\Command\ImportFolderExportCommand;
use Vd\VdMaintenance\Command\ImportFolderImportCommand;

final class ImportFolderExportImportCommandTest extends FunctionalTestCase
{
    protected array $testExtensionsToLoad = [
        'typo3conf/ext/vd_maintenance',
    ];

    private string $importDir;
    private string $outputDir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->importDir = Environment::getProjectPath() . '/import';
        $this->outputDir = sys_get_temp_dir() . '/vd-maintenance-import-folder-test-' . uniqid();

        mkdir($this->importDir . '/2026', 0777, true);
        file_put_contents($this->importDir . '/2026/202601.pdf', '%PDF-1.4 test content');
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->importDir);
        $this->removeDirectory($this->outputDir);

        parent::tearDown();
    }

    #[Test]
    public function exportsAndRestoresImportFolder(): void
    {
        $exportTester = new CommandTester($this->get(ImportFolderExportCommand::class));
        $exitCode = $exportTester->execute(['--output-dir' => $this->outputDir]);
        self::assertSame(0, $exitCode);

        $archives = glob($this->outputDir . '/import-folder-*.tar.gz');
        self::assertNotEmpty($archives);

        $this->removeDirectory($this->importDir);
        self::assertDirectoryDoesNotExist($this->importDir);

        $importTester = new CommandTester($this->get(ImportFolderImportCommand::class));
        $importExitCode = $importTester->execute(['file' => $archives[0], '--yes' => true]);

        self::assertSame(0, $importExitCode);
        self::assertSame('%PDF-1.4 test content', file_get_contents($this->importDir . '/2026/202601.pdf'));
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
