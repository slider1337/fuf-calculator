/**
 * Findet Elemente, die auf schmalen Viewports seitlich herausragen.
 *
 *   npm i -D playwright
 *   node tools/check-overflow.js http://localhost:8000/ 390
 *
 * Exit-Code 1, wenn etwas herausragt – damit als CI-Schritt nutzbar.
 */
const { chromium } = require('playwright');

const url = process.argv[2] || 'http://localhost:8000/';
const width = parseInt(process.argv[3] || '390', 10);

(async () => {
  const browser = await chromium.launch();
  const page = await browser.newPage({ viewport: { width, height: 844 } });
  await page.goto(url, { waitUntil: 'networkidle' });
  await page.waitForTimeout(500);

  const hits = await page.evaluate(() => {
    const out = [];
    const docW = document.documentElement.clientWidth;
    document.querySelectorAll('body *').forEach((el) => {
      const r = el.getBoundingClientRect();
      if (r.width === 0 || r.height === 0) return;
      const st = getComputedStyle(el);
      if (st.position === 'fixed' || st.overflowX === 'auto' || st.overflowX === 'scroll') return;
      if (r.right > docW + 0.5 || r.left < -0.5) {
        out.push({
          sel: el.tagName.toLowerCase() +
               (el.id ? '#' + el.id : '') +
               (typeof el.className === 'string' && el.className ? '.' + el.className.trim().split(/\s+/).join('.') : ''),
          over: Math.round(Math.max(r.right - docW, -r.left)),
          text: (el.textContent || '').trim().slice(0, 50).replace(/\s+/g, ' '),
        });
      }
    });
    return out;
  });

  if (hits.length === 0) {
    console.log(`OK – nichts ragt bei ${width} px heraus.`);
    await browser.close();
    return;
  }

  console.log(`${hits.length} Element(e) ragen bei ${width} px heraus:\n`);
  hits.slice(0, 40).forEach((h) => console.log(`  +${h.over}px  ${h.sel}\n           "${h.text}"`));
  console.log(`
Die beiden häufigsten Ursachen:
  1. Grid-Spur "1fr" statt "minmax(0, 1fr)" – 1fr schrumpft nicht unter die Inhaltsbreite.
  2. Flex-Kind ohne "min-width: 0" – auch dort ist die Mindestbreite der Inhalt.
`);
  await browser.close();
  process.exit(1);
})();
