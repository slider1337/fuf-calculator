const assert=require('node:assert/strict'),fs=require('node:fs'),vm=require('node:vm');
const scope={JSON};vm.createContext(scope);vm.runInContext(fs.readFileSync('public/assets/js/participation.js','utf8'),scope);
const text=scope.participationJobMessage({status:'unknown',attempts:24,errorCode:'external_result_unproven',initialErrorCode:'external_guest_reference_conflict',validationErrorsJson:'[{"field":"companion_name","reason":"required"}]'});
assert.match(text,/Gast/);assert.match(text,/Gastname: fehlt/);assert.match(text,/nicht erneut an/);assert.match(text,/wiederholt nicht lösen/);
assert.match(scope.participationError('new_unmapped_error'),/new_unmapped_error/);
assert.match(scope.participationJobMessage({errorCode:'provider_rejected',validationErrorsJson:'invalid'}),/abgelehnt/);
// Regression for the exact screenshot: render values, never JavaScript object coercion.
scope.formatCurrency=n=>n.toFixed(2)+' €';scope.valueText=String;
const groups=scope.participationValue('groups',[{id:'21',label:'Erwachsener',price:'85'},{id:'22',label:'Kind',price:'40'}]);
assert.match(groups,/Erwachsener: 85.00 €/);assert.match(groups,/Kind: 40.00 €/);assert(!groups.includes('[object Object]'));
assert.match(scope.participationError('guest_waiting_for_parent'),/automatisch fortgesetzt/);
