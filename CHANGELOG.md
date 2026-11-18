# Changelog

Alle nennenswerten Änderungen an dieser Extension werden hier dokumentiert.

Das Format orientiert sich an [Keep a Changelog](https://keepachangelog.com/de/1.1.0/),
die Versionierung folgt [Semantic Versioning](https://semver.org/lang/de/).

## [Unreleased]

## [1.0.0] — 2026-11-18

Erstes Release als eigenständiges Composer-Paket. Die Extension war zuvor
ausschließlich Teil des Entwicklungs-Monorepos und ist dort seit ihrem
Initial-Commit produktiv im Einsatz — `1.0.0` statt `0.x`, weil der
Funktionsumfang vollständig und erprobt ist.

### Funktionsumfang

- **Datenbank-Backup/Restore** (`maintenance:database-export`/`-import`):
  `mysqldump` bzw. `mysql` über eine temporäre `--defaults-extra-file`
  (Modus 0600), damit das Passwort nicht als Klartext-Argument in `ps aux`
  erscheint. Zugangsdaten kommen aus TYPO3s `ConnectionPool`, der Import
  ersetzt Tabelle für Tabelle statt per `DROP DATABASE` — dafür fehlen einem
  normalen App-DB-Benutzer auf Shared-Hosting meist die Rechte.
- **`fileadmin/`-Backup/Restore** (`maintenance:fileadmin-export`/`-import`).
- **Backup/Restore beliebiger konfigurierter Verzeichnisse**
  (`maintenance:folder-export`/`-import`, `--list`).
- Alle Exporte schreiben datiert nach `backup/` neben der Installation, per
  `--output-dir` überschreibbar; alle Importe finden eine Datei auch anhand
  ihres bloßen Namens dort wieder.

### Hinzugefügt gegenüber dem Monorepo-Stand

- Extension-Konfiguration `directories` (`ext_conf_template.txt`):
  kommagetrennte `name:pfad`-Paare. Damit legt das einbindende Projekt fest,
  welche Verzeichnisse die Commands anfassen dürfen. Zuvor war
  `<projekt>/import` fest verdrahtet und die Command-Beschreibung nannte die
  Datenlage eines bestimmten Projekts.
- `maintenance:folder-export <name>` / `maintenance:folder-import <name>
  <datei>` samt `--list`.
- `LICENSE` (GPL-2.0-or-later, war bisher nur in `composer.json` deklariert),
  dieses `CHANGELOG.md` und ein eigener CI-Workflow.

### Geändert

- `maintenance:import-folder-export`/`-import` sind jetzt Aliase auf die neuen
  Commands mit dem festen Namen `import`. Sie bleiben bewusst erhalten, weil
  sie in Betriebsabläufen verankert sind.
- `composer.json` deklariert `symfony/console` und `symfony/process`. Beide
  waren schon immer in Gebrauch (42 bzw. 3 Verwendungen), kamen aber nur
  transitiv über `typo3/cms-core` herein — das war Glück, keine Zusage.
- `composer.json` erlaubt die beiden TYPO3-Composer-Plugins. Ohne diesen Block
  bricht ein `composer install` im eigenständigen Checkout mit
  `typo3/class-alias-loader contains a Composer plugin which is blocked` ab.

### Sicherheit

- Ein konfigurierter Pfad wird gegen das Projektverzeichnis geprüft und
  abgewiesen, wenn er absolut ist oder per `..` herausführt. Ein
  Verzeichnisname wird auf `[A-Za-z0-9_-]` eingeschränkt, da er in den
  Archiv-Dateinamen eingeht.

### Kompatibilität

- Keine Breaking Changes gegenüber dem Monorepo-Stand. Das Archiv-Präfix
  `<name>-folder` ergibt für `import` unverändert
  `import-folder-<Datum>.tar.gz`; bereits abgelegte Archive bleiben
  einspielbar. Ohne jede Konfiguration arbeiten die Alias-Commands auf
  `<projekt>/import` weiter.

[Unreleased]: https://github.com/vd-do-lab/vd-maintenance/compare/v1.0.0...HEAD
[1.0.0]: https://github.com/vd-do-lab/vd-maintenance/releases/tag/v1.0.0
