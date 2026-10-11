# Zusätzliche easyVerein-Anmeldung

Die Anmeldung mit E-Mail/Passwort, Einladungen und Passwort-Reset bleiben erhalten.
Der zusätzliche Button erscheint erst bei vollständiger Serverkonfiguration.
Es werden keine neuen Kalkulator-Konten automatisch angelegt und keine E-Mail-Adressen
zum automatischen Zusammenführen von Konten verwendet. Zugriffsrechte bleiben die
bestehenden Kalkulator-Rollen. Die WordPress-Anmeldung ist hiervon unabhängig.

## Aktivierung

1. In https://easyverein.com/app/settings/identityprovider/ eine eigene Anwendung
   „FuF Kalkulator“ erstellen: **Confidential**, OpenID Connect **RSA256**, PKCE S256.
2. Redirect URL exakt: `https://fuf-calc.benna.me/auth/easyverein/callback`.
   Allowed origin: `https://fuf-calc.benna.me`. Scope: `openid`.
   Es werden keine Mitgliederdaten/API-Schlüssel benötigt, nur die signierte Identität.
3. Client-ID als GitHub Actions **Variable** `EASYVEREIN_CLIENT_ID` setzen und
   Client Secret als **Secret** `EASYVEREIN_CLIENT_SECRET`. Das Secret niemals committen.
   `APP_URL` muss `https://fuf-calc.benna.me` sein.
4. Änderung deployen bzw. bestehenden Deploy-Workflow ausführen. Die neue Migration
   legt die Identitätszuordnung an. Die Anwendung läuft ohne die neuen Werte weiter.
5. Mit dem bisherigen Kalkulator-Konto anmelden, im Kopfbereich
   **easyVerein verknüpfen** öffnen, Kalkulator-Passwort erneut eingeben und
   bei easyVerein anmelden. Danach abmelden und **Mit easyVerein anmelden** verwenden.

Für andere HTTPS-Domains müssen APP_URL, Redirect URL und Allowed origin übereinstimmen.
Lokal werden die Werte als echte Umgebungsvariablen gesetzt; PHP lädt keine .env-Datei.

## Verhalten und Prüfung

- Einmalige explizite Verknüpfung nach erneutem lokalen Passwortnachweis; CSRF-Schutz.
- Stabile Zuordnung über Issuer + signiertes Subject, nicht über E-Mail.
- Keine Rollenänderung, keine öffentliche Registrierung, keine Benachrichtigungs-Mails.
- Authorization Code Flow, PKCE S256, State/Nonce sowie Signatur-, Issuer-, Audience-
  und Ablaufprüfung durch jumbojett/openid-connect-php; TLS-Prüfung bleibt aktiv.
- Rückruf ist nach zehn Minuten oder nach einmaligem Verbrauch ungültig.
- Abbruch/Fehler lassen den bisherigen Anmeldeweg verfügbar; Tokens/Secrets werden
  weder dauerhaft gespeichert noch in Fehlermeldungen ausgegeben.
- Ein easyVerein-Konto kann nicht mehreren Kalkulator-Konten zugewiesen werden.
- Gelöschte Kalkulator-Konten verlieren auch ihre Verknüpfung.
- Abmelden beendet die lokale Kalkulator-Sitzung, nicht die easyVerein-Sitzung.

Live-Abnahme nach Konfiguration: beide Anmeldewege, Admin-Rechte, Abbruch,
Verknüpfung mit falschem Passwort und doppelter Verknüpfungsversuch prüfen.
Ohne Client-ID/Secret ist kein echter easyVerein-Login-Test möglich.

Quelle: https://hilfe.easyverein.com/en/articles/2746882

## Implementierungsprüfung (2026-10-11)

- Gesamte PHPUnit-Suite lokal auf PHP 8.5.11: **253 Tests, 726 Assertions,
  keine Fehler oder fehlgeschlagenen Tests**.
- PHP-Syntax und Composer-Konfiguration geprüft.
- Zwölf neue Tests prüfen Kontoverknüpfung, Passwort-Fallback, Rechte,
  Identitätskonflikte, Kontolöschung, CSRF, Ablauf/State, Sessionwechsel sowie
  verpflichtende OIDC-Claims und autorisierte Empfänger.
- Provider-Antworten sind im Integrationstest simuliert; ein echter Login und
  die Prüfung auf der CI-Zielversion PHP 8.4 stehen noch aus.
- Composer-Audit meldet vorhandene Hinweise bei sechs bereits verwendeten
  Paketen; die vier neu hinzugefügten Pakete sind hiervon nicht betroffen.
