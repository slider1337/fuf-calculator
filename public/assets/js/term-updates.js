// Term edits are saved directly; the server supplies the stored trip dates.
const termFieldLabels={title:'Titel',start:'Beginn',end:'Ende',location:'Ort',description:'Beschreibung',capacity:'Maximale Teilnehmerzahl',registrationDeadline:'Anmeldeschluss',visibleToMembers:'Im Mitgliederbereich sichtbar'};
function termLocalDateTime(value){if(!value)return '';const d=new Date(value);const parts=new Intl.DateTimeFormat('sv-SE',{timeZone:'Europe/Berlin',year:'numeric',month:'2-digit',day:'2-digit',hour:'2-digit',minute:'2-digit',second:'2-digit',hourCycle:'h23'}).format(d);return parts.replace(' ','T');}
async function openTermUpdate(trip){
  const url=`/api/trips/${trip.id}/term/updates`,data=await api(url);
  const active=data.jobs.find(j=>['pending','running','unknown','reconciling'].includes(j.status));
  const locked=!!trip.archivedAt||!!trip.closedAt||!!data.blockedReason||!!active;
  const fields=Object.entries(termFieldLabels).filter(([field])=>!['start','end'].includes(field)).map(([field,label])=>{
    const value=data.fields[field],date=field==='registrationDeadline';
    const input=field==='description'?`<textarea aria-label="${label}" data-term-update="${field}" maxlength="10000">${escapeHtml(value??'')}</textarea>`:field==='visibleToMembers'?`<select aria-label="${label}" data-term-update="${field}"><option value="true" ${value?'selected':''}>Ja</option><option value="false" ${!value?'selected':''}>Nein</option></select>`:`<input aria-label="${label}" data-term-update="${field}" type="${date?'datetime-local':field==='capacity'?'number':'text'}" value="${escapeHtml(date?termLocalDateTime(value):field==='capacity'&&value===0?'':value)}" ${date?'step="1"':field==='capacity'?'min="1" max="1000000"':field==='title'?'maxlength="200" required':'maxlength="500"'}>`;
    return `<label class="f">${label}${date?' (deutsche Ortszeit)':''}<div class="in">${input}</div></label>`;
  }).join('');
  let command=null;
  const latest=data.jobs[0];
  showManagementModal('Termin bearbeiten',`<p>Beginn und Ende werden aus der gespeicherten Reise übernommen.</p><p><strong>${escapeHtml(termUpdateValue('start',data.tripFields.start))} – ${escapeHtml(termUpdateValue('end',data.tripFields.end))}</strong></p><p class="help">Datumsänderungen können easyVerein-Benachrichtigungen auslösen.</p>${locked?`<p class="status-note is-error">${active?termUpdateOutcome(active):escapeHtml(termProblemText(data.blockedReason))}</p>`:''}${fields}<p id="term-save-progress" role="status" aria-live="polite">${latest&&latest.status!=='succeeded'?escapeHtml(termUpdateOutcome(latest)):''}</p>${active?'<button type="button" class="btn btn-o" id="term-update-refresh">Status aktualisieren</button>':''}${active?.status==='unknown'?`<button type="button" class="btn btn-o" id="term-resolve">Aktuellen easyVerein-Stand übernehmen</button>`:''}`,async()=>{
    if(locked)throw new Error('Eine Terminänderung ist noch offen. Bitte den aktuellen Status prüfen.');
    const values=collect();
    if(command&&command.input!==JSON.stringify(values))throw new Error('Eine Übertragung ist noch ungeklärt. Bitte mit unveränderten Werten erneut speichern, um ihren Status zu prüfen.');
    command||={input:JSON.stringify(values),body:{operationKey:crypto.randomUUID(),observedHash:data.observedHash,changes:values}};
    byId('term-save-progress').textContent='Wird in easyVerein gespeichert …';
    let result,accepted=false;
    try{result=await api(url+'/save',{method:'POST',body:JSON.stringify(command.body)});accepted=true;result=await waitForTermUpdate(url,result);}
    catch(error){
      if(!accepted){try{if(['validation_error','term_registration_overlap','term_review_changed','term_changed','term_update_in_progress','provider_read_failed','provider_read_invalid','provider_rate_limited','provider_term_fields_unproven','easyverein_production_writes_disabled','trip_archived','trip_closed','forbidden','unauthorized'].includes(JSON.parse(error.message).error))command=null;}catch{}}
      const message=termSaveMessage(error);byId('term-save-progress').textContent=message;throw new Error(message);
    }
    byId('term-save-progress').textContent=termUpdateOutcome(result);
    if(result.status!=='succeeded'){
      if(['blocked','conflict','failed'].includes(result.status))command=null;
      throw new Error(termUpdateOutcome(result));
    }
    try{await loadWorkflowStatus(trip);setStatusNote('management-status','Termin in easyVerein gespeichert.','success');}
    catch{setStatusNote('management-status','Termin in easyVerein gespeichert. Die Anzeige konnte gerade nicht aktualisiert werden. Bitte die Seite neu laden.','success');}
  },'In easyVerein speichern');
  function collect(){const changes={};byId('management-fields').querySelectorAll('[data-term-update]').forEach(input=>{
    const field=input.dataset.termUpdate,value=input.value;
    if(field==='registrationDeadline'){
      if(value!==termLocalDateTime(data.fields[field]))changes[field]=value||null;
    }else{if(field==='capacity'&&value===''&&data.fields[field]===0)return;const parsed=field==='capacity'?Number(value):field==='visibleToMembers'?value==='true':value;if(parsed!==data.fields[field])changes[field]=parsed;}
  });return changes;}
  byId('management-submit').disabled=locked;
  if(active)byId('term-update-refresh').onclick=()=>workflowAction(()=>openTermUpdate(trip));
  if(active?.status==='unknown')byId('term-resolve').onclick=()=>workflowAction(()=>openTermUpdateResolution(trip,active.id));
}
async function waitForTermUpdate(url,result){
  for(let attempt=0;attempt<12&&['pending','running','reconciling'].includes(result.status);attempt++){
    await new Promise(resolve=>setTimeout(resolve,1500));const state=await api(url);const job=state.jobs.find(j=>j.id===result.jobId);if(job)result={...job,jobId:job.id};
  }
  return result;
}
function termSaveMessage(error){
  try{
    const data=JSON.parse(error.message);
    if(data.error==='validation_error'){
      const labels={'changes.registrationDeadline':'Anmeldeschluss: Bitte ein gültiges Datum und eine gültige Uhrzeit eingeben.','changes.capacity':'Maximale Teilnehmerzahl: Bitte eine positive ganze Zahl eingeben.','changes.title':'Bitte einen Titel eingeben.','changes.end':'Das Reiseende muss nach dem Reisebeginn liegen.'};
      return Object.keys(data.details||{}).map(field=>labels[field]||'Bitte die eingegebenen Termindaten prüfen.').join(' ')||'Bitte die eingegebenen Termindaten prüfen.';
    }
    const messages={term_changed:'Die Terminzuordnung wurde geändert. Bitte den Dialog neu öffnen.',term_review_changed:'Reise oder Termin wurden inzwischen geändert. Bitte den Dialog neu öffnen.',trip_archived:'Die Reise ist archiviert und kann nicht geändert werden.',trip_closed:'Die Reise ist abgerechnet und kann nicht geändert werden.',forbidden:'Du hast keine Berechtigung zum Ändern dieses Termins.',unauthorized:'Deine Anmeldung ist abgelaufen. Bitte erneut anmelden.',easyverein_production_writes_disabled:'Die easyVerein-Schreibfunktion ist nicht freigegeben.'};
    if(messages[data.error])return messages[data.error];
    if(['provider_rate_limited','provider_read_failed','provider_read_invalid','provider_term_fields_unproven'].includes(data.error))return termUpdateError(data.error);
    return managementMessage(error);
  }catch{if(error.name==='SyntaxError')return 'Der Server hat keine lesbare Antwort geliefert. Bitte unverändert erneut speichern, um den Übertragungsstatus zu prüfen.';return error.message==='Failed to fetch'?'Die Verbindung wurde unterbrochen. Bitte unverändert erneut speichern, um den Übertragungsstatus zu prüfen.':managementMessage(error);}
}
function termUpdateOutcome(result){
  if(result.status==='succeeded')return 'In easyVerein gespeichert und geprüft.';
  if(['pending','running','reconciling'].includes(result.status))return 'Die Übertragung läuft noch. Bitte den Status aktualisieren.';
  return [termUpdateError(result.errorCode),termValidationText(result.validationErrors)].filter(Boolean).join(' ')||'Die Änderung konnte nicht bestätigt werden. Bitte den Status prüfen.';
}
function termUpdateValue(field,value){return ['start','end','registrationDeadline'].includes(field)&&value?new Date(value).toLocaleString('de-DE',{timeZone:'Europe/Berlin'}):valueText(value);}
function termUpdateError(code){return ({provider_rate_limited:'easyVerein erlaubt momentan keine weiteren API-Anfragen. Bitte in einer Minute erneut versuchen.',provider_read_failed:'Der aktuelle Termin konnte nicht aus easyVerein gelesen werden. Bitte API-Zugang und Erreichbarkeit prüfen.',provider_read_invalid:'Die Antwort von easyVerein konnte nicht gelesen werden.',provider_term_fields_unproven:'easyVerein liefert unvollständige oder ungültige Termindaten.',term_update_unauthorized:'easyVerein hat den API-Token abgelehnt. Bitte den API-Zugang prüfen.',term_update_forbidden:'Dem API-Token fehlt die Berechtigung zum Ändern von Terminen.',term_update_not_found:'Der verknüpfte Termin wurde in easyVerein nicht gefunden.',term_update_rejected:'easyVerein hat die Änderung abgelehnt.',term_registration_overlap:termProblemText('term_registration_overlap'),term_update_resolved:'Der aktuelle easyVerein-Stand wurde übernommen.',external_outcome_unknown:'easyVerein hat keine eindeutige Rückmeldung geliefert. Die Änderung könnte übernommen worden sein. Bitte den aktuellen Stand prüfen.',external_result_unproven:'Die zurückgelesenen easyVerein-Werte bestätigen die Änderung noch nicht.'})[code]||participationError(code);}
async function openTermUpdateResolution(trip,jobId){
  const url=`/api/trips/${trip.id}/term/updates`,data=await api(url);
  if(!data.jobs.some(j=>j.id===jobId&&j.status==='unknown'))throw new Error('Der Auftrag hat sich geändert. Bitte den Status aktualisieren.');
  showManagementModal('Aktuellen easyVerein-Stand übernehmen',`<p>Diese Werte stehen aktuell in easyVerein. Mit „Übernehmen“ wird die offene Übertragung abgeschlossen. Danach kannst du den Termin wieder bearbeiten.</p>${Object.entries(data.fields).map(([field,value])=>`<p><strong>${escapeHtml(termFieldLabels[field])}</strong>: ${escapeHtml(termUpdateValue(field,value))}</p>`).join('')}`,async()=>{
    await api(url+'/resolve',{method:'POST',body:JSON.stringify({jobId,observedHash:data.observedHash,confirmed:true})});await loadWorkflowStatus(trip);
  },'Übernehmen');
}

async function openMemberRelease(trip){
  const url=`/api/trips/${trip.id}/member-settings`,data=await api(url),settings=data.settings;
  const locked=!!data.blockedReason||!!trip.archivedAt||!!trip.closedAt;
  const deadline=data.blockedReason?'Der Anmeldeschluss kann gerade nicht geladen werden.':data.bookingUntil===null?'Kein Anmeldeschluss im Termin festgelegt.':new Date(data.bookingUntil*1000).toLocaleString('de-DE');
  showManagementModal('Mitgliederanmeldung einstellen',`<p>Der Anmeldeschluss des easyVerein-Termins gilt automatisch für neue Buchungen, Änderungen und Stornos. Änderungen am Anmeldeschluss erfolgen im Termin.</p>${data.blockedReason?`<p class="status-note is-error">${escapeHtml(termProblemText(data.blockedReason))}</p>`:''}<label class="f">Freigabe<div class="in"><select id="member-release-enabled"><option value="false" ${!settings?.enabled||locked?'selected':''}>Gesperrt</option><option value="true" ${settings?.enabled&&!locked?'selected':''} ${locked?'disabled':''}>Freigegeben</option></select></div></label><p class="help">Anmeldeschluss: <strong>${escapeHtml(deadline)}</strong></p>`,async()=>{
    const enabled=byId('member-release-enabled').value==='true';
    if(enabled&&locked)throw new Error('Die Mitgliederfreigabe ist derzeit gesperrt.');
    await api(url,{method:'PUT',body:JSON.stringify({enabled})});await loadWorkflowStatus(state.currentTrip);
  },'Freigabe speichern');
}
