const assert=require('node:assert/strict'),fs=require('node:fs'),vm=require('node:vm');
let config,listener,saves=0,synced=0,removed=0;
const scope={escapeHtml:s=>String(s).replaceAll('&','&amp;').replaceAll('"','&quot;').replaceAll('<','&lt;').replaceAll('>','&gt;'),tinymce:{init:async c=>{config=c;c.setup({on:(name,fn)=>{listener=fn},save:()=>saves++})},triggerSave:()=>synced++,remove:()=>removed++}};
vm.createContext(scope);vm.runInContext(fs.readFileSync('public/assets/js/rich-text.js','utf8'),scope);
(async()=>{
 const textarea={};await scope.mountTermEditors({querySelectorAll:()=>[textarea]});
 assert.equal(config.target,textarea);assert.equal(config.license_key,'gpl');assert.equal(config.base_url,'/assets/vendor/tinymce');assert.equal(config.plugins,'lists link code');
 listener();assert.equal(saves,1);scope.syncTermEditors();assert.equal(synced,1);scope.destroyTermEditors();assert.equal(removed,1);
 const html=scope.termDescriptionHtml('<p><strong>Reise</strong></p><script>bad()</script>');assert(html.includes('sandbox=""'));assert(!html.includes('<script>'));assert(html.includes('&lt;strong&gt;Reise'));
 console.log('Standard editor: self-hosting, save synchronization, lifecycle and isolated HTML preview passed.');
})().catch(e=>{console.error(e);process.exitCode=1});
