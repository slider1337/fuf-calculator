// Isolated UI logic; no browser or provider requests.
const assert=require('node:assert/strict'),fs=require('node:fs'),vm=require('node:vm');
const scope={Date,JSON,Error,crypto:{randomUUID:()=> 'fake-key'},escapeHtml:value=>String(value??''),valueText:value=>String(value??''),reasonField:()=>'<textarea id="action-reason"></textarea>',termProblemText:()=> 'Anmeldeschluss muss vor dem Terminbeginn liegen.',participationError:code=>code||'',participationStateLabels:{unknown:'Klärung nötig'},workflowAction:fn=>fn(),loadWorkflowStatus:async()=>{}};
vm.createContext(scope);vm.runInContext(fs.readFileSync('public/assets/js/term-updates.js','utf8'),scope);
const fields={start:'2026-10-15T22:00:00+00:00',registrationDeadline:'2026-10-16T21:59:00+00:00'};
assert.match(scope.termUpdateDeadlineIssue(fields),/vor dem Terminbeginn/);
assert.equal(scope.termUpdateDeadlineIssue({...fields,registrationDeadline:'2026-10-15T23:59:59+02:00'}),'');
assert.match(scope.termUpdateDeadlineIssue({...fields,registrationDeadline:fields.start}),/vor dem Terminbeginn/);
assert.equal(scope.termUpdateDeadlineIssue({...fields,registrationDeadline:null}),'');
assert.match(scope.termUpdateError('term_update_forbidden'),/Berechtigung/);
const nodes=new Map();scope.byId=id=>{if(!nodes.has(id))nodes.set(id,{value:'Stand geprüft',querySelectorAll:()=>[]});return nodes.get(id)};
(async()=>{
 const trip={id:2};let markup,submit,calls=[];
 scope.showManagementModal=(title,html,fn)=>{markup=html;submit=fn};
 scope.api=async(url,options)=>{calls.push({url,options});return {fields,jobs:[{id:1,status:'unknown'}],observedHash:'fresh-hash'}};
 await scope.openTermUpdate(trip);
 assert.equal(scope.byId('management-submit').disabled,true);
 assert.match(markup,/data-term-resolve="1"/);assert.match(markup,/vorhandener Änderungsauftrag/);
 assert.match(markup,/id="term-update-preview" disabled/);
 await scope.openTermUpdateResolution(trip,1);
 assert.match(markup,/gerade aus easyVerein geladenen Stand/);assert.match(markup,/beendest den alten Auftrag lokal/);
 await submit();const request=calls.at(-1);
 assert.equal(request.url,'/api/trips/2/term/updates/resolve');
 assert.deepEqual(JSON.parse(request.options.body),{jobId:1,observedHash:'fresh-hash',confirmed:true,reason:'Stand geprüft'});
 scope.api=async()=>({fields,jobs:[{id:1,status:'running'}]});
 await assert.rejects(scope.openTermUpdateResolution(trip,1),/Auftrag hat sich geändert/);
 console.log('Terminänderung: Fristprüfung, Sperre und geprüfter lokaler Abschluss erfolgreich.');
})().catch(error=>{console.error(error);process.exitCode=1});
