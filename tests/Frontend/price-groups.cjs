const assert=require('node:assert/strict'),fs=require('node:fs'),vm=require('node:vm');
const nodes=new Map();
const scope={byId:id=>{if(!nodes.has(id))nodes.set(id,{});return nodes.get(id)},escapeHtml:String,loadWorkflowStatus:async()=>{},setStatusNote:()=>{}};
vm.createContext(scope);vm.runInContext(fs.readFileSync('public/assets/js/term-updates.js','utf8'),scope);

(async()=>{
 const choices=[{id:'21',label:'Erwachsener',price:'12'},{id:'22',label:'Gast',price:'15'}];let save,request;
 scope.api=async(url,options)=>{if(!options)return {groups:choices};request={url,body:JSON.parse(options.body)};return {status:'succeeded'}};
 scope.showManagementModal=(title,html,callback)=>{assert(html.includes('Bereits gebuchte Preise bleiben erhalten'));save=callback};
 await scope.openPriceGroups({id:2});scope.byId('price-group-choice').value='22';scope.byId('price-group-choice').onchange();
 assert.equal(scope.byId('price-group-price').value,'15');scope.byId('price-group-label').value='Gast neu';scope.byId('price-group-price').value='17.50';
 await save();assert.equal(request.url,'/api/trips/2/term/price-groups/22');assert.deepEqual(request.body,{label:'Gast neu',price:'17.50',beforeLabel:'Gast',beforePrice:'15'});
})().catch(e=>{console.error(e);process.exitCode=1});
