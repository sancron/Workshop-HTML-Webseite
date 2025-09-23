# Workshop-HTML-Webseite

Machen voll Krasse Webseite zum Laden von voll krasse HTML

## Admin-Zugang konfigurieren

Der Admin-Login verwendet ein gehashtes Passwort, das aus Sicherheitsgründen nicht im Quellcode liegt. Richte vor dem Deployment eine Umgebungskonfiguration ein:

1. **Passwort-Hash erzeugen**
   ```bash
   php -r 'echo password_hash("<dein-passwort>", PASSWORD_DEFAULT), PHP_EOL;'
   ```
   Kopiere den ausgegebenen Hash.

2. **Umgebungsvariable setzen**
   - Variante `.env`: Kopiere `.env.example` nach `.env` und ersetze den Wert von `ADMIN_PASSWORD_HASH` durch deinen Hash.
   - Variante Deployment-Umgebung: Setze die Variable direkt im Webserver/Hosting (z. B. `export ADMIN_PASSWORD_HASH="<hash>"`).

Der Login vergleicht das eingegebene Passwort mit `password_verify` gegen diesen Hash. Stelle sicher, dass die Variable auf allen Zielsystemen gesetzt ist, bevor du die Anwendung startest.
