const fs = require('fs');
const path = require('path');
const filePath = path.join(__dirname, 'boek_grippartner.html');
let html = fs.readFileSync(filePath, 'utf8');

const startTag = '<section class="chapter" id="ch8">';
const startIdx = html.indexOf(startTag);
if (startIdx === -1) {
  console.error('ch8 start not found');
  process.exit(1);
}
const afterCh8 = html.slice(startIdx);
const endMatch = afterCh8.match(/\n<\/section>\s*\n\s*<section class="chapter" id="ch9">/);
const endIdx = endMatch ? startIdx + endMatch.index : -1;
const endTag = endMatch ? endMatch[0] : '';
if (endIdx === -1 || !endTag) {
  console.error('ch8 end not found');
  process.exit(1);
}

const newCh8 = `<section class="chapter" id="ch8">
<h2 class="chapter-title">De Eisenhower Matrix</h2>
<p>In 1954 hield Dwight D. Eisenhower een toespraak in Evanston, voor de World Council of Churches. Hij zei iets dat sindsdien talloze boeken en cursussen over productiviteit heeft gevoed: &quot;Ik heb twee soorten problemen: de urgent en de important. Het urgent is niet belangrijk, en het important is nooit urgent.&quot; Hij doelde op het dilemma van de moderne mens: we laten ons meeslepen door wat nu schreeuwt om aandacht, terwijl wat er echt toe doet vaak kan wachten – tot het te laat is. De Eisenhower Matrix helpt je om dat onderscheid te maken. En een grippartner? Die helpt je om er wekelijks naar te kijken: wat was urgent maar niet belangrijk, en wat heb je gedaan dat wél belangrijk was?</p>
<p>De Eisenhower-matrix is zo'n concept dat je overal tegenkomt. Net als boekenplanken die je op kleur sorteert – ziet er mooi uit, maar zegt weinig over de inhoud – zo voelt het soms ook met deze theorie. De klassieke uitleg ken je waarschijnlijk: kwadrant 1 (urgent en belangrijk) is doen, kwadrant 2 (belangrijk en niet urgent) is plannen, kwadrant 3 (urgent en niet belangrijk) is delegeren, kwadrant 4 (niet urgent en niet belangrijk) is elimineren. Klinkt logisch. Veel mensen lezen dit, knikken en proberen het toe te passen. Maar hier gaat het mis: als je het hierbij laat, mis je de kern.</p>
<p>De standaard uitleg suggereert dat het draait om wat je doet met taken. Maar daar zit de denkfout. Het gaat namelijk niet primair om wat je doet; het gaat om hoeveel tijd je ergens voor overlaat. De wet van Parkinson zegt: werk dijt uit naar de tijd die ervoor beschikbaar is. Ik noem het zelf liever de wet van de laatste dag voor vakantie. Op je laatste werkdag voor vakantie krijg je in één dag meer gedaan dan normaal in twee of drie dagen. Niet omdat je ineens slimmer bent, maar omdat je minder tijd hebt. Je denkt minder na, je twijfelt minder, je doet gewoon – en voilà, productiviteit.</p>
<p>De echte sleutel is niet doen, plannen, delegeren of elimineren. De crux is: hoe meer tijd je geeft aan kwadrant 2, hoe minder tijd er overblijft voor de rest. Kwadrant 2 is waar de magie zit: bouwen aan je bedrijf, investeren in relaties, werken aan je gezondheid, nadenken, reflecteren, verbeteren. Maar kwadrant 2 heeft één groot probleem: het kan altijd morgen. En als morgen niet lukt, dan volgende week. Onze hersenen zijn extreem goed in het reageren op urgentie, maar behoorlijk matig in het starten van wat écht belangrijk is.</p>
<p>Kwadrant 2-taken zijn vaak moeilijker, minder concreet en minder belonend op korte termijn. Brian Tracy noemt dit "eat that frog"-taken: je moet er even doorheen. Dus wat doet je brein? Het kiest voor e-mail, voor appjes, voor kleine brandjes. Want dat voelt productief. Maar dat is schijn. Ik was zelf jarenlang uren per dag bezig met e-mail, gemiddeld zo'n drie uur. E-mail voelt urgent en belangrijk, maar meestal is het dat niet. Dankzij mijn grippartner ging ik meer tijd blokkeren voor kwadrant 2 – en dus bleef er automatisch minder tijd over voor e-mail. Ik checkte nog maar één keer per dag mijn mail. Mijn e-mailtijd ging van drie uur naar 45 minuten. Alles wat echt belangrijk was, werd nog steeds gewoon afgehandeld. Het belangrijkste verschil: ik leefde minder gejaagd en kreeg eindelijk ruimte voor wat er écht toe doet.</p>
<p>De meeste mensen proberen kwadrant 3 en 4 te verminderen. Maar dat is dweilen met de kraan open. De betere strategie is: vergroot kwadrant 2 en laat de rest zich aanpassen. Minder tijd leidt tot snellere beslissingen, minder ruimte tot minder ruis, minder focus op onbelangrijke dingen tot meer impact. De wet van Parkinson gaat dan vóór je werken in plaats van tegen je. Niet "Ik ga kwadrant 2 plannen", maar: ik ga kwadrant 2 domineren in mijn agenda. Bijvoorbeeld elke ochtend twee uur deep work, vaste blokken voor reflectie, tijd reserveren voor strategie. En dan de rest laten "passen" in de overgebleven tijd.</p>
<p>Dit is precies waar het voor de meeste mensen stukloopt. Je weet het wel, maar je doet het niet. Een grippartner houdt je eerlijk ("Waarom zit je weer in je mail?") en ziet wat jij niet ziet – jouw blinde vlekken, die zak op je rug uit de oude fabel. Daardoor word je niet alleen productiever; je wordt bewuster. En dat is waar echte groei zit. De Eisenhower-matrix gaat niet over taken, maar over tijd. Niet kwadrant 2 plannen, maar kwadrant 2 maximaliseren zodat de rest vanzelf krimpt. Als je dat eenmaal ervaart, voelt het bijna vreemd dat je ooit anders hebt gewerkt. De vraag is dus niet wat je morgen gaat doen. De vraag is: hoeveel ruimte geef jij aan wat echt belangrijk is?</p>
</section>
`;

const before = html.slice(0, startIdx);
const after = html.slice(endIdx + endTag.length);
html = before + newCh8 + '\n\n<section class="chapter" id="ch9">' + after;
fs.writeFileSync(filePath, html);
console.log('Ch8 Eisenhower herschreven naar alinea-stijl.');
