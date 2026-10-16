// Shared state belongs to core.js; routing belongs to navigation.js; this file adds the event/team views.
const management = { events: [], event: null, view: 'cards', newEventId: null, modal: null, submit: null, team: [] };
const tripCapabilities = ['trip.view','trip.manage','registration.view','registration.manage','answers.view','answers.correct','answers.export','analytics.view','calculation.manage','settlement.view','settlement.close','settlement.reopen','expense.view','expense.manage','term.manage','access.manage'];
const eventCapabilities = ['event.view','event.manage','form.manage','form.publish','access.manage'];
const capabilityLabels = {'trip.view':'Reise ansehen','trip.manage':'Reise verwalten','registration.view':'Anmeldungen ansehen','registration.manage':'Anmeldungen bearbeiten','answers.view':'Antworten ansehen','answers.correct':'Antworten korrigieren','answers.export':'Antworten exportieren','analytics.view':'Auswertung ansehen','calculation.manage':'Planung bearbeiten und berechnen','settlement.view':'Abrechnung ansehen','settlement.close':'Abrechnung abschließen','settlement.reopen':'Abrechnung wiederöffnen','expense.view':'Ausgaben ansehen','expense.manage':'Ausgaben bearbeiten','term.manage':'Termin verwalten','access.manage':'Zugriffe verwalten','event.view':'Veranstaltung ansehen','event.manage':'Veranstaltung verwalten','form.manage':'Formular bearbeiten','form.publish':'Formular veröffentlichen'};
function hideEventSections() {
  document.body.classList.remove('is-form-view');
  byId('event-list-section').classList.add('d-none'); byId('event-detail-section').classList.add('d-none');
}
function hasTripCapability(cap) { return state.currentTrip?.capabilities?.includes(cap) || false; }
function dirtyPlanning() {
  return state.formSnapshot !== null && (roomReservationRowIds() !== state.reservationRowsSnapshot || tripFormFields().some(field => state.formSnapshot[field.id] !== undefined && state.formSnapshot[field.id] !== fieldState(field)));
}
function allowManagementNavigation() {
  if (typeof formDirty === 'function' && formDirty()) {
    if (!window.confirm('Ungespeicherte Formularänderungen verwerfen?')) { return false; }
    formsUI.eventId = null;
  }
  if (!byId('trip-editor-section').classList.contains('d-none') && dirtyPlanning()) {
    return window.confirm('Ungespeicherte Planungsänderungen verwerfen?');
  }
  if (!byId('event-detail-section').classList.contains('d-none') && management.event && eventSettingsDirty()) {
    return window.confirm('Ungespeicherte Veranstaltungseinstellungen verwerfen?');
  }
  return true;
}
function eventSettingsDirty() {
  const e = management.event;
  return e && (byId('event-setting-name').value !== e.name || byId('event-setting-description').value !== e.description || byId('event-setting-archived').checked !== e.archived);
}
function managementMessage(error){
  try{const code=JSON.parse(error.message).error;return ({provider_network_transaction_forbidden:'Die Aktion konnte wegen eines internen Fehlers nicht abgeschlossen werden. Bitte die Seite neu laden und erneut versuchen.',provider_read_failed:'Der ausgewählte Termin konnte nicht aus easyVerein gelesen werden. Bitte API-Zugang und Leserechte prüfen und erneut versuchen.',provider_read_invalid:'Die Antwort von easyVerein zum ausgewählten Termin konnte nicht gelesen werden. Bitte erneut versuchen.',term_review_changed:'Der Terminauftrag hat sich inzwischen geändert. Bitte den Status aktualisieren und die Aktion erneut wählen.',term_resume_conflict:'Ein abweichender oder möglicher doppelter Termin wurde gefunden. Bitte den vorhandenen Termin über die Suche bewusst zuordnen.',confirmed_term_required:'Bitte zuerst den easyVerein-Termin verbinden. Bei unklarer Anlage den vorhandenen Termin über die Suche zuordnen.',term_job_in_progress:'Der Terminauftrag wird gerade verarbeitet. Bitte kurz warten und den Status aktualisieren.',event_already_linked:'Dieser Termin ist bereits einer anderen Reise zugeordnet. Bitte den passenden Termin auswählen.',trip_already_linked:'Diese Reise ist bereits mit einem anderen Termin verbunden. Bitte den aktuellen Terminstatus prüfen.'})[code]||error.message;}catch{return error.message;}
}
function managementError(error) {
  setStatusNote('management-status', `Fehler: ${managementMessage(error)}`, 'error'); byId('management-status').focus();
}
async function performManagement(operation) { try { setStatusNote('management-status','',null); await operation(); } catch (error) { managementError(error); } }
function showManagementModal(title, fields, submit, submitLabel = 'Speichern') {
  byId('management-modal').classList.remove('is-term-wizard');
  byId('wizard-footer-navigation')?.remove();
  byId('wizard-save-draft')?.remove();
  byId('management-submit').hidden=false;
  byId('management-submit').disabled=false;
  management.modal ||= new bootstrap.Modal(byId('management-modal'));
  byId('management-title').textContent = title; byId('management-fields').innerHTML = fields;
  byId('management-modal-error').textContent = ''; byId('management-submit').textContent = submitLabel; management.submit = submit;
  management.modal.show();
  byId('management-modal').addEventListener('shown.bs.modal', () => byId('management-fields').querySelector('input,select,textarea')?.focus(), {once:true});
}
function textField(id, label, value = '', type = 'text') {
  return `<label class="f" for="${id}">${label}<div class="in"><input id="${id}" type="${type}" value="${escapeHtml(value)}" required maxlength="200"></div></label>`;
}
function reasonField() { return '<label class="f" for="action-reason">Grund<div class="in"><textarea id="action-reason" required maxlength="500" rows="2"></textarea></div></label>'; }
byId('management-form').addEventListener('submit', async event => {
  event.preventDefault(); const button = event.currentTarget.querySelector('[type=submit]'); button.disabled = true;
  try { await management.submit(); management.modal.hide(); } catch (error) { byId('management-modal-error').textContent = `Fehler: ${managementMessage(error)}`; byId('management-modal-error').focus(); }
  finally { button.disabled = false; }
});
async function showEvents() {
  state.currentTripId = null; state.currentTrip = null; hideEventSections();
  byId('trip-list-section').classList.add('d-none'); byId('trip-editor-section').classList.add('d-none');
  document.body.classList.remove('is-trip-view'); byId('event-list-section').classList.remove('d-none');
  management.events = await api(`/api/events?archived=${byId('event-archived').checked}`); renderEvents();
}
function renderEvents() {
  const query = byId('event-search').value.trim().toLocaleLowerCase('de');
  const events = management.events.filter(e => `${e.name} ${e.description}`.toLocaleLowerCase('de').includes(query));
  byId('event-cards-btn').setAttribute('aria-pressed', String(management.view === 'cards'));
  byId('event-table-btn').setAttribute('aria-pressed', String(management.view === 'table'));
  if (!events.length) { byId('event-list-content').innerHTML = '<div class="empty-state"><h2>Keine Veranstaltungen</h2><p>Lege eine Veranstaltung an oder ändere Suche und Archivfilter.</p></div>'; return; }
  if (management.view === 'table') {
    byId('event-list-content').innerHTML = `<div class="event-table-wrap"><table class="tb"><thead><tr><th>Veranstaltung</th><th>Erlaubte Reisen</th><th>Nächste Reise</th><th>Status</th></tr></thead><tbody>${events.map(e => `<tr><td><a href="/events/${e.id}" data-spa>${escapeHtml(e.name)}</a></td><td>${e.tripCount}</td><td>${e.nextTripDate ? formatDate(e.nextTripDate) : 'Keine geplant'}</td><td>${e.archived ? 'Archiviert' : 'Aktiv'}</td></tr>`).join('')}</tbody></table></div>`;
  } else {
    byId('event-list-content').innerHTML = `<div class="event-grid">${events.map(e => `<a href="/events/${e.id}" data-spa class="event-card"><div class="event-card-title"><h2>${escapeHtml(e.name)}</h2><span class="chip ${e.archived ? 'c-grey' : 'c-green'}">${e.archived ? 'Archiviert' : 'Aktiv'}</span></div><p class="help">${escapeHtml(e.description)}</p><div class="event-card-stats"><div><span class="help">Erlaubte Reisen</span><b>${e.tripCount}</b></div><div><span class="help">Nächste Reise</span><b>${e.nextTripDate ? formatDate(e.nextTripDate) : 'Keine geplant'}</b></div></div></a>`).join('')}</div>`;
  }
}
async function showEvent(id) {
  state.currentTripId = null; state.currentTrip = null; hideEventSections();
  byId('trip-list-section').classList.add('d-none'); byId('trip-editor-section').classList.add('d-none');
  document.body.classList.remove('is-trip-view'); byId('event-detail-section').classList.remove('d-none');
  const e = await api(`/api/events/${id}`); management.event = e;
  byId('event-title').textContent = e.name; byId('event-breadcrumb').textContent = e.name; byId('event-description').textContent = e.description;
  byId('event-status').textContent = e.archived ? 'Archiviert' : 'Aktiv'; byId('event-status').className = `chip ${e.archived ? 'c-grey' : 'c-green'}`;
  byId('event-setting-name').value = e.name; byId('event-setting-description').value = e.description; byId('event-setting-archived').checked = e.archived;
  const manage = e.capabilities.includes('event.manage');
  for (const id of ['event-new-trip','event-assign-trip']) { byId(id).classList.toggle('d-none', !manage); byId(id).disabled = e.archived; }
  byId('event-settings-form').querySelectorAll('input,textarea,button').forEach(input => input.disabled = !manage);
  byId('event-trips').innerHTML = e.trips.length ? `<div class="event-grid">${e.trips.map(tripCard).join('')}</div>` : '<div class="empty-state"><h2>Noch keine erlaubten Reisen</h2><p>Neue Durchführungen können hier angelegt werden. Bestehende Reisen bleiben erhalten.</p></div>';
  const tab = window.location.hash.slice(1); await showEventTab(['reisen','formular','standardteam','einstellungen'].includes(tab) ? tab : 'reisen');
}
function tripCard(t) {
  return `<article class="event-card"><a href="/trips/${t.id}" data-spa><h3>${escapeHtml(t.name)}</h3></a><p>${escapeHtml(formatTripPeriod(t))}</p><span class="chip ${t.closedAt ? 'c-grey' : 'c-green'}">${t.closedAt ? 'Abgerechnet' : 'Offen'}</span><div class="event-card-stats"><div><span class="help">Nächte</span><b>${t.nights}</b></div><div><span class="help">Überschuss</span><b>${t.surplus === null ? 'Nicht freigegeben' : formatCurrency(t.surplus)}</b></div></div></article>`;
}
async function showEventTab(tab) {
  document.body.classList.toggle('is-form-view', tab === 'formular');
  for (const name of ['reisen','formular','standardteam','einstellungen']) {
    byId(`event-panel-${name}`).classList.toggle('d-none', tab !== name);
    document.querySelector(`[data-event-tab="${name}"]`).classList.toggle('is-active', tab === name);
    document.querySelector(`[data-event-tab="${name}"]`).setAttribute('aria-current', tab === name ? 'page' : 'false');
  }
  if (tab === 'formular') { await loadFormWorkspace(); }
  if (tab === 'standardteam') {
    if (management.event.capabilities.includes('access.manage') || state.currentUser?.role === 'admin') {
      await loadTeam('default'); await loadTeam('event');
    } else {
      byId('default-team-list').textContent = 'Für die Teamverwaltung fehlt die Berechtigung.'; byId('event-access-list').textContent = 'Zugriff nicht freigegeben.';
      byId('default-team-add').classList.add('d-none'); byId('event-access-add').classList.add('d-none');
    }
  }
}
byId('event-search').addEventListener('input', renderEvents);
byId('event-archived').addEventListener('change', () => performManagement(showEvents));
byId('event-cards-btn').addEventListener('click', () => { management.view = 'cards'; renderEvents(); });
byId('event-table-btn').addEventListener('click', () => { management.view = 'table'; renderEvents(); });
byId('event-create-btn').addEventListener('click', () => showManagementModal('Veranstaltung anlegen', textField('new-event-name','Name') + '<label class="f">Beschreibung<div class="in"><textarea id="new-event-description" rows="4" maxlength="10000"></textarea></div></label>', async () => {
  const e = await api('/api/events', {method:'POST', body:JSON.stringify({name:byId('new-event-name').value,description:byId('new-event-description').value})});
  await navigateTo(`/events/${e.id}`);
}));
byId('event-settings-form').addEventListener('submit', event => { event.preventDefault(); performManagement(async () => {
  const e = management.event;
  const updated = await api(`/api/events/${e.id}`, {method:'PUT',body:JSON.stringify({name:byId('event-setting-name').value,description:byId('event-setting-description').value,archived:byId('event-setting-archived').checked,revision:e.revision})});
  management.event = updated; await showEvent(e.id);
}); });
byId('event-new-trip').addEventListener('click', () => performManagement(async () => { management.newEventId = management.event.id; await openTermWizard(null, management.event); }));
async function loadEventSelector(selected = null) {
  const events = await api('/api/events');
  const creating = state.currentTripId === null;
  const selectable = events.filter(e => e.capabilities.includes('event.manage') || e.id === selected);
  byId('trip-event-select').innerHTML = `<option value="">${creating ? 'Ohne Veranstaltung' : 'Keine Veranstaltung zugeordnet'}</option>` + selectable.map(e => `<option value="${e.id}">${escapeHtml(e.name)}</option>`).join('');
  if (selected && !events.some(e => e.id === selected)) { byId('trip-event-select').innerHTML += `<option value="${selected}">Veranstaltung #${selected}</option>`; }
  byId('trip-event-select').value = selected || management.newEventId || ''; management.newEventId = null;
  byId('trip-event-select').disabled = !creating;
  byId('trip-event-create').classList.toggle('d-none', !creating);
  byId('trip-event-help').textContent = !creating
    ? 'Die Zuordnung einer bestehenden Reise wird in der Veranstaltungsverwaltung geändert.'
    : selectable.length === 0
      ? 'Keine auswählbare Veranstaltung vorhanden. Du kannst die Reise ohne Veranstaltung speichern oder hier eine anlegen.'
      : 'Du kannst die Reise ohne Veranstaltung speichern und später zuordnen.';
}
byId('trip-event-create').addEventListener('click', () => showManagementModal('Veranstaltung für diese Reise anlegen', textField('trip-new-event-name','Name') + '<label class="f">Beschreibung<div class="in"><textarea id="trip-new-event-description" rows="3" maxlength="10000"></textarea></div></label>', async () => {
  const e = await api('/api/events', {method:'POST',body:JSON.stringify({name:byId('trip-new-event-name').value,description:byId('trip-new-event-description').value})});
  await loadEventSelector(e.id); renderDirtyState();
}));
byId('event-assign-trip').addEventListener('click', () => performManagement(async () => {
  const trips = await api('/api/trips');
  const unassigned = trips.filter(t => t.eventId === null && !t.closedAt);
  if (!unassigned.length) { throw new Error('Keine unzugeordneten offenen Reisen verfügbar.'); }
  showManagementModal('Bestehende Reise zuordnen', '<label class="f">Reise<div class="in"><select id="assign-trip-id">' + unassigned.map(t => `<option value="${t.id}">${escapeHtml(t.name)}</option>`).join('') + '</select></div></label>' + reasonField(), async () => {
    await api(`/api/trips/${byId('assign-trip-id').value}/event`, {method:'PUT',body:JSON.stringify({eventId:management.event.id,reason:byId('action-reason').value})}); await showEvent(management.event.id);
  });
}));
function teamPath(type) { return type === 'trip' ? `/api/trips/${state.currentTripId}/team` : `/api/events/${management.event.id}/${type === 'default' ? 'default-team' : 'access'}`; }
async function loadTeam(type) {
  const members = await api(teamPath(type)); management.team[type] = members;
  const target = byId(type === 'trip' ? 'trip-team-list' : type === 'default' ? 'default-team-list' : 'event-access-list');
  byId(type === 'trip' ? 'trip-team-add' : type === 'default' ? 'default-team-add' : 'event-access-add').classList.remove('d-none');
  target.innerHTML = members.length ? members.map(m => `<div class="team-member"><div><b>${escapeHtml(m.email)}</b><p class="help">${m.capabilities.map(c => escapeHtml(capabilityLabels[c] || c)).join(' · ')}</p></div><button class="btn btn-o" type="button" data-team-edit="${type}" data-user-id="${m.userId}">Bearbeiten / Widerrufen</button></div>`).join('') : '<p class="help">Noch kein Team hinterlegt.</p>';
}
async function editTeam(type, userId = null) {
  const path = type === 'trip' ? `/api/trips/${state.currentTripId}/people` : `/api/events/${management.event.id}/people`;
  const people = await api(path); const member = management.team[type]?.find(m => m.userId === userId);
  const caps = type === 'event' ? eventCapabilities : tripCapabilities;
  showManagementModal(type === 'default' ? 'Standardteam bearbeiten' : type === 'event' ? 'Veranstaltungsrechte bearbeiten' : 'Reiseteam bearbeiten', '<label class="f">Person<div class="in"><select id="team-user" required>' + people.map(p => `<option value="${p.id}">${escapeHtml(p.email)}</option>`).join('') + '</select></div></label><p class="help">Alle Haken entfernen widerruft die Freigabe. Antwortrechte sind von Abrechnungsrechten getrennt.</p>' + (type === 'event' ? '' : '<div class="event-toolbar"><button type="button" class="btn btn-o" data-team-preset="orga">Orga</button><button type="button" class="btn btn-o" data-team-preset="settlement">Abrechnung lesen</button><button type="button" class="btn btn-o" data-team-preset="term">Terminpflege</button></div>') + `<div class="capability-grid">${caps.map(c => `<label><input type="checkbox" data-capability="${c}" ${member?.capabilities.includes(c) ? 'checked' : ''}> ${escapeHtml(capabilityLabels[c])}</label>`).join('')}</div>` + reasonField(), async () => {
    const capabilities = Array.from(byId('management-fields').querySelectorAll('[data-capability]:checked')).map(c => c.dataset.capability);
    await api(`${teamPath(type)}/${byId('team-user').value}`, {method:'PUT',body:JSON.stringify({capabilities,reason:byId('action-reason').value})}); await loadTeam(type);
  });
  if (userId) { byId('team-user').value = userId; byId('team-user').disabled = true; }
}
for (const [id,type] of [['default-team-add','default'],['event-access-add','event'],['trip-team-add','trip']]) { byId(id).addEventListener('click', () => performManagement(() => editTeam(type))); }
function resetTripPermissions() {
  for (const name of ['uebersicht','planung','termin','anmeldungen','auswertung','abrechnung','team']) { byId(`tab-${name}`).classList.remove('d-none'); }
  byId('trip-form').querySelectorAll('input,select,textarea,button').forEach(e => e.disabled = false);
  for (const id of ['trip-save-btn-head','trip-save-btn','trip-save-btn-sticky','trip-save-btn-bar','calculate-btn']) { if (byId(id)) byId(id).disabled = false; }
}
function renderTripManagement(trip) {
  resetTripPermissions(); const caps = trip.capabilities;
  applyRegistrationModeLayout(trip);
  if (typeof loadWorkflowStatus === "function") { performManagement(() => loadWorkflowStatus(trip)); }
  byId('trip-event-assign').disabled = true;
  byId('trip-event-assign').classList.toggle('d-none', !caps.includes('trip.manage') || !!trip.closedAt);
  byId('trip-event-assignment-note').textContent = '';
  if(tripUsesEasyVerein(trip)){performManagement(() => loadTripFormBinding(trip.id));}else{byId('trip-event-assign').disabled=false;}
  byId('trip-event-select').disabled = true;
  for (const [tab,cap] of [['planung','calculation.manage'],['anmeldungen','registration.view'],['auswertung','analytics.view'],['abrechnung','settlement.view']]) { byId(`tab-${tab}`).classList.toggle('d-none', !caps.includes(cap)); }
  applyRegistrationModeLayout(trip);
  const canAccess = caps.includes('access.manage') || state.currentUser?.role === 'admin';
  byId('trip-team-add').classList.toggle('d-none', !canAccess);
  byId('trip-team-list').textContent = canAccess ? 'Team wird beim Öffnen geladen.' : 'Für die Teamverwaltung fehlt die Berechtigung.';
  byId('trip-lifecycle-status').textContent = trip.archivedAt ? 'Archiviert' : trip.closedAt ? 'Abgerechnet' : 'Offen'; byId('trip-lifecycle-status').className = `chip ${trip.archivedAt || trip.closedAt ? 'c-grey' : 'c-green'}`;
  byId('trip-overview-details').innerHTML = `${trip.archivedAt?'<p class="status-note">Archiviert: Daten bleiben lesbar. Zum Bearbeiten zuerst aus dem Archiv holen.</p>':''}<p>${escapeHtml(trip.name)} · ${formatDate(trip.startDate)}${trip.endDate ? ' – ' + formatDate(trip.endDate) : ''}</p>${trip.closedAt ? '<p class="status-note">Abgerechnet: Finanzänderungen und Neuberechnung sind gesperrt.</p>' : ''}<p class="help">${trip.eventId ? 'Veranstaltung #' + trip.eventId : 'Alt-Reise ohne Veranstaltungszuordnung'}</p>`;
  if (trip.eventId) {
    const crumb = byId('trip-editor-breadcrumb'); crumb.textContent = trip.name;
    const first = byId('back-to-list-btn'); first.href = `/events/${trip.eventId}`; first.textContent = 'Veranstaltung';
    api(`/api/events/${trip.eventId}`).then(e => { if (state.currentTripId === trip.id) { first.textContent = e.name; } }).catch(() => {});
  } else { byId('back-to-list-btn').href = '/'; byId('back-to-list-btn').textContent = 'Alle Reisen'; }
  byId('trip-duplicate').classList.toggle('d-none', !(caps.includes('trip.manage') && caps.includes('calculation.manage')));
  byId('trip-close').classList.toggle('d-none', !!trip.archivedAt || !!trip.closedAt || !caps.includes('settlement.close'));
  byId('trip-reopen').classList.toggle('d-none', !!trip.archivedAt || !trip.closedAt || !caps.includes('settlement.reopen'));
  byId('trip-archive').classList.toggle('d-none', !caps.includes('trip.manage'));
  byId('trip-archive').textContent = trip.archivedAt ? 'Reise aus Archiv holen' : 'Reise archivieren';
  const readOnly = !!trip.archivedAt || !!trip.closedAt || !caps.includes('calculation.manage');
  byId('trip-form').querySelectorAll('input,select,button:not([data-bs-toggle])').forEach(e => e.disabled = readOnly || e.id === 'trip-event-select');
  byId('trip-registration-mode-choice').disabled=true;
  for (const id of ['trip-save-btn-head','trip-save-btn','trip-save-btn-sticky','trip-save-btn-bar','calculate-btn']) { if (byId(id)) byId(id).disabled = readOnly; }
  byId('registrations-section').classList.toggle('d-none', !caps.includes('registration.view'));
  byId('actual-expense-form')?.querySelectorAll('input,button').forEach(e => e.disabled = !!trip.archivedAt || !!trip.closedAt || !caps.includes('expense.manage'));
}
byId('trip-event-assign').addEventListener('click', () => performManagement(async () => {
  const trip = state.currentTrip;
  if (!trip || !hasTripCapability('trip.manage') || trip.closedAt) { throw new Error('Die Veranstaltungszuordnung ist nicht freigegeben.'); }
  if (dirtyPlanning()) { throw new Error('Bitte zuerst die Planungsänderungen speichern, bevor du die Veranstaltung änderst.'); }
  const events = (await api('/api/events')).filter(e => e.capabilities.includes('event.manage'));
  if (trip.eventId && !events.some(e => e.id === trip.eventId)) { throw new Error('Zum Ändern fehlt die Verwaltungsberechtigung für die bisherige Veranstaltung.'); }
  if (!events.length) { throw new Error('Keine verwaltbare Veranstaltung vorhanden. Lege unter Veranstaltungen zuerst eine an.'); }
  showManagementModal('Veranstaltung zuordnen / ändern', '<p>Die Reise und ihre Kalkulation bleiben erhalten. Das Reiseteam wird nicht durch das Standardteam ersetzt.</p><label class="f">Veranstaltung<div class="in"><select id="trip-assignment-event"><option value="">Ohne Veranstaltung</option>' + events.map(e => `<option value="${e.id}">${escapeHtml(e.name)}</option>`).join('') + '</select></div></label>' + reasonField(), async () => {
    const eventId = byId('trip-assignment-event').value === '' ? null : Number(byId('trip-assignment-event').value);
    if (eventId === trip.eventId) { throw new Error('Bitte eine andere Zuordnung wählen.'); }
    await api(`/api/trips/${trip.id}/event`, {method:'PUT',body:JSON.stringify({eventId,reason:byId('action-reason').value})});
    await openTrip(trip.id);
  }, 'Zuordnung speichern');
  byId('trip-assignment-event').value = trip.eventId || '';
}));
for (const [id,action,title] of [['trip-close','close','Abrechnung abschließen'],['trip-reopen','reopen','Abrechnung begründet wiederöffnen']]) {
  byId(id).addEventListener('click', () => showManagementModal(title, '<p>Der Schritt wird mit Akteur, Zeitpunkt und Grund protokolliert.</p>' + reasonField(), async () => {
    await api(`/api/trips/${state.currentTripId}/${action}`, {method:'POST',body:JSON.stringify({reason:byId('action-reason').value})}); await openTrip(state.currentTripId);
  }));
}
byId('trip-duplicate').addEventListener('click', () => showManagementModal('Reise duplizieren', '<p class="help">Kopiert Planung und aktuelles Standardteam. Anmeldungen und Abschluss werden nicht übernommen.</p>' + textField('duplicate-name','Name',state.currentTrip.name + ' – Kopie') + textField('duplicate-start','Beginn','','date') + '<label class="f">Ende (optional)<div class="in"><input id="duplicate-end" type="date"></div></label>', async () => {
  const t = await api(`/api/trips/${state.currentTripId}/duplicate`, {method:'POST',body:JSON.stringify({name:byId('duplicate-name').value,startDate:byId('duplicate-start').value,endDate:byId('duplicate-end').value || null})}); await navigateTo(`/trips/${t.id}`);
}));
byId('legacy-preview-btn').addEventListener('click', () => performManagement(async () => {
  state.usersModal.hide(); const rows = await api('/api/admin/legacy-access');
  showManagementModal('Alt-Reiserechte übernehmen', '<p>Die Auswahl zeigt Benutzer und Reisen zum Migrationszeitpunkt. Es wurden keine Rechte automatisch vergeben. Nur markierte Zuordnungen werden übernommen. Jede Auswahl vergibt vollständige Orga-Reiserechte; anschließend können diese einzeln eingeschränkt werden.</p>' + rows.map((r,i) => `<label class="team-member"><input type="checkbox" data-legacy-pair="${i}"> ${escapeHtml(r.tripName)} (#${r.tripId}) · ${escapeHtml(r.email)}</label>`).join('') + reasonField(), async () => {
    const selected = Array.from(byId('management-fields').querySelectorAll('[data-legacy-pair]:checked')).map(e => rows[Number(e.dataset.legacyPair)]);
    if (!selected.length) { throw new Error('Mindestens eine Zuordnung wählen.'); }
    await api('/api/admin/legacy-access', {method:'POST',body:JSON.stringify({pairs:selected.map(r => ({tripId:r.tripId,userId:r.userId})),reason:byId('action-reason').value})}); await loadTripList();
  });
}));
document.addEventListener('click', event => {
  const spa = event.target.closest('[data-spa], #nav-events, #nav-trips');
  if (spa) { event.preventDefault(); performManagement(() => navigateTo(new URL(spa.href).pathname)); return; }
  const eventTab = event.target.closest('[data-event-tab]');
  if (eventTab) { performManagement(async () => { await showEventTab(eventTab.dataset.eventTab); writeTabHash(eventTab.dataset.eventTab); }); return; }
  const tripTab = event.target.closest('[data-trip-tab]'); if (tripTab) { showTab(tripTab.dataset.tripTab); return; }
  const team = event.target.closest('[data-team-edit]'); if (team) { performManagement(() => editTeam(team.dataset.teamEdit,Number(team.dataset.userId))); return; }
  const preset = event.target.closest('[data-team-preset]'); if (preset) {
    const caps = preset.dataset.teamPreset === 'orga' ? tripCapabilities : preset.dataset.teamPreset === 'settlement' ? ['trip.view','settlement.view','expense.view'] : ['trip.view','term.manage'];
    byId('management-fields').querySelectorAll('[data-capability]').forEach(e => e.checked = caps.includes(e.dataset.capability));
  }
});
for (const name of ['uebersicht','planung','termin','anmeldungen','auswertung','team']) {
  byId(`tab-${name}`).addEventListener('shown.bs.tab', () => { writeTabHash(name); renderHeadTabState(); if (['planung','termin'].includes(name) && typeof loadWorkflowStatus === 'function' && state.currentTrip) { performManagement(() => loadWorkflowStatus(state.currentTrip)); } if (name === 'team' && (hasTripCapability('access.manage') || state.currentUser?.role === 'admin')) { performManagement(() => loadTeam('trip')); } });
}
window.addEventListener('beforeunload', event => { if ((typeof formDirty === 'function' && formDirty()) || (!byId('trip-editor-section').classList.contains('d-none') && dirtyPlanning()) || (!byId('event-detail-section').classList.contains('d-none') && eventSettingsDirty())) { event.preventDefault(); event.returnValue = ''; } });

function applyTripWriteControls() {
  const trip = state.currentTrip; if (!trip) { return; }
  const registrationReadOnly = !!trip.archivedAt || !!trip.closedAt || !hasTripCapability('registration.manage');
  for (const id of ['csv-upload-form','manual-registration-form']) { byId(id).querySelectorAll('input,select,textarea,button').forEach(e => e.disabled = registrationReadOnly || (id==='csv-upload-form'&&tripUsesEasyVerein(trip)) || (e.id==='booking-contact'&&!tripUsesEasyVerein(trip))); }
  for (const id of ['recalculate-billings-btn','delete-registrations-btn']) { byId(id).disabled = registrationReadOnly || (id === 'delete-registrations-btn' && typeof bookingUI !== 'undefined' && bookingUI.items.some(b => b.managed)); }
  document.querySelectorAll('[data-delete-registration]').forEach(e => e.disabled = registrationReadOnly);
  const expenseReadOnly = !!trip.closedAt || !hasTripCapability('expense.manage');
  document.querySelectorAll('[data-edit-expense],[data-delete-expense]').forEach(e => e.disabled = expenseReadOnly);
  byId('actual-expense-form').querySelectorAll('input,button').forEach(e => e.disabled = expenseReadOnly);
  byId('refund-section').classList.toggle('d-none', !hasTripCapability('registration.view'));
  if (!hasTripCapability('registration.view') && state.currentSettlement) {
    const s = state.currentSettlement;
    byId('panel-settlement-per-registration').textContent = s.registrationCount ? formatCurrency(s.totalRevenue / s.registrationCount) : '–';
  }
  renderManagementBar();
}
function renderManagementBar() {
  if (!state.currentTripId) { return; }
  const tab = activeTabName();
  byId('booking-save-btn-bar').classList.toggle('d-none', !tripUsesEasyVerein(state.currentTrip) || tab !== 'anmeldungen' || !hasTripCapability('registration.manage') || !hasTripCapability('answers.correct'));
  byId('booking-save-btn-bar').disabled = !!state.currentTrip?.closedAt;
  byId('trip-save-btn-bar').classList.toggle('d-none', tab !== 'planung');
  byId('trip-bar-team-btn').classList.toggle('d-none', tab !== 'team' || !(hasTripCapability('access.manage') || state.currentUser?.role === 'admin'));
}
byId('trip-bar-team-btn').addEventListener('click', () => performManagement(() => editTeam('trip')));

window.addEventListener('hashchange', () => {
  if (!byId('event-detail-section').classList.contains('d-none') && management.event) {
    const tab = window.location.hash.slice(1);
    performManagement(() => showEventTab(['reisen','formular','standardteam','einstellungen'].includes(tab) ? tab : 'reisen'));
  }
});

byId('trip-archive').addEventListener('click',()=>performManagement(async()=>{
  const trip=state.currentTrip;if(dirtyPlanning())throw new Error('Bitte die Planungsänderungen zuerst speichern.');
  const archived=!trip.archivedAt;
  showManagementModal(archived?'Reise archivieren':'Reise aus Archiv holen','<p>'+ (archived?'Die Reise verschwindet aus der aktiven Liste. Buchungen, Antworten, Revisionen und Finanzdaten bleiben erhalten. Mitgliederänderungen und externe Aufträge sind im Archiv gesperrt.':'Die Reise erscheint wieder in der aktiven Liste. Ein bestehender Abrechnungsabschluss bleibt bestehen.')+'</p>'+reasonField(),async()=>{
    await api(`/api/trips/${trip.id}/archive`,{method:'PUT',body:JSON.stringify({archived,archivedAt:trip.archivedAt??null,reason:byId('action-reason').value})});await openTrip(trip.id);await loadTripList();
  },archived?'Archivieren':'Aus Archiv holen');
}));

function tripUsesEasyVerein(trip=state.currentTrip){return trip?.registrationMode==='easyverein';}
function applyRegistrationModeLayout(trip){
  const easy=tripUsesEasyVerein(trip);
  byId('trip-registration-mode-label').textContent=easy?'easyVerein · Zusatzformular':'Clubdesk / CSV · bisheriger Ablauf';
  byId('trip-registration-mode-change').disabled=!!trip.archivedAt||!!trip.closedAt||!trip.capabilities.includes('trip.manage');
  byId('trip-registration-mode-summary').classList.remove('d-none');
  byId('trip-registration-mode-create').classList.add('d-none');
  byId('trip-registration-mode-choice').value=trip.registrationMode||'clubdesk_csv';byId('trip-registration-mode-choice').disabled=true;
  for(const id of ['trip-term-section','planning-transfer-section','trip-form-binding']){byId(id).classList.toggle('d-none',!easy);}
  byId('trip-form-binding').nextElementSibling?.classList.toggle('d-none',!easy);
  byId('tab-team').textContent='Team';
  byId('tab-termin').classList.toggle('d-none',!easy||!trip.capabilities.includes('term.manage'));
  byId('panel-termin').classList.toggle('d-none',!easy||!trip.capabilities.includes('term.manage'));
  byId('tab-anmeldungen').classList.toggle('d-none',!trip.capabilities.includes('registration.view'));
  byId('tab-auswertung').classList.toggle('d-none',!easy||!trip.capabilities.includes('analytics.view'));
  byId('csv-upload-form').classList.toggle('d-none',easy);
  byId('reg-filter-easyverein').classList.toggle('d-none',!easy);
  byId('booking-contact-field').classList.toggle('d-none',!easy);byId('booking-contact').required=easy;
  byId('booking-intake-note').classList.toggle('d-none',!easy);byId('booking-extra-fields').classList.toggle('d-none',!easy);
}
byId('trip-registration-mode-change').addEventListener('click',()=>performManagement(async()=>{
  if(dirtyPlanning())throw new Error('Bitte die Planungsänderungen zuerst speichern.');
  const trip=state.currentTrip,current=await api(`/api/trips/${trip.id}/registration-mode`);
  showManagementModal('Anmeldeverfahren wählen',`<p>Clubdesk / CSV nutzt den bisherigen Import und die bisherige Abrechnung. easyVerein ergänzt versionierte Formulare, Mitgliederbuchungen und den Teilnahmeabgleich.</p><label class="f">Verfahren<div class="in"><select id="registration-mode-target"><option value="clubdesk_csv">Clubdesk / CSV · bisheriger Ablauf</option><option value="easyverein">easyVerein · mit Zusatzformular</option></select></div></label><p class="help">Vorhandene CSV-Anmeldungen bleiben beim Wechsel erhalten. Ein Rückwechsel ist möglich, solange keine neuen Buchungen, Gastfreigaben oder laufenden Übertragungen vorliegen.</p>${reasonField()}`,async()=>{
    try{await api(`/api/trips/${trip.id}/registration-mode`,{method:'PUT',body:JSON.stringify({registrationMode:byId('registration-mode-target').value,revision:current.revision,confirmed:true,reason:byId('action-reason').value})});}
    catch(error){let detail;try{detail=JSON.parse(error.message);}catch{};const text={registration_mode_managed_bookings_exist:'Für diese Reise gibt es bereits Buchungen im neuen Verfahren. Ein Rückwechsel würde sie vom Anmeldeweg trennen.',registration_mode_operation_in_progress:'Eine Übertragung läuft oder ist noch ungeklärt. Bitte zuerst den Auftrag klären.',registration_mode_guest_approvals_exist:'Bitte zuerst die offenen Gastfreigaben widerrufen.',revision_conflict:'Das Verfahren wurde inzwischen geändert. Bitte den Dialog neu öffnen.'};throw new Error(text[detail?.error]||error.message);}
    await openTrip(trip.id);
  },'Verfahren übernehmen');byId('registration-mode-target').value=current.registrationMode;
}));
