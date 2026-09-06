<?php
$lines = file('C:/Users/korne/.cursor/projects/c-laragon-www-kornepot-nl/agent-transcripts/f7e1caab-bd41-44b2-ac50-40cc252ae1ee/f7e1caab-bd41-44b2-ac50-40cc252ae1ee.jsonl');
$j = json_decode($lines[0], true);
$t = $j['message']['content'][0]['text'];
$pos = strpos($t, '17. PROMO');
if ($pos === false) $pos = strpos($t, 'PROMO-WORKFLOW');
if ($pos === false) $pos = strpos($t, 'sectie 17');
echo "pos=$pos\n";
if ($pos !== false) echo substr($t, $pos, 6000);
$pos2 = strpos($t, 'Ideegoedkeuring');
echo "\n\npos2=$pos2\n";
if ($pos2 !== false) echo substr($t, $pos2, 2000);
