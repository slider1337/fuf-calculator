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
            <a class="app-brand" href="/">
                <img src="/assets/img/fuflogo.png" alt="FuF Erding">
                <span>Gruppenreise-Kalkulator</span>
            </a>
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
        </div>
        <div class="app-header-right">
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
    </header>
    <div class="app-main">

    <div class="modal fade" id="users-modal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Benutzerverwaltung</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="d-flex justify-content-between align-items-center gap-2 mb-3">
                        <div class="in" style="max-width: 260px;">
                            <span class="p">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="M21 21l-4.3-4.3"/></svg>
                            </span>
                            <input id="users-search" type="text" placeholder="Benutzer suchen &hellip;" aria-label="Benutzer suchen">
                        </div>
                        <button id="open-invite-btn" class="btn btn-p btn-s" type="button">
                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M16 21v-2a4 4 0 00-4-4H6a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M19 8v6M22 11h-6"/></svg>
                            Nutzer einladen
                        </button>
                    </div>
                    <div id="users-status" class="small mb-2"></div>
                    <div class="table-responsive">
                        <table class="table table-striped align-middle mb-0">
                            <thead>
                            <tr>
                                <th>E-Mail</th>
                                <th>Rolle</th>
                                <th>Status</th>
                                <th class="text-end">Aktion</th>
                            </tr>
                            </thead>
                            <tbody id="users-table-body"></tbody>
                        </table>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Schliessen</button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="invite-modal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Nutzer einladen</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="text-muted small">Die eingeladene Person bekommt einen Link, &uuml;ber den sie ein Passwort vergeben und sich anmelden kann.</p>
                    <form id="invite-form">
                        <div class="mb-3">
                            <label class="form-label" for="invite-email">E-Mail-Adresse</label>
                            <input id="invite-email" class="form-control" type="email" required>
                        </div>
                        <div id="invite-status" class="small"></div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Schliessen</button>
                    <button type="submit" form="invite-form" class="btn btn-primary">Einladung senden</button>
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

        <div id="trip-list-card" class="sec">
            <table class="tb">
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
        <div class="trip-head">
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
            <ul class="nav nav-tabs trip-tabs" id="tripTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="tab-planung" data-bs-toggle="tab" data-bs-target="#panel-planung" type="button" role="tab" aria-controls="panel-planung" aria-selected="true">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 20h9M16.5 3.5a2 2 0 013 3L7 19l-4 1 1-4z"/></svg>
                        Planung
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="tab-abrechnung" data-bs-toggle="tab" data-bs-target="#panel-abrechnung" type="button" role="tab" aria-controls="panel-abrechnung" aria-selected="false">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 3h14v18l-3-2-2 2-2-2-2 2-2-2-3 2z"/><path d="M9 8h6M9 12h6M9 16h4"/></svg>
                        Abrechnung
                    </button>
                </li>
            </ul>
        </div>

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
                                    <div class="in"><input id="markupPercent" name="markupPercent" type="number" step="0.01" required><span class="u">%</span></div>
                                    <span class="help">Puffer auf die Unterkunftskosten</span>
                                </div>
                                <div class="f">
                                    <label for="clubFeePercent">Vereinsgebühr</label>
                                    <div class="in"><input id="clubFeePercent" name="clubFeePercent" type="number" step="0.01" required><span class="u">%</span></div>
                                    <span class="help">Anteil für den Verein</span>
                                </div>
                                <div class="f">
                                    <label for="distributionMethod">Verteilung</label>
                                    <div class="in">
                                        <select id="distributionMethod" name="distributionMethod" required>
                                            <option value="PER_PERSON">Per Person</option>
                                            <option value="PER_CATEGORY_UNITS">Per Kategorieeinheit</option>
                                        </select>
                                    </div>
                                    <span class="help">Wie Gemeinkosten umgelegt werden</span>
                                </div>
                            </div>
                            <div class="box-sand fuf-grid fuf-grid-spa">
                                <div class="box-sand-label">
                                    <b>Kurabgabe</b>
                                    <span class="help">an die Gemeinde</span>
                                </div>
                                <div class="f">
                                    <label for="spaTaxPerPerson">Pro Person &amp; Nacht</label>
                                    <div class="in"><input id="spaTaxPerPerson" name="spaTaxPerPerson" type="number" step="0.01" required><span class="u">€</span></div>
                                </div>
                                <div class="f">
                                    <label for="spaTaxAgeThreshold">Pflichtig ab</label>
                                    <div class="in"><input id="spaTaxAgeThreshold" name="spaTaxAgeThreshold" type="number" step="1" required><span class="u">Jahre</span></div>
                                </div>
                                <div class="f">
                                    <label for="spaTaxCount">Personen</label>
                                    <div class="in"><input id="spaTaxCount" name="spaTaxCount" type="number" step="1" min="0" value="0" required><span class="u">Pers.</span></div>
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

                                <div class="room-cat">
                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="var(--fuf-green-600)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="9" cy="7" r="3"/><circle cx="17" cy="8" r="2.5"/><path d="M3 20v-2a5 5 0 015-5h2a5 5 0 015 5v2M16 14h1a4 4 0 014 4v2"/></svg>
                                    <span class="room-cat-text"><b>Erwachsene</b><span class="help">Doppelzimmer</span></span>
                                </div>
                                <div class="in"><input id="adultDoubleCount" name="adultDoubleCount" type="number" step="1" min="0" value="0" required aria-label="Anzahl Erwachsene im Doppelzimmer"><span class="u">Pers.</span></div>
                                <div class="in"><input id="adultDoublePrice" name="adultDoublePrice" type="number" step="0.01" min="0" value="0" required aria-label="Preis pro Nacht Erwachsene im Doppelzimmer"><span class="u">€</span></div>
                                <b class="fuf-num room-sum" id="room-sum-adult-double">&ndash;</b>

                                <div class="room-cat">
                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="var(--fuf-green-600)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="7" cy="7" r="2.5"/><circle cx="12" cy="6" r="2.5"/><circle cx="17" cy="7" r="2.5"/><path d="M2 20v-1a4 4 0 014-4h12a4 4 0 014 4v1"/></svg>
                                    <span class="room-cat-text"><b>Erwachsene</b><span class="help">Mehrbettzimmer</span></span>
                                </div>
                                <div class="in"><input id="adultMultiCount" name="adultMultiCount" type="number" step="1" min="0" value="0" required aria-label="Anzahl Erwachsene im Mehrbettzimmer"><span class="u">Pers.</span></div>
                                <div class="in"><input id="adultMultiPrice" name="adultMultiPrice" type="number" step="0.01" min="0" value="0" required aria-label="Preis pro Nacht Erwachsene im Mehrbettzimmer"><span class="u">€</span></div>
                                <b class="fuf-num room-sum" id="room-sum-adult-multi">&ndash;</b>

                                <div class="room-cat">
                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="var(--fuf-orange-600)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="8" r="4"/><path d="M6 21v-1a6 6 0 0112 0v1"/></svg>
                                    <span class="room-cat-text"><b>Kinder</b><span class="help" id="room-cat-child-sub">unter der Altersgrenze</span></span>
                                </div>
                                <div class="in"><input id="childCount" name="childCount" type="number" step="1" min="0" value="0" required aria-label="Anzahl Kinder"><span class="u">Pers.</span></div>
                                <div class="in"><input id="childPrice" name="childPrice" type="number" step="0.01" min="0" value="0" required aria-label="Preis pro Nacht Kinder"><span class="u">€</span></div>
                                <b class="fuf-num room-sum" id="room-sum-child">&ndash;</b>
                            </div>
                            <div class="room-foot">
                                <label for="adultAgeThreshold">Erwachsen ab</label>
                                <div class="in in-sm"><input id="adultAgeThreshold" name="adultAgeThreshold" type="number" step="1" min="0" required><span class="u">J.</span></div>
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
                                    <div class="in"><input id="expenseAmount" name="expenseAmount" type="number" step="0.01" min="0"><span class="u">€</span></div>
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
                </div>
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
            </section>

            <section id="registrations-section">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h2 class="h5 mb-0">Anmeldungen &amp; Abrechnung</h2>
                    <div class="d-flex gap-2">
                        <button id="recalculate-billings-btn" class="btn btn-sm btn-outline-warning d-none" type="button">Abrechnungen neu berechnen</button>
                        <button id="delete-registrations-btn" class="btn btn-sm btn-outline-danger d-none" type="button">Alle Anmeldungen l&ouml;schen</button>
                    </div>
                </div>

                <div class="card border-0 shadow-sm mb-3">
                    <div class="card-body">
                        <form id="csv-upload-form" class="row g-2 align-items-end">
                            <div class="col-md-8">
                                <label class="form-label" for="csvFile">CSV-Datei importieren</label>
                                <input id="csvFile" class="form-control" name="csv_file" type="file" accept=".csv" required>
                            </div>
                            <div class="col-md-4 d-grid">
                                <button class="btn btn-primary" type="submit">Importieren</button>
                            </div>
                        </form>
                        <div class="small text-muted mt-2">
                            Erwartetes Format: Semikolon-getrennte CSV mit Kopfzeile. Beim Import werden nur vorherige CSV-Anmeldungen ersetzt. Manuelle Anmeldungen bleiben erhalten.
                        </div>
                    </div>
                </div>

                <div class="card border-0 shadow-sm mb-3">
                    <div class="card-header bg-white">Anmeldung manuell hinzuf&uuml;gen</div>
                    <div class="card-body">
                        <form id="manual-registration-form" class="row g-2 align-items-end">
                            <div class="col-md-3">
                                <label class="form-label" for="manualRoomCategory">Zimmerkategorie</label>
                                <select id="manualRoomCategory" class="form-select" required>
                                    <option value="2-Bettzimmer">2-Bettzimmer</option>
                                    <option value="3-Bettzimmer">3-Bettzimmer</option>
                                    <option value="4-Bettzimmer">4-Bettzimmer</option>
                                    <option value="5-Bettzimmer">5-Bettzimmer</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="manualComment">Kommentar</label>
                                <input id="manualComment" class="form-control" type="text">
                            </div>
                            <div class="col-md-3"></div>
                            <div class="col-12" id="manual-participants-container">
                                <div class="row g-2 align-items-end mb-1 manual-participant-row">
                                    <div class="col-md-5">
                                        <label class="form-label" for="manualParticipantName1">Name Teilnehmer 1</label>
                                        <input class="form-control manual-participant-name" type="text" required id="manualParticipantName1">
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label" for="manualParticipantBirthdate1">Geburtsdatum Teilnehmer 1</label>
                                        <input class="form-control manual-participant-birthdate" type="date" required id="manualParticipantBirthdate1">
                                    </div>
                                    <div class="col-md-3"></div>
                                </div>
                            </div>
                            <div class="col-12 d-flex gap-2">
                                <button type="button" id="add-participant-row-btn" class="btn btn-sm btn-outline-secondary">+ Teilnehmer</button>
                                <button type="button" id="remove-participant-row-btn" class="btn btn-sm btn-outline-secondary d-none">&minus; Teilnehmer</button>
                                <button type="submit" class="btn btn-sm btn-success ms-auto">Anmeldung hinzuf&uuml;gen</button>
                            </div>
                        </form>
                    </div>
                </div>

                <div id="registrations-summary" class="row g-3 mb-3 d-none">
                    <div class="col-md-4">
                        <div class="card border-0 shadow-sm h-100">
                            <div class="card-body">
                                <div class="text-muted small">Anmeldungen</div>
                                <div id="reg-count" class="fs-4 fw-semibold">-</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card border-0 shadow-sm h-100">
                            <div class="card-body">
                                <div class="text-muted small">Teilnehmer gesamt</div>
                                <div id="reg-participant-count" class="fs-4 fw-semibold">-</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card border-0 shadow-sm h-100">
                            <div class="card-body">
                                <div class="text-muted small">Abrechnungssumme</div>
                                <div id="reg-billing-total" class="fs-4 fw-semibold">-</div>
                            </div>
                        </div>
                    </div>
                </div>

                <div id="registrations-table-wrapper" class="d-none">
                    <div class="accordion" id="registrations-accordion"></div>
                </div>

                <p id="registrations-empty" class="text-muted mb-0">Noch keine Anmeldungen vorhanden. Bitte CSV-Datei importieren.</p>
            </section>

            <section id="room-summary-section" class="d-none mt-3">
                <h2 class="h5 mb-3">Zimmerbedarf</h2>
                <div class="row g-3">
                    <div class="col-lg-6">
                        <div class="card border-0 shadow-sm h-100">
                            <div class="card-header bg-white">Zimmer nach Typ</div>
                            <div class="card-body p-0">
                                <div class="table-responsive">
                                    <table class="table table-striped mb-0">
                                        <thead>
                                        <tr>
                                            <th>Zimmerkategorie</th>
                                            <th class="text-end">Anzahl</th>
                                        </tr>
                                        </thead>
                                        <tbody id="room-detail-body"></tbody>
                                        <tfoot>
                                        <tr class="table-light">
                                            <td><strong>Gesamt</strong></td>
                                            <td class="text-end"><strong id="room-detail-total">0</strong></td>
                                        </tr>
                                        </tfoot>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-6">
                        <div class="card border-0 shadow-sm h-100">
                            <div class="card-header bg-white">Personen nach Abrechnungskategorie</div>
                            <div class="card-body p-0">
                                <div class="table-responsive">
                                    <table class="table table-striped mb-0">
                                        <thead>
                                        <tr>
                                            <th>Kategorie</th>
                                            <th class="text-end">Anzahl</th>
                                        </tr>
                                        </thead>
                                        <tbody>
                                        <tr>
                                            <td>Erwachsener im Doppelzimmer</td>
                                            <td class="text-end" id="cat-summary-adult-double">0</td>
                                        </tr>
                                        <tr>
                                            <td>Erwachsener im Mehrbettzimmer</td>
                                            <td class="text-end" id="cat-summary-adult-multi">0</td>
                                        </tr>
                                        <tr>
                                            <td>Kind</td>
                                            <td class="text-end" id="cat-summary-child">0</td>
                                        </tr>
                                        </tbody>
                                        <tfoot>
                                        <tr class="table-light">
                                            <td><strong>Gesamt</strong></td>
                                            <td class="text-end"><strong id="cat-summary-total">0</strong></td>
                                        </tr>
                                        </tfoot>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>
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
            </nav>

            <div class="pnl">
                <div class="pnl-h">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="var(--fuf-green-600)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="4" y="2" width="16" height="20" rx="2"/><path d="M8 6h8M8 10h2M12 10h2M8 14h2M12 14h2M8 18h2M12 18h6"/></svg>
                    <h2>Kalkulation</h2>
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
                        <span class="help"><span id="result-start-date">&ndash;</span> &ndash; <span id="result-end-date">&ndash;</span></span>
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
                <div class="pnl-green">
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
        </aside>
        </div><!-- end pg -->
            </div><!-- end panel-planung -->

            <div class="tab-pane fade" id="panel-abrechnung" role="tabpanel" aria-labelledby="tab-abrechnung">
                <div class="row g-3 mb-3">
                    <div class="col-md-3">
                        <div class="card border-0 shadow-sm h-100">
                            <div class="card-body">
                                <div class="text-muted small">Einnahmen (Billing)</div>
                                <div id="settlement-total-revenue" class="fs-4 fw-semibold">-</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card border-0 shadow-sm h-100">
                            <div class="card-body">
                                <div class="text-muted small">Geplante Kosten</div>
                                <div id="settlement-planned-costs" class="fs-4 fw-semibold">-</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card border-0 shadow-sm h-100">
                            <div class="card-body">
                                <div class="text-muted small">Gesamtausgaben</div>
                                <div id="settlement-total-expenses" class="fs-4 fw-semibold">-</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card border-0 shadow-sm h-100">
                            <div class="card-body">
                                <div class="text-muted small">&Uuml;berschuss / Defizit</div>
                                <div id="settlement-surplus" class="fs-4 fw-semibold">-</div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card border-0 shadow-sm mb-3">
                    <div class="card-header bg-white"><strong>Geplante Kosten</strong> <span class="text-muted small">(aus Reisedaten berechnet)</span></div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-striped align-middle mb-0">
                                <thead>
                                <tr>
                                    <th>Position</th>
                                    <th class="text-end">Betrag</th>
                                </tr>
                                </thead>
                                <tbody id="planned-costs-body"></tbody>
                                <tfoot>
                                <tr class="table-light">
                                    <td><strong>Summe geplante Kosten</strong></td>
                                    <td class="text-end"><strong id="planned-costs-sum">-</strong></td>
                                </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>
                </div>

                <div class="card border-0 shadow-sm mb-3">
                    <div class="card-header bg-white"><strong>Zus&auml;tzliche Ausgaben</strong> <span class="text-muted small">(manuell erfasst)</span></div>
                    <div class="card-body">
                        <form id="actual-expense-form" class="row g-2 align-items-end mb-3">
                            <input id="actualExpenseId" type="hidden" value="">
                            <div class="col-md-5">
                                <label class="form-label" for="actualExpenseLabel">Bezeichnung</label>
                                <input id="actualExpenseLabel" class="form-control" name="label" required>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label" for="actualExpenseAmount">Betrag (&euro;)</label>
                                <input id="actualExpenseAmount" class="form-control" name="amount" type="number" step="0.01" min="0" required>
                            </div>
                            <div class="col-md-2 d-grid">
                                <button id="actual-expense-save-btn" class="btn btn-success" type="submit">Hinzuf&uuml;gen</button>
                            </div>
                            <div class="col-md-2 d-grid">
                                <button id="actual-expense-cancel-btn" class="btn btn-outline-secondary d-none" type="button">Abbrechen</button>
                            </div>
                        </form>

                        <div class="table-responsive">
                            <table class="table table-striped align-middle mb-0">
                                <thead>
                                <tr>
                                    <th>Bezeichnung</th>
                                    <th class="text-end">Betrag</th>
                                    <th class="text-end">Aktionen</th>
                                </tr>
                                </thead>
                                <tbody id="actual-expenses-body"></tbody>
                                <tfoot>
                                <tr class="table-light">
                                    <td><strong>Summe zus&auml;tzliche Ausgaben</strong></td>
                                    <td class="text-end"><strong id="additional-expenses-sum">-</strong></td>
                                    <td></td>
                                </tr>
                                </tfoot>
                            </table>
                        </div>
                        <p id="actual-expenses-empty" class="text-muted mt-2 mb-0">Noch keine zus&auml;tzlichen Ausgaben erfasst.</p>
                    </div>
                </div>

                <div class="card border-0 shadow-sm mb-3">
                    <div class="card-header bg-white"><strong>Gesamtabrechnung</strong></div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table mb-0">
                                <tbody>
                                <tr>
                                    <td>Einnahmen (Abrechnungssumme Anmeldungen)</td>
                                    <td class="text-end fw-semibold" id="settlement-summary-revenue">-</td>
                                </tr>
                                <tr>
                                    <td>− Geplante Kosten (Zimmer, Kurabgabe, Gruppenausgaben)</td>
                                    <td class="text-end" id="settlement-summary-planned">-</td>
                                </tr>
                                <tr>
                                    <td>− Zus&auml;tzliche Ausgaben</td>
                                    <td class="text-end" id="settlement-summary-additional">-</td>
                                </tr>
                                <tr class="table-light">
                                    <td><strong>= Gesamtausgaben</strong></td>
                                    <td class="text-end"><strong id="settlement-summary-total-expenses">-</strong></td>
                                </tr>
                                <tr class="table-dark">
                                    <td><strong>= &Uuml;berschuss / Defizit</strong></td>
                                    <td class="text-end"><strong id="settlement-summary-surplus">-</strong></td>
                                </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div class="card border-0 shadow-sm mb-3" id="refund-section">
                    <div class="card-header bg-white"><strong>R&uuml;ckerstattung / Nachzahlung pro Anmeldung</strong></div>
                    <div class="card-body">
                        <div class="row g-2 align-items-end mb-3">
                            <div class="col-md-3">
                                <label class="form-label" for="retentionPercent">Einbehalt (%)</label>
                                <input id="retentionPercent" class="form-control" type="number" step="0.01" min="0" max="100" value="5">
                            </div>
                        </div>

                        <div class="card bg-light border mb-3">
                            <div class="card-body p-0">
                                <table class="table table-sm mb-0 small">
                                    <tbody>
                                    <tr>
                                        <td>&Uuml;berschuss / Defizit</td>
                                        <td class="text-end fw-semibold" id="refund-surplus">-</td>
                                    </tr>
                                    <tr class="table-light">
                                        <td colspan="2" class="text-muted fst-italic pt-2 pb-1">Berechnung Einbehalt:</td>
                                    </tr>
                                    <tr>
                                        <td class="ps-4">Gesamteinnahmen (Billing)</td>
                                        <td class="text-end" id="refund-revenue-base">-</td>
                                    </tr>
                                    <tr>
                                        <td class="ps-4">&times; Einbehalt-Prozentsatz</td>
                                        <td class="text-end" id="refund-retention-percent-display">-</td>
                                    </tr>
                                    <tr>
                                        <td class="ps-4" id="refund-retention-label">= Einbehalt</td>
                                        <td class="text-end fw-semibold" id="refund-retention">-</td>
                                    </tr>
                                    <tr class="table-light">
                                        <td colspan="2" class="pt-2 pb-1"></td>
                                    </tr>
                                    <tr>
                                        <td id="refund-distributable-formula">&Uuml;berschuss &minus; Einbehalt</td>
                                        <td class="text-end"></td>
                                    </tr>
                                    <tr class="table-dark">
                                        <td><strong>= Verteilbarer Betrag</strong></td>
                                        <td class="text-end"><strong id="refund-distributable">-</strong></td>
                                    </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <div class="table-responsive">
                            <table class="table table-striped table-sm align-middle mb-0">
                                <thead>
                                <tr>
                                    <th>Anmeldung</th>
                                    <th class="text-end">Gezahlt (Billing)</th>
                                    <th class="text-end">Anteil</th>
                                    <th class="text-end">R&uuml;ckerstattung / Nachzahlung</th>
                                </tr>
                                </thead>
                                <tbody id="refund-body"></tbody>
                                <tfoot>
                                <tr class="table-light">
                                    <td><strong>Summe</strong></td>
                                    <td class="text-end"><strong id="refund-total-billing">-</strong></td>
                                    <td class="text-end">100%</td>
                                    <td class="text-end"><strong id="refund-total-amount">-</strong></td>
                                </tr>
                                </tfoot>
                            </table>
                        </div>
                        <p id="refund-empty" class="text-muted mt-2 mb-0">Keine Anmeldungen mit Abrechnung vorhanden.</p>
                    </div>
                </div>
            </div><!-- end panel-abrechnung -->
            </div><!-- end tab-content -->
        </div>
    </section>

    <div class="modal fade" id="settings-modal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Globale Settings</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="settings-form" class="row g-2">
                        <div class="col-md-4">
                            <label class="form-label" for="defaultMarkupPercent">Aufschlag %</label>
                            <input id="defaultMarkupPercent" class="form-control" name="defaultMarkupPercent" type="number" step="0.01" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="defaultClubFeePercent">Vereinsgebuehr %</label>
                            <input id="defaultClubFeePercent" class="form-control" name="defaultClubFeePercent" type="number" step="0.01" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="defaultDistributionMethod">Verteilung</label>
                            <select id="defaultDistributionMethod" class="form-select" name="defaultDistributionMethod" required>
                                <option value="PER_PERSON">Per Person</option>
                                <option value="PER_CATEGORY_UNITS">Per Kategorieeinheit</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="defaultSpaTaxPerPerson">Kurabgabe</label>
                            <input id="defaultSpaTaxPerPerson" class="form-control" name="defaultSpaTaxPerPerson" type="number" step="0.01" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="defaultSpaTaxAgeThreshold">Kurabgabe ab Alter</label>
                            <input id="defaultSpaTaxAgeThreshold" class="form-control" name="defaultSpaTaxAgeThreshold" type="number" step="1" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="defaultAdultAgeThreshold">Erwachsen ab Alter</label>
                            <input id="defaultAdultAgeThreshold" class="form-control" name="defaultAdultAgeThreshold" type="number" step="1" min="0" required>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Schliessen</button>
                    <button type="submit" form="settings-form" class="btn btn-primary">Settings speichern</button>
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



