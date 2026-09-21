/*
 * Screenshots und ID-Pruefung fuer die Oberflaeche.
 *
 * Meldet sich per POST /login an, uebergibt das Session-Cookie an ein
 * headless Chrome und fotografiert die angegebenen Routen. Prueft ausserdem,
 * dass alle Elemente vorhanden sind, an die app.js auf Modulebene Listener
 * bindet - genau daran wuerde ein Redesign unbemerkt scheitern.
 *
 * Kommt ohne npm-Pakete aus: Node >= 22 hat WebSocket eingebaut.
 *
 * Aufruf:
 *   node scripts/ui-shot.mjs --base http://127.0.0.1:8123 --out build/screenshots \
 *     --email demo@fuf-erding.de --password demo12345 \
 *     / :liste /trips/1 :planung
 *
 * Mit --eval laeuft vor der Aufnahme noch JavaScript in der Seite. Nur so lassen
 * sich Zustaende fotografieren, die erst durch eine Eingabe entstehen:
 *   --eval "document.getElementById('trip-search').value='xy';
 *           document.getElementById('trip-search').dispatchEvent(new Event('input'))"
 */

import { mkdir, writeFile } from 'node:fs/promises';

import { evalJson, login, navigate, prepareSession, sleep, withChrome } from './lib/browser.mjs';

// Elemente, an die app.js beim Laden des Skripts Listener bindet. Fehlt eines,
// ist der zugehoerige Bedienschritt tot - ohne jede Fehlermeldung.
const REQUIRED_IDS = [
  'actual-expense-cancel-btn', 'actual-expense-form', 'actual-expenses-body',
  'add-participant-row-btn', 'back-to-list-btn', 'calculate-btn',
  'csv-upload-form', 'delete-registrations-btn', 'invite-form',
  'manual-registration-form', 'new-trip-btn', 'open-invite-btn',
  'open-settings-btn', 'open-users-btn', 'recalculate-billings-btn',
  'registrations-accordion', 'remove-participant-row-btn',
  'result-breakdowns-container', 'retentionPercent',
  'sales-apply-btn', 'settings-form', 'tab-abrechnung', 'trip-form',
  'trip-list-body', 'users-table-body',
  // Modal-Wurzeln, die bootstrapPage() an new bootstrap.Modal(...) uebergibt
  'settings-modal', 'invite-modal', 'users-modal',
];

function parseArgs(argv) {
  const opts = {
    base: 'http://127.0.0.1:8123',
    out: 'build/screenshots',
    email: 'demo@fuf-erding.de',
    password: 'demo12345',
    width: 1440,
    height: 1200,
    eval: null,
    targets: [],
  };

  for (let i = 0; i < argv.length; i += 1) {
    const arg = argv[i];
    if (arg === '--base') { opts.base = argv[++i]; continue; }
    if (arg === '--out') { opts.out = argv[++i]; continue; }
    if (arg === '--email') { opts.email = argv[++i]; continue; }
    if (arg === '--password') { opts.password = argv[++i]; continue; }
    if (arg === '--width') { opts.width = Number(argv[++i]); continue; }
    if (arg === '--height') { opts.height = Number(argv[++i]); continue; }
    if (arg === '--eval') { opts.eval = argv[++i]; continue; }
    opts.targets.push(arg);
  }

  // Ziele kommen paarweise: Pfad, dann :name fuer die Datei
  const targets = [];
  for (let i = 0; i < opts.targets.length; i += 2) {
    targets.push({ path: opts.targets[i], name: (opts.targets[i + 1] || '').replace(/^:/, '') });
  }
  opts.targets = targets.length > 0 ? targets : [{ path: '/', name: 'start' }];

  return opts;
}

async function main() {
  const opts = parseArgs(process.argv.slice(2));
  await mkdir(opts.out, { recursive: true });

  const cookie = await login(opts.base, opts.email, opts.password);
  const { origin } = new URL(opts.base);

  let failures = 0;

  await withChrome({ width: opts.width, height: opts.height }, async (cdp) => {
    await prepareSession(cdp, opts.base, cookie, { width: opts.width, height: opts.height });

    // Laufzeitfehler einsammeln, bevor irgendein Skript der Seite laeuft
    await cdp.send('Page.addScriptToEvaluateOnNewDocument', {
      source: `
        window.__fufErrors = [];
        addEventListener('error', (e) => {
          window.__fufErrors.push(String(e.message || e.error));
        });
        addEventListener('unhandledrejection', (e) => {
          window.__fufErrors.push('unhandled rejection: ' + String(e.reason));
        });
      `,
    });

    for (const target of opts.targets) {
      await navigate(cdp, `${origin}${target.path}`);

      // Optionaler Eingriff vor der Aufnahme: Feld fuellen, Section aufklappen.
      if (opts.eval) {
        await cdp.send('Runtime.evaluate', { expression: opts.eval, awaitPromise: true });
        await sleep(300);
      }

      const missing = await evalJson(
        cdp,
        `${JSON.stringify(REQUIRED_IDS)}.filter((id) => !document.getElementById(id))`,
      );
      const jsErrors = await evalJson(cdp, 'window.__fufErrors || []');

      const metrics = await cdp.send('Page.getLayoutMetrics');
      const full = metrics.cssContentSize;

      const shot = await cdp.send('Page.captureScreenshot', {
        format: 'png',
        captureBeyondViewport: true,
        clip: { x: 0, y: 0, width: opts.width, height: Math.min(full.height, 6000), scale: 1 },
      });

      const file = `${opts.out}/${target.name || 'shot'}.png`;
      await writeFile(file, Buffer.from(shot.data, 'base64'));

      const status = missing.length === 0 ? 'ok' : `FEHLEND: ${missing.join(', ')}`;
      console.log(`${target.path} -> ${file} (${Math.round(full.height)}px) ${status}`);

      if (missing.length > 0) failures += 1;
      if (jsErrors.length > 0) {
        console.log(`  JS-Fehler: ${jsErrors.join(' | ')}`);
        failures += 1;
      }
    }
  });

  if (failures > 0) {
    console.error(`\n${failures} Pruefung(en) fehlgeschlagen.`);
    process.exit(1);
  }
}

await main();
