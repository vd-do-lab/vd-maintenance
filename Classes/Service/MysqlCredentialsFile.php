<?php
declare(strict_types=1);

namespace Vd\VdMaintenance\Service;

use RuntimeException;

/**
 * Schreibt Host/Port/User/Passwort als temporäre --defaults-extra-file für
 * mysqldump/mysql, damit das Passwort nicht als Klartext-Argument in der
 * Prozessliste (ps aux) sichtbar wird.
 */
final class MysqlCredentialsFile
{
    /**
     * @param array<string, mixed> $connectionParams Doctrine-DBAL-Connection-Params (host/port/user/password)
     *
     * @return string Pfad zur temporären Datei - vom Aufrufer nach Gebrauch zu löschen
     */
    public function write(array $connectionParams): string
    {
        $path = tempnam(sys_get_temp_dir(), 'vd-maintenance-db-');
        if ($path === false) {
            throw new RuntimeException('Temporäre Zugangsdaten-Datei konnte nicht angelegt werden.');
        }

        file_put_contents(
            $path,
            sprintf(
                "[client]\nhost=%s\nport=%d\nuser=%s\npassword=%s\n",
                $connectionParams['host'] ?? '127.0.0.1',
                $connectionParams['port'] ?? 3306,
                $connectionParams['user'] ?? '',
                $connectionParams['password'] ?? ''
            )
        );
        chmod($path, 0600);

        return $path;
    }
}
