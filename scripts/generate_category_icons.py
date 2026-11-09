#!/usr/bin/env python3
"""Buat ilustrasi SVG kecil untuk kategori etalase."""
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1] / 'public/assets/categories'
ROOT.mkdir(parents=True, exist_ok=True)

# Latar dan bentuk vektor dibuat lokal agar kategori tetap tampil tanpa layanan gambar luar.
ICONS = {
    'kuliner': ('#fff0e3', '#e98c48', '<circle cx="48" cy="49" r="24" fill="white" stroke="currentColor" stroke-width="4"/><circle cx="48" cy="49" r="13" fill="none" stroke="currentColor" stroke-width="3"/><path d="M15 24v22m-6-22v13m12-13v13M15 46v28M80 24c-9 7-10 20 0 23v27" fill="none" stroke="currentColor" stroke-width="4" stroke-linecap="round"/>'),
    'batik-fesyen': ('#f3eaff', '#8757b4', '<path d="M30 18 20 29l-8 17 15 7 5-10v31h32V43l5 10 15-7-8-17-10-11-9 7H39z" fill="white" stroke="currentColor" stroke-width="4" stroke-linejoin="round"/><path d="M40 43c7-10 12 10 19 0M39 57c8-9 11 9 19 0" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round"/>'),
    'kerajinan': ('#ecf5ee', '#4f936d', '<path d="M28 23h40v9c-9 3-12 10-12 16l8 25H32l8-25c0-6-3-13-12-16z" fill="white" stroke="currentColor" stroke-width="4" stroke-linejoin="round"/><path d="M32 35h32M39 58c6-7 13-7 19 0" fill="none" stroke="currentColor" stroke-width="3"/><circle cx="48" cy="47" r="3" fill="currentColor"/>'),
    'hasil-laut': ('#e4f5ff', '#2889bb', '<path d="M70 48c-12-18-36-19-49 0 13 19 37 18 49 0zM70 48l15-14v28z" fill="white" stroke="currentColor" stroke-width="4" stroke-linejoin="round"/><circle cx="38" cy="43" r="3" fill="currentColor"/><path d="M14 72c9-4 17-4 26 0m14 0c9-4 17-4 26 0" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round"/>'),
    'pertanian': ('#eaf8e7', '#6b9d4d', '<path d="M48 78V47M48 57c-18-1-28-14-25-31 18 0 28 10 25 31zm0-9c1-16 12-25 27-25 0 17-10 26-27 25z" fill="white" stroke="currentColor" stroke-width="4" stroke-linejoin="round"/><path d="M31 79h34" fill="none" stroke="currentColor" stroke-width="4" stroke-linecap="round"/>'),
    'kecantikan': ('#fff0f4', '#d36e94', '<path d="M37 28h22v11l8 8v28H29V47l8-8z" fill="white" stroke="currentColor" stroke-width="4" stroke-linejoin="round"/><path d="M41 21h14v7H41zM38 56h20" fill="none" stroke="currentColor" stroke-width="4"/><path d="m73 20 2 6 6 2-6 2-2 6-2-6-6-2 6-2z" fill="currentColor"/>'),
    'jasa': ('#eaf1ff', '#5d7fca', '<circle cx="48" cy="49" r="25" fill="white" stroke="currentColor" stroke-width="4"/><path d="m48 26 3 10 10 3-7 8 2 10-8-5-8 5 2-10-7-8 10-3z" fill="none" stroke="currentColor" stroke-width="3" stroke-linejoin="round"/><path d="M48 14v8m0 54v8M13 49h8m54 0h8" stroke="currentColor" stroke-width="4" stroke-linecap="round"/>'),
    'ritel': ('#fff4e2', '#c4903f', '<path d="M23 39h50l-4 39H27z" fill="white" stroke="currentColor" stroke-width="4" stroke-linejoin="round"/><path d="M35 39V30a13 13 0 0 1 26 0v9" fill="none" stroke="currentColor" stroke-width="4" stroke-linecap="round"/><path d="M35 54h26" stroke="currentColor" stroke-width="3"/>'),
    'wisata': ('#e7f5f3', '#489d9a', '<rect x="23" y="30" width="50" height="47" rx="7" fill="white" stroke="currentColor" stroke-width="4"/><path d="M38 30v-8h20v8M48 30v47M23 49h50" fill="none" stroke="currentColor" stroke-width="4"/><circle cx="72" cy="23" r="9" fill="#ffd877"/>'),
}

for slug, (background, foreground, shapes) in ICONS.items():
    svg = f'''<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 96 96" role="img" aria-label="{slug}">
<rect width="96" height="96" rx="23" fill="{background}"/>
<g color="{foreground}">{shapes}</g></svg>'''
    (ROOT / f'{slug}.svg').write_text(svg)
print(f'{len(ICONS)} ikon kategori dibuat di {ROOT}')
