// Live calculation, numeric parsing and planning summaries.
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

// DESIGN: Die Zahlenfelder sind type="text" mit inputmode, damit iOS die
// richtige Tastatur zeigt und im deutschen Layout ein Komma moeglich ist
// (RESPONSIVE.md, Regel 2). Dafuer muss das Parsen beide Schreibweisen kennen.
//
// Mit Komma gilt deutsch: Punkte sind Tausendertrenner, das Komma trennt die
// Nachkommastellen ("1.234,56" -> 1234.56). Ohne Komma bleibt es beim bisherigen
// Number(), sonst wuerde aus dem gespeicherten "12.50" die Zahl 1250.
function parseDecimal(value) {
  const text = String(value ?? "").trim();
  if (text === "") {
    return NaN;
  }
  const normalized = text.includes(",")
    ? text.replace(/\./g, "").replace(",", ".")
    : text;
  return Number(normalized);
}

// DESIGN: Gegenstueck zu parseDecimal fuers Zurueckschreiben. Ein Feld, das
// "12.5" zeigt, waehrend nebenan "12,5" erwartet wird, laedt zu Fehleingaben
// ein. Ohne Tausenderpunkte - beim Tippen stoeren sie mehr als sie helfen.
function formatDecimal(value) {
  if (value === null || value === undefined || value === "") {
    return "";
  }
  return String(value).replace(".", ",");
}

function numberFromField(id) {
  const parsed = parseDecimal(byId(id).value);
  return Number.isFinite(parsed) ? parsed : 0;
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

  const nightWord = nights === 1 ? "Nacht" : "Nächte";

  rows.forEach(([target, countId, priceId]) => {
    const count = numberFromField(countId);
    const price = numberFromField(priceId);
    const total = round2(round2(price * nights) * count);
    byId(target).textContent = formatCurrency(total);

    // DESIGN: Der Rechenweg steht am Desktop in der Spaltenueberschrift
    // ("Summe (4 Naechte)"). In der Karte aus Schritt 4d gibt es keine
    // Spaltenueberschrift, also steht er als Hilfstext in der Fusszeile.
    byId(`${target}-calc`).textContent =
      `${formatDecimal(count)} × ${formatCurrency(price)} × ${nights} ${nightWord}`;
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
  renderRoomSummary();
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
  const reservedRooms = reservedRoomTotal();
  byId("sum-unterkunft").textContent = [
    `${participants} ${participants === 1 ? "Person" : "Personen"}`,
    adultAge === "" ? null : `Erwachsen ab ${adultAge} J.`,
    reservedRooms > 0 ? `${reservedRooms} Zimmer reserviert` : null,
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
