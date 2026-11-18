<?php
declare(strict_types=1);

namespace Vd\VdMaintenance\Service;

use RuntimeException;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Configuration\Exception\ExtensionConfigurationExtensionNotConfiguredException;
use TYPO3\CMS\Core\Configuration\Exception\ExtensionConfigurationPathDoesNotExistException;
use TYPO3\CMS\Core\Core\Environment;

/**
 * Löst die in der Extension-Konfiguration hinterlegten, benannten Verzeichnisse
 * auf absolute Pfade auf ("maintenance.directories", Format: "name:pfad,
 * name:pfad"). Dadurch kennt diese Extension kein konkretes Projektverzeichnis
 * mehr - siehe docs/0-planning/19-vd-maintenance-paket/README.md.
 *
 * Jeder Pfad wird relativ zum Projektverzeichnis aufgelöst und muss darin
 * liegen: Ein Export-/Import-Command, der über eine Fehlkonfiguration aus dem
 * Projekt herauszeigt, schreibt sonst an unerwarteter Stelle.
 */
final class MaintenanceDirectories
{
    private const NAME_PATTERN = '/^[a-zA-Z0-9][a-zA-Z0-9_-]*$/';

    public function __construct(
        private readonly ExtensionConfiguration $extensionConfiguration
    ) {
    }

    /**
     * @return array<string, string> Name => absoluter Pfad
     *
     * @throws RuntimeException bei ungültiger Konfiguration
     */
    public function all(): array
    {
        $directories = [];

        foreach (explode(',', $this->rawConfiguration()) as $entry) {
            $entry = trim($entry);
            if ($entry === '') {
                continue;
            }

            [$name, $relativePath] = $this->splitEntry($entry);
            $directories[$name] = $this->resolve($name, $relativePath);
        }

        return $directories;
    }

    /**
     * Absoluter Pfad des benannten Verzeichnisses.
     *
     * $fallbackRelativePath existiert ausschließlich für die Alias-Commands
     * maintenance:import-folder-export/-import, die ohne Konfiguration
     * weiterhin funktionieren müssen (AGENTS.md: keine bestehende
     * Funktionalität ersatzlos streichen).
     *
     * @throws RuntimeException wenn der Name unbekannt und kein Fallback gesetzt ist
     */
    public function path(string $name, string|null $fallbackRelativePath = null): string
    {
        $directories = $this->all();

        if (isset($directories[$name])) {
            return $directories[$name];
        }

        if ($fallbackRelativePath !== null) {
            return $this->resolve($name, $fallbackRelativePath);
        }

        $known = array_keys($directories);
        throw new RuntimeException(sprintf(
            'Unbekanntes Verzeichnis "%s". %s',
            $name,
            $known === []
                ? 'Es ist bisher keines konfiguriert - siehe Extension-Konfiguration '
                    . '"vd_maintenance" -> directories.'
                : 'Konfiguriert sind: ' . implode(', ', $known) . '.'
        ));
    }

    /**
     * Präfix der Archivdatei für ein benanntes Verzeichnis.
     *
     * Für den Namen "import" ergibt das "import-folder" und damit exakt das
     * Namensschema, das die bisherigen Commands erzeugt haben - bereits
     * abgelegte Archive bleiben so auffindbar und wiedereinspielbar.
     */
    public function archivePrefix(string $name): string
    {
        return $name . '-folder';
    }

    private function rawConfiguration(): string
    {
        try {
            $value = $this->extensionConfiguration->get('vd_maintenance', 'directories');
        } catch (
            ExtensionConfigurationExtensionNotConfiguredException
            | ExtensionConfigurationPathDoesNotExistException
        ) {
            // Keine Konfiguration ist kein Fehler: database-* und fileadmin-*
            // arbeiten ohne, folder-* meldet dann "nichts konfiguriert".
            return '';
        }

        return is_string($value) ? $value : '';
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function splitEntry(string $entry): array
    {
        $separatorPosition = strpos($entry, ':');
        if ($separatorPosition === false) {
            // Kurzform "import" entspricht "import:import".
            return [$entry, $entry];
        }

        return [
            trim(substr($entry, 0, $separatorPosition)),
            trim(substr($entry, $separatorPosition + 1)),
        ];
    }

    /**
     * @throws RuntimeException
     */
    private function resolve(string $name, string $relativePath): string
    {
        if (preg_match(self::NAME_PATTERN, $name) !== 1) {
            // Der Name landet im Archiv-Dateinamen - alles andere als
            // [A-Za-z0-9_-] darf dort nicht ankommen.
            throw new RuntimeException(sprintf(
                'Ungültiger Verzeichnisname "%s": erlaubt sind Buchstaben, Ziffern, "-" und "_".',
                $name
            ));
        }

        if ($relativePath === '' || str_starts_with($relativePath, '/')) {
            throw new RuntimeException(sprintf(
                'Pfad für "%s" muss relativ zum Projektverzeichnis angegeben werden (nicht "%s").',
                $name,
                $relativePath
            ));
        }

        $projectPath = rtrim(Environment::getProjectPath(), '/');
        $resolved = $this->normalize($projectPath . '/' . $relativePath);

        if ($resolved === $projectPath || !str_starts_with($resolved, $projectPath . '/')) {
            throw new RuntimeException(sprintf(
                'Pfad für "%s" liegt nicht innerhalb des Projektverzeichnisses: %s',
                $name,
                $resolved
            ));
        }

        return $resolved;
    }

    /**
     * Rein lexikalische Auflösung von "." und ".." - realpath() scheidet aus,
     * weil ein Zielverzeichnis beim Import noch nicht existieren muss.
     */
    private function normalize(string $path): string
    {
        $segments = [];

        foreach (explode('/', $path) as $segment) {
            if ($segment === '' || $segment === '.') {
                continue;
            }

            if ($segment === '..') {
                array_pop($segments);
                continue;
            }

            $segments[] = $segment;
        }

        return '/' . implode('/', $segments);
    }
}
