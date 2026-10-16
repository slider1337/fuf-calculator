// Account, user management and calculator defaults.
function fillSettingsForm(settings) {
  const form = byId("settings-form");
  form.defaultMarkupPercent.value = formatDecimal(settings.defaultMarkupPercent);
  form.defaultClubFeePercent.value = formatDecimal(settings.defaultClubFeePercent);
  form.defaultDistributionMethod.value = settings.defaultDistributionMethod;
  form.defaultSpaTaxPerPerson.value = formatDecimal(settings.defaultSpaTaxPerPerson);
  form.defaultSpaTaxAgeThreshold.value = formatDecimal(settings.defaultSpaTaxAgeThreshold);
  form.defaultAdultAgeThreshold.value = formatDecimal(settings.defaultAdultAgeThreshold);
}

// DESIGN: Reservierte Zimmer - eine freie Liste aus Typ und Anzahl. Die IDs
// kommen aus einem laufenden Zaehler, damit der Stepper-Listener
// (data-step-target) und numberFromField() die Felder ohne Sonderweg finden.

async function loadSettings() {
  state.settings = await api("/api/settings");
  fillSettingsForm(state.settings);
}

// DESIGN: "2027-02-10" + "2027-02-14" -> "10.–14.02.2027". Gemeinsame Bestandteile
// werden zusammengezogen, wie in Mockup 01.

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
    for (const id of ['open-users-btn', 'open-settings-btn']) { byId(id).classList.toggle('d-none', me?.role !== 'admin'); }
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
      <td class="tb-head">
        <div class="user-cell">
          <span class="user-avatar" aria-hidden="true">${escapeHtml(initialsFromEmail(user.email))}</span>
          <b>${escapeHtml(user.email)}</b>
        </div>
      </td>
      <td data-col="Rolle">${role}</td>
      <td data-col="Status">${status}</td>
      <td class="r tb-act">${action}</td>
    </tr>`;
  }).join("");
}

// DESIGN: Eine Meldungsbox fuer Benutzer- und Einladungs-Modal. tone ist
// "ok", "error" oder null fuer neutral; ein leerer Text blendet sie aus.

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

function initAdministration() {
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
      defaultMarkupPercent: parseDecimal(form.defaultMarkupPercent.value),
      defaultClubFeePercent: parseDecimal(form.defaultClubFeePercent.value),
      defaultDistributionMethod: form.defaultDistributionMethod.value,
      defaultSpaTaxPerPerson: parseDecimal(form.defaultSpaTaxPerPerson.value),
      defaultSpaTaxAgeThreshold: parseDecimal(form.defaultSpaTaxAgeThreshold.value),
      defaultAdultAgeThreshold: parseDecimal(form.defaultAdultAgeThreshold.value),
    };

    try {
      state.settings = await api("/api/settings", { method: "PUT", body: JSON.stringify(payload) });
      fillSettingsForm(state.settings);
      state.settingsModal.hide();
    } catch (error) {
      alert("Fehler beim Speichern der Settings: " + error.message);
    }
  });


}
