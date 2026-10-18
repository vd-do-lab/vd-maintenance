<?php
declare(strict_types=1);

namespace Vd\VdMaintenance\Tests\Functional\Command;

use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Console\Tester\CommandTester;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;
use Vd\VdMaintenance\Command\DatabaseImportCommand;

/**
 * Prüft ausschließlich die Datei-Auflösung vor dem eigentlichen mysql-Import
 * (siehe DatabaseExportCommand/-ImportCommand für den echten DB-Import,
 * verifiziert gegen die lokale Docker-MariaDB statt hier gegen SQLite -
 * siehe docs/09-backup-restore/README.md).
 */
final class DatabaseImportCommandTest extends FunctionalTestCase
{
    protected array $testExtensionsToLoad = [
        'typo3conf/ext/vd_maintenance',
    ];

    private string $backupDir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->backupDir = dirname(Environment::getProjectPath()) . '/backup';
        if (!is_dir($this->backupDir)) {
            mkdir($this->backupDir, 0777, true);
        }
    }

    protected function tearDown(): void
    {
        $fixture = $this->backupDir . '/db-fixture-test.sql.gz';
        if (is_file($fixture)) {
            unlink($fixture);
        }

        parent::tearDown();
    }

    #[Test]
    public function resolvesBareFilenameAgainstDefaultBackupDirectory(): void
    {
        file_put_contents($this->backupDir . '/db-fixture-test.sql.gz', 'not a real dump, just a fixture');

        $tester = new CommandTester($this->get(DatabaseImportCommand::class));
        $tester->execute(['file' => 'db-fixture-test.sql.gz', '--yes' => true]);

        // Das SQLite-Testsystem kennt keinen "dbname" - der Befehl scheitert
        // danach zwangsläufig an der eigentlichen Verbindung, wichtig ist
        // hier nur: er kommt überhaupt an diese Stelle und meldet NICHT
        // "Dump-Datei nicht gefunden" - das beweist die Fallback-Auflösung.
        self::assertStringNotContainsString('Dump-Datei nicht gefunden', $tester->getDisplay());
    }

    #[Test]
    public function reportsMissingFileWhenNeitherGivenPathNorFallbackExists(): void
    {
        $tester = new CommandTester($this->get(DatabaseImportCommand::class));
        $exitCode = $tester->execute(['file' => 'does-not-exist-anywhere.sql.gz', '--yes' => true]);

        self::assertSame(1, $exitCode);
        self::assertStringContainsString('Dump-Datei nicht gefunden', $tester->getDisplay());
    }
}
