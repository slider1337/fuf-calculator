// One standard editor for both term dialogs. Assets are pinned and self-hosted.
function destroyTermEditors(){if(typeof tinymce!=='undefined')tinymce.remove('[data-rich-text]');}
async function mountTermEditors(root){
  if(typeof tinymce==='undefined')throw new Error('Der HTML-Editor konnte nicht geladen werden. Bitte die Seite neu laden.');
  if(typeof tinymce.addI18n==='function')tinymce.addI18n('de',{Undo:'Rückgängig',Redo:'Wiederholen',Paragraph:'Absatz',Headings:'Überschriften',Heading:'Überschrift',Bold:'Fett',Italic:'Kursiv','Bullet list':'Aufzählung','Numbered list':'Nummerierte Liste','Insert/edit link':'Link einfügen/bearbeiten','Remove link':'Link entfernen','Clear formatting':'Formatierung entfernen','Source code':'HTML-Quelltext',Save:'Speichern',Cancel:'Abbrechen',Close:'Schließen',Link:'Link',Text:'Text','Text to display':'Angezeigter Text','Open link in...':'Link öffnen in…','Current window':'Gleichem Fenster','New window':'Neuem Fenster'});
  const targets=[...root.querySelectorAll('textarea[data-rich-text]')];
  for(const target of targets){
    await tinymce.init({target,language:'de',license_key:'gpl',base_url:'/assets/vendor/tinymce',suffix:'.min',
      menubar:false,height:320,plugins:'lists link code',toolbar:'undo redo | blocks | bold italic | bullist numlist | link | removeformat code',
      promotion:false,branding:false,convert_urls:false,content_style:'body { font-family: system-ui, sans-serif; font-size: 16px; }',
      setup(editor){editor.on('change input undo redo',()=>editor.save());}
    });
  }
}
function syncTermEditors(){if(typeof tinymce!=='undefined')tinymce.triggerSave();}
function termDescriptionHtml(value){return value?`<section class="term-description"><h3>Beschreibung</h3><iframe title="Terminbeschreibung" sandbox="" srcdoc="${escapeHtml(value)}" style="width:100%;min-height:180px;border:0"></iframe></section>`:'<p class="help">Noch keine Terminbeschreibung hinterlegt.</p>';}
