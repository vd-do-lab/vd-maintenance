# vd_maintenance

Projektweite Backup/Restore-Commands für `app.do` (Datenbank, `webapp/import/`, `webapp/public/fileadmin/`) — leben hier statt in `vd_budget`/`vd_weather`, da sie z. B. auch TYPO3-Core-Tabellen betreffen und zu keiner der beiden fachlichen Extensions gehören.

## Commands

Alle Export-Commands schreiben nach `backup/` (Geschwisterverzeichnis der TYPO3-Installation), alle Import-Commands akzeptieren dort auch einen reinen Dateinamen ohne Pfad (automatischer Fallback auf `backup/`).

- `maintenance:database-export` — exportiert die komplette Datenbank als datierten, komprimierten `mysqldump`.
- `maintenance:database-import <datei>` — importiert einen damit erzeugten Dump (Tabelle für Tabelle, kein `DROP DATABASE`).
- `maintenance:import-folder-export` — packt `webapp/import/` (vd_budgets Kontoauszug-Rohdaten) als `.tar.gz`.
- `maintenance:import-folder-import <datei>` — spielt ein solches Archiv wieder nach `webapp/import/` ein (überschreibt nur gleichnamige Dateien, löscht nichts vorab).
- `maintenance:fileadmin-export` — packt `webapp/public/fileadmin/` (Redakteurs-Uploads) als `.tar.gz`.
- `maintenance:fileadmin-import <datei>` — spielt ein solches Archiv wieder nach `webapp/public/fileadmin/` ein (gleiches Verhalten wie oben).

**Lokal (Docker) müssen alle Commands im `web`-Container laufen**, nicht direkt auf dem Host:

```bash
docker compose exec web php vendor/bin/typo3 maintenance:database-export
```

Die lokale Datenbank ist als `db:3306` (Docker-interner Hostname) konfiguriert — vom Host aus direkt per `vendor/bin/typo3` ausgeführt, schlägt die Verbindung mit `Operation timed out` fehl, da `db` außerhalb des Docker-Netzwerks nicht auflösbar ist. Auf Produktion/Test entfällt dieses Thema, da dort kein Docker im Spiel ist und `vendor/bin/typo3` direkt auf dem Hosting-Server läuft (genau wie bei `budget:import-pdf` & Co.).

Details und Hintergrund siehe [Planung: Backup/Restore](../../../docs/09-backup-restore/README.md).

Diese Extension ist bewusst **nicht** für eine eigenständige Extraktion/Veröffentlichung vorgesehen (anders als `vd_budget`/`vd_weather`, siehe [Paket-Extraktions-Planung](../../../docs/08-package-extraktion/README.md)) — sie existiert ausschließlich für dieses Projekt.
