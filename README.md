# vd_maintenance

Projektunabhängige Backup/Restore-Commands (Datenbank, `fileadmin/`, frei konfigurierbare Verzeichnisse) — leben hier statt in `vd_budget`/`vd_weather`, da sie z. B. auch TYPO3-Core-Tabellen betreffen und zu keiner der beiden fachlichen Extensions gehören.

## Commands

Alle Export-Commands schreiben nach `backup/` (Geschwisterverzeichnis der TYPO3-Installation), alle Import-Commands akzeptieren dort auch einen reinen Dateinamen ohne Pfad (automatischer Fallback auf `backup/`).

- `maintenance:database-export` — exportiert die komplette Datenbank als datierten, komprimierten `mysqldump`.
- `maintenance:database-import <datei>` — importiert einen damit erzeugten Dump (Tabelle für Tabelle, kein `DROP DATABASE`).
- `maintenance:folder-export <name>` — packt ein konfiguriertes Verzeichnis als `<name>-folder-<Datum>.tar.gz`; `--list` zeigt die konfigurierten Namen samt Pfad.
- `maintenance:folder-import <name> <datei>` — spielt ein solches Archiv wieder dorthin ein (überschreibt nur gleichnamige Dateien, löscht nichts vorab).
- `maintenance:import-folder-export` / `-import <datei>` — Aliase für die beiden obigen mit dem Namen `import`. Bleiben erhalten, weil sie in Betriebsabläufen verankert sind.
- `maintenance:fileadmin-export` — packt `webapp/public/fileadmin/` (Redakteurs-Uploads) als `.tar.gz`.
- `maintenance:fileadmin-import <datei>` — spielt ein solches Archiv wieder nach `webapp/public/fileadmin/` ein (gleiches Verhalten wie oben).

**Lokal (Docker) müssen alle Commands im `web`-Container laufen**, nicht direkt auf dem Host:

```bash
docker compose exec web php vendor/bin/typo3 maintenance:database-export
```

Die lokale Datenbank ist als `db:3306` (Docker-interner Hostname) konfiguriert — vom Host aus direkt per `vendor/bin/typo3` ausgeführt, schlägt die Verbindung mit `Operation timed out` fehl, da `db` außerhalb des Docker-Netzwerks nicht auflösbar ist. Auf Produktion/Test entfällt dieses Thema, da dort kein Docker im Spiel ist und `vendor/bin/typo3` direkt auf dem Hosting-Server läuft (genau wie bei `budget:import-pdf` & Co.).

Details und Hintergrund siehe [Planung: Backup/Restore](../../../docs/09-backup-restore/README.md).

## Konfiguration

Welche Verzeichnisse `folder-export`/`folder-import` anfassen dürfen, legt das einbindende Projekt fest — in `config/system/settings.php`:

```php
'EXTENSIONS' => [
    'vd_maintenance' => [
        'directories' => 'import:import, scans:var/scans',
    ],
],
```

Kommagetrennte `name:pfad`-Paare. Die Kurzform `import` entspricht `import:import`. Jeder Pfad wird relativ zum Projektverzeichnis (`Environment::getProjectPath()`) aufgelöst und muss darin liegen; ein absoluter oder herausführender Pfad wird mit einer klaren Meldung abgewiesen, statt an unerwarteter Stelle zu schreiben.

Ohne Konfiguration bleiben `database-*` und `fileadmin-*` voll funktionsfähig; `folder-export --list` meldet dann, dass nichts konfiguriert ist. Die beiden `import-folder-*`-Aliase arbeiten auch dann weiter und greifen auf `<projekt>/import` zurück.

## Wiederverwendung in anderen Projekten

Die Extension kennt kein konkretes Projekt mehr. Der Weg in ein eigenes Repository und die Einbindung per Composer-VCS-Repository ist in [Planung 19](../../../docs/0-planning/19-vd-maintenance-paket/README.md) beschrieben (Phasen 2–4, noch nicht umgesetzt).
