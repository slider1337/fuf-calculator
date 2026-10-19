const assert=require('node:assert/strict'),fs=require('node:fs'),vm=require('node:vm');
const inputs=[{dataset:{participationPerson:'1'},checked:false},{dataset:{participationPerson:'2'},checked:false}];
const nodes={'participation-kind':{},'participation-select-all':{},'participation-price-1':{value:'21'},'participation-price-2':{value:'22'},'management-fields':{querySelectorAll:selector=>selector.includes(':checked')?inputs.filter(p=>p.checked):inputs}};
let modal,posted;
const scope={crypto:{randomUUID:()=> 'test-dialog-key'},JSON,escapeHtml:s=>String(s??'').replaceAll('<','&lt;'),byId:id=>nodes[id],state:{currentTrip:{}},loadRegistrations:async()=>{},loadWorkflowStatus:async()=>{},showManagementModal:(title,html,submit,label)=>{modal={title,html,submit,label}},api:async(url,options)=>{if(options){posted=JSON.parse(options.body);return {}}return {priceGroups:[{id:'21',label:'Erwachsener',price:'208'},{id:'22',label:'Gast <Kind>',price:'115'}],jobs:[]}}};
vm.createContext(scope);vm.runInContext(fs.readFileSync('public/assets/js/participation.js','utf8'),scope);
(async()=>{
 const booking={tripId:5,id:1,revision:1,persons:[{id:1,name:'Mitglied',memberReference:'123',priceGroupId:'21'},{id:2,name:'Gast',memberReference:'123',guestId:7,priceGroupId:'22'}]};
 await scope.openParticipationRequest(booking);
 assert.equal(modal.title,'Personen an easyVerein übertragen');assert.equal(modal.label,'Jetzt übertragen');
 assert.match(modal.html,/Erwachsener · 208 €/);assert.match(modal.html,/Gast &lt;Kind> · 115 €/);assert.doesNotMatch(modal.html,/>#21|participation-member-|Mitglieds-ID/);
 assert.match(modal.html,/Alle Personen auswählen/);nodes['participation-select-all'].onclick();assert.ok(inputs.every(i=>i.checked));
 await modal.submit();assert.equal(posted.persons.length,2);assert.equal(posted.persons[1].priceGroupId,'22');assert.ok(posted.persons.every(p=>!Object.hasOwn(p,'memberReference')));
 await scope.openParticipationRequest(booking,'cancel');assert.equal(nodes['participation-kind'].value,'cancel');assert.equal(modal.label,'Stornieren');
 scope.api=async()=>{throw Error('{"error":"person_operation_in_progress"}')};await assert.rejects(modal.submit(),/bereits eine andere Aktion/);
 assert.doesNotMatch(fs.readFileSync('public/assets/js/bookings.js','utf8'),/id="booking-room"|Zimmer zuweisen|Teilnahmeauftrag vorbereiten/);
 console.log('Participation dialog: names, immutable identity, select all, submission, cancellation and conflict message passed.');
})().catch(error=>{console.error(error);process.exitCode=1});
