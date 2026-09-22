/*
 * Findet Elemente, die auf schmalen Viewports seitlich herausragen.
 *
 * Portierung von docs/design-handoff/tools/check-overflow.js auf den npm-freien
 * CDP-Weg von scripts/ui-shot.mjs - das Projekt hat bewusst kein package.json,
 * und der Handoff-Original braucht playwright. Messlogik und Ausgabe sind
 * gleich, dazu kommt die Anmeldung, ohne die es hier nur die Loginseite zu
 * sehen gaebe.
 *
 * Exit-Code 1, wenn etwas herausragt - damit als CI-Schritt nutzbar.
 *
 * Aufruf:
 *   node scripts/check-overflow.mjs --base http://127.0.0.1:8123 \
 *     --email demo@fuf-erding.de --password demo12345 \
 *     --width 390 --width 375 / /trips/1
 */

import { evalJson, login, navigate, prepareSession, sleep, withChrome } from './lib/browser.mjs';

function parseArgs(argv) {
  const opts = {
    base: 'http://127.0.0.1:8123',
    email: 'demo@fuf-erding.de',
    password: 'demo12345',
    widths: [],
    eval: null,
    paths: [],
  };

  for (let i = 0; i < argv.length; i += 1) {
    const arg = argv[i];
    if (arg === '--base') { opts.base = argv[++i]; continue; }
    if (arg === '--email') { opts.email = argv[++i]; continue; }
    if (arg === '--password') { opts.password = argv[++i]; continue; }
    if (arg === '--width') { opts.widths.push(Number(argv[++i])); continue; }
    if (arg === '--eval') { opts.eval = argv[++i]; continue; }
    opts.paths.push(arg);
  }

  // 390 = iPhone 14/15, 375 = iPhone SE, der engste realistische Fall.
  if (opts.widths.length === 0) opts.widths = [390, 375];
  if (opts.paths.length === 0) opts.paths = ['/'];

  return opts;
}

// Laeuft im Browser. Position fixed und eigene Scrollcontainer sind
// ausgenommen: die duerfen breiter sein als das Dokument.
//
// Unsichtbares ebenso: ein geschlossenes Offcanvas-Panel parkt per
// translateX(100%) rechts neben dem Viewport. Sein Rahmen ist zwar fixed und
// faellt schon durch die Zeile darunter, seine Kinder aber nicht - die zaehlten
// sonst allesamt als Ueberlauf. visibility vererbt sich, ein Test deckt deshalb
// den ganzen Teilbaum ab. Ein geoeffnetes Panel wird weiter gemessen.
const MEASURE = `(() => {
  const out = [];
  const docW = document.documentElement.clientWidth;
  document.querySelectorAll('body *').forEach((el) => {
    const r = el.getBoundingClientRect();
    if (r.width === 0 || r.height === 0) return;
    const st = getComputedStyle(el);
    if (st.visibility === 'hidden') return;
    if (st.position === 'fixed' || st.overflowX === 'auto' || st.overflowX === 'scroll') return;
    if (r.right > docW + 0.5 || r.left < -0.5) {
      out.push({
        sel: el.tagName.toLowerCase() +
             (el.id ? '#' + el.id : '') +
             (typeof el.className === 'string' && el.className ? '.' + el.className.trim().split(/\\s+/).join('.') : ''),
        over: Math.round(Math.max(r.right - docW, -r.left)),
        text: (el.textContent || '').trim().slice(0, 50).replace(/\\s+/g, ' '),
      });
    }
  });
  return out;
})()`;

const URSACHEN = `
Die beiden haeufigsten Ursachen:
  1. Grid-Spur "1fr" statt "minmax(0, 1fr)" - 1fr schrumpft nicht unter die Inhaltsbreite.
  2. Flex-Kind ohne "min-width: 0" - auch dort ist die Mindestbreite der Inhalt.
`;

async function main() {
  const opts = parseArgs(process.argv.slice(2));
  const cookie = await login(opts.base, opts.email, opts.password);
  const { origin } = new URL(opts.base);

  let failures = 0;

  // Ein Browser fuer alle Breiten: den Viewport stellt die Emulation, nicht das
  // Fenster.
  await withChrome({ width: Math.max(...opts.widths), height: 844 }, async (cdp) => {
    for (const width of opts.widths) {
      await prepareSession(cdp, opts.base, cookie, { width, height: 844 });

      for (const path of opts.paths) {
        await navigate(cdp, `${origin}${path}`);
        if (opts.eval) {
          await cdp.send('Runtime.evaluate', { expression: opts.eval, awaitPromise: true });
          await sleep(300);
        }

        const hits = await evalJson(cdp, MEASURE);

        if (hits.length === 0) {
          console.log(`OK - ${path} bei ${width} px: nichts ragt heraus.`);
          continue;
        }

        failures += 1;
        console.log(`\n${hits.length} Element(e) ragen bei ${path} / ${width} px heraus:\n`);
        hits.slice(0, 40).forEach((h) => console.log(`  +${h.over}px  ${h.sel}\n           "${h.text}"`));
      }
    }
  });

  if (failures > 0) {
    console.log(URSACHEN);
    process.exit(1);
  }
}

await main();
