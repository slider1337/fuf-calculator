// Shared state, formatting and the authenticated API client.
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
  // Zeilen der reservierten Zimmer beim letzten Laden/Speichern - hinzugefuegte
  // Zeilen haben keinen Eintrag in formSnapshot und fielen sonst durch.
  reservationRowsSnapshot: "",
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


async function api(url, options = {}) {
  const response = await fetch(url, {
    ...options,
    headers: { "Content-Type": "application/json", "X-CSRF-Token": document.querySelector('meta[name="csrf-token"]').content, ...options.headers },
  });

  const text = await response.text();
  const data = text ? JSON.parse(text) : {};

  if (!response.ok) {
    throw new Error(JSON.stringify(data));
  }

  return data;
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


function setStatusNote(elementId, text, tone) {
  const element = byId(elementId);
  element.textContent = text;

  if (text === "") {
    element.className = "status-note d-none";
    return;
  }

  element.className = tone === null ? "status-note" : `status-note is-${tone}`;
}
