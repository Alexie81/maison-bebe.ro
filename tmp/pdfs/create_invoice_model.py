from pathlib import Path
from reportlab.lib import colors
from reportlab.lib.enums import TA_RIGHT
from reportlab.lib.pagesizes import A4
from reportlab.lib.styles import ParagraphStyle
from reportlab.lib.units import mm
from reportlab.pdfbase import pdfmetrics
from reportlab.pdfbase.ttfonts import TTFont
from reportlab.platypus import SimpleDocTemplate, Paragraph, Spacer, Table, TableStyle, KeepTogether

ROOT = Path(__file__).resolve().parents[2]
OUT = ROOT / "output" / "pdf" / "model-factura-maison-bebe.pdf"
OUT.parent.mkdir(parents=True, exist_ok=True)

font_dir = Path(r"C:\Windows\Fonts")
pdfmetrics.registerFont(TTFont("MB", str(font_dir / "arial.ttf")))
pdfmetrics.registerFont(TTFont("MB-Bold", str(font_dir / "arialbd.ttf")))

INK = colors.HexColor("#342C2A")
MUTED = colors.HexColor("#7A6F69")
ROSE = colors.HexColor("#B88D84")
BLUSH = colors.HexColor("#F4EAE6")
CREAM = colors.HexColor("#FBF8F5")
LINE = colors.HexColor("#DED3CE")

body = ParagraphStyle("body", fontName="MB", fontSize=8.5, leading=12, textColor=INK)
small = ParagraphStyle("small", parent=body, fontSize=7.2, leading=10, textColor=MUTED)
label = ParagraphStyle("label", parent=small, fontName="MB-Bold", fontSize=7, leading=9, textColor=ROSE, spaceAfter=2)
right = ParagraphStyle("right", parent=body, alignment=TA_RIGHT)
right_bold = ParagraphStyle("right_bold", parent=right, fontName="MB-Bold", fontSize=9.5)

def p(text, style=body):
    return Paragraph(text, style)

def footer(canvas, doc):
    canvas.saveState()
    w, _ = A4
    canvas.setStrokeColor(LINE)
    canvas.line(18*mm, 15*mm, w-18*mm, 15*mm)
    canvas.setFont("MB", 7)
    canvas.setFillColor(MUTED)
    canvas.drawString(18*mm, 9.5*mm, "Maison Bébé  •  maison-bebe.ro  •  comenzi@maison-bebe.ro")
    canvas.drawRightString(w-18*mm, 9.5*mm, f"Pagina {doc.page}")
    canvas.restoreState()

doc = SimpleDocTemplate(str(OUT), pagesize=A4, rightMargin=18*mm, leftMargin=18*mm,
                        topMargin=16*mm, bottomMargin=22*mm, title="Model factură Maison Bébé",
                        author="Maison Bébé")
story = []

brand = p("<font name='MB-Bold' size='18'>MAISON BÉBÉ</font><br/><font size='7' color='#B88D84'>LUCRURI MICI, IUBIRE MARE</font>")
invoice = p("<font name='MB-Bold' size='22'>FACTURĂ</font><br/><font size='8' color='#7A6F69'>MODEL DEMONSTRATIV</font>", right)
header = Table([[brand, invoice]], colWidths=[105*mm, 69*mm])
header.setStyle(TableStyle([("VALIGN",(0,0),(-1,-1),"TOP"), ("BOTTOMPADDING",(0,0),(-1,-1),8)]))
story += [header, Table([[""]], colWidths=[174*mm], rowHeights=[1.2*mm], style=[("BACKGROUND",(0,0),(-1,-1),ROSE)]), Spacer(1, 8*mm)]

meta = Table([
    [p("SERIA / NUMĂR", label), p("DATA EMITERII", label), p("DATA SCADENȚEI", label), p("MONEDA", label)],
    [p("MB 0001"), p("12.07.2026"), p("12.07.2026"), p("RON")],
], colWidths=[48*mm, 48*mm, 48*mm, 30*mm])
meta.setStyle(TableStyle([("BACKGROUND",(0,0),(-1,-1),CREAM), ("BOX",(0,0),(-1,-1),0.5,LINE),
                          ("INNERGRID",(0,0),(-1,-1),0.35,LINE), ("LEFTPADDING",(0,0),(-1,-1),6),
                          ("RIGHTPADDING",(0,0),(-1,-1),6), ("TOPPADDING",(0,0),(-1,0),6), ("BOTTOMPADDING",(0,1),(-1,1),7)]))
story += [meta, Spacer(1, 7*mm)]

issuer = [p("FURNIZOR", label), p("[DENUMIRE LEGALĂ] S.R.L.", ParagraphStyle("company", parent=body, fontName="MB-Bold", fontSize=10)),
          p("CUI: [DE COMPLETAT]  •  Reg. Com.: [DE COMPLETAT]"), p("Sediu: [ADRESĂ, LOCALITATE, JUDEȚ]"),
          p("IBAN: [RO__ ____ ____ ____ ____ ____]"), p("Banca: [DE COMPLETAT]"),
          p("comenzi@maison-bebe.ro  •  maison-bebe.ro", small)]
customer = [p("CLIENT", label), p("[NUME CLIENT / DENUMIRE FIRMĂ]", ParagraphStyle("company2", parent=body, fontName="MB-Bold", fontSize=10)),
            p("CNP/CUI: [DE COMPLETAT]"), p("Adresă: [STRADĂ, NUMĂR, LOCALITATE, JUDEȚ]"),
            p("Email: [client@email.ro]"), p("Telefon: [07__ ___ ___]"), p("Comanda: MB-2026-0001", small)]
party = Table([[issuer, customer]], colWidths=[87*mm, 87*mm])
party.setStyle(TableStyle([("VALIGN",(0,0),(-1,-1),"TOP"), ("BACKGROUND",(0,0),(0,0),CREAM),
                           ("BOX",(0,0),(-1,-1),0.5,LINE), ("INNERGRID",(0,0),(-1,-1),0.5,LINE),
                           ("LEFTPADDING",(0,0),(-1,-1),8), ("RIGHTPADDING",(0,0),(-1,-1),8),
                           ("TOPPADDING",(0,0),(-1,-1),8), ("BOTTOMPADDING",(0,0),(-1,-1),8)]))
story += [party, Spacer(1, 8*mm)]

rows = [[p("NR.", label), p("DENUMIRE PRODUS / SERVICIU", label), p("UM", label), p("CANT.", label), p("PREȚ UNITAR", label), p("TVA", label), p("VALOARE", label)],
        [p("1"), p("Păturică tricotată din bumbac organic - crem"), p("buc."), p("1"), p("189,00", right), p("19%", right), p("189,00", right)],
        [p("2"), p("Set cadou nou-născut Maison Bébé"), p("buc."), p("1"), p("249,00", right), p("19%", right), p("249,00", right)],
        [p("3"), p("Transport prin curier"), p("serv."), p("1"), p("19,00", right), p("19%", right), p("19,00", right)]]
items = Table(rows, colWidths=[10*mm, 75*mm, 13*mm, 14*mm, 23*mm, 14*mm, 25*mm], repeatRows=1)
items.setStyle(TableStyle([("BACKGROUND",(0,0),(-1,0),BLUSH), ("GRID",(0,0),(-1,-1),0.4,LINE),
                           ("VALIGN",(0,0),(-1,-1),"MIDDLE"), ("LEFTPADDING",(0,0),(-1,-1),5),
                           ("RIGHTPADDING",(0,0),(-1,-1),5), ("TOPPADDING",(0,0),(-1,-1),7),
                           ("BOTTOMPADDING",(0,0),(-1,-1),7), ("ROWBACKGROUNDS",(0,1),(-1,-1),[colors.white,CREAM])]))
story += [items, Spacer(1, 5*mm)]

totals = Table([
    [p("Observații", label), "", p("Subtotal fără TVA", right), p("384,03 RON", right_bold)],
    [p("Exemplu orientativ. Înlocuiți datele marcate înainte de emitere.", small), "", p("TVA 19%", right), p("72,97 RON", right_bold)],
    ["", "", p("TOTAL DE PLATĂ", ParagraphStyle("total_label", parent=right_bold, textColor=ROSE)), p("457,00 RON", ParagraphStyle("total", parent=right_bold, fontSize=13, textColor=INK))],
], colWidths=[70*mm, 28*mm, 45*mm, 31*mm])
totals.setStyle(TableStyle([("SPAN",(0,0),(1,0)), ("SPAN",(0,1),(1,2)), ("VALIGN",(0,0),(-1,-1),"TOP"),
                            ("TOPPADDING",(0,0),(-1,-1),5), ("BOTTOMPADDING",(0,0),(-1,-1),5),
                            ("LINEABOVE",(2,2),(-1,2),1.2,ROSE), ("BACKGROUND",(2,2),(-1,2),BLUSH)]))
story += [KeepTogether(totals), Spacer(1, 8*mm)]

payment = Table([[p("PLATĂ", label), p("Metodă: Card online / Transfer bancar / Ramburs")],
                 [p("MENȚIUNI", label), p("Document generat electronic. Semnătura și ștampila nu sunt obligatorii conform legislației în vigoare.", small)]],
                colWidths=[28*mm,146*mm])
payment.setStyle(TableStyle([("BOX",(0,0),(-1,-1),0.5,LINE), ("INNERGRID",(0,0),(-1,-1),0.35,LINE),
                             ("LEFTPADDING",(0,0),(-1,-1),7), ("RIGHTPADDING",(0,0),(-1,-1),7),
                             ("TOPPADDING",(0,0),(-1,-1),6), ("BOTTOMPADDING",(0,0),(-1,-1),6)]))
story.append(payment)
doc.build(story, onFirstPage=footer, onLaterPages=footer)
print(OUT)
