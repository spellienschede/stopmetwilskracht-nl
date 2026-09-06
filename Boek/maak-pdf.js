const puppeteer = require('puppeteer');
const path = require('path');
const fs = require('fs');

const dir = __dirname;
const htmlPath = path.join(dir, 'boek_grippartner.html');
const pdfPath = path.join(dir, 'Grippartner_boek.pdf');

if (!fs.existsSync(htmlPath)) {
  console.error('Bestand niet gevonden:', htmlPath);
  process.exit(1);
}

(async () => {
  const browser = await puppeteer.launch({ headless: 'new' });
  const page = await browser.newPage();
  await page.goto('file://' + htmlPath.replace(/\\/g, '/'), {
    waitUntil: 'networkidle0'
  });
  await page.pdf({
    path: pdfPath,
    format: 'A4',
    printBackground: true,
    margin: { top: '20mm', right: '20mm', bottom: '20mm', left: '20mm' }
  });
  await browser.close();
  console.log('PDF opgeslagen:', pdfPath);
})();
