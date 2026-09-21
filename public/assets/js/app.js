const state = {
  settings: null,
  currentTripId: null,
  settingsModal: null,
  inviteModal: null,
  usersModal: null,
  currentUser: null,
  trips: [],
  currentRegistrations: [],
  // DESIGN: Die Verkaufspreis-Felder sitzen jetzt in den Akkordeonzeilen, also in
  // Markup, das renderCalculationResult() erzeugt. Sie existieren damit erst nach
  // einer Berechnung - tripPayloadFromForm() braucht die Werte aber bei jedem
  // Speichern. Deshalb liegt der Stand hier und nicht nur im DOM.
  // ADULT gilt beim einheitlichen Erwachsenenpreis und ersetzt dann die beiden
  // Einzelkategorien.
  salesPrices: { ADULT_DOUBLE: null, ADULT_MULTI: null, ADULT: null, CHILD: null },
  formSnapshot: null,
  sectionObserver: null,
  currentSettlement: null,
  // DESIGN: Filter und Suche bei den Anmeldungen sind rein clientseitig.
  registrationFilter: { source: "all", query: "" },
  users: [],
  userSearch: "",
};

const categoryLabels = {
  ADULT_DOUBLE: "Erwachsener im Doppelzimmer",
  ADULT_MULTI: "Erwachsener im Mehrbettzimmer",
  ADULT: "Erwachsener",
  CHILD: "Kind",
  SPA_TAX: "Kurabgabe",
  SPA_TAX_CREDIT: "Kurabgabe (nicht pflichtig)",
};

// DESIGN: Kurzform fuer die Chips in der Teilnehmertabelle einer Anmeldung.
// Die Langform bleibt fuer Zimmerbedarf und Rueckerstattung.
const categoryShortLabels = {
  ADULT_DOUBLE: "Erw. DZ",
  ADULT_MULTI: "Erw. MBZ",
  ADULT: "Erw.",
  CHILD: "Kind",
};

const distributionLabels = {
  PER_PERSON: "Verteilung pro Person",
  PER_CATEGORY_UNITS: "Verteilung pro gebuchter Kategorie",
};

function routeForTrip(tripId) {
  return `/trips/${tripId}`;
}

function byId(id) {
  return document.getElementById(id);
}

function formatCurrency(value) {
  return new Intl.NumberFormat("de-DE", {
    style: "currency",
    currency: "EUR",
  }).format(Number(value || 0));
}

function formatDate(value) {
  if (typeof value !== "string") {
    return value;
  }

  const match = value.match(/^(\d{4})-(\d{1,2})-(\d{1,2})$/);
  if (!match) {
    return value;
  }

  const [, year, month, day] = match;
  return `${day.padStart(2, "0")}.${month.padStart(2, "0")}.${year}`;
}

// DESIGN: Alter bei Anreise. Dieselbe Regel wie im Backend
// (Participant::ageAtDate): volle Jahre am Anreisetag, der Geburtstag zaehlt
// erst ab dem Tag selbst. Kein Backend-Feld noetig.
function ageAtArrival(birthDate, startDate) {
  if (typeof birthDate !== "string" || typeof startDate !== "string") {
    return null;
  }

  const birth = birthDate.match(/^(\d{4})-(\d{1,2})-(\d{1,2})$/);
  const start = startDate.match(/^(\d{4})-(\d{1,2})-(\d{1,2})$/);
  if (!birth || !start) {
    return null;
  }

  const [, birthYear, birthMonth, birthDay] = birth.map(Number);
  const [, startYear, startMonth, startDay] = start.map(Number);

  let age = startYear - birthYear;
  if (startMonth < birthMonth || (startMonth === birthMonth && startDay < birthDay)) {
    age -= 1;
  }

  return age < 0 ? null : age;
}

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
  byId("trip-editor-meta").textContent = "";
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
  // In der schmalen Kontextleiste ist nur Platz fuer das Verb.
  const short = text.replace(/^Reise /, "");
  byId("trip-save-label-head").textContent = short.charAt(0).toUpperCase() + short.slice(1);
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
            <input id="${inputId}" class="calc-sales-input" type="number" step="0.01" min="0" value="${salesPrice ?? ""}"
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
  byId("trip-editor-meta").textContent = tripEditorMeta(result);
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
function round2(value) {
  if (!Number.isFinite(value)) {
    return 0;
  }

  return Math.round(Number((value * 100).toPrecision(12))) / 100;
}

function roundToNearest5(value) {
  return Math.round(value / 5) * 5;
}

function round4(value) {
  if (!Number.isFinite(value)) {
    return 0;
  }

  return Math.round(Number((value * 10000).toPrecision(12))) / 10000;
}

// Die Wertobjekte der Domaene runden jeden Geldbetrag auf zwei Stellen
// (RoomBooking::basePricePerPerson(), TripPricingPolicy::spaTaxPerPerson(),
// GroupExpense::amount()). Die Vorschau muss vor dem Rechnen dasselbe tun, sonst
// weicht sie bei Eingaben mit mehr Nachkommastellen ab.
function moneyFromField(id) {
  return round2(numberFromField(id));
}

// Percentage::factor() rundet auf vier Stellen.
function percentFactor(percent) {
  return round4(round2(percent) / 100);
}

function numberFromField(id) {
  const value = byId(id).value;
  const parsed = Number(value);
  return value === "" || !Number.isFinite(parsed) ? 0 : parsed;
}

// Wie Trip::nights(): Differenz in Tagen, 0 ohne Abreisedatum.
function nightsFromFields() {
  const start = byId("startDate").value;
  const end = byId("endDate").value;
  if (!start || !end) {
    return 0;
  }

  const diff = (Date.parse(`${end}T00:00:00Z`) - Date.parse(`${start}T00:00:00Z`)) / 86400000;
  return Number.isFinite(diff) && diff > 0 ? Math.round(diff) : 0;
}

function bookingsFromFields() {
  return [
    { categoryType: "ADULT_DOUBLE", count: numberFromField("adultDoubleCount"), basePricePerPerson: moneyFromField("adultDoublePrice") },
    { categoryType: "ADULT_MULTI", count: numberFromField("adultMultiCount"), basePricePerPerson: moneyFromField("adultMultiPrice") },
    { categoryType: "CHILD", count: numberFromField("childCount"), basePricePerPerson: moneyFromField("childPrice") },
  ];
}

// Spiegelt PricingCategory aus dem Backend: normalerweise eine Buchungszeile,
// beim einheitlichen Erwachsenenpreis fallen DZ und MBZ zu einer Kategorie mit
// gewichtetem Durchschnittspreis zusammen.
function pricingCategories(bookings, averageAdultPrice) {
  const asCategory = (booking) => ({
    key: booking.categoryType,
    count: booking.count,
    basePricePerPerson: booking.basePricePerPerson,
    spaTaxLiable: booking.categoryType !== "CHILD",
  });

  if (!averageAdultPrice) {
    return bookings.map(asCategory);
  }

  const adults = bookings.filter((booking) => booking.categoryType !== "CHILD");
  const others = bookings.filter((booking) => booking.categoryType === "CHILD").map(asCategory);

  if (adults.length === 0) {
    return others;
  }

  const count = adults.reduce((sum, booking) => sum + booking.count, 0);
  const weightedSum = adults.reduce((sum, booking) => sum + booking.basePricePerPerson * booking.count, 0);
  // Die Herleitung fehlt hier bewusst: die Vorschau speist nur das Sticky-Panel,
  // die Akkordeonzeilen kommen immer aus der Backend-Antwort.
  return [
    {
      key: "ADULT",
      count,
      basePricePerPerson: count === 0 ? 0 : round2(weightedSum / count),
      spaTaxLiable: true,
    },
    ...others,
  ];
}

// Kosten sind, was die Unterkunft tatsaechlich kostet - der gemittelte Preis ist
// eine Frage der Preisbildung und aendert daran nichts.
function lodgingCosts(bookings, nights, spaTaxPerPerson) {
  let base = 0;
  let spaTax = 0;

  bookings.forEach((booking) => {
    base = round2(base + round2(round2(booking.basePricePerPerson * nights) * booking.count));
    const spaTaxPerNight = booking.categoryType === "CHILD" ? 0 : spaTaxPerPerson;
    spaTax = round2(spaTax + round2(round2(spaTaxPerNight * nights) * booking.count));
  });

  return { base, spaTax };
}

function previewCalculation() {
  const nights = nightsFromFields();
  const bookings = bookingsFromFields();
  const distributionMethod = byId("distributionMethod").value;
  const averageAdultPrice = byId("averageAdultPrice").checked;
  const markupPercent = round2(numberFromField("markupPercent"));
  const clubFeePercent = round2(numberFromField("clubFeePercent"));
  const spaTaxPerPerson = moneyFromField("spaTaxPerPerson");

  const expenseLabel = byId("expenseLabel").value.trim();
  const expenseAmount = byId("expenseAmount").value;
  const totalGroupExpenses = expenseLabel !== "" && expenseAmount !== ""
    ? moneyFromField("expenseAmount")
    : 0;

  const categories = pricingCategories(bookings, averageAdultPrice);

  // PerPersonDistributionStrategy summiert die Personen,
  // PerCategoryUnitsDistributionStrategy zaehlt die belegten Kategorien. Beim
  // einheitlichen Erwachsenenpreis sind die Erwachsenen dabei eine Einheit.
  const denominator = Math.max(1, distributionMethod === "PER_CATEGORY_UNITS"
    ? categories.filter((category) => category.count > 0).length
    : categories.reduce((sum, category) => sum + category.count, 0));
  const sharedExpensePerUnit = round2(totalGroupExpenses / denominator);

  const priceBreakdowns = {};
  let totalParticipants = 0;
  let totalCalculatedRevenue = 0;

  categories.forEach((category) => {
    if (category.count === 0) {
      return;
    }

    const groupExpenseShare = distributionMethod === "PER_CATEGORY_UNITS"
      ? round2(sharedExpensePerUnit / category.count)
      : sharedExpensePerUnit;
    const spaTaxPerNight = category.spaTaxLiable ? spaTaxPerPerson : 0;
    const baseTotalPerPerson = round2(category.basePricePerPerson * nights);
    const spaTaxTotalPerPerson = round2(spaTaxPerNight * nights);
    const subtotalBeforeMarkup = round2(round2(baseTotalPerPerson + spaTaxTotalPerPerson) + groupExpenseShare);
    const markupAmount = round2(subtotalBeforeMarkup * percentFactor(markupPercent));
    const subtotalBeforeClubFee = round2(subtotalBeforeMarkup + markupAmount);
    const clubFeeAmount = round2(subtotalBeforeClubFee * percentFactor(clubFeePercent));
    const finalPrice = round2(subtotalBeforeClubFee + clubFeeAmount);
    const salesPriceDefault = roundToNearest5(finalPrice);
    const salesPrice = state.salesPrices[category.key] != null
      ? round2(state.salesPrices[category.key])
      : salesPriceDefault;
    const categoryRevenue = round2(salesPrice * category.count);

    priceBreakdowns[category.key] = {
      basePricePerPerson: category.basePricePerPerson,
      spaTaxPerPerson: spaTaxPerNight,
      nights,
      baseTotalPerPerson,
      spaTaxTotalPerPerson,
      groupExpenseShare,
      subtotalBeforeMarkup,
      markupPercent,
      markupAmount,
      subtotalBeforeClubFee,
      clubFeePercent,
      clubFeeAmount,
      finalPrice,
      salesPricePerPerson: salesPrice,
      salesPriceDefault,
      salesPriceOverridden: state.salesPrices[category.key] != null,
      count: category.count,
      categoryRevenue,
    };

    totalCalculatedRevenue = round2(totalCalculatedRevenue + categoryRevenue);
    totalParticipants += category.count;
  });

  const costs = lodgingCosts(bookings, nights, spaTaxPerPerson);
  const totalCalculatedCosts = round2(costs.base + costs.spaTax + totalGroupExpenses);

  return {
    priceBreakdowns,
    totalGroupExpenses,
    totalParticipants,
    totalCalculatedRevenue,
    totalCalculatedCosts,
    surplus: round2(totalCalculatedRevenue - totalCalculatedCosts),
    distributionMethod,
    spaTaxAgeThreshold: numberFromField("spaTaxAgeThreshold"),
    adultAgeThreshold: numberFromField("adultAgeThreshold"),
    averageAdultPrice,
    startDate: byId("startDate").value || null,
    endDate: byId("endDate").value || null,
    nights,
  };
}

// DESIGN: Zahlen im Sticky-Panel. Quelle ist entweder das Ergebnis des Backends
// ("calculated") oder die Vorschau aus den Feldern ("preview"); der Punkt im
// Panel-Kopf sagt, welche von beiden gerade zu sehen ist.
function renderPanel(result, source) {
  const breakdowns = result.priceBreakdowns || {};
  const nights = Number(result.nights ?? 0);
  const categories = Object.values(breakdowns);

  const lodgingCosts = categories.reduce(
    (sum, bd) => round2(sum + round2(Number(bd.baseTotalPerPerson ?? 0) * Number(bd.count ?? 0))),
    0
  );
  const spaTaxCosts = categories.reduce(
    (sum, bd) => round2(sum + round2(Number(bd.spaTaxTotalPerPerson ?? 0) * Number(bd.count ?? 0))),
    0
  );
  const spaTaxPersons = categories.reduce(
    (sum, bd) => sum + (Number(bd.spaTaxPerPerson ?? 0) > 0 ? Number(bd.count ?? 0) : 0),
    0
  );
  const spaTaxPerPerson = categories.reduce(
    (value, bd) => (Number(bd.spaTaxPerPerson ?? 0) > 0 ? Number(bd.spaTaxPerPerson) : value),
    0
  );

  byId("panel-participants").textContent = String(result.totalParticipants ?? 0);
  byId("panel-participants-sub").textContent = participantsSplit(breakdowns);
  byId("result-nights").textContent = String(nights);
  byId("result-start-date").textContent = result.startDate ? formatDate(result.startDate) : "–";
  byId("result-end-date").textContent = result.endDate ? formatDate(result.endDate) : "–";
  setPanelRangeVisible(Boolean(result.startDate && result.endDate));

  byId("panel-cost-lodging-label").textContent = nights > 0
    ? `Unterkunft (${nights} ${nights === 1 ? "Nacht" : "Nächte"})`
    : "Unterkunft";
  byId("panel-cost-lodging").textContent = formatCurrency(lodgingCosts);
  byId("panel-cost-spa-tax-label").textContent = spaTaxPersons > 0
    ? `Kurabgabe ${spaTaxPersons} × ${formatCurrency(spaTaxPerPerson)} × ${nights}`
    : "Kurabgabe";
  byId("panel-cost-spa-tax").textContent = formatCurrency(spaTaxCosts);
  byId("panel-cost-extra").textContent = formatCurrency(result.totalGroupExpenses ?? 0);
  byId("panel-cost-total").textContent = formatCurrency(result.totalCalculatedCosts ?? 0);
  byId("panel-revenue").textContent = formatCurrency(result.totalCalculatedRevenue ?? 0);

  renderPanelBar(Number(result.totalCalculatedCosts ?? 0), Number(result.surplus ?? 0));

  const surplus = Number(result.surplus ?? 0);
  byId("panel-surplus").textContent = signedCurrency(surplus);
  byId("panel-green").className = surplus < 0 ? "pnl-green pnl-green-bad" : "pnl-green";
  renderHeadKpi();

  // Beim einheitlichen Erwachsenenpreis gibt es statt der beiden Zimmerkategorien
  // nur noch eine Zahl.
  const adultPrices = breakdowns.ADULT !== undefined
    ? [breakdowns.ADULT.salesPricePerPerson]
    : [breakdowns.ADULT_DOUBLE?.salesPricePerPerson, breakdowns.ADULT_MULTI?.salesPricePerPerson];
  const child = breakdowns.CHILD?.salesPricePerPerson;
  byId("panel-sales-adults").textContent = adultPrices
    .filter((value) => value != null)
    .map((value) => formatCurrency(value))
    .join(" / ") || "–";
  byId("panel-sales-child").textContent = child != null ? formatCurrency(child) : "–";

  setPanelState(source);
}

// Der Balken zeigt, welchen Anteil der Einnahmen die Kosten ausmachen. Bei einem
// Defizit gibt es keinen Ueberschussanteil - der Balken ist dann voll Kosten.
function renderPanelBar(costs, surplus) {
  const total = costs + Math.max(0, surplus);
  const costShare = total > 0 ? (costs / total) * 100 : 0;
  const surplusShare = total > 0 ? 100 - costShare : 0;

  byId("panel-bar-costs").style.width = `${costShare}%`;
  byId("panel-bar-surplus").style.width = `${surplusShare}%`;
  byId("panel-bar-costs-label").textContent = total > 0
    ? `Kosten ${formatPercentValue(costShare)}`
    : "Kosten";
  byId("panel-bar-surplus-label").textContent = surplus < 0
    ? "Defizit"
    : total > 0 ? `Überschuss ${formatPercentValue(surplusShare)}` : "Überschuss";
}

function formatPercentValue(value) {
  return `${value.toLocaleString("de-DE", { minimumFractionDigits: 1, maximumFractionDigits: 1 })} %`;
}

function setPanelState(source) {
  const state_ = byId("panel-state");
  if (source === "preview") {
    state_.textContent = "Vorschau";
    state_.className = "pnl-state pnl-state-preview";
    return;
  }

  if (source === "calculated") {
    state_.textContent = "berechnet";
    state_.className = "pnl-state";
    return;
  }

  state_.textContent = "";
  state_.className = "pnl-state d-none";
}

function setPanelRangeVisible(visible) {
  byId("panel-range-sep").classList.toggle("d-none", !visible);
  byId("result-end-date").classList.toggle("d-none", !visible);
}

function resetPanel() {
  byId("panel-participants").textContent = "–";
  byId("panel-participants-sub").textContent = "";
  byId("panel-cost-lodging-label").textContent = "Unterkunft";
  byId("panel-cost-lodging").textContent = "–";
  byId("panel-cost-spa-tax-label").textContent = "Kurabgabe";
  byId("panel-cost-spa-tax").textContent = "–";
  byId("panel-cost-extra").textContent = "–";
  byId("panel-cost-total").textContent = "–";
  byId("panel-revenue").textContent = "–";
  byId("panel-bar-costs").style.width = "0%";
  byId("panel-bar-surplus").style.width = "0%";
  byId("panel-bar-costs-label").textContent = "Kosten";
  byId("panel-bar-surplus-label").textContent = "Überschuss";
  byId("panel-surplus").textContent = "–";
  byId("panel-green").className = "pnl-green";
  byId("panel-sales-adults").textContent = "–";
  byId("panel-sales-child").textContent = "–";
  setPanelRangeVisible(false);
  setPanelState(null);
}

// DESIGN: Summenspalte im Unterkunft-Raster: Anzahl × Preis/Nacht × Naechte.
function renderRoomSums() {
  const nights = nightsFromFields();
  byId("room-sum-nights").textContent = String(nights);

  const rows = [
    ["room-sum-adult-double", "adultDoubleCount", "adultDoublePrice"],
    ["room-sum-adult-multi", "adultMultiCount", "adultMultiPrice"],
    ["room-sum-child", "childCount", "childPrice"],
  ];

  rows.forEach(([target, countId, priceId]) => {
    const total = round2(round2(numberFromField(priceId) * nights) * numberFromField(countId));
    byId(target).textContent = formatCurrency(total);
  });

  const adultAge = byId("adultAgeThreshold").value;
  byId("room-cat-child-sub").textContent = adultAge === ""
    ? "unter der Altersgrenze"
    : `unter ${adultAge} Jahren`;
}

// DESIGN: Ein Listener am Formular genuegt - er faengt jede Feldaenderung und
// rechnet die Vorschau neu.
function renderLivePreview() {
  renderRoomSums();
  renderPanel(previewCalculation(), "preview");
  renderSectionSummaries();
  renderRoomPlanTargets();
  renderDirtyState();
  renderJumpState();
}


// DESIGN: Zusammenfassung im Kopf jeder Section. Sie bleibt zugeklappt sichtbar
// und muss deshalb aus den Feldwerten kommen, nicht aus einem Ergebnis.
function renderSectionSummaries() {
  const nights = nightsFromFields();
  const participants = numberFromField("adultDoubleCount")
    + numberFromField("adultMultiCount")
    + numberFromField("childCount");

  const startDate = byId("startDate").value;
  const endDate = byId("endDate").value;
  byId("sum-eckdaten").textContent = startDate
    ? [formatTripPeriod({ startDate, endDate: endDate || null }), nights > 0 ? `${nights} ${nights === 1 ? "Nacht" : "Nächte"}` : null]
      .filter(Boolean)
      .join(" · ")
    : "";

  byId("sum-aufschlaege").textContent = [
    formatPercent(round2(numberFromField("markupPercent"))),
    formatPercent(round2(numberFromField("clubFeePercent"))),
    `Kurabgabe ${formatCurrency(moneyFromField("spaTaxPerPerson"))}`,
  ].join(" · ");

  const adultAge = byId("adultAgeThreshold").value;
  byId("sum-unterkunft").textContent = [
    `${participants} ${participants === 1 ? "Person" : "Personen"}`,
    adultAge === "" ? null : `Erwachsen ab ${adultAge} J.`,
  ].filter(Boolean).join(" · ");

  const expenseLabel = byId("expenseLabel").value.trim();
  const expenseAmount = byId("expenseAmount").value;
  if (expenseLabel === "" || expenseAmount === "") {
    byId("sum-zusatz").textContent = "kein Posten";
  } else {
    byId("sum-zusatz").textContent = [
      "1 Posten",
      formatCurrency(moneyFromField("expenseAmount")),
      participants > 0 ? `wird auf alle ${participants} Teilnehmer umgelegt` : null,
    ].filter(Boolean).join(" · ");
  }
}

// DESIGN: Ungespeicherte Aenderungen. Verglichen wird gegen einen Schnappschuss
// der Feldwerte vom letzten Laden oder Speichern - "einmal angefasst" reicht
// nicht, ein auf den Ausgangswert zurueckgesetztes Feld gilt wieder als sauber.
function tripFormFields() {
  return Array.from(byId("trip-form").querySelectorAll("input, select"))
    .filter((field) => field.type !== "hidden");
}

// Bei einer Checkbox ist value konstant - hier zaehlt der Haken.
function fieldState(field) {
  return field.type === "checkbox" ? String(field.checked) : field.value;
}

function snapshotTripForm() {
  const snapshot = {};
  tripFormFields().forEach((field) => {
    snapshot[field.id] = fieldState(field);
  });
  state.formSnapshot = snapshot;
  renderDirtyState();
}

function renderDirtyState() {
  const snapshot = state.formSnapshot;
  let changed = 0;

  tripFormFields().forEach((field) => {
    const isDirty = snapshot !== null && snapshot[field.id] !== undefined && fieldState(field) !== snapshot[field.id];
    if (isDirty) {
      changed += 1;
    }

    const wrapper = field.closest(".in");
    if (wrapper) {
      wrapper.classList.toggle("is-dirty", isDirty);
    }
  });

  byId("trip-dirty-note").textContent = changed === 0
    ? ""
    : `Ungespeicherte Änderungen: ${changed} ${changed === 1 ? "Feld" : "Felder"}`;
  renderStaleNote(changed);
}

// DESIGN: Status-Punkt je Abschnitt - gefuellt, sobald der Abschnitt Inhalt hat.
function renderJumpState() {
  const registrations = state.currentRegistrations || [];
  const settlement = state.currentSettlement;
  const filled = {
    "sec-eckdaten": byId("name").value.trim() !== "" && byId("startDate").value !== "",
    "sec-aufschlaege": byId("markupPercent").value !== "",
    "sec-unterkunft": numberFromField("adultDoubleCount") + numberFromField("adultMultiCount") + numberFromField("childCount") > 0,
    "sec-zusatz": byId("expenseLabel").value.trim() !== "" && byId("expenseAmount").value !== "",
    "sec-kalkulation": !byId("result-panel").classList.contains("d-none"),
    "registrations-section": registrations.length > 0,
    "room-summary-section": registrations.length > 0,
    // Abrechnung: derselbe Mechanismus, nur aus dem Settlement gespeist.
    "sec-geplante-kosten": settlement !== null && (settlement.plannedCostItems || []).length > 0,
    "sec-zusatzausgaben": settlement !== null && (settlement.expenses || []).length > 0,
    "sec-gesamtabrechnung": settlement !== null,
    "refund-section": settlement !== null && registrations.some((reg) => reg.billingTotal !== null && reg.billingTotal > 0),
  };

  Object.entries(filled).forEach(([sectionId, isFilled]) => {
    const dot = document.querySelector(`.jump[href="#${sectionId}"] .dot`);
    if (dot) {
      dot.classList.toggle("is-set", isFilled);
    }
  });
}

// DESIGN: Sticky Kontextleiste. Umschaltpunkt ist die Scrollposition und kein
// Sentinel: .trip-head zieht sich mit negativen Raendern aus dem Innenabstand
// von .app-main heraus, ein Sentinel darueber muesste sich darin zurechtfinden.
const HEAD_SCROLL_THRESHOLD = 120;

function renderHeadScrollState() {
  byId("trip-head").classList.toggle("is-scrolled", window.scrollY > HEAD_SCROLL_THRESHOLD);
}

function observeHeadScroll() {
  let pending = false;

  window.addEventListener("scroll", () => {
    if (pending) {
      return;
    }
    pending = true;
    window.requestAnimationFrame(() => {
      pending = false;
      renderHeadScrollState();
    });
  }, { passive: true });

  renderHeadScrollState();
}

function activeTabName() {
  return byId("tab-abrechnung").classList.contains("active") ? "abrechnung" : "planung";
}

// DESIGN: Die wichtigste Zahl des jeweiligen Bereichs, damit man sie aus jeder
// Scrollposition sieht. Sie wird nicht neu gerechnet, sondern von der Stelle
// abgelesen, die sie ohnehin schon anzeigt.
function renderHeadKpi() {
  const isSettlement = activeTabName() === "abrechnung";
  const source = isSettlement ? byId("settlement-surplus") : byId("panel-surplus");
  const value = source.textContent.trim();

  byId("trip-head-kpi-label").textContent = isSettlement ? "Überschuss Ist" : "Überschuss";
  byId("trip-head-kpi-value").textContent = value === "" ? "–" : value;
  byId("trip-head-kpi-value").className = value.includes("−") || value.startsWith("-")
    ? "fuf-neg"
    : "";
}

function renderHeadTabState() {
  byId("trip-head").classList.toggle("is-abrechnung", activeTabName() === "abrechnung");
  renderHeadKpi();
}

// DESIGN: Der Tab steht im Hash, damit Zurueck-Button, Neuladen und geteilte
// Links denselben Bereich zeigen.
function tabFromHash() {
  return window.location.hash === "#abrechnung" ? "abrechnung" : "planung";
}

function showTab(name) {
  if (activeTabName() === name) {
    return;
  }

  const trigger = byId(name === "abrechnung" ? "tab-abrechnung" : "tab-planung");
  if (window.bootstrap && window.bootstrap.Tab) {
    window.bootstrap.Tab.getOrCreateInstance(trigger).show();
  } else {
    trigger.click();
  }

  // Wer ueber die Karte am Seitenende wechselt, stuende sonst im neuen
  // Bereich ganz unten.
  window.scrollTo({ top: 0 });
}

// DESIGN: Derselbe Zielort, drei Einstiege - Tab-Chip, Karte am Seitenende und
// Eintrag in der Sprungnavigation. Sie zeigen alle denselben Stand des jeweils
// anderen Bereichs, also fuellt eine Funktion sie gemeinsam.
function renderSwitchTargets() {
  const registrations = state.currentRegistrations || [];
  const settlement = state.currentSettlement;
  const participants = registrations.reduce((sum, reg) => sum + reg.participants.length, 0);
  const expenses = settlement ? (settlement.expenses || []) : [];

  setTabChip("tab-planung-count", registrations.length);
  setTabChip("tab-abrechnung-count", expenses.length);

  byId("jump-to-abrechnung-count").textContent = expenses.length === 0
    ? ""
    : `${expenses.length} ${expenses.length === 1 ? "Ausgabe" : "Ausgaben"}`;
  byId("jump-to-planung-count").textContent = participants === 0 ? "" : `${participants} Pers.`;

  byId("switch-to-abrechnung-sub").textContent = [
    "Belege und tatsächliche Ausgaben erfassen",
    expenses.length === 0 ? null : `${expenses.length} ${expenses.length === 1 ? "Posten" : "Posten"}`,
    settlement === null ? null : formatCurrency(settlement.totalAdditionalExpenses),
  ].filter(Boolean).join(" · ");

  byId("switch-to-planung-sub").textContent = [
    "Kalkulation, Verkaufspreise und Anmeldungen",
    participants === 0 ? null : `${participants} Teilnehmer`,
  ].filter(Boolean).join(" · ");
}

function setTabChip(elementId, count) {
  const chip = byId(elementId);
  chip.textContent = String(count);
  chip.classList.toggle("d-none", count === 0);
}

// DESIGN: Der Tab-Wechsel verwirft nichts - die Feldwerte bleiben stehen. Die
// Abrechnung rechnet aber mit dem gespeicherten Stand, zeigt also veraltete
// Zahlen. Genau das sagt dieser Hinweis, statt vor einem Verlust zu warnen,
// den es nicht gibt.
function renderStaleNote(changed) {
  byId("settlement-stale-note").classList.toggle("d-none", changed === 0);
  byId("settlement-stale-text").textContent = changed === 0
    ? ""
    : `Die Planung hat ${changed} ungespeicherte ${changed === 1 ? "Änderung" : "Änderungen"}. Die Zahlen hier zeigen den gespeicherten Stand.`;
}

function writeTabHash(name) {
  const hash = `#${name}`;
  if (window.location.hash === hash) {
    return;
  }
  window.history.pushState({}, "", window.location.pathname + hash);
}

// DESIGN: Der aktive Eintrag folgt dem Abschnitt, der gerade oben im Blickfeld
// steht. Der Observer wird beim Oeffnen des Editors aufgebaut.
function observeSections() {
  if (state.sectionObserver) {
    state.sectionObserver.disconnect();
  }

  const links = Array.from(document.querySelectorAll(".jump"));
  const sections = links
    .map((link) => document.getElementById(link.getAttribute("href").slice(1)))
    .filter(Boolean);

  if (sections.length === 0 || typeof IntersectionObserver !== "function") {
    return;
  }

  const visible = new Set();
  state.sectionObserver = new IntersectionObserver((entries) => {
    entries.forEach((entry) => {
      if (entry.isIntersecting) {
        visible.add(entry.target.id);
      } else {
        visible.delete(entry.target.id);
      }
    });

    const activeId = sections.map((section) => section.id).find((id) => visible.has(id));
    links.forEach((link) => {
      link.classList.toggle("is-active", link.getAttribute("href") === `#${activeId}`);
    });
  }, { rootMargin: "-80px 0px -60% 0px" });

  sections.forEach((section) => state.sectionObserver.observe(section));
}

async function api(url, options = {}) {
  const response = await fetch(url, {
    headers: { "Content-Type": "application/json" },
    ...options,
  });

  const text = await response.text();
  const data = text ? JSON.parse(text) : {};

  if (!response.ok) {
    throw new Error(JSON.stringify(data));
  }

  return data;
}

function showListSection() {
  byId("trip-list-section").classList.remove("d-none");
  byId("trip-editor-section").classList.add("d-none");
  window.scrollTo({ top: 0 });
  if (state.sectionObserver) {
    state.sectionObserver.disconnect();
    state.sectionObserver = null;
  }
}

function showEditorSection(titleText, breadcrumbText) {
  byId("trip-editor-title").textContent = titleText;
  // DESIGN: Die Ueberschrift traegt den Reisenamen, der Breadcrumb die Nummer.
  byId("trip-editor-breadcrumb").textContent = breadcrumbText;
  byId("trip-list-section").classList.add("d-none");
  byId("trip-editor-section").classList.remove("d-none");
  // Mit der sticky Kontextleiste faellt eine mitgeschleppte Scrollposition
  // auf: man landet mitten in der Seite und die Leiste ist schon geschrumpft.
  window.scrollTo({ top: 0 });
  renderHeadScrollState();
  renderHeadTabState();
  observeSections();
}

function matchRoute(pathname) {
  if (pathname === "/") {
    return { name: "list" };
  }

  if (pathname === "/trips/new") {
    return { name: "new-trip" };
  }

  const tripMatch = pathname.match(/^\/trips\/(\d+)$/);
  if (tripMatch) {
    return { name: "trip-detail", tripId: Number(tripMatch[1]) };
  }

  return { name: "list" };
}

async function navigateTo(pathname, options = {}) {
  const { replace = false } = options;
  const currentPath = window.location.pathname;

  if (currentPath !== pathname) {
    const method = replace ? "replaceState" : "pushState";
    window.history[method]({}, "", pathname);
  }

  await renderCurrentRoute();
}

async function renderCurrentRoute(options = {}) {
  const { reuseLoadedTrip = false } = options;
  const route = matchRoute(window.location.pathname);

  if (route.name === "list") {
    state.currentTripId = null;
    resetCalculationResult();
    await loadTripList();
    showListSection();
    return;
  }

  if (route.name === "new-trip") {
    state.currentTripId = null;
    clearTripFormWithDefaults();
    showEditorSection("Neue Reise", "Neue Reise");
    return;
  }

  if (route.name === "trip-detail") {
    // Zurueck zwischen zwei Tabs derselben Reise ist ein Hash-Wechsel. Ohne
    // diese Abkuerzung wuerde jeder davon die Reise komplett neu laden. Sie
    // gilt nur fuer die History-Navigation - wer hier absichtlich hinnavigiert,
    // etwa nach dem Speichern, will den frisch geladenen Stand sehen.
    if (reuseLoadedTrip && state.currentTripId === route.tripId) {
      showTab(tabFromHash());
      return;
    }

    await openTrip(route.tripId);
  }
}

function fillSettingsForm(settings) {
  const form = byId("settings-form");
  form.defaultMarkupPercent.value = settings.defaultMarkupPercent;
  form.defaultClubFeePercent.value = settings.defaultClubFeePercent;
  form.defaultDistributionMethod.value = settings.defaultDistributionMethod;
  form.defaultSpaTaxPerPerson.value = settings.defaultSpaTaxPerPerson;
  form.defaultSpaTaxAgeThreshold.value = settings.defaultSpaTaxAgeThreshold;
  form.defaultAdultAgeThreshold.value = settings.defaultAdultAgeThreshold;
}

function clearTripFormWithDefaults() {
  const form = byId("trip-form");
  form.reset();
  byId("tripFormId").value = "";
  resetCalculationResult();
  resetRegistrations();
  resetSalesPrices();
  setTripSaveLabel("Reise speichern");

  form.adultDoubleCount.value = 0;
  form.adultDoublePrice.value = 0;
  form.adultMultiCount.value = 0;
  form.adultMultiPrice.value = 0;
  form.childCount.value = 0;
  form.childPrice.value = 0;
  form.averageAdultPrice.checked = false;

  if (state.settings) {
    form.markupPercent.value = state.settings.defaultMarkupPercent;
    form.clubFeePercent.value = state.settings.defaultClubFeePercent;
    form.distributionMethod.value = state.settings.defaultDistributionMethod;
    form.spaTaxPerPerson.value = state.settings.defaultSpaTaxPerPerson;
    form.spaTaxAgeThreshold.value = state.settings.defaultSpaTaxAgeThreshold;
    form.adultAgeThreshold.value = state.settings.defaultAdultAgeThreshold;
  }

  resetSettlement();
  snapshotTripForm();
  renderSectionSummaries();
  renderRoomSums();
  renderJumpState();
}

function tripPayloadFromForm() {
  const form = byId("trip-form");
  const expenses = [];
  if (form.expenseLabel.value.trim() !== "" && form.expenseAmount.value !== "") {
    expenses.push({
      label: form.expenseLabel.value.trim(),
      amount: Number(form.expenseAmount.value),
    });
  }

  const averageAdultPrice = form.averageAdultPrice.checked;
  // Beim einheitlichen Erwachsenenpreis gibt es nur ein Verkaufspreisfeld. Es geht
  // auf beide Zimmerzeilen, damit der gespeicherte Stand zum angezeigten passt.
  const adultSalesPrice = averageAdultPrice ? state.salesPrices.ADULT : null;

  return {
    name: form.name.value,
    startDate: form.startDate.value,
    endDate: form.endDate.value,
    markupPercent: Number(form.markupPercent.value),
    clubFeePercent: Number(form.clubFeePercent.value),
    distributionMethod: form.distributionMethod.value,
    spaTaxPerPerson: Number(form.spaTaxPerPerson.value),
    spaTaxAgeThreshold: Number(form.spaTaxAgeThreshold.value),
    adultAgeThreshold: Number(form.adultAgeThreshold.value),
    averageAdultPrice,
    spaTaxCount: Number(form.spaTaxCount.value),
    bookings: [
      {
        categoryType: "ADULT_DOUBLE",
        count: Number(form.adultDoubleCount.value),
        basePricePerPerson: Number(form.adultDoublePrice.value),
        salesPricePerPerson: averageAdultPrice ? adultSalesPrice : state.salesPrices.ADULT_DOUBLE,
      },
      {
        categoryType: "ADULT_MULTI",
        count: Number(form.adultMultiCount.value),
        basePricePerPerson: Number(form.adultMultiPrice.value),
        salesPricePerPerson: averageAdultPrice ? adultSalesPrice : state.salesPrices.ADULT_MULTI,
      },
      {
        categoryType: "CHILD",
        count: Number(form.childCount.value),
        basePricePerPerson: Number(form.childPrice.value),
        salesPricePerPerson: state.salesPrices.CHILD,
      },
    ],
    groupExpenses: expenses,
  };
}

function fillTripForm(trip) {
  const form = byId("trip-form");
  byId("tripFormId").value = String(trip.id);
  form.name.value = trip.name;
  form.startDate.value = trip.startDate;
  form.endDate.value = trip.endDate ?? "";
  form.markupPercent.value = trip.markupPercent;
  form.clubFeePercent.value = trip.clubFeePercent;
  form.distributionMethod.value = trip.distributionMethod;
  form.spaTaxPerPerson.value = trip.spaTaxPerPerson;
  form.spaTaxAgeThreshold.value = trip.spaTaxAgeThreshold;
  form.adultAgeThreshold.value = trip.adultAgeThreshold ?? 16;
  form.averageAdultPrice.checked = Boolean(trip.averageAdultPrice);
  form.spaTaxCount.value = trip.spaTaxCount ?? 0;

  const byType = {};
  trip.bookings.forEach((booking) => {
    byType[booking.categoryType] = booking;
  });

  form.adultDoubleCount.value = byType.ADULT_DOUBLE ? byType.ADULT_DOUBLE.count : 0;
  form.adultDoublePrice.value = byType.ADULT_DOUBLE ? byType.ADULT_DOUBLE.basePricePerPerson : 0;
  form.adultMultiCount.value = byType.ADULT_MULTI ? byType.ADULT_MULTI.count : 0;
  form.adultMultiPrice.value = byType.ADULT_MULTI ? byType.ADULT_MULTI.basePricePerPerson : 0;
  form.childCount.value = byType.CHILD ? byType.CHILD.count : 0;
  form.childPrice.value = byType.CHILD ? byType.CHILD.basePricePerPerson : 0;
  // DESIGN: Gespeicherte Verkaufspreise in den Zustand, nicht ins DOM - die
  // Felder gibt es vor der ersten Berechnung noch nicht. Ohne das wuerde ein
  // Speichern die bereits gesetzten Verkaufspreise auf null zuruecksetzen.
  state.salesPrices = {
    ADULT_DOUBLE: byType.ADULT_DOUBLE?.salesPricePerPerson ?? null,
    ADULT_MULTI: byType.ADULT_MULTI?.salesPricePerPerson ?? null,
    // Bei einheitlichem Preis tragen beide Zeilen denselben Wert - die erste
    // gesetzte gewinnt, genau wie im Backend.
    ADULT: byType.ADULT_DOUBLE?.salesPricePerPerson ?? byType.ADULT_MULTI?.salesPricePerPerson ?? null,
    CHILD: byType.CHILD?.salesPricePerPerson ?? null,
  };

  const firstExpense = trip.groupExpenses.length > 0 ? trip.groupExpenses[0] : null;
  form.expenseLabel.value = firstExpense ? firstExpense.label : "";
  form.expenseAmount.value = firstExpense ? firstExpense.amount : "";

  setTripSaveLabel("Reise aktualisieren");
  // DESIGN: Der gefuellte Stand ist der gespeicherte - ab hier zaehlt jede
  // Abweichung als ungespeicherte Aenderung.
  snapshotTripForm();
  renderSectionSummaries();
  renderRoomSums();
  renderJumpState();
}

async function loadSettings() {
  state.settings = await api("/api/settings");
  fillSettingsForm(state.settings);
}

// DESIGN: "2027-02-10" + "2027-02-14" -> "10.–14.02.2027". Gemeinsame Bestandteile
// werden zusammengezogen, wie in Mockup 01.
function formatTripPeriod(trip) {
  const start = formatDate(trip.startDate);
  if (!trip.endDate) {
    return start;
  }

  const end = formatDate(trip.endDate);
  const [startDay, startMonth, startYear] = String(start).split(".");
  const [endDay, endMonth, endYear] = String(end).split(".");

  if (startYear !== endYear) {
    return `${start} – ${end}`;
  }

  if (startMonth !== endMonth) {
    return `${startDay}.${startMonth}.–${endDay}.${endMonth}.${endYear}`;
  }

  return `${startDay}.–${endDay}.${endMonth}.${endYear}`;
}

// DESIGN: Dieselbe Hybrid-Logik, nach der auch surplusBasis umschaltet: sobald es
// Anmeldungen gibt, zaehlt der Ist-Stand, sonst die Planung.
function effectiveParticipants(trip) {
  return trip.registeredParticipants > 0 ? trip.registeredParticipants : trip.plannedParticipants;
}

// DESIGN: Ueberschuss farbig, bei reiner Kalkulation zusaetzlich als "geplant" markiert.
function tripSurplusCell(trip) {
  if (trip.surplus === null || trip.surplus === undefined) {
    return '<td class="r">–</td>';
  }

  const toneClass = trip.surplus < 0 ? "fuf-neg" : "fuf-pos";
  const sign = trip.surplus < 0 ? "− " : "+ ";
  const amount = formatCurrency(Math.abs(trip.surplus));
  const plannedChip = trip.surplusBasis === "calculation"
    ? ' <span class="chip c-grey">geplant</span>'
    : "";

  return `<td class="r"><span class="${toneClass}">${sign}${amount}</span>${plannedChip}</td>`;
}

function tripListRow(trip) {
  const nightsInfo = trip.endDate
    ? `<span class="help tb-sub">${trip.nights} ${trip.nights === 1 ? "Nacht" : "Nächte"}</span>`
    : "";

  return `
      <td class="help fuf-num">#${trip.id}</td>
      <td><button class="tb-title" type="button" data-trip-id="${trip.id}">${escapeHtml(trip.name)}</button></td>
      <td class="fuf-num">${formatTripPeriod(trip)}${nightsInfo}</td>
      <td class="r">${trip.registeredParticipants} / ${trip.plannedParticipants}</td>
      ${tripSurplusCell(trip)}
      <td class="r">
        <span class="fuf-actions">
          <button class="btn btn-o btn-s" type="button" data-trip-id="${trip.id}">Öffnen</button>
          <button class="ib ib-danger" type="button" disabled aria-label="Löschen"
                  title="Löschen ist noch nicht möglich – es gibt keinen Endpunkt dafür.">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 6h18M8 6V4h8v2M19 6l-1 14H6L5 6"/></svg>
          </button>
        </span>
      </td>
    `;
}

// DESIGN: Rendert aus state.trips, damit die Suche ohne erneuten Request filtert.
function renderTripList() {
  const trips = state.trips;
  const term = byId("trip-search").value.trim().toLowerCase();
  const matches = term === ""
    ? trips
    : trips.filter((trip) => String(trip.name).toLowerCase().includes(term));

  const tbody = byId("trip-list-body");
  const emptyInfo = byId("trip-list-empty");
  const card = byId("trip-list-card");
  tbody.innerHTML = "";

  // Gar keine Reise: nur die Anlegen-Karte. Kein Treffer: die Tabelle bleibt stehen
  // und sagt es in einer Zeile, damit die Suche nicht ins Leere greift.
  const hasTrips = trips.length > 0;
  emptyInfo.classList.toggle("d-none", hasTrips);
  card.classList.toggle("d-none", !hasTrips);

  const tripWord = trips.length === 1 ? "Reise" : "Reisen";
  const participants = trips.reduce((sum, trip) => sum + effectiveParticipants(trip), 0);
  byId("trip-list-summary").textContent = hasTrips
    ? `${trips.length} ${tripWord} · ${participants} Teilnehmer`
    : "";
  byId("trip-list-count").textContent = hasTrips
    ? `${matches.length} von ${trips.length} ${tripWord}`
    : "";

  if (!hasTrips) {
    return;
  }

  if (matches.length === 0) {
    const tr = document.createElement("tr");
    tr.className = "tb-empty";
    tr.innerHTML = '<td colspan="6">Keine Reise gefunden.</td>';
    tbody.appendChild(tr);
  } else {
    matches.forEach((trip) => {
      const tr = document.createElement("tr");
      tr.innerHTML = tripListRow(trip);
      tbody.appendChild(tr);
    });
  }
}

async function loadTripList() {
  const trips = await api("/api/trips");
  trips.sort((left, right) => {
    const dateCompare = String(right.startDate).localeCompare(String(left.startDate));
    if (dateCompare !== 0) {
      return dateCompare;
    }

    return Number(right.id) - Number(left.id);
  });

  state.trips = trips;
  renderTripList();
}

async function openTrip(tripId) {
  const trip = await api(`/api/trips/${tripId}`);
  state.currentTripId = trip.id;
  fillTripForm(trip);
  showEditorSection(trip.name, `Reise #${trip.id}`);
  showTab(tabFromHash());
  byId("retentionPercent").value = trip.clubFeePercent;
  await calculateTripResult(String(trip.id));
  await loadRegistrations(String(trip.id));
  await loadSettlement(String(trip.id));
}

async function calculateTripResult(tripId) {
  const result = await api(`/api/trips/${tripId}/calculate`, { method: "POST" });
  renderCalculationResult(result);
}

function resetRegistrations() {
  byId("registrations-summary").classList.add("d-none");
  byId("registrations-controls").classList.add("d-none");
  byId("registrations-table-wrapper").classList.add("d-none");
  byId("registrations-empty").classList.remove("d-none");
  byId("registrations-accordion").innerHTML = "";
  byId("registrations-no-match").classList.add("d-none");
  byId("recalculate-billings-btn").classList.add("d-none");
  byId("delete-registrations-btn").classList.add("d-none");
  byId("reg-count").textContent = "–";
  byId("reg-participant-count").textContent = "–";
  byId("reg-billing-total").textContent = "–";
  byId("reg-count-note").textContent = "";
  byId("reg-participant-note").textContent = "";
  byId("reg-billing-note").textContent = "";
  byId("reg-participant-kpi").className = "kpi";
  byId("sum-anmeldungen").textContent = "keine Anmeldungen";
  byId("jump-reg-count").className = "chip c-grey jump-chip d-none";
  resetRegistrationFilter();
  renderSwitchTargets();
  renderJumpState();
  resetRoomSummary();
}

function resetRegistrationFilter() {
  state.registrationFilter = { source: "all", query: "" };
  byId("reg-search").value = "";
  document.querySelectorAll(".chip-btn[data-reg-filter]").forEach((chip) => {
    chip.classList.toggle("is-active", chip.getAttribute("data-reg-filter") === "all");
  });
}

const categorySummaryRows = [
  ["cat-summary-adult-double", "cat-plan-adult-double", "cat-check-adult-double", "adultDoubleCount"],
  ["cat-summary-adult-multi", "cat-plan-adult-multi", "cat-check-adult-multi", "adultMultiCount"],
  ["cat-summary-child", "cat-plan-child", "cat-check-child", "childCount"],
];

function resetRoomSummary() {
  byId("room-summary-section").classList.add("d-none");
  byId("room-detail-body").innerHTML = "";
  byId("room-detail-total").textContent = "0";
  byId("sum-zimmer").textContent = "";

  categorySummaryRows.forEach(([istId, , checkId]) => {
    byId(istId).textContent = "0";
    byId(checkId).innerHTML = "";
  });
  byId("cat-summary-total").textContent = "0";
  renderRoomPlanTargets();
}

// DESIGN: Soll neben Ist. Das Soll steht in den Planfeldern, nicht in den
// Anmeldungen - es muss deshalb auch bei einer Feldaenderung mitlaufen und
// nicht erst beim naechsten Laden der Anmeldungen.
function renderRoomPlanTargets() {
  let total = 0;

  categorySummaryRows.forEach(([, planId, , fieldId]) => {
    const planned = numberFromField(fieldId);
    total += planned;
    byId(planId).textContent = `geplant ${planned}`;
  });

  byId("cat-plan-total").textContent = `geplant ${total}`;
  renderCategoryChecks();
}

// Haekchen, solange Ist und Soll uebereinstimmen. Null gegen null ist keine
// Uebereinstimmung, sondern schlicht noch nichts da.
function renderCategoryChecks() {
  categorySummaryRows.forEach(([istId, , checkId, fieldId]) => {
    const actual = Number(byId(istId).textContent) || 0;
    const planned = numberFromField(fieldId);
    byId(checkId).innerHTML = planned > 0 && actual === planned
      ? '<span class="chip c-green"><svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 6L9 17l-5-5"/></svg></span>'
      : "";
  });
}

function renderRoomSummary(registrations) {
  if (!registrations || registrations.length === 0) {
    resetRoomSummary();
    return;
  }

  byId("room-summary-section").classList.remove("d-none");

  // Count rooms by exact category
  const roomCounts = {};
  registrations.forEach((reg) => {
    const cat = reg.roomCategory;
    roomCounts[cat] = (roomCounts[cat] || 0) + 1;
  });

  const sortedCategories = Object.keys(roomCounts).sort();

  // Detail table: rooms by type
  const detailBody = byId("room-detail-body");
  detailBody.innerHTML = "";
  let totalRooms = 0;

  // DESIGN: Der Balken bezieht sich auf die groesste Kategorie, nicht auf die
  // Gesamtzahl - sonst bleiben alle Balken bei vielen Kategorien winzig.
  const maxRooms = Math.max(...sortedCategories.map((cat) => roomCounts[cat]));

  sortedCategories.forEach((cat) => {
    const share = maxRooms > 0 ? Math.round((roomCounts[cat] / maxRooms) * 100) : 0;
    const tr = document.createElement("tr");
    tr.innerHTML = `
      <td>${escapeHtml(cat)}</td>
      <td class="room-bar-cell"><span class="bar"><span style="width: ${share}%"></span></span></td>
      <td class="r"><b>${roomCounts[cat]}</b></td>
    `;
    detailBody.appendChild(tr);
    totalRooms += roomCounts[cat];
  });
  byId("room-detail-total").textContent = String(totalRooms);
  byId("sum-zimmer").textContent = `aus den Anmeldungen abgeleitet · ${totalRooms} Zimmer`;

  // Category summary from billing items
  let adultDouble = 0;
  let adultMulti = 0;
  let child = 0;

  registrations.forEach((reg) => {
    if (reg.billingItems && reg.billingItems.length > 0) {
      reg.billingItems.forEach((item) => {
        if (item.categoryType === "ADULT_DOUBLE") adultDouble++;
        else if (item.categoryType === "ADULT_MULTI") adultMulti++;
        else if (item.categoryType === "CHILD") child++;
      });
    }
  });

  byId("cat-summary-adult-double").textContent = String(adultDouble);
  byId("cat-summary-adult-multi").textContent = String(adultMulti);
  byId("cat-summary-child").textContent = String(child);
  byId("cat-summary-total").textContent = String(adultDouble + adultMulti + child);
  renderRoomPlanTargets();
}

// DESIGN: Je Teilnehmer gehoert genau eine Hauptkategorie-Position und
// optional eine Kurabgabe-Korrektur zusammen (RegistrationBillingService
// erzeugt sie in dieser Reihenfolge). Die Zeile zeigt beides zusammengefasst.
function billingGroupsFor(reg) {
  const groups = reg.participants.map(() => ({ main: null, correction: null, total: null }));
  let index = -1;

  (reg.billingItems || []).forEach((item) => {
    if (categoryShortLabels[item.categoryType] !== undefined) {
      index += 1;
      if (groups[index]) {
        groups[index].main = item;
        groups[index].total = item.price;
      }
      return;
    }

    if (groups[index]) {
      groups[index].correction = item;
      groups[index].total = round2((groups[index].total ?? 0) + item.price);
    }
  });

  return groups;
}

function registrationRows(reg, startDate) {
  const groups = billingGroupsFor(reg);

  return reg.participants.map((participant, i) => {
    const group = groups[i];
    const age = ageAtArrival(participant.birthDate, startDate);
    const ageNote = age === null ? "" : `<span class="help reg-age">${age} J. bei Anreise</span>`;

    let category = '<span class="help">–</span>';
    if (group && group.main) {
      const tone = group.main.categoryType === "CHILD" ? "c-orange" : "c-green";
      category = `<span class="chip ${tone}">${categoryShortLabels[group.main.categoryType]}</span>`;
      if (group.correction) {
        const sign = group.correction.categoryType === "SPA_TAX" ? "+" : "−";
        category += ` <span class="chip c-grey">${sign} Kurabgabe</span>`;
      }
    }

    const price = group && group.total !== null ? formatCurrency(group.total) : "–";

    return `
      <tr>
        <td>${escapeHtml(participant.name)}</td>
        <td class="reg-date">${formatDate(participant.birthDate)}${ageNote}</td>
        <td>${category}</td>
        <td class="r"><b>${price}</b></td>
      </tr>`;
  }).join("");
}

function registrationItem(reg, index, startDate, adultAgeThreshold) {
  const collapseId = `reg-collapse-${index}`;
  const primaryName = reg.participants.length > 0 ? reg.participants[0].name : "Unbekannt";
  const sourceChip = reg.source === "manual"
    ? '<span class="chip c-amber">Manuell</span>'
    : '<span class="chip c-grey">CSV</span>';
  const amount = reg.billingTotal !== null
    ? `<b class="reg-amount">${formatCurrency(reg.billingTotal)}</b>`
    : '<b class="reg-amount reg-amount-none">keine Abrechnung</b>';
  const foot = reg.billingTotal !== null
    ? `<tfoot><tr class="sum-row"><td colspan="3">Gesamt</td><td class="r">${formatCurrency(reg.billingTotal)}</td></tr></tfoot>`
    : "";

  const meta = [
    reg.comment ? `Kommentar: „${escapeHtml(reg.comment)}“` : null,
    reg.receivedAt ? `Eingegangen: ${escapeHtml(reg.receivedAt)}` : null,
    reg.billingCalculatedAt ? `Berechnet: ${escapeHtml(reg.billingCalculatedAt)}` : null,
  ].filter(Boolean).join(" · ");

  const categoryHint = adultAgeThreshold === null
    ? "Kategorie wird aus Alter bei Anreise und Zimmertyp bestimmt."
    : `Kategorie wird aus Alter bei Anreise (Erwachsen ab ${adultAgeThreshold}) und Zimmertyp bestimmt.`;

  const item = document.createElement("div");
  item.className = "reg-item";
  item.setAttribute("data-reg-source", reg.source === "manual" ? "manual" : "csv");
  item.setAttribute("data-reg-name", [primaryName, ...reg.participants.map((p) => p.name)].join(" ").toLowerCase());
  item.innerHTML = `
    <div class="reg-row">
      <button class="reg-row-main collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#${collapseId}" aria-expanded="false" aria-controls="${collapseId}">
        <span class="reg-name">${escapeHtml(primaryName)}</span>
        <span class="chip c-green">
          <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 21h18M5 21V4a1 1 0 011-1h9a1 1 0 011 1v17M14 12h.01"/></svg>
          ${escapeHtml(reg.roomCategory)}
        </span>
        <span class="chip c-grey">${reg.participants.length} Pers.</span>
        ${sourceChip}
        ${amount}
      </button>
      <div class="reg-actions">
        <button type="button" class="ib" disabled title="Anmeldungen lassen sich noch nicht bearbeiten" aria-label="Anmeldung bearbeiten">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 20h9M16.5 3.5a2 2 0 013 3L7 19l-4 1 1-4z"/></svg>
        </button>
        <button type="button" class="ib ib-danger" data-delete-registration="${reg.id}" aria-label="Anmeldung löschen" title="Anmeldung löschen">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 6h18M8 6V4h8v2M19 6l-1 14H6L5 6"/></svg>
        </button>
      </div>
      <button class="chev" type="button" tabindex="-1" aria-hidden="true" data-bs-toggle="collapse" data-bs-target="#${collapseId}" aria-expanded="false">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 15l-6-6-6 6"/></svg>
      </button>
    </div>
    <div id="${collapseId}" class="collapse" data-bs-parent="#registrations-accordion">
      <div class="reg-body">
        <table class="tb">
          <thead>
            <tr><th>Teilnehmer</th><th>Geburtsdatum</th><th>Abrechnungskategorie</th><th class="r">Preis</th></tr>
          </thead>
          <tbody>${registrationRows(reg, startDate)}</tbody>
          ${foot}
        </table>
        <div class="reg-body-foot">
          <span class="help">${categoryHint}</span>
          <span class="help">${meta}</span>
        </div>
      </div>
    </div>
  `;

  return item;
}

function renderRegistrations(registrations) {
  if (!registrations || registrations.length === 0) {
    state.currentRegistrations = [];
    resetRegistrations();
    return;
  }

  state.currentRegistrations = registrations;

  byId("registrations-empty").classList.add("d-none");
  byId("registrations-summary").classList.remove("d-none");
  byId("registrations-controls").classList.remove("d-none");
  byId("registrations-table-wrapper").classList.remove("d-none");
  byId("recalculate-billings-btn").classList.remove("d-none");
  byId("delete-registrations-btn").classList.remove("d-none");

  let totalParticipants = 0;
  let totalBilling = 0;
  let manualCount = 0;

  registrations.forEach((reg) => {
    totalParticipants += reg.participants.length;
    if (reg.billingTotal !== null) {
      totalBilling += reg.billingTotal;
    }
    if (reg.source === "manual") {
      manualCount += 1;
    }
  });

  const csvCount = registrations.length - manualCount;
  const planned = numberFromField("adultDoubleCount")
    + numberFromField("adultMultiCount")
    + numberFromField("childCount");
  const plannedRevenue = previewCalculation().totalCalculatedRevenue;

  byId("reg-count").textContent = String(registrations.length);
  byId("reg-count-note").textContent = [
    manualCount > 0 ? `${manualCount} manuell` : null,
    csvCount > 0 ? `${csvCount} aus CSV` : null,
  ].filter(Boolean).join(" · ");

  byId("reg-participant-count").textContent = planned > 0
    ? `${totalParticipants} / ${planned}`
    : String(totalParticipants);
  byId("reg-participant-note").textContent = participantGapNote(totalParticipants, planned);
  byId("reg-participant-kpi").className = planned > 0 && totalParticipants === planned
    ? "kpi kpi-good"
    : "kpi";

  byId("reg-billing-total").textContent = formatCurrency(totalBilling);
  byId("reg-billing-note").textContent = plannedRevenue > 0
    ? planNote(round2(totalBilling - plannedRevenue))
    : "";

  byId("sum-anmeldungen").textContent = [
    `${registrations.length} ${registrations.length === 1 ? "Anmeldung" : "Anmeldungen"}`,
    `${totalParticipants} Teilnehmer`,
    formatCurrency(totalBilling),
  ].join(" · ");

  // DESIGN: Chip in der Sprungnavigation: angemeldete gegen geplante Teilnehmer.
  const chip = byId("jump-reg-count");
  chip.textContent = `${totalParticipants} / ${planned}`;
  chip.className = totalParticipants === planned && planned > 0
    ? "chip c-green jump-chip"
    : "chip c-grey jump-chip";
  renderJumpState();

  const startDate = byId("startDate").value || null;
  const adultAgeValue = byId("adultAgeThreshold").value;
  const adultAgeThreshold = adultAgeValue === "" ? null : adultAgeValue;

  const accordion = byId("registrations-accordion");
  accordion.innerHTML = "";
  registrations.forEach((reg, index) => {
    accordion.appendChild(registrationItem(reg, index, startDate, adultAgeThreshold));
  });

  applyRegistrationFilter();
  renderSwitchTargets();
  renderRoomSummary(registrations);
}

function participantGapNote(registered, planned) {
  if (planned === 0) {
    return "";
  }
  if (registered === planned) {
    return "ausgebucht";
  }
  if (registered < planned) {
    const open = planned - registered;
    return `${open} ${open === 1 ? "Platz" : "Plätze"} offen`;
  }
  const over = registered - planned;
  return `${over} über Plan`;
}

function planNote(difference) {
  if (difference === 0) {
    return "entspricht der Planung";
  }
  return `${signedCurrency(difference)} gegen Plan`;
}

// DESIGN: Filter und Suche blenden nur aus, sie laden nichts nach. Die Zaehler
// in den Chips zeigen deshalb immer den vollen Bestand.
function applyRegistrationFilter() {
  const { source, query } = state.registrationFilter;
  const needle = query.trim().toLowerCase();
  const items = Array.from(byId("registrations-accordion").children);
  let visible = 0;

  items.forEach((item) => {
    const matchesSource = source === "all" || item.getAttribute("data-reg-source") === source;
    const matchesQuery = needle === "" || (item.getAttribute("data-reg-name") || "").includes(needle);
    const show = matchesSource && matchesQuery;
    item.classList.toggle("d-none", !show);
    if (show) {
      visible += 1;
    }
  });

  const registrations = state.currentRegistrations || [];
  const manualCount = registrations.filter((reg) => reg.source === "manual").length;
  byId("reg-filter-all").textContent = `Alle ${registrations.length}`;
  byId("reg-filter-csv").textContent = `CSV ${registrations.length - manualCount}`;
  byId("reg-filter-manual").textContent = `Manuell ${manualCount}`;

  byId("registrations-no-match").classList.toggle("d-none", visible > 0 || registrations.length === 0);
}

async function loadRegistrations(tripId) {
  try {
    const registrations = await api(`/api/trips/${tripId}/registrations`);
    renderRegistrations(registrations);
  } catch (error) {
    resetRegistrations();
  }
}

function resetSettlement() {
  state.currentSettlement = null;
  byId("settlement-total-revenue").textContent = "–";
  byId("settlement-total-expenses").textContent = "–";
  byId("settlement-participants").textContent = "–";
  byId("settlement-surplus").textContent = "–";
  byId("settlement-surplus-kpi").className = "kpi";
  ["settlement-revenue-dev", "settlement-expenses-dev", "settlement-participants-dev", "settlement-surplus-dev",
    "settlement-revenue-plan", "settlement-expenses-plan", "settlement-participants-plan", "settlement-surplus-plan"]
    .forEach((id) => {
      byId(id).textContent = "";
    });
  byId("planned-costs-body").innerHTML = "";
  byId("planned-costs-sum").textContent = "–";
  byId("actual-expenses-body").innerHTML = "";
  byId("additional-expenses-sum").textContent = "–";
  byId("actual-expenses-empty").classList.remove("d-none");
  byId("sum-zusatzausgaben").textContent = "manuell erfasst";
  byId("jump-expense-count").className = "chip c-amber jump-chip d-none";
  byId("settlement-summary-revenue").textContent = "–";
  byId("settlement-summary-planned").textContent = "–";
  byId("settlement-summary-additional").textContent = "–";
  byId("settlement-summary-total-expenses").textContent = "–";
  byId("settlement-summary-surplus").textContent = "–";
  byId("settlement-summary-chain").className = "k chain-surplus";
  resetActualExpenseForm();
  resetRefundDistribution();
  resetSettlementPanel();
  renderSwitchTargets();
  renderJumpState();
}

function resetActualExpenseForm() {
  byId("actualExpenseId").value = "";
  byId("actualExpenseLabel").value = "";
  byId("actualExpenseAmount").value = "";
  byId("actual-expense-save-label").textContent = "Hinzufügen";
  byId("actual-expense-cancel-btn").classList.add("d-none");
}

const iconEdit = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 20h9M16.5 3.5a2 2 0 013 3L7 19l-4 1 1-4z"/></svg>';
const iconTrash = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 6h18M8 6V4h8v2M19 6l-1 14H6L5 6"/></svg>';

function renderSettlement(settlement) {
  state.currentSettlement = settlement;

  const registrations = state.currentRegistrations || [];
  const participants = registrations.reduce((sum, reg) => sum + reg.participants.length, 0);

  byId("settlement-total-revenue").textContent = formatCurrency(settlement.totalRevenue);
  byId("settlement-total-expenses").textContent = formatCurrency(settlement.totalAllExpenses);
  byId("settlement-participants").textContent = String(participants);
  byId("settlement-surplus").textContent = formatCurrency(settlement.surplus);
  byId("settlement-surplus-kpi").className = Number(settlement.surplus) >= 0 ? "kpi kpi-good" : "kpi kpi-bad";

  renderSettlementComparison(settlement, participants);

  // Planned costs table
  const plannedBody = byId("planned-costs-body");
  plannedBody.innerHTML = "";
  (settlement.plannedCostItems || []).forEach((item) => {
    const tr = document.createElement("tr");
    tr.innerHTML = `
      <td>${escapeHtml(item.label)}</td>
      <td class="r">${formatCurrency(item.amount)}</td>
    `;
    plannedBody.appendChild(tr);
  });
  if (plannedBody.children.length === 0) {
    const tr = document.createElement("tr");
    tr.className = "tb-empty";
    tr.innerHTML = '<td colspan="2">Keine geplanten Kosten (Buchungen mit Anzahl 0).</td>';
    plannedBody.appendChild(tr);
  }
  byId("planned-costs-sum").textContent = formatCurrency(settlement.totalPlannedCosts);

  // Additional expenses table
  const expenses = settlement.expenses || [];
  const tbody = byId("actual-expenses-body");
  tbody.innerHTML = "";
  byId("actual-expenses-empty").classList.toggle("d-none", expenses.length > 0);

  expenses.forEach((expense) => {
    const tr = document.createElement("tr");
    tr.innerHTML = `
      <td><b>${escapeHtml(expense.label)}</b></td>
      <td class="r">${formatCurrency(expense.amount)}</td>
      <td class="r">
        <div class="tb-actions">
          <button type="button" class="ib" data-edit-expense="${expense.id}" data-label="${escapeHtml(expense.label)}" data-amount="${expense.amount}" aria-label="Ausgabe bearbeiten" title="Bearbeiten">${iconEdit}</button>
          <button type="button" class="ib ib-danger" data-delete-expense="${expense.id}" aria-label="Ausgabe löschen" title="Löschen">${iconTrash}</button>
        </div>
      </td>
    `;
    tbody.appendChild(tr);
  });
  byId("additional-expenses-sum").textContent = formatCurrency(settlement.totalAdditionalExpenses);

  byId("sum-zusatzausgaben").textContent = expenses.length === 0
    ? "manuell erfasst"
    : `manuell erfasst · ${expenses.length} Posten · ${formatCurrency(settlement.totalAdditionalExpenses)}`;
  const expenseChip = byId("jump-expense-count");
  expenseChip.textContent = String(expenses.length);
  expenseChip.className = expenses.length > 0 ? "chip c-amber jump-chip" : "chip c-amber jump-chip d-none";

  // Summary chain
  byId("settlement-summary-revenue").textContent = formatCurrency(settlement.totalRevenue);
  byId("settlement-summary-planned").textContent = "− " + formatCurrency(settlement.totalPlannedCosts);
  byId("settlement-summary-additional").textContent = "− " + formatCurrency(settlement.totalAdditionalExpenses);
  byId("settlement-summary-total-expenses").textContent = formatCurrency(settlement.totalAllExpenses);
  byId("settlement-summary-surplus").textContent = formatCurrency(settlement.surplus);
  byId("settlement-summary-chain").className = Number(settlement.surplus) < 0
    ? "k chain-surplus chain-surplus-bad"
    : "k chain-surplus";

  renderJumpState();
  renderRefundDistribution();
  renderSettlementPanel();
  renderSwitchTargets();
  renderHeadKpi();
}

// DESIGN: Plan/Ist nach NAVIGATION.md. Die Planwerte fuer Einnahmen, Teilnehmer
// und Ueberschuss kommen aus previewCalculation() - derselben Quelle wie das
// Kalkulationspanel der Planung, also kein zweiter Rechenweg. Die geplanten
// Kosten stehen dagegen schon in der Settlement-Response.
function renderSettlementComparison(settlement, participants) {
  const plan = previewCalculation();

  const revenueDev = round2(Number(settlement.totalRevenue) - plan.totalCalculatedRevenue);
  renderDeviation("settlement-revenue-dev", revenueDev, deviationCurrency(revenueDev));
  byId("settlement-revenue-plan").textContent = `geplant ${formatCurrency(plan.totalCalculatedRevenue)}`;

  // Mehr Kosten als geplant ist unguenstig - das Vorzeichen wird deshalb gedreht.
  const costDev = round2(Number(settlement.totalAllExpenses) - Number(settlement.totalPlannedCosts));
  renderDeviation("settlement-expenses-dev", -costDev, deviationCurrency(costDev));
  byId("settlement-expenses-plan").textContent = `geplant ${formatCurrency(settlement.totalPlannedCosts)}`;

  const participantDev = participants - plan.totalParticipants;
  renderDeviation("settlement-participants-dev", participantDev, deviationCount(participantDev));
  byId("settlement-participants-plan").textContent = `geplant ${plan.totalParticipants}`;

  const surplusDev = round2(Number(settlement.surplus) - plan.surplus);
  renderDeviation("settlement-surplus-dev", surplusDev, deviationCurrency(surplusDev));
  byId("settlement-surplus-plan").textContent = `geplant ${formatCurrency(plan.surplus)}`;
}

// DESIGN: Sticky-Panel der Abrechnung. Die Segmente summieren sich immer auf
// die Balkenbreite, weil die Bezugsgroesse ihre eigene Summe ist - bei einem
// Defizit decken die Einnahmen die Ausgaben nicht und der Balken liefe sonst
// ueber.
function renderSettlementPanel() {
  const settlement = state.currentSettlement;
  if (!settlement) {
    resetSettlementPanel();
    return;
  }

  const retentionPercent = Number(byId("retentionPercent").value) || 0;
  const retention = round2(Number(settlement.totalRevenue) * (retentionPercent / 100));
  const distributable = round2(Number(settlement.surplus) - retention);

  const registrations = state.currentRegistrations || [];
  const participants = registrations.reduce((sum, reg) => sum + reg.participants.length, 0);
  const planned = Number(settlement.totalPlannedCosts);
  const additional = Number(settlement.totalAdditionalExpenses);
  const positiveRetention = Math.max(0, retention);
  const positiveDistributable = Math.max(0, distributable);
  const base = planned + additional + positiveRetention + positiveDistributable;

  const segments = [
    ["settlement-bar-planned", planned],
    ["settlement-bar-additional", additional],
    ["settlement-bar-retention", positiveRetention],
    ["settlement-bar-distributable", positiveDistributable],
  ];
  segments.forEach(([id, value]) => {
    byId(id).style.width = base > 0 ? `${(value / base) * 100}%` : "0";
  });

  byId("panel-settlement-revenue").textContent = formatCurrency(settlement.totalRevenue);
  byId("panel-settlement-expenses").textContent = `− ${formatCurrency(settlement.totalAllExpenses)}`;
  byId("panel-settlement-surplus").textContent = formatCurrency(settlement.surplus);
  byId("panel-settlement-retention-label").textContent = `Einbehalt ${formatPercentTwo(retentionPercent)}`;
  byId("panel-settlement-retention").textContent = `− ${formatCurrency(retention)}`;

  byId("panel-settlement-green").className = distributable < 0 ? "pnl-green pnl-green-bad" : "pnl-green";
  byId("panel-settlement-distributable").textContent = formatCurrency(distributable);
  byId("panel-settlement-per-registration").textContent = registrations.length > 0
    ? `≈ ${formatCurrency(distributable / registrations.length)}`
    : "–";
  byId("panel-settlement-per-participant").textContent = participants > 0
    ? `≈ ${formatCurrency(distributable / participants)}`
    : "–";
}

function resetSettlementPanel() {
  ["settlement-bar-planned", "settlement-bar-additional", "settlement-bar-retention", "settlement-bar-distributable"]
    .forEach((id) => {
      byId(id).style.width = "0";
    });
  byId("panel-settlement-revenue").textContent = "–";
  byId("panel-settlement-expenses").textContent = "–";
  byId("panel-settlement-surplus").textContent = "–";
  byId("panel-settlement-retention-label").textContent = "Einbehalt";
  byId("panel-settlement-retention").textContent = "–";
  byId("panel-settlement-green").className = "pnl-green";
  byId("panel-settlement-distributable").textContent = "–";
  byId("panel-settlement-per-registration").textContent = "–";
  byId("panel-settlement-per-participant").textContent = "–";
}

function resetRefundDistribution() {
  byId("refund-surplus").textContent = "–";
  byId("refund-revenue-base").textContent = "–";
  byId("refund-retention-percent-display").textContent = "–";
  byId("refund-retention-label").textContent = "= Einbehalt";
  byId("refund-retention").textContent = "–";
  byId("refund-distributable-formula").textContent = "";
  byId("refund-distributable").textContent = "–";
  byId("refund-distributable").className = "";
  byId("refund-body").innerHTML = "";
  byId("refund-total-billing").textContent = "–";
  byId("refund-total-amount").textContent = "–";
  byId("refund-total-amount").className = "r";
  byId("refund-empty").classList.remove("d-none");
}

function formatPercentTwo(value) {
  return `${value.toFixed(2).replace(".", ",")} %`;
}

function renderRefundDistribution() {
  const settlement = state.currentSettlement;
  const registrations = state.currentRegistrations;

  if (!settlement || !registrations || registrations.length === 0) {
    resetRefundDistribution();
    return;
  }

  // Only registrations with billing
  const billedRegs = registrations.filter((r) => r.billingTotal !== null && r.billingTotal > 0);
  if (billedRegs.length === 0) {
    resetRefundDistribution();
    return;
  }

  byId("refund-empty").classList.add("d-none");

  const surplus = Number(settlement.surplus);
  const totalRevenue = Number(settlement.totalRevenue);
  const retentionPercent = Number(byId("retentionPercent").value) || 0;

  const retention = round2(totalRevenue * (retentionPercent / 100));
  const distributable = round2(surplus - retention);

  byId("refund-surplus").textContent = formatCurrency(surplus);
  byId("refund-revenue-base").textContent = formatCurrency(totalRevenue);
  byId("refund-retention-percent-display").textContent = formatPercentTwo(retentionPercent);
  byId("refund-retention-label").textContent =
    `= ${formatCurrency(totalRevenue)} × ${formatPercentTwo(retentionPercent)}`;
  byId("refund-retention").textContent = formatCurrency(retention);
  byId("refund-distributable-formula").textContent =
    `${formatCurrency(surplus)} − ${formatCurrency(retention)}`;
  byId("refund-distributable").textContent = formatCurrency(distributable);
  byId("refund-distributable").className = distributable >= 0 ? "fuf-pos" : "fuf-neg";

  const totalBilling = billedRegs.reduce((sum, r) => sum + r.billingTotal, 0);

  const tbody = byId("refund-body");
  tbody.innerHTML = "";
  let totalRefund = 0;

  billedRegs.forEach((reg) => {
    const primaryName = reg.participants.length > 0 ? reg.participants[0].name : "Unbekannt";
    const share = totalBilling > 0 ? reg.billingTotal / totalBilling : 0;
    const sharePercent = Math.round(share * 10000) / 100;
    const refundAmount = round2(distributable * share);
    totalRefund = round2(totalRefund + refundAmount);

    const tr = document.createElement("tr");
    tr.innerHTML = `
      <td><b>${escapeHtml(primaryName)}</b><span class="help reg-age">${escapeHtml(reg.roomCategory)}</span></td>
      <td class="r">${formatCurrency(reg.billingTotal)}</td>
      <td class="r help">${formatPercentTwo(sharePercent)}</td>
      <td class="r ${refundAmount >= 0 ? "fuf-pos" : "fuf-neg"}">${signedCurrency(refundAmount)}</td>
    `;
    tbody.appendChild(tr);
  });

  byId("refund-total-billing").textContent = formatCurrency(totalBilling);
  const totalEl = byId("refund-total-amount");
  totalEl.textContent = signedCurrency(totalRefund);
  totalEl.className = `r ${totalRefund >= 0 ? "fuf-pos" : "fuf-neg"}`;
}

async function loadSettlement(tripId) {
  try {
    const settlement = await api(`/api/trips/${tripId}/settlement`);
    renderSettlement(settlement);
  } catch (error) {
    resetSettlement();
  }
}

// DESIGN: "benedikt.schaller@x.de" -> "BS", "admin@x.de" -> "AD"
function initialsFromEmail(email) {
  const local = String(email).split("@")[0];
  const parts = local.split(/[._-]+/).filter((part) => part.length > 0);

  if (parts.length >= 2) {
    return (parts[0][0] + parts[1][0]).toUpperCase();
  }

  return local.slice(0, 2).toUpperCase();
}

async function loadCurrentUser() {
  try {
    const me = await api("/api/auth/me");
    state.currentUser = me;
    if (me && me.email) {
      byId("current-user-email").textContent = me.email;
      // DESIGN: Initialen fuer den Avatar in der Kopfleiste
      byId("current-user-avatar").textContent = initialsFromEmail(me.email);
    }
    const badge = byId("current-user-role-badge");
    if (me && me.role === "admin") {
      badge.classList.remove("d-none");
    } else {
      badge.classList.add("d-none");
    }
  } catch (error) {
    // ignored — middleware would have redirected if not logged in
  }
}

function escapeHtml(value) {
  return String(value).replace(/[&<>"']/g, (char) => {
    return {
      "&": "&amp;",
      "<": "&lt;",
      ">": "&gt;",
      '"': "&quot;",
      "'": "&#39;",
    }[char];
  });
}

async function loadUsers() {
  byId("users-table-body").innerHTML = "";
  setStatusNote("users-status", "", null);

  try {
    const data = await api("/api/users");
    state.users = data.users || [];
    renderUsers();
  } catch (error) {
    state.users = [];
    byId("users-modal-sub").textContent = "";
    setStatusNote("users-status", "Fehler beim Laden: " + error.message, "error");
  }
}

// DESIGN: Rolle und Status als Chip, Loeschen als Icon. Das Rolle-Select des
// Mockups bleibt weg - Rollenverwaltung ist gestrichen und es gibt keinen
// Endpunkt dafuer.
function renderUsers() {
  const users = state.users || [];
  const invited = users.filter((user) => !user.hasPassword).length;

  byId("users-modal-sub").textContent = [
    `${users.length} Benutzer`,
    invited === 0 ? null : `${invited} offene ${invited === 1 ? "Einladung" : "Einladungen"}`,
  ].filter(Boolean).join(" · ");

  const needle = (state.userSearch || "").trim().toLowerCase();
  const visible = needle === ""
    ? users
    : users.filter((user) => user.email.toLowerCase().includes(needle));

  const body = byId("users-table-body");

  if (visible.length === 0) {
    body.innerHTML = `<tr class="tb-empty"><td colspan="4">${users.length === 0 ? "Keine Benutzer." : "Kein Benutzer passt zur Suche."}</td></tr>`;
    return;
  }

  body.innerHTML = visible.map((user) => {
    const role = user.role === "admin"
      ? '<span class="chip c-green">Admin</span>'
      : '<span class="chip c-grey">Benutzer</span>';
    const status = user.hasPassword
      ? '<span class="chip c-green">Aktiv</span>'
      : '<span class="chip c-amber">Eingeladen</span>';
    const action = user.isCurrentUser
      ? '<span class="help">Das bist du</span>'
      : user.canDelete
        ? `<button type="button" class="ib ib-danger" data-delete-user-id="${user.id}" aria-label="Benutzer entfernen" title="Benutzer entfernen">${iconTrash}</button>`
        : '<span class="help">&mdash;</span>';

    return `<tr>
      <td>
        <div class="user-cell">
          <span class="user-avatar" aria-hidden="true">${escapeHtml(initialsFromEmail(user.email))}</span>
          <b>${escapeHtml(user.email)}</b>
        </div>
      </td>
      <td>${role}</td>
      <td>${status}</td>
      <td class="r">${action}</td>
    </tr>`;
  }).join("");
}

// DESIGN: Eine Meldungsbox fuer Benutzer- und Einladungs-Modal. tone ist
// "ok", "error" oder null fuer neutral; ein leerer Text blendet sie aus.
function setStatusNote(elementId, text, tone) {
  const element = byId(elementId);
  element.textContent = text;

  if (text === "") {
    element.className = "status-note d-none";
    return;
  }

  element.className = tone === null ? "status-note" : `status-note is-${tone}`;
}

async function deleteUser(userId) {
  try {
    await api(`/api/users/${userId}`, { method: "DELETE" });
    await loadUsers();
    setStatusNote("users-status", "Benutzer gelöscht.", "ok");
  } catch (error) {
    let message = error.message;
    try {
      const parsed = JSON.parse(error.message);
      if (parsed && parsed.message) {
        message = parsed.message;
      }
    } catch (_) {}
    setStatusNote("users-status", "Fehler: " + message, "error");
  }
}

async function bootstrapPage() {
  if (window.bootstrap && window.bootstrap.Modal) {
    state.settingsModal = new window.bootstrap.Modal(byId("settings-modal"));
    state.inviteModal = new window.bootstrap.Modal(byId("invite-modal"));
    state.usersModal = new window.bootstrap.Modal(byId("users-modal"));
  } else {
    throw new Error("Bootstrap Modal konnte nicht initialisiert werden.");
  }

  await loadCurrentUser();
  await loadSettings();
  await loadTripList();
  window.addEventListener("popstate", async () => {
    try {
      await renderCurrentRoute({ reuseLoadedTrip: true });
    } catch (error) {
      alert("Route konnte nicht geladen werden: " + error.message);
    }
  });

  observeHeadScroll();
  await renderCurrentRoute();
}

// --- Event Handlers ---

byId("open-users-btn").addEventListener("click", async () => {
  state.userSearch = "";
  byId("users-search").value = "";
  state.usersModal.show();
  await loadUsers();
});

byId("users-table-body").addEventListener("click", async (event) => {
  const target = event.target;
  if (!(target instanceof Element)) {
    return;
  }
  // DESIGN: Loeschen ist ein Icon-Button - geklickt wird oft das SVG darin.
  const button = target.closest("[data-delete-user-id]");
  const id = button ? button.getAttribute("data-delete-user-id") : null;
  if (!id) {
    return;
  }
  if (!window.confirm("Diesen Benutzer wirklich loeschen?")) {
    return;
  }
  await deleteUser(Number(id));
});

byId("users-search").addEventListener("input", () => {
  state.userSearch = byId("users-search").value;
  renderUsers();
});

byId("open-invite-btn").addEventListener("click", () => {
  byId("invite-form").reset();
  setStatusNote("invite-status", "", null);
  // DESIGN: Der Button sitzt jetzt im Benutzer-Modal. Erst schliessen,
  // damit sich die beiden Dialoge nicht uebereinander stapeln.
  state.usersModal.hide();
  state.inviteModal.show();
});

byId("invite-form").addEventListener("submit", async (event) => {
  event.preventDefault();
  const email = byId("invite-email").value.trim();
  setStatusNote("invite-status", "Sende Einladung …", null);

  try {
    await api("/api/auth/invite", {
      method: "POST",
      body: JSON.stringify({ email }),
    });
    setStatusNote("invite-status", `Einladung an ${email} gesendet.`, "ok");
    byId("invite-form").reset();
  } catch (error) {
    setStatusNote("invite-status", "Fehler: " + error.message, "error");
  }
});

byId("open-settings-btn").addEventListener("click", async () => {
  try {
    await loadSettings();
    state.settingsModal.show();
  } catch (error) {
    alert("Settings konnten nicht geladen werden: " + error.message);
  }
});

byId("settings-form").addEventListener("submit", async (event) => {
  event.preventDefault();
  const form = event.currentTarget;

  const payload = {
    defaultMarkupPercent: Number(form.defaultMarkupPercent.value),
    defaultClubFeePercent: Number(form.defaultClubFeePercent.value),
    defaultDistributionMethod: form.defaultDistributionMethod.value,
    defaultSpaTaxPerPerson: Number(form.defaultSpaTaxPerPerson.value),
    defaultSpaTaxAgeThreshold: Number(form.defaultSpaTaxAgeThreshold.value),
    defaultAdultAgeThreshold: Number(form.defaultAdultAgeThreshold.value),
  };

  try {
    state.settings = await api("/api/settings", { method: "PUT", body: JSON.stringify(payload) });
    fillSettingsForm(state.settings);
    state.settingsModal.hide();
  } catch (error) {
    alert("Fehler beim Speichern der Settings: " + error.message);
  }
});

async function startNewTrip() {
  try {
    if (!state.settings) {
      await loadSettings();
    }
    await navigateTo("/trips/new");
  } catch (error) {
    alert("Defaults konnten nicht geladen werden: " + error.message);
  }
}

byId("new-trip-btn").addEventListener("click", startNewTrip);

// DESIGN: Der Knopf in der Leerzustands-Karte macht dasselbe wie der in der Kopfleiste.
byId("trip-list-empty-create-btn").addEventListener("click", startNewTrip);

// DESIGN: Clientseitige Suche ueber den Reisenamen, ohne erneuten Request.
byId("trip-search").addEventListener("input", () => {
  renderTripList();
});

byId("back-to-list-btn").addEventListener("click", async (event) => {
  // DESIGN: Der Breadcrumb-Link ersetzt den Button, das Routing bleibt clientseitig.
  event.preventDefault();
  await navigateTo("/");
});

byId("trip-head-back-btn").addEventListener("click", async (event) => {
  event.preventDefault();
  await navigateTo("/");
});

byId("trip-list-body").addEventListener("click", async (event) => {
  const target = event.target;
  if (!(target instanceof HTMLElement)) {
    return;
  }
  const tripId = target.getAttribute("data-trip-id");
  if (!tripId) {
    return;
  }

  try {
    await navigateTo(routeForTrip(Number(tripId)));
  } catch (error) {
    alert("Reise konnte nicht geoeffnet werden: " + error.message);
  }
});

byId("trip-form").addEventListener("submit", async (event) => {
  event.preventDefault();
  const tripIdValue = byId("tripFormId").value;
  const payload = tripPayloadFromForm();

  try {
    const trip = tripIdValue
      ? await api(`/api/trips/${tripIdValue}`, { method: "PUT", body: JSON.stringify(payload) })
      : await api("/api/trips", { method: "POST", body: JSON.stringify(payload) });

    state.currentTripId = trip.id;
    fillTripForm(trip);
    resetCalculationResult();
    await loadTripList();
    await navigateTo(routeForTrip(trip.id));
  } catch (error) {
    alert("Fehler beim Speichern der Reise: " + error.message);
  }
});

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

  state.salesPrices[input.dataset.salesCategory] = input.value === "" ? null : Number(input.value);
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

byId("csv-upload-form").addEventListener("submit", async (event) => {
  event.preventDefault();
  const tripId = byId("tripFormId").value;
  if (!tripId) {
    alert("Bitte erst Reise speichern.");
    return;
  }

  const fileInput = byId("csvFile");
  if (!fileInput.files || fileInput.files.length === 0) {
    alert("Bitte eine CSV-Datei auswaehlen.");
    return;
  }

  const formData = new FormData();
  formData.append("csv_file", fileInput.files[0]);

  try {
    const response = await fetch(`/api/trips/${tripId}/registrations/import`, {
      method: "POST",
      body: formData,
    });
    const text = await response.text();
    const data = text ? JSON.parse(text) : {};

    if (!response.ok) {
      throw new Error(JSON.stringify(data));
    }

    renderRegistrations(data);
    fileInput.value = "";
    byId("csv-file-name").textContent = "";
    alert("Import erfolgreich! " + data.length + " Anmeldungen insgesamt (manuelle bleiben erhalten).");
  } catch (error) {
    alert("Fehler beim Import: " + error.message);
  }
});

// DESIGN: Die Dropzone verspricht Ablegen, also muss Ablegen auch gehen. Die
// Datei landet ueber ein DataTransfer im bestehenden #csvFile, der Import
// laeuft danach durch denselben Submit-Pfad wie beim Auswaehlen.
const csvDropzone = byId("csv-upload-form");

["dragenter", "dragover"].forEach((type) => {
  csvDropzone.addEventListener(type, (event) => {
    event.preventDefault();
    csvDropzone.classList.add("is-over");
  });
});

["dragleave", "dragend"].forEach((type) => {
  csvDropzone.addEventListener(type, (event) => {
    if (type === "dragleave" && csvDropzone.contains(event.relatedTarget)) {
      return;
    }
    csvDropzone.classList.remove("is-over");
  });
});

csvDropzone.addEventListener("drop", (event) => {
  event.preventDefault();
  csvDropzone.classList.remove("is-over");

  const file = event.dataTransfer && event.dataTransfer.files[0];
  if (!file) {
    return;
  }

  if (!file.name.toLowerCase().endsWith(".csv")) {
    alert("Bitte eine CSV-Datei ablegen.");
    return;
  }

  const transfer = new DataTransfer();
  transfer.items.add(file);
  byId("csvFile").files = transfer.files;
  byId("csv-file-name").textContent = file.name;
  csvDropzone.requestSubmit();
});

byId("csvFile").addEventListener("change", () => {
  const file = byId("csvFile").files[0];
  byId("csv-file-name").textContent = file ? file.name : "";
});

// DESIGN: Filter-Chips und Suche bei den Anmeldungen, rein clientseitig.
document.querySelectorAll(".chip-btn[data-reg-filter]").forEach((chip) => {
  chip.addEventListener("click", () => {
    state.registrationFilter.source = chip.getAttribute("data-reg-filter");
    document.querySelectorAll(".chip-btn[data-reg-filter]").forEach((other) => {
      other.classList.toggle("is-active", other === chip);
    });
    applyRegistrationFilter();
  });
});

byId("reg-search").addEventListener("input", () => {
  state.registrationFilter.query = byId("reg-search").value;
  applyRegistrationFilter();
});

byId("recalculate-billings-btn").addEventListener("click", async () => {
  const tripId = byId("tripFormId").value;
  if (!tripId) {
    return;
  }

  try {
    const registrations = await api(`/api/trips/${tripId}/registrations/recalculate`, { method: "POST" });
    renderRegistrations(registrations);
    alert("Abrechnungen wurden neu berechnet.");
  } catch (error) {
    alert("Fehler bei Neuberechnung: " + error.message);
  }
});

byId("delete-registrations-btn").addEventListener("click", async () => {
  const tripId = byId("tripFormId").value;
  if (!tripId) {
    return;
  }

  if (!confirm("Alle Anmeldungen und Abrechnungen fuer diese Reise loeschen?")) {
    return;
  }

  try {
    await api(`/api/trips/${tripId}/registrations`, { method: "DELETE" });
    resetRegistrations();
  } catch (error) {
    alert("Fehler beim Loeschen: " + error.message);
  }
});

// --- Manual Registration ---

byId("add-participant-row-btn").addEventListener("click", () => {
  const container = byId("manual-participants-container");
  const rows = container.querySelectorAll(".manual-participant-row");
  if (rows.length >= 5) return;
  const num = rows.length + 1;
  const row = document.createElement("div");
  row.className = "reg-manual-row manual-participant-row";
  row.innerHTML = `
    <div class="f">
      <label for="manualParticipantName${num}">Teilnehmer ${num}</label>
      <div class="in"><input class="manual-participant-name" type="text" required id="manualParticipantName${num}" placeholder="Name"></div>
    </div>
    <div class="f f-date">
      <label for="manualParticipantBirthdate${num}">Geburtsdatum</label>
      <div class="in"><input class="manual-participant-birthdate" type="date" required id="manualParticipantBirthdate${num}"></div>
    </div>
  `;
  container.appendChild(row);
  byId("remove-participant-row-btn").classList.remove("d-none");
  if (container.querySelectorAll(".manual-participant-row").length >= 5) {
    byId("add-participant-row-btn").disabled = true;
  }
});

byId("remove-participant-row-btn").addEventListener("click", () => {
  const container = byId("manual-participants-container");
  const rows = container.querySelectorAll(".manual-participant-row");
  if (rows.length <= 1) return;
  rows[rows.length - 1].remove();
  byId("add-participant-row-btn").disabled = false;
  if (container.querySelectorAll(".manual-participant-row").length <= 1) {
    byId("remove-participant-row-btn").classList.add("d-none");
  }
});

byId("manual-registration-form").addEventListener("submit", async (event) => {
  event.preventDefault();
  const tripId = byId("tripFormId").value;
  if (!tripId) {
    alert("Bitte erst Reise speichern.");
    return;
  }

  const names = document.querySelectorAll(".manual-participant-name");
  const dates = document.querySelectorAll(".manual-participant-birthdate");
  const participants = [];
  names.forEach((nameInput, i) => {
    const name = nameInput.value.trim();
    const birthDate = dates[i].value;
    if (name && birthDate) {
      participants.push({ name, birthDate });
    }
  });

  if (participants.length === 0) {
    alert("Mindestens ein Teilnehmer mit Name und Geburtsdatum ist erforderlich.");
    return;
  }

  const payload = {
    roomCategory: byId("manualRoomCategory").value,
    comment: byId("manualComment").value,
    participants,
  };

  try {
    const registrations = await api(`/api/trips/${tripId}/registrations`, {
      method: "POST",
      body: JSON.stringify(payload),
    });
    renderRegistrations(registrations);
    // Reset form
    byId("manual-registration-form").reset();
    const container = byId("manual-participants-container");
    const rows = container.querySelectorAll(".manual-participant-row");
    while (rows.length > 1) {
      rows[rows.length - 1].remove();
      break;
    }
    // Keep only first row, remove extras
    const allRows = container.querySelectorAll(".manual-participant-row");
    for (let i = allRows.length - 1; i > 0; i--) {
      allRows[i].remove();
    }
    byId("remove-participant-row-btn").classList.add("d-none");
    byId("add-participant-row-btn").disabled = false;
    alert("Anmeldung erfolgreich hinzugefügt!");
  } catch (error) {
    alert("Fehler beim Hinzufügen: " + error.message);
  }
});

// --- Single Registration Deletion ---

byId("registrations-accordion").addEventListener("click", async (event) => {
  const target = event.target;
  if (!(target instanceof Element)) return;

  // DESIGN: Der Loeschen-Knopf ist jetzt ein Icon-Button - geklickt wird oft das
  // SVG darin, deshalb ueber closest() statt direkt am Ziel.
  const button = target.closest("[data-delete-registration]");
  if (!button) return;

  const deleteRegId = button.getAttribute("data-delete-registration");

  const tripId = byId("tripFormId").value;
  if (!tripId) return;

  if (!confirm("Diese Anmeldung wirklich löschen?")) return;

  try {
    const registrations = await api(`/api/trips/${tripId}/registrations/${deleteRegId}`, { method: "DELETE" });
    renderRegistrations(registrations);
  } catch (error) {
    alert("Fehler beim Löschen: " + error.message);
  }
});

// --- Actual Expense Event Handlers ---

byId("actual-expense-form").addEventListener("submit", async (event) => {
  event.preventDefault();
  const tripId = byId("tripFormId").value;
  if (!tripId) {
    alert("Bitte erst Reise speichern.");
    return;
  }

  const expenseId = byId("actualExpenseId").value;
  const payload = {
    label: byId("actualExpenseLabel").value,
    amount: Number(byId("actualExpenseAmount").value),
  };

  try {
    if (expenseId) {
      await api(`/api/trips/${tripId}/actual-expenses/${expenseId}`, { method: "PUT", body: JSON.stringify(payload) });
    } else {
      await api(`/api/trips/${tripId}/actual-expenses`, { method: "POST", body: JSON.stringify(payload) });
    }
    resetActualExpenseForm();
    await loadSettlement(tripId);
  } catch (error) {
    alert("Fehler beim Speichern der Ausgabe: " + error.message);
  }
});

byId("actual-expense-cancel-btn").addEventListener("click", () => {
  resetActualExpenseForm();
});

byId("actual-expenses-body").addEventListener("click", async (event) => {
  const target = event.target;
  if (!(target instanceof Element)) return;

  const tripId = byId("tripFormId").value;
  if (!tripId) return;

  // DESIGN: Bearbeiten und Loeschen sind jetzt Icon-Buttons - geklickt wird oft
  // das SVG darin, deshalb ueber closest() statt direkt am Ziel.
  const editButton = target.closest("[data-edit-expense]");
  if (editButton) {
    byId("actualExpenseId").value = editButton.getAttribute("data-edit-expense");
    byId("actualExpenseLabel").value = editButton.getAttribute("data-label") || "";
    byId("actualExpenseAmount").value = editButton.getAttribute("data-amount") || "";
    byId("actual-expense-save-label").textContent = "Aktualisieren";
    byId("actual-expense-cancel-btn").classList.remove("d-none");
    return;
  }

  const deleteButton = target.closest("[data-delete-expense]");
  const deleteId = deleteButton ? deleteButton.getAttribute("data-delete-expense") : null;
  if (deleteId) {
    if (!confirm("Ausgabe wirklich löschen?")) return;
    try {
      await api(`/api/trips/${tripId}/actual-expenses/${deleteId}`, { method: "DELETE" });
      await loadSettlement(tripId);
    } catch (error) {
      alert("Fehler beim Löschen: " + error.message);
    }
  }
});

// Load settlement when switching to the Abrechnung tab
byId("tab-abrechnung").addEventListener("shown.bs.tab", async () => {
  writeTabHash("abrechnung");
  renderHeadTabState();
  const tripId = byId("tripFormId").value;
  if (tripId) {
    await loadSettlement(tripId);
  }
});

byId("tab-planung").addEventListener("shown.bs.tab", () => {
  writeTabHash("planung");
  renderHeadTabState();
});

// DESIGN: Die Wechsel-Einstiege am Seitenende und in der Sprungnavigation sind
// echte Links auf #abrechnung bzw. #planung. Ein Fragmentsprung feuert
// hashchange, aber kein popstate - ohne diesen Listener wuerde sich nur die
// URL aendern und sonst nichts passieren.
window.addEventListener("hashchange", () => {
  if (byId("trip-editor-section").classList.contains("d-none")) {
    return;
  }
  showTab(tabFromHash());
});

// DESIGN: 1 und 2 wechseln die Bereiche - aber nicht, waehrend jemand tippt
// oder in einem Dialog steht.
document.addEventListener("keydown", (event) => {
  if (event.key !== "1" && event.key !== "2") {
    return;
  }
  if (event.ctrlKey || event.metaKey || event.altKey) {
    return;
  }
  if (byId("trip-editor-section").classList.contains("d-none")) {
    return;
  }
  if (document.querySelector(".modal.show") !== null) {
    return;
  }

  const active = document.activeElement;
  if (active instanceof Element && active.closest("input, select, textarea, [contenteditable='true']") !== null) {
    return;
  }

  event.preventDefault();
  showTab(event.key === "1" ? "planung" : "abrechnung");
});

// Recalculate refund distribution when retention percent changes
byId("retentionPercent").addEventListener("input", () => {
  renderRefundDistribution();
  renderSettlementPanel();
});

bootstrapPage().catch((error) => {
  byId("trip-list-empty").classList.remove("d-none");
  byId("trip-list-empty").textContent = "Fehler beim Laden: " + error.message;
});


