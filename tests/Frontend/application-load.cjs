// Execute the real template's scripts in order using an inert DOM and no network.
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');

class ElementFixture {
  constructor(id = '') {
    this.id = id;
    this.value = '';
    this.dataset = {};
    this.listeners = new Map();
    this.classList = {add() {}, remove() {}, toggle() {}, contains() {return false;}};
  }
  addEventListener(type, callback) {
    const callbacks = this.listeners.get(type) || [];
    callbacks.push(callback);
    this.listeners.set(type, callbacks);
  }
  querySelectorAll() {return [];}
  querySelector() {return null;}
  getAttribute() {return null;}
}

function load(readyState = 'loading', beforeStartup = () => {}) {
  const nodes = new Map(), document = new ElementFixture(), window = new ElementFixture();
  document.readyState = readyState;
  document.getElementById = id => {
    if (!nodes.has(id)) nodes.set(id, new ElementFixture(id));
    return nodes.get(id);
  };
  document.body = new ElementFixture();
  window.location = {pathname: '/', hash: ''};
  const scope = {document, window, Element: ElementFixture, HTMLElement: ElementFixture,
    HTMLInputElement: ElementFixture, URLSearchParams, Intl, Date, JSON, console,
    fetch: () => {throw new Error('Unexpected network call');}};
  vm.createContext(scope);
  const template = fs.readFileSync('templates/index.html.php', 'utf8');
  const scripts = [...template.matchAll(/<script src="(\/assets\/js\/[^?]+)\?/g)].map(match => match[1]);
  assert.equal(new Set(scripts).size, scripts.length, 'Each feature is included once');
  assert.equal(scripts.at(-1), '/assets/js/app.js', 'Startup follows feature definitions');
  for (const script of scripts) {
    if (script === '/assets/js/app.js') beforeStartup(scope);
    vm.runInContext(fs.readFileSync(`public${script}`, 'utf8'), scope, {filename: script});
  }
  return {scope, nodes, document};
}

(async () => {
  const {scope, nodes, document} = load();
  let boots = 0;
  scope.bootstrapPage = async () => {boots++;};
  assert.equal(boots, 0, 'Boot waits until the DOM is ready');
  const ready = document.listeners.get('DOMContentLoaded');
  assert.equal(ready.length, 1);
  ready[0]();
  ready[0]();
  assert.equal(boots, 1, 'Initialization is idempotent');
  assert.equal(nodes.get('trip-form').listeners.get('submit').length, 1);
  assert.equal(nodes.get('csv-upload-form').listeners.get('submit').length, 1);
  assert.equal(nodes.get('manual-registration-form').listeners.get('submit').length, 1);
  assert.equal(nodes.get('actual-expense-form').listeners.get('submit').length, 1);
  assert.equal(nodes.get('trip-archive-filter').listeners.get('change').length, 1);

  assert.equal(scope.matchRoute('/trips/new').name, 'new-trip');
  assert.equal(scope.matchRoute('/trips/42').tripId, 42);
  assert.equal(scope.matchRoute('/events/9').eventId, 9);
  assert.equal(scope.parseDecimal('1.234,56'), 1234.56);
  assert.equal(scope.ageAtArrival('2008-10-17', '2026-10-16'), 17);
  assert.equal(scope.escapeHtml('<script>"&'), '&lt;script&gt;&quot;&amp;');

  const navigation = [];
  scope.navigateTo = async path => navigation.push(path);
  await nodes.get('trip-head-back-btn').listeners.get('click')[0]({preventDefault() {}});
  assert.deepEqual(navigation, ['/']);
  let previews = 0;
  scope.renderLivePreview = () => {previews++;};
  // The existing planning input action remains connected after moving it.
  const input = nodes.get('trip-form').listeners.get('input');
  assert.equal(input.length, 1);
  scope.renderSectionSummaries = () => {};
  scope.renderDirtyState = () => {};
  scope.renderHeadKpi = () => {};
  input[0]();
  assert.equal(previews, 1);
  // Real lifecycle listeners: successful writes leave the modal and return to the list.
  vm.runInContext("state.currentTrip={id:2,archivedAt:null};",scope);
  scope.dirtyPlanning=()=>false;scope.performManagement=fn=>fn();
  let submit,markup,hidden=0;const writes=[];
  scope.showManagementModal=(title,html,fn)=>{markup=html;submit=fn;};
  vm.runInContext('management.modal={hide:()=>hideLifecycleModal()};',scope);scope.hideLifecycleModal=()=>hidden++;
  scope.api=async(url,options)=>{writes.push({url,options});return {archivedAt:'2026-10-17'};};
  scope.openTrip=async()=>{throw Error('Archived trip must not be reopened after save');};
  await nodes.get('trip-archive').listeners.get('click')[0]();assert(!markup.includes('action-reason'));await submit();
  assert.equal(writes[0].url,'/api/trips/2/archive');assert.equal(navigation.at(-1),'/');assert.equal(hidden,1);
  await nodes.get('trip-delete').listeners.get('click')[0]();assert(markup.includes('easyVerein bleiben bestehen'));await submit();
  assert.equal(writes[1].options.method,'DELETE');assert.deepEqual(JSON.parse(writes[1].options.body),{confirmed:true});assert.equal(hidden,2);
  const startup = [];
  const complete = load('complete', context => {
    context.window.bootstrap = {Modal: class {}};
    for (const name of ['loadCurrentUser', 'loadSettings', 'loadTripList', 'renderCurrentRoute']) {
      context[name] = async () => {startup.push(name);};
    }
    for (const name of ['observeHeadScroll', 'observeSheetSwipe']) context[name] = () => {startup.push(name);};
  });
  await new Promise(setImmediate);
  assert.deepEqual(startup, ['loadCurrentUser', 'loadSettings', 'loadTripList', 'observeHeadScroll', 'observeSheetSwipe', 'renderCurrentRoute']);
  complete.scope.initializeApp();
  assert.equal(startup.length, 6, 'Already-ready documents boot only once');
  console.log('Application script order, startup, navigation and feature wiring passed.');
})().catch(error => {console.error(error);process.exitCode = 1;});
