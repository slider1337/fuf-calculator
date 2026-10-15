// Runs only isolated frontend logic with fake API responses; no browser/provider calls.
const assert=require('node:assert/strict'),fs=require('node:fs'),vm=require('node:vm');
const source=fs.readFileSync('public/assets/js/workflows.js','utf8');
const scope={JSON,Error};vm.createContext(scope);
vm.runInContext(source.slice(source.indexOf('function termProblemText'),source.indexOf('async function loadWorkflowStatus')),scope);
const draft={title:'Fiktive Reise',startDate:'2027-03-01',endDate:null,mode:'create',calendarReference:'42',externalId:'',priceGroups:[],teamUserIds:[],registrationDeadline:null,formVersionId:null};
const existing=()=>({id:2,eventId:null,revision:1,createKey:'local-key',actionKey:'external-key'});
let count=0;
function fake({issues=[],blockedReason=null,failRequest=false}={}){
 const calls=[];scope.api=async(url,options={})=>{
  const body=options.body?JSON.parse(options.body):null;calls.push({url,body,method:options.method??'GET'});
  if(url==='/api/trip-wizard')return {tripId:8};
  if(url.endsWith('/term/draft')||url.endsWith('/term'))return {draft:{revision:3}};
  if(url.endsWith('/review'))return {issues,blockedReason,reviewHash:'fresh-hash'};
  if(failRequest&&url.endsWith('/request'))throw new Error('response lost');
  return {job:{status:'pending'}};
 };return calls;
}
(async()=>{
 let calls=fake(),context=existing();await scope.saveTermSetup(context,draft,false);
 assert.deepEqual(calls.map(c=>c.url),['/api/trips/2/term/draft']);assert.equal(context.revision,3);count++;
 calls=fake();context=existing();await scope.saveTermSetup(context,draft,true);
 assert.deepEqual(calls.map(c=>c.url),['/api/trips/2/term/draft','/api/trips/2/term/review','/api/trips/2/term/request']);assert.deepEqual(calls[2].body,{operationKey:'external-key',reviewHash:'fresh-hash',confirmed:true});count++;
 calls=fake();context=existing();await scope.saveTermSetup(context,{...draft,mode:'link',externalId:'77'},true);
 assert.equal(calls[2].url,'/api/trips/2/term/link');assert.equal(calls[2].body.externalId,'77');assert(calls[2].body.reason);count++;
 calls=fake({issues:['Bitte einen Kalender auswählen.']});context=existing();await assert.rejects(scope.saveTermSetup(context,draft,true),/Kalender/);assert.equal(calls.length,2);assert(!context.command);count++;
 calls=fake({blockedReason:'easyverein_production_writes_disabled'});context=existing();await assert.rejects(scope.saveTermSetup(context,draft,true),/noch nicht freigegeben/);assert.equal(calls.length,2);count++;
 calls=fake({failRequest:true});context=existing();await assert.rejects(scope.saveTermSetup(context,draft,true),/response lost/);const original=calls[2];
 calls=fake();await scope.saveTermSetup(context,draft,true);assert.deepEqual(calls,[original]);
 await assert.rejects(scope.saveTermSetup(context,{...draft,title:'Geändert'},true),/bereits gesendet/);
 await assert.rejects(scope.saveTermSetup(context,draft,false),/bereits gesendet/);assert.equal(calls.length,1);count++;
 calls=fake();context={...existing(),id:null};await scope.saveTermSetup(context,draft,true);
 assert.deepEqual(calls.map(c=>c.url),['/api/trip-wizard','/api/trips/8/term','/api/trips/8/term/review','/api/trips/8/term/request']);assert.equal(context.id,8);assert.equal(calls[0].body.operationKey,'local-key');count++;
 calls=fake();context={...existing(),id:null};await scope.saveTermSetup(context,draft,false);assert.equal(calls.length,2);assert.equal(calls[0].url,'/api/trip-wizard');count++;
 assert.match(scope.termProgressText({draft:{}}),/Nächster Schritt/);
 for(const status of ['pending','running','resume_pending'])assert.match(scope.termProgressText({job:{status}}),/automatisch/);
 assert.match(scope.termProgressText({job:{status:'unknown'}}),/prüfen und anlegen/);
 assert(!scope.termProgressText({link:{},registrationEnabled:true}).includes('gesperrt'));count++;
 // Generate real wizard markup before DOM wiring; inspect three sibling panels.
 scope.dirtyPlanning=()=>false;scope.escapeHtml=x=>String(x??'');scope.crypto={randomUUID:()=> 'fake-key'};scope.hasTripCapability=()=>false;scope.state={currentUser:{role:'member'}};scope.capabilityLabels={};
 vm.runInContext(source.slice(source.indexOf('async function openTermWizard'),source.indexOf('// Explicit searches')),scope);
 for(const trip of [null,{id:2,name:'Fiktive Reise',startDate:'2027-03-01',endDate:null,eventId:null}]){
  scope.api=async url=>url.endsWith('/form')?{versionId:null,availableVersions:[]}:{};
  let html;scope.showManagementModal=(title,fields)=>{html=fields;throw new Error('capture')};
  await assert.rejects(scope.openTermWizard(trip),/capture/);
  assert.deepEqual([...html.matchAll(/data-wizard-panel="(\d)"/g)].map(m=>m[1]),['0','1','2']);
  assert(!html.includes('wizard-save-review'));assert(!html.includes('wizard-term-action'));
  let depth=0;for(const m of html.matchAll(/<\/?section\b[^>]*>/g)){if(m[0].startsWith('</'))depth--;else{assert.equal(depth,0);depth++}}assert.equal(depth,0);
  assert(html.includes('wizard-event-query'));assert(html.includes('wizard-calendar-query'));count++;
 }
 // Status UI shows only the next useful action; background reads never create a term.
 const nodes=new Map();scope.byId=id=>{if(!nodes.has(id))nodes.set(id,{addEventListener(){}});return nodes.get(id)};
 scope.state={currentTripId:2,currentTrip:{id:2,registrationMode:'easyverein'}};scope.tripUsesEasyVerein=()=>true;scope.clearTimeout=()=>{};let timers=0;scope.setTimeout=()=>++timers;scope.loadParticipationStatus=async()=>{};
 vm.runInContext('const workflowUI={};'+source.slice(source.indexOf('async function loadWorkflowStatus'),source.indexOf("byId('planning-transfer-btn').addEventListener")),scope);
 const trip={id:2,registrationMode:'easyverein',capabilities:['term.manage','trip.manage']};
 scope.api=async()=>({draft:{},link:null,job:null,blockedReason:null});await scope.loadWorkflowStatus(trip);
 assert.equal(nodes.get('term-wizard').textContent,'Termin fertigstellen');assert.equal(nodes.get('term-wizard').hidden,false);assert(!nodes.get('term-details').innerHTML.includes('member-release-open'));count++;
 scope.api=async()=>({draft:{},link:null,job:{status:'pending'},blockedReason:null});await scope.loadWorkflowStatus(trip);
 assert.equal(nodes.get('term-wizard').hidden,true);assert.equal(timers,1);assert(nodes.get('term-details').innerHTML.includes('term-refresh'));assert(!nodes.get('term-details').innerHTML.includes('member-release-open'));count++;
 scope.api=async()=>({draft:{},link:{externalId:'77'},job:{status:'succeeded'},registrationEnabled:false,registrationReason:'member_release_disabled'});await scope.loadWorkflowStatus(trip);
 assert(nodes.get('term-details').innerHTML.includes('member-release-open'));assert.match(nodes.get('term-status').textContent,/Termin verbunden/);assert.equal(nodes.get('term-wizard').hidden,true);count++;
 // No participation request/UI before a confirmed term; recovery never creates a term.
 scope.api=async()=>({draft:{},link:null,job:{status:'unknown',errorCode:'external_outcome_unknown'},blockedReason:null});
 let participationReads=0;scope.loadParticipationStatus=async()=>participationReads++;
 await scope.loadWorkflowStatus(trip);assert.equal(participationReads,0);assert(nodes.get('term-details').innerHTML.includes('term-recover'));assert.match(nodes.get('participation-details').textContent,/sobald der Termin verbunden/);count++;
 const recoveryNodes=new Map();function node(id){if(!recoveryNodes.has(id))recoveryNodes.set(id,{value:'',children:[],textContent:'',isConnected:true,replaceChildren(){this.children=[]},append(child){this.children.push(child)}});return recoveryNodes.get(id)}
 scope.byId=node;scope.URLSearchParams=URLSearchParams;scope.document={createElement:()=>({})};
 let submit;scope.showManagementModal=(title,html,callback)=>{recoveryNodes.clear();submit=callback;node('term-recovery-query').value='Test & Reise';};
 const recoveryCalls=[];scope.api=async(url,options={})=>{recoveryCalls.push({url,options});return {items:[{id:'77',name:'Gefundener Termin',start:'2027-01-01T09:00:00Z',end:'2027-01-01T12:00:00Z',canceled:false}],nextOffset:null}};
 scope.loadWorkflowStatus=async()=>{};
 await scope.openTermRecovery(trip);assert.equal(recoveryCalls.length,1);assert(recoveryCalls[0].url.includes('/term/catalog/events?'));assert(recoveryCalls[0].url.includes('q=Test+%26+Reise'));assert.equal(node('management-submit').disabled,true);
 await assert.rejects(submit(),/Suchtreffern auswählen/);node('term-recovery-results').children[0].onclick();assert.equal(node('management-submit').disabled,false);await submit();
 assert.equal(recoveryCalls.length,2);assert.equal(recoveryCalls[1].url,'/api/trips/2/term/link');assert.equal(JSON.parse(recoveryCalls[1].options.body).externalId,'77');assert(recoveryCalls.every(c=>!c.url.endsWith('/request')));count++;
 let resolveSearch;scope.api=()=>new Promise(resolve=>{resolveSearch=resolve});const pending=scope.openTermRecovery(trip);await Promise.resolve();node('term-recovery-query').oninput();resolveSearch({items:[{id:'99',name:'Stale'}],nextOffset:null});await pending;assert.equal(node('term-recovery-results').children.length,0);assert.equal(node('management-submit').disabled,true);count++;
 const managementSource=fs.readFileSync('public/assets/js/management.js','utf8');vm.runInContext(managementSource.slice(managementSource.indexOf('function managementMessage'),managementSource.indexOf('async function performManagement')),scope);
 assert.match(scope.managementMessage(new Error(JSON.stringify({error:'confirmed_term_required'}))),/Terminsuche|Suche zuordnen/);assert.match(scope.managementMessage(new Error(JSON.stringify({error:'term_job_in_progress'}))),/kurz warten/);count++;
 const resumeCalls=[];scope.api=async(url,options={})=>{resumeCalls.push({url,options});return {job:{id:7,attempts:3,status:'unknown'},link:null}};scope.state={currentTripId:2,currentTrip:trip};scope.loadWorkflowStatus=async()=>{};
 await scope.resumeTermSetup(trip);assert.deepEqual(resumeCalls.map(c=>c.url),['/api/trips/2/term','/api/trips/2/term/resume']);assert.deepEqual(JSON.parse(resumeCalls[1].options.body),{jobId:7,attempts:3,confirmed:true});count++;
 resumeCalls.length=0;scope.api=async(url,options={})=>{resumeCalls.push({url,options});return {link:{externalId:'77'},job:{id:7,attempts:4,status:'succeeded'}}};await scope.resumeTermSetup(trip);assert.equal(resumeCalls.length,1);count++;
 console.log(`${count} term setup scenarios passed.`);
})().catch(error=>{console.error(error);process.exit(1)});
