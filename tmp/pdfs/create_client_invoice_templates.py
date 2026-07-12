from pathlib import Path

from reportlab.lib import colors
from reportlab.lib.enums import TA_CENTER, TA_RIGHT
from reportlab.lib.pagesizes import A4
from reportlab.lib.styles import ParagraphStyle
from reportlab.lib.units import mm
from reportlab.pdfbase import pdfmetrics
from reportlab.pdfbase.ttfonts import TTFont
from reportlab.platypus import (
    KeepTogether,
    Paragraph,
    SimpleDocTemplate,
    Spacer,
    Table,
    TableStyle,
)


ROOT = Path(__file__).resolve().parents[2]
OUT_DIR = ROOT / "output" / "pdf"
OUT_DIR.mkdir(parents=True, exist_ok=True)

FONT_DIR = Path(r"C:\Windows\Fonts")
if (FONT_DIR / "arial.ttf").exists() and (FONT_DIR / "arialbd.ttf").exists():
    pdfmetrics.registerFont(TTFont("MB", str(FONT_DIR / "arial.ttf")))
    pdfmetrics.registerFont(TTFont("MB-Bold", str(FONT_DIR / "arialbd.ttf")))
else:
    pdfmetrics.registerFont(TTFont("MB", "Helvetica"))
    pdfmetrics.registerFont(TTFont("MB-Bold", "Helvetica-Bold"))


TEMPLATES = [
    {
        "slug": "01-clasic",
        "title": "Clasic curat",
        "primary": "#B77C74",
        "accent": "#F8EEE9",
        "dark": "#2F2928",
        "muted": "#766B66",
        "style": "classic",
    },
    {
        "slug": "02-boutique",
        "title": "Boutique verde",
        "primary": "#52736B",
        "accent": "#EAF2EF",
        "dark": "#243633",
        "muted": "#66736F",
        "style": "side",
    },
    {
        "slug": "03-premium-cadou",
        "title": "Premium cadou",
        "primary": "#C48A3A",
        "accent": "#FFF3E1",
        "dark": "#33271B",
        "muted": "#806D55",
        "style": "gift",
    },
    {
        "slug": "04-compact",
        "title": "Compact operational",
        "primary": "#415F8C",
        "accent": "#EEF4FA",
        "dark": "#1E2A39",
        "muted": "#657082",
        "style": "compact",
    },
    {
        "slug": "05-modern",
        "title": "Modern cu QR",
        "primary": "#8A6F8E",
        "accent": "#F4EEF6",
        "dark": "#322A35",
        "muted": "#736A76",
        "style": "modern",
    },
]


SAMPLE_ITEMS = [
    ["1", "Body organic bebelusi - set 2 buc.", "buc.", "2", "65,00", "130,00", "19%", "154,70"],
    ["2", "Paturica tricotata Maison Bebe", "buc.", "1", "145,00", "145,00", "19%", "172,55"],
    ["3", "Transport prin curier", "serv.", "1", "18,00", "18,00", "19%", "21,42"],
]


def styles(theme):
    primary = colors.HexColor(theme["primary"])
    dark = colors.HexColor(theme["dark"])
    muted = colors.HexColor(theme["muted"])
    base = ParagraphStyle("body", fontName="MB", fontSize=8.2, leading=11, textColor=dark)
    return {
        "body": base,
        "small": ParagraphStyle("small", parent=base, fontSize=7.0, leading=9, textColor=muted),
        "tiny": ParagraphStyle("tiny", parent=base, fontSize=6.3, leading=8, textColor=muted),
        "label": ParagraphStyle(
            "label",
            parent=base,
            fontName="MB-Bold",
            fontSize=6.8,
            leading=8,
            textColor=primary,
            spaceAfter=1,
        ),
        "brand": ParagraphStyle("brand", parent=base, fontName="MB-Bold", fontSize=16.0, leading=18),
        "title": ParagraphStyle(
            "title",
            parent=base,
            fontName="MB-Bold",
            fontSize=22.0,
            leading=24,
            alignment=TA_RIGHT,
        ),
        "right": ParagraphStyle("right", parent=base, alignment=TA_RIGHT),
        "right_bold": ParagraphStyle(
            "right_bold", parent=base, fontName="MB-Bold", alignment=TA_RIGHT, fontSize=8.8
        ),
        "center": ParagraphStyle("center", parent=base, alignment=TA_CENTER),
        "company": ParagraphStyle("company", parent=base, fontName="MB-Bold", fontSize=9.2, leading=12),
        "total": ParagraphStyle(
            "total", parent=base, fontName="MB-Bold", fontSize=13.0, leading=16, alignment=TA_RIGHT
        ),
    }


def para(text, style):
    return Paragraph(text, style)


def cell(lines, s):
    return [para(line[0], s[line[1]]) if isinstance(line, tuple) else para(line, s["body"]) for line in lines]


def on_page(theme):
    primary = colors.HexColor(theme["primary"])
    accent = colors.HexColor(theme["accent"])
    muted = colors.HexColor(theme["muted"])
    dark = colors.HexColor(theme["dark"])

    def draw(canvas, doc):
        width, height = A4
        canvas.saveState()
        if theme["style"] == "side":
            canvas.setFillColor(primary)
            canvas.rect(0, 0, 9 * mm, height, stroke=0, fill=1)
            canvas.setFillColor(accent)
            canvas.rect(9 * mm, height - 22 * mm, width - 9 * mm, 9 * mm, stroke=0, fill=1)
        elif theme["style"] == "gift":
            canvas.setFillColor(accent)
            canvas.rect(0, height - 30 * mm, width, 30 * mm, stroke=0, fill=1)
            canvas.setFillColor(primary)
            canvas.rect(18 * mm, height - 16 * mm, width - 36 * mm, 2 * mm, stroke=0, fill=1)
        elif theme["style"] == "compact":
            canvas.setFillColor(dark)
            canvas.rect(0, height - 24 * mm, width, 24 * mm, stroke=0, fill=1)
        elif theme["style"] == "modern":
            canvas.setFillColor(accent)
            canvas.circle(width - 18 * mm, height - 18 * mm, 36 * mm, stroke=0, fill=1)
            canvas.setFillColor(primary)
            canvas.rect(18 * mm, height - 24 * mm, 58 * mm, 2.2 * mm, stroke=0, fill=1)
        else:
            canvas.setFillColor(accent)
            canvas.rect(0, height - 12 * mm, width, 12 * mm, stroke=0, fill=1)

        canvas.setStrokeColor(colors.HexColor("#D8D2CF"))
        canvas.line(18 * mm, 15 * mm, width - 18 * mm, 15 * mm)
        canvas.setFont("MB", 6.8)
        canvas.setFillColor(muted)
        canvas.drawString(
            18 * mm,
            9.5 * mm,
            "Maison Bebe | sablon PDF pentru client | pentru ANAF se transmite separat XML UBL e-Factura",
        )
        canvas.drawRightString(width - 18 * mm, 9.5 * mm, f"Pagina {doc.page}")
        canvas.restoreState()

    return draw


def header_table(theme, s):
    primary = colors.HexColor(theme["primary"])
    accent = colors.HexColor(theme["accent"])
    dark = colors.HexColor(theme["dark"])
    muted = colors.HexColor(theme["muted"])

    if theme["style"] == "compact":
        brand_style = ParagraphStyle("compact_brand", parent=s["brand"], textColor=colors.white)
        small_style = ParagraphStyle("compact_small", parent=s["small"], textColor=colors.HexColor("#E9EEF6"))
        tiny_style = ParagraphStyle("compact_tiny", parent=s["tiny"], textColor=colors.HexColor("#C9D4E4"))
        title_style = ParagraphStyle("compact_title", parent=s["title"], textColor=colors.white)
        right_style = ParagraphStyle("compact_right", parent=s["right"], textColor=colors.HexColor("#E9EEF6"))
        right_bold_style = ParagraphStyle(
            "compact_right_bold", parent=s["right_bold"], textColor=colors.white
        )
        brand = [
            para("MAISON BEBE", brand_style),
            para("haine, cadouri si produse pentru cei mici", small_style),
            para(f"Sablon client: {theme['title']}", tiny_style),
        ]
        title = [
            para("FACTURA", title_style),
            para("MODEL DE COMPLETAT", right_style),
            para("Serie/Numar: MB-[0001]", right_bold_style),
        ]
    else:
        brand = cell(
            [
                ("MAISON BEBE", "brand"),
                ("haine, cadouri si produse pentru cei mici", "small"),
                (f"Sablon client: {theme['title']}", "tiny"),
            ],
            s,
        )
        title = cell(
            [
                ("FACTURA", "title"),
                ("MODEL DE COMPLETAT", "right"),
                ("Serie/Numar: MB-[0001]", "right_bold"),
            ],
            s,
        )
    table = Table([[brand, title]], colWidths=[102 * mm, 72 * mm])
    commands = [
        ("VALIGN", (0, 0), (-1, -1), "TOP"),
        ("BOTTOMPADDING", (0, 0), (-1, -1), 8),
    ]
    if theme["style"] == "compact":
        commands += [
            ("TEXTCOLOR", (0, 0), (-1, -1), colors.white),
            ("BACKGROUND", (0, 0), (-1, -1), dark),
            ("LEFTPADDING", (0, 0), (-1, -1), 8),
            ("RIGHTPADDING", (0, 0), (-1, -1), 8),
            ("TOPPADDING", (0, 0), (-1, -1), 10),
            ("BOTTOMPADDING", (0, 0), (-1, -1), 10),
        ]
    elif theme["style"] == "gift":
        commands += [
            ("BACKGROUND", (0, 0), (-1, -1), accent),
            ("BOX", (0, 0), (-1, -1), 0.5, colors.HexColor("#E8D8BF")),
            ("LEFTPADDING", (0, 0), (-1, -1), 9),
            ("RIGHTPADDING", (0, 0), (-1, -1), 9),
            ("TOPPADDING", (0, 0), (-1, -1), 9),
        ]
    elif theme["style"] == "side":
        commands += [
            ("LINEBEFORE", (0, 0), (0, 0), 4, primary),
            ("LEFTPADDING", (0, 0), (-1, -1), 9),
        ]
    elif theme["style"] == "modern":
        commands += [
            ("BACKGROUND", (1, 0), (1, 0), accent),
            ("BOX", (1, 0), (1, 0), 0.5, colors.HexColor("#DFD2E4")),
            ("RIGHTPADDING", (1, 0), (1, 0), 9),
            ("TOPPADDING", (1, 0), (1, 0), 9),
        ]
    else:
        commands += [("LINEBELOW", (0, 0), (-1, -1), 1.1, primary)]
    table.setStyle(TableStyle(commands))
    return table


def meta_table(theme, s):
    accent = colors.HexColor(theme["accent"])
    line = colors.HexColor("#DAD4D0")
    rows = [
        [
            para("DATA EMITERII", s["label"]),
            para("DATA SCADENTEI", s["label"]),
            para("MONEDA", s["label"]),
            para("COMANDA", s["label"]),
            para("INDEX E-FACTURA", s["label"]),
        ],
        [
            para("[ZZ.LL.AAAA]", s["body"]),
            para("[ZZ.LL.AAAA]", s["body"]),
            para("RON", s["body"]),
            para("MB-[000000]", s["body"]),
            para("[dupa transmitere]", s["body"]),
        ],
    ]
    table = Table(rows, colWidths=[34 * mm, 34 * mm, 20 * mm, 40 * mm, 46 * mm])
    table.setStyle(
        TableStyle(
            [
                ("BACKGROUND", (0, 0), (-1, -1), accent),
                ("BOX", (0, 0), (-1, -1), 0.5, line),
                ("INNERGRID", (0, 0), (-1, -1), 0.35, line),
                ("VALIGN", (0, 0), (-1, -1), "MIDDLE"),
                ("LEFTPADDING", (0, 0), (-1, -1), 5),
                ("RIGHTPADDING", (0, 0), (-1, -1), 5),
                ("TOPPADDING", (0, 0), (-1, -1), 6),
                ("BOTTOMPADDING", (0, 0), (-1, -1), 6),
            ]
        )
    )
    return table


def party_table(theme, s):
    accent = colors.HexColor(theme["accent"])
    line = colors.HexColor("#DAD4D0")
    supplier = cell(
        [
            ("FURNIZOR", "label"),
            ("[DENUMIRE LEGALA FIRMA]", "company"),
            ("Brand comercial: Maison Bebe", "body"),
            ("CUI / Cod TVA: [DE COMPLETAT]", "body"),
            ("Nr. Reg. Com.: [DE COMPLETAT]", "body"),
            ("Sediu: [strada, nr., localitate, judet]", "body"),
            ("IBAN: [RO__ ____ ____ ____ ____ ____]", "body"),
            ("Email: comenzi@maison-bebe.ro | maison-bebe.ro", "small"),
        ],
        s,
    )
    customer = cell(
        [
            ("CLIENT", "label"),
            ("[NUME CLIENT / DENUMIRE FIRMA]", "company"),
            ("CUI/CNP: [daca este cazul]", "body"),
            ("Adresa: [strada, nr., localitate, judet]", "body"),
            ("Email: [client@email.ro]", "body"),
            ("Telefon: [07__ ___ ___]", "body"),
            ("Livrare: [adresa de livrare, daca difera]", "small"),
        ],
        s,
    )
    table = Table([[supplier, customer]], colWidths=[87 * mm, 87 * mm])
    table.setStyle(
        TableStyle(
            [
                ("VALIGN", (0, 0), (-1, -1), "TOP"),
                ("BACKGROUND", (0, 0), (0, 0), accent),
                ("BOX", (0, 0), (-1, -1), 0.5, line),
                ("INNERGRID", (0, 0), (-1, -1), 0.5, line),
                ("LEFTPADDING", (0, 0), (-1, -1), 8),
                ("RIGHTPADDING", (0, 0), (-1, -1), 8),
                ("TOPPADDING", (0, 0), (-1, -1), 8),
                ("BOTTOMPADDING", (0, 0), (-1, -1), 8),
            ]
        )
    )
    return table


def items_table(theme, s):
    primary = colors.HexColor(theme["primary"])
    accent = colors.HexColor(theme["accent"])
    line = colors.HexColor("#DAD4D0")
    rows = [
        [
            para("NR.", s["label"]),
            para("DENUMIRE PRODUS / SERVICIU", s["label"]),
            para("UM", s["label"]),
            para("CANT.", s["label"]),
            para("PRET NET", s["label"]),
            para("VAL. NETA", s["label"]),
            para("TVA", s["label"]),
            para("TOTAL", s["label"]),
        ]
    ]
    for item in SAMPLE_ITEMS:
        rows.append(
            [
                para(item[0], s["body"]),
                para(item[1], s["body"]),
                para(item[2], s["body"]),
                para(item[3], s["body"]),
                para(item[4], s["right"]),
                para(item[5], s["right"]),
                para(item[6], s["right"]),
                para(item[7], s["right_bold"]),
            ]
        )
    col_widths = [9 * mm, 64 * mm, 13 * mm, 13 * mm, 19 * mm, 21 * mm, 13 * mm, 22 * mm]
    table = Table(rows, colWidths=col_widths, repeatRows=1)
    table.setStyle(
        TableStyle(
            [
                ("BACKGROUND", (0, 0), (-1, 0), accent),
                ("LINEBELOW", (0, 0), (-1, 0), 1.0, primary),
                ("GRID", (0, 0), (-1, -1), 0.35, line),
                ("VALIGN", (0, 0), (-1, -1), "MIDDLE"),
                ("LEFTPADDING", (0, 0), (-1, -1), 4),
                ("RIGHTPADDING", (0, 0), (-1, -1), 4),
                ("TOPPADDING", (0, 0), (-1, -1), 6),
                ("BOTTOMPADDING", (0, 0), (-1, -1), 6),
                ("ROWBACKGROUNDS", (0, 1), (-1, -1), [colors.white, colors.HexColor("#FCFBFA")]),
            ]
        )
    )
    return table


def totals_and_notes(theme, s):
    primary = colors.HexColor(theme["primary"])
    accent = colors.HexColor(theme["accent"])
    line = colors.HexColor("#DAD4D0")
    notes = cell(
        [
            ("OBSERVATII", "label"),
            ("Sablon demonstrativ. Inlocuieste toate campurile marcate cu [ ] inainte de emitere.", "small"),
            ("Pentru facturile raportate prin e-Factura, PDF-ul este copia pentru client; XML-ul UBL se transmite in SPV.", "tiny"),
        ],
        s,
    )
    totals = [
        [para("Subtotal fara TVA", s["right"]), para("293,00 RON", s["right_bold"])],
        [para("TVA 19%", s["right"]), para("55,67 RON", s["right_bold"])],
        [para("Total factura", s["right"]), para("348,67 RON", s["right_bold"])],
        [para("Achitat", s["right"]), para("[0,00 RON]", s["right_bold"])],
        [para("TOTAL DE PLATA", s["right_bold"]), para("348,67 RON", s["total"])],
    ]
    inner = Table(totals, colWidths=[35 * mm, 31 * mm])
    inner.setStyle(
        TableStyle(
            [
                ("LINEABOVE", (0, 4), (-1, 4), 1.1, primary),
                ("BACKGROUND", (0, 4), (-1, 4), accent),
                ("TOPPADDING", (0, 0), (-1, -1), 4),
                ("BOTTOMPADDING", (0, 0), (-1, -1), 4),
                ("LEFTPADDING", (0, 0), (-1, -1), 4),
                ("RIGHTPADDING", (0, 0), (-1, -1), 4),
            ]
        )
    )
    table = Table([[notes, inner]], colWidths=[104 * mm, 70 * mm])
    table.setStyle(
        TableStyle(
            [
                ("VALIGN", (0, 0), (-1, -1), "TOP"),
                ("BOX", (0, 0), (-1, -1), 0.5, line),
                ("LEFTPADDING", (0, 0), (-1, -1), 7),
                ("RIGHTPADDING", (0, 0), (-1, -1), 7),
                ("TOPPADDING", (0, 0), (-1, -1), 7),
                ("BOTTOMPADDING", (0, 0), (-1, -1), 7),
            ]
        )
    )
    return table


def payment_table(theme, s):
    line = colors.HexColor("#DAD4D0")
    rows = [
        [para("MODALITATE PLATA", s["label"]), para("[card online / transfer bancar / ramburs]", s["body"])],
        [para("TERMENI", s["label"]), para("[ex.: plata la primire / plata in avans / 7 zile]", s["body"])],
        [para("MENTIUNI CLIENT", s["label"]), para("[mesaj scurt pentru client, garantie, retur sau link comanda]", s["small"])],
    ]
    table = Table(rows, colWidths=[35 * mm, 139 * mm])
    table.setStyle(
        TableStyle(
            [
                ("BOX", (0, 0), (-1, -1), 0.5, line),
                ("INNERGRID", (0, 0), (-1, -1), 0.35, line),
                ("VALIGN", (0, 0), (-1, -1), "MIDDLE"),
                ("LEFTPADDING", (0, 0), (-1, -1), 6),
                ("RIGHTPADDING", (0, 0), (-1, -1), 6),
                ("TOPPADDING", (0, 0), (-1, -1), 5),
                ("BOTTOMPADDING", (0, 0), (-1, -1), 5),
            ]
        )
    )
    return table


def qr_block(theme, s):
    line = colors.HexColor("#DAD4D0")
    primary = colors.HexColor(theme["primary"])
    placeholder = Table(
        [[para("QR", ParagraphStyle("qr", parent=s["center"], fontName="MB-Bold", fontSize=16, textColor=primary))]],
        colWidths=[22 * mm],
        rowHeights=[22 * mm],
    )
    placeholder.setStyle(
        TableStyle(
            [
                ("BOX", (0, 0), (-1, -1), 1.0, primary),
                ("VALIGN", (0, 0), (-1, -1), "MIDDLE"),
                ("ALIGN", (0, 0), (-1, -1), "CENTER"),
            ]
        )
    )
    text = cell(
        [
            ("OPTIONAL", "label"),
            ("Cod QR pentru comanda, plata sau verificare interna.", "small"),
        ],
        s,
    )
    table = Table([[placeholder, text]], colWidths=[27 * mm, 147 * mm])
    table.setStyle(
        TableStyle(
            [
                ("BOX", (0, 0), (-1, -1), 0.5, line),
                ("VALIGN", (0, 0), (-1, -1), "MIDDLE"),
                ("LEFTPADDING", (0, 0), (-1, -1), 6),
                ("RIGHTPADDING", (0, 0), (-1, -1), 6),
                ("TOPPADDING", (0, 0), (-1, -1), 5),
                ("BOTTOMPADDING", (0, 0), (-1, -1), 5),
            ]
        )
    )
    return table


def build_pdf(theme):
    file_name = f"model-factura-client-{theme['slug']}.pdf"
    out = OUT_DIR / file_name
    s = styles(theme)
    doc = SimpleDocTemplate(
        str(out),
        pagesize=A4,
        rightMargin=18 * mm,
        leftMargin=18 * mm,
        topMargin=17 * mm,
        bottomMargin=22 * mm,
        title=f"Model factura client Maison Bebe - {theme['title']}",
        author="Maison Bebe",
    )

    story = [
        header_table(theme, s),
        Spacer(1, 5 * mm),
        meta_table(theme, s),
        Spacer(1, 5 * mm),
        party_table(theme, s),
        Spacer(1, 6 * mm),
        items_table(theme, s),
        Spacer(1, 5 * mm),
        KeepTogether(totals_and_notes(theme, s)),
        Spacer(1, 5 * mm),
        payment_table(theme, s),
    ]
    if theme["style"] == "modern":
        story += [Spacer(1, 4 * mm), qr_block(theme, s)]
    elif theme["style"] == "gift":
        story += [
            Spacer(1, 4 * mm),
            para(
                "Mesaj client: Multumim pentru comanda. Pregatim coletul cu grija si revenim cu detaliile de livrare.",
                s["small"],
            ),
        ]

    doc.build(story, onFirstPage=on_page(theme), onLaterPages=on_page(theme))
    return out


if __name__ == "__main__":
    for template in TEMPLATES:
        print(build_pdf(template))


