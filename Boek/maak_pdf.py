# -*- coding: utf-8 -*-
# Maakt boek_grippartner.pdf met voorkant, alle hoofdstukken en achterkant.
import os
import sys

try:
    from fpdf import FPDF
except ImportError:
    print("Installeer fpdf2: python -m pip install fpdf2")
    sys.exit(1)

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


def strip_unsupported(text):
    """Verwijder tekens die problemen geven in PDF (emoji etc.)."""
    return "".join(c for c in text if ord(c) < 0x10000 and (ord(c) < 0x2000 or ord(c) > 0x2BFF))


class PDF(FPDF):
    def __init__(self, *args, **kwargs):
        super().__init__(*args, **kwargs)
        self.set_auto_page_break(True, margin=25)

    def header(self):
        pass

    def footer(self):
        self.set_y(-15)
        self.set_font("Helvetica", "", 8)
        self.set_text_color(128, 128, 128)
        self.cell(0, 10, f"Pagina {self.page_no()}", align="C")


def add_cover(pdf):
    pdf.add_page()
    pdf.set_fill_color(45, 55, 72)  # donkergrijs/blauw
    pdf.rect(0, 0, pdf.w, pdf.h, "F")
    pdf.set_y(80)
    pdf.set_font("Helvetica", "B", 28)
    pdf.set_text_color(255, 255, 255)
    pdf.multi_cell(0, 12, VOORKANT_TITEL, align="C")
    pdf.ln(10)
    pdf.set_font("Helvetica", "", 12)
    pdf.set_text_color(230, 230, 230)
    pdf.multi_cell(0, 7, VOORKANT_ONDERTITEL, align="C")


def add_back(pdf):
    pdf.add_page()
    pdf.set_fill_color(26, 32, 44)
    pdf.rect(0, 0, pdf.w, pdf.h, "F")
    pdf.set_y(100)
    pdf.set_font("Helvetica", "B", 14)
    pdf.set_text_color(226, 232, 240)
    pdf.multi_cell(0, 8, ACHTERKANT_TEKST, align="C")
    pdf.ln(15)
    pdf.set_font("Helvetica", "", 11)
    pdf.multi_cell(0, 7, ACHTERKANT_DOELGROEP, align="C")


def add_chapter(pdf, title, body_text):
    pdf.add_page()
    pdf.set_font("Helvetica", "B", 14)
    pdf.set_text_color(26, 32, 44)
    pdf.multi_cell(0, 10, title, align="L")
    pdf.ln(4)
    pdf.set_font("Helvetica", "", 11)
    pdf.set_text_color(0, 0, 0)
    for para in body_text.split("\n\n"):
        para = strip_unsupported(para.strip())
        if not para:
            continue
        pdf.multi_cell(0, 6, para.replace("\n", " "))
        pdf.ln(3)


def main():
    pdf = PDF()
    pdf.set_margins(25, 25, 25)

    add_cover(pdf)

    for filename in CHAPTER_ORDER:
        path = os.path.join(BOEK_DIR, filename)
        if not os.path.isfile(path):
            continue
        with open(path, "r", encoding="utf-8") as f:
            raw = f.read()
        title = filename.replace(".txt", "")
        add_chapter(pdf, title, raw)

    add_back(pdf)

    out_path = os.path.join(BOEK_DIR, "boek_grippartner.pdf")
    pdf.output(out_path)
    print("PDF opgeslagen:", out_path)


if __name__ == "__main__":
    main()
