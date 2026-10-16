// Term changes and member registration release.
const termFieldLabels={title:'Titel',start:'Beginn',end:'Ende',location:'Ort',description:'Beschreibung',capacity:'Maximale Teilnehmerzahl',registrationDeadline:'Anmeldeschluss',visibleToMembers:'Im Mitgliederbereich sichtbar'};
function termLocalDateTime(value){if(!value)return '';const d=new Date(value);return `${d.getFullYear()}-${String(d.getMonth()+1).padStart(2,'0')}-${String(d.getDate()).padStart(2,'0')}T${String(d.getHours()).padStart(2,'0')}:${String(d.getMinutes()).padStart(2,'0')}:${String(d.getSeconds()).padStart(2,'0')}`;}
async function openTermUpdate(trip){
  const url=`/api/trips/${trip.id}/term/updates`,data=await api(url);let preview=null,reviewInput=null;const key=crypto.randomUUID();
  const locked=!!trip.archivedAt||!!trip.closedAt||!!data.blockedReason;
  const fields=Object.entries(termFieldLabels).map(([field,label])=>{
    const value=data.fields[field],date=['start','end','registrationDeadline'].includes(field);
    const input=field==='description'?`<textarea aria-label="${label}" data-term-update="${field}" maxlength="10000">${escapeHtml(value)}</textarea>`:field==='visibleToMembers'?`<select aria-label="${label}" data-term-update="${field}"><option value="true" ${value?'selected':''}>Ja</option><option value="false" ${!value?'selected':''}>Nein</option></select>`:`<input aria-label="${label}" data-term-update="${field}" type="${date?'datetime-local':field==='capacity'?'number':'text'}" value="${escapeHtml(date?termLocalDateTime(value):value)}" ${date?'step="1"':field==='capacity'?'min="1" max="1000000"':field==='title'?'maxlength="200"':'maxlength="500"'}>`;
    return `<label class="f"><span><input type="checkbox" data-term-change="${field}" ${locked?'disabled':''}> ${label} übertragen</span><div class="in">${input}</div></label>`;
  }).join('');
  showManagementModal('Termin feldweise ändern',`<p>Nur angehakte Felder werden übertragen. Eine Datumsänderung kann easyVerein-Benachrichtigungen und geänderte Teilnahmestatus auslösen.</p>${locked?'<p class="status-note is-error">Änderungen sind für diese Reise oder Umgebung gesperrt.</p>':''}<button type="button" class="btn btn-o" id="term-use-trip" ${locked?'disabled':''}>Gespeicherte Reisedaten einsetzen</button>${fields}<button type="button" class="btn btn-o" id="term-update-preview" ${locked?'disabled':''}>Änderungen prüfen</button><div id="term-update-review" aria-live="polite"></div>${reasonField()}<h3>Änderungsaufträge</h3>${data.jobs.map(j=>`<p>#${j.id} · ${escapeHtml(participationStateLabels[j.status]||j.status)} · ${escapeHtml(participationError(j.errorCode))}</p>`).join('')||'<p>Noch keine Änderungen beauftragt.</p>'}`,async()=>{
    if(locked)throw new Error('Änderungen sind gesperrt.');const changes=collect();
    if(!preview||JSON.stringify(changes)!==reviewInput)throw new Error('Bitte die aktuelle Auswahl zuerst prüfen.');
    await api(url+'/request',{method:'POST',body:JSON.stringify({operationKey:key,changes,reviewHash:preview.reviewHash,confirmed:true,reason:byId('action-reason').value})});await loadWorkflowStatus(state.currentTrip);
  },'Ausgewählte Änderungen beauftragen');
  function collect(){const changes={};byId('management-fields').querySelectorAll('[data-term-change]:checked').forEach(check=>{const field=check.dataset.termChange,value=byId('management-fields').querySelector(`[data-term-update="${field}"]`).value;changes[field]=field==='capacity'?Number(value):field==='visibleToMembers'?value==='true':['start','end','registrationDeadline'].includes(field)?(value?new Date(value).toISOString().replace('.000Z','Z'):null):value;});return changes;}
  byId('term-use-trip').onclick=()=>{const root=byId('management-fields');root.querySelector('[data-term-update="title"]').value=trip.name;root.querySelector('[data-term-change="title"]').checked=true;[['start',trip.startDate],['end',trip.endDate||trip.startDate]].forEach(([field,date])=>{const input=root.querySelector(`[data-term-update="${field}"]`);input.value=date+input.value.slice(10);root.querySelector(`[data-term-change="${field}"]`).checked=true;});};
  byId('term-update-preview').onclick=()=>workflowAction(async()=>{const changes=collect();preview=await api(url+'/preview',{method:'POST',body:JSON.stringify({changes})});reviewInput=JSON.stringify(changes);byId('term-update-review').innerHTML=`<div class="event-table-wrap"><table class="tb"><thead><tr><th>Feld</th><th>Aktuell extern</th><th>Gewählt</th></tr></thead><tbody>${Object.entries(preview.changes).map(([field,value])=>`<tr><td>${escapeHtml(termFieldLabels[field])}</td><td>${escapeHtml(valueText(preview.before[field]))}</td><td>${escapeHtml(valueText(value))}</td></tr>`).join('')}</tbody></table></div>`;});
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
