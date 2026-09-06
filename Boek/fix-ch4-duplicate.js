const fs = require('fs');
const path = require('path');
const filePath = path.join(__dirname, 'boek_grippartner.html');
let html = fs.readFileSync(filePath, 'utf8');

const startMarker = '<p>2. **Een Gedeelde Structuur in Google Sheets**';
// Match second "Wat wordt jouw eerste stap?" paragraph through <section id="ch5">
const endMatch = html.match(/Ga ervoor, betrek je partner, en ontdek hoe vet het is om samen te groeien\. Wat wordt jouw eerste stap\?<\/p>\s*<\/section>\s*<section class="chapter" id="ch5">/);
const startIdx = html.indexOf(startMarker);
const endIdx = endMatch ? endMatch.index : -1;
const endBlock = endMatch ? endMatch[0] : '';
if (startIdx === -1 || endIdx === -1 || !endBlock) {
  console.error('Markers not found. startIdx:', startIdx, 'endIdx:', endIdx);
  process.exit(1);
}
const before = html.slice(0, startIdx);
const afterPart = html.slice(endIdx + endBlock.length);
const sectionCh5 = '</section>\n\n<section class="chapter" id="ch5">';
html = before + sectionCh5 + afterPart;
fs.writeFileSync(filePath, html);
console.log('Ch4 duplicate block removed.');
