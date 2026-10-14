// Uses the existing session, tabs and accessible management dialog.
const bookingUI = {items: [], version: null, answers: {booking: {}, persons: {}}, operation: null, payload: null, tripId: null};
const bookingStatus = {pending:'In Bearbeitung',confirmed:'Bestätigt',partially_confirmed:'Teilweise bestätigt',cancelled:'Storniert'};
const syncStatus = {pending:'Übertragung ausstehend',local_only:'Lokal · keine externe Bestätigung',synced:'Synchronisiert',error:'Übertragungsfehler',unknown:'Klärung nötig'};
const answerStatus = {answered:'beantwortet',missing:'fehlt',not_applicable:'nicht anwendbar',not_asked:'nicht abgefragt',unchecked:'ungeprüft'};
const operationKey = () => crypto.randomUUID();
const valueText = v => v === null || v === undefined ? '' : typeof v === 'boolean' ? (v ? 'Ja' : 'Nein') : Array.isArray(v) ? v.join(', ') : String(v);
function bookingPeople() {return [...document.querySelectorAll('.manual-participant-row')].map((row,i)=>({id:`person_${i}`,name:row.querySelector('.manual-participant-name').value,birthDate:row.querySelector('.manual-participant-birthdate').value}));}
function readBookingInputs(root) {
  const answers = {booking:{},persons:{}};
  root.querySelectorAll('[data-booking-answer]').forEach(input => {
    const pid=input.dataset.person, fid=input.dataset.bookingAnswer;
    const target=pid==='booking'?answers.booking:(answers.persons[pid] ||= {});
    let value=input.value;
    if (input.dataset.type==='checkbox') {value=input.checked;}
    if (input.dataset.type==='yes_no') {value=value===''?null:value==='true';}
    if (input.dataset.type==='multi_select') {value=[...input.selectedOptions].map(x=>x.value);}
    target[fid]=value===''?null:value;
  });return answers;
}
function bookingInput(f,pid,values,visible=true) {
  const v=values?.[f.id]??null,id=`ba-${pid}-${f.id}`,attrs=`id="${id}" data-booking-answer="${f.id}" data-person="${pid}" data-type="${f.type}"`;
  if (['notice','section'].includes(f.type)) {return `<p>${escapeHtml(f.label)}</p>`;}
  let control;
  if(f.type==='checkbox'){control=`<input type="checkbox" ${attrs} ${v===true?'checked':''} ${f.required?'required':''}>`;}
  else if(f.type==='yes_no'){control=`<select ${attrs} ${f.required?'required':''}><option value="">Bitte wählen</option><option value="true" ${v===true?'selected':''}>Ja</option><option value="false" ${v===false?'selected':''}>Nein</option></select>`;}
  else if(['single_select','multi_select'].includes(f.type)){control=`<select ${attrs} ${f.type==='multi_select'?'multiple':''} ${f.required?'required':''}>${f.type==='single_select'?'<option value="">Bitte wählen</option>':''}${f.options.map(o=>`<option value="${o.id}" ${(Array.isArray(v)?v.includes(o.id):v===o.id)?'selected':''}>${escapeHtml(o.label)}</option>`).join('')}</select>`;}
  else if(f.type==='long_text'){control=`<textarea ${attrs} maxlength="${f.maxLength}" ${f.required?'required':''}>${escapeHtml(v??'')}</textarea>`;}
  else {control=`<input ${attrs} type="${f.type==='date'?'date':f.type==='number'?'number':'text'}" value="${escapeHtml(v??'')}" ${f.type==='number'?`step="${f.integer?'1':'0.000001'}"`:''} ${f.min!==null?`min="${escapeHtml(f.min)}"`:''} ${f.max!==null?`max="${escapeHtml(f.max)}"`:''} ${f.required?'required':''} ${['short_text','long_text'].includes(f.type)?`maxlength="${f.maxLength}"`:''}>`;}
  return visible?`<label class="f booking-answer" for="${id}"><span>${escapeHtml(f.label)} ${f.required?'*':''} ${escapeHtml(f.unit)}</span><span class="booking-control">${control}</span><small class="help">${escapeHtml(f.help)}</small></label>`:'';
}
function answerFields(version,people,answers,trip) {
  const age=p=>{if(!p.birthDate)return null;const birth=new Date(p.birthDate+'T00:00:00Z'), date=new Date(trip.startDate+'T00:00:00Z');return date.getUTCFullYear()-birth.getUTCFullYear()-(date.getUTCMonth()<birth.getUTCMonth()||(date.getUTCMonth()===birth.getUTCMonth()&&date.getUTCDate()<birth.getUTCDate())?1:0);};
  const child=p=>age(p)!==null&&age(p)<trip.adultAgeThreshold;
  const render=(scope,p=null)=>version.fields.filter(f=>f.scope===scope).map(f=>{
    const vis=f.visibility, pid=p?String(p.id):'booking', vals=p?(answers.persons[pid]||{}):answers.booking;
    let visible=true;
    if(vis.kind==='children')visible=people.some(child);
    if(vis.kind==='child')visible=child(p);
    if(vis.kind==='adult')visible=age(p)!==null&&!child(p);
    if(vis.kind==='selection'){const selected=vals[vis.fieldId]??answers.booking[vis.fieldId];visible=Array.isArray(selected)?selected.includes(vis.value):selected===vis.value;}
    return bookingInput(f,pid,vals,visible);
  }).join('');
  return `<h3>Zusatzangaben · ${version.number?'v'+version.number:'kein Zusatzformular'}</h3>${render('booking')}${people.map(p=>`<fieldset><legend>${escapeHtml(p.name||'Person')} · ${escapeHtml(String(p.id))}</legend>${render('person',p)}</fieldset>`).join('')}`;
}
function renderBookingIntake() {
  const root=byId('booking-extra-fields');
  const read=readBookingInputs(root);bookingUI.answers.booking={...bookingUI.answers.booking,...read.booking};for(const [p,v] of Object.entries(read.persons)){bookingUI.answers.persons[p]={...bookingUI.answers.persons[p],...v};}
  root.innerHTML=bookingUI.version?answerFields(bookingUI.version,bookingPeople(),bookingUI.answers,state.currentTrip):'<p class="help">Kein Zusatzformular.</p>';
}
async function loadBookingWorkspace(tripId) {
  if(bookingUI.tripId!==tripId){bookingUI.answers={booking:{},persons:{}};bookingUI.payload=null;bookingUI.operation=null;bookingUI.tripId=tripId;}
  const [items,binding]=await Promise.all([api(`/api/trips/${tripId}/bookings`),api(`/api/trips/${tripId}/form`)]);
  if(state.currentTripId!==tripId)return;
  bookingUI.items=items;bookingUI.version=binding.version;renderBookingIntake();
  document.querySelectorAll('[data-delete-registration]').forEach(btn=>{
    const b=items.find(x=>x.id===Number(btn.dataset.deleteRegistration));if(!b)return;
    const body=btn.closest('.reg-item')||btn.closest('[data-source]')||btn.closest('.reg-row').parentElement;
    const meta=document.createElement('div');meta.className='booking-meta';
    meta.innerHTML=`<span class="chip ${b.status==='confirmed'?'c-green':'c-amber'}">${bookingStatus[b.status]||b.status}</span> <span class="chip c-grey">${syncStatus[b.syncStatus]||b.syncStatus}</span> <small>${escapeHtml(b.reference)} · ${b.versionId?'Formular #'+b.versionId:'Altanmeldung ohne Zusatzformular'} ${b.supplementPending?'· Ergänzung erforderlich':''}</small>`;
    body.append(meta);
    const detail=document.createElement('button');detail.type='button';detail.className='btn btn-o btn-s';detail.textContent='Buchungsdetails';detail.onclick=()=>performManagement(()=>openBookingDetail(b.id));meta.append(detail);
    if(b.managed){btn.classList.add('d-none');}
  });
  byId('delete-registrations-btn').disabled=!!state.currentTrip.archivedAt&&!state.currentTrip.closedAt||items.some(b=>b.managed);
  byId('manual-registration-form').classList.toggle('d-none',!hasTripCapability('registration.manage')||!hasTripCapability('answers.correct'));
}
async function bookingCreate(event) {
  event.preventDefault();const form=event.currentTarget,button=form.querySelector('[type=submit]');button.disabled=true;
  try {
    const data={versionId:bookingUI.version?.id??null,persons:bookingPeople(),contactIdentity:byId('booking-contact').value,roomCategory:byId('manualRoomCategory').value||null,comment:byId('manualComment').value,answers:readBookingInputs(byId('booking-extra-fields'))};
    const serialized=JSON.stringify(data);if(bookingUI.payload!==serialized){bookingUI.operation=operationKey();bookingUI.payload=serialized;}
    await api(`/api/trips/${state.currentTripId}/bookings`,{method:'POST',body:JSON.stringify({...data,operationKey:bookingUI.operation})});
    bookingUI.operation=null;bookingUI.payload=null;bookingUI.answers={booking:{},persons:{}};form.reset();byId('booking-intake-error').textContent='Lokal gespeichert · externe Teilnahme ausstehend.';await loadRegistrations(String(state.currentTripId));await loadAnalyticsWorkspace();
  } catch(error){byId('booking-intake-error').textContent=`Fehler: ${error.message}`;byId('booking-intake-error').focus();}
  finally {button.disabled=false;applyTripWriteControls();}
}
async function openBookingDetail(id) {
  const tripId=state.currentTripId,b=await api(`/api/trips/${tripId}/bookings/${id}`),binding=await api(`/api/trips/${tripId}/form`);
  const definition=b.formVersion;
  const labels=new Map((definition?.fields||[]).map(f=>[f.id,f.label]));
  const history=(b.revisions||[]).map(a=>`<li>${escapeHtml(labels.get(a.fieldId)||a.fieldId)} · Person ${a.participantId??'Buchung'} · v#${a.versionId} · ${escapeHtml(answerStatus[a.state])}: ${escapeHtml(valueText(a.value))}<br><small>${escapeHtml(a.createdAt)} · Akteur #${a.actorId} · ${escapeHtml(a.reason)}</small></li>`).join('');
  showManagementModal(`Buchung ${b.reference}`,`<p><span class="chip c-amber">${bookingStatus[b.status]}</span> <span class="chip c-grey">${syncStatus[b.syncStatus]}</span></p><p>${escapeHtml(b.contactIdentity || 'Kontakt nicht erfasst')} · ${escapeHtml(b.source)} · ${b.versionId?'Formular #'+b.versionId:'Altanmeldung ohne Zusatzformular'} ${b.supplementPending?'· Ergänzung erforderlich':''}</p><p>Zimmerwunsch: ${escapeHtml(b.roomCategory||'Ohne Unterkunft')} · Zuteilung: ${escapeHtml(b.roomAssignment||'offen')}</p><div class="booking-persons">${b.persons.map(p=>`<div><b>${escapeHtml(p.name)}</b> · #${p.id} · ${escapeHtml(p.birthDate)}<br><span class="chip c-grey">${bookingStatus[p.status]}</span> <span class="chip c-grey">${syncStatus[p.syncStatus]}</span></div>`).join('')}</div><h3>Aktuelle Antworten</h3>${(b.answers||[]).map(a=>`<p><b>${escapeHtml(labels.get(a.fieldId)||a.fieldId)}</b> · ${a.participantId?'Person #'+a.participantId:'pro Buchung'}<br>${escapeHtml(answerStatus[a.state])}: ${escapeHtml(valueText(a.value))}</p>`).join('')||'<p>Keine lesbaren Zusatzangaben.</p>'}<details><summary>Personenkorrekturen (${b.personRevisions?.length??0})</summary>${(b.personRevisions||[]).map(r=>`<p>Person #${r.participant_id} · ${escapeHtml(r.created_at)} · ${escapeHtml(r.reason)}<br>${escapeHtml(r.before_json)} → ${escapeHtml(r.after_json)}</p>`).join('')}</details><details><summary>Antwortrevisionen (${b.revisions?.length??0})</summary><ol>${history}</ol></details><div class="event-toolbar">${!state.currentTrip.archivedAt&&hasTripCapability('answers.correct')&&hasTripCapability('answers.view')&&b.status!=='cancelled'?'<button type="button" class="btn btn-o" id="booking-correct">Antworten korrigieren / ergänzen</button>':''}${hasTripCapability('registration.manage')&&!state.currentTrip.archivedAt&&!state.currentTrip.closedAt&&b.status!=='cancelled'?'<button type="button" class="btn btn-o" id="booking-transmit">Teilnahmeauftrag vorbereiten</button><button type="button" class="btn btn-o" id="booking-cancel">Personen stornieren</button><button type="button" class="btn btn-o" id="booking-room">Zimmer zuweisen</button>':''}</div>`,async()=>{},'Schließen');
  if(byId('booking-transmit')){byId('booking-transmit').disabled=!hasTripCapability('term.manage');byId('booking-transmit').onclick=()=>performManagement(()=>openParticipationRequest(b));}
  byId('booking-correct')?.addEventListener('click',()=>performManagement(()=>editBooking(b,binding)));
  byId('booking-cancel')?.addEventListener('click',()=>{
    const key=operationKey();showManagementModal('Personen stornieren',reasonField()+b.persons.filter(p=>p.status!=='cancelled').map(p=>`<label><input type="checkbox" name="cancel-person" value="${p.id}"> ${escapeHtml(p.name)}</label>`).join('')+'<p>Buchungsmengen werden ungeprüft. Historische Antworten bleiben erhalten.</p>',async()=>{
      await api(`/api/trips/${tripId}/bookings/${id}/cancel`,{method:'POST',body:JSON.stringify({operationKey:key,revision:b.revision,reason:byId('action-reason').value,personIds:[...document.querySelectorAll('[name=cancel-person]:checked')].map(x=>Number(x.value))})});await loadRegistrations(String(tripId));await loadAnalyticsWorkspace();
    },'Stornieren');
  });
  byId('booking-room')?.addEventListener('click',()=>{
    const key=operationKey();showManagementModal('Zimmer zuweisen',reasonField()+textField('booking-room-value','Zuteilung',b.roomAssignment),async()=>{await api(`/api/trips/${tripId}/bookings/${id}/room`,{method:'PUT',body:JSON.stringify({operationKey:key,revision:b.revision,reason:byId('action-reason').value,roomAssignment:byId('booking-room-value').value})});await loadRegistrations(String(tripId));});
  });
}
async function editBooking(b,binding) {
  let versions=[];
  const detail=await api(`/api/trips/${b.tripId}/bookings/${b.id}`);
  const stored=detail.formVersion;
  if(stored)versions.push(stored);if(binding.version&&!versions.some(v=>v.id===binding.version.id))versions.push(binding.version);
  if(!versions.length)versions.push({id:null,number:0,fields:[]});
  const values={booking:{},persons:{}};for(const a of b.answers||[]){if(['answered','unchecked'].includes(a.state)){const target=a.participantId===null?values.booking:(values.persons[String(a.participantId)] ||= {});target[a.fieldId]=a.value;}}
  const people=b.persons.filter(p=>p.status!=='cancelled');let v=versions[0],answers=values;const key=operationKey();
  const canEditPeople=hasTripCapability('registration.manage')&&!state.currentTrip.archivedAt&&!state.currentTrip.closedAt;
  const personFields=canEditPeople?`<details><summary>Personen korrigieren (IDs bleiben erhalten)</summary>${people.map(p=>textField('person-name-'+p.id,'Name · #'+p.id,p.name)+textField('person-birth-'+p.id,'Geburtsdatum',p.birthDate,'date')).join('')}</details>`:'';
  const currentPeople=()=>canEditPeople?people.map(p=>({...p,name:byId('person-name-'+p.id).value,birthDate:byId('person-birth-'+p.id).value})):people;
  showManagementModal('Antworten korrigieren',reasonField()+personFields+`<label class="f">Formularstand ausdrücklich bestätigen<select id="booking-correction-version">${versions.map(x=>`<option value="${x.id}">${x.number?'v'+x.number:'Ohne Zusatzformular'}${x.id===b.versionId?' · gespeicherter Stand':' · Ergänzung auf aktiven Stand'}</option>`).join('')}</select></label><div id="booking-correction-fields">${answerFields(v,people,answers,state.currentTrip)}</div>`,async()=>{await api(`/api/trips/${b.tripId}/bookings/${b.id}/answers`,{method:'PUT',body:JSON.stringify({operationKey:key,revision:b.revision,versionId:v.id,reason:byId('action-reason').value,answers:readBookingInputs(byId('booking-correction-fields')),...(canEditPeople?{persons:currentPeople().map(p=>({id:p.id,name:p.name,birthDate:p.birthDate}))}:{})})});await loadRegistrations(String(b.tripId));await loadAnalyticsWorkspace();});
  const refresh=()=>{const read=readBookingInputs(byId('booking-correction-fields'));answers.booking={...answers.booking,...read.booking};for(const [pid,vals] of Object.entries(read.persons)){answers.persons[pid]={...answers.persons[pid],...vals};}v=versions.find(x=>String(x.id)===byId('booking-correction-version').value);byId('booking-correction-fields').innerHTML=answerFields(v,currentPeople(),answers,state.currentTrip);};
  byId('booking-correction-version').onchange=refresh;byId('booking-correction-fields').addEventListener('change',refresh);if(canEditPeople){people.forEach(p=>byId('person-birth-'+p.id).addEventListener('change',refresh));}
}
byId('booking-extra-fields').addEventListener('change',renderBookingIntake);
byId('manual-participants-container').addEventListener('change',renderBookingIntake);
byId('add-participant-row-btn').addEventListener('click',renderBookingIntake);
byId('remove-participant-row-btn').addEventListener('click',renderBookingIntake);
const analyticsUI={path:null,query:{},data:null,page:1,tripIds:null,sequence:0};
function analyticsControls(event=false) {
  return `<div class="event-toolbar"><label class="f">Grundgesamtheit<select id="analytics-status"><option value="confirmed">Bestätigt & aktiv</option><option value="all">Inkl. offen & storniert</option><option value="pending">In Bearbeitung</option><option value="cancelled">Storniert</option></select></label><label class="f">Version<select id="analytics-version"><option value="">Alle Versionen</option></select></label><label class="f">Feld<select id="analytics-field"><option value="">Alle Felder</option></select></label><label class="f">Antwortzustand<select id="analytics-state"><option value="">Alle</option>${Object.entries(answerStatus).map(([v,l])=>`<option value="${v}">${l}</option>`).join('')}</select></label><label class="f">Suche<input id="analytics-search" type="search" maxlength="200"></label>${event?'<fieldset><legend>Ausgewählte Reisen</legend><div id="analytics-trips">Wird geladen …</div></fieldset>':''}<button type="button" class="btn btn-p" id="analytics-apply">Anzeigen</button></div><p id="analytics-error" role="alert" tabindex="-1"></p><div id="analytics-results"></div>`;
}
async function loadAnalyticsWorkspace() {
  if(!hasTripCapability('analytics.view')||!hasTripCapability('answers.view')){byId('analytics-workspace').textContent='Für Zusatzangaben und Auswertung fehlen die Rechte.';return;}
  analyticsUI.path=`/api/trips/${state.currentTripId}/analytics`;analyticsUI.query={};analyticsUI.page=1;
  byId('analytics-workspace').innerHTML=analyticsControls();wireAnalytics();await fetchAnalytics();
}
function wireAnalytics() {
  byId('analytics-apply').onclick=()=>performManagement(async()=>{analyticsUI.query={};for(const [id,key] of [['status','status'],['version','versionId'],['field','fieldId'],['state','state'],['search','search']]){const input=byId(`analytics-${id}`);if(input?.value)analyticsUI.query[key]=input.value;}if(byId('analytics-trips'))analyticsUI.query.tripIds=[...byId('analytics-trips').querySelectorAll('input:checked')].map(x=>x.value).join(',');analyticsUI.page=1;await fetchAnalytics();});
}
function analyticsUrl(extra={}) {return analyticsUI.path+'?'+new URLSearchParams({...analyticsUI.query,...extra});}
async function fetchAnalytics() {
  try {
    const sequence=++analyticsUI.sequence;const data=await api(analyticsUrl({page:analyticsUI.page}));if(sequence!==analyticsUI.sequence||!byId('analytics-results'))return;analyticsUI.data=data;byId('analytics-error').textContent='';
    if(byId('analytics-trips')&&analyticsUI.query.tripIds===undefined){byId('analytics-trips').innerHTML=data.tripMetrics.map(t=>`<label><input type="checkbox" value="${t.tripId}" checked> ${escapeHtml(management.event?.trips.find(x=>x.id===t.tripId)?.name||'Reise #'+t.tripId)}</label>`).join('')||'<p>Keine für Antworten freigegebenen Reisen.</p>';}
    const version=byId('analytics-version'),field=byId('analytics-field');
    if(!analyticsUI.query.versionId)version.innerHTML='<option value="">Alle Versionen</option>'+data.versions.map(v=>`<option value="${v}">${v?'Formular #'+v:'Ohne Formular'}</option>`).join('');
    if(!analyticsUI.query.fieldId)field.innerHTML='<option value="">Alle Felder</option>'+data.metrics.map(m=>`<option value="${m.field.id}">${escapeHtml(m.field.label)} · ${m.field.scope==='person'?'Person':'Buchung'}</option>`).join('');
    renderAnalytics(data);
  }catch(error){byId('analytics-error').textContent=`Fehler: ${error.message}`;byId('analytics-results').innerHTML='';}
}
function metricHtml(m) {
  const f=m.field,c=m.counts;
  let value=f.type==='number'?`${m.sum} ${escapeHtml(f.unit)}`:['checkbox','yes_no'].includes(f.type)?`${m.yes} von ${m.denominator}`:['single_select','multi_select'].includes(f.type)?m.options.map(o=>`${escapeHtml(o.label)}: ${o.count}`).join(' · '):'Antwortliste';
  return `<article class="kpi"><h3>${escapeHtml(f.label)}</h3><span>${escapeHtml(formTypeLabels[f.type])} · ${f.scope==='person'?'pro Person':'pro Buchung'}</span><b>${value}</b><small>${c.answered} von ${m.denominator} ${f.scope==='person'?'Personen':'Buchungen'} beantwortet · ${c.missing} fehlt · ${c.unchecked} ungeprüft</small>${['checkbox','yes_no'].includes(f.type)?`<small>${m.yes} Ja / angehakt · ${m.no} Nein / nicht angehakt</small>`:''}<small>${c.not_applicable} nicht anwendbar · ${c.not_asked} nicht abgefragt</small>${f.type==='number'?`<small>Ungeprüfte Summe: ${m.uncheckedSum} ${escapeHtml(f.unit)} (separat)</small>`:''}${f.type==='multi_select'?'<small>Mehrfachauswahl: Summe kann größer als Grundgesamtheit sein.</small>':''}<button type="button" class="btn btn-o" data-drilldown="${f.id}">Antworten anzeigen →</button></article>`;
}
function renderAnalytics(data) {
  const canExport=analyticsUI.path.includes('/events/')||hasTripCapability('answers.export');
  byId('analytics-results').innerHTML=`<p class="${data.filters.status==='confirmed'?'help':'booking-warning'}">${escapeHtml(data.populationLabel)}</p><p class="help">${escapeHtml(data.totalLabel)} · Stand ${escapeHtml(data.stand)}</p><p class="help">Filter: ${escapeHtml(JSON.stringify(data.filters))}</p><div class="event-toolbar"><button type="button" class="btn btn-o" data-analytics-export="csv" ${!canExport||!data.totalRows?'disabled':''}>CSV</button><button type="button" class="btn btn-o" data-analytics-export="print" ${!canExport||!data.totalRows?'disabled':''}>Druckansicht</button><button type="button" class="btn btn-o" data-analytics-export="revisions" ${!canExport||!data.totalRows?'disabled':''}>Revisionen CSV</button></div><div class="analytics-grid">${data.metrics.map(metricHtml).join('')||'<p>Noch kein veröffentlichtes Antwortfeld vorhanden.</p>'}</div>${analyticsUI.path.includes('/events/')?`<details><summary>Einzelne Reisen</summary>${data.tripMetrics.map(t=>`<h3>Reise #${t.tripId}</h3><div class="analytics-grid">${t.metrics.map(metricHtml).join('')}</div>`).join('')}</details>`:''}<h3>Antworten · ${data.totalRows} Einträge</h3>${data.totalRows?`<div class="analytics-table"><table class="tb"><thead><tr><th>Buchung / Person</th><th>Version / Status</th><th>Feld</th><th>Antwort / Zustand</th></tr></thead><tbody>${data.rows.map(r=>`<tr><td>${escapeHtml(r.reference)} · Reise #${r.tripId}<br>${escapeHtml(r.person)} ${r.participantId?'· #'+r.participantId:''}</td><td>${r.versionId?'#'+r.versionId:'ohne Formular'}<br>${escapeHtml(bookingStatus[r.status]||r.status)} · ${escapeHtml(syncStatus[r.syncStatus]||r.syncStatus)}<br>${escapeHtml(r.source)}</td><td>${escapeHtml(r.label)}<br>${escapeHtml(r.unit)}</td><td>${escapeHtml(valueText(r.value))}<br>${escapeHtml(answerStatus[r.state])}<br><small>${escapeHtml(r.answerAt||'')}</small></td></tr>`).join('')}</tbody></table></div><div class="event-toolbar"><button type="button" class="btn btn-o" id="analytics-prev" ${data.page===1?'disabled':''}>Zurück</button><span>Seite ${data.page}</span><button type="button" class="btn btn-o" id="analytics-next" ${data.page*data.pageSize>=data.totalRows?'disabled':''}>Weiter</button></div>`:'<p class="empty-state">Keine Antworten in dieser Grundgesamtheit. Lokale Eingänge sind noch keine bestätigten Teilnahmen; wähle bei Bedarf „Inkl. offen & storniert“.</p>'}`;
  byId('analytics-prev')?.addEventListener('click',()=>{analyticsUI.page--;fetchAnalytics();});byId('analytics-next')?.addEventListener('click',()=>{analyticsUI.page++;fetchAnalytics();});
  document.querySelectorAll('[data-drilldown]').forEach(btn=>btn.onclick=()=>{byId('analytics-field').value=btn.dataset.drilldown;analyticsUI.query.fieldId=btn.dataset.drilldown;analyticsUI.page=1;fetchAnalytics();});
  document.querySelectorAll('[data-analytics-export]').forEach(btn=>btn.onclick=async()=>{
    const format=btn.dataset.analyticsExport;let response;
    try {response=await fetch(analyticsUrl({format}),{credentials:'same-origin'});if(!response.ok){throw new Error(`Export verweigert (${response.status})`);}const blob=await response.blob(),url=URL.createObjectURL(blob);const link=document.createElement('a');link.href=url;if(format==='print'){link.target='_blank';link.rel='noopener';}else{link.download=format==='revisions'?'antwortrevisionen.csv':'antworten.csv';}link.click();setTimeout(()=>URL.revokeObjectURL(url),60000);}catch(error){byId('analytics-error').textContent=error.message;}
  });
}
byId('event-analytics-open')?.addEventListener('click',()=>performManagement(async()=>{
  showManagementModal('Veranstaltungsauswertung',analyticsControls(true),async()=>{},'Schließen');analyticsUI.path=`/api/events/${management.event.id}/analytics`;analyticsUI.query={};analyticsUI.page=1;wireAnalytics();await fetchAnalytics();
}));
