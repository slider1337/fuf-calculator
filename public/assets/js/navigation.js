// Routing, tabs, mobile navigation and dirty-state feedback.
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
  state.reservationRowsSnapshot = roomReservationRowIds();
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

  // Zeile hinzugefuegt oder entfernt zaehlt als eine Aenderung; geaenderte
  // Werte in bestehenden Zeilen zaehlen oben schon je Feld.
  if (snapshot !== null && roomReservationRowIds() !== state.reservationRowsSnapshot) {
    changed += 1;
  }

  byId("trip-dirty-note").textContent = changed === 0
    ? ""
    : `Ungespeicherte Änderungen: ${changed} ${changed === 1 ? "Feld" : "Felder"}`;
  renderStaleNote(changed);
}

// DESIGN: Status-Punkt je Abschnitt - gefuellt, sobald der Abschnitt Inhalt hat.
function renderJumpState() {
  const registrations = activeRegistrations(state.currentRegistrations || []);
  const settlement = state.currentSettlement;
  const filled = {
    "sec-eckdaten": byId("name").value.trim() !== "" && byId("startDate").value !== "",
    "sec-aufschlaege": byId("markupPercent").value !== "",
    "sec-unterkunft": numberFromField("adultDoubleCount") + numberFromField("adultMultiCount") + numberFromField("childCount") > 0
      || reservedRoomTotal() > 0,
    "sec-zusatz": byId("expenseLabel").value.trim() !== "" && byId("expenseAmount").value !== "",
    "sec-kalkulation": !byId("result-panel").classList.contains("d-none"),
    "registrations-section": registrations.length > 0,
    "room-summary-section": !byId("room-summary-section").classList.contains("d-none"),
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
  return document.querySelector('#tripTabs .nav-link.active')?.id.replace('tab-', '') || 'planung';
}

// DESIGN: Die wichtigste Zahl des jeweiligen Bereichs, damit man sie aus jeder
// Scrollposition sieht. Sie wird nicht neu gerechnet, sondern von der Stelle
// abgelesen, die sie ohnehin schon anzeigt.
function renderHeadKpi() {
  const isSettlement = activeTabName() === "abrechnung";
  const source = isSettlement ? byId("settlement-surplus") : byId("panel-surplus");
  const value = source.textContent.trim();
  const label = isSettlement ? "Überschuss Ist" : "Überschuss";
  const allowed = !state.currentTrip || state.currentTrip.capabilities.some(c => ["calculation.manage","settlement.view"].includes(c));
  const shown = allowed ? (value === "" ? "–" : value) : "Nicht freigegeben";
  const isNegative = value.includes("−") || value.startsWith("-");

  byId("trip-head-kpi-label").textContent = label;
  byId("trip-head-kpi-value").textContent = shown;
  byId("trip-head-kpi-value").className = isNegative ? "fuf-neg" : "";

  // DESIGN: Auf dem Handy steht dieselbe Zahl in der Bottom-Bar. Sie wird an
  // derselben Stelle abgelesen, damit es keine zweite Rechnung gibt, die
  // auseinanderlaufen koennte.
  byId("trip-bar-kpi-label").textContent = label;
  byId("trip-bar-kpi-value").textContent = shown;
  byId("trip-bar-kpi-value").className = isNegative ? "fuf-neg" : "";
}

// DESIGN: Das Blatt des aktiven Bereichs. Der Umweg ueber app.js statt
// data-bs-toggle am Knopf haelt die Zuordnung an einer Stelle - sonst muesste
// der Tab-Wechsel ein Attribut nachziehen.
function openCalcSheet() {
  if (!window.bootstrap || !window.bootstrap.Offcanvas) {
    return;
  }
  const sheet = byId(activeTabName() === "abrechnung" ? "calc-sheet-abrechnung" : "calc-sheet-planung");
  window.bootstrap.Offcanvas.getOrCreateInstance(sheet).show();
}

// DESIGN: Wisch nach unten schliesst das Blatt. Bootstrap kennt nur Backdrop,
// Escape und den Knopf; der Griff ist die Flaeche, auf der ein Blatt
// erwartungsgemaess nachgibt. 60 px trennen den Wisch vom Tipp, der ueber
// data-bs-dismiss ohnehin schliesst.
const SHEET_SWIPE_THRESHOLD = 60;

function observeSheetSwipe() {
  document.querySelectorAll(".sheet-grab").forEach((grab) => {
    let startY = null;

    grab.addEventListener("touchstart", (event) => {
      startY = event.touches[0].clientY;
    }, { passive: true });

    grab.addEventListener("touchend", (event) => {
      if (startY === null) {
        return;
      }
      const moved = event.changedTouches[0].clientY - startY;
      startY = null;
      if (moved < SHEET_SWIPE_THRESHOLD) {
        return;
      }
      // Der Tipp-Weg von data-bs-dismiss wuerde sonst gleich hinterher feuern.
      event.preventDefault();
      const sheet = grab.closest(".offcanvas-lg");
      if (sheet && window.bootstrap && window.bootstrap.Offcanvas) {
        window.bootstrap.Offcanvas.getOrCreateInstance(sheet).hide();
      }
    });
  });
}

function renderHeadTabState() {
  document.body.dataset.tripTab = activeTabName();
  byId("trip-head").classList.toggle("is-abrechnung", activeTabName() === "abrechnung");
  renderHeadKpi();
  renderManagementBar();
}

// DESIGN: Der Tab steht im Hash, damit Zurueck-Button, Neuladen und geteilte
// Links denselben Bereich zeigen.
function tabFromHash() {
  const value = window.location.hash.slice(1);
  return ['uebersicht','planung','termin','anmeldungen','auswertung','abrechnung','team'].includes(value) ? value : 'uebersicht';
}

function showTab(name) {
  if (activeTabName() === name) {
    return;
  }

  const trigger = byId(`tab-${name}`);
  if (!trigger || trigger.classList.contains('d-none')) { return; }
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
  const registrations = activeRegistrations(state.currentRegistrations || []);
  const settlement = state.currentSettlement;
  const participants = registrations.reduce((sum, reg) => sum + activeRegistrationPeople(reg).length, 0);
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


function showListSection() {
  hideEventSections();
  byId("trip-list-section").classList.remove("d-none");
  byId("trip-editor-section").classList.add("d-none");
  // DESIGN: Ohne offene Reise zeigt die Kopfleiste auf dem Handy wieder das Logo.
  document.body.classList.remove("is-trip-view");
  window.scrollTo({ top: 0 });
  if (state.sectionObserver) {
    state.sectionObserver.disconnect();
    state.sectionObserver = null;
  }
}

// DESIGN: Die Sprungnavigation entfaellt auf dem Handy (Abschnitt 5c im CSS).
// Statt einer Liste von Sprungzielen starten die Abschnitte zugeklappt und
// zeigen nur ihre Zusammenfassung - man oeffnet, was man braucht.
//
// Gemessen wird einmal beim Oeffnen der Reise, nicht bei jedem Resize: wer am
// Desktop das Fenster schmal zieht, soll nicht mitten in der Arbeit alles
// zufallen sehen. Die Klassen werden direkt gesetzt, weil zu diesem Zeitpunkt
// noch keine Collapse-Instanz existiert - Bootstrap liest den Stand beim
// ersten Umschalten von der Klasse ab.
//
// sec-kalkulation war bis dahin ausgenommen, weil die Section selbst ein
// Akkordeon ist und ihre Zeilen ohnehin zu sind. Sie hat seitdem einen
// Chevron wie alle anderen: mit vier Kennzahl-Kacheln, drei Zeilen und der
// Fusszeile ist sie auch mit zugeklappten Zeilen lang, und eine einzige
// Ausnahme unter sieben Abschnitten liest sich wie ein Fehler.
//
// Die Schleife laeuft ueber alle .chev mit collapse-Ziel. Die Chevrons der
// Kalkulations- und Anmeldungszeilen traegt sie damit theoretisch auch - zu
// diesem Zeitpunkt sind beide Listen aber noch nicht gerendert, und beim
// Wechsel auf eine andere Reise werden sie danach ohnehin neu aufgebaut.
const SECTION_COLLAPSE_MAX_WIDTH = 992;

// DESIGN: Bei einer neuen Reise sind die drei obersten Abschnitte der
// Einstieg - Name und Zeitraum, Aufschlaege und Abgaben, Unterkunft und
// Teilnehmer. Sie bleiben offen, damit man nicht erst dreimal tippen muss,
// um anfangen zu koennen.
const NEW_TRIP_OPEN_SECTIONS = [
  "sec-eckdaten-body",
  "sec-aufschlaege-body",
  "sec-unterkunft-body",
];

// Die Funktion setzt den Stand, statt nur zuzuklappen: wer erst eine
// bestehende Reise ansieht und dann "Neue Reise" drueckt, findet die
// Abschnitte sonst zugeklappt vor - sie sind es aus dem vorigen Aufruf, und
// eine Regel, die nur schliesst, bekommt sie nie wieder auf.
function setSectionsOnMobile(openIds = []) {
  if (window.innerWidth >= SECTION_COLLAPSE_MAX_WIDTH) {
    return;
  }

  document.querySelectorAll('.chev[data-bs-toggle="collapse"]').forEach((chev) => {
    const body = document.querySelector(chev.dataset.bsTarget);
    if (!body) {
      return;
    }
    const open = openIds.includes(body.id);
    body.classList.toggle("show", open);
    chev.setAttribute("aria-expanded", open ? "true" : "false");
    // Bootstrap markiert den zugeklappten Ausloeser selbst mit .collapsed.
    chev.classList.toggle("collapsed", !open);
  });
}

function showEditorSection(titleText, breadcrumbText, openSections = []) {
  hideEventSections();
  byId("trip-editor-title").textContent = titleText;
  // DESIGN: Auf dem Handy traegt die App-Kopfleiste den Reisenamen, weil im
  // 52-px-Kopf darunter nur der Umschalter Platz hat.
  byId("app-head-title").textContent = titleText;
  document.body.classList.add("is-trip-view");
  // DESIGN: Die Ueberschrift traegt den Reisenamen, der Breadcrumb die Nummer.
  byId("trip-editor-breadcrumb").textContent = breadcrumbText;
  byId("trip-list-section").classList.add("d-none");
  byId("trip-editor-section").classList.remove("d-none");
  // Mit der sticky Kontextleiste faellt eine mitgeschleppte Scrollposition
  // auf: man landet mitten in der Seite und die Leiste ist schon geschrumpft.
  window.scrollTo({ top: 0 });
  renderHeadScrollState();
  renderHeadTabState();
  setSectionsOnMobile(openSections);
  observeSections();
}

function matchRoute(pathname) {
  if (pathname === '/events') { return { name: 'events' }; }
  const eventMatch = pathname.match(/^\/events\/(\d+)$/);
  if (eventMatch) { return { name: 'event', eventId: Number(eventMatch[1]) }; }
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

  if (currentPath !== pathname && !allowManagementNavigation()) { return; }
  if (currentPath !== pathname) {
    const method = replace ? "replaceState" : "pushState";
    window.history[method]({}, "", pathname);
  }

  await renderCurrentRoute();
}

async function renderCurrentRoute(options = {}) {
  const { reuseLoadedTrip = false } = options;
  state.currentRoutePath = window.location.pathname;
  const route = matchRoute(window.location.pathname);
  byId('nav-events').classList.toggle('is-active', ['events', 'event'].includes(route.name));
  byId('nav-trips').classList.toggle('is-active', !['events', 'event'].includes(route.name));
  if (route.name === 'events') { await showEvents(); return; }
  if (route.name === 'event') {
    if (reuseLoadedTrip && management.event?.id === route.eventId) { await showEventTab(window.location.hash.slice(1) || 'reisen'); }
    else { await showEvent(route.eventId); }
    return;
  }

  if (route.name === "list") {
    state.currentTripId = null;
    resetCalculationResult();
    await loadTripList();
    showListSection();
    return;
  }

  if (route.name === "new-trip") {
    state.currentTripId = null;
    state.currentTrip = null;
    clearTripFormWithDefaults();
    await loadEventSelector();
    snapshotTripForm();
    resetTripPermissions();
    byId("tab-termin").classList.add("d-none");
    byId("panel-termin").classList.add("d-none");
    showEditorSection("Neue Reise", "Neue Reise", NEW_TRIP_OPEN_SECTIONS);
    showTab('planung');
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

function initNavigation() {
  byId("tab-abrechnung").addEventListener("shown.bs.tab", async () => {
    writeTabHash("abrechnung");
    renderHeadTabState();
    const tripId = byId("tripFormId").value;
    if (tripId) {
      if (state.currentTrip?.capabilities.includes('settlement.view')) { await loadSettlement(tripId); }
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

  // DESIGN: Mit type="text" prueft der Browser nicht mehr, ob ueberhaupt eine
  // Zahl im Feld steht (RESPONSIVE.md, Regel 2). Ohne Rueckmeldung rechnet die
  // Vorschau still mit 0 weiter - deshalb hier eine sichtbare Markierung. blur
  // steigt nicht auf, also in der Capture-Phase.
  document.addEventListener("blur", (event) => {
    const field = event.target;
    if (!(field instanceof HTMLInputElement) || !field.hasAttribute("inputmode")) {
      return;
    }
    const invalid = field.value.trim() !== "" && !Number.isFinite(parseDecimal(field.value));
    field.classList.toggle("is-invalid", invalid);
  }, true);


}
