// Planning transfer and a three-step term setup with one explicit final action.
async function workflowAction(action){try{await action();}catch(error){byId('management-modal-error').textContent=managementMessage(error);byId('management-modal-error').focus();}}
const workflowUI = {tripId:null,term:null,preview:null};
const termBlockedText = 'Externe Schreibaktionen sind in dieser Umgebung nicht freigegeben. Entwürfe lassen sich lokal speichern.';
function termProblemText(code){return ({term_deleted:'Der Termin wurde in easyVerein gelöscht. Bestätige die Löschung, um einen neuen Termin anzulegen.',term_still_exists:'Der Termin ist in easyVerein noch vorhanden. Die Verknüpfung wurde nicht gelöst.',provider_event_not_found:'Der Termin ist in easyVerein nicht mehr vorhanden. Bitte den Terminstatus aktualisieren.',term_registration_overlap:'Der Anmeldeschluss muss vor dem Terminbeginn liegen. Bei ganztägigen Terminen spätestens am Vortag (23:59 Uhr).',term_resume_conflict:'Es gibt einen abweichenden oder möglichen doppelten Termin. Bitte den passenden vorhandenen Termin über die Suche zuordnen.',term_resume_claim_lost:'Der Terminauftrag wird bereits von einem anderen Prozess bearbeitet. Bitte den Status aktualisieren.',term_resume_unsupported:'Die Fortsetzung der Terminanlage ist für diese Anbindung noch nicht verfügbar.',provider_event_invalid:'Die Rückmeldung zum Termin konnte nicht bestätigt werden. Bitte die Terminanlage erneut prüfen und fortsetzen.',term_create_unauthorized:'easyVerein hat den API-Token beim Anlegen abgelehnt. Bitte den API-Zugang prüfen.',term_create_forbidden:'Dem easyVerein-API-Token fehlt die Berechtigung zum Anlegen von Terminen.',term_create_invalid:'easyVerein hat die Terminanlage abgelehnt. Es wurde kein Termin angelegt.',term_preflight_failed:'Die Vorprüfung bei easyVerein ist fehlgeschlagen. Es wurde kein neuer Termin angelegt. Bitte Kalender und API-Zugang prüfen.',term_create_rejected:'easyVerein hat die Terminanlage abgelehnt. Bitte Schreibrechte des API-Tokens und Termindaten prüfen.',provider_read_failed:'Der Terminabgleich konnte easyVerein nicht lesen. Bitte API-Zugang und Berechtigungen prüfen.',provider_read_invalid:'easyVerein hat beim Terminabgleich eine nicht lesbare Antwort geliefert.',confirmed_term_required:'Der Termin ist bisher nur als Entwurf gespeichert. Lege ihn zuerst in easyVerein an oder verknüpfe einen vorhandenen Termin.',easyverein_credentials_missing:'Der easyVerein-API-Zugang fehlt. Bitte die Administration kontaktieren.',easyverein_production_writes_disabled:'Das Anlegen und Ändern von easyVerein-Terminen ist noch nicht freigegeben.',provider_preflight_failed:'Der Termin konnte gerade nicht aus easyVerein gelesen werden. Bitte erneut versuchen.',integration_not_configured:'Die Website-Anbindung für Mitgliederanmeldungen ist noch nicht eingerichtet.',member_release_disabled:'Die Mitgliederanmeldung ist noch gesperrt.',trip_closed_or_booking_deadline_passed:'Die Reise ist abgeschlossen, archiviert oder der Anmeldeschluss ist erreicht.'})[code]||'Die easyVerein-Anbindung ist derzeit nicht verfügbar.';}
function termValidationText(errors){
  const fields={name:'Titel',start:'Beginn',end:'Ende',all_day:'Ganztägiger Termin',calendar:'Kalender',location_name:'Ort',location_object:'Standort',description:'Beschreibung',prologue:'Beschreibung',min_participators:'Mindestteilnehmerzahl',max_participators:'Teilnehmerlimit',start_participation:'Anmeldebeginn',end_participation:'Anmeldeschluss',access:'Anmeldestatus',note:'Interne Zuordnung',creator:'Terminersteller',show_memberarea:'Mitgliedersichtbarkeit',is_public:'Öffentliche Sichtbarkeit',is_reservation:'Reservierung',reservation_parent_event:'Reservierungstermin',allow_multiple_price_groups_per_participation:'Preisgruppenauswahl',confirmation_to_addresses:'Benachrichtigungsadressen',send_mail_check:'Benachrichtigungen',event:'Termindaten'};
  const reasons={overlap:'muss vor dem Terminbeginn liegen',required:'Pflichtangabe fehlt',blank:'darf nicht leer sein',null:'darf nicht ohne Wert übertragen werden',reference:'Zuordnung wurde nicht gefunden',permission:'Berechtigung fehlt',range:'zulässiger Wertebereich überschritten',invalid:'Wert wurde abgelehnt'};
  return (Array.isArray(errors)?errors:[]).filter(e=>Object.hasOwn(fields,e.field)&&Object.hasOwn(reasons,e.reason)).map(e=>`${fields[e.field]}: ${reasons[e.reason]}.`).join(' ');
}
function termProgressText(data){
  if(data.deleted)return termProblemText('term_deleted');
  if(data.link)return data.registrationEnabled?'Termin verbunden · Mitgliederanmeldung freigegeben.':'Termin verbunden · '+termProblemText(data.registrationReason);
  if(data.job)return ({resume_pending:'Die Terminanlage wird geprüft und fortgesetzt. Diese Anzeige aktualisiert sich automatisch.',pending:'Der Termin wird in easyVerein angelegt. Diese Anzeige aktualisiert sich automatisch.',running:'Der Termin wird in easyVerein angelegt. Diese Anzeige aktualisiert sich automatisch.',reconciling:'Die Rückmeldung von easyVerein wird geprüft. Bitte keinen zweiten Termin anlegen.',unknown:'Der bisherige Terminauftrag ist nicht abgeschlossen. Über „Termin prüfen und anlegen“ kannst du ihn fortsetzen.',blocked:termProblemText(data.job.errorCode)+(termValidationText(data.job.validationErrors)?' '+termValidationText(data.job.validationErrors):''),failed:'Die Terminanlage ist fehlgeschlagen. Bitte den bestehenden Auftrag prüfen.'})[data.job.status]||'Der Terminauftrag wird geprüft.';
  return data.draft?'Entwurf gespeichert. Nächster Schritt: Termin in easyVerein anlegen oder vorhandenen Termin verknüpfen.':'Verbinde diese Reise mit einem easyVerein-Termin.';
}
function termJobError(code){return ({external_outcome_unknown:'Die Rückmeldung der Terminanlage ist unklar. „Termin prüfen und anlegen“ prüft vorhandene Ergebnisse und setzt den Auftrag fort.',external_result_unproven:'Der bisherige Auftrag ist noch nicht abgeschlossen. Du kannst die Terminanlage jetzt prüfen und fortsetzen.',verified_reference_conflict:'Der gefundene Termin ist nicht eindeutig zuordenbar. Bitte die Terminzuordnung prüfen.',worker_lease_expired:'Die Übertragung wurde unterbrochen. Der bestehende Auftrag wird abgeglichen.'})[code]||termProblemText(code);}
function termDeadlineMax(start){
  if(!/^\d{4}-\d{2}-\d{2}$/.test(start||''))return '';
  const day=new Date(`${start}T00:00:00Z`);if(!Number.isFinite(day.getTime()))return '';
  day.setUTCDate(day.getUTCDate()-1);return day.toISOString().slice(0,10);
}
function termDeadlineIssue(draft){return draft.mode==='create'&&draft.registrationDeadline&&draft.startDate&&draft.registrationDeadline>=draft.startDate?termProblemText('term_registration_overlap'):'';}
// The final click saves and validates internally. Retries keep the same command/key.
async function saveTermSetup(context,draft,execute){
  if(context.command){
    if(!execute)throw new Error('Ein Terminauftrag wurde bereits gesendet. Bitte den Terminstatus prüfen.');
    if(JSON.stringify(draft)!==context.command.input)throw new Error('Ein Terminauftrag wurde bereits gesendet. Bitte den Terminstatus prüfen, bevor du Daten änderst.');
    return api(context.command.url,{method:'POST',body:context.command.body});
  }
  if(execute&&termDeadlineIssue(draft))throw new Error(termDeadlineIssue(draft));
  if(!context.id){
    const planning={registrationMode:'easyverein',name:draft.title,startDate:draft.startDate,endDate:draft.endDate,eventId:context.eventId,markupPercent:0,clubFeePercent:0,distributionMethod:'PER_PERSON',spaTaxPerPerson:0,spaTaxAgeThreshold:18,adultAgeThreshold:18,bookings:['ADULT_DOUBLE','ADULT_MULTI','CHILD'].map(categoryType=>({categoryType,count:0,basePricePerPerson:0})),groupExpenses:[]};
    const result=await api('/api/trip-wizard',{method:'POST',body:JSON.stringify({operationKey:context.createKey,planning,draft})});context.id=result.tripId;
    context.revision=(await api(`/api/trips/${context.id}/term`)).draft.revision;
  }else{
    const saved=await api(`/api/trips/${context.id}/term/draft`,{method:'PUT',body:JSON.stringify({revision:context.revision,draft})});context.revision=saved.draft.revision;
  }
  if(!execute)return;
  const review=await api(`/api/trips/${context.id}/term/review`,{method:'POST',body:'{}'});
  if(review.issues.length)throw new Error(review.issues.join(' '));
  if(review.blockedReason)throw new Error(termProblemText(review.blockedReason));
  const linking=draft.mode==='link';
  context.command={input:JSON.stringify(draft),url:`/api/trips/${context.id}/term/${linking?'link':'request'}`,body:JSON.stringify(linking?{externalId:draft.externalId,reason:'Ausgewählten easyVerein-Termin im Einrichtungsdialog bestätigt'}:{operationKey:context.actionKey,reviewHash:review.reviewHash,confirmed:true})};
  return api(context.command.url,{method:'POST',body:context.command.body});
}
async function loadWorkflowStatus(trip) {
  clearTimeout(workflowUI.refreshTimer);
  workflowUI.tripId=trip.id;
  byId('participation-details').textContent='Terminstatus wird geprüft.';
  if(!tripUsesEasyVerein(trip)){workflowUI.term=null;byId('term-status').textContent='Clubdesk / CSV: kein easyVerein-Anmeldeweg.';byId('term-details').textContent='';byId('participation-details').textContent='';return;}
  const planningAllowed=['calculation.manage','registration.view','answers.view','analytics.view'].every(c=>trip.capabilities.includes(c));
  byId('planning-transfer-btn').disabled=!!trip.archivedAt||!!trip.closedAt||!planningAllowed;
  byId('planning-transfer-status').textContent=planningAllowed?'Stand wird geladen.':'Für die Übernahme fehlen Planungs-, Anmeldungs- oder Antwortrechte.';
  byId('term-wizard').disabled=!!trip.archivedAt||!!trip.closedAt||!trip.capabilities.includes('term.manage');
  byId('term-status').textContent='Terminverwaltung nicht freigegeben.';byId('term-details').textContent='';
  if(planningAllowed){const data=await api(`/api/trips/${trip.id}/planning-transfer`);if(state.currentTripId!==trip.id)return;
    byId('planning-transfer-status').textContent=data.snapshot?`${data.stale?'Kalkulationsstand veraltet':'Übernommener Stand aktuell'} · ${new Date(data.snapshot.createdAt).toLocaleString('de-DE')}`:'Noch keine Anmeldedaten übernommen.';
  }
  if(trip.capabilities.includes('term.manage')){const data=await api(`/api/trips/${trip.id}/term`);if(state.currentTripId!==trip.id)return;workflowUI.term=data;
    byId('term-status').textContent=termProgressText(data);
    const editable=!trip.archivedAt&&!trip.closedAt;
    if(data.deleted){
      byId('term-wizard').hidden=true;byId('term-wizard').disabled=true;
      byId('term-details').innerHTML=`<p class="status-note is-error">${escapeHtml(termProblemText('term_deleted'))}</p>${editable?'<button type="button" class="btn btn-p" id="term-confirm-deleted">Löschung bestätigen</button>':''}`;
      byId('participation-details').textContent='Der bisherige Termin ist gelöscht. Bestätige dies oben; danach kannst du einen neuen Termin einrichten.';
      byId('term-confirm-deleted')?.addEventListener('click',()=>showManagementModal('Gelöschten Termin bestätigen',`<p>Die Verknüpfung zum gelöschten easyVerein-Termin wird gelöst. Bisherige Anmeldungen bleiben als beendete Einträge mit ihren Antworten erhalten. Anschließend kannst du einen neuen Termin anlegen.</p>`,async()=>{
        await api(`/api/trips/${trip.id}/term/confirm-deleted`,{method:'POST',body:JSON.stringify({externalId:data.link.externalId,confirmed:true})});await loadWorkflowStatus(trip);
      },'Löschung bestätigen'));
      return;
    }

    byId('term-wizard').textContent=data.draft?'Termin fertigstellen':'Termin einrichten';
    byId('term-details').innerHTML=`${data.blockedReason?`<p class="status-note is-error">${escapeHtml(termProblemText(data.blockedReason))}</p>`:''}${data.link?'<button type="button" class="btn btn-o" id="term-edit-open">Termin bearbeiten</button>':''}${data.link&&trip.capabilities.includes('trip.manage')?'<button type="button" class="btn btn-p" id="member-release-open">Mitgliederanmeldung einstellen</button>':''}${!data.link&&data.job&&['unknown','blocked'].includes(data.job.status)&&editable?`<button type="button" class="btn btn-p" id="term-resume" ${data.blockedReason?'disabled':''}>Termin prüfen und anlegen</button><p class="help">Prüft den bisherigen Auftrag in easyVerein. Eine vorhandene Anlage wird fortgesetzt; fehlt der Termin, wird er nach Prüfung möglicher Doppeltermine angelegt.</p>`:''}${data.job?'<button type="button" class="btn btn-o" id="term-refresh">Status aktualisieren</button>':''}${!data.link&&data.job&&['unknown','blocked','failed'].includes(data.job.status)&&editable?'<button type="button" class="btn btn-o" id="term-recover">Vorhandenen Termin selbst zuordnen</button>':''}${!data.link&&data.job?.errorCode&&data.job.status!=='blocked'?`<p class="help">${escapeHtml(termJobError(data.job.errorCode))}</p>`:''}`;
    byId('member-release-open')?.addEventListener('click',()=>performManagement(()=>openMemberRelease(trip)));
    byId('term-edit-open')?.addEventListener('click',()=>performManagement(()=>openTermUpdate(trip)));
    byId('term-resume')?.addEventListener('click',()=>performManagement(async()=>{const button=byId('term-resume');button.disabled=true;try{await resumeTermSetup(trip);}finally{if(button.isConnected)button.disabled=false;}}));
    byId('term-recover')?.addEventListener('click',()=>performManagement(()=>openTermRecovery(trip)));
    byId('term-refresh')?.addEventListener('click',()=>performManagement(()=>loadWorkflowStatus(trip)));
    byId('term-wizard').hidden=!!data.link||!!data.job;
    byId('term-wizard').disabled=!editable||!!data.link||!!data.job;
    if(!data.link&&data.job&&['pending','running','reconciling','unknown','resume_pending'].includes(data.job.status)){
      workflowUI.polls=workflowUI.pollTrip===trip.id?(workflowUI.polls??0)+1:0;workflowUI.pollTrip=trip.id;
      if(workflowUI.polls<24)workflowUI.refreshTimer=setTimeout(()=>{if(state.currentTripId===trip.id&&tripUsesEasyVerein(state.currentTrip))performManagement(()=>loadWorkflowStatus(state.currentTrip));},5000);
    }else{workflowUI.polls=0;workflowUI.pollTrip=trip.id;}
    if(data.link){await loadParticipationStatus(trip,true);}else{byId('participation-details').textContent='Der Teilnahmeabgleich ist verfügbar, sobald der Termin verbunden ist.';}
  }
}
byId('planning-transfer-btn').addEventListener('click',()=>performManagement(async()=>{
  const trip=state.currentTrip;if(!trip||dirtyPlanning())throw new Error('Bitte zuerst die Planungsänderungen speichern.');
  const id=trip.id;workflowUI.preview=null;
  const initial=await api(`/api/trips/${id}/planning-transfer/preview`,{method:'POST',body:'{}'});
  showManagementModal('Anmeldedaten in die Planung übernehmen',`<p>Nur bestätigte aktive Personen. Zimmerwünsche ersetzen keine Zuteilung.</p>${initial.assignedRooms.map((room,i)=>`<label class="f">Zuteilung ${escapeHtml(room)}<div class="in"><select data-room="${escapeHtml(room)}" id="transfer-room-${i}"><option value="">Kategorie wählen</option><option value="ADULT_DOUBLE">Erwachsene Doppelzimmer</option><option value="ADULT_MULTI">Erwachsene Mehrbettzimmer</option></select></div></label>`).join('')}<button type="button" class="btn btn-o" id="transfer-preview">Vorschau berechnen</button><div id="transfer-results" aria-live="polite"></div>${reasonField()}`,async()=>{
    const preview=workflowUI.preview;if(!preview||!preview.canApply)throw new Error('Bitte zuerst eine vollständige Vorschau berechnen.');
    const roomMapping=transferRoomMapping();if(JSON.stringify(Object.entries(roomMapping))!==JSON.stringify(Object.entries(preview.roomMapping)))throw new Error('Zuordnung geändert. Bitte die Vorschau neu berechnen.');
    await api(`/api/trips/${id}/planning-transfer/apply`,{method:'POST',body:JSON.stringify({roomMapping,sourceHash:preview.sourceHash,operationKey:preview.operationKey,reason:byId('action-reason').value})});await openTrip(id);
  },'Übernehmen & neu berechnen');
  byId('transfer-preview').onclick=()=>workflowAction(async()=>{
    const preview=await api(`/api/trips/${id}/planning-transfer/preview`,{method:'POST',body:JSON.stringify({roomMapping:transferRoomMapping()})});preview.operationKey=crypto.randomUUID();workflowUI.preview=preview;
    byId('transfer-results').innerHTML=`<div class="event-table-wrap"><table class="tb"><thead><tr><th>Kategorie</th><th>Geplant</th><th>Angemeldet</th></tr></thead><tbody>${preview.after.bookings.map((b,i)=>`<tr><td>${escapeHtml(b.categoryType==='CHILD'?'Kinder':b.categoryType==='ADULT_DOUBLE'?'Erwachsene Doppelzimmer':preview.budgetAfter.nights===0?'Erwachsene ohne Unterkunft':'Erwachsene Mehrbettzimmer')}</td><td>${preview.before.bookings[i].count}</td><td>${b.count}</td></tr>`).join('')}</tbody></table></div><p>${preview.excludedPersons} offene / stornierte Personen ausgeschlossen.</p><div class="event-grid">${[['Einnahmen','totalCalculatedRevenue'],['Kosten','totalCalculatedCosts'],['Überschuss','surplus']].map(([label,key])=>`<div class="kpi"><span>${label}</span><b>${formatCurrency(preview.budgetAfter[key])}</b><small>vorher ${formatCurrency(preview.budgetBefore[key])}</small></div>`).join('')}</div>${preview.quantities.map(q=>`<p>Zusatzmenge ${escapeHtml(q.target)}: ${escapeHtml(q.value)}</p>`).join('')}<p class="help">${escapeHtml(preview.note)}</p>${preview.issues.map(issue=>`<p class="status-note is-error">${escapeHtml(issue)}</p>`).join('')}`;
  });
}));
function transferRoomMapping(){const map={};byId('management-fields').querySelectorAll('[data-room]').forEach(s=>{if(s.value)map[s.dataset.room]=s.value;});return map;}
byId('term-wizard').addEventListener('click',()=>performManagement(()=>openTermWizard(state.currentTrip)));
async function openTermWizard(trip,event=null){
  if(trip&&dirtyPlanning())throw new Error('Bitte zuerst die Planung speichern.');
  const id=trip?.id;const term=id?await api(`/api/trips/${id}/term`):null;
  const binding=id?await api(`/api/trips/${id}/form`):null;
  const eventId=trip?.eventId??event?.id??null;
  const choices=binding?.availableVersions??(eventId?(await api(`/api/events/${eventId}/forms`)).filter(v=>v.status==='published'):[]);
  const team=id?(hasTripCapability('access.manage')||state.currentUser?.role==='admin'?await api(`/api/trips/${id}/team`):[]):eventId?await api(`/api/events/${eventId}/default-team`):[];
  const d=term?.draft?.data??{title:trip?.name??'',startDate:trip?.startDate??'',endDate:trip?.endDate??null,location:'',description:'',calendarReference:'',visibility:'members',capacity:0,registrationDeadline:null,priceGroups:[],formVersionId:binding?.versionId??null,mode:'create',externalId:'',teamUserIds:team.map(m=>m.userId)};
  if(id){d.title=trip.name;d.startDate=trip.startDate;d.endDate=trip.endDate??null;d.formVersionId=binding?.versionId??null;}
  let step=0,busy=false;const context={id:id??null,eventId,revision:term?.draft?.revision??0,createKey:crypto.randomUUID(),actionKey:crypto.randomUUID()};
  const labels=['Eckdaten','Anmeldung','Prüfen & abschließen'];
  const shortLabels=['Eckdaten','Anmeldung','Abschluss'];
  const fld=(key,label,type='text')=>`<label class="f">${label}<div class="in"><input data-term="${key}" type="${type}" value="${escapeHtml(d[key]??'')}" ${type==='number'?'min="0" max="1000000"':key==='registrationDeadline'?`max="${termDeadlineMax(d.startDate)}" aria-describedby="wizard-deadline-help"`:''}></div></label>`;
  showManagementModal(trip?'Termin einrichten':'Reise & Termin einrichten',`<nav class="wizard-steps" aria-label="Wizard-Schritte">${labels.map((l,i)=>`<button type="button" class="wizard-step" data-wizard-step="${i}" aria-label="Schritt ${i+1}: ${l}"><span class="wizard-step-number" aria-hidden="true">${i+1}</span><span class="wizard-step-full">${l}</span><span class="wizard-step-short">${shortLabels[i]}</span></button>`).join('')}</nav><section class="wizard-panel" data-wizard-panel="0" aria-labelledby="wizard-heading-0"><h3 id="wizard-heading-0">Eckdaten</h3><div class="wizard-fields">${id?'<p class="help">Name und Reisedaten werden aus der Planung übernommen.</p>':''}${fld('title','Reisename')}${fld('startDate','Beginn','date')}${fld('endDate','Ende / leer für Tagesveranstaltung','date')}${fld('location','Ort')}<label class="f">Beschreibung<div class="in ta"><textarea data-term="description" maxlength="10000">${escapeHtml(d.description)}</textarea></div></label></div></section><section class="wizard-panel" data-wizard-panel="1" aria-labelledby="wizard-heading-1"><h3 id="wizard-heading-1">Kalender & Anmeldung</h3><div class="wizard-fields"><label class="f">Terminweg<div class="in"><select data-term="mode"><option value="create">Neuen Termin vorbereiten</option><option value="link">Bestehenden Termin verknüpfen</option></select></div></label><input type="hidden" data-term="externalId" value="${escapeHtml(d.externalId)}"><input type="hidden" data-term="calendarReference" value="${escapeHtml(d.calendarReference)}"><div id="wizard-event-picker"><label class="f" for="wizard-event-query">easyVerein-Termin suchen<div class="in"><input type="search" id="wizard-event-query" maxlength="200" placeholder="Name des Termins"></div></label><label class="f" for="wizard-event-from">Termine ab<div class="in"><input type="date" id="wizard-event-from" value="${escapeHtml(d.startDate)}"></div></label><p class="help">Datum leeren, um auch frühere Termine zu finden.</p><button type="button" id="wizard-event-search" class="btn btn-g">Termine suchen</button><p id="wizard-event-status" class="help" role="status"></p><div id="wizard-event-results" class="catalog-results"></div><button type="button" id="wizard-event-more" class="btn btn-g" hidden>Weitere Termine laden</button><p id="wizard-event-selected" class="status-note" aria-live="polite"></p></div><div id="wizard-calendar-picker"><label class="f" for="wizard-calendar-query">easyVerein-Kalender suchen<div class="in"><input type="search" id="wizard-calendar-query" maxlength="200" placeholder="Name des Kalenders"></div></label><button type="button" id="wizard-calendar-search" class="btn btn-g">Kalender suchen</button><p id="wizard-calendar-status" class="help" role="status"></p><div id="wizard-calendar-results" class="catalog-results"></div><button type="button" id="wizard-calendar-more" class="btn btn-g" hidden>Weitere Kalender laden</button></div><p id="wizard-calendar-selected" class="status-note" aria-live="polite"></p><label class="f">Sichtbarkeit<div class="in"><select data-term="visibility"><option value="members">Mitglieder</option><option value="private">Privat</option></select></div></label>${fld('capacity','Maximale Teilnehmerzahl','number')}${fld('registrationDeadline','Anmeldeschluss','date')}<p class="help" id="wizard-deadline-help">Der Anmeldeschluss gilt bis 23:59 Uhr. Bei ganztägigen Terminen muss er spätestens am Vortag liegen.</p></div><h3>Preisgruppen</h3><p>Ohne Preisgruppen entfällt die Preisauswahl bei der Anmeldung. Für angelegte Preisgruppen bitte vor dem Abschluss einen Preis eintragen.</p><div id="wizard-price-groups" class="wizard-price-groups"></div><button type="button" id="wizard-add-price" class="btn btn-g">Preisgruppe hinzufügen</button><details><summary>Zusatzformular & Team</summary><label class="f">Formularversion<div class="in"><select data-term="formVersionId"><option value="">Ohne Zusatzformular</option>${choices.map(v=>`<option value="${v.id}">Version ${v.number}</option>`).join('')}</select></div></label><p>Team für diese Reise</p>${team.map(m=>`<label class="fld"><input data-term-user="${m.userId}" type="checkbox" ${d.teamUserIds.includes(m.userId)?'checked':''}>${escapeHtml(m.email)} · ${escapeHtml(m.capabilities.map(c=>capabilityLabels[c]??c).join(', '))}</label>`).join('')}<p class="help">${id?'Formularwechsel und Teamfreigaben erfolgen bewusst in den vorhandenen Verwaltungsdialogen.':'Das gewählte Standardteam wird für diese Reise übernommen. Du behältst deine Reiserechte.'}</p></details></section><section class="wizard-panel" data-wizard-panel="2" aria-labelledby="wizard-heading-2"><h3 id="wizard-heading-2">Prüfen & abschließen</h3><div id="wizard-review" aria-live="polite"></div><p class="help" id="wizard-action-help"></p><div id="wizard-review-errors" role="alert"></div></section>`,async()=>{
    if(step!==2)throw new Error('Bitte zuerst die Zusammenfassung öffnen.');
    await finish(true);
  },'Termin in easyVerein anlegen');
  byId('management-modal').classList.add('is-term-wizard');
  const footerNav=document.createElement('div');footerNav.id='wizard-footer-navigation';footerNav.className='wizard-footer-navigation';footerNav.innerHTML='<button type="button" class="btn btn-g" id="wizard-back">Zurück</button><span id="wizard-position" role="status"></span><button type="button" class="btn btn-p" id="wizard-next">Weiter</button>';byId('management-modal').querySelector('.modal-footer').prepend(footerNav);
  const draftButton=document.createElement('button');draftButton.type='button';draftButton.id='wizard-save-draft';draftButton.className='btn btn-o';draftButton.textContent='Als Entwurf speichern';byId('management-modal').querySelector('.modal-footer').append(draftButton);
  async function finish(execute){
    if(busy)throw new Error('Bitte warten, der Termin wird gespeichert.');
    busy=true;draftButton.disabled=true;
    try{await saveTermSetup(context,collect(),execute);management.modal.hide();
      if(!id){await loadTripList();await navigateTo(`/trips/${context.id}#termin`);}else await loadWorkflowStatus(state.currentTrip);
    }finally{busy=false;draftButton.disabled=false;}
  }
  draftButton.onclick=()=>workflowAction(async()=>{draftButton.disabled=true;byId('management-submit').disabled=true;try{await finish(false);}finally{draftButton.disabled=false;byId('management-submit').disabled=false;}});
  byId('management-fields').querySelector('[data-term="mode"]').value=d.mode;byId('management-fields').querySelector('[data-term="visibility"]').value=d.visibility;byId('management-fields').querySelector('[data-term="formVersionId"]').value=d.formVersionId??'';
  function addPrice(g={label:'',price:null,category:'ADULT_MULTI'}){const row=document.createElement('div');row.className='wizard-price-card';row.innerHTML=`<label class="f">Bezeichnung<div class="in"><input data-price-label value="${escapeHtml(g.label)}" maxlength="200"></div></label><label class="f">Preis<div class="in"><input data-price-value type="number" min="0" max="1000000" step="0.01" value="${escapeHtml(g.price??'')}"><span class="u">€</span></div></label><label class="f">Kategorie<div class="in"><select data-price-category>${['ADULT_DOUBLE','ADULT_MULTI','CHILD','GUEST'].map(c=>`<option value="${c}" ${g.category===c?'selected':''}>${({ADULT_DOUBLE:'Erwachsene · Doppelzimmer',ADULT_MULTI:'Erwachsene · Mehrbettzimmer',CHILD:'Kinder',GUEST:'Gäste'})[c]}</option>`).join('')}</select></div></label><button type="button" class="btn btn-d wizard-remove-price">Preisgruppe entfernen</button>`;row.querySelector('button').onclick=()=>row.remove();byId('wizard-price-groups').append(row);}
  d.priceGroups.forEach(addPrice);byId('wizard-add-price').onclick=()=>addPrice();
  function collect(){const draft={...d};byId('management-fields').querySelectorAll('[data-term]').forEach(el=>draft[el.dataset.term]=el.value);draft.endDate=draft.endDate||null;draft.registrationDeadline=draft.registrationDeadline||null;draft.capacity=Number(draft.capacity);draft.formVersionId=draft.formVersionId?Number(draft.formVersionId):null;draft.teamUserIds=[...byId('management-fields').querySelectorAll('[data-term-user]:checked')].map(el=>Number(el.dataset.termUser));draft.priceGroups=[...byId('wizard-price-groups').children].map(row=>({label:row.querySelector('[data-price-label]').value,price:row.querySelector('[data-price-value]').value||null,category:row.querySelector('[data-price-category]').value}));return draft;}
  if(id){for(const key of ['title','startDate','endDate'])byId('management-fields').querySelector(`[data-term="${key}"]`).readOnly=true;byId('management-fields').querySelector('[data-term="formVersionId"]').disabled=true;byId('management-fields').querySelectorAll('[data-term-user]').forEach(input=>input.disabled=true);}
  const startInput=byId('management-fields').querySelector('[data-term="startDate"]'),deadlineInput=byId('management-fields').querySelector('[data-term="registrationDeadline"]');
  startInput.addEventListener('change',()=>{deadlineInput.max=termDeadlineMax(startInput.value);});
  const catalogue=initTermCatalogPicker(byId('management-fields'),id);
  function showStep(n){step=Math.max(0,Math.min(2,n));byId('management-fields').querySelectorAll('[data-wizard-panel]').forEach(el=>el.classList.toggle('d-none',Number(el.dataset.wizardPanel)!==step));byId('management-fields').querySelectorAll('[data-wizard-step]').forEach(el=>{const index=Number(el.dataset.wizardStep);el.setAttribute('aria-current',index===step?'step':'false');el.classList.toggle('is-done',index<step);el.querySelector('.wizard-step-number').textContent=index<step?'✓':String(index+1);});byId('wizard-back').disabled=step===0;byId('wizard-next').hidden=step===2;byId('management-submit').hidden=step!==2;byId('wizard-position').textContent=`Schritt ${step+1} von 3`;byId('management-fields').scrollTop=0;byId(`wizard-heading-${step}`).setAttribute('tabindex','-1');byId(`wizard-heading-${step}`).focus({preventScroll:true});if(step===1){catalogue.load();}if(step===2){const draft=collect();byId('wizard-review').innerHTML=`<h3>${escapeHtml(draft.title)}</h3><p>${escapeHtml(draft.startDate)} – ${escapeHtml(draft.endDate??'Tagesveranstaltung')} · ${escapeHtml(draft.location)}</p><p>Kalender: ${escapeHtml(catalogue.calendarName())} · ${draft.visibility==='members'?'Nur Mitglieder':'Privat'} · Kapazität ${draft.capacity}</p>${draft.mode==='link'?`<p>Bestehender Termin: ${escapeHtml(catalogue.eventName())}</p>`:''}<p>Formular #${draft.formVersionId??'–'} · ${draft.teamUserIds.length} Teamvorschläge</p><p>${draft.priceGroups.map(g=>escapeHtml(`${g.label}: ${g.price??'Preis fehlt'} €`)).join(' · ')}</p><p>${draft.registrationDeadline?`Anmeldeschluss: ${escapeHtml(draft.registrationDeadline)}`:"Kein Anmeldeschluss festgelegt."}</p>`;byId('management-submit').textContent=draft.mode==='link'?'Ausgewählten Termin verknüpfen':'Termin in easyVerein anlegen';byId('wizard-action-help').textContent=draft.mode==='link'?'Der ausgewählte Termin wird dieser Reise zugeordnet. Danach kannst du die Mitgliederanmeldung freigeben.':'Mit diesem Klick wird der Termin in easyVerein angelegt. Danach kannst du die Mitgliederanmeldung freigeben.';}}

  byId('wizard-back').onclick=()=>showStep(step-1);byId('wizard-next').onclick=()=>showStep(step+1);byId('management-fields').querySelectorAll('[data-wizard-step]').forEach(el=>el.onclick=()=>showStep(Number(el.dataset.wizardStep)));showStep(0);
}

async function resumeTermSetup(trip){
  const data=await api(`/api/trips/${trip.id}/term`);
  if(!data.link&&data.job)await api(`/api/trips/${trip.id}/term/resume`,{method:'POST',body:JSON.stringify({jobId:data.job.id,attempts:data.job.attempts,confirmed:true})});
  if(state.currentTripId===trip.id)await loadWorkflowStatus(state.currentTrip);
}

async function openTermRecovery(trip){
  let selected=null,offset=null,sequence=0;
  showManagementModal('Terminzuordnung klären',`<p>Suche den Termin, der bereits in easyVerein angelegt wurde, und ordne ihn dieser Reise zu. Hier wird kein neuer Termin angelegt. Falls keiner vorhanden ist, prüfe die Anlage direkt in easyVerein.</p><label class="f">Termin suchen<div class="in"><input type="search" id="term-recovery-query" value="${escapeHtml(trip.name)}" maxlength="200"></div></label><button type="button" class="btn btn-o" id="term-recovery-search">Suchen</button><p id="term-recovery-status" role="status"></p><div id="term-recovery-results" class="catalog-results"></div><button type="button" class="btn btn-o" id="term-recovery-more" hidden>Weitere Termine laden</button><p id="term-recovery-selected" class="status-note" aria-live="polite"></p>`,async()=>{
    if(!selected)throw new Error('Bitte zuerst einen Termin aus den Suchtreffern auswählen.');
    await api(`/api/trips/${trip.id}/term/link`,{method:'POST',body:JSON.stringify({externalId:selected.id,reason:'Festhängenden Terminauftrag über die Terminsuche bewusst zugeordnet'})});
    await loadWorkflowStatus(state.currentTrip);
  },'Ausgewählten Termin zuordnen');
  const root=byId('management-fields'),queryInput=byId('term-recovery-query');const active=()=>root.isConnected&&byId('term-recovery-query')===queryInput;byId('management-submit').disabled=true;
  async function search(more=false){
    const request=++sequence;if(!more){offset=null;selected=null;byId('term-recovery-selected').textContent='';byId('management-submit').disabled=true;byId('term-recovery-results').replaceChildren();}
    byId('term-recovery-status').textContent='Termine werden gesucht …';byId('term-recovery-search').disabled=true;byId('term-recovery-more').hidden=true;
    try{
      const data=await api(`/api/trips/${trip.id}/term/catalog/events?${new URLSearchParams({q:byId('term-recovery-query').value.trim(),offset:String(more?offset??0:0),from:''})}`);
      if(!active()||request!==sequence)return;
      for(const item of data.items){const button=document.createElement('button');button.type='button';button.className='btn btn-o catalog-result';button.textContent=`${item.name} · ${new Date(item.start).toLocaleDateString('de-DE')} – ${new Date(item.end).toLocaleDateString('de-DE')}${item.canceled?' · abgesagt':''}`;button.disabled=!!item.canceled;button.onclick=()=>{selected=item;byId('term-recovery-selected').textContent='Ausgewählt: '+button.textContent;byId('management-submit').disabled=false;};byId('term-recovery-results').append(button);}
      offset=data.nextOffset;byId('term-recovery-more').hidden=offset===null;byId('term-recovery-status').textContent=byId('term-recovery-results').children.length?'Bitte Datum und Termin prüfen und den passenden Treffer auswählen.':'Kein Treffer. Suchbegriff ändern oder prüfen, ob der Termin in easyVerein vorhanden ist.';
    }catch(error){if(active()&&request===sequence)byId('term-recovery-status').textContent='Die Terminsuche ist fehlgeschlagen. Bitte API-Zugang prüfen und erneut versuchen.';}
    finally{if(active()&&request===sequence)byId('term-recovery-search').disabled=false;}
  }
  byId('term-recovery-search').onclick=()=>search();byId('term-recovery-more').onclick=()=>search(true);
  byId('term-recovery-query').onkeydown=event=>{if(event.key==='Enter'){event.preventDefault();search();}};
  byId('term-recovery-query').oninput=()=>{sequence++;selected=null;offset=null;byId('term-recovery-selected').textContent='';byId('management-submit').disabled=true;byId('term-recovery-search').disabled=false;byId('term-recovery-more').hidden=true;byId('term-recovery-results').replaceChildren();byId('term-recovery-status').textContent='Suche starten.';};
  await search();
}

// Explicit searches avoid an API call on every keystroke. Provider IDs stay in hidden fields.
function initTermCatalogPicker(root,tripId){
  const el=id=>root.querySelector('#'+id),field=key=>root.querySelector(`[data-term="${key}"]`);
  const base=tripId?`/api/trips/${tripId}/term/catalog`:'/api/trip-wizard/catalog';
  const pickers={event:{resource:'events',next:null,sequence:0,loaded:false,loading:false},calendar:{resource:'calendars',next:null,sequence:0,loaded:false,loading:false}};
  let calendar=null,event=null,hydrated=false;
  const dateLabel=value=>new Intl.DateTimeFormat('de-DE',{timeZone:'Europe/Berlin',day:'2-digit',month:'2-digit',year:'numeric'}).format(new Date(value));
  const eventLabel=item=>`${item.name} · ${dateLabel(item.start)}${dateLabel(item.end)!==dateLabel(item.start)?' – '+dateLabel(item.end):''}`;
  function selected(){el('wizard-event-selected').hidden=!field('externalId').value;el('wizard-calendar-selected').hidden=!field('calendarReference').value;el('wizard-event-selected').textContent=field('externalId').value?'Ausgewählt: '+(event?eventLabel(event):'Gespeicherter Termin'):'';el('wizard-calendar-selected').textContent=field('calendarReference').value?'Kalender: '+(calendar?.name??'Gespeicherter Kalender'):'';}
  function loadError(error){let code='';try{code=JSON.parse(error.message).error;}catch{}return ({easyverein_credentials_missing:'Der easyVerein-API-Zugang ist noch nicht eingerichtet.',provider_rate_limited:'easyVerein begrenzt gerade die Anfragen. Bitte später erneut versuchen.',forbidden:'Für die Terminauswahl fehlt die Berechtigung.',unauthorized:'Bitte erneut anmelden.'})[code]??'Die Auswahl konnte nicht aus easyVerein geladen werden. Bitte erneut versuchen.';}
  async function readSelected(kind,id){const data=await api(`${base}/${pickers[kind].resource}?selected=${encodeURIComponent(id)}`);return data.items[0];}
  async function choose(kind,item){
    if(kind==='event'){
      event=item;field('externalId').value=item.id;field('calendarReference').value=item.calendarId;calendar=null;selected();
      const expected=item.calendarId;
      try{const row=await readSelected('calendar',expected);if(!root.isConnected||field('calendarReference').value!==expected)return;calendar=row;selected();}catch(error){if(root.isConnected&&field('calendarReference').value===expected)el('wizard-event-status').textContent='Der Kalendername konnte nicht geladen werden. Suche erneut, um es noch einmal zu versuchen.';}
    }else{calendar=item;field('calendarReference').value=item.id;selected();}
    root.dispatchEvent(new Event('change',{bubbles:true}));
  }
  async function search(kind,more=false){
    const picker=pickers[kind],prefix='wizard-'+kind,sequence=++picker.sequence;
    const q=el(prefix+'-query').value.trim(),from=kind==='event'?el(prefix+'-from').value:'';
    if(!more){picker.next=null;el(prefix+'-results').replaceChildren();}
    const params=new URLSearchParams({q,offset:String(more?picker.next??0:0),from});
    picker.loading=true;el(prefix+'-status').textContent='Wird geladen …';el(prefix+'-search').disabled=true;el(prefix+'-more').hidden=true;
    try{
      const data=await api(`${base}/${picker.resource}?${params}`);
      if(!root.isConnected||sequence!==picker.sequence)return;
      for(const item of data.items){const button=document.createElement('button');button.type='button';button.className='btn btn-o catalog-result';button.textContent=kind==='event'?eventLabel(item)+(item.canceled?' · abgesagt':''):item.name;button.disabled=!!item.canceled;button.addEventListener('click',()=>choose(kind,item));el(prefix+'-results').append(button);}
      picker.next=data.nextOffset;picker.loaded=true;el(prefix+'-more').hidden=picker.next===null;
      el(prefix+'-status').textContent=el(prefix+'-results').children.length?'Treffer auswählen.': 'Keine Treffer. Suchbegriff oder Datum ändern.';
    }catch(error){if(root.isConnected&&sequence===picker.sequence)el(prefix+'-status').textContent='Laden fehlgeschlagen. '+loadError(error);}
    finally{if(root.isConnected&&sequence===picker.sequence){picker.loading=false;el(prefix+'-search').disabled=false;}}
  }
  function mode(){const link=field('mode').value==='link';el('wizard-event-picker').hidden=!link;el('wizard-calendar-picker').hidden=link;selected();}
  for(const kind of ['event','calendar']){const prefix='wizard-'+kind;
    el(prefix+'-search').onclick=()=>search(kind);el(prefix+'-more').onclick=()=>search(kind,true);
    el(prefix+'-query').addEventListener('keydown',e=>{if(e.key==='Enter'){e.preventDefault();search(kind);}});
    const invalidate=()=>{const picker=pickers[kind];picker.sequence++;picker.loaded=false;picker.loading=false;picker.next=null;el(prefix+'-results').replaceChildren();el(prefix+'-more').hidden=true;el(prefix+'-search').disabled=false;el(prefix+'-status').textContent='Suche starten, um passende Treffer zu laden.';};
    el(prefix+'-query').addEventListener('input',invalidate);if(kind==='event')el(prefix+'-from').addEventListener('input',invalidate);
  }
  field('mode').addEventListener('change',()=>{mode();load();});mode();
  async function load(){
    const kind=field('mode').value==='link'?'event':'calendar';if(!pickers[kind].loaded&&!pickers[kind].loading)search(kind);
    if(hydrated)return;hydrated=true;
    try{
      if(field('externalId').value){const expected=field('externalId').value;const row=await readSelected('event',expected);if(root.isConnected&&field('externalId').value===expected){event=row;selected();}}
      if(field('calendarReference').value){const expected=field('calendarReference').value;const row=await readSelected('calendar',expected);if(root.isConnected&&field('calendarReference').value===expected){calendar=row;selected();}}
    }catch(error){if(root.isConnected){hydrated=false;el('wizard-'+kind+'-status').textContent='Gespeicherte Auswahl konnte nicht geladen werden. '+loadError(error);}}
  }
  return {load,calendarName:()=>calendar?.name??(field('calendarReference').value?'Gespeicherter Kalender':'Kein Kalender gewählt'),eventName:()=>event?eventLabel(event):(field('externalId').value?'Gespeicherter Termin':'Kein Termin gewählt')};
}
