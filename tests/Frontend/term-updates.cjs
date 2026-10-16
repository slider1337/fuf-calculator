// Isolated UI logic with fake transport, no browser/provider requests.
const assert=require('node:assert/strict'),fs=require('node:fs'),vm=require('node:vm');
let key=0;
const scope={Date,Intl,JSON,Error,Promise,crypto:{randomUUID:()=> `fake-key-${++key}`},escapeHtml:value=>String(value??''),valueText:value=>String(value??''),termProblemText:()=> 'Anmeldeschluss muss vor dem Terminbeginn liegen.',participationError:code=>code||'',workflowAction:fn=>fn(),loadWorkflowStatus:async()=>{},setStatusNote:()=>{},termValidationText:errors=>(errors||[]).map(e=>e.field==='prologue'?'Beschreibung: darf nicht leer sein.':'').join(' '),managementMessage:error=>error.message};
vm.createContext(scope);vm.runInContext(fs.readFileSync('public/assets/js/term-updates.js','utf8'),scope);
assert.equal(scope.termLocalDateTime('2026-10-22T21:59:00Z'),'2026-10-22T23:59:00');
assert.equal(scope.termLocalDateTime('2026-12-19T22:59:00Z'),'2026-12-19T23:59:00');
assert.match(scope.termUpdateError('term_update_forbidden'),/Berechtigung/);
assert.match(scope.termUpdateOutcome({status:'blocked',errorCode:'term_update_rejected',validationErrors:[{field:'prologue',reason:'blank'}]}),/Beschreibung: darf nicht leer sein/);
assert.match(scope.termSaveMessage(new Error(JSON.stringify({error:'validation_error',details:{'changes.registrationDeadline':'invalid_term_value'}}))),/gültiges Datum/);
const nodes=new Map();let inputs=[];
scope.byId=id=>{if(!nodes.has(id))nodes.set(id,{querySelectorAll:()=>inputs});return nodes.get(id)};
const fields={title:'Test',start:'2026-10-15T22:00:00Z',end:'2026-10-18T21:59:59Z',registrationDeadline:'2026-10-15T21:59:59Z',location:'',description:'',capacity:20,visibleToMembers:true};
const data={fields,tripFields:{start:'2026-10-22T22:00:00Z',end:'2026-10-25T22:59:59Z'},jobs:[],observedHash:'fresh-hash'};
(async()=>{
 const trip={id:2};let markup,submit,calls=[],result={status:'succeeded',jobId:3};
 scope.showManagementModal=(title,html,fn)=>{markup=html;submit=fn};
 scope.api=async(url,options)=>{calls.push({url,options});return url.endsWith('/save')?result:data};
 await scope.openTermUpdate(trip);
 assert.equal(scope.byId('management-submit').disabled,false);
 assert(!markup.includes('action-reason'));assert(!markup.includes('data-term-change'));assert(!markup.includes('term-update-preview'));
 assert(!markup.includes('data-term-update="start"'));assert(!markup.includes('data-term-update="end"'));assert(markup.includes('gespeicherten Reise'));
 inputs=[{dataset:{termUpdate:'title'},value:'Geändert'},{dataset:{termUpdate:'registrationDeadline'},value:'2026-10-22T23:59'}];
 await submit();let request=calls.at(-1);assert.equal(request.url,'/api/trips/2/term/updates/save');
 let body=JSON.parse(request.options.body);assert.deepEqual(body.changes,{title:'Geändert',registrationDeadline:'2026-10-22T23:59'});assert(!Object.hasOwn(body,'reason'));assert(!Object.hasOwn(body,'reviewHash'));
 // Failed provider validation remains in the dialog; a corrected submit gets a fresh key.
 result={status:'blocked',jobId:4,errorCode:'term_update_rejected',validationErrors:[{field:'prologue',reason:'blank'}]};
 await assert.rejects(submit(),/Beschreibung: darf nicht leer sein/);const rejected=JSON.parse(calls.at(-1).options.body);
 inputs[0].value='Korrigiert';result={status:'succeeded',jobId:5};await submit();assert.notEqual(JSON.parse(calls.at(-1).options.body).operationKey,rejected.operationKey);
 // A lost response retries the exact saved command rather than inventing a second operation.
 await scope.openTermUpdate(trip);const transport=scope.api;let failed=true;
 scope.api=async(url,options)=>{if(url.endsWith('/save')&&failed){calls.push({url,options});throw new Error('Failed to fetch')}return transport(url,options)};
 await assert.rejects(submit(),/Verbindung wurde unterbrochen/);const original=calls.at(-1).options.body;failed=false;await submit();assert.equal(calls.at(-1).options.body,original);
 // A rejected preflight must allow correcting the input immediately.
 scope.api=transport;await scope.openTermUpdate(trip);let reject=true;
 scope.api=async(url,options)=>{if(url.endsWith('/save')&&reject)throw new Error(JSON.stringify({error:'term_registration_overlap'}));return transport(url,options)};
 await assert.rejects(submit(),/term_registration_overlap/);inputs[0].value='Nach Fristkorrektur';reject=false;await submit();
 scope.api=async()=>({...data,jobs:[{id:1,status:'unknown'}]});await scope.openTermUpdate(trip);
 assert.equal(scope.byId('management-submit').disabled,true);assert(markup.includes('term-resolve'));
 await scope.openTermUpdateResolution(trip,1);assert(!markup.includes('action-reason'));
 scope.api=async(url,options)=>{calls.push({url,options});return {}};await submit();body=JSON.parse(calls.at(-1).options.body);assert.deepEqual(body,{jobId:1,observedHash:'fresh-hash',confirmed:true});
 console.log('Direktspeicherung, Reisezeitraum, deutsche Zeitzone, Fehlerdetails und sichere Wiederholung erfolgreich.');
})().catch(error=>{console.error(error);process.exitCode=1});
