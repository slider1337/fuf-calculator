// Travel form, room reservations and save actions.
let roomReservationSeq = 0;

const iconMinus = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14"/></svg>';
const iconPlus = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg>';

function roomReservationRow(roomType = "", count = 0) {
  roomReservationSeq += 1;
  const n = roomReservationSeq;
  const row = document.createElement("div");
  row.className = "room-res-row";
  row.dataset.reservationRow = String(n);
  row.innerHTML = `
    <div class="in room-res-type"><input id="roomReservationType-${n}" class="room-res-type-input" type="text" list="room-type-suggestions" placeholder="z.&nbsp;B. 2-Bettzimmer" aria-label="Zimmertyp" value="${escapeHtml(roomType)}"></div>
    <div class="stepper">
      <button class="step" type="button" data-step="-1" data-step-target="roomReservationCount-${n}" aria-label="Anzahl reservierter Zimmer verringern">${iconMinus}</button>
      <div class="in"><input id="roomReservationCount-${n}" class="room-res-count-input" type="text" inputmode="numeric" value="${escapeHtml(formatDecimal(count))}" aria-label="Anzahl reservierter Zimmer"><span class="u">Zi.</span></div>
      <button class="step" type="button" data-step="1" data-step-target="roomReservationCount-${n}" aria-label="Anzahl reservierter Zimmer erh&ouml;hen">${iconPlus}</button>
    </div>
    <button type="button" class="ib ib-danger" data-remove-room-reservation aria-label="Zimmertyp entfernen" title="Zimmertyp entfernen">${iconTrash}</button>
  `;
  return row;
}

function roomReservationRows() {
  return Array.from(byId("room-reservations-list").querySelectorAll(".room-res-row"));
}

function setRoomReservations(reservations) {
  const list = byId("room-reservations-list");
  list.innerHTML = "";
  reservations.forEach((reservation) => {
    list.appendChild(roomReservationRow(reservation.roomType, reservation.count));
  });
  renderRoomReservationsEmpty();
  // Neue Liste, neuer Abgleich - sonst stuende unter Zimmerbedarf noch der
  // Stand der vorigen Reise.
  renderRoomSummary();
}

function renderRoomReservationsEmpty() {
  byId("room-reservations-empty").classList.toggle("d-none", roomReservationRows().length > 0);
}

function roomReservationsFromForm() {
  return roomReservationRows().map((row) => ({
    roomType: row.querySelector(".room-res-type-input").value.trim(),
    count: Math.max(0, Math.round(numberFromField(row.querySelector(".room-res-count-input").id))),
  }));
}

// Wie RoomReservation::matchKeyFor() im Backend.
function roomTypeKey(roomType) {
  return String(roomType).trim().toLowerCase();
}

// Dieselben Regeln wie TripService::roomReservationPayloads(), nur mit
// lesbarer Meldung statt des rohen 422-JSON.
function roomReservationProblem(reservations) {
  if (reservations.some((reservation) => reservation.roomType === "" && reservation.count > 0)) {
    return "Bei den reservierten Zimmern fehlt in einer Zeile der Zimmertyp.";
  }

  const seen = new Set();
  for (const reservation of reservations) {
    if (reservation.roomType === "") {
      continue;
    }
    const key = roomTypeKey(reservation.roomType);
    if (seen.has(key)) {
      return `Der Zimmertyp „${reservation.roomType}“ steht bei den reservierten Zimmern doppelt.`;
    }
    seen.add(key);
  }

  return null;
}

function reservedRoomTotal() {
  return roomReservationsFromForm().reduce((sum, reservation) => sum + reservation.count, 0);
}

// DESIGN: Vorschlaege aus der Auswahl der manuellen Anmeldung plus allem, was
// in den Anmeldungen dieser Reise steht - so passt der Typ meist schon beim
// ersten Tippen zu dem, was der Abgleich unter Zimmerbedarf erwartet.
function renderRoomTypeSuggestions() {
  const byKey = new Map();
  const add = (roomType) => {
    const trimmed = String(roomType ?? "").trim();
    if (trimmed !== "" && !byKey.has(roomTypeKey(trimmed))) {
      byKey.set(roomTypeKey(trimmed), trimmed);
    }
  };

  Array.from(byId("manualRoomCategory").options).forEach((option) => add(option.value));
  (state.currentRegistrations || []).forEach((reg) => add(reg.roomCategory));

  byId("room-type-suggestions").innerHTML = Array.from(byKey.values())
    .sort((a, b) => a.localeCompare(b, "de"))
    .map((roomType) => `<option value="${escapeHtml(roomType)}"></option>`)
    .join("");
}

function roomReservationRowIds() {
  return roomReservationRows().map((row) => row.dataset.reservationRow).join(",");
}

function clearTripFormWithDefaults() {
  byId('trip-registration-mode-summary').classList.add('d-none');byId('trip-registration-mode-create').classList.remove('d-none');byId('trip-registration-mode-choice').disabled=false;
  const form = byId("trip-form");
  form.reset();
  byId("tripFormId").value = "";
  resetCalculationResult();
  resetRegistrations();
  resetSalesPrices();
  setTripSaveLabel("Reise speichern");

  form.adultDoubleCount.value = formatDecimal(0);
  form.adultDoublePrice.value = formatDecimal(0);
  form.adultMultiCount.value = formatDecimal(0);
  form.adultMultiPrice.value = formatDecimal(0);
  form.childCount.value = formatDecimal(0);
  form.childPrice.value = formatDecimal(0);
  form.averageAdultPrice.checked = false;
  setRoomReservations([]);

  if (state.settings) {
    form.markupPercent.value = formatDecimal(state.settings.defaultMarkupPercent);
    form.clubFeePercent.value = formatDecimal(state.settings.defaultClubFeePercent);
    form.distributionMethod.value = state.settings.defaultDistributionMethod;
    form.spaTaxPerPerson.value = formatDecimal(state.settings.defaultSpaTaxPerPerson);
    form.spaTaxAgeThreshold.value = formatDecimal(state.settings.defaultSpaTaxAgeThreshold);
    form.adultAgeThreshold.value = formatDecimal(state.settings.defaultAdultAgeThreshold);
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
      amount: parseDecimal(form.expenseAmount.value),
    });
  }

  const roomReservations = roomReservationsFromForm();
  const reservationProblem = roomReservationProblem(roomReservations);
  if (reservationProblem !== null) {
    throw new Error(reservationProblem);
  }

  const averageAdultPrice = form.averageAdultPrice.checked;
  // Beim einheitlichen Erwachsenenpreis gibt es nur ein Verkaufspreisfeld. Es geht
  // auf beide Zimmerzeilen, damit der gespeicherte Stand zum angezeigten passt.
  const adultSalesPrice = averageAdultPrice ? state.salesPrices.ADULT : null;

  return {
    registrationMode: state.currentTripId ? state.currentTrip.registrationMode : byId('trip-registration-mode-choice').value,
    name: form.name.value,
    startDate: form.startDate.value,
    endDate: form.endDate.value,
    markupPercent: parseDecimal(form.markupPercent.value),
    clubFeePercent: parseDecimal(form.clubFeePercent.value),
    distributionMethod: form.distributionMethod.value,
    spaTaxPerPerson: parseDecimal(form.spaTaxPerPerson.value),
    spaTaxAgeThreshold: parseDecimal(form.spaTaxAgeThreshold.value),
    adultAgeThreshold: parseDecimal(form.adultAgeThreshold.value),
    averageAdultPrice,
    spaTaxCount: parseDecimal(form.spaTaxCount.value),
    bookings: [
      {
        categoryType: "ADULT_DOUBLE",
        count: parseDecimal(form.adultDoubleCount.value),
        basePricePerPerson: parseDecimal(form.adultDoublePrice.value),
        salesPricePerPerson: averageAdultPrice ? adultSalesPrice : state.salesPrices.ADULT_DOUBLE,
      },
      {
        categoryType: "ADULT_MULTI",
        count: parseDecimal(form.adultMultiCount.value),
        basePricePerPerson: parseDecimal(form.adultMultiPrice.value),
        salesPricePerPerson: averageAdultPrice ? adultSalesPrice : state.salesPrices.ADULT_MULTI,
      },
      {
        categoryType: "CHILD",
        count: parseDecimal(form.childCount.value),
        basePricePerPerson: parseDecimal(form.childPrice.value),
        salesPricePerPerson: state.salesPrices.CHILD,
      },
    ],
    groupExpenses: expenses,
    roomReservations,
  };
}

function fillTripForm(trip) {
  byId('trip-registration-mode-choice').value=trip.registrationMode||'clubdesk_csv';
  const form = byId("trip-form");
  byId("tripFormId").value = String(trip.id);
  form.name.value = trip.name;
  form.startDate.value = trip.startDate;
  form.endDate.value = trip.endDate ?? "";
  form.markupPercent.value = formatDecimal(trip.markupPercent);
  form.clubFeePercent.value = formatDecimal(trip.clubFeePercent);
  form.distributionMethod.value = trip.distributionMethod;
  form.spaTaxPerPerson.value = formatDecimal(trip.spaTaxPerPerson);
  form.spaTaxAgeThreshold.value = formatDecimal(trip.spaTaxAgeThreshold);
  form.adultAgeThreshold.value = formatDecimal(trip.adultAgeThreshold ?? 16);
  form.averageAdultPrice.checked = Boolean(trip.averageAdultPrice);
  form.spaTaxCount.value = formatDecimal(trip.spaTaxCount ?? 0);

  const byType = {};
  trip.bookings.forEach((booking) => {
    byType[booking.categoryType] = booking;
  });

  form.adultDoubleCount.value = formatDecimal(byType.ADULT_DOUBLE ? byType.ADULT_DOUBLE.count : 0);
  form.adultDoublePrice.value = formatDecimal(byType.ADULT_DOUBLE ? byType.ADULT_DOUBLE.basePricePerPerson : 0);
  form.adultMultiCount.value = formatDecimal(byType.ADULT_MULTI ? byType.ADULT_MULTI.count : 0);
  form.adultMultiPrice.value = formatDecimal(byType.ADULT_MULTI ? byType.ADULT_MULTI.basePricePerPerson : 0);
  form.childCount.value = formatDecimal(byType.CHILD ? byType.CHILD.count : 0);
  form.childPrice.value = formatDecimal(byType.CHILD ? byType.CHILD.basePricePerPerson : 0);
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
  form.expenseAmount.value = formatDecimal(firstExpense ? firstExpense.amount : "");

  setRoomReservations(trip.roomReservations || []);

  setTripSaveLabel("Reise aktualisieren");
  // DESIGN: Der gefuellte Stand ist der gespeicherte - ab hier zaehlt jede
  // Abweichung als ungespeicherte Aenderung.
  snapshotTripForm();
  renderSectionSummaries();
  renderRoomSums();
  renderJumpState();
}


async function openTrip(tripId) {
  const trip = await api(`/api/trips/${tripId}`);
  state.currentTripId = trip.id;
  state.currentTrip = trip;
  await loadEventSelector(trip.eventId);
  if (Array.isArray(trip.bookings)) { fillTripForm(trip); } else { clearTripFormWithDefaults(); byId('tripFormId').value = trip.id; }
  showEditorSection(trip.name, `Reise #${trip.id}`);
  renderTripManagement(trip);
  showTab(tabFromHash());
  if (byId(`tab-${tabFromHash()}`).classList.contains('d-none')) {
    showTab(!tripUsesEasyVerein(trip)&&trip.capabilities.includes('calculation.manage')?'planung':trip.capabilities.includes('settlement.view') ? 'abrechnung' : 'uebersicht');
  }
  byId("retentionPercent").value = formatDecimal(trip.clubFeePercent);
  resetCalculationResult(); resetRegistrations(); resetSettlement();
  if (trip.closedAt && trip.calculationSnapshot) { renderCalculationResult(trip.calculationSnapshot); }
  else if (trip.capabilities.includes('calculation.manage')) { await calculateTripResult(String(trip.id)); }
  if (trip.capabilities.includes('registration.view')) { await loadRegistrations(String(trip.id)); }
  if (trip.capabilities.includes('settlement.view')) { await loadSettlement(String(trip.id)); }
  if (typeof loadAnalyticsWorkspace === "function" && tripUsesEasyVerein(trip) && trip.capabilities.includes('analytics.view')) { await loadAnalyticsWorkspace(); }
  applyTripWriteControls();
}


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

// DESIGN: Regel 3 und RESPONSIVE.md - auf dem Handy zaehlt der - / + Stepper,
// nicht die Tastatur. Die Knoepfe stehen immer im DOM und sind ab 901 px

async function backToList(event) {
  event.preventDefault();
  await navigateTo("/");
}

function initTripEditor() {
  // ausgeblendet, damit die Tabelle am Desktop unveraendert bleibt.
  //
  // Der Wert geht durch numberFromField(), damit "1.234,56" hier genauso gelesen
  // wird wie ueberall sonst, und loest danach ein input-Event aus - so laufen
  // Vorschau, Summenspalte und Dirty-State genauso mit wie beim Tippen.
  byId("sec-unterkunft-body").addEventListener("click", (event) => {
    const button = event.target.closest("[data-step]");

    if (!button) {
      return;
    }

    const field = byId(button.dataset.stepTarget);
    const next = Math.max(0, Math.round(numberFromField(button.dataset.stepTarget)) + Number(button.dataset.step));
    field.value = String(next);
    field.dispatchEvent(new Event("input", { bubbles: true }));
  });

  // DESIGN: Hinzufuegen und Entfernen loesen ein input-Event am Formular aus,
  // damit Vorschau, Kopfzeilen und Dirty-State mitlaufen wie beim Tippen.
  byId("add-room-reservation-btn").addEventListener("click", () => {
    const row = roomReservationRow();
    byId("room-reservations-list").appendChild(row);
    renderRoomReservationsEmpty();
    row.querySelector(".room-res-type-input").focus();
    row.dispatchEvent(new Event("input", { bubbles: true }));
  });

  byId("room-reservations-list").addEventListener("click", (event) => {
    const button = event.target.closest("[data-remove-room-reservation]");
    if (!button) {
      return;
    }

    button.closest(".room-res-row").remove();
    renderRoomReservationsEmpty();
    byId("trip-form").dispatchEvent(new Event("input", { bubbles: true }));
  });

  byId("new-trip-btn").addEventListener("click", startNewTrip);

  // DESIGN: Der Knopf in der Leerzustands-Karte macht dasselbe wie der in der Kopfleiste.
  byId("trip-list-empty-create-btn").addEventListener("click", startNewTrip);

  // DESIGN: Das Plus in der Kopfleiste macht dasselbe wie der Knopf im Menue.
  // Es steht unter lg an der Stelle, an der bis 4i eine Bottom-Bar war.
  byId("new-trip-head-btn").addEventListener("click", startNewTrip);

  // DESIGN: Clientseitige Suche ueber den Reisenamen, ohne erneuten Request.
  byId("trip-search").addEventListener("input", () => {
    renderTripList();
  });

  byId("back-to-list-btn").addEventListener("click", async (event) => {
    // DESIGN: Der Breadcrumb-Link ersetzt den Button, das Routing bleibt clientseitig.
    event.preventDefault();
    await navigateTo(event.currentTarget.getAttribute("href"));
  });

  // DESIGN: Denselben Weg zurueck gibt es zweimal - im Reisekopf auf dem Desktop,
  // in der App-Kopfleiste auf dem Handy.

  byId("trip-head-back-btn").addEventListener("click", backToList);
  byId("app-head-back-btn").addEventListener("click", backToList);

  byId("trip-bar-kpi").addEventListener("click", openCalcSheet);

  // DESIGN: Aus dem Menuepanel heraus geht es weiter - in ein Modal, in eine neue
  // Reise oder zurueck zur Liste. Das Panel muss vorher zu sein, sonst liegt es
  // ueber dem, was es geoeffnet hat. Ab lg gibt es keine Offcanvas-Instanz, dort
  // laeuft der Aufruf ins Leere.
  byId("app-menu").addEventListener("click", (event) => {
    if (!(event.target instanceof HTMLElement) || !event.target.closest("a, button")) {
      return;
    }
    window.bootstrap?.Offcanvas.getInstance(byId("app-menu"))?.hide();
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

    try {
      const payload = tripPayloadFromForm();
      if (!tripIdValue) { payload.eventId = byId('trip-event-select').value === '' ? null : Number(byId('trip-event-select').value); }
      else if (Number(byId('trip-event-select').value) !== (state.currentTrip?.eventId || 0)) { throw new Error('Die Zuordnung bestehender Reisen erfolgt in der Veranstaltung.'); }
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


}
