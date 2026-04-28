const state = {
  settings: null,
  currentTripId: null,
  settingsModal: null,
  currentRegistrations: [],
  currentSettlement: null,
};

const categoryLabels = {
  ADULT_DOUBLE: "Erwachsener im Doppelzimmer",
  ADULT_MULTI: "Erwachsener im Mehrbettzimmer",
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

function resetCalculationResult() {
  byId("result-panel").classList.add("d-none");
  byId("result-placeholder").classList.remove("d-none");
  byId("result-total-participants").textContent = "-";
  byId("result-total-revenue").textContent = "-";
  byId("result-total-costs").textContent = "-";
  byId("result-surplus").textContent = "-";
  byId("result-surplus").className = "fs-4 fw-semibold";
  byId("result-category-prices-body").innerHTML = "";
  byId("result-distribution-method").textContent = "-";
  byId("result-spa-tax-age").textContent = "-";
  byId("result-start-date").textContent = "-";
  byId("result-total-group-expenses").textContent = "-";
  byId("result-breakdowns-container").innerHTML = '<p class="text-muted text-center p-3 mb-0">Keine Aufschlüsselung vorhanden.</p>';
}

function renderCalculationResult(result) {
  byId("result-placeholder").classList.add("d-none");
  byId("result-panel").classList.remove("d-none");

  byId("result-total-participants").textContent = String(result.totalParticipants ?? 0);
  byId("result-total-revenue").textContent = formatCurrency(result.totalCalculatedRevenue);
  byId("result-total-costs").textContent = formatCurrency(result.totalCalculatedCosts);
  byId("result-surplus").textContent = formatCurrency(result.surplus);
  byId("result-surplus").className = `fs-4 fw-semibold ${Number(result.surplus) >= 0 ? "text-success" : "text-danger"}`;
  byId("result-distribution-method").textContent = distributionLabels[result.distributionMethod] || result.distributionMethod;
  byId("result-spa-tax-age").textContent = `${result.spaTaxAgeThreshold} Jahre`;
  byId("result-start-date").textContent = formatDate(result.startDate);
  byId("result-total-group-expenses").textContent = formatCurrency(result.totalGroupExpenses);

  // Summary table
  const tbody = byId("result-category-prices-body");
  tbody.innerHTML = "";

  Object.entries(result.priceBreakdowns || {}).forEach(([category, bd]) => {
    const tr = document.createElement("tr");
    tr.innerHTML = `
      <td>${categoryLabels[category] || category}</td>
      <td class="text-end">${bd.count}</td>
      <td class="text-end">${formatCurrency(bd.finalPrice)}</td>
      <td class="text-end">${formatCurrency(bd.categoryRevenue)}</td>
    `;
    tbody.appendChild(tr);
  });

  if (tbody.children.length === 0) {
    const tr = document.createElement("tr");
    tr.innerHTML = '<td colspan="4" class="text-muted text-center">Keine Preise vorhanden.</td>';
    tbody.appendChild(tr);
  }

  // Detailed breakdowns
  const container = byId("result-breakdowns-container");
  container.innerHTML = "";

  const breakdowns = result.priceBreakdowns || {};
  const categories = Object.keys(breakdowns);

  if (categories.length === 0) {
    container.innerHTML = '<p class="text-muted text-center p-3 mb-0">Keine Aufschlüsselung vorhanden.</p>';
    return;
  }

  categories.forEach((category) => {
    const bd = breakdowns[category];
    const section = document.createElement("div");
    section.className = "p-3 border-bottom";
    section.innerHTML = `
      <h6 class="mb-2">${categoryLabels[category] || category} <span class="text-muted fw-normal small">(${bd.count} Personen)</span></h6>
      <table class="table table-sm table-bordered mb-0 small">
        <tbody>
          <tr>
            <td>Grundpreis pro Person</td>
            <td class="text-end fw-semibold">${formatCurrency(bd.basePricePerPerson)}</td>
          </tr>
          <tr>
            <td>+ Kurabgabe pro Person</td>
            <td class="text-end">${formatCurrency(bd.spaTaxPerPerson)}</td>
          </tr>
          <tr>
            <td>+ Anteil Gruppenausgaben</td>
            <td class="text-end">${formatCurrency(bd.groupExpenseShare)}</td>
          </tr>
          <tr class="table-light">
            <td><strong>Zwischensumme</strong></td>
            <td class="text-end"><strong>${formatCurrency(bd.subtotalBeforeMarkup)}</strong></td>
          </tr>
          <tr>
            <td>+ Aufschlag (${bd.markupPercent}%)</td>
            <td class="text-end">${formatCurrency(bd.markupAmount)}</td>
          </tr>
          <tr class="table-light">
            <td><strong>Nach Aufschlag</strong></td>
            <td class="text-end"><strong>${formatCurrency(bd.subtotalBeforeClubFee)}</strong></td>
          </tr>
          <tr>
            <td>+ Vereinsgeb&uuml;hr (${bd.clubFeePercent}%)</td>
            <td class="text-end">${formatCurrency(bd.clubFeeAmount)}</td>
          </tr>
          <tr class="table-success">
            <td><strong>Endpreis pro Person</strong></td>
            <td class="text-end"><strong>${formatCurrency(bd.finalPrice)}</strong></td>
          </tr>
          <tr class="table-info">
            <td><strong>Einnahmen Kategorie</strong> (${bd.count} &times; ${formatCurrency(bd.finalPrice)})</td>
            <td class="text-end"><strong>${formatCurrency(bd.categoryRevenue)}</strong></td>
          </tr>
        </tbody>
      </table>
    `;
    container.appendChild(section);
  });
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
}

function showEditorSection(titleText) {
  byId("trip-editor-title").textContent = titleText;
  byId("trip-list-section").classList.add("d-none");
  byId("trip-editor-section").classList.remove("d-none");
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

async function renderCurrentRoute() {
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
    showEditorSection("Neue Reise erstellen");
    return;
  }

  if (route.name === "trip-detail") {
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
}

function clearTripFormWithDefaults() {
  const form = byId("trip-form");
  form.reset();
  byId("tripFormId").value = "";
  resetCalculationResult();
  resetRegistrations();
  byId("trip-save-btn").textContent = "Reise speichern";

  form.adultDoubleCount.value = 0;
  form.adultDoublePrice.value = 0;
  form.adultMultiCount.value = 0;
  form.adultMultiPrice.value = 0;
  form.childCount.value = 0;
  form.childPrice.value = 0;

  if (state.settings) {
    form.markupPercent.value = state.settings.defaultMarkupPercent;
    form.clubFeePercent.value = state.settings.defaultClubFeePercent;
    form.distributionMethod.value = state.settings.defaultDistributionMethod;
    form.spaTaxPerPerson.value = state.settings.defaultSpaTaxPerPerson;
    form.spaTaxAgeThreshold.value = state.settings.defaultSpaTaxAgeThreshold;
  }

  resetSettlement();
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

  return {
    name: form.name.value,
    startDate: form.startDate.value,
    markupPercent: Number(form.markupPercent.value),
    clubFeePercent: Number(form.clubFeePercent.value),
    distributionMethod: form.distributionMethod.value,
    spaTaxPerPerson: Number(form.spaTaxPerPerson.value),
    spaTaxAgeThreshold: Number(form.spaTaxAgeThreshold.value),
    bookings: [
      { categoryType: "ADULT_DOUBLE", count: Number(form.adultDoubleCount.value), basePricePerPerson: Number(form.adultDoublePrice.value) },
      { categoryType: "ADULT_MULTI", count: Number(form.adultMultiCount.value), basePricePerPerson: Number(form.adultMultiPrice.value) },
      { categoryType: "CHILD", count: Number(form.childCount.value), basePricePerPerson: Number(form.childPrice.value) },
    ],
    groupExpenses: expenses,
  };
}

function fillTripForm(trip) {
  const form = byId("trip-form");
  byId("tripFormId").value = String(trip.id);
  form.name.value = trip.name;
  form.startDate.value = trip.startDate;
  form.markupPercent.value = trip.markupPercent;
  form.clubFeePercent.value = trip.clubFeePercent;
  form.distributionMethod.value = trip.distributionMethod;
  form.spaTaxPerPerson.value = trip.spaTaxPerPerson;
  form.spaTaxAgeThreshold.value = trip.spaTaxAgeThreshold;

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

  const firstExpense = trip.groupExpenses.length > 0 ? trip.groupExpenses[0] : null;
  form.expenseLabel.value = firstExpense ? firstExpense.label : "";
  form.expenseAmount.value = firstExpense ? firstExpense.amount : "";

  byId("trip-save-btn").textContent = "Reise aktualisieren";
}

async function loadSettings() {
  state.settings = await api("/api/settings");
  fillSettingsForm(state.settings);
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

  const tbody = byId("trip-list-body");
  const emptyInfo = byId("trip-list-empty");
  tbody.innerHTML = "";

  if (trips.length === 0) {
    emptyInfo.classList.remove("d-none");
    return;
  }

  emptyInfo.classList.add("d-none");
  trips.forEach((trip) => {
    const tr = document.createElement("tr");
    tr.innerHTML = `
      <td>${trip.id}</td>
      <td>${trip.name}</td>
      <td>${formatDate(trip.startDate)}</td>
      <td class="text-end"><button class="btn btn-sm btn-outline-primary" data-trip-id="${trip.id}">Öffnen</button></td>
    `;
    tbody.appendChild(tr);
  });
}

async function openTrip(tripId) {
  const trip = await api(`/api/trips/${tripId}`);
  state.currentTripId = trip.id;
  fillTripForm(trip);
  showEditorSection(`Reise #${trip.id}: ${trip.name}`);
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
  byId("registrations-table-wrapper").classList.add("d-none");
  byId("registrations-empty").classList.remove("d-none");
  byId("registrations-accordion").innerHTML = "";
  byId("recalculate-billings-btn").classList.add("d-none");
  byId("delete-registrations-btn").classList.add("d-none");
  byId("reg-count").textContent = "-";
  byId("reg-participant-count").textContent = "-";
  byId("reg-billing-total").textContent = "-";
  resetRoomSummary();
}

function resetRoomSummary() {
  byId("room-summary-section").classList.add("d-none");
  byId("room-detail-body").innerHTML = "";
  byId("room-detail-total").textContent = "0";
  byId("cat-summary-adult-double").textContent = "0";
  byId("cat-summary-adult-multi").textContent = "0";
  byId("cat-summary-child").textContent = "0";
  byId("cat-summary-total").textContent = "0";
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

  sortedCategories.forEach((cat) => {
    const tr = document.createElement("tr");
    tr.innerHTML = `
      <td>${cat}</td>
      <td class="text-end">${roomCounts[cat]}</td>
    `;
    detailBody.appendChild(tr);
    totalRooms += roomCounts[cat];
  });
  byId("room-detail-total").textContent = String(totalRooms);

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
  byId("registrations-table-wrapper").classList.remove("d-none");
  byId("recalculate-billings-btn").classList.remove("d-none");
  byId("delete-registrations-btn").classList.remove("d-none");

  let totalParticipants = 0;
  let totalBilling = 0;

  registrations.forEach((reg) => {
    totalParticipants += reg.participants.length;
    if (reg.billingTotal !== null) {
      totalBilling += reg.billingTotal;
    }
  });

  byId("reg-count").textContent = String(registrations.length);
  byId("reg-participant-count").textContent = String(totalParticipants);
  byId("reg-billing-total").textContent = formatCurrency(totalBilling);

  const accordion = byId("registrations-accordion");
  accordion.innerHTML = "";

  registrations.forEach((reg, index) => {
    const collapseId = `reg-collapse-${index}`;
    const headingId = `reg-heading-${index}`;
    const primaryName = reg.participants.length > 0 ? reg.participants[0].name : "Unbekannt";
    const billingBadge = reg.billingTotal !== null
      ? `<span class="badge bg-success ms-2">${formatCurrency(reg.billingTotal)}</span>`
      : `<span class="badge bg-secondary ms-2">Keine Abrechnung</span>`;
    const sourceBadge = reg.source === "manual"
      ? `<span class="badge bg-warning text-dark ms-2">Manuell</span>`
      : `<span class="badge bg-primary ms-2">CSV</span>`;

    let billingItemsHtml = "";
    if (reg.billingItems && reg.billingItems.length > 0) {
      billingItemsHtml = `
        <table class="table table-sm table-striped mb-0 mt-2">
          <thead>
            <tr>
              <th>Teilnehmer</th>
              <th>Kategorie</th>
              <th class="text-end">Preis</th>
            </tr>
          </thead>
          <tbody>
            ${reg.billingItems.map((item) => `
              <tr>
                <td>${item.participantName}</td>
                <td>${categoryLabels[item.categoryType] || item.categoryType}</td>
                <td class="text-end">${formatCurrency(item.price)}</td>
              </tr>
            `).join("")}
            <tr class="table-dark">
              <td colspan="2"><strong>Gesamt</strong></td>
              <td class="text-end"><strong>${formatCurrency(reg.billingTotal)}</strong></td>
            </tr>
          </tbody>
        </table>
      `;
    }

    let participantsHtml = reg.participants.map((p) =>
      `<li class="list-group-item d-flex justify-content-between align-items-center py-1">
        <span>${p.name}</span>
        <span class="text-muted small">${formatDate(p.birthDate)}</span>
      </li>`
    ).join("");

    const commentHtml = reg.comment ? `<div class="mt-2 small text-muted"><strong>Kommentar:</strong> ${reg.comment}</div>` : "";

    const item = document.createElement("div");
    item.className = "accordion-item";
    item.innerHTML = `
      <h2 class="accordion-header" id="${headingId}">
        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#${collapseId}" aria-expanded="false" aria-controls="${collapseId}">
          <span class="me-2"><strong>${primaryName}</strong></span>
          <span class="badge bg-info me-2">${reg.roomCategory}</span>
          <span class="badge bg-light text-dark me-2">${reg.participants.length} Pers.</span>
          ${sourceBadge}
          ${billingBadge}
        </button>
      </h2>
      <div id="${collapseId}" class="accordion-collapse collapse" aria-labelledby="${headingId}" data-bs-parent="#registrations-accordion">
        <div class="accordion-body">
          <div class="row">
            <div class="col-md-6">
              <h6>Teilnehmer</h6>
              <ul class="list-group list-group-flush">${participantsHtml}</ul>
              ${commentHtml}
              ${reg.receivedAt ? `<div class="mt-2 small text-muted">Eingegangen: ${reg.receivedAt}</div>` : ""}
            </div>
            <div class="col-md-6">
              <h6>Abrechnung</h6>
              ${billingItemsHtml || '<p class="text-muted small">Keine Abrechnung vorhanden.</p>'}
              ${reg.billingCalculatedAt ? `<div class="mt-2 small text-muted">Berechnet am: ${reg.billingCalculatedAt}</div>` : ""}
            </div>
          </div>
          <div class="mt-3 text-end">
            <button class="btn btn-sm btn-outline-danger" data-delete-registration="${reg.id}">Anmeldung l&ouml;schen</button>
          </div>
        </div>
      </div>
    `;
    accordion.appendChild(item);
  });

  renderRoomSummary(registrations);
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
  byId("settlement-total-revenue").textContent = "-";
  byId("settlement-planned-costs").textContent = "-";
  byId("settlement-total-expenses").textContent = "-";
  byId("settlement-surplus").textContent = "-";
  byId("settlement-surplus").className = "fs-4 fw-semibold";
  byId("planned-costs-body").innerHTML = "";
  byId("planned-costs-sum").textContent = "-";
  byId("actual-expenses-body").innerHTML = "";
  byId("additional-expenses-sum").textContent = "-";
  byId("actual-expenses-empty").classList.remove("d-none");
  byId("settlement-summary-revenue").textContent = "-";
  byId("settlement-summary-planned").textContent = "-";
  byId("settlement-summary-additional").textContent = "-";
  byId("settlement-summary-total-expenses").textContent = "-";
  byId("settlement-summary-surplus").textContent = "-";
  resetActualExpenseForm();
  resetRefundDistribution();
}

function resetActualExpenseForm() {
  byId("actualExpenseId").value = "";
  byId("actualExpenseLabel").value = "";
  byId("actualExpenseAmount").value = "";
  byId("actual-expense-save-btn").textContent = "Hinzufügen";
  byId("actual-expense-cancel-btn").classList.add("d-none");
}

function renderSettlement(settlement) {
  state.currentSettlement = settlement;

  // Summary cards
  byId("settlement-total-revenue").textContent = formatCurrency(settlement.totalRevenue);
  byId("settlement-planned-costs").textContent = formatCurrency(settlement.totalPlannedCosts);
  byId("settlement-total-expenses").textContent = formatCurrency(settlement.totalAllExpenses);
  byId("settlement-surplus").textContent = formatCurrency(settlement.surplus);
  byId("settlement-surplus").className = `fs-4 fw-semibold ${Number(settlement.surplus) >= 0 ? "text-success" : "text-danger"}`;

  // Planned costs table
  const plannedBody = byId("planned-costs-body");
  plannedBody.innerHTML = "";
  (settlement.plannedCostItems || []).forEach((item) => {
    const tr = document.createElement("tr");
    tr.innerHTML = `
      <td>${item.label}</td>
      <td class="text-end">${formatCurrency(item.amount)}</td>
    `;
    plannedBody.appendChild(tr);
  });
  if (plannedBody.children.length === 0) {
    const tr = document.createElement("tr");
    tr.innerHTML = '<td colspan="2" class="text-muted text-center">Keine geplanten Kosten (Buchungen mit Anzahl 0).</td>';
    plannedBody.appendChild(tr);
  }
  byId("planned-costs-sum").textContent = formatCurrency(settlement.totalPlannedCosts);

  // Additional expenses table
  const tbody = byId("actual-expenses-body");
  tbody.innerHTML = "";

  if (!settlement.expenses || settlement.expenses.length === 0) {
    byId("actual-expenses-empty").classList.remove("d-none");
  } else {
    byId("actual-expenses-empty").classList.add("d-none");
    settlement.expenses.forEach((expense) => {
      const tr = document.createElement("tr");
      tr.innerHTML = `
        <td>${expense.label}</td>
        <td class="text-end">${formatCurrency(expense.amount)}</td>
        <td class="text-end">
          <button class="btn btn-sm btn-outline-primary me-1" data-edit-expense="${expense.id}" data-label="${expense.label}" data-amount="${expense.amount}">Bearbeiten</button>
          <button class="btn btn-sm btn-outline-danger" data-delete-expense="${expense.id}">L&ouml;schen</button>
        </td>
      `;
      tbody.appendChild(tr);
    });
  }
  byId("additional-expenses-sum").textContent = formatCurrency(settlement.totalAdditionalExpenses);

  // Summary table
  byId("settlement-summary-revenue").textContent = formatCurrency(settlement.totalRevenue);
  byId("settlement-summary-planned").textContent = "− " + formatCurrency(settlement.totalPlannedCosts);
  byId("settlement-summary-additional").textContent = "− " + formatCurrency(settlement.totalAdditionalExpenses);
  byId("settlement-summary-total-expenses").textContent = formatCurrency(settlement.totalAllExpenses);
  const surplusEl = byId("settlement-summary-surplus");
  surplusEl.textContent = formatCurrency(settlement.surplus);
  surplusEl.className = Number(settlement.surplus) >= 0 ? "text-success" : "text-danger";

  renderRefundDistribution();
}

function resetRefundDistribution() {
  byId("refund-surplus").textContent = "-";
  byId("refund-revenue-base").textContent = "-";
  byId("refund-retention-percent-display").textContent = "-";
  byId("refund-retention").textContent = "-";
  byId("refund-retention-label").textContent = "= Einbehalt";
  byId("refund-distributable-formula").textContent = "Überschuss − Einbehalt";
  byId("refund-distributable").textContent = "-";
  byId("refund-body").innerHTML = "";
  byId("refund-total-billing").textContent = "-";
  byId("refund-total-amount").textContent = "-";
  byId("refund-empty").classList.remove("d-none");
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

  const retention = Math.round(totalRevenue * (retentionPercent / 100) * 100) / 100;
  const distributable = Math.round((surplus - retention) * 100) / 100;

  byId("refund-surplus").textContent = formatCurrency(surplus);
  byId("refund-revenue-base").textContent = formatCurrency(totalRevenue);
  byId("refund-retention-percent-display").textContent =
    retentionPercent.toFixed(2).replace(".", ",") + " %";
  byId("refund-retention-label").textContent =
    `= Einbehalt (${formatCurrency(totalRevenue)} × ${retentionPercent.toFixed(2).replace(".", ",")}%)`;
  byId("refund-retention").textContent = formatCurrency(retention);
  byId("refund-distributable-formula").textContent =
    `${formatCurrency(surplus)} − ${formatCurrency(retention)}`;
  byId("refund-distributable").textContent = formatCurrency(distributable);

  const totalBilling = billedRegs.reduce((sum, r) => sum + r.billingTotal, 0);

  const tbody = byId("refund-body");
  tbody.innerHTML = "";
  let totalRefund = 0;

  billedRegs.forEach((reg) => {
    const primaryName = reg.participants.length > 0 ? reg.participants[0].name : "Unbekannt";
    const share = totalBilling > 0 ? reg.billingTotal / totalBilling : 0;
    const sharePercent = Math.round(share * 10000) / 100;
    const refundAmount = Math.round(distributable * share * 100) / 100;
    totalRefund += refundAmount;

    const tr = document.createElement("tr");
    const refundClass = refundAmount >= 0 ? "text-success" : "text-danger";
    const refundLabel = refundAmount >= 0 ? "Rückerstattung" : "Nachzahlung";
    tr.innerHTML = `
      <td>${primaryName} <span class="text-muted small">(${reg.roomCategory})</span></td>
      <td class="text-end">${formatCurrency(reg.billingTotal)}</td>
      <td class="text-end">${sharePercent.toFixed(2)}%</td>
      <td class="text-end ${refundClass}">${formatCurrency(Math.abs(refundAmount))} <span class="small">(${refundLabel})</span></td>
    `;
    tbody.appendChild(tr);
  });

  totalRefund = Math.round(totalRefund * 100) / 100;
  byId("refund-total-billing").textContent = formatCurrency(totalBilling);
  const totalEl = byId("refund-total-amount");
  totalEl.textContent = formatCurrency(Math.abs(totalRefund));
  totalEl.className = `text-end ${totalRefund >= 0 ? "text-success" : "text-danger"}`;
}

async function loadSettlement(tripId) {
  try {
    const settlement = await api(`/api/trips/${tripId}/settlement`);
    renderSettlement(settlement);
  } catch (error) {
    resetSettlement();
  }
}

async function bootstrapPage() {
  if (window.bootstrap && window.bootstrap.Modal) {
    state.settingsModal = new window.bootstrap.Modal(byId("settings-modal"));
  } else {
    throw new Error("Bootstrap Modal konnte nicht initialisiert werden.");
  }

  await loadSettings();
  await loadTripList();
  window.addEventListener("popstate", async () => {
    try {
      await renderCurrentRoute();
    } catch (error) {
      alert("Route konnte nicht geladen werden: " + error.message);
    }
  });

  await renderCurrentRoute();
}

// --- Event Handlers ---

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
  };

  try {
    state.settings = await api("/api/settings", { method: "PUT", body: JSON.stringify(payload) });
    fillSettingsForm(state.settings);
    state.settingsModal.hide();
  } catch (error) {
    alert("Fehler beim Speichern der Settings: " + error.message);
  }
});

byId("new-trip-btn").addEventListener("click", async () => {
  try {
    if (!state.settings) {
      await loadSettings();
    }
    await navigateTo("/trips/new");
  } catch (error) {
    alert("Defaults konnten nicht geladen werden: " + error.message);
  }
});

byId("back-to-list-btn").addEventListener("click", async () => {
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
    alert("Import erfolgreich! " + data.length + " Anmeldungen insgesamt (manuelle bleiben erhalten).");
  } catch (error) {
    alert("Fehler beim Import: " + error.message);
  }
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
  row.className = "row g-2 align-items-end mb-1 manual-participant-row";
  row.innerHTML = `
    <div class="col-md-5">
      <label class="form-label">Name Teilnehmer ${num}</label>
      <input class="form-control manual-participant-name" type="text" required>
    </div>
    <div class="col-md-4">
      <label class="form-label">Geburtsdatum Teilnehmer ${num}</label>
      <input class="form-control manual-participant-birthdate" type="date" required>
    </div>
    <div class="col-md-3"></div>
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
  if (!(target instanceof HTMLElement)) return;

  const deleteRegId = target.getAttribute("data-delete-registration");
  if (!deleteRegId) return;

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
  if (!(target instanceof HTMLElement)) return;

  const tripId = byId("tripFormId").value;
  if (!tripId) return;

  const editId = target.getAttribute("data-edit-expense");
  if (editId) {
    byId("actualExpenseId").value = editId;
    byId("actualExpenseLabel").value = target.getAttribute("data-label") || "";
    byId("actualExpenseAmount").value = target.getAttribute("data-amount") || "";
    byId("actual-expense-save-btn").textContent = "Aktualisieren";
    byId("actual-expense-cancel-btn").classList.remove("d-none");
    return;
  }

  const deleteId = target.getAttribute("data-delete-expense");
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
  const tripId = byId("tripFormId").value;
  if (tripId) {
    await loadSettlement(tripId);
  }
});

// Recalculate refund distribution when retention percent changes
byId("retentionPercent").addEventListener("input", () => {
  renderRefundDistribution();
});

bootstrapPage().catch((error) => {
  byId("trip-list-empty").classList.remove("d-none");
  byId("trip-list-empty").textContent = "Fehler beim Laden: " + error.message;
});


