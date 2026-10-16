// Settlement, expense editing and refund distribution.
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
  byId("actualExpenseAmount").value = formatDecimal("");
  byId("actual-expense-save-label").textContent = "Hinzufügen";
  byId("actual-expense-cancel-btn").classList.add("d-none");
}

const iconEdit = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 20h9M16.5 3.5a2 2 0 013 3L7 19l-4 1 1-4z"/></svg>';
const iconTrash = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 6h18M8 6V4h8v2M19 6l-1 14H6L5 6"/></svg>';

function renderSettlement(settlement) {
  state.currentSettlement = settlement;

  const registrations = state.currentRegistrations || [];
  const participants = settlement.participantCount;

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
      <td class="tb-head">${escapeHtml(item.label)}</td>
      <td class="r" data-col="Betrag">${formatCurrency(item.amount)}</td>
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
      <td class="tb-head"><b>${escapeHtml(expense.label)}</b></td>
      <td class="r" data-col="Betrag">${formatCurrency(expense.amount)}</td>
      <td class="r tb-act">
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

  const retentionPercent = parseDecimal(byId("retentionPercent").value) || 0;
  const retention = round2(Number(settlement.totalRevenue) * (retentionPercent / 100));
  const distributable = round2(Number(settlement.surplus) - retention);

  const registrations = state.currentRegistrations || [];
  const participants = settlement.participantCount;
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
  const retentionPercent = parseDecimal(byId("retentionPercent").value) || 0;

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
      <td class="tb-head"><b>${escapeHtml(primaryName)}</b><span class="help reg-age">${escapeHtml(reg.roomCategory || 'Ohne Unterkunft')}</span></td>
      <td class="r refund-paid" data-col="Gezahlt">${formatCurrency(reg.billingTotal)}</td>
      <td class="r help refund-share" data-col="Anteil">${formatPercentTwo(sharePercent)}</td>
      <td class="r ${refundAmount >= 0 ? "fuf-pos" : "fuf-neg"}" data-col="Erstattung">${signedCurrency(refundAmount)}</td>
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
  applyTripWriteControls();
  } catch (error) {
    resetSettlement();
  }
}

// DESIGN: "benedikt.schaller@x.de" -> "BS", "admin@x.de" -> "AD"

function initSettlement() {
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
      amount: parseDecimal(byId("actualExpenseAmount").value),
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
      byId("actualExpenseAmount").value = formatDecimal(editButton.getAttribute("data-amount") || "");
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

  byId("retentionPercent").addEventListener("input", () => {
    renderRefundDistribution();
    renderSettlementPanel();
  });


}
