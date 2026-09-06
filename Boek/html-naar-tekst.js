const fs = require('fs');
const path = require('path');

const htmlPath = path.join(__dirname, 'boek_grippartner.html');
const txtPath = path.join(__dirname, 'Grippartner_boek_tekst.txt');

let html = fs.readFileSync(htmlPath, 'utf8');

// Alleen body content: tussen <body> en </body>, zonder style/script
const bodyMatch = html.match(/<body[^>]*>([\s\S]*?)<\/body>/i);
if (!bodyMatch) {
  console.error('Geen body gevonden');
  process.exit(1);
}
let text = bodyMatch[1];

// Decode HTML entities (voor we tags strippen)
const entities = [
  [/&quot;/g, '"'],
  [/&#x27;/g, "'"],
  [/&#39;/g, "'"],
  [/&amp;/g, '&'],
  [/&lt;/g, '<'],
  [/&gt;/g, '>'],
  [/&nbsp;/g, ' '],
];
entities.forEach(([regex, replacement]) => {
  text = text.replace(regex, replacement);
});

// Cover: titel + ondertitel
text = text.replace(/<div class="cover">\s*<h1>([^<]*)<\/h1>\s*<p[^>]*>([^<]*)<\/p>\s*<\/div>/i, (_, titel, ondertitel) => {
  return '\n' + titel.trim() + '\n' + ondertitel.trim() + '\n\n' + '='.repeat(60) + '\n\n';
});

// Inhoud (nav): kop + regels
text = text.replace(/<nav[^>]*>[\s\S]*?<h2[^>]*>([^<]*)<\/h2>([\s\S]*?)<\/nav>/i, (_, kop, inhoud) => {
  let out = '\nINHOUD\n\n' + kop.trim() + '\n\n';
  const ps = inhoud.match(/<p>([^<]*)<\/p>/g) || [];
  ps.forEach(p => {
    const m = p.match(/<p>([\s\S]*?)<\/p>/);
    if (m) out += m[1].trim() + '\n';
  });
  return out + '\n' + '='.repeat(60) + '\n\n';
});

// Secties: chapter title + paragrafen
text = text.replace(/<section[^>]*id="([^"]*)"[^>]*>\s*<h2[^>]*>([^<]*)<\/h2>/gi, (_, id, title) => {
  return '\n\n--- ' + title.trim() + ' ---\n\n';
});

// Paragrafen: <p>...</p> -> regel + lege regel. <br> binnen p -> newline
text = text.replace(/<p>([\s\S]*?)<\/p>/gi, (_, inhoud) => {
  let block = inhoud.replace(/<br\s*\/?>\s*/gi, '\n');
  block = block.replace(/<[^>]+>/g, '').trim();
  block = block.replace(/\n{2,}/g, '\n');
  return block + '\n\n';
});

// Overgebleven tags (bijv. in back div) strippen
text = text.replace(/<[^>]+>/g, '');
text = text.replace(/&[#\w]+;/g, ' ');

// Back cover
text = text.replace(/\s*<div class="back">/i, '\n\n' + '='.repeat(60) + '\n\n');
text = text.replace(/<[^>]+>/g, '');
text = text.replace(/&[#\w]+;/g, ' ');

// Meerdere lege regels naar max 2
text = text.replace(/\n{3,}/g, '\n\n');
// Ruimte aan begin/einde
text = text.trim() + '\n';

fs.writeFileSync(txtPath, text, 'utf8');
console.log('Tekstbestand opgeslagen:', txtPath);
