<!doctype html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>FUF Gruppenreise Kalkulator</title>
    <link rel="stylesheet" href="/assets/vendor/bootstrap/css/bootstrap.min.css">
</head>
<body class="bg-light">
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h1 class="h3 mb-1">FUF Gruppenreise Kalkulator</h1>
            <div class="small text-muted">
                API-Doku: <a href="/docs" target="_blank" rel="noopener">/docs</a>
                &middot;
                OpenAPI YAML: <a href="/openapi.yaml" target="_blank" rel="noopener">/openapi.yaml</a>
            </div>
        </div>
        <div class="d-flex gap-2">
            <button id="open-settings-btn" class="btn btn-outline-secondary" type="button">Globale Settings</button>
            <button id="new-trip-btn" class="btn btn-primary" type="button">Neue Reise erstellen</button>
        </div>
    </div>

    <section id="trip-list-section" class="card mb-3">
        <div class="card-header">Alle Reisen</div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped align-middle mb-0">
                    <thead>
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th title="Sortierung: absteigend">Startdatum &darr;</th>
                        <th class="text-end">Aktion</th>
                    </tr>
                    </thead>
                    <tbody id="trip-list-body"></tbody>
                </table>
            </div>
            <p id="trip-list-empty" class="text-muted mb-0 d-none">Noch keine Reisen vorhanden.</p>
        </div>
    </section>

    <section id="trip-editor-section" class="card mb-3 d-none">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span id="trip-editor-title">Reise</span>
            <button id="back-to-list-btn" class="btn btn-sm btn-outline-secondary" type="button">Zur Liste</button>
        </div>
        <div class="card-body">
            <ul class="nav nav-tabs mb-3" id="tripTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="tab-planung" data-bs-toggle="tab" data-bs-target="#panel-planung" type="button" role="tab" aria-controls="panel-planung" aria-selected="true">Planung</button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="tab-abrechnung" data-bs-toggle="tab" data-bs-target="#panel-abrechnung" type="button" role="tab" aria-controls="panel-abrechnung" aria-selected="false">Abrechnung</button>
                </li>
            </ul>

            <div class="tab-content" id="tripTabContent">
            <div class="tab-pane fade show active" id="panel-planung" role="tabpanel" aria-labelledby="tab-planung">
            <form id="trip-form" class="row g-2">
                <input id="tripFormId" type="hidden" name="tripFormId">
                <div class="col-md-4">
                    <label class="form-label" for="name">Reisename</label>
                    <input id="name" class="form-control" name="name" required>
                </div>
                <div class="col-md-2">
                    <label class="form-label" for="startDate">Startdatum</label>
                    <input id="startDate" class="form-control" name="startDate" type="date" required>
                </div>
                <div class="col-md-2">
                    <label class="form-label" for="markupPercent">Aufschlag %</label>
                    <input id="markupPercent" class="form-control" name="markupPercent" type="number" step="0.01" required>
                </div>
                <div class="col-md-2">
                    <label class="form-label" for="clubFeePercent">Vereinsgebuehr %</label>
                    <input id="clubFeePercent" class="form-control" name="clubFeePercent" type="number" step="0.01" required>
                </div>
                <div class="col-md-2">
                    <label class="form-label" for="distributionMethod">Verteilung</label>
                    <select id="distributionMethod" class="form-select" name="distributionMethod" required>
                        <option value="PER_PERSON">Per Person</option>
                        <option value="PER_CATEGORY_UNITS">Per Kategorieeinheit</option>
                    </select>
                </div>

                <div class="col-md-3">
                    <label class="form-label" for="spaTaxPerPerson">Kurabgabe pro Person</label>
                    <input id="spaTaxPerPerson" class="form-control" name="spaTaxPerPerson" type="number" step="0.01" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label" for="spaTaxAgeThreshold">Kurabgabe ab Alter</label>
                    <input id="spaTaxAgeThreshold" class="form-control" name="spaTaxAgeThreshold" type="number" step="1" required>
                </div>
                <div class="col-md-6"></div>

                <div class="col-12"><hr></div>
                <div class="col-md-2">
                    <label class="form-label" for="adultDoubleCount">Erwachsene DZ Anzahl</label>
                    <input id="adultDoubleCount" class="form-control" name="adultDoubleCount" type="number" step="1" min="0" value="0" required>
                </div>
                <div class="col-md-2">
                    <label class="form-label" for="adultDoublePrice">Erwachsene DZ Preis</label>
                    <input id="adultDoublePrice" class="form-control" name="adultDoublePrice" type="number" step="0.01" min="0" value="0" required>
                </div>
                <div class="col-md-2">
                    <label class="form-label" for="adultMultiCount">Erwachsene MBZ Anzahl</label>
                    <input id="adultMultiCount" class="form-control" name="adultMultiCount" type="number" step="1" min="0" value="0" required>
                </div>
                <div class="col-md-2">
                    <label class="form-label" for="adultMultiPrice">Erwachsene MBZ Preis</label>
                    <input id="adultMultiPrice" class="form-control" name="adultMultiPrice" type="number" step="0.01" min="0" value="0" required>
                </div>
                <div class="col-md-2">
                    <label class="form-label" for="childCount">Kinder Anzahl</label>
                    <input id="childCount" class="form-control" name="childCount" type="number" step="1" min="0" value="0" required>
                </div>
                <div class="col-md-2">
                    <label class="form-label" for="childPrice">Kinder Preis</label>
                    <input id="childPrice" class="form-control" name="childPrice" type="number" step="0.01" min="0" value="0" required>
                </div>

                <div class="col-12"><hr></div>
                <div class="col-md-5">
                    <label class="form-label" for="expenseLabel">Zusatzausgabe (optional)</label>
                    <input id="expenseLabel" class="form-control" name="expenseLabel">
                </div>
                <div class="col-md-3">
                    <label class="form-label" for="expenseAmount">Betrag</label>
                    <input id="expenseAmount" class="form-control" name="expenseAmount" type="number" step="0.01" min="0">
                </div>
                <div class="col-md-4 d-grid"><button id="trip-save-btn" class="btn btn-success" type="submit">Reise speichern</button></div>
            </form>

            <hr>
            <div class="d-flex gap-2 mb-3">
                <button id="calculate-btn" class="btn btn-outline-primary" type="button">Berechnen</button>
            </div>

            <section id="result-panel" class="d-none">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h2 class="h5 mb-0">Berechnungsergebnis</h2>
                    <div class="small text-muted">
                        Details der API: <a href="/docs" target="_blank" rel="noopener">Swagger UI</a>
                    </div>
                </div>

                <div class="row g-3 mb-3">
                    <div class="col-md-3">
                        <div class="card border-0 shadow-sm h-100">
                            <div class="card-body">
                                <div class="text-muted small">Teilnehmer</div>
                                <div id="result-total-participants" class="fs-4 fw-semibold">-</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card border-0 shadow-sm h-100">
                            <div class="card-body">
                                <div class="text-muted small">Gesamteinnahmen</div>
                                <div id="result-total-revenue" class="fs-4 fw-semibold">-</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card border-0 shadow-sm h-100">
                            <div class="card-body">
                                <div class="text-muted small">Gesamtkosten</div>
                                <div id="result-total-costs" class="fs-4 fw-semibold">-</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card border-0 shadow-sm h-100">
                            <div class="card-body">
                                <div class="text-muted small">Ueberschuss / Defizit</div>
                                <div id="result-surplus" class="fs-4 fw-semibold">-</div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row g-3">
                    <div class="col-12">
                        <div class="card border-0 shadow-sm">
                            <div class="card-header bg-white">Preisaufschl&uuml;sselung pro Kategorie</div>
                            <div class="card-body p-0" id="result-breakdowns-container">
                                <p class="text-muted text-center p-3 mb-0">Keine Aufschl&uuml;sselung vorhanden.</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-6">
                        <div class="card border-0 shadow-sm h-100">
                            <div class="card-header bg-white">Berechnungsdetails</div>
                            <div class="card-body">
                                <dl class="row mb-0 small">
                                    <dt class="col-6">Verteilung</dt>
                                    <dd id="result-distribution-method" class="col-6 text-end mb-2">-</dd>
                                    <dt class="col-6">Kurabgabe ab Alter</dt>
                                    <dd id="result-spa-tax-age" class="col-6 text-end mb-2">-</dd>
                                    <dt class="col-6">Reisebeginn</dt>
                                    <dd id="result-start-date" class="col-6 text-end mb-2">-</dd>
                                    <dt class="col-6">Gruppenausgaben gesamt</dt>
                                    <dd id="result-total-group-expenses" class="col-6 text-end mb-0">-</dd>
                                </dl>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-6">
                        <div class="card border-0 shadow-sm h-100">
                            <div class="card-header bg-white">Kosten&uuml;bersicht</div>
                            <div class="card-body p-0">
                                <div class="table-responsive">
                                    <table class="table table-sm mb-0">
                                        <thead><tr><th>Kategorie</th><th class="text-end">Anzahl</th><th class="text-end">Preis/Pers.</th><th class="text-end">Summe</th></tr></thead>
                                        <tbody id="result-category-prices-body"></tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <div id="result-placeholder" class="alert alert-light border mb-0">
                Noch keine Berechnung vorhanden. Nach dem Speichern kannst du hier die Ergebnisse lesen.
            </div>

            <hr>

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
<script src="/assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
<script src="/assets/js/app.js"></script>
</body>
</html>



