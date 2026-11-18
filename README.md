# vd_maintenance

Projektunabhängige Backup/Restore-Commands für TYPO3: Datenbank, `fileadmin/`
und frei konfigurierbare Verzeichnisse. Die Extension enthält keine fachliche
Logik und kennt kein bestimmtes Projekt — sie lässt sich in jede TYPO3-v14-
Installation einbinden.

## Installation

```bash
composer require vd/vd-maintenance
```

Das Paket liegt in einem privaten Repository, also braucht das Zielprojekt
zusätzlich einen `repositories`-Eintrag:

```json
{
    "repositories": [
        { "type": "vcs", "url": "https://github.com/vd-do-lab/vd-maintenance.git" }
    ]
}
```

Composer greift bei privaten GitHub-Repositories auf SSH zurück, auch wenn eine
HTTPS-URL konfiguriert ist. Ohne hinterlegten SSH-Key oder `github-oauth`-Token
bricht die Installation mit `Permission denied (publickey)` ab — eine bestehende
`gh`-Anmeldung allein genügt dafür nicht.

## Commands

Alle Export-Commands schreiben nach `backup/` (Geschwisterverzeichnis der
TYPO3-Installation, per `--output-dir` überschreibbar), alle Import-Commands
akzeptieren auch einen reinen Dateinamen ohne Pfad und suchen ihn dort.

| Command | Wirkung |
| --- | --- |
| `maintenance:database-export` | Exportiert die komplette Datenbank als datierten, komprimierten `mysqldump` |
| `maintenance:database-import <datei>` | Importiert einen damit erzeugten Dump (Tabelle für Tabelle, kein `DROP DATABASE`) |
| `maintenance:folder-export <name>` | Packt ein konfiguriertes Verzeichnis als `<name>-folder-<Datum>.tar.gz`; `--list` zeigt die konfigurierten Namen samt Pfad |
| `maintenance:folder-import <name> <datei>` | Spielt ein solches Archiv wieder dorthin ein |
| `maintenance:fileadmin-export` | Packt `fileadmin/` (Redakteurs-Uploads) als `.tar.gz` |
| `maintenance:fileadmin-import <datei>` | Spielt ein solches Archiv wieder nach `fileadmin/` ein |
| `maintenance:import-folder-export` / `-import <datei>` | Aliase für `folder-export`/`folder-import` mit dem Namen `import`. Bleiben erhalten, weil sie in Betriebsabläufen verankert sind |

Alle Import-Commands fragen vor dem Schreiben nach (`--yes` überspringt das) und
**löschen vorab nichts**: Gleichnamige Dateien aus dem Archiv werden
überschrieben, bereits vorhandene Dateien außerhalb des Archivs bleiben
unangetastet. Das ist bewusst konservativ, weil die Daten in der Regel nicht
reproduzierbar sind.

`maintenance:database-export`/`-import` rufen `mysqldump` bzw. `mysql` auf. Beide
müssen auf dem Zielsystem im `PATH` liegen; die Zugangsdaten kommen über
TYPO3s `ConnectionPool` und werden in eine temporäre `--defaults-extra-file`
(Modus 0600) geschrieben, damit das Passwort nicht als Klartext-Argument in
`ps aux` erscheint.

### Ausführung in Docker-Setups

Die Commands müssen dort laufen, wo die Datenbank erreichbar ist — bei einem
Docker-Setup also **im PHP-Container**, nicht auf dem Host:

```bash
docker compose exec <php-service> php vendor/bin/typo3 maintenance:database-export
```

Ist die Datenbank als Docker-interner Hostname konfiguriert (typisch
`db:3306`), scheitert ein Aufruf vom Host mit `Operation timed out`, weil der
Name außerhalb des Docker-Netzwerks nicht auflösbar ist. Auf Produktions- und
Testsystemen ohne Docker entfällt das.

## Konfiguration

Welche Verzeichnisse `folder-export`/`folder-import` anfassen dürfen, legt das
einbindende Projekt fest — in `config/system/settings.php`:

```php
'EXTENSIONS' => [
    'vd_maintenance' => [
        'directories' => 'import:import, scans:var/scans',
    ],
],
```

Kommagetrennte `name:pfad`-Paare; die Kurzform `import` entspricht
`import:import`. Jeder Pfad wird relativ zum Projektverzeichnis
(`Environment::getProjectPath()`) aufgelöst und muss darin liegen — ein
absoluter oder per `..` herausführender Pfad wird mit einer klaren Meldung
abgewiesen, statt an unerwarteter Stelle zu schreiben. Der Name darf nur
`A-Z a-z 0-9 - _` enthalten, weil er in den Archiv-Dateinamen eingeht.

Ohne Konfiguration bleiben `database-*` und `fileadmin-*` voll funktionsfähig;
`maintenance:folder-export --list` meldet dann, dass nichts konfiguriert ist.
Die beiden `import-folder-*`-Aliase arbeiten auch dann weiter und greifen auf
`<projekt>/import` zurück.

## Tests

```bash
composer install
composer test:unit
composer test:functional
```

Die Functional-Tests laufen gegen SQLite und brauchen keine Datenbank.

## Lizenz

GPL-2.0-or-later, siehe [LICENSE](LICENSE).
