#!/usr/bin/env python3
"""Generate Techvenza's original SVG illustration library.

One motif per category (three layout/colour variants each) plus page illustrations.
Output: wp-content/plugins/techvenza-core/assets/illustrations/<name>.svg (1600×900).

    python3 bin/illustrations.py            # write SVGs
    node bin/illustrations-png.mjs          # render 1200×630 JPEG social cards

The artwork is original, licence-free and owned by the project. Edit this file and re-run to
change the style; editors replace any image from the Media Library without touching code.
"""
import math
import os
import random

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
OUT = os.path.join(ROOT, 'wp-content/plugins/techvenza-core/assets/illustrations')
W, H = 1600, 900

CATS = {
    'ai': ('#7C3AED', 'neural'),
    'business': ('#0D9488', 'growth'),
    'technology': ('#2563EB', 'chip'),
    'software': ('#4F46E5', 'code'),
    'startups': ('#F97362', 'rocket'),
    'manufacturing': ('#F59E0B', 'gears'),
    'cybersecurity': ('#E11D48', 'shield'),
    'cloud': ('#0284C7', 'cloud'),
    'data': ('#059669', 'charts'),
    'digital-transformation': ('#9333EA', 'transform'),
    'martech': ('#DB2777', 'megaphone'),
    'fintech': ('#0891B2', 'finance'),
    'healthtech': ('#DC2626', 'health'),
    'robotics': ('#EA580C', 'robot'),
    'product': ('#2563EB', 'target'),
    'future-of-work': ('#0D9488', 'work'),
    'sustainability': ('#16A34A', 'leaf'),
}
PAGES = {
    'page-about': ('#2563EB', 'newsroom'),
    'page-authors': ('#7C3AED', 'pen'),
    'page-newsletter': ('#2563EB', 'mail'),
    'page-contribute': ('#F97362', 'pen'),
    'page-contact': ('#0D9488', 'mail'),
    'page-404': ('#4F46E5', 'compass'),
    'default': ('#2563EB', 'neural'),
}
INK = '#0F172A'


def hex_rgb(h):
    h = h.lstrip('#')
    return tuple(int(h[i:i + 2], 16) for i in (0, 2, 4))


def mix(a, b, t):
    ra, rb = hex_rgb(a), hex_rgb(b)
    return '#%02X%02X%02X' % tuple(round(x + (y - x) * t) for x, y in zip(ra, rb))


def pal(color, variant):
    base = color
    shift = ['#2563EB', '#F97362', '#0D9488'][variant % 3]
    return {
        'c': base,
        'c2': mix(base, shift, 0.55),
        'dark': mix(base, INK, 0.55),
        'soft': mix(base, '#FFFFFF', 0.86),
        'soft2': mix(base, '#FFFFFF', 0.72),
        'mid': mix(base, '#FFFFFF', 0.45),
        'white': '#FFFFFF',
    }


def background(p, rnd, uid):
    bx, by = rnd.randint(1000, 1350), rnd.randint(120, 300)
    dots = ''.join(
        f'<circle cx="{x}" cy="{y}" r="2.2"/>'
        for x in range(80, W, 56) for y in range(70, H, 56)
        if rnd.random() < 0.55
    )
    return (
        f'<defs><linearGradient id="bg{uid}" x1="0" y1="0" x2="1" y2="1">'
        f'<stop offset="0" stop-color="{p["soft"]}"/><stop offset="1" stop-color="#FFFFFF"/></linearGradient>'
        f'<radialGradient id="blob{uid}" cx="0.5" cy="0.5" r="0.5"><stop offset="0" stop-color="{p["mid"]}" stop-opacity=".55"/>'
        f'<stop offset="1" stop-color="{p["mid"]}" stop-opacity="0"/></radialGradient>'
        f'<linearGradient id="ac{uid}" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="{p["c"]}"/>'
        f'<stop offset="1" stop-color="{p["c2"]}"/></linearGradient></defs>'
        f'<rect width="{W}" height="{H}" fill="url(#bg{uid})"/>'
        f'<circle cx="{bx}" cy="{by}" r="520" fill="url(#blob{uid})"/>'
        f'<circle cx="{rnd.randint(150, 400)}" cy="{rnd.randint(650, 820)}" r="380" fill="url(#blob{uid})"/>'
        f'<g fill="{p["dark"]}" opacity=".10">{dots}</g>'
    )


def card(x, y, w, h, p, r=28, fill='#FFFFFF', shadow=True):
    s = f'<rect x="{x + 10}" y="{y + 16}" width="{w}" height="{h}" rx="{r}" fill="{p["dark"]}" opacity=".10"/>' if shadow else ''
    return s + f'<rect x="{x}" y="{y}" width="{w}" height="{h}" rx="{r}" fill="{fill}" stroke="{p["soft2"]}" stroke-width="3"/>'


def stroke(p, w=10, color=None):
    return f'fill="none" stroke="{color or p["dark"]}" stroke-width="{w}" stroke-linecap="round" stroke-linejoin="round"'


# ---------------------------------------------------------------- motifs (centre ~ (cx, cy))

def m_neural(p, rnd, uid, cx, cy):
    layers = [3, 5, 5, 3]
    pts = []
    for i, n in enumerate(layers):
        x = cx - 330 + i * 220
        col = [(x, cy - (n - 1) * 70 + j * 140) for j in range(n)]
        pts.append(col)
    lines = ''
    for a, b in zip(pts, pts[1:]):
        for (x1, y1) in a:
            for (x2, y2) in b:
                op = 0.18 + 0.5 * rnd.random()
                lines += f'<line x1="{x1}" y1="{y1}" x2="{x2}" y2="{y2}" stroke="{p["c"]}" stroke-width="3" opacity="{op:.2f}"/>'
    nodes = ''
    for i, col in enumerate(pts):
        for (x, y) in col:
            hot = rnd.random() < 0.35
            nodes += f'<circle cx="{x}" cy="{y}" r="{30 if hot else 24}" fill="{"url(#ac%s)" % uid if hot else "#FFFFFF"}" stroke="{p["dark"]}" stroke-width="6"/>'
    spark = ''.join(sparkle(cx + dx, cy + dy, s, p) for dx, dy, s in ((380, -290, 46), (-420, 250, 34), (300, 300, 26)))
    return lines + nodes + spark


def sparkle(x, y, s, p):
    return (f'<path d="M{x} {y - s} C{x + s * .18} {y - s * .18} {x + s * .18} {y - s * .18} {x + s} {y} '
            f'C{x + s * .18} {y + s * .18} {x + s * .18} {y + s * .18} {x} {y + s} C{x - s * .18} {y + s * .18} '
            f'{x - s * .18} {y + s * .18} {x - s} {y} C{x - s * .18} {y - s * .18} {x - s * .18} {y - s * .18} {x} {y - s}Z" fill="{p["c2"]}"/>')


def m_growth(p, rnd, uid, cx, cy):
    out = card(cx - 380, cy - 260, 760, 520, p)
    bars = ''
    vals = sorted(rnd.sample(range(90, 330), 5))
    for i, v in enumerate(vals):
        x = cx - 300 + i * 120
        bars += f'<rect x="{x}" y="{cy + 200 - v}" width="76" height="{v}" rx="14" fill="{p["c"] if i == 4 else p["soft2"]}"/>'
    line = ' '.join(f'{cx - 262 + i * 120},{cy + 160 - v}' for i, v in enumerate(vals))
    arrow = f'<polyline points="{line}" {stroke(p, 9)}/>'
    lx, ly = cx - 262 + 4 * 120, cy + 160 - vals[-1]
    head = f'<path d="M{lx - 44} {ly - 6} L{lx + 6} {ly - 20} L{lx - 10} {ly + 30}" {stroke(p, 9)}/>'
    people = ''.join(
        f'<circle cx="{cx + 470}" cy="{cy - 180 + i * 150}" r="34" fill="{c}"/><path d="M{cx + 420} {cy - 100 + i * 150} q50 -58 100 0" fill="{c}"/>'
        for i, c in enumerate((p['c'], p['c2'], p['dark'])))
    return out + bars + arrow + head + people


def m_chip(p, rnd, uid, cx, cy):
    s = 300
    traces = ''
    for i in range(6):
        o = -s / 2 + 30 + i * (s - 60) / 5
        for (x1, y1, x2, y2, x3, y3) in (
            (cx + o, cy - s / 2, cx + o, cy - s / 2 - 90, cx + o + rnd.choice((-60, 60)), cy - s / 2 - 160),
            (cx + o, cy + s / 2, cx + o, cy + s / 2 + 90, cx + o + rnd.choice((-60, 60)), cy + s / 2 + 160),
            (cx - s / 2, cy + o, cx - s / 2 - 90, cy + o, cx - s / 2 - 170, cy + o + rnd.choice((-50, 50))),
            (cx + s / 2, cy + o, cx + s / 2 + 90, cy + o, cx + s / 2 + 170, cy + o + rnd.choice((-50, 50))),
        ):
            traces += f'<polyline points="{x1},{y1} {x2},{y2} {x3},{y3}" {stroke(p, 6, p["c"])} opacity=".75"/><circle cx="{x3}" cy="{y3}" r="11" fill="#FFFFFF" stroke="{p["c"]}" stroke-width="5"/>'
    chip = (f'<rect x="{cx - s / 2}" y="{cy - s / 2}" width="{s}" height="{s}" rx="36" fill="{p["dark"]}"/>'
            f'<rect x="{cx - s / 2 + 50}" y="{cy - s / 2 + 50}" width="{s - 100}" height="{s - 100}" rx="22" fill="url(#ac{uid})"/>'
            f'<path d="M{cx - 40} {cy + 20} l40 -70 l40 70 m-64 -20 h48" {stroke(p, 12, "#FFFFFF")}/>')
    return traces + chip


def m_code(p, rnd, uid, cx, cy):
    out = card(cx - 420, cy - 280, 840, 560, p, fill='#FFFFFF')
    out += f'<rect x="{cx - 420}" y="{cy - 280}" width="840" height="70" rx="28" fill="{p["dark"]}"/><rect x="{cx - 420}" y="{cy - 240}" width="840" height="30" fill="{p["dark"]}"/>'
    out += ''.join(f'<circle cx="{cx - 380 + i * 36}" cy="{cy - 245}" r="10" fill="{c}"/>' for i, c in enumerate(('#F97362', '#F59E0B', '#22C55E')))
    y = cy - 160
    for i in range(7):
        indent = rnd.choice((0, 0, 50, 100))
        segs = rnd.randint(1, 3)
        x = cx - 360 + indent
        for s in range(segs):
            w = rnd.randint(70, 200)
            col = (p['c'], p['c2'], p['mid'], p['soft2'])[rnd.randint(0, 3)]
            out += f'<rect x="{x}" y="{y}" width="{w}" height="22" rx="11" fill="{col}"/>'
            x += w + 24
        y += 52
    out += f'<path d="M{cx + 240} {cy + 40} l-70 70 l70 70 M{cx + 330} {cy + 40} l70 70 l-70 70" {stroke(p, 14, p["c"])}/>'
    return out


def m_rocket(p, rnd, uid, cx, cy):
    g = f'<g transform="rotate(35 {cx} {cy})">'
    g += f'<path d="M{cx} {cy - 300} C{cx + 120} {cy - 200} {cx + 120} {cy + 40} {cx + 90} {cy + 150} L{cx - 90} {cy + 150} C{cx - 120} {cy + 40} {cx - 120} {cy - 200} {cx} {cy - 300}Z" fill="#FFFFFF" stroke="{p["dark"]}" stroke-width="10"/>'
    g += f'<circle cx="{cx}" cy="{cy - 90}" r="48" fill="url(#ac{uid})" stroke="{p["dark"]}" stroke-width="10"/>'
    g += f'<path d="M{cx - 90} {cy + 40} L{cx - 170} {cy + 170} L{cx - 90} {cy + 150}Z M{cx + 90} {cy + 40} L{cx + 170} {cy + 170} L{cx + 90} {cy + 150}Z" fill="{p["c"]}" stroke="{p["dark"]}" stroke-width="10" stroke-linejoin="round"/>'
    g += f'<path d="M{cx - 55} {cy + 160} Q{cx} {cy + 360} {cx + 55} {cy + 160}Z" fill="#F59E0B"/><path d="M{cx - 28} {cy + 160} Q{cx} {cy + 280} {cx + 28} {cy + 160}Z" fill="#FDE68A"/></g>'
    stars = ''.join(sparkle(cx + rnd.randint(-560, 560), cy + rnd.randint(-330, 330), rnd.randint(14, 30), p) for _ in range(6))
    trail = ''.join(f'<circle cx="{cx - 220 - i * 70}" cy="{cy + 250 + i * 40}" r="{40 - i * 7}" fill="{p["soft2"]}"/>' for i in range(5))
    return stars + trail + g


def gear(x, y, r, teeth, p, fill):
    pts = []
    for i in range(teeth * 2):
        a = math.pi * i / teeth
        rr = r if i % 2 == 0 else r * 0.82
        for da in (-0.12, 0.12):
            pts.append(f'{x + rr * math.cos(a + da):.1f},{y + rr * math.sin(a + da):.1f}')
    return (f'<polygon points="{" ".join(pts)}" fill="{fill}" stroke="{p["dark"]}" stroke-width="8" stroke-linejoin="round"/>'
            f'<circle cx="{x}" cy="{y}" r="{r * 0.32:.0f}" fill="#FFFFFF" stroke="{p["dark"]}" stroke-width="8"/>')


def m_gears(p, rnd, uid, cx, cy):
    out = gear(cx - 120, cy - 60, 190, 12, p, f'url(#ac{uid})')
    out += gear(cx + 175, cy + 120, 120, 9, p, p['soft2'])
    out += gear(cx + 230, cy - 210, 80, 7, p, '#FFFFFF')
    belt = f'<rect x="{cx - 520}" y="{cy + 260}" width="1040" height="60" rx="30" fill="{p["dark"]}"/>'
    belt += ''.join(f'<circle cx="{cx - 480 + i * 120}" cy="{cy + 290}" r="16" fill="{p["mid"]}"/>' for i in range(9))
    boxes = ''.join(card(cx - 470 + i * 260, cy + 170, 110, 90, p, r=14, fill=(p['c'], '#FFFFFF')[i % 2], shadow=False) for i in range(4))
    return belt + boxes + out


def m_shield(p, rnd, uid, cx, cy):
    net = ''
    nodes = [(cx + rnd.randint(-620, 620), cy + rnd.randint(-330, 330)) for _ in range(10)]
    for i, a in enumerate(nodes):
        b = nodes[(i + 3) % len(nodes)]
        net += f'<line x1="{a[0]}" y1="{a[1]}" x2="{b[0]}" y2="{b[1]}" stroke="{p["c"]}" stroke-width="3" opacity=".3"/>'
    net += ''.join(f'<circle cx="{x}" cy="{y}" r="12" fill="{p["c"]}" opacity=".6"/>' for x, y in nodes)
    sh = (f'<path d="M{cx} {cy - 290} L{cx + 230} {cy - 200} V{cy + 10} C{cx + 230} {cy + 170} {cx + 110} {cy + 270} {cx} {cy + 310} '
          f'C{cx - 110} {cy + 270} {cx - 230} {cy + 170} {cx - 230} {cy + 10} V{cy - 200}Z" fill="url(#ac{uid})" stroke="{p["dark"]}" stroke-width="12" stroke-linejoin="round"/>')
    lock = (f'<rect x="{cx - 80}" y="{cy - 20}" width="160" height="130" rx="20" fill="#FFFFFF"/>'
            f'<path d="M{cx - 50} {cy - 20} V{cy - 70} a50 50 0 0 1 100 0 V{cy - 20}" {stroke(p, 18, "#FFFFFF")}/>'
            f'<circle cx="{cx}" cy="{cy + 35}" r="18" fill="{p["dark"]}"/><rect x="{cx - 7}" y="{cy + 40}" width="14" height="40" rx="7" fill="{p["dark"]}"/>')
    return net + sh + lock


def cloud_shape(x, y, s, fill, p):
    return (f'<path d="M{x - 1.6 * s} {y + .55 * s} a{.6 * s} {.6 * s} 0 0 1 {.2 * s} {-1.15 * s} a{.85 * s} {.85 * s} 0 0 1 {1.55 * s} {-.35 * s} '
            f'a{.7 * s} {.7 * s} 0 0 1 {1.2 * s} {.55 * s} a{.55 * s} {.55 * s} 0 0 1 {.25 * s} {.95 * s}Z" fill="{fill}" stroke="{p["dark"]}" stroke-width="10" stroke-linejoin="round"/>')


def m_cloud(p, rnd, uid, cx, cy):
    out = cloud_shape(cx, cy - 60, 190, f'url(#ac{uid})', p)
    out += cloud_shape(cx + 420, cy - 220, 80, '#FFFFFF', p) + cloud_shape(cx - 430, cy - 200, 60, '#FFFFFF', p)
    for i in range(3):
        x = cx - 330 + i * 230
        out += card(x, cy + 170, 200, 150, p, r=20, shadow=True)
        out += ''.join(f'<rect x="{x + 24}" y="{cy + 195 + j * 40}" width="120" height="18" rx="9" fill="{p["soft2"]}"/><circle cx="{x + 172}" cy="{cy + 204 + j * 40}" r="9" fill="{p["c"]}"/>' for j in range(3))
        out += f'<path d="M{x + 100} {cy + 170} V{cy + 90}" {stroke(p, 6, p["c"])} stroke-dasharray="4 16"/>'
    return out


def m_charts(p, rnd, uid, cx, cy):
    out = card(cx - 470, cy - 280, 560, 380, p)
    pts = [(cx - 420 + i * 70, cy - 20 - rnd.randint(20, 200)) for i in range(8)]
    area = ' '.join(f'{x},{y}' for x, y in pts)
    out += f'<polygon points="{pts[0][0]},{cy + 60} {area} {pts[-1][0]},{cy + 60}" fill="{p["soft2"]}"/><polyline points="{area}" {stroke(p, 8, p["c"])}/>'
    out += ''.join(f'<circle cx="{x}" cy="{y}" r="10" fill="#FFFFFF" stroke="{p["c"]}" stroke-width="6"/>' for x, y in pts)
    out += card(cx + 150, cy - 280, 330, 330, p)
    r, ox, oy = 110, cx + 315, cy - 115
    a0 = 0
    for frac, col in ((0.45, p['c']), (0.3, p['c2']), (0.25, p['soft2'])):
        a1 = a0 + frac * 2 * math.pi
        large = 1 if frac > 0.5 else 0
        out += (f'<path d="M{ox} {oy} L{ox + r * math.cos(a0):.1f} {oy + r * math.sin(a0):.1f} A{r} {r} 0 {large} 1 '
                f'{ox + r * math.cos(a1):.1f} {oy + r * math.sin(a1):.1f}Z" fill="{col}" stroke="#FFFFFF" stroke-width="6"/>')
        a0 = a1
    out += card(cx - 470, cy + 150, 950, 150, p)
    out += ''.join(f'<rect x="{cx - 430 + i * 110}" y="{cy + 270 - h}" width="64" height="{h}" rx="10" fill="{(p["c"], p["mid"])[i % 2]}"/>' for i, h in enumerate(rnd.sample(range(30, 100), 8)))
    return out


def m_transform(p, rnd, uid, cx, cy):
    out = card(cx - 470, cy - 130, 260, 260, p, fill=p['soft2'])
    out += f'<rect x="{cx + 210}" y="{cy - 150}" width="290" height="290" rx="145" fill="url(#ac{uid})" stroke="{p["dark"]}" stroke-width="10"/>'
    out += f'<path d="M{cx - 160} {cy - 40} C{cx - 40} {cy - 180} {cx + 80} {cy - 180} {cx + 170} {cy - 60}" {stroke(p, 12)}/><path d="M{cx + 130} {cy - 100} l45 40 l-55 25" {stroke(p, 12)}/>'
    out += f'<path d="M{cx + 170} {cy + 70} C{cx + 60} {cy + 200} {cx - 60} {cy + 200} {cx - 160} {cy + 80}" {stroke(p, 12, p["c"])}/><path d="M{cx - 120} {cy + 120} l-45 -40 l55 -25" {stroke(p, 12, p["c"])}/>'
    out += ''.join(f'<rect x="{cx - 430 + (i % 3) * 70}" y="{cy - 90 + (i // 3) * 70}" width="50" height="50" rx="10" fill="#FFFFFF" stroke="{p["dark"]}" stroke-width="5"/>' for i in range(9))
    out += ''.join(sparkle(cx + 355 + dx, cy + dy, 22, p) for dx, dy in ((-60, -40), (50, 30), (-10, 80)))
    return out


def m_megaphone(p, rnd, uid, cx, cy):
    out = (f'<path d="M{cx - 300} {cy - 70} L{cx + 120} {cy - 250} V{cy + 250} L{cx - 300} {cy + 70}Z" fill="url(#ac{uid})" stroke="{p["dark"]}" stroke-width="12" stroke-linejoin="round"/>'
           f'<rect x="{cx - 400}" y="{cy - 90}" width="120" height="180" rx="30" fill="#FFFFFF" stroke="{p["dark"]}" stroke-width="12"/>'
           f'<path d="M{cx - 300} {cy + 80} L{cx - 250} {cy + 260} H{cx - 170} L{cx - 200} {cy + 100}" fill="#FFFFFF" stroke="{p["dark"]}" stroke-width="12" stroke-linejoin="round"/>')
    out += ''.join(f'<path d="M{cx + 200 + i * 80} {cy - 140 - i * 40} Q{cx + 290 + i * 80} {cy} {cx + 200 + i * 80} {cy + 140 + i * 40}" {stroke(p, 12, (p["c"], p["c2"], p["mid"])[i])}/>' for i in range(3))
    icons = ''.join(f'<circle cx="{x}" cy="{y}" r="34" fill="#FFFFFF" stroke="{p["dark"]}" stroke-width="6"/>' + sparkle(x, y, 16, p) for x, y in ((cx + 560, cy - 260), (cx + 600, cy + 230), (cx - 520, cy - 280)))
    return out + icons


def m_finance(p, rnd, uid, cx, cy):
    out = (f'<g transform="rotate(-10 {cx} {cy})">{card(cx - 360, cy - 150, 520, 320, p, fill=p["dark"])}'
           f'<rect x="{cx - 310}" y="{cy - 80}" width="90" height="66" rx="12" fill="#FDE68A"/>'
           f'<rect x="{cx - 310}" y="{cy + 80}" width="300" height="22" rx="11" fill="{p["mid"]}"/></g>')
    out += f'<g transform="rotate(6 {cx} {cy})">{card(cx - 200, cy - 60, 520, 320, p, fill="url(#ac%s)" % uid)}<rect x="{cx - 150}" y="{cy + 10}" width="90" height="66" rx="12" fill="#FDE68A"/><rect x="{cx - 150}" y="{cy + 170}" width="240" height="22" rx="11" fill="#FFFFFF" opacity=".8"/></g>'
    coin = ''.join(f'<ellipse cx="{cx + 470}" cy="{cy + 200 - i * 34}" rx="90" ry="30" fill="{("#F59E0B", "#FBBF24")[i % 2]}" stroke="{p["dark"]}" stroke-width="7"/>' for i in range(5))
    spark = f'<polyline points="{cx - 560},{cy - 220} {cx - 480},{cy - 270} {cx - 410},{cy - 240} {cx - 330},{cy - 320}" {stroke(p, 10, p["c"])}/>'
    return out + coin + spark


def m_health(p, rnd, uid, cx, cy):
    heart = (f'<path d="M{cx} {cy + 230} C{cx - 340} {cy + 20} {cx - 300} {cy - 280} {cx - 120} {cy - 260} C{cx - 50} {cy - 255} {cx} {cy - 200} {cx} {cy - 160} '
             f'C{cx} {cy - 200} {cx + 50} {cy - 255} {cx + 120} {cy - 260} C{cx + 300} {cy - 280} {cx + 340} {cy + 20} {cx} {cy + 230}Z" fill="url(#ac{uid})" stroke="{p["dark"]}" stroke-width="12"/>')
    pulse = f'<polyline points="{cx - 640},{cy - 10} {cx - 160},{cy - 10} {cx - 110},{cy - 110} {cx - 40},{cy + 110} {cx + 30},{cy - 60} {cx + 80},{cy - 10} {cx + 640},{cy - 10}" {stroke(p, 12, "#FFFFFF")}/>'
    pulse_bg = f'<polyline points="{cx - 640},{cy - 10} {cx - 160},{cy - 10} {cx - 110},{cy - 110} {cx - 40},{cy + 110} {cx + 30},{cy - 60} {cx + 80},{cy - 10} {cx + 640},{cy - 10}" {stroke(p, 22, p["dark"])}/>'
    cross = f'<g transform="translate({cx + 430} {cy - 250})">{card(-70, -70, 140, 140, p, r=24)}<path d="M0 -40 V40 M-40 0 H40" {stroke(p, 18, p["c"])}/></g>'
    return heart + pulse_bg + pulse + cross


def m_robot(p, rnd, uid, cx, cy):
    base = f'<rect x="{cx - 260}" y="{cy + 230}" width="260" height="70" rx="20" fill="{p["dark"]}"/>'
    arm = (f'<path d="M{cx - 130} {cy + 230} L{cx - 60} {cy - 30} L{cx + 210} {cy - 170}" {stroke(p, 64, p["dark"])}/>'
           f'<path d="M{cx - 130} {cy + 230} L{cx - 60} {cy - 30} L{cx + 210} {cy - 170}" {stroke(p, 44, p["c"])}/>')
    joints = ''.join(f'<circle cx="{x}" cy="{y}" r="44" fill="#FFFFFF" stroke="{p["dark"]}" stroke-width="10"/><circle cx="{x}" cy="{y}" r="14" fill="{p["c2"]}"/>' for x, y in ((cx - 130, cy + 230), (cx - 60, cy - 30), (cx + 210, cy - 170)))
    claw = f'<path d="M{cx + 240} {cy - 140} l90 50 l-20 60 M{cx + 250} {cy - 200} l100 -10 l20 60" {stroke(p, 16)}/>'
    box = card(cx + 290, cy + 60, 200, 160, p, r=18, fill=p['soft2'])
    return base + arm + joints + claw + box


def m_target(p, rnd, uid, cx, cy):
    out = ''.join(f'<circle cx="{cx - 100}" cy="{cy}" r="{r}" fill="{f}" stroke="{p["dark"]}" stroke-width="10"/>' for r, f in ((270, '#FFFFFF'), (195, p['soft2']), (120, '#FFFFFF'), (50, f'url(#ac{uid})')))
    out += f'<path d="M{cx + 360} {cy - 300} L{cx - 90} {cy - 10}" {stroke(p, 14)}/><path d="M{cx + 360} {cy - 300} l60 -10 l-30 60 Z M{cx + 360} {cy - 300} l-10 -60 l60 30 Z" fill="{p["c"]}" stroke="{p["dark"]}" stroke-width="8" stroke-linejoin="round"/>'
    road = ''.join(f'<rect x="{cx + 260}" y="{cy + 60 + i * 80}" width="{220 - i * 40}" height="44" rx="22" fill="{(p["c"], p["c2"], p["mid"])[i]}"/>' for i in range(3))
    return out + road


def m_work(p, rnd, uid, cx, cy):
    lap = (card(cx - 330, cy - 240, 660, 420, p, r=24, fill=p['dark']) +
           f'<rect x="{cx - 300}" y="{cy - 210}" width="600" height="360" rx="12" fill="#FFFFFF"/>'
           f'<path d="M{cx - 420} {cy + 180} H{cx + 420} l-50 60 H{cx - 370}Z" fill="{p["mid"]}" stroke="{p["dark"]}" stroke-width="10" stroke-linejoin="round"/>')
    tiles = ''
    for i in range(4):
        x = cx - 270 + (i % 2) * 290
        y = cy - 180 + (i // 2) * 160
        c = (p['c'], p['c2'], p['dark'], p['mid'])[i]
        tiles += f'<rect x="{x}" y="{y}" width="250" height="140" rx="14" fill="{p["soft"]}"/><circle cx="{x + 125}" cy="{y + 55}" r="30" fill="{c}"/><path d="M{x + 80} {y + 130} q45 -60 90 0" fill="{c}"/>'
    globe = (f'<circle cx="{cx + 520}" cy="{cy - 220}" r="90" fill="url(#ac{uid})" stroke="{p["dark"]}" stroke-width="8"/>'
             f'<ellipse cx="{cx + 520}" cy="{cy - 220}" rx="40" ry="90" fill="none" stroke="#FFFFFF" stroke-width="6"/><path d="M{cx + 430} {cy - 220} H{cx + 610}" stroke="#FFFFFF" stroke-width="6"/>')
    return lap + tiles + globe


def m_leaf(p, rnd, uid, cx, cy):
    sun = f'<circle cx="{cx + 400}" cy="{cy - 230}" r="90" fill="#FBBF24"/>' + ''.join(
        f'<line x1="{cx + 400 + 120 * math.cos(a):.0f}" y1="{cy - 230 + 120 * math.sin(a):.0f}" x2="{cx + 400 + 160 * math.cos(a):.0f}" y2="{cy - 230 + 160 * math.sin(a):.0f}" {stroke(p, 10, "#F59E0B")}/>'
        for a in [i * math.pi / 4 for i in range(8)])
    turbine = (f'<path d="M{cx + 330} {cy + 320} L{cx + 350} {cy - 20} L{cx + 370} {cy + 320}Z" fill="#FFFFFF" stroke="{p["dark"]}" stroke-width="8" stroke-linejoin="round"/>'
               + ''.join(f'<path d="M{cx + 350} {cy - 20} l{150 * math.cos(a):.0f} {150 * math.sin(a):.0f}" {stroke(p, 22, p["dark"])}/>' for a in (-math.pi / 2, math.pi / 6, 5 * math.pi / 6))
               + f'<circle cx="{cx + 350}" cy="{cy - 20}" r="20" fill="{p["c"]}"/>')
    leaf = (f'<path d="M{cx - 380} {cy + 260} C{cx - 420} {cy - 60} {cx - 200} {cy - 300} {cx + 120} {cy - 280} C{cx + 140} {cy + 40} {cx - 80} {cy + 260} {cx - 380} {cy + 260}Z" fill="url(#ac{uid})" stroke="{p["dark"]}" stroke-width="12" stroke-linejoin="round"/>'
            f'<path d="M{cx - 380} {cy + 260} C{cx - 200} {cy + 60} {cx - 60} {cy - 100} {cx + 100} {cy - 260}" {stroke(p, 10, "#FFFFFF")}/>')
    return sun + turbine + leaf


def m_newsroom(p, rnd, uid, cx, cy):
    out = card(cx - 420, cy - 260, 520, 560, p)
    out += f'<rect x="{cx - 380}" y="{cy - 220}" width="440" height="200" rx="16" fill="url(#ac{uid})"/>'
    out += ''.join(f'<rect x="{cx - 380}" y="{cy + 10 + i * 46}" width="{(440, 400, 420, 300, 360)[i]}" height="20" rx="10" fill="{p["soft2"]}"/>' for i in range(5))
    out += card(cx + 160, cy - 200, 300, 200, p) + card(cx + 160, cy + 40, 300, 200, p)
    out += f'<path d="M{cx + 210} {cy - 120} h200 M{cx + 210} {cy - 70} h140 M{cx + 210} {cy + 120} h200 M{cx + 210} {cy + 170} h120" {stroke(p, 16, p["mid"])}/>'
    return out


def m_pen(p, rnd, uid, cx, cy):
    out = card(cx - 380, cy - 280, 560, 600, p)
    out += ''.join(f'<rect x="{cx - 330}" y="{cy - 220 + i * 60}" width="{rnd.randint(300, 460)}" height="20" rx="10" fill="{p["soft2"]}"/>' for i in range(8))
    out += (f'<g transform="rotate(40 {cx + 250} {cy})"><rect x="{cx + 210}" y="{cy - 330}" width="80" height="460" rx="20" fill="url(#ac{uid})" stroke="{p["dark"]}" stroke-width="10"/>'
            f'<path d="M{cx + 210} {cy + 130} L{cx + 250} {cy + 230} L{cx + 290} {cy + 130}Z" fill="#FDE68A" stroke="{p["dark"]}" stroke-width="10" stroke-linejoin="round"/></g>')
    return out + sparkle(cx + 420, cy - 260, 40, p)


def m_mail(p, rnd, uid, cx, cy):
    out = card(cx - 360, cy - 200, 720, 440, p, r=30)
    out += f'<path d="M{cx - 340} {cy - 180} L{cx} {cy + 60} L{cx + 340} {cy - 180}" {stroke(p, 14)}/>'
    out += f'<rect x="{cx - 260}" y="{cy - 330}" width="520" height="260" rx="20" fill="url(#ac{uid})" opacity=".95" transform="rotate(-4 {cx} {cy - 200})"/>'
    out += f'<path d="M{cx - 200} {cy - 270} h300 M{cx - 200} {cy - 220} h220" {stroke(p, 16, "#FFFFFF")} transform="rotate(-4 {cx} {cy - 200})"/>'
    return out + sparkle(cx + 470, cy - 220, 36, p) + sparkle(cx - 480, cy + 200, 26, p)


def m_compass(p, rnd, uid, cx, cy):
    out = f'<circle cx="{cx}" cy="{cy}" r="280" fill="#FFFFFF" stroke="{p["dark"]}" stroke-width="12"/><circle cx="{cx}" cy="{cy}" r="230" fill="{p["soft"]}"/>'
    out += ''.join(f'<line x1="{cx + 230 * math.cos(a):.0f}" y1="{cy + 230 * math.sin(a):.0f}" x2="{cx + 200 * math.cos(a):.0f}" y2="{cy + 200 * math.sin(a):.0f}" {stroke(p, 8)}/>' for a in [i * math.pi / 6 for i in range(12)])
    out += f'<path d="M{cx} {cy - 190} L{cx + 50} {cy} L{cx} {cy + 190} L{cx - 50} {cy}Z" fill="url(#ac{uid})" stroke="{p["dark"]}" stroke-width="10" stroke-linejoin="round"/><circle cx="{cx}" cy="{cy}" r="22" fill="#FFFFFF" stroke="{p["dark"]}" stroke-width="8"/>'
    return out


MOTIFS = {k[2:]: v for k, v in globals().items() if k.startswith('m_')}


def build(name, color, motif, variant):
    rnd = random.Random(f'{name}-{variant}')
    p = pal(color, variant)
    uid = f'{name}{variant}'.replace('-', '')
    cx, cy = 800, 450
    # Variants differ in scale, placement and floating accents so a category's covers don't repeat.
    scale, dx, dy = ((1.0, 0, 0), (0.82, -170, 20), (0.9, 190, -10))[variant % 3]
    deco = ''
    for _ in range(3 + variant * 2):
        x, y, s2 = rnd.randint(60, W - 60), rnd.randint(60, H - 60), rnd.randint(18, 46)
        if abs(x - (cx + dx)) < 520 and abs(y - (cy + dy)) < 360:
            continue
        shape = rnd.choice(('c', 'r', 's'))
        col = rnd.choice((p['c'], p['c2'], p['mid']))
        if shape == 'c':
            deco += f'<circle cx="{x}" cy="{y}" r="{s2}" fill="none" stroke="{col}" stroke-width="6" opacity=".6"/>'
        elif shape == 'r':
            deco += f'<rect x="{x}" y="{y}" width="{s2 * 2}" height="{s2 * 2}" rx="{s2 // 2}" fill="{col}" opacity=".35" transform="rotate({rnd.randint(-25, 25)} {x} {y})"/>'
        else:
            deco += sparkle(x, y, s2, p)
    art = MOTIFS[motif](p, rnd, uid, cx, cy)
    body = background(p, rnd, uid) + deco + (
        f'<g transform="translate({cx + dx} {cy + dy}) scale({scale}) translate({-cx} {-cy})">{art}</g>'
    )
    return (f'<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 {W} {H}" width="{W}" height="{H}" role="img">'
            f'{body}</svg>\n')


def main():
    os.makedirs(OUT, exist_ok=True)
    count = 0
    for slug, (color, motif) in CATS.items():
        for v in range(3):
            with open(os.path.join(OUT, f'{slug}-{v + 1}.svg'), 'w', encoding='utf-8') as f:
                f.write(build(slug, color, motif, v))
            count += 1
    for name, (color, motif) in PAGES.items():
        with open(os.path.join(OUT, f'{name}.svg'), 'w', encoding='utf-8') as f:
            f.write(build(name, color, motif, 0))
        count += 1
    print(f'{count} illustrations written to {OUT}')


if __name__ == '__main__':
    main()
