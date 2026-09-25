# Konzept 6: MFA, WebAuthn und Passkeys

**Status:** Entwurf, 22.09.2026. Keine Implementierung. Eigenständiges Konzept für die Kontosicherheit.

## Ziel und Einordnung

FUF soll lokale Konten mit einem zweiten Faktor absichern und optional eine Anmeldung mit **Passkey** anbieten. WebAuthn ist der technische Standard für kryptografische Zugangsdaten. Bei lokaler Passwortanmeldung kann ein WebAuthn-Schlüssel als **zweiter Faktor** dienen. Ein Passkey mit Nutzerverifikation (z. B. Geräte-PIN/Biometrie) kann auch eine eigenständige passwortlose Anmeldung ermöglichen. Ein TOTP-Code aus einer Authenticator-App ist als alternative zweite Methode und für die Übergangszeit denkbar. Diese drei Anmeldewege dürfen nicht als ein und dieselbe Sicherheitsstufe angezeigt werden.

Externe Anmeldung über easyVerein oder Google bleibt das [SSO-Konzept](01-sso-benutzerprovisionierung.md). Dabei führt zunächst der jeweilige Provider die Anmeldung durch. Ob dort MFA verwendet wurde und FUF dies verlässlich erkennen bzw. für sensible Aktionen verlangen kann, ist je Provider/Tarif und verfügbarer Aussage über die Authentisierung gesondert zu prüfen; ein FUF-Passkey entsteht nicht automatisch durch „Mit Google anmelden“.

## Bestehender Stand im Repo

[AuthService](../../src/Application/AuthService.php) prüft E-Mail/Passwort und setzt nach Erfolg über [Session](../../src/Infrastructure/Http/Auth/Session.php) unmittelbar eine volle Sitzung. [User](../../src/Domain/User/User.php) und die [Benutzermigration](../../migrations/20260101000003_add_users_and_auth_tokens.php) enthalten noch keine WebAuthn-Credentials, TOTP-Geheimnisse oder Wiederherstellungscodes. Der bestehende Einladungs-/Passwort-Reset-Ablauf ist deshalb im MFA-Design ausdrücklich mit zu behandeln.

## Empfohlener Funktionsumfang

| Zugang | Vorschlag | Was FUF prüft |
| --- | --- | --- |
| Lokales Passwort | Weiter nutzbar; danach zweiter Faktor, falls aktiviert. Für Orga/Admin MFA verpflichtend. | Passwort, Rate Limit, danach WebAuthn oder TOTP; Sitzung bis dahin nur „MFA ausstehend“. |
| WebAuthn als MFA | Bevorzugte zweite Methode für lokale Konten. | Einmalige Challenge, RP-ID/Origin, Signatur, Credential-ID, Nutzerverifikation. |
| Passkey-Anmeldung | Optional für alle, insbesondere auf Mobilgeräten. | Vollständig verifizierte WebAuthn-Antwort, Nutzerverifikation und Konto-Zuordnung. |
| TOTP | Alternative zur zweiten Methode; etwas einfacher verfügbar, aber anfälliger für Phishing. | Einmalcode nach RFC 6238 mit begrenztem Zeitfenster, Rate Limit und sicher verwahrtem Geheimnis. |
| Google/easyVerein SSO | Anmeldung über Provider; Reiserechte bleiben lokal. | Tokenprüfung gemäß SSO-Konzept; für sensibles FUF-Recht ggf. frische Provider-Authentisierung oder lokale WebAuthn-Bestätigung, nur wenn zuverlässig umsetzbar. |

## Nutzerablauf

1. Angemeldete Person öffnet „Konto & Sicherheit“ und registriert einen oder mehrere Passkeys/Sicherheitsschlüssel. Für die Registrierung oder Entfernung vorhandener Faktoren ist eine frische Anmeldung erforderlich.
2. Beim Passwort-Login bleibt die Sitzung bis zur Faktorprüfung eingeschränkt. Erst danach dürfen Reisen und API-Daten geladen werden. Für Passkey-Login ist die Passwortstufe nicht erforderlich.
3. Orga und globale Admins müssen vor der Vergabe entsprechender Rechte eine geeignete MFA-Methode eingerichtet haben; Uploadende können zunächst freiwillig beginnen. Eine spätere Pflicht für alle ist möglich.
4. Beim Verlust eines Geräts helfen ein zweiter registrierter Passkey oder einmalige Wiederherstellungscodes. Codes werden nur einmal angezeigt, serverseitig nur gehasht gespeichert. Eine administrative Rücksetzung benötigt einen dokumentierten Identitätsnachweis, zwei Personen oder eine gleichwertige Kontrolle und Audit.
5. Passwort-Reset, E-Mail-Wechsel und Kontoverknüpfung setzen vorhandene MFA nicht stillschweigend außer Kraft. Nach Reset sind bestehende Sitzungen zu widerrufen und der zweite Faktor weiterhin nötig.

### Kleine Konto-Skizze

| Konto & Sicherheit |
| --- |
| Passkeys: Pixel 8 Pro · Sicherheitsschlüssel [Weiteren hinzufügen] |
| Authenticator-App: Aktiv [Verwalten] |
| Wiederherstellungscodes: 7 übrig [Neue Codes erstellen] |
| Anmeldung: Google verbunden · easyVerein später verbinden |
| [Passkey-Anmeldung testen] |

## Technische Leitplanken und Daten

Credential-Datensatz: Benutzer-ID, Credential-ID (eindeutig), öffentlicher Schlüssel, Zähler/Metadaten, Anzeigename, erstellt/zuletzt genutzt/widerrufen. TOTP-Geheimnisse geschützt speichern; Wiederherstellungscodes gehasht und nach Einmalnutzung sperren. Registrierungs- und Login-Challenges kurzlebig, an Sitzung und Zweck gebunden. Prüfung serverseitig nach WebAuthn-Spezifikation; HTTPS, korrekte RP-ID/Origin sowie Nutzerverifikation zwingend bei passwortloser Anmeldung. Sicherheitsschlüssel und synchronisierte Passkeys zulassen, mehrere Geräte unterstützen; Wiederherstellung nicht über bloßen Zugang zur E-Mail-Adresse ermöglichen.

FUF muss unterscheiden zwischen **Login gelungen**, **zweiter Faktor ausstehend** und **vollständig authentisiert**. Vor Abschluss der MFA dürfen auch direkte API-Anfragen keine Reisedaten liefern. Sensible Aktionen wie Faktorentfernung, neue SSO-Verknüpfung, Rollenvergabe und Abschluss/Wiedereröffnung einer Reise erfordern frische Authentisierung. Nach Sperrung eines Kontos ist der Zugang über sämtliche Methoden entzogen.

## Abnahmekriterien

- Passwort allein öffnet für MFA-pflichtige Konten keine Reisen oder API-Daten.
- Ein zweiter Passkey kann registriert und ein einzelner verlorener Schlüssel entfernt werden; ein Benutzer wird dabei nicht versehentlich ausgesperrt.
- Ein Wiederherstellungscode funktioniert einmal; ein verwendeter Code funktioniert nie wieder.
- Ein fremder Origin, wiederverwendete Challenge, falsche Signatur oder abgelaufene Challenge wird abgewiesen.
- Durch Passwort-Reset oder Anmeldung mit einem anderen Provider werden MFA-Pflicht und Reiserechte nicht umgangen.
- Orga- und Admin-Rechte setzen die vorgesehene starke Anmeldung voraus; Abrufe nur lesender Daten bleiben von der fachlichen Rollenprüfung abhängig.

## Umsetzungsreihenfolge

Zuerst vollständig eingeschränkten Login-Zwischenzustand und Wiederherstellung konzipieren, dann WebAuthn-Registrierung und zweiten Faktor, danach Passkey-Login und optional TOTP. Die Rechte aus [Konzept 2](02-berechtigungen-gruppen.md) bleiben unabhängig vom Anmeldeverfahren.

## Quellen

- [W3C: Web Authentication Level 3](https://www.w3.org/TR/webauthn/)
- [NIST: Authentication and Authenticator Management](https://pages.nist.gov/800-63-4/sp800-63b.html)
- [RFC 6238: TOTP](https://www.rfc-editor.org/rfc/rfc6238.html)
- [Google: Passkeys für Webanwendungen](https://developers.google.com/identity/passkeys/developer-guides)
