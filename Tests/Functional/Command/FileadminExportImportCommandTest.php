<?php
declare(strict_types=1);

namespace Vd\VdMaintenance\Tests\Functional\Command;

use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Console\Tester\CommandTester;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;
use Vd\VdMaintenance\Command\FileadminExportCommand;
use Vd\VdMaintenance\Command\FileadminImportCommand;

final class FileadminExportImportCommandTest extends FunctionalTestCase
{
    protected array $testExtensionsToLoad = [
        'typo3conf/ext/vd_maintenance',
    ];

    private string $fileadminDir;
    private string $outputDir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->fileadminDir = Environment::getPublicPath() . '/fileadmin';
        $this->outputDir = sys_get_temp_dir() . '/vd-maintenance-fileadmin-test-' . uniqid();

        if (!is_dir($this->fileadminDir)) {
            mkdir($this->fileadminDir, 0777, true);
        }
        file_put_contents($this->fileadminDir . '/upload.txt', 'editor upload');
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->outputDir);

        if (is_file($this->fileadminDir . '/upload.txt')) {
            unlink($this->fileadminDir . '/upload.txt');
        }

        parent::tearDown();
    }

    #[Test]
    public function exportsAndRestoresFileadmin(): void
    {
        $exportTester = new CommandTester($this->get(FileadminExportCommand::class));
        $exitCode = $exportTester->execute(['--output-dir' => $this->outputDir]);
        self::assertSame(0, $exitCode);

        $archives = glob($this->outputDir . '/fileadmin-*.tar.gz');
        self::assertNotEmpty($archives);

        unlink($this->fileadminDir . '/upload.txt');

        $importTester = new CommandTester($this->get(FileadminImportCommand::class));
        $importExitCode = $importTester->execute(['file' => $archives[0], '--yes' => true]);

        self::assertSame(0, $importExitCode);
        self::assertSame('editor upload', file_get_contents($this->fileadminDir . '/upload.txt'));
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
