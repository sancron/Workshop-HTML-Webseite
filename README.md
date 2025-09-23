# Workshop-HTML-Webseite

Machen voll Krasse Webseite zum Laden von voll krasse HTML

## Admin-Zugang konfigurieren

Der Admin-Login verwendet ein gehashtes Passwort, das aus Sicherheitsgründen nicht im Quellcode liegt. Richte vor dem Deployment eine Umgebungskonfiguration ein:

1. **Passwort-Hash erzeugen**
   ```bash
   php -r 'echo password_hash("<dein-passwort>", PASSWORD_DEFAULT), PHP_EOL;'
   ```
   Kopiere den ausgegebenen Hash.

2. **Konfigurationsdatei vorbereiten**
   - Kopiere `config/credentials.example.php` an einen Ort außerhalb des öffentlichen Document-Roots (z. B. einen Ordner `config/` eine Ebene über deiner `html`/`public`-Struktur).
   - Ersetze den Platzhalterwert von `ADMIN_PASSWORD_HASH` durch deinen Hash und speichere die Datei als `credentials.php`.
   - Setze die Dateirechte restriktiv (z. B. `chmod 600 credentials.php`), sodass nur der PHP-Prozess lesen darf.

3. **Anwendung auf die Konfigurationsdatei verweisen**
   - Standard: Lege die Datei als `../config/credentials.php` (eine Ebene oberhalb des Document-Roots) ab – die Anwendung findet sie automatisch.
   - Alternative: Hinterlege im Hosting-Panel die Umgebungsvariable `CREDENTIALS_FILE` oder `$_SERVER['CREDENTIALS_FILE']` mit dem absoluten Pfad zu deiner Datei.
   - Falls dein Hosting-Panel direkte Environment-Variablen unterstützt, kannst du stattdessen `ADMIN_PASSWORD_HASH` hinterlegen – die Anwendung nutzt diesen Wert bevorzugt.

Der Login vergleicht das eingegebene Passwort mit `password_verify` gegen diesen Hash. Stelle sicher, dass die Konfiguration vor dem ersten Deployment vorhanden ist.

### Beispiel: All-Inkl Shared Hosting

1. Melde dich via SFTP an und lege außerhalb von `html/` einen Ordner `secure-config` an, z. B. `/www/htdocs/w0123456/secure-config`.
2. Lade `credentials.php` (basierend auf der Beispiel-Datei) in diesen Ordner hoch und setze die Rechte auf `600`.
3. Öffne im KAS (Kundenadministrationssystem) unter *Domain -> Einstellungen* die Umgebungsvariablen und trage `CREDENTIALS_FILE=/www/htdocs/w0123456/secure-config/credentials.php` ein.
4. Leere ggf. den OPCache oder starte PHP-FPM neu, damit die neue Variable greift.

## Fehlermeldungen für lokale Entwicklung aktivieren

Im Produktivbetrieb bleiben PHP-Warnungen und -Notices verborgen, damit keine Serverdetails nach außen gelangen. Für die lokale Entwicklung kannst du die Ausgabe detaillierter Fehlermeldungen aktivieren, indem du in deiner nicht öffentlich zugänglichen Konfiguration (z. B. `.env`, Hosting-Panel oder `config/credentials.php`) die Variable `APP_DEBUG=1` setzt. Entferne oder setze den Wert auf `0`, bevor du die Anwendung auf einen geteilten Host hochlädst.
