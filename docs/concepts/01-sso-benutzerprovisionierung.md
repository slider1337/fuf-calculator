# Konzept 1: SSO und Benutzerprovisionierung

**Status:** Entwurf, Recherche vom 22.09.2026. Keine Implementierung.

## Befund im Projekt

Die Anwendung nutzt lokale E-Mail/Passwort-Konten, Einladungslinks, PHP-Sessions und die globalen Rollen `admin`/`user`. Ein externer Identitätsanbieter und eine Zuordnung externer Konten existieren nicht. Einstiegspunkte: [AuthService](../../src/Application/AuthService.php), [User](../../src/Domain/User/User.php), [Session](../../src/Infrastructure/Http/Auth/Session.php), [AuthController](../../src/Infrastructure/Http/Controller/AuthController.php), [Benutzermigration](../../migrations/20260101000003_add_users_and_auth_tokens.php).

## Anbieterprüfung

| Anbieter | Anmeldung am Kalkulator | Provisionierung | Aussagekraft |
| --- | --- | --- | --- |
| **easyVerein** | Ja, nach Erweiterung des Kalkulators als OIDC-Client. easyVerein dokumentiert den Identity Provider für eigene Anwendungen, Authorization Code Flow, vertrauliche Clients, PKCE und RS256. | Erstlogin nach Einladung (JIT); optional Mitgliederdaten-Abgleich über die separate easyVerein-API, deren Details im Vereinsaccount geprüft werden müssen. | Dokumentierte Unterstützung; Konfiguration und verfügbare Identitätsmerkmale im konkreten Tarif/Account prüfen. |
| **Google** | Ja, Sign in with Google/OIDC. | Erstlogin nach Einladung (JIT); Google liefert Identität, keine FUF-Reiserollen. | Gut dokumentiert. |
| **ClubDesk** | Derzeit **nicht verlässlich zusagbar**: keine öffentlich belegte ClubDesk-Funktion als OIDC/SAML-Identitätsanbieter für Drittanwendungen gefunden. | Dokumentierter Kontakte-CSV-Export mit ClubDesk-ID erlaubt einen kontrollierten Import von Stammdaten; keine Echtzeit-Provisionierung behaupten. | Anbieter nach privater API/SSO und Vertragsbedingungen fragen. |
| **Proton Mail/Proton-Konto** | Derzeit **nicht verlässlich zusagbar**: keine öffentliche allgemeine Proton-OIDC-Anmeldung für beliebige Drittanwendungen belegt. Protons Business-SSO-Doku beschreibt vor allem die Gegenrichtung: Anmeldung **bei Proton** mittels externem IdP. | Eine Proton-Mailadresse kann über Einladungslink mit lokalem Konto genutzt werden; die Adresse allein bestätigt keine Proton-Identität. | Nur nach bestätigtem Anbieterprogramm neu bewerten. |

## Zielverhalten

1. Admin lädt eine E-Mail-Adresse ein und kann optional erlaubte Login-Provider vorgeben. Bis zur Annahme gibt es **keinen Reisezugriff**.
2. Nach OIDC-Anmeldung validiert der Server Aussteller, Zielgruppe, Signatur, Ablauf und Nonce/State; er verknüpft `(provider, subject)` mit einem internen Benutzer. E-Mail dient der Kontaktaufnahme, **nicht** als dauerhaftes Identitätsmerkmal oder automatischer Schlüssel zum Verbinden bestehender Konten.
3. Bestehende Konten werden nach erneutem Nachweis der lokalen Anmeldung oder bestätigtem Einladungsvorgang verbunden. Bei E-Mail-Kollision: manuelle Klärung, niemals stille Zusammenführung.
4. Die lokale Sitzung und die lokalen Freigaben bleiben maßgeblich. Bei Sperrung entzieht FUF den Zugang unabhängig vom Provider. Bestehende Passwortanmeldung zunächst als Rückfallweg erhalten; Adminzugang absichern.
5. Mitgliedschaftsstatus und Gruppen aus einem Vereinssystem sind **optional** zu synchronisieren; sie vergeben ohne explizite lokale Regel keine Orga- oder Abrechnungsrechte. Entzug, Synchronisationsintervall und Konflikte sind festzulegen.

### Kleine Login-Skizze

| FUF – Anmelden |
| --- |
| [Mit easyVerein anmelden] |
| [Mit Google anmelden] |
| E-Mail · Passwort · [Anmelden] |
| Einladung erhalten? Zugang verbinden |

## Festgelegter Einsatz und Migrationspfad

**Heute nutzt der Verein ClubDesk; ein Wechsel zu easyVerein ist geplant.** Für die Übergangszeit bleiben die vorhandenen lokalen Einladungen nutzbar. Ein kontrollierter ClubDesk-CSV-Import kann Kontaktdaten vorbefüllen, stellt aber kein SSO dar. Nach dem Umzug kann easyVerein die primäre externe Anmeldung werden; Google ist der zusätzliche verbreitete Login-Weg für Personen mit Google-Konto. Proton-Mailadressen können weiterhin normale Einladungen empfangen.

Der Login allein gibt **keine** Reise frei. Bereits vorhandene lokale Konten und zugewiesene Reisen behalten beim Providerwechsel ihre interne Benutzer-ID; eine bewusste Kontoverknüpfung verhindert doppelte Personen. Der Prozess muss auch für Nichtmitglieder funktionieren, die nur für eine Reise Belege einreichen.

## Erweiterung für die FuF-Website und Veranstaltungsanmeldung

WordPress wird neben dem Kalkulator ein eigener OIDC-Client von easyVerein. Beide Anwendungen erhalten getrennte Client-IDs, Redirect-URIs und lokale Sitzungen; eine zentral bestätigte easyVerein-Identität wird jeweils über `(issuer, subject)` gebunden. Der WordPress-Login wird vor dem [eigenen Familienanmeldeplugin](08-familienanmeldung-wordpress.md) umgesetzt. Öffentliche [Veranstaltungsseiten](07-veranstaltungen-easyverein-clubdesk.md) bleiben ohne Anmeldung erreichbar. Gäste einer Gruppenanmeldung brauchen kein eigenes Konto. Vor Freigabe ist im konkreten Vereinsaccount zu prüfen, ob auch Nichtmitglieder als Kontaktperson ein easyVerein-Konto erhalten können und welche Familienbeziehungen über API/Claims tatsächlich verfügbar sind. Keine Familienzuordnung allein aus E-Mail-Adresse oder Nachname ableiten.

MFA/WebAuthn ist als [eigenes Konzept](06-mfa-webauthn.md) beschrieben.

## Vor Umsetzung klären

- Welche easyVerein-Scopes/Claims, Tarife und API-Rechte sind beim geplanten Umzug in eurem konkreten Account vorhanden? API-Schlüssel-Lebensdauer/Rotation beachten.
- Welche ClubDesk-Datensätze sollen beim Wechsel einmalig abgeglichen werden? Nichtmitglieder können per Einladung unabhängig vom Vereinssystem freigegeben werden.
- Bestehende Benutzer bei doppelten E-Mails und ausgeschiedene Vereinsmitglieder behandeln.

## Quellen

- [easyVerein: Identity Provider](https://hilfe.easyverein.com/en/articles/2746882)
- [easyVerein: API](https://hilfe.easyverein.com/en/articles/360898)
- [Google: OIDC](https://developers.google.com/identity/openid-connect/openid-connect)
- [Google: Sign in best practices](https://developers.google.com/identity/siwg/best-practices)
- [ClubDesk: Kontakte exportieren/importieren](https://support.clubdesk.com/support/solutions/articles/13000100665)
- [Proton: Business-SSO für Proton Pass](https://proton.me/support/pass-set-up-sso)
