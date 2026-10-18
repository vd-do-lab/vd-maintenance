<?php
declare(strict_types=1);

namespace Vd\VdMaintenance\Tests\Unit\Service;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Vd\VdMaintenance\Service\MysqlCredentialsFile;

final class MysqlCredentialsFileTest extends TestCase
{
    #[Test]
    public function writesClientSectionWithGivenConnectionParams(): void
    {
        $path = (new MysqlCredentialsFile())->write([
            'host' => 'db.example.test',
            'port' => 3306,
            'user' => 'typo3',
            'password' => 'secret',
        ]);

        self::assertFileExists($path);
        self::assertSame(
            "[client]\nhost=db.example.test\nport=3306\nuser=typo3\npassword=secret\n",
            file_get_contents($path)
        );

        unlink($path);
    }

    #[Test]
    public function restrictsFilePermissionsToOwnerOnly(): void
    {
        $path = (new MysqlCredentialsFile())->write([
            'host' => '127.0.0.1',
            'port' => 3306,
            'user' => 'typo3',
            'password' => 'secret',
        ]);

        self::assertSame('0600', substr(sprintf('%o', fileperms($path)), -4));

        unlink($path);
    }

    #[Test]
    public function fallsBackToDefaultsForMissingConnectionParams(): void
    {
        $path = (new MysqlCredentialsFile())->write([]);

        self::assertSame(
            "[client]\nhost=127.0.0.1\nport=3306\nuser=\npassword=\n",
            file_get_contents($path)
        );

        unlink($path);
    }
}
