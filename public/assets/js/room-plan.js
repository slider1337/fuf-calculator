// Room demand, allocation and category summaries.
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

// DESIGN: Abgleich reserviert gegen angemeldet je Zimmertyp. Eine Anmeldung ist
// ein Zimmer. Die Zuordnung ignoriert Gross-/Kleinschreibung und Rand-Leerzeichen
// (roomTypeKey, wie im Backend); angezeigt wird der Name aus der Reservierung.
// Die Reservierungen kommen aus dem Formular, nicht aus dem gespeicherten Stand -
// so laeuft der Balken beim Tippen mit, wie das Soll neben dem Ist.
function roomFillRows(registrations, reservations) {
  const rows = new Map();
  const rowFor = (roomType) => {
    const key = roomTypeKey(roomType);
    if (!rows.has(key)) {
      rows.set(key, { roomType: String(roomType).trim(), reserved: 0, registered: 0 });
    }
    return rows.get(key);
  };

  reservations.forEach((reservation) => {
    rowFor(reservation.roomType).reserved += reservation.count;
  });
  registrations.forEach((reg) => {
    if (reg.roomCategory && reg.participants.some(p => !p.status || p.status === 'confirmed')) { rowFor(reg.roomCategory).registered += 1; }
  });

  return Array.from(rows.values())
    .filter((row) => row.reserved > 0 || row.registered > 0)
    .sort((a, b) => a.roomType.localeCompare(b.roomType, "de"));
}

// Teilweise gefuellt heisst Zimmer frei (gelb), genau voll passt (gruen),
// darueber muss nachreserviert werden (rot, voll gefuellt).
function roomFillState(row) {
  if (row.registered > row.reserved) {
    return "is-over";
  }
  return row.registered === row.reserved ? "is-full" : "is-partial";
}

function roomFillCell(row) {
  const fillState = roomFillState(row);
  const share = row.reserved > 0 ? Math.min(100, Math.round((row.registered / row.reserved) * 100)) : 100;
  const label = `${row.registered} von ${row.reserved} reservierten Zimmern belegt`;

  return `
    <div class="room-fill">
      <span class="bar ${fillState}" role="img" aria-label="${escapeHtml(label)}"><span style="width: ${share}%"></span></span>
      <b class="room-fill-num ${fillState}">${row.registered}/${row.reserved}</b>
    </div>
  `;
}

// Ohne jede Reservierung bleibt es bei der bisherigen Zaehlung aus den
// Anmeldungen - sonst stuende jede alte Reise komplett rot da.
function roomCountCell(row, maxRooms) {
  const share = maxRooms > 0 ? Math.round((row.registered / maxRooms) * 100) : 0;

  return `
    <div class="room-fill">
      <span class="bar"><span style="width: ${share}%"></span></span>
      <b class="room-fill-num">${row.registered}</b>
    </div>
  `;
}

function roomBalanceSummary(rows) {
  const missing = rows.reduce((sum, row) => sum + Math.max(0, row.registered - row.reserved), 0);
  const free = rows.reduce((sum, row) => sum + Math.max(0, row.reserved - row.registered), 0);

  if (missing === 0 && free === 0) {
    return "alles passt";
  }

  return [
    missing > 0 ? `${missing} nachreservieren` : null,
    free > 0 ? `${free} frei` : null,
  ].filter(Boolean).join(" · ");
}

function renderRoomSummary() {
  const registrations = state.currentRegistrations || [];
  const reservations = roomReservationsFromForm().filter((reservation) => reservation.roomType !== "");
  const rows = roomFillRows(registrations, reservations);

  if (rows.length === 0) {
    resetRoomSummary();
    return;
  }

  byId("room-summary-section").classList.remove("d-none");

  const hasReservations = rows.some((row) => row.reserved > 0);
  const totalRegistered = rows.reduce((sum, row) => sum + row.registered, 0);
  const totalReserved = rows.reduce((sum, row) => sum + row.reserved, 0);
  // DESIGN: Ohne Reservierungen bezieht sich der Balken auf die groesste
  // Kategorie, nicht auf die Gesamtzahl - sonst blieben alle Balken winzig.
  const maxRooms = Math.max(...rows.map((row) => row.registered));

  byId("room-detail-body").innerHTML = rows.map((row) => `
    <tr>
      <td class="tb-head">${escapeHtml(row.roomType)}</td>
      <td class="room-fill-cell">${hasReservations ? roomFillCell(row) : roomCountCell(row, maxRooms)}</td>
    </tr>
  `).join("");

  byId("room-detail-total").textContent = hasReservations
    ? `${totalRegistered}/${totalReserved}`
    : String(totalRegistered);
  byId("sum-zimmer").textContent = hasReservations
    ? roomBalanceSummary(rows)
    : `aus den Anmeldungen abgeleitet · ${totalRegistered} Zimmer`;

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
