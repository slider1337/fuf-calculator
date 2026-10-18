const activeRegistrationPeople = reg => reg.participants.filter(p=>p.status!=="cancelled");
const activeRegistrations = regs => regs.filter(reg=>activeRegistrationPeople(reg).length>0);
// Registration rendering, CSV import and manual intake actions.
function resetRegistrations() {
  state.currentRegistrations = [];
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
  renderRoomTypeSuggestions();
  renderSwitchTargets();
  renderRoomSummary();
  renderJumpState();
}

function resetRegistrationFilter() {
  state.registrationFilter = { source: "all", query: "" };
  byId("reg-search").value = "";
  document.querySelectorAll(".chip-btn[data-reg-filter]").forEach((chip) => {
    chip.classList.toggle("is-active", chip.getAttribute("data-reg-filter") === "all");
  });
}


function billingGroupsFor(reg) {
  const groups = reg.participants.map(() => ({ main: null, correction: null, total: null }));
  let index = -1;
  const activeIndices=reg.participants.map((p,i)=>p.status!=='cancelled'?i:null).filter(i=>i!==null);

  (reg.billingItems || []).forEach((item) => {
    if (categoryShortLabels[item.categoryType] !== undefined) {
      index += 1;
      if (groups[activeIndices[index]]) {
        groups[activeIndices[index]].main = item;
        groups[activeIndices[index]].total = item.price;
      }
      return;
    }

    if (groups[activeIndices[index]]) {
      groups[activeIndices[index]].correction = item;
      groups[activeIndices[index]].total = round2((groups[activeIndices[index]].total ?? 0) + item.price);
    }
  });

  return groups;
}

function registrationRows(reg, startDate) {
  const groups = billingGroupsFor(reg);

  return reg.participants.map((participant, i) => {
    const group = groups[i];
    const cancelled=participant.status==='cancelled';
    const age = ageAtArrival(participant.birthDate, startDate);
    const ageNote = age === null ? "" : `<span class="help reg-age">${age} J. bei Anreise</span>`;

    const pricePerson=reg.bookingPeople?.find(p=>p.id===participant.id);
    const selectedGroup=bookingUI.priceGroups.find(g=>g.id===pricePerson?.priceGroupId);
    let category = selectedGroup?`<span class="chip c-green">${escapeHtml(selectedGroup.label)}</span>`:'<span class="help">–</span>';
    if (group && group.main) {
      const tone = group.main.categoryType === "CHILD" ? "c-orange" : "c-green";
      category = `<span class="chip ${tone}">${categoryShortLabels[group.main.categoryType]}</span>`;
      if (group.correction) {
        const sign = group.correction.categoryType === "SPA_TAX" ? "+" : "−";
        category += ` <span class="chip c-grey">${sign} Kurabgabe</span>`;
      }
    }

    const price = !cancelled && group && group.total !== null ? formatCurrency(group.total) : !cancelled&&pricePerson?.expectedPrice!==null&&pricePerson?.expectedPrice!==undefined?formatCurrency(Number(pricePerson.expectedPrice)):"–";

    return `
      <tr>
        <td class="tb-head">${escapeHtml(participant.name)}${cancelled?' <span class="chip c-grey">Storniert</span>':''}</td>
        <td class="reg-date" data-col="Geburtstag">${formatDate(participant.birthDate)}${ageNote}</td>
        <td data-col="Kategorie">${category}</td>
        <td class="r" data-col="Preis"><b>${price}</b></td>
      </tr>`;
  }).join("");
}

function registrationItem(reg, index, startDate, adultAgeThreshold) {
  const collapseId = `reg-collapse-${index}`;
  const cancelled=activeRegistrationPeople(reg).length===0;
  const primaryName = reg.participants.length > 0 ? reg.participants[0].name : "Unbekannt";
  const sourceChip = reg.source === "website" ? '<span class="chip c-green">Website</span>' : reg.source === "easyverein" ? '<span class="chip c-green">easyVerein</span>' : reg.source === "manual"
    ? '<span class="chip c-amber">Manuell</span>'
    : '<span class="chip c-grey">CSV</span>';
  const amount = reg.billingTotal !== null
    ? `<b class="reg-amount">${formatCurrency(reg.billingTotal)}</b>`
    : reg.bookingTotal!==null&&reg.bookingTotal!==undefined?`<b class="reg-amount">${formatCurrency(reg.bookingTotal)}</b>`:'<b class="reg-amount reg-amount-none">keine Preisangabe</b>';
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
  item.setAttribute("data-reg-source", reg.source);
  item.dataset.cancelled=String(cancelled);
  item.setAttribute("data-reg-name", [primaryName, ...reg.participants.map((p) => p.name)].join(" ").toLowerCase());
  item.innerHTML = `
    <div class="reg-row">
      <button class="reg-row-main collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#${collapseId}" aria-expanded="false" aria-controls="${collapseId}">
        <span class="reg-name">${escapeHtml(primaryName)}</span>
        <span class="chip c-green">
          <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 21h18M5 21V4a1 1 0 011-1h9a1 1 0 011 1v17M14 12h.01"/></svg>
          ${escapeHtml(reg.rooms?.length?reg.rooms.map((r,i)=>`Zimmer ${i+1}: ${r.category}`).join(' · '):(reg.roomCategory || 'Ohne Unterkunft'))}
        </span>
        <span class="chip c-grey">${activeRegistrationPeople(reg).length} Pers.</span>
        ${sourceChip}
        ${cancelled?'<span class="chip c-grey">Storniert</span>':''}
        ${amount}
      </button>
      <div class="reg-actions">
        <button type="button" class="ib" data-edit-registration="${reg.id}" title="Anmeldung bearbeiten" aria-label="Anmeldung bearbeiten">
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
        <table class="tb tb-regs">
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
  renderRoomTypeSuggestions();

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
    totalParticipants += reg.participants.filter(p=>p.status!=="cancelled").length;
    if (reg.billingTotal !== null) {
      totalBilling += reg.billingTotal;
    }
    if (reg.source === "manual" && activeRegistrationPeople(reg).length) {
      manualCount += 1;
    }
  });

  const active=activeRegistrations(registrations);
  const csvCount = active.filter(r=>r.source==="csv").length;
  const easyCount = active.filter(r=>r.source==="easyverein").length;
  const planned = numberFromField("adultDoubleCount")
    + numberFromField("adultMultiCount")
    + numberFromField("childCount");
  const plannedRevenue = previewCalculation().totalCalculatedRevenue;

  byId("reg-count").textContent = String(active.length);
  byId("reg-count-note").textContent = [
    manualCount > 0 ? `${manualCount} manuell` : null,
    csvCount > 0 ? `${csvCount} aus CSV` : null,
    easyCount > 0 ? `${easyCount} aus easyVerein` : null,
    active.some(r=>r.source==="website") ? `${active.filter(r=>r.source==="website").length} über Website` : null,
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
    `${active.length} ${active.length === 1 ? "Anmeldung" : "Anmeldungen"}`,
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
  renderRoomSummary();
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
    const cancelled=item.dataset.cancelled==='true';
    const matchesSource = source==='cancelled'?cancelled:!cancelled&&(source === "all" || item.getAttribute("data-reg-source") === source);
    const matchesQuery = needle === "" || (item.getAttribute("data-reg-name") || "").includes(needle);
    const show = matchesSource && matchesQuery;
    item.classList.toggle("d-none", !show);
    if (show) {
      visible += 1;
    }
  });

  const allRegistrations=state.currentRegistrations||[];
  const registrations = activeRegistrations(allRegistrations);
  byId("reg-filter-cancelled").textContent=`Storniert ${allRegistrations.length-registrations.length}`;
  const manualCount = registrations.filter((reg) => reg.source === "manual").length;
  byId("reg-filter-website").textContent = `Website ${registrations.filter(r=>r.source==="website").length}`;
  byId("reg-filter-all").textContent = `Alle ${registrations.length}`;
  byId("reg-filter-csv").textContent = `CSV ${registrations.filter(r=>r.source==="csv").length}`;
  byId("reg-filter-manual").textContent = `Manuell ${manualCount}`;
  byId("reg-filter-easyverein").textContent = `easyVerein ${registrations.filter(r=>r.source==="easyverein").length}`;

  byId("registrations-no-match").classList.toggle("d-none", visible > 0 || allRegistrations.length === 0);
}

async function loadRegistrations(tripId) {
  try {
    const registrations = await api(`/api/trips/${tripId}/registrations`);
    renderRegistrations(registrations);
    if (typeof loadBookingWorkspace === "function") { await loadBookingWorkspace(Number(tripId)); }
  applyTripWriteControls();
  } catch (error) {
    byId("booking-intake-error").textContent="Anmeldungen konnten nicht geladen werden: "+error.message;
  }
}

function initRegistrations() {
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
        headers: { "X-CSRF-Token": document.querySelector('meta[name="csrf-token"]').content },
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
    applyTripWriteControls();
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
    if (rows.length >= 100) return;
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
    if (container.querySelectorAll(".manual-participant-row").length >= 100) {
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

  byId("manual-registration-form").addEventListener("submit", event => bookingCreate(event));

  // --- Single Registration Deletion ---

  byId("registrations-accordion").addEventListener("click", async (event) => {
    const target = event.target;
    if (!(target instanceof Element)) return;

    // DESIGN: Der Loeschen-Knopf ist jetzt ein Icon-Button - geklickt wird oft das
    // SVG darin, deshalb ueber closest() statt direkt am Ziel.
    const editButton=target.closest("[data-edit-registration]");
    if(editButton){await performManagement(()=>openBookingDetail(Number(editButton.dataset.editRegistration)));return;}
    const button = target.closest("[data-delete-registration]");
    if (!button) return;

    const deleteRegId = button.getAttribute("data-delete-registration");

    const tripId = byId("tripFormId").value;
    if (!tripId) return;

    if (typeof bookingUI !== "undefined" && bookingUI.items.some(b => b.id === Number(deleteRegId) && b.managed)) { await openBookingDetail(Number(deleteRegId)); return; }
    if (!confirm("Diese Anmeldung wirklich löschen?")) return;

    try {
      const registrations = await api(`/api/trips/${tripId}/registrations/${deleteRegId}`, { method: "DELETE" });
      renderRegistrations(registrations);
    applyTripWriteControls();
    } catch (error) {
      alert("Fehler beim Löschen: " + error.message);
    }
  });

  // --- Actual Expense Event Handlers ---


}
