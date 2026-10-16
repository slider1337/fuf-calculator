// Travel list, search and archive filter.
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
// DESIGN: Icons der Kartenzeile der Reiseliste. Sie stehen inline im Markup,
// weil Icons im Projekt inline-SVG sind, und sind ab 901 px ausgeblendet -
// in der Tabelle am Desktop haben sie nichts zu suchen.
const iconCalendarSm = '<svg class="tb-ico" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>';
const iconUsersSm = '<svg class="tb-ico" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M16 21v-2a4 4 0 00-4-4H6a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M19 8v6M22 11h-6"/></svg>';

function tripSurplusCell(trip) {
  if (trip.surplus === null || trip.surplus === undefined) {
    return '<td class="r tb-trip-sum" data-col="Überschuss">–</td>';
  }

  const toneClass = trip.surplus < 0 ? "fuf-neg" : "fuf-pos";
  const sign = trip.surplus < 0 ? "− " : "+ ";
  const amount = formatCurrency(Math.abs(trip.surplus));
  const plannedChip = trip.surplusBasis === "calculation"
    ? ' <span class="chip c-grey">geplant</span>'
    : "";

  return `<td class="r tb-trip-sum" data-col="Überschuss"><span class="${toneClass}">${sign}${amount}</span>${plannedChip}</td>`;
}

function tripListRow(trip) {
  const nightsInfo = trip.endDate
    ? `<span class="help tb-sub">${trip.nights} ${trip.nights === 1 ? "Nacht" : "Nächte"}</span>`
    : "";

  return `
      <td class="help fuf-num tb-col-id">#${trip.id}</td>
      <td class="tb-head"><button class="tb-title" type="button" data-trip-id="${trip.id}">${escapeHtml(trip.name)}</button>${trip.archivedAt?' <span class="chip c-grey">Archiviert</span>':''}</td>
      <td class="fuf-num tb-trip-meta">${iconCalendarSm}${formatTripPeriod(trip)}${nightsInfo}</td>
      <td class="r tb-trip-meta">${iconUsersSm}${trip.registeredParticipants ?? '–'} / ${trip.plannedParticipants ?? '–'}</td>
      ${tripSurplusCell(trip)}
      <td class="r tb-act">
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
    ? (trips.every(t => t.registeredParticipants !== null && t.plannedParticipants !== null) ? `${trips.length} ${tripWord} · ${participants} Teilnehmer` : `${trips.length} ${tripWord} · Teilnehmerzahlen teilweise nicht freigegeben`)
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
  const trips = await api("/api/trips" + (byId("trip-archive-filter")?.checked ? "?archived=true" : ""));
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

function initTrips() {
  byId("trip-archive-filter").addEventListener("change",()=>performManagement(loadTripList));

}
