#!/usr/bin/env python3
# Panduan Afiliator Energi.Click — PDF generator (reportlab)
from reportlab.lib.pagesizes import A4
from reportlab.lib.units import mm
from reportlab.lib.colors import HexColor, white
from reportlab.lib.styles import ParagraphStyle
from reportlab.lib.enums import TA_CENTER, TA_LEFT
from reportlab.platypus import (BaseDocTemplate, PageTemplate, Frame, Paragraph, Spacer,
                                Table, TableStyle, PageBreak, KeepTogether)

BRAND = HexColor('#0f766e')      # teal (brand)
BRAND_DARK = HexColor('#115e59')
ACCENT = HexColor('#f59e0b')     # amber accent
GREEN = HexColor('#16a34a')
RED = HexColor('#dc2626')
GRAY = HexColor('#6b7280')
LIGHT = HexColor('#f0fdfa')      # teal-50
LIGHT2 = HexColor('#f9fafb')
BORDER = HexColor('#e5e7eb')

W, H = A4
OUT = 'public/panduan-afiliator.pdf'  # jalankan dari root repo: python3 docs/generators/panduan_afiliator.py

styles = {
    'h1': ParagraphStyle('h1', fontName='Helvetica-Bold', fontSize=22, leading=27, textColor=BRAND_DARK, spaceAfter=4),
    'h2': ParagraphStyle('h2', fontName='Helvetica-Bold', fontSize=14.5, leading=18, textColor=BRAND_DARK, spaceBefore=14, spaceAfter=6),
    'body': ParagraphStyle('body', fontName='Helvetica', fontSize=10, leading=14.5, textColor=HexColor('#1f2937')),
    'small': ParagraphStyle('small', fontName='Helvetica', fontSize=8.5, leading=12, textColor=GRAY),
    'step_num': ParagraphStyle('step_num', fontName='Helvetica-Bold', fontSize=15, leading=18, textColor=white, alignment=TA_CENTER),
    'step_title': ParagraphStyle('step_title', fontName='Helvetica-Bold', fontSize=11, leading=14, textColor=BRAND_DARK),
    'cover_title': ParagraphStyle('cover_title', fontName='Helvetica-Bold', fontSize=30, leading=36, textColor=white),
    'cover_sub': ParagraphStyle('cover_sub', fontName='Helvetica', fontSize=13, leading=18, textColor=HexColor('#ccfbf1')),
    'badge': ParagraphStyle('badge', fontName='Helvetica-Bold', fontSize=10, leading=13, textColor=white, alignment=TA_CENTER),
}

def body(t): return Paragraph(t, styles['body'])
def h2(t): return Paragraph(t, styles['h2'])

# ---- Mock UI kecil (meniru tampilan website, pakai data dummy) ----
MONO = ParagraphStyle('mono', fontName='Courier-Bold', fontSize=9, leading=12, textColor=HexColor('#374151'))
BTN = ParagraphStyle('btn', fontName='Helvetica-Bold', fontSize=9, leading=12, textColor=white, alignment=TA_CENTER)
BTN_OUT = ParagraphStyle('btn_out', fontName='Helvetica-Bold', fontSize=9, leading=12, textColor=BRAND_DARK, alignment=TA_CENTER)
UI_TITLE = ParagraphStyle('ui_title', fontName='Helvetica-Bold', fontSize=10.5, leading=13, textColor=HexColor('#111827'))

def ui_input(text, width):
    t = Table([[Paragraph(text, MONO)]], colWidths=[width], rowHeights=[9*mm])
    t.setStyle(TableStyle([('BACKGROUND', (0, 0), (0, 0), LIGHT2), ('BOX', (0, 0), (0, 0), 0.75, BORDER),
                           ('VALIGN', (0, 0), (0, 0), 'MIDDLE'), ('LEFTPADDING', (0, 0), (0, 0), 6),
                           ('ROUNDEDCORNERS', [5, 5, 5, 5])]))
    return t

def ui_button(text, width, outline=False):
    t = Table([[Paragraph(text, BTN_OUT if outline else BTN)]], colWidths=[width], rowHeights=[9*mm])
    t.setStyle(TableStyle([('BACKGROUND', (0, 0), (0, 0), white if outline else BRAND),
                           ('BOX', (0, 0), (0, 0), 0.9, BRAND),
                           ('VALIGN', (0, 0), (0, 0), 'MIDDLE'), ('ROUNDEDCORNERS', [5, 5, 5, 5])]))
    return t

def ui_card(rows, pad=8):
    t = Table([[r] for r in rows], colWidths=[None])
    t.setStyle(TableStyle([('BOX', (0, 0), (-1, -1), 0.9, BORDER), ('BACKGROUND', (0, 0), (-1, -1), white),
                           ('LEFTPADDING', (0, 0), (-1, -1), pad), ('RIGHTPADDING', (0, 0), (-1, -1), pad),
                           ('TOPPADDING', (0, 0), (0, 0), pad), ('BOTTOMPADDING', (-1, -1), (-1, -1), pad),
                           ('TOPPADDING', (0, 1), (-1, -1), 3), ('BOTTOMPADDING', (0, 0), (-1, -2), 3),
                           ('ROUNDEDCORNERS', [8, 8, 8, 8])]))
    return t

def on_page(canvas, doc):
    canvas.saveState()
    # header band (skip on cover page 1)
    if doc.page > 1:
        canvas.setFillColor(BRAND)
        canvas.rect(0, H - 14*mm, W, 14*mm, stroke=0, fill=1)
        canvas.setFillColor(white)
        canvas.setFont('Helvetica-Bold', 10)
        canvas.drawString(18*mm, H - 9.5*mm, 'Panduan Afiliator Energi.Click')
        canvas.setFont('Helvetica', 9)
        canvas.drawRightString(W - 18*mm, H - 9.5*mm, 'energi.click/afiliasi')
    # footer
    canvas.setFillColor(GRAY)
    canvas.setFont('Helvetica', 8)
    canvas.drawString(18*mm, 10*mm, 'Energi.Click by PT Rekasurya Primadaya  ·  Diperbarui: 9 September 2026')
    canvas.drawRightString(W - 18*mm, 10*mm, f'Hal. {doc.page}')
    canvas.restoreState()

def cover(canvas, doc):
    canvas.saveState()
    canvas.setFillColor(BRAND)
    canvas.rect(0, 0, W, H, stroke=0, fill=1)
    canvas.setFillColor(BRAND_DARK)
    canvas.rect(0, 0, W, H/2.6, stroke=0, fill=1)
    # sun rays motif
    canvas.setFillColor(ACCENT)
    canvas.circle(W - 38*mm, H - 48*mm, 16*mm, stroke=0, fill=1)
    canvas.setFillColor(HexColor('#fbbf24'))
    canvas.circle(W - 38*mm, H - 48*mm, 11*mm, stroke=0, fill=1)
    canvas.restoreState()

doc = BaseDocTemplate(OUT, pagesize=A4, leftMargin=18*mm, rightMargin=18*mm, topMargin=22*mm, bottomMargin=18*mm,
                      title='Panduan Afiliator Energi.Click', author='Energi.Click by PT Rekasurya Primadaya')
frame = Frame(18*mm, 18*mm, W - 36*mm, H - 40*mm, id='main')
cover_frame = Frame(20*mm, 30*mm, W - 40*mm, H - 60*mm, id='cover')
doc.addPageTemplates([
    PageTemplate(id='cover', frames=[cover_frame], onPage=cover),
    PageTemplate(id='page', frames=[frame], onPage=on_page),
])

story = []

# ---------- COVER ----------
story.append(Spacer(1, 34*mm))
story.append(Paragraph('PANDUAN<br/>AFILIATOR', styles['cover_title']))
story.append(Spacer(1, 6*mm))
story.append(Paragraph('Hasilkan komisi hingga 10% dengan merekomendasikan<br/>produk energi surya — panel, inverter, baterai, dan PLTS.',
                       styles['cover_sub']))
story.append(Spacer(1, 10*mm))
badges = Table([[Paragraph('Komisi 2,5%–10%', styles['badge']),
                 Paragraph('Cair ke rekening', styles['badge']),
                 Paragraph('Proteksi klik pertama', styles['badge'])]],
               colWidths=[46*mm, 46*mm, 52*mm], rowHeights=[10*mm])
badges.setStyle(TableStyle([
    ('BACKGROUND', (0, 0), (0, 0), ACCENT),
    ('BACKGROUND', (1, 0), (1, 0), ACCENT),
    ('BACKGROUND', (2, 0), (2, 0), ACCENT),
    ('VALIGN', (0, 0), (-1, -1), 'MIDDLE'),
    ('ROUNDEDCORNERS', [8, 8, 8, 8]),
    ('LEFTPADDING', (0, 0), (-1, -1), 4), ('RIGHTPADDING', (0, 0), (-1, -1), 4),
]))
story.append(badges)
story.append(Spacer(1, 42*mm))
story.append(Paragraph('<b>Energi.Click</b> — Energi Cerdas, Tinggal Klik!', styles['cover_sub']))
story.append(Paragraph('energi.click/afiliasi', styles['cover_sub']))
story.append(Spacer(1, 3*mm))
story.append(Paragraph('Diperbarui: 9 September 2026', ParagraphStyle('upd', fontName='Helvetica', fontSize=10, textColor=HexColor('#99f6e4'))))

from reportlab.platypus import NextPageTemplate
story.insert(0, NextPageTemplate('cover'))
story.append(NextPageTemplate('page'))
story.append(PageBreak())

# ---------- 1. APA ITU ----------
story.append(Paragraph('Selamat Datang, Calon Afiliator!', styles['h1']))
story.append(body(
    'Program Afiliasi <b>Energi.Click</b> membayar Anda setiap kali seseorang membeli lewat rekomendasi Anda. '
    'Tanpa modal, tanpa stok barang, tanpa perlu mengurus pengiriman — cukup bagikan link, kami yang mengurus sisanya. '
    'Cocok untuk instalatir listrik/PLTS, konsultan energi, admin komunitas, content creator, sampai karyawan yang ingin penghasilan tambahan.'))
story.append(Spacer(1, 4))

t = Table([
    ['Komisi per penjualan', '2,5% – 10% dari harga produk (di luar ongkir & pajak)'],
    ['Contoh nyata', 'Power station Rp 11.000.000 dengan fee 5% = Rp 550.000 per unit'],
    ['Masa atribusi', '30 hari sejak link Anda diklik'],
    ['Pencairan', 'Transfer bank, minimum Rp 100.000'],
    ['Biaya pendaftaran', 'GRATIS'],
], colWidths=[48*mm, None])
t.setStyle(TableStyle([
    ('FONTNAME', (0, 0), (0, -1), 'Helvetica-Bold'),
    ('FONTNAME', (1, 0), (1, -1), 'Helvetica'),
    ('FONTSIZE', (0, 0), (-1, -1), 9.5),
    ('TEXTCOLOR', (0, 0), (0, -1), BRAND_DARK),
    ('ROWBACKGROUNDS', (0, 0), (-1, -1), [LIGHT, white]),
    ('GRID', (0, 0), (-1, -1), 0.5, BORDER),
    ('TOPPADDING', (0, 0), (-1, -1), 5), ('BOTTOMPADDING', (0, 0), (-1, -1), 5),
    ('LEFTPADDING', (0, 0), (-1, -1), 7),
]))
story.append(t)

# ---------- 2. LANGKAH ----------
story.append(h2('Langkah Memulai — 10 Menit Selesai'))

steps = [
    ('1', 'Buat akun Energi.Click', 'Daftar di <b>energi.click</b> dengan email aktif, lalu verifikasi email Anda.'),
    ('2', 'Daftar sebagai afiliator', 'Buka <b>Akun → Afiliasi</b>. Isi data diri, unggah <b>foto KTP + selfie</b>, nomor rekening bank atas nama sendiri, dan NPWP. Data ini hanya untuk verifikasi & pencairan dana — tidak dipublikasikan.'),
    ('3', 'Tunggu verifikasi admin', 'Tim kami memeriksa kelengkapan data (biasanya 1×24 jam kerja). Setelah disetujui, akun afiliasi Anda aktif dan kode unik Anda terbit.'),
    ('4', 'Salin link Anda', 'Dari <b>Dashboard Afiliasi</b> (link toko) atau halaman <b>energi.click/afiliasi</b> dan <b>Akun → Afiliasi → Produk &amp; Komisi</b> untuk link per produk — di sana terlihat fee % dan perkiraan komisi rupiah tiap produk.'),
    ('5', 'Bagikan & pantau', 'Sebarkan link ke calon pembeli. Klik, pesanan, dan komisi Anda tercatat otomatis dan bisa dipantau real-time di dashboard.'),
]
for num, title, desc in steps:
    numcell = Table([[Paragraph(num, styles['step_num'])]], colWidths=[11*mm], rowHeights=[11*mm])
    numcell.setStyle(TableStyle([('BACKGROUND', (0, 0), (0, 0), BRAND), ('VALIGN', (0, 0), (0, 0), 'MIDDLE'),
                                 ('ROUNDEDCORNERS', [6, 6, 6, 6])]))
    row = Table([[numcell, [Paragraph(title, styles['step_title']), Paragraph(desc, styles['body'])]]],
                colWidths=[14*mm, None])
    row.setStyle(TableStyle([('VALIGN', (0, 0), (-1, -1), 'TOP'),
                             ('BOTTOMPADDING', (0, 0), (-1, -1), 6), ('LEFTPADDING', (0, 0), (0, 0), 0)]))
    story.append(KeepTogether(row))

story.append(PageBreak())

# ---------- 2b. DUA CARA MEMBAGIKAN LINK ----------
story.append(Paragraph('Dua Cara Membagikan Link Anda', styles['h1']))
story.append(body('Keduanya sama-sama membawa kode unik Anda — bedanya di halaman mana calon pembeli mendarat. '
                  'Tampilan di bawah meniru website (kode <b>KODEKU</b> hanya contoh; pakai kode Anda sendiri).'))
story.append(Spacer(1, 6))

# Cara 1: link umum dari dashboard
link_row = Table([[ui_input('https://energi.click?ref=KODEKU', 108*mm), ui_button('Salin Link', 30*mm)]],
                 colWidths=[112*mm, 32*mm])
link_row.setStyle(TableStyle([('VALIGN', (0, 0), (-1, -1), 'MIDDLE'), ('LEFTPADDING', (0, 0), (-1, -1), 0)]))
story.append(KeepTogether([
    Paragraph('CARA 1 — Link Toko (Umum)', styles['step_title']),
    Spacer(1, 3),
    ui_card([
        Paragraph('Link Referral Anda', UI_TITLE),
        link_row,
        Paragraph('Kode: KODEKU  ·  Berlaku 30 hari sejak diklik.', styles['small']),
    ]),
    Spacer(1, 3),
    body('Ada di <b>Dashboard Afiliasi</b>. Pembeli mendarat di beranda toko, dan <b>semua</b> belanjaannya selama 30 hari '
         'menghasilkan komisi Anda. Paling pas untuk bio Instagram/TikTok, status WA, dan tanda tangan email.'),
]))
story.append(Spacer(1, 8))

# Cara 2: link per produk
share_row = Table([[Paragraph('Bagikan:', styles['body']), ui_button('Salin Link', 26*mm, outline=True),
                    ui_button('Salin Link Afiliator', 38*mm)]],
                  colWidths=[18*mm, 30*mm, 42*mm])
share_row.setStyle(TableStyle([('VALIGN', (0, 0), (-1, -1), 'MIDDLE'), ('LEFTPADDING', (0, 0), (0, 0), 0)]))
story.append(KeepTogether([
    Paragraph('CARA 2 — Link per Produk (paling gampang closing)', styles['step_title']),
    Spacer(1, 3),
    ui_card([
        Paragraph('Panel Surya AIKO Comet 2U N-Type ABC (640-670 Wp)', UI_TITLE),
        share_row,
        Paragraph('<font color="#16a34a"><b>Komisi kamu: 2,5% (Rp 59.750) per penjualan</b></font>', styles['body']),
        Paragraph('<b>Rp 2.390.000</b>', ParagraphStyle('prc', fontName='Helvetica-Bold', fontSize=13, leading=16,
                                                        textColor=HexColor('#111827'))),
    ]),
    Spacer(1, 3),
    body('Buka <b>halaman produk mana pun</b> saat login sebagai afiliator — tombol <b>&#8220;Salin Link Afiliator&#8221;</b> muncul '
         'lengkap dengan komisi rupiah per penjualan. Daftar lengkapnya (urut fee tertinggi) ada di <b>Akun &#8594; Afiliasi &#8594; Produk &amp; Komisi</b>. '
         'Pembeli langsung mendarat di produk yang Anda rekomendasikan — jalur paling singkat menuju checkout.'),
]))

story.append(PageBreak())

# ---------- 3. CARA DAPAT CUAN ----------
story.append(h2('Cara Kerja Komisi — Dari Klik Sampai Cair'))
flow = Table([
    ['1. DIKLIK', '2. BELANJA', '3. DIBAYAR', '4. SELESAI', '5. CAIR'],
    [Paragraph('Calon pembeli membuka link Anda. Atribusi tersimpan <b>30 hari</b>.', styles['small']),
     Paragraph('Ia berbelanja di energi.click — kapan pun dalam masa 30 hari itu.', styles['small']),
     Paragraph('Pesanan dibayar → komisi <b>tercatat</b> (status tertahan).', styles['small']),
     Paragraph('Pesanan berstatus <b>Selesai</b> → komisi <b>disetujui</b> &amp; masuk saldo.', styles['small']),
     Paragraph('Tarik saldo (min Rp 100rb) → <b>transfer ke rekening</b> Anda.', styles['small'])],
], colWidths=[(W - 36*mm)/5.0]*5)
flow.setStyle(TableStyle([
    ('BACKGROUND', (0, 0), (-1, 0), BRAND),
    ('TEXTCOLOR', (0, 0), (-1, 0), white),
    ('FONTNAME', (0, 0), (-1, 0), 'Helvetica-Bold'),
    ('FONTSIZE', (0, 0), (-1, 0), 8.5),
    ('ALIGN', (0, 0), (-1, 0), 'CENTER'),
    ('VALIGN', (0, 0), (-1, -1), 'TOP'),
    ('GRID', (0, 0), (-1, -1), 0.5, BORDER),
    ('BACKGROUND', (0, 1), (-1, 1), LIGHT2),
    ('TOPPADDING', (0, 0), (-1, -1), 5), ('BOTTOMPADDING', (0, 0), (-1, -1), 5),
    ('LEFTPADDING', (0, 0), (-1, -1), 4), ('RIGHTPADDING', (0, 0), (-1, -1), 4),
]))
story.append(flow)
story.append(Spacer(1, 6))
story.append(body('Komisi dihitung dari <b>harga barang saat pesanan terjadi</b> (di luar ongkir, packing, dan pajak). '
                  'Bila pesanan dibatalkan atau diretur, komisinya ikut batal — itulah sebabnya komisi baru cair setelah pesanan benar-benar selesai.'))

story.append(h2('Simulasi Penghasilan'))
sim = Table([
    ['Produk yang Anda promosikan', 'Harga', 'Fee', 'Komisi Anda'],
    ['Power station BLUETTI', 'Rp 11.000.000', '5%', 'Rp 550.000'],
    ['Inverter hybrid 6 kW', 'Rp 9.450.000', '2,5%', 'Rp 236.250'],
    ['Baterai lithium 16 kWh', 'Rp 34.600.000', '2,5%', 'Rp 865.000'],
    ['Paket PLTS rumah', 'Rp 45.000.000', '2,5%', 'Rp 1.125.000'],
], colWidths=[None, 32*mm, 16*mm, 30*mm])
sim.setStyle(TableStyle([
    ('BACKGROUND', (0, 0), (-1, 0), BRAND_DARK), ('TEXTCOLOR', (0, 0), (-1, 0), white),
    ('FONTNAME', (0, 0), (-1, 0), 'Helvetica-Bold'), ('FONTSIZE', (0, 0), (-1, -1), 9.5),
    ('FONTNAME', (3, 1), (3, -1), 'Helvetica-Bold'), ('TEXTCOLOR', (3, 1), (3, -1), GREEN),
    ('ALIGN', (1, 0), (-1, -1), 'RIGHT'),
    ('GRID', (0, 0), (-1, -1), 0.5, BORDER),
    ('ROWBACKGROUNDS', (0, 1), (-1, -1), [white, LIGHT2]),
    ('TOPPADDING', (0, 0), (-1, -1), 5), ('BOTTOMPADDING', (0, 0), (-1, -1), 5),
    ('LEFTPADDING', (0, 0), (-1, -1), 7),
]))
story.append(sim)
story.append(Paragraph('Fee tiap produk bisa berbeda (2,5%–10%) dan tercantum jelas di halaman <b>energi.click/afiliasi</b> — '
                       'diurutkan dari fee tertinggi, lengkap dengan perkiraan komisi rupiah per unit. Angka di atas contoh; harga &amp; fee mengikuti yang berlaku saat pesanan.', styles['small']))

story.append(h2('Pembeli Lebih Suka Pesan Lewat WhatsApp? Tetap Dapat Komisi'))
story.append(body(
    'Banyak pembelian energi surya diputuskan lewat konsultasi WA, bukan klik keranjang. Komisi Anda tetap aman dengan dua cara:'))
story.append(body(
    '&bull;&nbsp; <b>Tetap kirimkan link Anda dulu.</b> Selama pembeli pernah membuka link Anda lalu checkout sendiri di website dalam 30 hari, komisi otomatis tercatat.<br/>'
    '&bull;&nbsp; <b>Pesanan diproses admin (WA/offline/marketplace):</b> minta pembeli <b>menyebutkan kode afiliator Anda</b> saat memesan '
    '(contoh: &#8220;Saya direferensikan kode <b>AB3xYz</b>&#8221;). Admin akan memilih nama Anda saat menginput pesanan, dan komisi mengalir seperti biasa — tercatat saat dibayar, cair saat selesai.'))
story.append(Paragraph('Tips: cantumkan kode afiliator Anda di setiap materi promosi, bukan hanya link — untuk berjaga bila pembeli langsung menghubungi CS.', styles['small']))

story.append(PageBreak())

# ---------- 3b. PANTAU KOMISI & TARIK DANA ----------
story.append(Paragraph('Memantau Komisi &amp; Menarik Dana', styles['h1']))
story.append(body('Begini tampilan Dashboard Afiliasi saat komisi Anda mulai terisi (angka di bawah contoh):'))
story.append(Spacer(1, 6))

status_st = lambda c: ParagraphStyle('st'+c, fontName='Helvetica-Bold', fontSize=8, leading=11, textColor=HexColor(c), alignment=TA_CENTER)
riw = Table([
    ['Tanggal', 'Pesanan', 'Produk', 'Rate', 'Komisi', 'Status'],
    ['28 Agu', 'ORD-0877', Paragraph('Inverter hybrid 6 kW', styles['small']), '2,5%', 'Rp 236.250',
     Paragraph('Dibayar', status_st('#6b7280'))],
    ['02 Sep', 'ORD-0912', Paragraph('Power station BLUETTI', styles['small']), '5%', 'Rp 550.000',
     Paragraph('Disetujui', status_st('#16a34a'))],
    ['05 Sep', 'ORD-0931', Paragraph('Panel Surya AIKO Comet 2U', styles['small']), '2,5%', 'Rp 59.750',
     Paragraph('Ditahan', status_st('#d97706'))],
], colWidths=[16*mm, 20*mm, None, 12*mm, 24*mm, 20*mm])
riw.setStyle(TableStyle([
    ('BACKGROUND', (0, 0), (-1, 0), BRAND_DARK), ('TEXTCOLOR', (0, 0), (-1, 0), white),
    ('FONTNAME', (0, 0), (-1, 0), 'Helvetica-Bold'), ('FONTSIZE', (0, 0), (-1, -1), 8.5),
    ('ALIGN', (3, 0), (4, -1), 'RIGHT'), ('ALIGN', (5, 0), (5, -1), 'CENTER'),
    ('VALIGN', (0, 0), (-1, -1), 'MIDDLE'),
    ('GRID', (0, 0), (-1, -1), 0.5, BORDER),
    ('ROWBACKGROUNDS', (0, 1), (-1, -1), [white, LIGHT2]),
    ('TOPPADDING', (0, 0), (-1, -1), 4), ('BOTTOMPADDING', (0, 0), (-1, -1), 4),
    ('LEFTPADDING', (0, 0), (-1, -1), 5),
]))
story.append(KeepTogether([
    ui_card([Paragraph('Riwayat Komisi', UI_TITLE), riw]),
    Spacer(1, 3),
    body('Arti status: <font color="#d97706"><b>Ditahan</b></font> = pesanan sudah dibayar, menunggu pesanan Selesai. '
         '<font color="#16a34a"><b>Disetujui</b></font> = pesanan Selesai, komisi masuk <b>Saldo Tersedia</b> dan siap ditarik. '
         '<font color="#6b7280"><b>Dibayar</b></font> = sudah ditransfer ke rekening Anda.'),
]))
story.append(Spacer(1, 8))

tarik_row = Table([[ui_input('Jumlah (Rp)', 60*mm), ui_button('Ajukan Penarikan', 40*mm)]], colWidths=[64*mm, 42*mm])
tarik_row.setStyle(TableStyle([('VALIGN', (0, 0), (-1, -1), 'MIDDLE'), ('LEFTPADDING', (0, 0), (-1, -1), 0)]))
story.append(KeepTogether([
    h2('Cara Menarik Dana — 4 Langkah'),
    ui_card([
        Paragraph('Tarik Dana', UI_TITLE),
        Paragraph('Saldo tersedia: <font color="#16a34a"><b>Rp 550.000</b></font>  ·  Minimum penarikan Rp 100.000.', styles['body']),
        tarik_row,
        Paragraph('Transfer ke: BCA &#8226;&#8226;&#8226;&#8226;1234 a.n. Nama Anda (rekening yang Anda daftarkan)', styles['small']),
    ]),
    Spacer(1, 4),
    body('&bull;&nbsp; <b>1.</b> Pastikan Saldo Tersedia minimal Rp 100.000 (kumpulan komisi berstatus Disetujui).<br/>'
         '&bull;&nbsp; <b>2.</b> Buka <b>Dashboard Afiliasi</b> &#8594; kartu <b>Tarik Dana</b>, isi jumlah yang ingin dicairkan.<br/>'
         '&bull;&nbsp; <b>3.</b> Klik <b>Ajukan Penarikan</b> — permintaan masuk ke tim keuangan kami.<br/>'
         '&bull;&nbsp; <b>4.</b> Dana ditransfer ke rekening terdaftar (wajib atas nama sendiri); statusnya terpantau di <b>Riwayat Penarikan</b> pada halaman yang sama.'),
]))

story.append(PageBreak())

# ---------- 4. PROTEKSI ----------
story.append(h2('Perlindungan untuk Anda'))
prot = [
    ('Atribusi KLIK PERTAMA', 'Siapa yang pertama memperkenalkan, dia yang berhak. Selama masa atribusi Anda hidup (30 hari), '
     'link afiliator lain — termasuk pembeli yang mendaftar jadi afiliator lalu mengeklik link sendiri — <b>tidak bisa merebut</b> komisi Anda.'),
    ('Masa berlaku 30 hari penuh', 'Pembeli tidak harus langsung transaksi. Klik hari ini, beli minggu depan — tetap komisi Anda. '
     'Klik ulang link Anda oleh orang yang sama justru memperpanjang masa berlakunya.'),
    ('Kode unik & dashboard transparan', 'Setiap klik, pesanan, dan komisi tercatat dengan kode unik Anda dan bisa Anda pantau sendiri '
     'kapan pun di Dashboard Afiliasi — tidak ada hitungan yang tersembunyi.'),
    ('Komisi aman dari pembatalan', 'Komisi mengikuti status pesanan. Yang sudah berstatus Selesai tidak terpengaruh retur pesanan lain, '
     'dan saldo yang sudah cair tidak pernah ditarik kembali.'),
]
for title, desc in prot:
    box = Table([[Paragraph('&#10003;', ParagraphStyle('chk', fontName='Helvetica-Bold', fontSize=13, textColor=GREEN, alignment=TA_CENTER)),
                  [Paragraph(title, styles['step_title']), Paragraph(desc, styles['body'])]]],
                colWidths=[10*mm, None])
    box.setStyle(TableStyle([('VALIGN', (0, 0), (-1, -1), 'TOP'), ('BOTTOMPADDING', (0, 0), (-1, -1), 6)]))
    story.append(KeepTogether(box))

story.append(h2('Aturan Main — Supaya Adil untuk Semua'))
rules = [
    ('Dilarang', 'Belanja lewat link sendiri — termasuk memakai akun lain milik sendiri. Komisinya tidak dihitung, dan pelanggaran berulang berakibat akun afiliasi ditangguhkan (data KYC membuat pendaftaran ulang tidak dimungkinkan).'),
    ('Dilarang', 'Spam massal, klaim menyesatkan (harga/garansi palsu), atau mengatasnamakan diri sebagai karyawan resmi Energi.Click.'),
    ('Wajib', 'Data rekening atas nama sendiri dan NPWP untuk keperluan pencairan dana.'),
    ('Catatan', 'Produk berlabel "Minta Penawaran" (skala proyek) tidak menghasilkan komisi otomatis karena harganya dinegosiasikan per proyek.'),
]
rt = Table([[Paragraph(f'<b>{a}</b>', ParagraphStyle('rl', fontName='Helvetica-Bold', fontSize=9.5,
                                                     textColor=(RED if a == 'Dilarang' else BRAND_DARK))),
             Paragraph(b, styles['body'])] for a, b in rules], colWidths=[22*mm, None])
rt.setStyle(TableStyle([
    ('VALIGN', (0, 0), (-1, -1), 'TOP'),
    ('GRID', (0, 0), (-1, -1), 0.5, BORDER),
    ('ROWBACKGROUNDS', (0, 0), (-1, -1), [white, LIGHT2]),
    ('TOPPADDING', (0, 0), (-1, -1), 5), ('BOTTOMPADDING', (0, 0), (-1, -1), 5),
    ('LEFTPADDING', (0, 0), (-1, -1), 7),
]))
story.append(rt)

story.append(h2('Tips Promosi yang Terbukti Jalan'))
story.append(body(
    '&bull;&nbsp; <b>Instalatir/teknisi:</b> sisipkan link di penawaran ke klien — komisi jalan di atas jasa instalasi Anda.<br/>'
    '&bull;&nbsp; <b>Status &amp; grup WA:</b> bagikan link produk spesifik (bukan cuma link toko) + foto/video singkat pemakaian.<br/>'
    '&bull;&nbsp; <b>Konten IG/TikTok/YouTube:</b> review power station atau hitung-hitungan hemat listrik, taruh link di bio/deskripsi.<br/>'
    '&bull;&nbsp; <b>Komunitas perumahan &amp; kantor:</b> tawarkan solusi listrik padam/backup — produk all-in-one paling gampang closing.<br/>'
    '&bull;&nbsp; Pilih produk ber-fee tinggi dari halaman <b>Produk &amp; Komisi</b> — urutan teratas = cuan terbesar per unit.'))

story.append(Spacer(1, 8))
cta = Table([[Paragraph('<b>Siap mulai?</b> Daftar sekarang di <b>energi.click/afiliasi</b> — gratis, 10 menit selesai.<br/>'
                        'Pertanyaan? Hubungi CS kami lewat tombol WhatsApp di website.',
                        ParagraphStyle('cta', fontName='Helvetica', fontSize=11, leading=16, textColor=white, alignment=TA_CENTER))]],
            colWidths=[None], rowHeights=[20*mm])
cta.setStyle(TableStyle([('BACKGROUND', (0, 0), (0, 0), BRAND), ('VALIGN', (0, 0), (0, 0), 'MIDDLE'),
                         ('ROUNDEDCORNERS', [10, 10, 10, 10])]))
story.append(cta)
story.append(Spacer(1, 4))
story.append(Paragraph('Dokumen ini panduan ringkas; ketentuan lengkap &amp; terbaru berlaku sebagaimana tercantum di energi.click/afiliasi. Diperbarui: 9 September 2026.',
                       styles['small']))

doc.build(story)
print('OK', OUT)
