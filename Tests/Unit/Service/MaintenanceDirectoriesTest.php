<?php
declare(strict_types=1);

namespace Vd\VdMaintenance\Tests\Unit\Service;

use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use TYPO3\CMS\Core\Configuration\Exception\ExtensionConfigurationExtensionNotConfiguredException;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;
use Vd\VdMaintenance\Service\MaintenanceDirectories;

final class MaintenanceDirectoriesTest extends UnitTestCase
{
    private function subject(string $configuration): MaintenanceDirectories
    {
        $extensionConfiguration = $this->createStub(ExtensionConfiguration::class);
        $extensionConfiguration->method('get')->willReturn($configuration);

        return new MaintenanceDirectories($extensionConfiguration);
    }

    private function projectPath(): string
    {
        return rtrim(Environment::getProjectPath(), '/');
    }

    #[Test]
    public function missingConfigurationIsNotAnError(): void
    {
        $extensionConfiguration = $this->createStub(ExtensionConfiguration::class);
        $extensionConfiguration->method('get')
            ->willThrowException(new ExtensionConfigurationExtensionNotConfiguredException());

        self::assertSame([], (new MaintenanceDirectories($extensionConfiguration))->all());
    }

    #[Test]
    public function emptyConfigurationYieldsNoDirectories(): void
    {
        self::assertSame([], $this->subject('')->all());
    }

    #[Test]
    public function parsesNamePathPairs(): void
    {
        self::assertSame(
            [
                'import' => $this->projectPath() . '/import',
                'scans' => $this->projectPath() . '/var/scans',
            ],
            $this->subject('import:import, scans:var/scans')->all()
        );
    }

    #[Test]
    public function shorthandEntryUsesNameAsPath(): void
    {
        self::assertSame(
            ['import' => $this->projectPath() . '/import'],
            $this->subject('import')->all()
        );
    }

    #[Test]
    public function resolvesRelativeSegmentsWithinTheProject(): void
    {
        self::assertSame(
            ['scans' => $this->projectPath() . '/var/scans'],
            $this->subject('scans:./var/tmp/../scans')->all()
        );
    }

    #[Test]
    public function rejectsAbsolutePaths(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('muss relativ zum Projektverzeichnis');

        $this->subject('scans:/etc')->all();
    }

    #[Test]
    public function rejectsPathsOutsideTheProject(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('liegt nicht innerhalb des Projektverzeichnisses');

        $this->subject('escape:../../etc')->all();
    }

    #[Test]
    public function rejectsTheProjectDirectoryItself(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('liegt nicht innerhalb des Projektverzeichnisses');

        $this->subject('all:.')->all();
    }

    #[Test]
    public function rejectsNamesThatWouldLeakIntoArchiveFilenames(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Ungültiger Verzeichnisname');

        $this->subject('../evil:import')->all();
    }

    #[Test]
    public function pathReturnsConfiguredDirectory(): void
    {
        self::assertSame(
            $this->projectPath() . '/var/scans',
            $this->subject('scans:var/scans')->path('scans')
        );
    }

    #[Test]
    public function pathFailsForUnknownNameAndNamesTheConfiguredOnes(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Konfiguriert sind: scans.');

        $this->subject('scans:var/scans')->path('import');
    }

    #[Test]
    public function pathFallsBackWhenNothingIsConfigured(): void
    {
        self::assertSame(
            $this->projectPath() . '/import',
            $this->subject('')->path('import', 'import')
        );
    }

    #[Test]
    public function configurationWinsOverFallback(): void
    {
        self::assertSame(
            $this->projectPath() . '/var/import',
            $this->subject('import:var/import')->path('import', 'import')
        );
    }

    #[Test]
    public function archivePrefixKeepsTheEstablishedNamingScheme(): void
    {
        self::assertSame('import-folder', $this->subject('')->archivePrefix('import'));
    }
}
