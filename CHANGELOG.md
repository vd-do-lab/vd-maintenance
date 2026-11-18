# Changelog

Alle nennenswerten Änderungen an dieser Extension werden hier dokumentiert.

Das Format orientiert sich an [Keep a Changelog](https://keepachangelog.com/de/1.1.0/),
die Versionierung folgt [Semantic Versioning](https://semver.org/lang/de/).

## [Unreleased]

Vorbereitung des ersten eigenständigen Releases. Bis dahin lebt die Extension
ausschließlich im Entwicklungs-Monorepo; siehe
`docs/0-planning/19-vd-maintenance-paket/` dort.

### Hinzugefügt

- `maintenance:folder-export <name>` und `maintenance:folder-import <name>
  <datei>`: sichern und restaurieren ein in der Extension-Konfiguration
  benanntes Verzeichnis. `maintenance:folder-export --list` zeigt die
  konfigurierten Namen samt Pfad und ob das Verzeichnis existiert.
- Extension-Konfiguration `directories` (`ext_conf_template.txt`): kommagetrennte
  `name:pfad`-Paare, jeder Pfad relativ zum Projektverzeichnis. Damit legt das
  einbindende Projekt fest, welche Verzeichnisse die Commands anfassen dürfen.
- `LICENSE` (GPL-2.0-or-later, war bisher nur in `composer.json` deklariert),
  dieses `CHANGELOG.md` und ein eigener CI-Workflow für das spätere
  eigenständige Repository.

### Geändert

- Die Extension kennt kein konkretes Projektverzeichnis mehr. Zuvor war
  `<projekt>/import` in beiden `import-folder-*`-Commands fest verdrahtet und
  ihre Beschreibung nannte die Datenlage eines bestimmten Projekts.
- `maintenance:import-folder-export`/`-import` sind jetzt Aliase auf die neuen
  Commands mit dem festen Namen `import`. Sie bleiben bewusst erhalten, weil sie
  in Betriebsabläufen verankert sind.
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

- Keine Breaking Changes. Das Archiv-Präfix `<name>-folder` ergibt für `import`
  unverändert `import-folder-<Datum>.tar.gz`; bereits abgelegte Archive bleiben
  einspielbar. Ohne jede Konfiguration arbeiten die Alias-Commands auf
  `<projekt>/import` weiter.
