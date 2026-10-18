const assert=require('node:assert/strict'),fs=require('node:fs'),vm=require('node:vm');
const scope={bookingUI:{priceGroups:[{id:'21',label:'Erwachsene'}]},document:{createElement:()=>({dataset:{},setAttribute(){}})},escapeHtml:s=>String(s??''),formatCurrency:n=>`${n} €`,formatDate:s=>s||'',ageAtArrival:()=>null,categoryShortLabels:{ADULT_DOUBLE:'Erwachsene'},round2:n=>n};
vm.createContext(scope);vm.runInContext(fs.readFileSync('public/assets/js/registrations.js','utf8'),scope);
vm.runInContext(`
 const cancelled={id:1,source:'website',participants:[{id:7,name:'Abgemeldet',status:'cancelled'}],billingItems:[],billingTotal:null};
 const active={id:2,source:'website',participants:[{id:8,name:'Mitglied',status:'confirmed'},{id:9,name:'Kind',status:'cancelled'}],billingItems:[],billingTotal:null,bookingPeople:[{id:8,priceGroupId:'21',expectedPrice:'12'},{id:9,priceGroupId:'21',expectedPrice:'12'}],rooms:[{category:'2-Bettzimmer',personIds:[8]}],bookingTotal:12};
 globalThis.result={count:activeRegistrations([cancelled,active]).length,persons:activeRegistrationPeople(active).length,html:registrationItem(active,0,'2027-01-01',18).innerHTML,cancelHtml:registrationItem(cancelled,1,'2027-01-01',18).innerHTML};
`,scope);
assert.equal(scope.result.count,1);assert.equal(scope.result.persons,1);
assert.match(scope.result.html,/Website/);assert.match(scope.result.html,/Zimmer 1: 2-Bettzimmer/);assert.match(scope.result.html,/1 Pers\./);
assert.match(scope.result.html,/data-edit-registration="2"/);assert.match(scope.result.html,/12 €/);assert.match(scope.result.cancelHtml,/Storniert/);
console.log('Booking list: website source, editing, saved room requests, accepted prices and cancelled people passed.');
