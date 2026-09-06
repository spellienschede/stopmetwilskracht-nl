const fs = require('fs');
const path = require('path');
const filePath = path.join(__dirname, 'boek_grippartner.html');
let html = fs.readFileSync(filePath, 'utf8');
const newText = '<p>Dingen die altijd "gewoon zo waren" blijken keuzes te zijn. Gedrag dat je niet zag wordt zichtbaar, en patronen waar je al jaren in zit worden benoemd.</p>';
const idx = html.indexOf('blijken ineens keuzes');
if (idx !== -1) {
  const start = html.lastIndexOf('<p>', idx);
  const end = html.indexOf('</p>', html.indexOf('benoemd.')) + 4;
  html = html.slice(0, start) + newText + html.slice(end);
}
fs.writeFileSync(filePath, html);
console.log('Inleiding gefixt.');
