// Calculation preview, result rendering and price changes.
function resetCalculationResult() {
  byId("result-panel").classList.add("d-none");
  byId("result-placeholder").classList.remove("d-none");
  byId("result-total-participants").textContent = "–";
  byId("result-participants-split").textContent = "";
  byId("result-total-revenue").textContent = "–";
  byId("result-total-costs").textContent = "–";
  byId("result-surplus").textContent = "–";
  byId("result-surplus-kpi").className = "kpi";
  byId("result-margin").textContent = "";
  byId("result-distribution-method").textContent = "–";
  byId("result-spa-tax-age").textContent = "–";
  byId("result-adult-age").textContent = "–";
  byId("result-total-group-expenses").textContent = "–";
  // DESIGN: Diese drei stehen im Sticky-Panel, sind also auch ohne Ergebnis
  // sichtbar - beim Reisewechsel duerfen keine alten Werte stehenbleiben.
  byId("result-start-date").textContent = "–";
  byId("result-end-date").textContent = "–";
  byId("result-nights").textContent = "–";
  setTripEditorMeta("");
  byId("result-breakdowns-container").innerHTML = "";
  resetPanel();
  // Die Zusammenfassung im Section-Kopf fasst ein Ergebnis zusammen. Ohne
  // Ergebnis stuenden dort nur Gedankenstriche.
  byId("sum-kalkulation").classList.add("d-none");
}

// DESIGN: Mockup 02 zeigt "Reise speichern" zweimal - in der Fusszeile der
// Zusatzausgaben und im Sticky-Panel. Beide Beschriftungen muessen gleich
// lauten. Der Text sitzt je in einem eigenen Span, damit das Icon im Button
// beim Umschreiben erhalten bleibt.
function setTripSaveLabel(text) {
  byId("trip-save-label").textContent = text;
  byId("trip-save-label-sticky").textContent = text;
  // In der schmalen Kontextleiste und in der Bottom-Bar ist nur Platz fuer das
  // Verb - dort steht die Kennzahl daneben.
  const short = text.replace(/^Reise /, "");
  const verb = short.charAt(0).toUpperCase() + short.slice(1);
  byId("trip-save-label-head").textContent = verb;
  byId("trip-save-label-bar").textContent = verb;
}

// DESIGN: Eingabefeld je Kategorie in der Akkordeonzeile. Die ID bleibt, damit
// die Felder auffindbar bleiben; der Wert wird ueber state.salesPrices gehalten.
const salesInputIds = {
  ADULT_DOUBLE: "salesAdultDouble",
  ADULT_MULTI: "salesAdultMulti",
  ADULT: "salesAdult",
  CHILD: "salesChild",
};

function resetSalesPrices() {
  state.salesPrices = { ADULT_DOUBLE: null, ADULT_MULTI: null, ADULT: null, CHILD: null };
}

// Reihenfolge: eine manuelle Eingabe schlaegt den gespeicherten Verkaufspreis,
// dieser die Vorbelegung aus der Berechnung. So uebersteht eine Eingabe ein
// "Neu berechnen", genau wie beim alten statischen Feld.
function salesPriceForRow(category, breakdown) {
  if (state.salesPrices[category] != null) {
    return state.salesPrices[category];
  }
  if (breakdown.salesPricePerPerson != null) {
    return breakdown.salesPricePerPerson;
  }
  return breakdown.salesPriceDefault ?? null;
}

// DESIGN: "10.-14.02.2027 - 4 Naechte - 56 Teilnehmer" unter der Ueberschrift.
function tripEditorMeta(result) {
  const nights = Number(result.nights ?? 0);
  const participants = Number(result.totalParticipants ?? 0);

  return [
    formatTripPeriod(result),
    `${nights} ${nights === 1 ? "Nacht" : "Nächte"}`,
    `${participants} Teilnehmer`,
  ].join(" · ");
}

// DESIGN: Auf dem Handy steht die Zeile ausserhalb des Reisekopfs, damit nur
// Kopfleiste und Umschalter sticky sind. Beide Orte tragen denselben Text.
function setTripEditorMeta(text) {
  byId("trip-editor-meta").textContent = text;
  byId("trip-editor-meta-mobile").textContent = text;
}

// DESIGN: Titel und Untertitel der Kategoriezeile, wie in Mockup 02.
const calcCategoryTitles = {
  ADULT_DOUBLE: { title: "Erwachsene · DZ", sub: "Doppelzimmer" },
  ADULT_MULTI: { title: "Erwachsene · MBZ", sub: "Mehrbettzimmer" },
  ADULT: { title: "Erwachsene", sub: "Doppel- und Mehrbettzimmer · gemittelter Preis" },
  CHILD: { title: "Kinder", sub: "unter der Altersgrenze · keine Kurabgabe" },
};

function formatPercent(value) {
  return `${String(value).replace(".", ",")} %`;
}

// DESIGN: Untertitel der Teilnehmer-KPI, z. B. "30 Erw. · 26 Kinder".
function participantsSplit(breakdowns) {
  const adults = (breakdowns.ADULT_DOUBLE?.count ?? 0)
    + (breakdowns.ADULT_MULTI?.count ?? 0)
    + (breakdowns.ADULT?.count ?? 0);
  const children = breakdowns.CHILD?.count ?? 0;

  if (adults === 0 && children === 0) {
    return "";
  }

  return `${adults} Erw. · ${children} ${children === 1 ? "Kind" : "Kinder"}`;
}

// DESIGN: Untertitel der Ueberschuss-KPI, z. B. "13,3 % Marge".
function marginLabel(surplus, revenue) {
  if (!revenue) {
    return "";
  }

  const margin = (surplus / revenue) * 100;
  return `${margin.toLocaleString("de-DE", { minimumFractionDigits: 1, maximumFractionDigits: 1 })} % Marge`;
}

function signedCurrency(value) {
  return `${value < 0 ? "− " : "+ "}${formatCurrency(Math.abs(value))}`;
}

// DESIGN: Abweichung des Verkaufspreises vom berechneten Endpreis. Nach unten
// amber, weil dann weniger eingenommen wird als die Kalkulation vorsieht.
// DESIGN: Abweichung in den Plan/Ist-Kacheln. Bei exakter Uebereinstimmung
// steht "± 0,00 €" - ein "+ 0,00 €" liest sich wie eine Abweichung nach oben.
function deviationCurrency(value) {
  return value === 0 ? `± ${formatCurrency(0)}` : signedCurrency(value);
}

function deviationCount(value) {
  if (value === 0) {
    return "± 0";
  }
  return `${value < 0 ? "− " : "+ "}${Math.abs(value)}`;
}

// Guenstig oder unguenstig haengt von der Kachel ab: mehr Einnahmen ist gut,
// mehr Kosten ist schlecht. Deshalb kommt die Richtung von aussen.
function renderDeviation(elementId, deviation, text) {
  const element = byId(elementId);
  element.textContent = text;
  element.className = deviation === 0
    ? "kpi-dev"
    : `kpi-dev ${deviation > 0 ? "kpi-dev-good" : "kpi-dev-bad"}`;
}

function salesDeviation(salesPrice, finalPrice) {
  if (salesPrice == null) {
    return "";
  }

  const difference = Number(salesPrice) - Number(finalPrice);
  if (Math.abs(difference) < 0.005) {
    return "";
  }

  const tone = difference < 0 ? "calc-dev-down" : "calc-dev-up";
  return `<span class="calc-dev ${tone}">${signedCurrency(difference)}</span>`;
}

// DESIGN: Eine Kategoriezeile des Akkordeons - Kopfzeile plus Rechenweg.
// Bootstrap uebernimmt das Auf- und Zuklappen ueber data-bs-toggle, auch in
// diesem per innerHTML erzeugten Markup: die Klicks sind am Dokument delegiert.
// DESIGN: Beim gemittelten Erwachsenenpreis ist der Naechtigungspreis selbst
// schon ein Rechenergebnis. Der Block zeigt es her, bevor der eigentliche
// Rechenweg damit weiterrechnet - abgesetzt, weil es eine Vorstufe ist und kein
// weiterer Schritt der Preiskette.
function averagedFromBlock(bd) {
  const parts = bd.averagedFrom ?? [];
  if (parts.length === 0) {
    return "";
  }

  const persons = parts.reduce((sum, part) => sum + part.count, 0);
  const rows = parts
    .map((part) => `
      <div class="cl">
        <span>${part.count} × ${formatCurrency(part.basePricePerPerson)} · ${calcCategoryTitles[part.categoryType]?.sub ?? part.categoryType}</span>
        <b>${formatCurrency(part.total)}</b>
      </div>`)
    .join("");

  return `
    <div class="calc-avg">
      <div class="calc-avg-head">Gemittelter Nächtigungspreis</div>
      ${rows}
      <div class="cl cl-total">
        <span>÷ ${persons} ${persons === 1 ? "Person" : "Personen"}</span>
        <b>${formatCurrency(bd.basePricePerPerson)}</b>
      </div>
    </div>
  `;
}

function calcRow(category, bd, isOpen) {
  const names = calcCategoryTitles[category] || { title: categoryLabels[category] || category, sub: "" };
  const bodyId = `calc-body-${category.toLowerCase().replace(/_/g, "-")}`;
  const inputId = salesInputIds[category];
  const salesPrice = state.salesPrices[category];
  const nights = bd.nights ?? 0;
  const nightsWord = nights === 1 ? "Nacht" : "Nächte";
  // Bei einem gesetzten Verkaufspreis steht in der Zeile genau dieser Wert, sonst
  // der Vorschlag - Beschriftung und Zahl muessen zusammenpassen.
  const prefillNote = bd.salesPriceOverridden
    ? "Verkaufspreis manuell gesetzt"
    : "Vorbelegung: auf 5 € gerundet";
  const prefillValue = bd.salesPriceOverridden
    ? bd.salesPricePerPerson ?? bd.finalPrice
    : bd.salesPriceDefault ?? bd.finalPrice;

  return `
    <div class="calc-row${isOpen ? " is-open" : ""}">
      <div class="fuf-grid fuf-grid-calc calc-row-head">
        <div class="calc-cell">
          <b>${names.title}</b>
          <span class="help">${names.sub}</span>
        </div>
        <div class="calc-cell">
          <span class="help">berechnet</span>
          <b class="fuf-num">${formatCurrency(bd.finalPrice)}</b>
        </div>
        <div class="calc-sales">
          <div class="in in-sm">
            <input id="${inputId}" class="calc-sales-input" type="text" inputmode="decimal" value="${formatDecimal(salesPrice)}"
                   data-sales-category="${category}" placeholder="Auto" aria-label="Verkaufspreis ${names.title}">
            <span class="u">€</span>
          </div>
          ${salesDeviation(salesPrice, bd.finalPrice)}
        </div>
        <div class="calc-cell calc-r">
          <span class="help">${bd.count} ${bd.count === 1 ? "Pers." : "Pers."}</span>
          <b class="fuf-num fuf-pos">${formatCurrency(bd.categoryRevenue)}</b>
        </div>
        <button class="chev" type="button" data-bs-toggle="collapse" data-bs-target="#${bodyId}"
                aria-expanded="${isOpen ? "true" : "false"}" aria-controls="${bodyId}"
                aria-label="Rechenweg ${names.title} ein- oder ausklappen">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M18 15l-6-6-6 6"/></svg>
        </button>
      </div>
      <div class="collapse${isOpen ? " show" : ""}" id="${bodyId}">
        <div class="calc-body">
          ${averagedFromBlock(bd)}
          <div class="calc-col">
            <div class="cl"><span>${formatCurrency(bd.basePricePerPerson)} × ${nights} ${nightsWord}</span><b>${formatCurrency(bd.baseTotalPerPerson ?? bd.basePricePerPerson * nights)}</b></div>
            <div class="cl"><span>+ Kurabgabe ${formatCurrency(bd.spaTaxPerPerson)} × ${nights}</span><b>${formatCurrency(bd.spaTaxTotalPerPerson ?? bd.spaTaxPerPerson * nights)}</b></div>
            <div class="cl"><span>+ Anteil Gruppenausgaben</span><b>${formatCurrency(bd.groupExpenseShare)}</b></div>
            <div class="cl cl-total"><span>Zwischensumme</span><b>${formatCurrency(bd.subtotalBeforeMarkup)}</b></div>
          </div>
          <div class="calc-col">
            <div class="cl"><span>+ Aufschlag ${formatPercent(bd.markupPercent)}</span><b>${formatCurrency(bd.markupAmount)}</b></div>
            <div class="cl"><span>= nach Aufschlag</span><b>${formatCurrency(bd.subtotalBeforeClubFee)}</b></div>
            <div class="cl"><span>+ Vereinsgebühr ${formatPercent(bd.clubFeePercent)}</span><b>${formatCurrency(bd.clubFeeAmount)}</b></div>
            <div class="cl cl-total"><span>Endpreis berechnet</span><b class="fuf-pos">${formatCurrency(bd.finalPrice)}</b></div>
            <div class="cl cl-note"><span class="help">${prefillNote}</span><b>${formatCurrency(prefillValue)}</b></div>
          </div>
        </div>
      </div>
    </div>
  `;
}

function renderCalculationResult(result) {
  byId("result-placeholder").classList.add("d-none");
  byId("result-panel").classList.remove("d-none");

  const breakdowns = result.priceBreakdowns || {};
  const surplus = Number(result.surplus);

  byId("result-total-participants").textContent = String(result.totalParticipants ?? 0);
  byId("result-participants-split").textContent = participantsSplit(breakdowns);
  // DESIGN: Kurzinfo im Reisekopf. Zeitraum, Naechte und Teilnehmer kommen aus
  // demselben Ergebnis, das auch das Sticky-Panel fuellt.
  setTripEditorMeta(tripEditorMeta(result));
  byId("result-total-revenue").textContent = formatCurrency(result.totalCalculatedRevenue);
  byId("result-total-costs").textContent = formatCurrency(result.totalCalculatedCosts);
  // DESIGN: Die Farbe des Ueberschusses kommt von der KPI-Karte, weil .kpi b die
  // Textfarbe sonst ueberschreibt.
  byId("result-surplus").textContent = signedCurrency(surplus);
  byId("result-surplus-kpi").className = `kpi ${surplus < 0 ? "kpi-bad" : "kpi-good"}`;
  byId("result-margin").textContent = marginLabel(surplus, Number(result.totalCalculatedRevenue));

  byId("sum-kalkulation").classList.remove("d-none");
  byId("result-distribution-method").textContent = distributionLabels[result.distributionMethod] || result.distributionMethod;
  byId("result-spa-tax-age").textContent = `${result.spaTaxAgeThreshold} Jahre`;
  byId("result-adult-age").textContent = `${result.adultAgeThreshold} Jahre`;
  byId("result-start-date").textContent = formatDate(result.startDate);
  byId("result-end-date").textContent = result.endDate ? formatDate(result.endDate) : "-";
  byId("result-nights").textContent = String(result.nights ?? 0);
  setPanelRangeVisible(Boolean(result.startDate && result.endDate));
  byId("result-total-group-expenses").textContent = formatCurrency(result.totalGroupExpenses);

  // DESIGN: Panel und Summenspalte folgen dem Ergebnis, nicht der Vorschau.
  renderPanel(result, "calculated");
  renderRoomSums();
  renderSectionSummaries();
  renderJumpState();

  const container = byId("result-breakdowns-container");
  const categories = Object.keys(breakdowns);

  if (categories.length === 0) {
    container.innerHTML = '<p class="help calc-empty">Keine Aufschlüsselung vorhanden.</p>';
    return;
  }

  // DESIGN: Der Feldwert ist der Stand, mit dem gespeichert wird - auch wenn er
  // nur die Vorbelegung ist. So fixiert "Verkaufspreise uebernehmen" wie bisher
  // auch die gerundeten Vorschlaege, nicht nur die manuell geaenderten Felder.
  categories.forEach((category) => {
    state.salesPrices[category] = salesPriceForRow(category, breakdowns[category]);
  });

  // Die erste Kategorie steht offen, die uebrigen zugeklappt - wie im Mockup.
  container.innerHTML = categories
    .map((category, index) => calcRow(category, breakdowns[category], index === 0))
    .join("");
}

// DESIGN: Vorschau der Kalkulation aus den Feldwerten, damit das Sticky-Panel und
// die Summenspalte beim Tippen mitlaufen. Spiegelt PriceCalculatorService:
// Rundung nach jedem Schritt, Kategorien ohne Personen zaehlen nicht, Kinder
// zahlen keine Kurabgabe. Nach einer echten Berechnung zeigt das Panel die Zahlen
// des Backends - die Vorschau gilt nur fuer den ungespeicherten Zwischenstand und
// ist im Panel-Kopf als solche gekennzeichnet.

// PHP rundet mit Praezisionskorrektur. Ohne die liefert JS fuer Werte wie 1,005
// einen Cent weniger.

async function calculateTripResult(tripId) {
  const result = await api(`/api/trips/${tripId}/calculate`, { method: "POST" });
  renderCalculationResult(result);
}

function initCalculator() {
  byId("calculate-btn").addEventListener("click", async () => {
    const tripId = byId("tripFormId").value;
    if (!tripId) {
      alert("Bitte erst Reise speichern.");
      return;
    }

    try {
      await calculateTripResult(tripId);
    } catch (error) {
      alert("Fehler bei der Berechnung: " + error.message);
    }
  });

  // DESIGN: Die Verkaufspreis-Felder entstehen bei jeder Berechnung neu. Der
  // Listener haengt deshalb am statischen Container, nicht am Feld selbst.
  // DESIGN: Jede Feldaenderung rechnet die Vorschau neu. Ein Listener am Formular
  // reicht, weil input-Events von den Feldern hochblubbern.
  byId("trip-form").addEventListener("input", () => {
    renderLivePreview();
  });

  byId("result-breakdowns-container").addEventListener("input", (event) => {
    const input = event.target;
    if (!(input instanceof HTMLInputElement) || !input.dataset.salesCategory) {
      return;
    }

    state.salesPrices[input.dataset.salesCategory] = input.value.trim() === "" ? null : parseDecimal(input.value);
    renderLivePreview();
  });

  byId("sales-apply-btn").addEventListener("click", async () => {
    const tripId = byId("tripFormId").value;
    if (!tripId) {
      alert("Bitte erst Reise speichern und berechnen.");
      return;
    }

    try {
      const payload = tripPayloadFromForm();
      const trip = await api(`/api/trips/${tripId}`, { method: "PUT", body: JSON.stringify(payload) });
      fillTripForm(trip);
      await calculateTripResult(String(trip.id));
    } catch (error) {
      alert("Fehler beim Übernehmen der Verkaufspreise: " + error.message);
    }
  });


}
