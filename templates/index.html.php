<!doctype html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>FUF Gruppenreise Kalkulator</title>
    <link rel="stylesheet" href="/assets/vendor/bootstrap/css/bootstrap.min.css">
    <link rel="stylesheet" href="/assets/css/fuf.css">
</head>
<body>
<div class="app-shell">
    <header class="app-header">
        <div class="app-header-left">
            <!-- DESIGN: Auf dem Handy treten Zurueck-Pfeil und Reisename an die Stelle der
                 Wortmarke, sobald eine Reise offen ist. app.js setzt dazu body.is-trip-view. -->
            <a class="app-head-back" id="app-head-back-btn" href="/" aria-label="Zur&uuml;ck zur Reiseliste">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>
            </a>
            <a class="app-brand" href="/">
                <img src="/assets/img/fuflogo.png" alt="FuF Erding">
                <span>Gruppenreise-Kalkulator</span>
            </a>
            <span class="app-head-title" id="app-head-title"></span>
        </div>
        <!-- DESIGN: `Neue Reise` steht unter lg hier statt in einer Bottom-Bar. Regel 5
             zieht auf der Liste nicht: es gibt keine Kennzahl, und die Hauptaktion ist
             eine Reise zu oeffnen - das sind die Karten selbst. Eine Reise legt man
             selten an, eine feste Bar kostete dafuer auf jedem Listenbildschirm 64 px.
             In der Reiseansicht ist der Knopf weg, dort traegt die Bar Kennzahl und
             Speichern. Ab lg blendet .app-menu-btn beide Icons aus. -->
        <button class="app-menu-btn app-head-new" id="new-trip-head-btn" type="button" aria-label="Neue Reise" title="Neue Reise">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg>
        </button>
        <!-- DESIGN: Unter lg steckt die Navigation hinter diesem Icon; offcanvas-lg macht
             aus demselben Markup ab lg wieder die waagerechte Kopfleiste. -->
        <button class="app-menu-btn" id="app-menu-btn" type="button" data-bs-toggle="offcanvas" data-bs-target="#app-menu" aria-controls="app-menu" aria-label="Men&uuml;">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 6h18M3 12h18M3 18h18"/></svg>
        </button>
        <div class="app-menu offcanvas-lg offcanvas-end" id="app-menu" tabindex="-1" aria-labelledby="app-menu-title">
            <div class="offcanvas-header">
                <h2 class="offcanvas-title" id="app-menu-title">Men&uuml;</h2>
                <button class="btn-close btn-close-white" type="button" data-bs-dismiss="offcanvas" data-bs-target="#app-menu" aria-label="Men&uuml; schlie&szlig;en"></button>
            </div>
            <div class="offcanvas-body">
                <nav class="app-nav">
                    <a href="/" class="is-active">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M8 6h13M8 12h13M8 18h13M3 6h.01M3 12h.01M3 18h.01"/></svg>
                        Reisen
                    </a>
                    <button id="open-users-btn" type="button">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M16 21v-2a4 4 0 00-4-4H6a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M19 8v6M22 11h-6"/></svg>
                        Benutzer
                    </button>
                    <button id="open-settings-btn" type="button">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.7 1.7 0 00.3 1.8l.1.1a2 2 0 11-2.8 2.8l-.1-.1a1.7 1.7 0 00-1.8-.3 1.7 1.7 0 00-1 1.5V21a2 2 0 11-4 0v-.1a1.7 1.7 0 00-1.1-1.5 1.7 1.7 0 00-1.8.3l-.1.1a2 2 0 11-2.8-2.8l.1-.1a1.7 1.7 0 00.3-1.8 1.7 1.7 0 00-1.5-1H3a2 2 0 110-4h.1a1.7 1.7 0 001.5-1.1 1.7 1.7 0 00-.3-1.8l-.1-.1a2 2 0 112.8-2.8l.1.1a1.7 1.7 0 001.8.3H9a1.7 1.7 0 001-1.5V3a2 2 0 114 0v.1a1.7 1.7 0 001 1.5 1.7 1.7 0 001.8-.3l.1-.1a2 2 0 112.8 2.8l-.1.1a1.7 1.7 0 00-.3 1.8V9a1.7 1.7 0 001.5 1H21a2 2 0 110 4h-.1a1.7 1.7 0 00-1.5 1z"/></svg>
                        Globale Settings
                    </button>
                </nav>
                <button id="new-trip-btn" class="btn btn-a btn-hdr" type="button">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg>
                    Neue Reise
                </button>
                <div class="app-user">
                    <div class="app-avatar" id="current-user-avatar" aria-hidden="true"></div>
                    <div class="app-user-meta">
                        <span class="app-user-email" id="current-user-email"></span>
                        <span class="app-user-role d-none" id="current-user-role-badge">Admin</span>
                    </div>
                    <form method="post" action="/logout" class="m-0">
                        <button class="app-icon-link" type="submit" aria-label="Abmelden" title="Abmelden">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4M16 17l5-5-5-5M21 12H9"/></svg>
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </header>
    <div class="app-main">

    <!-- DESIGN: Unter lg sind die drei Modals Vollbild-Blaetter. `lg-down` und nicht
         `md-down` wie in RESPONSIVE.md: Menue und Kalkulationsblatt, die beiden anderen
         Vollbild-Ueberlagerungen, schalten bei 992 px (offcanvas-lg). Eine dritte Grenze
         bei 768 px haette ein Band erzeugt, in dem ein zentrierter Dialog neben einem
         Vollbild-Menue stuende. -->
    <div class="modal fade" id="users-modal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-fullscreen-lg-down">
            <div class="modal-content">
                <div class="modal-header">
                    <div class="modal-title-group">
                        <h2 class="modal-title">Benutzerverwaltung</h2>
                        <span class="help" id="users-modal-sub"></span>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Schliessen"></button>
                </div>
                <div class="modal-body">
                    <div class="modal-toolbar">
                        <div class="in in-search in-sm">
                            <span class="p">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="M21 21l-4.3-4.3"/></svg>
                            </span>
                            <input id="users-search" type="search" placeholder="Benutzer suchen &hellip;" aria-label="Benutzer suchen">
                        </div>
                        <button id="open-invite-btn" class="btn btn-p btn-s" type="button">
                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="2" y="4" width="20" height="16" rx="2"/><path d="M22 6l-10 7L2 6"/></svg>
                            Nutzer einladen
                        </button>
                    </div>
                    <div id="users-status" class="status-note d-none"></div>
                    <table class="tb">
                        <thead>
                        <tr>
                            <th>E-Mail</th>
                            <th>Rolle</th>
                            <th>Status</th>
                            <th class="r">Aktion</th>
                        </tr>
                        </thead>
                        <tbody id="users-table-body"></tbody>
                    </table>
                    <span class="help calc-hint">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 8h.01M11 12h1v4h1"/></svg>
                        Admins k&ouml;nnen Benutzer und globale Settings verwalten. Die Rolle wird derzeit nicht &uuml;ber die Oberfl&auml;che ge&auml;ndert.
                    </span>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-o" data-bs-dismiss="modal">Schlie&szlig;en</button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="invite-modal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-fullscreen-lg-down">
            <div class="modal-content">
                <div class="modal-header">
                    <div class="modal-title-group">
                        <h2 class="modal-title">Nutzer einladen</h2>
                        <span class="help">Die Person erh&auml;lt einen Link, um ein Passwort zu vergeben.</span>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Schliessen"></button>
                </div>
                <div class="modal-body">
                    <form id="invite-form">
                        <div class="f">
                            <label for="invite-email">E-Mail-Adresse</label>
                            <div class="in"><input id="invite-email" type="email" placeholder="name@beispiel.de" required></div>
                        </div>
                    </form>
                    <div id="invite-status" class="status-note d-none"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-o" data-bs-dismiss="modal">Schlie&szlig;en</button>
                    <button type="submit" form="invite-form" class="btn btn-p">Einladung senden</button>
                </div>
            </div>
        </div>
    </div>

    <section id="trip-list-section">
        <div class="page-head">
            <div class="page-head-text">
                <h1 class="fuf-display">Alle Reisen</h1>
                <span id="trip-list-summary" class="help"></span>
            </div>
            <div class="in in-search">
                <span class="p">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="M21 21l-4.3-4.3"/></svg>
                </span>
                <input id="trip-search" type="search" placeholder="Reise suchen &hellip;" aria-label="Reise suchen">
            </div>
        </div>

        <div id="trip-list-card" class="sec sec-cards">
            <table class="tb tb-trips">
                <thead>
                <tr>
                    <th class="tb-col-id">ID</th>
                    <th>Reise</th>
                    <th title="Sortierung: absteigend">Zeitraum <span class="tb-sort" aria-hidden="true">&darr;</span></th>
                    <th class="r">Teilnehmer</th>
                    <th class="r">&Uuml;berschuss / Defizit</th>
                    <th class="r tb-col-action">Aktion</th>
                </tr>
                </thead>
                <tbody id="trip-list-body"></tbody>
            </table>
            <div class="sec-f">
                <span class="help">Sortiert nach Startdatum, neueste zuerst</span>
                <span id="trip-list-count" class="help"></span>
            </div>
        </div>

        <div id="trip-list-empty" class="fuf-empty d-none">
            <div class="fuf-empty-icon">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg>
            </div>
            <div class="fuf-empty-text">
                <b>Neue Reise anlegen</b>
                <span class="help">Aufschlag, Vereinsgeb&uuml;hr und Kurabgabe werden aus den globalen Settings vorbelegt.</span>
            </div>
            <button id="trip-list-empty-create-btn" class="btn btn-p" type="button">Reise erstellen</button>
        </div>
    </section>

    <section id="trip-editor-section" class="d-none">
        <div class="trip-head" id="trip-head">
            <a class="trip-head-back" id="trip-head-back-btn" href="/" aria-label="Zur&uuml;ck zur Reiseliste" title="Alle Reisen">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>
            </a>
            <div class="trip-head-text">
                <nav class="bc" aria-label="Pfad">
                    <a id="back-to-list-btn" href="/">Alle Reisen</a>
                    <span class="bc-sep" aria-hidden="true">›</span>
                    <span id="trip-editor-breadcrumb">Reise</span>
                </nav>
                <div class="trip-head-title">
                    <h1 id="trip-editor-title">Reise</h1>
                    <span class="help" id="trip-editor-meta"></span>
                </div>
            </div>
            <div class="trip-head-right">
                <div class="trip-head-kpi">
                    <span class="help" id="trip-head-kpi-label">&Uuml;berschuss</span>
                    <b id="trip-head-kpi-value">&ndash;</b>
                </div>
                <ul class="nav nav-tabs trip-tabs" id="tripTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="tab-planung" data-bs-toggle="tab" data-bs-target="#panel-planung" type="button" role="tab" aria-controls="panel-planung" aria-selected="true">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 20h9M16.5 3.5a2 2 0 013 3L7 19l-4 1 1-4z"/></svg>
                        Planung
                        <span class="chip c-amber tab-chip d-none" id="tab-planung-count"></span>
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="tab-abrechnung" data-bs-toggle="tab" data-bs-target="#panel-abrechnung" type="button" role="tab" aria-controls="panel-abrechnung" aria-selected="false">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 3h14v18l-3-2-2 2-2-2-2 2-2-2-3 2z"/><path d="M9 8h6M9 12h6M9 16h4"/></svg>
                        Abrechnung
                        <span class="chip c-amber tab-chip d-none" id="tab-abrechnung-count"></span>
                    </button>
                </li>
                </ul>
                <button id="trip-save-btn-head" class="btn btn-p btn-s" type="submit" form="trip-form">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M19 21H5a2 2 0 01-2-2V5a2 2 0 012-2h11l5 5v11a2 2 0 01-2 2z"/><path d="M17 21v-8H7v8M7 3v5h8"/></svg>
                    <span id="trip-save-label-head">Speichern</span>
                </button>
            </div>
        </div>

        <!-- DESIGN: Auf dem Handy sind nur Kopfleiste und Umschalter sticky, die Meta-Zeile
             scrollt mit. Sie steht deshalb ausserhalb von .trip-head - im Kopf selbst sitzt
             sie auf dem Desktop neben der Ueberschrift und kann nicht an beiden Orten sein. -->
        <div class="trip-meta-m d-lg-none"><span class="help" id="trip-editor-meta-mobile"></span></div>

        <div class="tab-content" id="tripTabContent">
        <div class="tab-pane fade show active" id="panel-planung" role="tabpanel" aria-labelledby="tab-planung">
        <div class="pg">
        <div class="pg-main">
            <form id="trip-form" class="pg-form">
                <input id="tripFormId" type="hidden" name="tripFormId">

                <section class="sec" id="sec-eckdaten">
                    <div class="sec-h">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="var(--fuf-green-600)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>
                        <h2>Eckdaten</h2>
                        <span class="sum" id="sum-eckdaten"></span>
                        <button class="chev" type="button" data-bs-toggle="collapse" data-bs-target="#sec-eckdaten-body" aria-expanded="true" aria-controls="sec-eckdaten-body" aria-label="Eckdaten ein- oder ausklappen">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M18 15l-6-6-6 6"/></svg>
                        </button>
                    </div>
                    <div class="collapse show" id="sec-eckdaten-body">
                        <div class="sec-b">
                            <div class="fuf-grid fuf-grid-eck">
                                <div class="f">
                                    <label for="name">Reisename</label>
                                    <div class="in"><input id="name" name="name" type="text" required></div>
                                </div>
                                <div class="f">
                                    <label for="startDate">Anreise</label>
                                    <div class="in"><input id="startDate" name="startDate" type="date" required></div>
                                </div>
                                <div class="f">
                                    <label for="endDate">Abreise</label>
                                    <div class="in"><input id="endDate" name="endDate" type="date" required></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                <section class="sec" id="sec-aufschlaege">
                    <div class="sec-h">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="var(--fuf-amber-700)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M19 5L5 19"/><circle cx="6.5" cy="6.5" r="2.5"/><circle cx="17.5" cy="17.5" r="2.5"/></svg>
                        <h2>Aufschläge &amp; Abgaben</h2>
                        <span class="sum" id="sum-aufschlaege"></span>
                        <button class="chev" type="button" data-bs-toggle="collapse" data-bs-target="#sec-aufschlaege-body" aria-expanded="true" aria-controls="sec-aufschlaege-body" aria-label="Aufschläge und Abgaben ein- oder ausklappen">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M18 15l-6-6-6 6"/></svg>
                        </button>
                    </div>
                    <div class="collapse show" id="sec-aufschlaege-body">
                        <div class="sec-b">
                            <div class="fuf-grid fuf-grid-3">
                                <div class="f">
                                    <label for="markupPercent">Aufschlag</label>
                                    <div class="in"><input id="markupPercent" name="markupPercent" type="text" inputmode="decimal" required><span class="u">%</span></div>
                                    <span class="help">Puffer auf die Unterkunftskosten</span>
                                </div>
                                <div class="f">
                                    <label for="clubFeePercent">Vereinsgebühr</label>
                                    <div class="in"><input id="clubFeePercent" name="clubFeePercent" type="text" inputmode="decimal" required><span class="u">%</span></div>
                                    <span class="help">Anteil für den Verein</span>
                                </div>
                                <div class="f">
                                    <label for="distributionMethod">Verteilung</label>
                                    <div class="in">
                                        <select id="distributionMethod" name="distributionMethod" required>
                                            <option value="PER_PERSON">Per Person</option>
                                            <option value="PER_CATEGORY_UNITS">Per Kategorieeinheit</option>
                                        </select><span class="u"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 9l6 6 6-6"/></svg></span>
                                    </div>
                                    <span class="help">Wie Gemeinkosten umgelegt werden</span>
                                </div>
                            </div>
                            <div class="opt-row">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" role="switch" id="averageAdultPrice" name="averageAdultPrice">
                                    <label class="form-check-label" for="averageAdultPrice">
                                        <b>Einheitlicher Erwachsenenpreis</b>
                                        <span class="help">Durchschnitt aus Doppel- und Mehrbettzimmer, gewichtet nach Personenzahl</span>
                                    </label>
                                </div>
                            </div>
                            <div class="box-sand fuf-grid fuf-grid-spa">
                                <div class="box-sand-label">
                                    <b>Kurabgabe</b>
                                    <span class="help">an die Gemeinde</span>
                                </div>
                                <div class="f">
                                    <label for="spaTaxPerPerson">Pro Person &amp; Nacht</label>
                                    <div class="in"><input id="spaTaxPerPerson" name="spaTaxPerPerson" type="text" inputmode="decimal" required><span class="u">€</span></div>
                                </div>
                                <div class="f">
                                    <label for="spaTaxAgeThreshold">Pflichtig ab</label>
                                    <div class="in"><input id="spaTaxAgeThreshold" name="spaTaxAgeThreshold" type="text" inputmode="numeric" required><span class="u">Jahre</span></div>
                                </div>
                                <div class="f">
                                    <label for="spaTaxCount">Personen</label>
                                    <div class="in"><input id="spaTaxCount" name="spaTaxCount" type="text" inputmode="numeric" value="0" required><span class="u">Pers.</span></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                <section class="sec" id="sec-unterkunft">
                    <div class="sec-h">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="var(--fuf-green-600)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 18v-6a2 2 0 012-2h14a2 2 0 012 2v6M3 22v-4h18v4M5 10V6a2 2 0 012-2h10a2 2 0 012 2v4"/></svg>
                        <h2>Unterkunft &amp; Teilnehmer</h2>
                        <span class="sum" id="sum-unterkunft"></span>
                        <button class="chev" type="button" data-bs-toggle="collapse" data-bs-target="#sec-unterkunft-body" aria-expanded="true" aria-controls="sec-unterkunft-body" aria-label="Unterkunft und Teilnehmer ein- oder ausklappen">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M18 15l-6-6-6 6"/></svg>
                        </button>
                    </div>
                    <div class="collapse show" id="sec-unterkunft-body">
                        <div class="sec-b">
                            <div class="fuf-grid fuf-grid-room room-table">
                                <span class="col-head">Kategorie</span>
                                <span class="col-head">Anzahl</span>
                                <span class="col-head">Preis / Nacht</span>
                                <span class="col-head room-sum">Summe (<span id="room-sum-nights">&ndash;</span> Nächte)</span>

                                <div class="room-row">
                                    <div class="room-cat">
                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="var(--fuf-green-600)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="9" cy="7" r="3"/><circle cx="17" cy="8" r="2.5"/><path d="M3 20v-2a5 5 0 015-5h2a5 5 0 015 5v2M16 14h1a4 4 0 014 4v2"/></svg>
                                    <span class="room-cat-text"><b>Erwachsene</b><span class="help">Doppelzimmer</span></span>
                                </div>
                                <span class="room-lbl">Personen</span>
                                <div class="stepper">
                                    <button class="step" type="button" data-step="-1" data-step-target="adultDoubleCount" aria-label="Anzahl Erwachsene im Doppelzimmer verringern"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14"/></svg></button>
                                    <div class="in"><input id="adultDoubleCount" name="adultDoubleCount" type="text" inputmode="numeric" value="0" required aria-label="Anzahl Erwachsene im Doppelzimmer"><span class="u">Pers.</span></div>
                                    <button class="step" type="button" data-step="1" data-step-target="adultDoubleCount" aria-label="Anzahl Erwachsene im Doppelzimmer erh&ouml;hen"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg></button>
                                </div>
                                <span class="room-lbl">&euro; / Nacht</span>
                                <div class="in"><input id="adultDoublePrice" name="adultDoublePrice" type="text" inputmode="decimal" value="0" required aria-label="Preis pro Nacht Erwachsene im Doppelzimmer"><span class="u">€</span></div>
                                <div class="room-sum-line">
                                    <span class="help room-calc" id="room-sum-adult-double-calc"></span>
                                    <b class="fuf-num room-sum" id="room-sum-adult-double">&ndash;</b>
                                </div>
                                </div>

                                <div class="room-row">
                                    <div class="room-cat">
                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="var(--fuf-green-600)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="7" cy="7" r="2.5"/><circle cx="12" cy="6" r="2.5"/><circle cx="17" cy="7" r="2.5"/><path d="M2 20v-1a4 4 0 014-4h12a4 4 0 014 4v1"/></svg>
                                    <span class="room-cat-text"><b>Erwachsene</b><span class="help">Mehrbettzimmer</span></span>
                                </div>
                                <span class="room-lbl">Personen</span>
                                <div class="stepper">
                                    <button class="step" type="button" data-step="-1" data-step-target="adultMultiCount" aria-label="Anzahl Erwachsene im Mehrbettzimmer verringern"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14"/></svg></button>
                                    <div class="in"><input id="adultMultiCount" name="adultMultiCount" type="text" inputmode="numeric" value="0" required aria-label="Anzahl Erwachsene im Mehrbettzimmer"><span class="u">Pers.</span></div>
                                    <button class="step" type="button" data-step="1" data-step-target="adultMultiCount" aria-label="Anzahl Erwachsene im Mehrbettzimmer erh&ouml;hen"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg></button>
                                </div>
                                <span class="room-lbl">&euro; / Nacht</span>
                                <div class="in"><input id="adultMultiPrice" name="adultMultiPrice" type="text" inputmode="decimal" value="0" required aria-label="Preis pro Nacht Erwachsene im Mehrbettzimmer"><span class="u">€</span></div>
                                <div class="room-sum-line">
                                    <span class="help room-calc" id="room-sum-adult-multi-calc"></span>
                                    <b class="fuf-num room-sum" id="room-sum-adult-multi">&ndash;</b>
                                </div>
                                </div>

                                <div class="room-row">
                                    <div class="room-cat">
                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="var(--fuf-orange-600)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="8" r="4"/><path d="M6 21v-1a6 6 0 0112 0v1"/></svg>
                                    <span class="room-cat-text"><b>Kinder</b><span class="help" id="room-cat-child-sub">unter der Altersgrenze</span></span>
                                </div>
                                <span class="room-lbl">Personen</span>
                                <div class="stepper">
                                    <button class="step" type="button" data-step="-1" data-step-target="childCount" aria-label="Anzahl Kinder verringern"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14"/></svg></button>
                                    <div class="in"><input id="childCount" name="childCount" type="text" inputmode="numeric" value="0" required aria-label="Anzahl Kinder"><span class="u">Pers.</span></div>
                                    <button class="step" type="button" data-step="1" data-step-target="childCount" aria-label="Anzahl Kinder erh&ouml;hen"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg></button>
                                </div>
                                <span class="room-lbl">&euro; / Nacht</span>
                                <div class="in"><input id="childPrice" name="childPrice" type="text" inputmode="decimal" value="0" required aria-label="Preis pro Nacht Kinder"><span class="u">€</span></div>
                                <div class="room-sum-line">
                                    <span class="help room-calc" id="room-sum-child-calc"></span>
                                    <b class="fuf-num room-sum" id="room-sum-child">&ndash;</b>
                                </div>
                                </div>
                            </div>
                            <div class="room-foot">
                                <label for="adultAgeThreshold">Erwachsen ab</label>
                                <div class="in in-sm"><input id="adultAgeThreshold" name="adultAgeThreshold" type="text" inputmode="numeric" required><span class="u">J.</span></div>
                            </div>
                            <!-- DESIGN: Reservierte Zimmer sind Zimmer, nicht Personen - sie fliessen
                                 nicht in die Kalkulation ein, sondern nur in den Abgleich unter
                                 Zimmerbedarf. Die Zeilen erzeugt app.js (roomReservationRow). -->
                            <div class="room-res">
                                <div class="room-res-h">
                                    <b>Reservierte Zimmer</b>
                                    <span class="help">Anzahl Zimmer je Typ, nicht Personen</span>
                                </div>
                                <div id="room-reservations-list" class="room-res-list"></div>
                                <span class="help" id="room-reservations-empty">Noch keine Zimmer reserviert.</span>
                                <div>
                                    <button type="button" id="add-room-reservation-btn" class="btn btn-o btn-s">
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg>
                                        Zimmertyp hinzuf&uuml;gen
                                    </button>
                                </div>
                                <datalist id="room-type-suggestions"></datalist>
                            </div>
                        </div>
                    </div>
                </section>

                <section class="sec" id="sec-zusatz">
                    <div class="sec-h">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="var(--fuf-orange-600)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 3c2 3-1 5 1 8 2-2 5 0 4 4 3-1 4 3 1 6H6c-3-3-2-7 1-6-1-4 2-6 4-4-1-3 1-5 1-8z"/></svg>
                        <h2>Zusatzausgaben</h2>
                        <span class="sum" id="sum-zusatz"></span>
                        <button class="chev" type="button" data-bs-toggle="collapse" data-bs-target="#sec-zusatz-body" aria-expanded="true" aria-controls="sec-zusatz-body" aria-label="Zusatzausgaben ein- oder ausklappen">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M18 15l-6-6-6 6"/></svg>
                        </button>
                    </div>
                    <div class="collapse show" id="sec-zusatz-body">
                        <div class="sec-b">
                            <div class="expense-row">
                                <div class="f">
                                    <label for="expenseLabel">Bezeichnung</label>
                                    <div class="in"><input id="expenseLabel" name="expenseLabel" type="text"></div>
                                </div>
                                <div class="f f-amount">
                                    <label for="expenseAmount">Betrag</label>
                                    <div class="in"><input id="expenseAmount" name="expenseAmount" type="text" inputmode="decimal"><span class="u">€</span></div>
                                </div>
                            </div>
                            <span class="help">Ein Posten, der auf alle Teilnehmer umgelegt wird.</span>
                        </div>
                        <div class="sec-f">
                            <span class="help" id="sum-zusatz-hint"></span>
                            <button id="trip-save-btn" class="btn btn-p" type="submit">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M19 21H5a2 2 0 01-2-2V5a2 2 0 012-2h11l5 5v11a2 2 0 01-2 2z"/><path d="M17 21v-8H7v8M7 3v5h8"/></svg>
                                <span id="trip-save-label">Reise speichern</span>
                            </button>
                        </div>
                    </div>
                </section>
            </form>

            <section class="sec" id="sec-kalkulation">
                <div class="sec-h">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="var(--fuf-green-600)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 20V10M10 20V4M16 20v-8M22 20H2"/></svg>
                    <h2>Kalkulation &amp; Verkaufspreise</h2>
                    <span class="sum" id="sum-kalkulation">
                        <span id="result-distribution-method">&ndash;</span>
                        <span aria-hidden="true">·</span>
                        <span>Kurabgabe ab <span id="result-spa-tax-age">&ndash;</span></span>
                        <span aria-hidden="true">·</span>
                        <span>Erwachsen ab <span id="result-adult-age">&ndash;</span></span>
                        <span aria-hidden="true">·</span>
                        <span>Gruppenausgaben <span id="result-total-group-expenses">&ndash;</span></span>
                    </span>
                    <button id="calculate-btn" class="btn btn-o btn-s" type="button">
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 12a9 9 0 11-3-6.7"/><path d="M21 3v6h-6"/></svg>
                        Neu berechnen
                    </button>
                    <button class="chev" type="button" data-bs-toggle="collapse" data-bs-target="#sec-kalkulation-body" aria-expanded="true" aria-controls="sec-kalkulation-body" aria-label="Kalkulation und Verkaufspreise ein- oder ausklappen">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M18 15l-6-6-6 6"/></svg>
                    </button>
                </div>
                <div class="collapse show" id="sec-kalkulation-body">
                <div class="sec-b">
                    <div id="result-panel" class="d-none calc-wrap">
                        <div class="fuf-grid fuf-grid-4">
                            <div class="kpi">
                                <span>Teilnehmer</span>
                                <b id="result-total-participants">&ndash;</b>
                                <small class="help" id="result-participants-split"></small>
                            </div>
                            <div class="kpi">
                                <span>Gesamteinnahmen</span>
                                <b id="result-total-revenue">&ndash;</b>
                                <small class="help">zu Verkaufspreisen</small>
                            </div>
                            <div class="kpi">
                                <span>Gesamtkosten</span>
                                <b id="result-total-costs">&ndash;</b>
                                <small class="help">Unterkunft + Kurabgabe + Zusatzausgaben</small>
                            </div>
                            <div class="kpi" id="result-surplus-kpi">
                                <span>Überschuss / Defizit</span>
                                <b id="result-surplus">&ndash;</b>
                                <small class="help" id="result-margin"></small>
                            </div>
                        </div>

                        <div class="fuf-grid fuf-grid-calc calc-head">
                            <span class="col-head">Kategorie</span>
                            <span class="col-head">Endpreis</span>
                            <span class="col-head">Verkaufspreis</span>
                            <span class="col-head calc-r">Einnahmen</span>
                            <span></span>
                        </div>

                        <div id="result-breakdowns-container" class="calc-rows"></div>
                    </div>

                    <p id="result-placeholder" class="calc-placeholder">
                        Noch keine Berechnung vorhanden. Nach dem Speichern kannst du hier die Ergebnisse lesen.
                    </p>
                </div>
                <div class="sec-f">
                    <span class="help calc-hint">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 8h.01M11 12h1v4h1"/></svg>
                        Der Verkaufspreis ist mit dem auf 5&nbsp;€ gerundeten Endpreis vorbelegt. Rechenweg je Kategorie über den Pfeil.
                    </span>
                    <button id="sales-apply-btn" class="btn btn-a" type="button">Verkaufspreise übernehmen &amp; neu berechnen</button>
                </div>
                </div><!-- end sec-kalkulation-body -->
            </section>

            <section class="sec" id="registrations-section">
                <div class="sec-h">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="var(--fuf-green-600)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M16 21v-2a4 4 0 00-4-4H6a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M19 8v6M22 11h-6"/></svg>
                    <h2>Anmeldungen</h2>
                    <span class="sum" id="sum-anmeldungen"></span>
                    <button id="recalculate-billings-btn" class="btn btn-g btn-s d-none" type="button">
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 12a9 9 0 11-3-6.7"/><path d="M21 3v6h-6"/></svg>
                        Abrechnungen neu berechnen
                    </button>
                    <button id="delete-registrations-btn" class="btn btn-d btn-s d-none" type="button">
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 6h18M8 6V4h8v2M19 6l-1 14H6L5 6"/></svg>
                        Alle l&ouml;schen
                    </button>
                    <button class="chev" type="button" data-bs-toggle="collapse" data-bs-target="#registrations-section-body" aria-expanded="true" aria-controls="registrations-section-body" aria-label="Anmeldungen ein- oder ausklappen">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M18 15l-6-6-6 6"/></svg>
                    </button>
                </div>
                <div class="collapse show" id="registrations-section-body">
                    <div class="sec-b">
                        <div id="registrations-summary" class="fuf-grid fuf-grid-3 d-none">
                            <div class="kpi">
                                <span>Anmeldungen</span>
                                <b id="reg-count">&ndash;</b>
                                <small class="help" id="reg-count-note"></small>
                            </div>
                            <div class="kpi" id="reg-participant-kpi">
                                <span>Teilnehmer gesamt</span>
                                <b id="reg-participant-count">&ndash;</b>
                                <small class="help" id="reg-participant-note"></small>
                            </div>
                            <div class="kpi">
                                <span>Abrechnungssumme</span>
                                <b id="reg-billing-total">&ndash;</b>
                                <small class="help" id="reg-billing-note"></small>
                            </div>
                        </div>

                        <div class="fuf-grid fuf-grid-intake">
                            <form id="csv-upload-form" class="dropzone">
                                <span class="dropzone-icon" aria-hidden="true">
                                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4M17 8l-5-5-5 5M12 3v12"/></svg>
                                </span>
                                <span class="dropzone-text">
                                    <b class="dropzone-drag">CSV hierher ziehen oder <label for="csvFile">ausw&auml;hlen</label></b>
                                    <!-- DESIGN: Ziehen gibt es auf dem Handy nicht. Unter 901 px tritt
                                         dieser Knopf an die Stelle der Zone, ab 901 px ist er weg und
                                         die Zeile darueber uebernimmt wieder. Zwei <label> auf dasselbe
                                         Feld sind erlaubt, #csvFile bleibt damit unberuehrt. -->
                                    <label for="csvFile" class="btn btn-o dropzone-pick">CSV ausw&auml;hlen</label>
                                    <span class="help">Semikolon-getrennt mit Kopfzeile. Ersetzt nur fr&uuml;here CSV-Anmeldungen &ndash; manuelle bleiben.</span>
                                    <span class="help" id="csv-file-name"></span>
                                </span>
                                <input id="csvFile" class="dropzone-input" name="csv_file" type="file" accept=".csv">
                                <button class="btn btn-o" type="submit">Importieren</button>
                            </form>

                            <form id="manual-registration-form" class="reg-manual">
                                <div class="reg-manual-head">
                                    <b>Manuell hinzuf&uuml;gen</b>
                                    <span class="chip c-amber">Manuell</span>
                                </div>
                                <div class="reg-manual-row">
                                    <div class="f f-cat">
                                        <label for="manualRoomCategory">Zimmerkategorie</label>
                                        <div class="in">
                                            <select id="manualRoomCategory" required>
                                                <option value="2-Bettzimmer">2-Bettzimmer</option>
                                                <option value="3-Bettzimmer">3-Bettzimmer</option>
                                                <option value="4-Bettzimmer">4-Bettzimmer</option>
                                                <option value="5-Bettzimmer">5-Bettzimmer</option>
                                            </select>
                                            <span class="u"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 9l6 6 6-6"/></svg></span>
                                        </div>
                                    </div>
                                    <div class="f">
                                        <label for="manualComment">Kommentar</label>
                                        <div class="in"><input id="manualComment" type="text" placeholder="optional"></div>
                                    </div>
                                </div>
                                <div id="manual-participants-container" class="reg-manual-participants">
                                    <div class="reg-manual-row manual-participant-row">
                                        <div class="f">
                                            <label for="manualParticipantName1">Teilnehmer 1</label>
                                            <div class="in"><input class="manual-participant-name" type="text" required id="manualParticipantName1" placeholder="Name"></div>
                                        </div>
                                        <div class="f f-date">
                                            <label for="manualParticipantBirthdate1">Geburtsdatum</label>
                                            <div class="in"><input class="manual-participant-birthdate" type="date" required id="manualParticipantBirthdate1"></div>
                                        </div>
                                    </div>
                                </div>
                                <div class="reg-manual-foot">
                                    <button type="button" id="add-participant-row-btn" class="ib" aria-label="Teilnehmer hinzuf&uuml;gen" title="Teilnehmer hinzuf&uuml;gen">
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg>
                                    </button>
                                    <button type="button" id="remove-participant-row-btn" class="ib d-none" aria-label="Letzten Teilnehmer entfernen" title="Letzten Teilnehmer entfernen">
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14"/></svg>
                                    </button>
                                    <button type="submit" class="btn btn-p btn-s">Anmeldung hinzuf&uuml;gen</button>
                                </div>
                            </form>
                        </div>

                        <div id="registrations-controls" class="reg-controls d-none">
                            <div class="reg-filters" role="group" aria-label="Anmeldungen filtern">
                                <button type="button" class="chip chip-btn is-active" id="reg-filter-all" data-reg-filter="all">Alle</button>
                                <button type="button" class="chip chip-btn" id="reg-filter-csv" data-reg-filter="csv">CSV</button>
                                <button type="button" class="chip chip-btn" id="reg-filter-manual" data-reg-filter="manual">Manuell</button>
                            </div>
                            <div class="in in-search">
                                <span class="p">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="M21 21l-4.3-4.3"/></svg>
                                </span>
                                <input id="reg-search" type="search" placeholder="Name suchen &hellip;" aria-label="Anmeldung suchen">
                            </div>
                        </div>

                        <div id="registrations-table-wrapper" class="d-none">
                            <div id="registrations-accordion" class="reg-list"></div>
                            <p id="registrations-no-match" class="help reg-no-match d-none">Keine Anmeldung passt zu Filter und Suche.</p>
                        </div>

                        <div id="registrations-empty" class="fuf-empty">
                            <div class="fuf-empty-icon">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M16 21v-2a4 4 0 00-4-4H6a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M19 8v6M22 11h-6"/></svg>
                            </div>
                            <div class="fuf-empty-text">
                                <b>Noch keine Anmeldungen</b>
                                <span class="help">CSV importieren oder eine Anmeldung manuell hinzuf&uuml;gen.</span>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <section class="sec d-none" id="room-summary-section">
                <div class="sec-h">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="var(--fuf-amber-700)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 21h18M5 21V4a1 1 0 011-1h9a1 1 0 011 1v17M14 12h.01"/></svg>
                    <h2>Zimmerbedarf</h2>
                    <span class="sum" id="sum-zimmer"></span>
                    <button class="chev" type="button" data-bs-toggle="collapse" data-bs-target="#room-summary-body" aria-expanded="true" aria-controls="room-summary-body" aria-label="Zimmerbedarf ein- oder ausklappen">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M18 15l-6-6-6 6"/></svg>
                    </button>
                </div>
                <div class="collapse show" id="room-summary-body">
                    <div class="sec-b">
                        <div class="fuf-grid fuf-grid-2">
                            <div class="room-card">
                                <div class="room-card-h">Zimmer nach Typ</div>
                                <table class="tb">
                                    <tbody id="room-detail-body"></tbody>
                                    <tfoot>
                                    <tr class="sum-row">
                                        <td>Gesamt</td>
                                        <td class="r" id="room-detail-total">0</td>
                                    </tr>
                                    </tfoot>
                                </table>
                            </div>
                            <div class="room-card">
                                <div class="room-card-h">Personen nach Abrechnungskategorie</div>
                                <table class="tb">
                                    <tbody>
                                    <tr>
                                        <td class="tb-head">Erwachsene im Doppelzimmer</td>
                                        <td class="help r" id="cat-plan-adult-double" data-col="Plan"></td>
                                        <td class="r cat-ist" data-col="Ist"><b id="cat-summary-adult-double">0</b><span id="cat-check-adult-double"></span></td>
                                    </tr>
                                    <tr>
                                        <td class="tb-head">Erwachsene im Mehrbettzimmer</td>
                                        <td class="help r" id="cat-plan-adult-multi" data-col="Plan"></td>
                                        <td class="r cat-ist" data-col="Ist"><b id="cat-summary-adult-multi">0</b><span id="cat-check-adult-multi"></span></td>
                                    </tr>
                                    <tr>
                                        <td class="tb-head">Kinder</td>
                                        <td class="help r" id="cat-plan-child" data-col="Plan"></td>
                                        <td class="r cat-ist" data-col="Ist"><b id="cat-summary-child">0</b><span id="cat-check-child"></span></td>
                                    </tr>
                                    </tbody>
                                    <tfoot>
                                    <tr class="sum-row">
                                        <td>Gesamt</td>
                                        <td class="help r" id="cat-plan-total"></td>
                                        <td class="r" id="cat-summary-total">0</td>
                                    </tr>
                                    </tfoot>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </section>
            <a class="switch-cta" href="#abrechnung" id="switch-to-abrechnung">
                <span class="switch-cta-icon" aria-hidden="true"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 3h14v18l-3-2-2 2-2-2-2 2-2-2-3 2z"/><path d="M9 8h6M9 12h6M9 16h4"/></svg></span>
                <span class="switch-cta-text">
                    <span class="switch-cta-label">Anderer Bereich</span>
                    <b>Abrechnung</b>
                    <span class="help" id="switch-to-abrechnung-sub">Belege und tats&auml;chliche Ausgaben erfassen</span>
                </span>
                <span class="btn btn-o switch-cta-btn"><span class="switch-cta-word">&Ouml;ffnen</span>
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
                </span>
            </a>
        </div><!-- end pg-main -->

        <aside class="pg-side">
            <nav class="jump-card" aria-label="Abschnitte dieser Seite">
                <span class="jump-title">Auf dieser Seite</span>
                <a class="jump" href="#sec-eckdaten"><span class="dot"></span>Eckdaten</a>
                <a class="jump" href="#sec-aufschlaege"><span class="dot"></span>Aufschläge &amp; Abgaben</a>
                <a class="jump" href="#sec-unterkunft"><span class="dot"></span>Unterkunft &amp; Teilnehmer</a>
                <a class="jump" href="#sec-zusatz"><span class="dot"></span>Zusatzausgaben</a>
                <a class="jump" href="#sec-kalkulation"><span class="dot"></span>Kalkulation &amp; Verkaufspreise</a>
                <a class="jump" href="#registrations-section"><span class="dot"></span>Anmeldungen<span class="chip c-grey jump-chip d-none" id="jump-reg-count"></span></a>
                <a class="jump" href="#room-summary-section"><span class="dot"></span>Zimmerbedarf</a>
                <span class="jump-sep" aria-hidden="true"></span>
                <a class="jump jump-switch" href="#abrechnung"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 3h14v18l-3-2-2 2-2-2-2 2-2-2-3 2z"/></svg>Zur Abrechnung<span class="help jump-switch-count" id="jump-to-abrechnung-count"></span></a>
            </nav>

            <!-- DESIGN: Unter lg ist das Panel ein aufziehbares Blatt am unteren Rand,
                 ab lg wieder die rechte Sticky-Spalte. offcanvas-lg leistet beides mit
                 demselben Markup, also bleibt jede ID einmalig und an ihrem Platz.
                 Backdrop, Escape und Scroll-Sperre kommen von Bootstrap, der Wisch nach
                 unten von app.js. -->
            <div class="calc-sheet offcanvas-lg offcanvas-bottom" id="calc-sheet-planung" tabindex="-1" aria-labelledby="panel-title">
                <div class="offcanvas-header">
                    <button class="sheet-grab" type="button" data-bs-dismiss="offcanvas" data-bs-target="#calc-sheet-planung" aria-label="Blatt schlie&szlig;en"></button>
                </div>
                <div class="offcanvas-body">
                    <div class="pnl">
                        <div class="pnl-h">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="var(--fuf-green-600)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="4" y="2" width="16" height="20" rx="2"/><path d="M8 6h8M8 10h2M12 10h2M8 14h2M12 14h2M8 18h2M12 18h6"/></svg>
                            <h2 id="panel-title">Kalkulation</h2>
                            <span class="pnl-state d-none" id="panel-state"></span>
                        </div>
                        <div class="fuf-grid fuf-grid-2">
                            <div class="pnl-box">
                                <span class="help">Teilnehmer</span>
                                <b id="panel-participants">&ndash;</b>
                                <span class="help" id="panel-participants-sub"></span>
                            </div>
                            <div class="pnl-box">
                                <span class="help">Nächte</span>
                                <b id="result-nights">&ndash;</b>
                                <span class="help"><span id="result-start-date">&ndash;</span><span id="panel-range-sep" class="d-none"> &ndash; </span><span id="result-end-date" class="d-none">&ndash;</span></span>
                            </div>
                        </div>
                        <div class="pnl-lines">
                            <div class="k"><span id="panel-cost-lodging-label">Unterkunft</span><b id="panel-cost-lodging">&ndash;</b></div>
                            <div class="k"><span id="panel-cost-spa-tax-label">Kurabgabe</span><b id="panel-cost-spa-tax">&ndash;</b></div>
                            <div class="k"><span>Zusatzausgaben</span><b id="panel-cost-extra">&ndash;</b></div>
                            <div class="k k-total"><span>Gesamtkosten</span><b id="panel-cost-total">&ndash;</b></div>
                            <div class="k"><span>Einnahmen zu Verkaufspreisen</span><b id="panel-revenue">&ndash;</b></div>
                        </div>
                        <div class="pnl-bar">
                            <div class="pnl-bar-track">
                                <span class="pnl-bar-costs" id="panel-bar-costs"></span>
                                <span class="pnl-bar-surplus" id="panel-bar-surplus"></span>
                            </div>
                            <div class="pnl-bar-legend">
                                <span><span class="pnl-bar-key pnl-bar-costs"></span><span id="panel-bar-costs-label">Kosten</span></span>
                                <span><span class="pnl-bar-key pnl-bar-surplus"></span><span id="panel-bar-surplus-label">Überschuss</span></span>
                            </div>
                        </div>
                        <div class="pnl-green" id="panel-green">
                            <div class="pnl-green-head">
                                <span>Überschuss</span>
                                <b id="panel-surplus">&ndash;</b>
                            </div>
                            <div class="pnl-green-line"><span>Verkaufspreis Erwachsene</span><b id="panel-sales-adults">&ndash;</b></div>
                            <div class="pnl-green-line"><span>Verkaufspreis Kind</span><b id="panel-sales-child">&ndash;</b></div>
                        </div>
                        <button id="trip-save-btn-sticky" class="btn btn-p btn-lg-save" type="submit" form="trip-form">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M19 21H5a2 2 0 01-2-2V5a2 2 0 012-2h11l5 5v11a2 2 0 01-2 2z"/><path d="M17 21v-8H7v8M7 3v5h8"/></svg>
                            <span id="trip-save-label-sticky">Reise speichern</span>
                        </button>
                        <span class="help pnl-note" id="trip-dirty-note"></span>
                    </div>
                </div>
            </div>
        </aside>
        </div><!-- end pg -->
            </div><!-- end panel-planung -->

            <div class="tab-pane fade" id="panel-abrechnung" role="tabpanel" aria-labelledby="tab-abrechnung">
            <div class="pg">
            <div class="pg-main">

                <div id="settlement-stale-note" class="stale-note d-none">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 9v4M12 17h.01M10.3 3.9L1.8 18a2 2 0 001.7 3h17a2 2 0 001.7-3L14.7 3.9a2 2 0 00-3.4 0z"/></svg>
                    <span id="settlement-stale-text"></span>
                    <button class="btn btn-o btn-s" type="submit" form="trip-form">Planung speichern</button>
                </div>

                <div id="settlement-kpis" class="fuf-grid fuf-grid-4">
                    <div class="kpi">
                        <span>Einnahmen</span>
                        <div class="kpi-line"><b id="settlement-total-revenue">&ndash;</b><span class="kpi-dev" id="settlement-revenue-dev"></span></div>
                        <small class="help" id="settlement-revenue-plan"></small>
                    </div>
                    <div class="kpi">
                        <span>Kosten</span>
                        <div class="kpi-line"><b id="settlement-total-expenses">&ndash;</b><span class="kpi-dev" id="settlement-expenses-dev"></span></div>
                        <small class="help" id="settlement-expenses-plan"></small>
                    </div>
                    <div class="kpi">
                        <span>Teilnehmer</span>
                        <div class="kpi-line"><b id="settlement-participants">&ndash;</b><span class="kpi-dev" id="settlement-participants-dev"></span></div>
                        <small class="help" id="settlement-participants-plan"></small>
                    </div>
                    <div class="kpi" id="settlement-surplus-kpi">
                        <span>&Uuml;berschuss / Defizit</span>
                        <div class="kpi-line"><b id="settlement-surplus">&ndash;</b><span class="kpi-dev" id="settlement-surplus-dev"></span></div>
                        <small class="help" id="settlement-surplus-plan"></small>
                    </div>
                </div>

                <section class="sec" id="sec-geplante-kosten">
                    <div class="sec-h">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="var(--fuf-green-600)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="4" y="2" width="16" height="20" rx="2"/><path d="M8 6h8M8 10h2M12 10h2M8 14h2M12 14h2M8 18h2M12 18h6"/></svg>
                        <h2>Geplante Kosten</h2>
                        <span class="sum">aus Reisedaten berechnet</span>
                        <button class="chev" type="button" data-bs-toggle="collapse" data-bs-target="#sec-geplante-kosten-body" aria-expanded="true" aria-controls="sec-geplante-kosten-body" aria-label="Geplante Kosten ein- oder ausklappen">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M18 15l-6-6-6 6"/></svg>
                        </button>
                    </div>
                    <div class="collapse show" id="sec-geplante-kosten-body">
                        <table class="tb">
                            <thead>
                            <tr>
                                <th>Position</th>
                                <th class="r">Betrag</th>
                            </tr>
                            </thead>
                            <tbody id="planned-costs-body"></tbody>
                            <tfoot>
                            <tr class="sum-row">
                                <td>Summe geplante Kosten</td>
                                <td class="r" id="planned-costs-sum">&ndash;</td>
                            </tr>
                            </tfoot>
                        </table>
                    </div>
                </section>

                <section class="sec" id="sec-zusatzausgaben">
                    <div class="sec-h">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="var(--fuf-amber-700)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 7H4a2 2 0 00-2 2v10a2 2 0 002 2h16a2 2 0 002-2V9a2 2 0 00-2-2zM16 3H8a2 2 0 00-2 2v2h12V5a2 2 0 00-2-2z"/><circle cx="16" cy="14" r="1.5"/></svg>
                        <h2>Zus&auml;tzliche Ausgaben</h2>
                        <span class="sum" id="sum-zusatzausgaben">manuell erfasst</span>
                        <button class="chev" type="button" data-bs-toggle="collapse" data-bs-target="#sec-zusatzausgaben-body" aria-expanded="true" aria-controls="sec-zusatzausgaben-body" aria-label="Zus&auml;tzliche Ausgaben ein- oder ausklappen">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M18 15l-6-6-6 6"/></svg>
                        </button>
                    </div>
                    <div class="collapse show" id="sec-zusatzausgaben-body">
                        <div class="sec-b">
                            <form id="actual-expense-form" class="expense-form box-sand">
                                <input id="actualExpenseId" type="hidden" value="">
                                <div class="f">
                                    <label for="actualExpenseLabel">Bezeichnung</label>
                                    <div class="in"><input id="actualExpenseLabel" name="label" type="text" placeholder="z.&nbsp;B. Verpflegung Anreisetag" required></div>
                                </div>
                                <div class="f f-amount">
                                    <label for="actualExpenseAmount">Betrag</label>
                                    <div class="in"><input id="actualExpenseAmount" name="amount" type="text" inputmode="decimal" required><span class="u">&euro;</span></div>
                                </div>
                                <button id="actual-expense-save-btn" class="btn btn-p" type="submit">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg>
                                    <span id="actual-expense-save-label">Hinzuf&uuml;gen</span>
                                </button>
                                <button id="actual-expense-cancel-btn" class="btn btn-o d-none" type="button">Abbrechen</button>
                            </form>

                            <table class="tb">
                                <thead>
                                <tr>
                                    <th>Bezeichnung</th>
                                    <th class="r">Betrag</th>
                                    <th class="r tb-col-action">Aktionen</th>
                                </tr>
                                </thead>
                                <tbody id="actual-expenses-body"></tbody>
                                <tfoot>
                                <tr class="sum-row">
                                    <td>Summe zus&auml;tzliche Ausgaben</td>
                                    <td class="r" id="additional-expenses-sum">&ndash;</td>
                                    <td></td>
                                </tr>
                                </tfoot>
                            </table>
                            <span class="help" id="actual-expenses-empty">Noch keine zus&auml;tzlichen Ausgaben erfasst.</span>
                        </div>
                    </div>
                </section>

                <section class="sec" id="sec-gesamtabrechnung">
                    <div class="sec-h">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="var(--fuf-green-600)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 3h14v18l-3-2-2 2-2-2-2 2-2-2-3 2z"/><path d="M9 8h6M9 12h6M9 16h4"/></svg>
                        <h2>Gesamtabrechnung</h2>
                        <button class="chev" type="button" data-bs-toggle="collapse" data-bs-target="#sec-gesamtabrechnung-body" aria-expanded="true" aria-controls="sec-gesamtabrechnung-body" aria-label="Gesamtabrechnung ein- oder ausklappen">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M18 15l-6-6-6 6"/></svg>
                        </button>
                    </div>
                    <div class="collapse show" id="sec-gesamtabrechnung-body">
                        <div class="sec-b chain">
                            <div class="k chain-row"><span>Einnahmen (Abrechnungssumme Anmeldungen)</span><b id="settlement-summary-revenue">&ndash;</b></div>
                            <div class="k chain-row"><span>&minus; Geplante Kosten (Zimmer, Kurabgabe, Gruppenausgaben)</span><b id="settlement-summary-planned">&ndash;</b></div>
                            <div class="k chain-row"><span>&minus; Zus&auml;tzliche Ausgaben</span><b id="settlement-summary-additional">&ndash;</b></div>
                            <div class="k chain-total"><span>= Gesamtausgaben</span><b id="settlement-summary-total-expenses">&ndash;</b></div>
                            <div class="k chain-surplus" id="settlement-summary-chain"><span>= &Uuml;berschuss / Defizit</span><b id="settlement-summary-surplus">&ndash;</b></div>
                        </div>
                    </div>
                </section>

                <section class="sec" id="refund-section">
                    <div class="sec-h">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="var(--fuf-orange-600)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 7v6h6"/><path d="M21 17a9 9 0 00-15-6.7L3 13"/></svg>
                        <h2>R&uuml;ckerstattung / Nachzahlung</h2>
                        <span class="sum">&Uuml;berschuss anteilig nach gezahltem Betrag</span>
                        <button class="chev" type="button" data-bs-toggle="collapse" data-bs-target="#refund-section-body" aria-expanded="true" aria-controls="refund-section-body" aria-label="R&uuml;ckerstattung ein- oder ausklappen">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M18 15l-6-6-6 6"/></svg>
                        </button>
                    </div>
                    <div class="collapse show" id="refund-section-body">
                        <div class="sec-b">
                            <div class="fuf-grid fuf-grid-refund">
                                <div class="box-amber">
                                    <div class="f">
                                        <label for="retentionPercent">Einbehalt</label>
                                        <div class="in"><input id="retentionPercent" type="text" inputmode="decimal" value="5"><span class="u">%</span></div>
                                    </div>
                                    <span class="help">R&uuml;cklage f&uuml;r den Verein, wird vor der Verteilung abgezogen.</span>
                                </div>
                                <div class="refund-calc">
                                    <div class="cl"><span>&Uuml;berschuss / Defizit</span><b id="refund-surplus">&ndash;</b></div>
                                    <div class="cl"><span>Gesamteinnahmen (Billing)</span><b id="refund-revenue-base">&ndash;</b></div>
                                    <div class="cl"><span>&times; Einbehalt-Prozentsatz</span><b id="refund-retention-percent-display">&ndash;</b></div>
                                    <div class="cl"><span class="help" id="refund-retention-label">= Einbehalt</span><b id="refund-retention">&ndash;</b></div>
                                    <div class="cl cl-total"><span>= Verteilbarer Betrag<span class="help refund-formula" id="refund-distributable-formula"></span></span><b id="refund-distributable">&ndash;</b></div>
                                </div>
                            </div>

                            <table class="tb tb-refund">
                                <thead>
                                <tr>
                                    <th>Anmeldung</th>
                                    <th class="r">Gezahlt (Billing)</th>
                                    <th class="r">Anteil</th>
                                    <th class="r">R&uuml;ckerstattung / Nachzahlung</th>
                                </tr>
                                </thead>
                                <tbody id="refund-body"></tbody>
                                <tfoot>
                                <tr class="sum-row">
                                    <td>Summe</td>
                                    <td class="r" id="refund-total-billing">&ndash;</td>
                                    <td class="r help">100 %</td>
                                    <td class="r" id="refund-total-amount">&ndash;</td>
                                </tr>
                                </tfoot>
                            </table>
                            <span class="help calc-hint">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 8h.01M11 12h1v4h1"/></svg>
                                Positive Betr&auml;ge sind R&uuml;ckerstattungen an die Anmeldung, negative w&auml;ren Nachzahlungen (bei Defizit).
                            </span>
                            <span class="help" id="refund-empty">Keine Anmeldungen mit Abrechnung vorhanden.</span>
                        </div>
                    </div>
                </section>
                <a class="switch-cta switch-cta-back" href="#planung" id="switch-to-planung">
                    <span class="btn btn-o switch-cta-btn">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>
                        <span class="switch-cta-word">&Ouml;ffnen</span>
                    </span>
                    <span class="switch-cta-text">
                        <span class="switch-cta-label">Anderer Bereich</span>
                        <b>Planung</b>
                        <span class="help" id="switch-to-planung-sub">Kalkulation, Verkaufspreise und Anmeldungen</span>
                    </span>
                    <span class="switch-cta-icon" aria-hidden="true"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 20h9M16.5 3.5a2 2 0 013 3L7 19l-4 1 1-4z"/></svg></span>
                </a>
            </div><!-- end pg-main -->

            <aside class="pg-side">
                <nav class="jump-card" aria-label="Abschnitte dieser Seite">
                    <span class="jump-title">Auf dieser Seite</span>
                    <a class="jump" href="#sec-geplante-kosten"><span class="dot"></span>Geplante Kosten</a>
                    <a class="jump" href="#sec-zusatzausgaben"><span class="dot"></span>Zus&auml;tzliche Ausgaben<span class="chip c-amber jump-chip d-none" id="jump-expense-count"></span></a>
                    <a class="jump" href="#sec-gesamtabrechnung"><span class="dot"></span>Gesamtabrechnung</a>
                    <a class="jump" href="#refund-section"><span class="dot"></span>R&uuml;ckerstattung / Nachzahlung</a>
                    <span class="jump-sep" aria-hidden="true"></span>
                    <a class="jump jump-switch" href="#planung"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 20h9M16.5 3.5a2 2 0 013 3L7 19l-4 1 1-4z"/></svg>Zur Planung<span class="help jump-switch-count" id="jump-to-planung-count"></span></a>
                </nav>

                <!-- DESIGN: Unter lg ist das Panel ein aufziehbares Blatt am unteren Rand,
                     ab lg wieder die rechte Sticky-Spalte. offcanvas-lg leistet beides mit
                     demselben Markup, also bleibt jede ID einmalig und an ihrem Platz.
                     Backdrop, Escape und Scroll-Sperre kommen von Bootstrap, der Wisch nach
                     unten von app.js. -->
                <div class="calc-sheet offcanvas-lg offcanvas-bottom" id="calc-sheet-abrechnung" tabindex="-1" aria-labelledby="settlement-panel-title">
                    <div class="offcanvas-header">
                        <button class="sheet-grab" type="button" data-bs-dismiss="offcanvas" data-bs-target="#calc-sheet-abrechnung" aria-label="Blatt schlie&szlig;en"></button>
                    </div>
                    <div class="offcanvas-body">
                        <div class="pnl" id="settlement-panel">
                            <div class="pnl-h">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="var(--fuf-green-600)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 3h14v18l-3-2-2 2-2-2-2 2-2-2-3 2z"/><path d="M9 8h6M9 12h6M9 16h4"/></svg>
                                <h2 id="settlement-panel-title">Abrechnung</h2>
                            </div>
                            <div class="pnl-bar">
                                <div class="pnl-bar-track">
                                    <span class="pnl-bar-planned" id="settlement-bar-planned"></span>
                                    <span class="pnl-bar-additional" id="settlement-bar-additional"></span>
                                    <span class="pnl-bar-retention" id="settlement-bar-retention"></span>
                                    <span class="pnl-bar-distributable" id="settlement-bar-distributable"></span>
                                </div>
                                <div class="pnl-bar-legend pnl-bar-legend-wrap">
                                    <span><span class="pnl-bar-key pnl-bar-planned"></span>Geplant</span>
                                    <span><span class="pnl-bar-key pnl-bar-additional"></span>Zus&auml;tzlich</span>
                                    <span><span class="pnl-bar-key pnl-bar-retention"></span>Einbehalt</span>
                                    <span><span class="pnl-bar-key pnl-bar-distributable"></span>Verteilbar</span>
                                </div>
                            </div>
                            <div class="pnl-lines">
                                <div class="k"><span>Einnahmen</span><b id="panel-settlement-revenue">&ndash;</b></div>
                                <div class="k"><span>Gesamtausgaben</span><b id="panel-settlement-expenses">&ndash;</b></div>
                                <div class="k k-total"><span>&Uuml;berschuss / Defizit</span><b id="panel-settlement-surplus">&ndash;</b></div>
                                <div class="k"><span id="panel-settlement-retention-label">Einbehalt</span><b id="panel-settlement-retention">&ndash;</b></div>
                            </div>
                            <div class="pnl-green" id="panel-settlement-green">
                                <div class="pnl-green-head">
                                    <span>Verteilbar</span>
                                    <b id="panel-settlement-distributable">&ndash;</b>
                                </div>
                                <div class="pnl-green-line"><span>&Oslash; pro Anmeldung</span><b id="panel-settlement-per-registration">&ndash;</b></div>
                                <div class="pnl-green-line"><span>&Oslash; pro Teilnehmer</span><b id="panel-settlement-per-participant">&ndash;</b></div>
                            </div>
                        </div>
                    </div>
                </div>
            </aside>
            </div><!-- end pg -->
            </div><!-- end panel-abrechnung -->
            </div><!-- end tab-content -->

        <!-- DESIGN: Regel 5 - wichtigste Zahl und Hauptaktion unten, in Daumenreichweite.
             Die Kennzahl zieht das Blatt des aktiven Bereichs auf; welches das ist,
             entscheidet app.js, damit der Tab-Wechsel nicht an einem Attribut haengt.
             Ab lg tragen Reisekopf und Sticky-Spalte beides, dann ist die Bar weg. -->
        <div class="trip-bar" id="trip-bar">
            <button class="trip-bar-kpi" id="trip-bar-kpi" type="button" aria-haspopup="dialog">
                <span class="help">
                    <span id="trip-bar-kpi-label">&Uuml;berschuss</span>
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M18 15l-6-6-6 6"/></svg>
                </span>
                <b id="trip-bar-kpi-value">&ndash;</b>
            </button>
            <button id="trip-save-btn-bar" class="btn btn-p" type="submit" form="trip-form">
                <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M19 21H5a2 2 0 01-2-2V5a2 2 0 012-2h11l5 5v11a2 2 0 01-2 2z"/><path d="M17 21v-8H7v8M7 3v5h8"/></svg>
                <span id="trip-save-label-bar">Speichern</span>
            </button>
        </div>
    </section>

    <div class="modal fade" id="settings-modal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable modal-fullscreen-lg-down">
            <div class="modal-content">
                <div class="modal-header">
                    <div class="modal-title-group">
                        <h2 class="modal-title">Globale Settings</h2>
                        <span class="help">Vorbelegung f&uuml;r jede neue Reise &ndash; bestehende Reisen bleiben unver&auml;ndert.</span>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Schliessen"></button>
                </div>
                <div class="modal-body">
                    <form id="settings-form">
                        <div class="settings-group">
                            <b class="settings-group-label">Aufschl&auml;ge</b>
                            <div class="fuf-grid fuf-grid-3">
                                <div class="f">
                                    <label for="defaultMarkupPercent">Aufschlag</label>
                                    <div class="in"><input id="defaultMarkupPercent" name="defaultMarkupPercent" type="text" inputmode="decimal" required><span class="u">%</span></div>
                                </div>
                                <div class="f">
                                    <label for="defaultClubFeePercent">Vereinsgeb&uuml;hr</label>
                                    <div class="in"><input id="defaultClubFeePercent" name="defaultClubFeePercent" type="text" inputmode="decimal" required><span class="u">%</span></div>
                                </div>
                                <div class="f">
                                    <label for="defaultDistributionMethod">Verteilung</label>
                                    <div class="in">
                                        <select id="defaultDistributionMethod" name="defaultDistributionMethod" required>
                                            <option value="PER_PERSON">Per Person</option>
                                            <option value="PER_CATEGORY_UNITS">Per Kategorieeinheit</option>
                                        </select>
                                        <span class="u"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 9l6 6 6-6"/></svg></span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="settings-group settings-group-split">
                            <b class="settings-group-label">Kurabgabe &amp; Altersgrenzen</b>
                            <div class="fuf-grid fuf-grid-3">
                                <div class="f">
                                    <label for="defaultSpaTaxPerPerson">Kurabgabe pro Person &amp; Nacht</label>
                                    <div class="in"><input id="defaultSpaTaxPerPerson" name="defaultSpaTaxPerPerson" type="text" inputmode="decimal" required><span class="u">&euro;</span></div>
                                </div>
                                <div class="f">
                                    <label for="defaultSpaTaxAgeThreshold">Kurabgabe ab Alter</label>
                                    <div class="in"><input id="defaultSpaTaxAgeThreshold" name="defaultSpaTaxAgeThreshold" type="text" inputmode="numeric" required><span class="u">Jahre</span></div>
                                </div>
                                <div class="f">
                                    <label for="defaultAdultAgeThreshold">Erwachsen ab Alter</label>
                                    <div class="in"><input id="defaultAdultAgeThreshold" name="defaultAdultAgeThreshold" type="text" inputmode="numeric" required><span class="u">Jahre</span></div>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-o" data-bs-dismiss="modal">Schlie&szlig;en</button>
                    <button type="submit" form="settings-form" class="btn btn-p">Settings speichern</button>
                </div>
            </div>
        </div>
    </div>
    </div>
    <footer class="app-footer">
        <span>&copy; <?= date('Y') ?> Freizeit und Familie Erding e.V.</span>
        <span class="app-footer-links">
            <a href="/docs" target="_blank" rel="noopener">API-Doku</a>
            <a href="/openapi.yaml" target="_blank" rel="noopener">OpenAPI YAML</a>
        </span>
    </footer>
</div>
<script src="/assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
<script src="/assets/js/app.js"></script>
</body>
</html>



