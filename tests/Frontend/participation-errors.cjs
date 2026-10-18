const assert=require('node:assert/strict'),fs=require('node:fs'),vm=require('node:vm');
const scope={JSON};vm.createContext(scope);vm.runInContext(fs.readFileSync('public/assets/js/participation.js','utf8'),scope);
const text=scope.participationJobMessage({status:'unknown',attempts:24,errorCode:'external_result_unproven',initialErrorCode:'external_guest_reference_conflict',validationErrorsJson:'[{"field":"companion_name","reason":"required"}]'});
assert.match(text,/Gast/);assert.match(text,/Gastname: fehlt/);assert.match(text,/nicht erneut an/);assert.match(text,/wiederholt nicht lösen/);
assert.match(scope.participationError('new_unmapped_error'),/new_unmapped_error/);
assert.match(scope.participationJobMessage({errorCode:'provider_rejected',validationErrorsJson:'invalid'}),/abgelehnt/);
