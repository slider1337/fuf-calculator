// Central editor/preview: server schemas and validation, no handoff demo runtime.
const formsUI = {eventId:null, versions:[], current:null, selected:0, snapshot:'', mode:'editor', mobile:'list', answers:{booking:{},persons:{}}, preview:null, previewSeq:0};
const formTypeLabels = {number:'Zahl',checkbox:'Checkbox',yes_no:'Ja/Nein',single_select:'Einzelauswahl',multi_select:'Mehrfachauswahl',short_text:'Kurzer Text',long_text:'Langer Text',date:'Datum',notice:'Hinweis',section:'Abschnitt'};
const formImpactLabels = {not_asked_in_older_versions:'In älteren Versionen nicht abgefragt · eigene Serie',historical_answers_preserved:'Historische Antworten bleiben lesbar',same_series:'Gleiche Feldserie · gemeinsam auswertbar',separate_series:'Getrennte Feldserie'};
async function performFormOperation(operation) {
  try {setStatusNote('management-status','',null);await operation();}
  catch(error){
    let payload=null;try{payload=JSON.parse(error.message);}catch{}
    if(payload?.error==='revision_conflict') {managementError(new Error('Der Stand wurde inzwischen geändert. Deine Eingaben bleiben erhalten. Lade den aktuellen Stand bewusst neu.'));return;}
    if(payload?.details && formsUI.current && !byId('event-detail-section').classList.contains('d-none')) {
      const entries=Object.entries(payload.details);const first=entries.map(([key])=>key.match(/^fields\.(\d+)/)).find(Boolean);
      if(first){formsUI.selected=Number(first[1]);formsUI.mode='editor';formsUI.mobile='edit';renderFormWorkspace();}
      const labels={options_required:'Mindestens eine Option erforderlich',previous_compatible_selection_required:'Wähle ein früheres passendes Auswahlfeld',invalid_condition_value:'Wähle eine gültige Bedingungsoption',invalid_selection_bounds:'Prüfe die Auswahlgrenzen',decimal_or_integer_required:'Gültige Zahl mit Punkt und bis zu sechs Dezimalstellen erforderlich',below_min:'Maximum darf nicht unter Minimum liegen',incompatible_mapping:'Diese Zuordnung passt nicht zu Typ und Geltung',invalid_text:'Beschriftung und Textlänge prüfen'};
      entries.forEach(([key,message])=>{
        const m=key.match(/^fields\.(\d+)\.(\w+)/);if(!m||Number(m[1])!==formsUI.selected){return;}
        const input=byId('form-field-settings')?.querySelector(`[data-field-key="${m[2]}"]`);if(input){input.setAttribute('aria-invalid','true');const p=document.createElement('p');p.className='form-field-error';p.textContent=labels[message]||'Einstellung prüfen: '+message;input.closest('.f')?.append(p);}
      });
      managementError(new Error(`${entries.length} Formulareinstellungen prüfen. Der Entwurf wurde nicht überschrieben.`));byId('form-field-settings')?.querySelector('[aria-invalid="true"]')?.focus();return;
    }
    managementError(error);
  }
}
function formDirty() { return formsUI.eventId === management.event?.id && !byId('event-detail-section').classList.contains('d-none') && formsUI.current?.status === 'draft' && JSON.stringify(formsUI.current.fields) !== formsUI.snapshot; }
function formWrite() { return formsUI.current?.status === 'draft' && management.event?.capabilities.includes('form.manage') && !management.event.archived; }
function formBase() { return `/api/events/${formsUI.eventId}/forms`; }
function formButton(action,label,disabled=false,extra='') { return `<button type="button" class="btn btn-o" data-form-action="${action}" ${disabled?'disabled':''} ${extra}>${label}</button>`; }
function formControl(key,label,value,type='text',extra='') { return `<label class="f">${label}<div class="in"><input data-field-key="${key}" type="${type}" value="${escapeHtml(String(value ?? ''))}" ${extra}></div></label>`; }
function formSelect(key,label,value,options) { return `<label class="f">${label}<div class="in"><select data-field-key="${key}">${options.map(([v,l])=>`<option value="${escapeHtml(String(v))}" ${String(value)===String(v)?'selected':''}>${escapeHtml(l)}</option>`).join('')}</select></div></label>`; }
async function loadFormWorkspace(force=false) {
  const e = management.event;
  if (!e.capabilities.includes('event.view')) { byId('form-workspace').innerHTML = '<div class="empty-state">Formularansicht nicht freigegeben.</div>'; return; }
  if (formsUI.eventId === e.id && !force) { renderFormWorkspace(); return; }
  const versions = await api(`/api/events/${e.id}/forms`);
  Object.assign(formsUI,{eventId:e.id,versions,current:versions.find(v=>v.status==='draft') || versions[0] || null,selected:0,mode:'editor',mobile:'list',answers:{booking:{},persons:{}},preview:null});
  formsUI.snapshot = JSON.stringify(formsUI.current?.fields || []); renderFormWorkspace();
}
function renderFormWorkspace() {
  const v = formsUI.current; const editable = management.event.capabilities.includes('form.manage') && !management.event.archived;
  if (!v) {
    byId('form-workspace').innerHTML = `<div class="empty-state"><h2>Noch kein Zusatzformular</h2><p>Zusatzfragen sind optional. Lege nur die für diese Veranstaltung benötigten Felder an.</p>${editable?formButton('create','Formular anlegen'):''}</div>`; return;
  }
  const publish = v.status==='draft' && management.event.capabilities.includes('form.publish') && !management.event.archived;
  byId('form-workspace').innerHTML = `<section class="sec"><div class="sec-h"><h2>Anmeldeformular</h2><span class="chip ${v.status==='draft'?'c-amber':v.status==='published'?'c-green':'c-grey'}">${v.number?'Version '+v.number:'Entwurf'} · ${v.status==='draft'?'Entwurf':v.status==='published'?'veröffentlicht':'archiviert'}</span></div><div class="sec-b"><p class="help">${v.basedOnId?'Basiert auf Formular #'+v.basedOnId:'Erste Fassung'} · Revision ${v.revision}</p><div class="event-toolbar form-toolbar">${formButton('editor','Felder')}${formButton('versions','Versionen & Vergleich')}${formButton('preview',formDirty()?'Speichern & Vorschau':'Vorschau',formDirty()&&!formWrite())}${formWrite()?formButton('save','Entwurf speichern'):''}${publish?formButton('publish','Veröffentlichen …',formDirty()):''}${formButton('reload','Neu laden')}<span class="chip c-amber ${formDirty()?'':'d-none'}" id="form-dirty">Ungespeicherte Änderungen</span></div></div></section><div id="form-mode-content"></div><div class="form-mobile-actions">${formButton('editor','Felder')}${formButton('preview',formDirty()?'Speichern & Vorschau':'Vorschau',formDirty()&&!formWrite())}${formWrite()?formButton('save','Speichern'):''}</div>`;
  if (formsUI.mode === 'versions') { renderFormVersions(); }
  else if (formsUI.mode === 'preview') { renderFormPreviewShell(); }
  else { renderFormEditor(); }
}
function renderFormEditor() {
  const v=formsUI.current; const write=formWrite(); const f=v.fields[formsUI.selected];
  byId('form-mode-content').innerHTML = `<div class="form-editor-grid" data-mobile-step="${formsUI.mobile}"><section class="sec form-field-list"><div class="sec-h"><h3>Felder · ${v.fields.length}</h3></div><div class="sec-b">${v.fields.map((x,i)=>`<div class="form-field-row ${i===formsUI.selected?'selected':''}"><button type="button" class="form-field-select" data-form-select="${i}"><strong>${escapeHtml(x.label)}</strong><span class="help">${formTypeLabels[x.type]} · ${x.scope==='person'?'pro Person':'pro Buchung'}</span></button><div class="form-order">${formButton('up','↑',!write||i===0,`data-index="${i}" aria-label="Feld ${i+1} nach oben"`)}${formButton('down','↓',!write||i===v.fields.length-1,`data-index="${i}" aria-label="Feld ${i+1} nach unten"`)}</div></div>`).join('') || '<p>Noch keine Felder.</p>'}${write?formButton('add','Feld hinzufügen'):''}<p class="help">Reihenfolge = Anzeige. Entfernte Felder bleiben in älteren Versionen lesbar.</p></div></section><section class="sec form-field-config"><div class="sec-h"><h3>Feld bearbeiten</h3>${formButton('list','Zur Feldliste')}</div><div class="sec-b" id="form-field-settings">${f?fieldSettings(f):'<p>Wähle ein Feld oder füge eines hinzu.</p>'}</div></section><section class="sec form-mini-preview"><div class="sec-h"><h3>Vorschau</h3></div><div class="sec-b"><p>Familie mit zwei Erwachsenen und zwei Kindern oder nur Erwachsene. Alle Antworten werden serverseitig geprüft.</p>${formButton('preview',formDirty()?'Speichern & Vorschau':'Ganze Vorschau öffnen')}<p class="help">Die Vorschau legt keine Anmeldung an.</p><div id="form-inline-preview"></div></div></section></div>`;
  renderInlineFormPreview();
  if (!write) { byId('form-field-settings').querySelectorAll('input,select,textarea,button').forEach(e=>e.disabled=true); }
}
async function renderInlineFormPreview() {
  const target=byId('form-inline-preview'); const v=formsUI.current; const eventId=formsUI.eventId;
  if(!target){return;}
  if(formDirty()){target.textContent='Speichere den Entwurf für eine aktuelle Vorschau.';return;}
  try {
    const result=await api(`${formBase()}/${v.id}/preview`,{method:'POST',body:JSON.stringify({context:{startDate:'2026-12-20',adultAgeThreshold:18,persons:[{id:'adult1',birthDate:'1986-01-01'},{id:'child1',birthDate:'2017-01-01'}]},answers:{}})});
    if(formsUI.current?.id!==v.id || formsUI.eventId!==eventId || !target.isConnected){return;}
    target.innerHTML=previewHtml(result,{});target.querySelectorAll('input,select,textarea').forEach(e=>e.disabled=true);
  } catch(error){if(target.isConnected){target.textContent=`Vorschau konnte nicht geladen werden: ${error.message}`;}}
}
function fieldSettings(f) {
  const isDisplay=['notice','section'].includes(f.type); const choice=['single_select','multi_select'].includes(f.type);
  const prior=formsUI.current.fields.slice(0,formsUI.selected).filter(x=>x.id && ['single_select','multi_select','checkbox','yes_no'].includes(x.type) && !(f.scope==='booking' && x.scope==='person'));
  const v=f.visibility; const source=prior.find(x=>x.id===v.fieldId);
  const visibility=[['always','Immer'],['children','Wenn Kinder angemeldet sind'],...(f.scope==='person'?[['child','Wenn Person Kind ist'],['adult','Wenn Person erwachsen ist']]:[]),['selection','Wenn vorherige Auswahl …']];
  const mappingOptions=[['','Keine'],...Object.entries({vegetarianMeals:'Vegetarische Essen',roomPreference:'Zimmerwunsch',arrivalDate:'Anreisedatum'}).filter(([key])=>({vegetarianMeals:['number','checkbox'],roomPreference:['single_select'],arrivalDate:['date']}[key]).includes(f.type))];
  return `${formControl('id','Fachliche ID',f.id||'Wird beim Speichern vergeben','text','readonly')}${formSelect('type','Feldtyp',f.type,Object.entries(formTypeLabels))}${formControl('label','Beschriftung',f.label,'text','maxlength="200" required')}<label class="f">Hilfetext / Hinweis<div class="in"><textarea data-field-key="help" maxlength="2000" rows="3">${escapeHtml(f.help||'')}</textarea></div></label>${formSelect('scope','Geltungsbereich',f.scope,[['booking','Pro Buchung'],['person','Pro Person']])}${!isDisplay?`<label class="form-check-line"><input type="checkbox" data-field-key="required" ${f.required?'checked':''}> ${f.type==='checkbox'?'Muss angehakt sein':f.type==='yes_no'?'Antwort erforderlich (Ja oder Nein)':'Antwort erforderlich'}</label><label class="form-check-line"><input type="checkbox" data-field-key="analytics" ${f.analytics?'checked':''}> In Auswertung zeigen</label>`:''}${formSelect('visibility','Sichtbar',v.kind,visibility)}${v.kind==='selection'?formSelect('conditionField','Vorheriges Feld',v.fieldId||'',[['','Feld wählen'],...prior.map(x=>[x.id,x.label])])+formSelect('conditionValue','Auswahl',String(v.value ?? ''),[['','Wert wählen'],...(source ? ['checkbox','yes_no'].includes(source.type)?[['true','Ja / angehakt'],['false','Nein / nicht angehakt']]:source.options.map(o=>[o.id,o.label]):[])]):''}<p class="help">Verborgene Felder gelten als nicht anwendbar, nicht als fehlend.</p>${f.type==='number'?`${formSelect('integer','Art',String(f.integer),[['true','Ganzzahl'],['false','Dezimalzahl (bis 6 Stellen)']])}${formControl('unit','Einheit',f.unit,'text','maxlength="50"')}${formControl('min','Minimum',f.min)}${formControl('max','Maximum',f.max)}${formControl('default','Startwert',f.default)}<p class="help">Leer ist nicht 0. Dezimalwerte werden exakt gespeichert.</p>`:''}${['short_text','long_text'].includes(f.type)?formControl('maxLength','Maximale Zeichen',f.maxLength,'number','min="1" max="10000"'):''}${f.type==='date'?formControl('min','Frühestes Datum',f.min,'date')+formControl('max','Spätestes Datum',f.max,'date'):''}${choice?`<h4>Optionen</h4>${f.options.map((o,i)=>`<div class="form-option"><label class="f">Option ${i+1}<div class="in"><input data-option-label="${i}" value="${escapeHtml(o.label)}" maxlength="200"></div><span class="help">${escapeHtml(o.id||'ID beim Speichern')}</span></label><label class="form-check-line"><input type="checkbox" data-option-semantic="${i}" ${o.semanticChange?'checked':''}> Bedeutung geändert</label>${formButton('remove-option','Entfernen',false,`data-index="${i}"`)}</div>`).join('')}${formButton('add-option','Option hinzufügen')}${f.type==='multi_select'?formControl('minSelections','Mindestens Auswahlen',f.minSelections,'number','min="0"')+formControl('maxSelections','Höchstens Auswahlen',f.maxSelections,'number','min="0"'):''}`:''}${!isDisplay?formSelect('mapping','Zuordnung zur Kalkulation',f.mapping||'',mappingOptions):''}<p class="help">Nur feste Ziele. Der Preis ändert sich erst bei einer späteren bewussten Übernahme in die Planung.</p><label class="form-check-line"><input type="checkbox" data-field-key="semanticChange" ${f.semanticChange?'checked':''}> Bedeutung geändert → neue Feldserie</label><p class="help">Typ, Einheit und Geltungsbereich erzeugen automatisch eine neue ID. Textkorrekturen gleicher Bedeutung behalten die ID.</p>${formButton('remove','Feld entfernen')}`;
}
function markFormDirty() {
  byId('form-dirty')?.classList.toggle('d-none',!formDirty());
  document.querySelector('[data-form-action="publish"]')?.toggleAttribute('disabled',formDirty());
  if(formDirty() && byId('form-inline-preview')){byId('form-inline-preview').textContent='Speichere den Entwurf für eine aktuelle Vorschau.';}
  const x=document.querySelector(`[data-form-select="${formsUI.selected}"] strong`); if(x){x.textContent=formsUI.current.fields[formsUI.selected].label;}
}
async function saveFormDraft() {
  const v=formsUI.current;
  const saved=await api(`${formBase()}/${v.id}`,{method:'PUT',body:JSON.stringify({revision:v.revision,fields:v.fields})});
  formsUI.current=saved; formsUI.snapshot=JSON.stringify(saved.fields); formsUI.versions=formsUI.versions.map(x=>x.id===saved.id?saved:x); formsUI.answers={booking:{},persons:{}}; formsUI.preview=null;
  renderFormWorkspace(); return saved;
}
function formVersionLabel(v) { return `${v.number?'v'+v.number:'Entwurf'} · ${v.status==='archived'?'archiviert':v.status==='draft'?'Entwurf':'veröffentlicht'}`; }
function renderFormVersions() {
  const versions=formsUI.versions; const draft=versions.some(v=>v.status==='draft');
  byId('form-mode-content').innerHTML=`<section class="sec"><div class="sec-h"><h3>Versionshistorie</h3></div><div class="sec-b form-history">${versions.map(v=>`<article class="event-card"><h4>${formVersionLabel(v)}</h4><p>${escapeHtml(v.changeDescription || 'Noch keine Veröffentlichung')}</p><p class="help">${v.publishedAt?escapeHtml(v.publishedAt)+' · Herausgeber #'+v.publishedBy:'Angelegt '+escapeHtml(v.createdAt)+' · Akteur #'+v.createdBy}</p>${formButton('open-version','Definition öffnen',false,`data-id="${v.id}"`)}${v.status!=='draft' && management.event.capabilities.includes('form.manage')?formButton('copy','Als neuen Entwurf kopieren',draft||management.event.archived,`data-id="${v.id}"`):''}${v.status==='published' && management.event.capabilities.includes('form.publish')?formButton('archive','Archivieren',management.event.archived,`data-id="${v.id}"`):''}</article>`).join('')}<p class="help">Höchstens ein offener Entwurf. Archivierte Definitionen bleiben lesbar; bestehende Reisen behalten ihre Version.</p><div class="event-toolbar"><label class="f">Von<div class="in"><select id="form-compare-from">${versions.map(v=>`<option value="${v.id}">${formVersionLabel(v)}</option>`).join('')}</select></div></label><label class="f">Nach<div class="in"><select id="form-compare-to">${versions.map(v=>`<option value="${v.id}">${formVersionLabel(v)}</option>`).join('')}</select></div></label>${formButton('compare','Vergleichen')}</div><div id="form-diff"></div></div></section>`;
  byId('form-compare-from').value=String(formsUI.current.basedOnId || versions.at(-1).id); byId('form-compare-to').value=String(formsUI.current.id);
}
function formFieldSummary(f) {
  const visibility={always:'immer',children:'wenn Kinder angemeldet',child:'nur Kind',adult:'nur Erwachsene',selection:'abhängig von vorheriger Auswahl'};
  const pieces=[f.label,formTypeLabels[f.type],f.scope==='person'?'pro Person':'pro Buchung',visibility[f.visibility.kind],f.required?'Pflicht':'optional',f.analytics?'Auswertung an':'Auswertung aus'];
  if(f.unit){pieces.push('Einheit '+f.unit);}
  if(f.help){pieces.push('Hilfetext: '+f.help);}
  if(f.type==='number'){pieces.push(f.integer?'Ganzzahl':'Dezimalzahl');}
  if(f.min!==null || f.max!==null){pieces.push(`Bereich ${f.min??'offen'} bis ${f.max??'offen'}`);}
  if(f.default!==null){pieces.push('Startwert '+f.default);}
  if(['short_text','long_text'].includes(f.type)){pieces.push('Maximal '+f.maxLength+' Zeichen');}
  if(f.type==='multi_select'){pieces.push(`${f.minSelections} bis ${f.maxSelections} Auswahlen`);}
  if(f.options.length){pieces.push('Optionen: '+f.options.map(o=>o.label+' ('+o.id+')').join(', '));}
  if(f.visibility.kind==='selection'){pieces.push('Bedingung: '+f.visibility.fieldId+' = '+f.visibility.value);}
  if(f.mapping){pieces.push('Kalkulationsziel: '+f.mapping);}
  return pieces.join(' · ');
}
function diffHtml(diff) {
  const labels={added:'Hinzugefügt',changed:'Geändert',removed:'Entfernt',unchanged:'Unverändert'};
  const describe=(f,position)=>f?`Position ${position+1} · ${formFieldSummary(f)}`:'–';
  return `<div class="event-table-wrap form-diff-scroll" tabindex="0" aria-label="Versionsvergleich"><table class="tb"><thead><tr><th>Feld</th><th>Änderung</th><th>Vorher</th><th>Nachher</th><th>Auswirkung</th></tr></thead><tbody>${diff.rows.map(r=>`<tr class="form-diff-${r.change}"><td>${escapeHtml((r.after||r.before).label)}<small>${escapeHtml(r.fieldId)}</small></td><td>${labels[r.change]}</td><td>${r.change==='removed'?'<del>':''}${escapeHtml(describe(r.before,r.positionBefore))}${r.change==='removed'?'</del>':''}</td><td>${escapeHtml(describe(r.after,r.positionAfter))}</td><td>${formImpactLabels[r.impact]}${r.before && r.after && JSON.stringify(r.before.options)!==JSON.stringify(r.after.options)?'<br>Options-IDs und Beschriftungen geändert; bestehende Optionsantworten bleiben erhalten.':''}</td></tr>`).join('')}</tbody></table></div>`;
}
function renderFormPreviewShell() {
  const trips=management.event.trips;
  byId('form-mode-content').innerHTML=`<section class="sec"><div class="sec-h"><h3>Vorschau mit fiktiven Personen</h3>${formButton('editor','Zurück zum Editor')}</div><div class="sec-b"><div class="event-toolbar"><label class="f">Beispiel<div class="in"><select id="form-preview-example"><option value="family">2 Erwachsene, 2 Kinder</option><option value="adults">Nur Erwachsene</option></select></div></label><label class="f">Reise / Altersstichtag<div class="in"><select id="form-preview-trip"><option value="">Eigener Teststichtag</option>${trips.map(t=>`<option value="${t.id}">${escapeHtml(t.name)}</option>`).join('')}</select></div></label><label class="f">Teststichtag<div class="in"><input id="form-preview-date" type="date" value="${new Date().getFullYear()}-12-20"></div></label><label class="f">Erwachsen ab<div class="in"><input id="form-preview-age" type="number" min="1" max="100" value="18"></div></label>${formButton('preview-mobile','Mobil / Desktop')}</div><p class="help">Bei Auswahl einer Reise gelten deren Beginn und Altersgrenze. Es werden keine Buchungen oder Teilnahmen gespeichert.</p><div id="form-preview-result" role="status" tabindex="-1"></div><div id="form-preview-inputs" class="form-preview-inputs"></div></div></section>`;
  if(!formsUI.preview){setPreviewDefaults();}
  performFormOperation(()=>updateFormPreview(true));
}
function setPreviewDefaults() {
  for(const f of formsUI.current.fields){if(f.type!=='number'||f.default===null){continue;}if(f.scope==='booking'){formsUI.answers.booking[f.id]=f.default;}else{for(const p of sampleContext().persons){(formsUI.answers.persons[p.id] ||= {})[f.id]=f.default;}}}
}
function sampleContext() {
  const date=byId('form-preview-date').value; const year=Number(date.slice(0,4));
  const persons=[{id:'adult1',birthDate:`${year-40}-01-01`},{id:'adult2',birthDate:`${year-38}-01-01`}];
  if(byId('form-preview-example').value==='family'){persons.push({id:'child1',birthDate:`${year-9}-01-01`},{id:'child2',birthDate:`${year-6}-01-01`});}
  return {startDate:date,adultAgeThreshold:Number(byId('form-preview-age').value),persons};
}
async function updateFormPreview(render=false) {
  const seq=++formsUI.previewSeq; const v=formsUI.current; const eventId=formsUI.eventId;
  const tripId=Number(byId('form-preview-trip').value)||null;
  const body={context:sampleContext(),answers:formsUI.answers,...(tripId?{tripId}:{})};
  const preview=await api(`${formBase()}/${v.id}/preview`,{method:'POST',body:JSON.stringify(body)});
  if(seq!==formsUI.previewSeq || formsUI.mode!=='preview' || formsUI.current.id!==v.id || formsUI.eventId!==eventId){return;}
  formsUI.preview=preview;
  byId('form-preview-result').textContent=preview.valid?`Alle Angaben gültig · Stichtag ${preview.startDate} · erwachsen ab ${preview.adultAgeThreshold}`:`${Object.keys(preview.errors).length} Angaben fehlen oder sind ungültig · Stichtag ${preview.startDate} · erwachsen ab ${preview.adultAgeThreshold}`;
  const input=document.activeElement; const focus=input?.dataset.answerField?{field:input.dataset.answerField,person:input.dataset.answerPerson,start:input.selectionStart,end:input.selectionEnd}:null;
  renderPreviewInputs(preview);
  if(!render && focus){const target=Array.from(byId('form-preview-inputs').querySelectorAll('[data-answer-field]')).find(e=>e.dataset.answerField===focus.field && e.dataset.answerPerson===focus.person);if(target){target.focus();if(typeof focus.start==='number' && ['text','textarea'].includes(target.type)){target.setSelectionRange(focus.start,focus.end);}}}
}
function previewInput(f,pid,entry,errors) {
  const attrs=`data-answer-field="${f.id}" data-answer-person="${pid || ''}"`; const value=pid?formsUI.answers.persons[pid]?.[f.id]:formsUI.answers.booking[f.id]; const inputId=`pv-${pid||'booking'}-${f.id}`;
  const error=errors[(pid?'persons.'+pid:'booking')+'.'+f.id];
  if(entry.state==='not_applicable'){return `<p class="help">${escapeHtml(f.label)} · nicht anwendbar</p>`;}
  if(['section','notice'].includes(f.type)){return `<div class="form-preview-notice"><h4>${escapeHtml(f.label)}</h4><p>${escapeHtml(f.help)}</p></div>`;}
  let input='';
  if(f.type==='checkbox'){input=`<input id="${inputId}" type="checkbox" ${attrs} ${value===true?'checked':''}>`;}
  else if(f.type==='yes_no'){input=`<select id="${inputId}" ${attrs}><option value="">Bitte wählen</option><option value="true" ${value===true?'selected':''}>Ja</option><option value="false" ${value===false?'selected':''}>Nein</option></select>`;}
  else if(['single_select','multi_select'].includes(f.type)){input=`<select id="${inputId}" ${attrs} ${f.type==='multi_select'?'multiple':''}>${f.type==='single_select'?'<option value="">Bitte wählen</option>':''}${f.options.map(o=>`<option value="${o.id}" ${(Array.isArray(value)?value.includes(o.id):value===o.id)?'selected':''}>${escapeHtml(o.label)}</option>`).join('')}</select>`;}
  else if(f.type==='long_text'){input=`<textarea id="${inputId}" ${attrs} maxlength="${f.maxLength}">${escapeHtml(value||'')}</textarea>`;}
  else {input=`<input id="${inputId}" ${attrs} type="${f.type==='date'?'date':'text'}" ${f.type==='number'?'inputmode="decimal"':''} value="${escapeHtml(String(value ?? ''))}">`;}
  return `<div class="f form-preview-field ${error?'has-error':''}"><label for="${inputId}">${escapeHtml(f.label)}${f.required?' *':''}${f.unit?' · '+escapeHtml(f.unit):''}</label><div class="in">${input}</div><p class="help">${escapeHtml(f.help)}</p>${error?`<p class="form-field-error" id="${inputId}-error">${error==='required'?'Antwort erforderlich':f.type==='checkbox'&&f.required?'Muss angehakt sein':'Ungültiger Wert'}</p>`:''}</div>`;
}
function previewHtml(preview,errors=preview.errors) {
  const fields=formsUI.current.fields;
  let html='<h4>Für eure ganze Buchung</h4>'+fields.filter(f=>f.scope==='booking').map(f=>previewInput(f,null,preview.answers.booking[f.id],errors)).join('');
  for(const p of preview.persons){html+=`<h4>Beispielperson ${escapeHtml(p.id)} · ${p.age} Jahre · ${p.child?'Kind':'erwachsen'}</h4>`+fields.filter(f=>f.scope==='person').map(f=>previewInput(f,p.id,preview.answers.persons[p.id]?.[f.id],errors)).join('');}
  return html;
}
function renderPreviewInputs(preview) {
  byId('form-preview-inputs').innerHTML=previewHtml(preview);
  byId('form-preview-inputs').querySelectorAll('.has-error [data-answer-field]').forEach(e=>{e.setAttribute('aria-invalid','true');e.setAttribute('aria-describedby',e.id+'-error');});
}
async function loadTripFormBinding(tripId) {
  const binding=await api(`/api/trips/${tripId}/form`);
  if(state.currentTripId!==tripId){return;}
  byId('trip-event-assign').disabled = binding.versionId !== null;
  byId('trip-event-assignment-note').textContent = binding.versionId !== null && hasTripCapability('trip.manage') ? 'Die Veranstaltung ist durch die gebundene Formularversion festgelegt.' : '';
  byId('trip-form-binding').innerHTML=`<section class="form-binding"><h3>Anmeldeformular</h3><p>${binding.version?'Version '+binding.version.number+' · '+(binding.version.status==='archived'?'archiviert, weiterhin gebunden':'veröffentlicht'):'Kein Zusatzformular ausgewählt'}</p>${binding.version?`<p class="help">${escapeHtml(binding.version.changeDescription)}</p><details><summary>Gebundene Definition ansehen</summary>${binding.version.fields.map(f=>`<p><b>${escapeHtml(f.label)}</b><br>${escapeHtml(formFieldSummary(f))}<br><small>${escapeHtml(f.id)}</small></p>`).join('')}</details>`:''}${hasTripCapability('trip.manage')&&!state.currentTrip.closedAt?formButton('trip-switch','Formularversion wählen / wechseln',!binding.availableVersions.some(v=>v.id!==binding.versionId)):''}<p class="help">Neue Veröffentlichungen wechseln die Reise nicht automatisch. Bestehende Antworten behalten ihren Formularstand.</p>${binding.history.length?`<details><summary>Versionswechsel-Historie</summary>${binding.history.map(h=>`<p>Revision ${h.revision} · Version #${h.toVersionId} · ${escapeHtml(h.createdAt)} · Akteur #${h.actorId}<br>${escapeHtml(h.reason)}${h.requestSupplement?' · Ergänzungsauftrag vorgemerkt':''}</p>`).join('')}</details>`:''}</section>`;
}
async function openTripFormSwitch() {
  const tripId=state.currentTripId; const binding=await api(`/api/trips/${tripId}/form`);
  const choices=binding.availableVersions.filter(v=>v.id!==binding.versionId);
  if(!choices.length){throw new Error('Keine andere veröffentlichte Version verfügbar.');}
  let checked=null;
  showManagementModal('Formularversion wechseln',`<p>Aktuell: ${binding.version?'v'+binding.version.number:'kein Zusatzformular'}. Bestehende Antworten werden nicht verändert.</p><label class="f">Neue Version<div class="in"><select id="trip-form-target">${choices.map(v=>`<option value="${v.id}">${formVersionLabel(v)} · ${escapeHtml(v.changeDescription)}</option>`).join('')}</select></div></label>${reasonField()}<label class="form-check-line"><input type="checkbox" id="trip-form-supplement" ${hasTripCapability('registration.manage')?'':'disabled'}> Ergänzung anfordern</label><p class="help">Der Datenauftrag wird lokal vorgemerkt. Die Anzeige in „Mein Bereich“ folgt mit der Website-Anbindung; es wird keine E-Mail versendet.</p><div id="trip-switch-diff"></div>`, async()=>{
    if(!checked || checked.toVersionId!==Number(byId('trip-form-target').value)){throw new Error('Versionsvergleich abwarten.');}
    await api(`/api/trips/${tripId}/form`,{method:'PUT',body:JSON.stringify({revision:checked.revision,versionId:checked.toVersionId,targetRevision:checked.targetRevision,requestSupplement:byId('trip-form-supplement').checked,reason:byId('action-reason').value})}); await loadTripFormBinding(tripId);
  }, 'Version übernehmen');
  let seq=0;
  const compare=async()=>{const n=++seq;checked=null;byId('trip-switch-diff').textContent='Vergleich wird geladen …';const result=await api(`/api/trips/${tripId}/form/preview-switch`,{method:'POST',body:JSON.stringify({revision:binding.revision,versionId:Number(byId('trip-form-target').value)})});if(n!==seq){return;}checked=result;byId('trip-switch-diff').innerHTML=diffHtml(result)+`<p>Bestehende Buchungen: ${result.existingRegistrationCount===null?'nicht freigegeben':result.existingRegistrationCount}. Sie bleiben unverändert.</p>${result.existingVersionBreakdownAvailable?`<p>Aufteilung: ${result.existingVersionBreakdown.map(x=>`${x.versionId?'Formular #'+x.versionId:'Ohne Formular'}: ${x.count}`).join(' · ')||'keine Buchungen'}</p>`:''}`;};
  byId('trip-form-target').addEventListener('change',()=>performFormOperation(compare)); await compare();
}
async function formAction(action,target) {
  const v=formsUI.current;
  if(action==='trip-switch'){await openTripFormSwitch();return;}
  if(action==='create'||action==='copy'){
    const saved=await api(formBase(),{method:'POST',body:JSON.stringify({basedOnId:action==='copy'?Number(target.dataset.id):null})});formsUI.current=saved;formsUI.versions.unshift(saved);formsUI.snapshot=JSON.stringify(saved.fields);formsUI.mode='editor';formsUI.selected=0;formsUI.answers={booking:{},persons:{}};formsUI.preview=null;renderFormWorkspace();return;
  }
  if(action==='reload'){if(formDirty()&&!window.confirm('Ungespeicherte Formularänderungen verwerfen und neu laden?')){return;}await loadFormWorkspace(true);return;}
  if(action==='save'){await saveFormDraft();return;}
  if(action==='editor'||action==='versions'){formsUI.mode=action;renderFormWorkspace();return;}
  if(action==='list'){formsUI.mobile='list';renderFormEditor();return;}
  if(action==='preview'){if(formDirty()){await saveFormDraft();}formsUI.mode='preview';renderFormWorkspace();return;}
  if(action==='preview-mobile'){byId('form-preview-inputs').classList.toggle('mobile-preview');return;}
  if(action==='open-version'){
    if(formDirty()&&!window.confirm('Ungespeicherte Formularänderungen verwerfen?')){return;}
    formsUI.current=await api(`${formBase()}/${target.dataset.id}`);formsUI.snapshot=JSON.stringify(formsUI.current.fields);formsUI.selected=0;formsUI.mode='editor';formsUI.answers={booking:{},persons:{}};formsUI.preview=null;renderFormWorkspace();return;
  }
  if(action==='publish'){
    showManagementModal('Unveränderlich veröffentlichen',`<p>Die Definition wird unveränderlich. Bestehende Reisen bleiben auf ihrer bisherigen Version.</p><label class="f">Änderungsbeschreibung<div class="in"><textarea id="form-publish-description" required maxlength="500" rows="3"></textarea></div></label>`,async()=>{await api(`${formBase()}/${v.id}/publish`,{method:'POST',body:JSON.stringify({revision:v.revision,changeDescription:byId('form-publish-description').value})});await loadFormWorkspace(true);});return;
  }
  if(action==='archive'){
    const version=formsUI.versions.find(x=>x.id===Number(target.dataset.id));
    showManagementModal('Formularversion archivieren','<p>Nicht mehr für neue Bindungen wählbar. Gebundene Reisen und Historie bleiben lesbar.</p>'+reasonField(),async()=>{await api(`${formBase()}/${version.id}/archive`,{method:'POST',body:JSON.stringify({revision:version.revision,reason:byId('action-reason').value})});await loadFormWorkspace(true);});return;
  }
  if(action==='compare'){if(formDirty()){throw new Error('Entwurf zuerst speichern, damit der Vergleich den aktuellen Stand zeigt.');}const diff=await api(`${formBase()}/compare?from=${byId('form-compare-from').value}&to=${byId('form-compare-to').value}`);byId('form-diff').innerHTML=diffHtml(diff);return;}
  if(!formWrite()){return;}
  if(action==='add'){
    showManagementModal('Feld hinzufügen','<label class="f">Feldtyp<div class="in"><select id="form-new-type">'+Object.entries(formTypeLabels).map(([key,label])=>`<option value="${key}">${label}</option>`).join('')+'</select></div></label>',async()=>{const type=byId('form-new-type').value;v.fields.push({id:null,type,label:'Neues Feld',help:'',scope:'booking',required:false,analytics:!['notice','section'].includes(type),unit:'',integer:true,min:null,max:null,default:null,maxLength:500,minSelections:0,maxSelections:1,mapping:null,visibility:{kind:'always'},options:['single_select','multi_select'].includes(type)?[{id:null,label:'Option 1'}]:[]});formsUI.selected=v.fields.length-1;formsUI.mobile='edit';renderFormWorkspace();});return;
  }
  const index=Number(target.dataset.index);const f=v.fields[formsUI.selected];
  if(action==='up'||action==='down'){const next=index+(action==='up'?-1:1);if(next<0||next>=v.fields.length){return;}[v.fields[index],v.fields[next]]=[v.fields[next],v.fields[index]];formsUI.selected=next;}
  if(action==='remove'){if(!window.confirm('Feld aus dem Entwurf entfernen? Ältere Versionen bleiben erhalten.')){return;}v.fields.splice(formsUI.selected,1);formsUI.selected=Math.max(0,formsUI.selected-1);formsUI.mobile='list';}
  if(action==='add-option'){f.options.push({id:null,label:'Neue Option'});}
  if(action==='remove-option'){f.options.splice(index,1);}
  renderFormWorkspace();
}
byId('form-workspace').addEventListener('input',event=>{
  if(!formWrite()){return;}const input=event.target;const f=formsUI.current.fields[formsUI.selected];if(!f){return;}
  if(input.dataset.optionLabel!==undefined){f.options[Number(input.dataset.optionLabel)].label=input.value;markFormDirty();return;}
  if(input.dataset.optionSemantic!==undefined){f.options[Number(input.dataset.optionSemantic)].semanticChange=input.checked;markFormDirty();return;}
  const key=input.dataset.fieldKey;if(!key||key==='id'){return;}
  if(['type','scope','visibility','conditionField','conditionValue','integer','mapping'].includes(key)){return;}
  const inputValue=f.type==='number'&&['min','max','default'].includes(key)?input.value.trim().replace(',', '.'):input.value;
  f[key]=input.type==='checkbox'?input.checked:input.type==='number'?Number(input.value):inputValue===''&&['min','max','default'].includes(key)?null:inputValue;markFormDirty();
});
byId('form-workspace').addEventListener('change',event=>{
  const input=event.target;const key=input.dataset.fieldKey;
  if(key&&formWrite()){
    const f=formsUI.current.fields[formsUI.selected];
    if(key==='type'){f.type=input.value;f.unit='';f.mapping=null;f.min=null;f.max=null;f.default=null;f.options=['single_select','multi_select'].includes(f.type)?[{id:null,label:'Option 1'}]:[];if(['notice','section'].includes(f.type)){f.required=false;f.analytics=false;}}
    if(key==='scope'){f.scope=input.value;if(f.scope==='booking'&&['child','adult'].includes(f.visibility.kind)){f.visibility={kind:'always'};}}
    if(key==='visibility'){f.visibility={kind:input.value};}
    if(key==='conditionField'){f.visibility.fieldId=input.value;f.visibility.value=null;}
    if(key==='conditionValue'){f.visibility.value=['true','false'].includes(input.value)?input.value==='true':input.value;}
    if(key==='integer'){f.integer=input.value==='true';}
    if(key==='mapping'){f.mapping=input.value||null;}
    if(['type','scope','visibility','conditionField','conditionValue','integer','mapping'].includes(key)){renderFormWorkspace();}
  }
  if(['form-preview-example','form-preview-trip','form-preview-date','form-preview-age'].includes(input.id)){formsUI.answers={booking:{},persons:{}};setPreviewDefaults();performFormOperation(()=>updateFormPreview(true));}
  if(input.dataset.answerField){const f=formsUI.current.fields.find(x=>x.id===input.dataset.answerField);const person=input.dataset.answerPerson;const map=person?(formsUI.answers.persons[person] ||= {}):formsUI.answers.booking;map[f.id]=f.type==='checkbox'?input.checked:f.type==='yes_no'?input.value===''?null:input.value==='true':f.type==='multi_select'?Array.from(input.selectedOptions).map(o=>o.value):f.type==='number'?input.value.trim().replace(',', '.'):input.value;performFormOperation(()=>updateFormPreview());}
});
document.addEventListener('click',event=>{
  const action=event.target.closest('[data-form-action]');if(action){performFormOperation(()=>formAction(action.dataset.formAction,action));return;}
  const select=event.target.closest('[data-form-select]');if(select){formsUI.selected=Number(select.dataset.formSelect);formsUI.mobile='edit';renderFormEditor();}
});
window.addEventListener('beforeunload',event=>{if(formDirty()){event.preventDefault();event.returnValue='';}});
