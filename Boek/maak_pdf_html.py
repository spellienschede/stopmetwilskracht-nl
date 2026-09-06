# -*- coding: utf-8 -*-
# Genereert boek_grippartner.html voor print naar PDF (Browser: Afdrukken > Opslaan als PDF)
import os
import html

BOEK_DIR = os.path.dirname(os.path.abspath(__file__))
CHAPTER_ORDER = [
    "inleiding.txt",
    "Wat is een grippartner.txt",
    "Hoe werkt een grippartner.txt",
    "Dit is hoe ik het doe.txt",
    "100 procent is makkelijker dan 90 % .txt",
    "Het vinden van een grippartner.txt",
    "Leuke vragen voor je grippartner.txt",
    "De Eisenhower Matrix.txt",
    "Het LNO framework.txt",
    "Hoe zet je plannen om in actie.txt",
    "Wanneer ben je een goede grippartner.txt",
    "Veel gemaakte fouten.txt",
]

VOORKANT_TITEL = "Grippartner"
VOORKANT_ONDERTITEL = "Waarom niemand op zoek is naar een Grippartner, maar iedereen er één nodig heeft"
ACHTERKANT_TEKST = "Stap uit de urgentie-valkuil en krijg grip"
ACHTERKANT_DOELGROEP = "Voor iedereen die met een grippartner meer uit zijn of haar leven wil halen."


def escape_and_paragraphs(content):
    """Zet platte tekst om in HTML paragrafen, met escape."""
    if not content or not content.strip():
        return ""
    out = []
    for block in content.split("\n\n"):
        block = block.strip()
        if not block:
            continue
        line = html.escape(block).replace("\n", "<br>\n")
        out.append(f"<p>{line}</p>")
    return "\n".join(out)


def main():
    chapters_html = []
    for i, filename in enumerate(CHAPTER_ORDER):
        path = os.path.join(BOEK_DIR, filename)
        if not os.path.isfile(path):
            continue
        with open(path, "r", encoding="utf-8") as f:
            raw = f.read()
        title = filename.replace(".txt", "")
        body = escape_and_paragraphs(raw)
        chapters_html.append(
            f'<section class="chapter" id="ch{i+1}">\n'
            f'<h2 class="chapter-title">{html.escape(title)}</h2>\n{body}\n</section>'
        )

    html_content = "\n\n".join(chapters_html)

    full_html = f"""<!DOCTYPE html>
<html lang="nl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Grippartner – Boek</title>
<style>
  * {{ box-sizing: border-box; }}
  body {{ font-family: Georgia, 'Times New Roman', serif; font-size: 11pt; line-height: 1.6; color: #1a1a1a; margin: 0; padding: 0; }}
  .cover, .back {{ min-height: 100vh; display: flex; flex-direction: column; justify-content: center; align-items: center; text-align: center; padding: 2rem; page-break-after: always; }}
  .cover {{ background: linear-gradient(160deg, #2d3748 0%, #1a202c 100%); color: #fff; }}
  .cover h1 {{ font-size: 2.5rem; margin: 0 0 1rem; font-weight: 600; }}
  .cover .subtitle {{ font-size: 1.15rem; max-width: 28em; line-height: 1.5; opacity: 0.95; }}
  .back {{ background: #1a202c; color: #e2e8f0; }}
  .back .tagline {{ font-size: 1.4rem; margin-bottom: 2rem; font-weight: 600; }}
  .back .doelgroep {{ font-size: 1rem; opacity: 0.9; max-width: 22em; }}
  .content {{ padding: 2rem 3rem 3rem; max-width: 42rem; margin: 0 auto; }}
  .chapter {{ page-break-before: always; }}
  .chapter:first-of-type {{ page-break-before: avoid; }}
  .chapter-title {{ font-size: 1.4rem; margin: 0 0 1.2rem; color: #1a202c; border-bottom: 2px solid #e2e8f0; padding-bottom: 0.4rem; }}
  .chapter p {{ margin: 0 0 0.85em; }}
  @media print {{
    .cover, .back {{ min-height: 100vh; -webkit-print-color-adjust: exact; print-color-adjust: exact; }}
    body {{ font-size: 10.5pt; }}
  }}
</style>
</head>
<body>
  <div class="cover">
    <h1>{html.escape(VOORKANT_TITEL)}</h1>
    <p class="subtitle">{html.escape(VOORKANT_ONDERTITEL)}</p>
  </div>
  <div class="content">
{html_content}
  </div>
  <div class="back">
    <p class="tagline">{html.escape(ACHTERKANT_TEKST)}</p>
    <p class="doelgroep">{html.escape(ACHTERKANT_DOELGROEP)}</p>
  </div>
</body>
</html>
"""
    out_path = os.path.join(BOEK_DIR, "boek_grippartner.html")
    with open(out_path, "w", encoding="utf-8") as f:
        f.write(full_html)
    print("Gereed:", out_path)
    print("Open in Chrome/Edge en kies: Afdrukken > Opslaan als PDF. Zet 'Achtergrondgrafieken' aan.")


if __name__ == "__main__":
    main()
