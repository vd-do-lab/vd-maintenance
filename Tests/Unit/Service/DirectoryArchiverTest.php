<?php
declare(strict_types=1);

namespace Vd\VdMaintenance\Tests\Unit\Service;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Vd\VdMaintenance\Service\DirectoryArchiver;

final class DirectoryArchiverTest extends TestCase
{
    private string $sourceDir;
    private string $outputDir;
    private string $restoreDir;

    protected function setUp(): void
    {
        parent::setUp();

        $base = sys_get_temp_dir() . '/vd-maintenance-archiver-test-' . uniqid();
        $this->sourceDir = $base . '/source';
        $this->outputDir = $base . '/output';
        $this->restoreDir = $base . '/restore';

        mkdir($this->sourceDir . '/sub', 0777, true);
        file_put_contents($this->sourceDir . '/sub/file.txt', 'hello world');
    }

    protected function tearDown(): void
    {
        $this->removeDirectory(dirname($this->sourceDir));

        parent::tearDown();
    }

    #[Test]
    public function archivesAndRestoresDirectoryContentsIdentically(): void
    {
        $archiver = new DirectoryArchiver();

        $archiveFile = $archiver->archive($this->sourceDir, $this->outputDir, 'test-prefix');

        self::assertFileExists($archiveFile);
        self::assertStringStartsWith('test-prefix-', basename($archiveFile));
        self::assertStringEndsWith('.tar.gz', $archiveFile);

        $archiver->restore($archiveFile, $this->restoreDir);

        self::assertFileExists($this->restoreDir . '/sub/file.txt');
        self::assertSame('hello world', file_get_contents($this->restoreDir . '/sub/file.txt'));
    }

    #[Test]
    public function restorePreservesExistingFilesNotContainedInArchive(): void
    {
        $archiver = new DirectoryArchiver();
        $archiveFile = $archiver->archive($this->sourceDir, $this->outputDir, 'test-prefix');

        mkdir($this->restoreDir, 0777, true);
        file_put_contents($this->restoreDir . '/untouched.txt', 'keep me');

        $archiver->restore($archiveFile, $this->restoreDir);

        self::assertFileExists($this->restoreDir . '/untouched.txt');
        self::assertSame('keep me', file_get_contents($this->restoreDir . '/untouched.txt'));
    }

    #[Test]
    public function archiveThrowsWhenSourceDirectoryDoesNotExist(): void
    {
        $this->expectException(RuntimeException::class);

        (new DirectoryArchiver())->archive($this->sourceDir . '-missing', $this->outputDir, 'test-prefix');
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
