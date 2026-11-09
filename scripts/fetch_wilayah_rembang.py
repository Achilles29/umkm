#!/usr/bin/env python3
"""Ambil hierarki Rembang dari API wilayah; cek jumlah desa terhadap BPS."""
import json
import subprocess
from datetime import datetime, timezone
from pathlib import Path

BASE = 'https://www.emsifa.com/api-wilayah-indonesia/v2'

def fetch(path):
    result = subprocess.run(['curl', '-fsSL', '--retry', '2', '--max-time', '20', BASE + path],
                            check=True, capture_output=True, text=True)
    payload = json.loads(result.stdout)
    return payload['data'], payload.get('meta', {})

province, province_meta = fetch('/provinces/33.json')
regency, regency_meta = fetch('/regencies/33.17.json')
districts, district_meta = fetch('/districts/33.17.json')
assert province['id'] == '33' and regency['id'] == '33.17'
assert len(districts) == 14, f'Expected 14 districts, got {len(districts)}'
rows = []
for district in districts:
    villages, meta = fetch('/villages/' + district['id'] + '.json')
    rows.append({'code': district['id'], 'name': district['name'],
                 'villages': [{'code': v['id'], 'name': v['name']} for v in villages],
                 'source_meta': meta})
count = sum(len(d['villages']) for d in rows)
assert count == 294, f'BPS 2025 count 294; source returned {count}'
assert len({v['code'] for d in rows for v in d['villages']}) == count
out = {'source': BASE, 'reference': 'Kepmendagri 300.2.2-2430/2025 via emsifa API v2',
       'downloaded_at': datetime.now(timezone.utc).isoformat(),
       'province': {'code': province['id'], 'name': province['name']},
       'regency': {'code': regency['id'], 'name': regency['name']},
       'districts': rows,
       'source_meta': {'province': province_meta, 'regency': regency_meta, 'districts': district_meta}}
path = Path(__file__).resolve().parents[1] / 'database/seeds/wilayah_rembang_2025.json'
path.write_text(json.dumps(out, ensure_ascii=False, indent=2) + '\n')
print(f'Wrote {path.name}: {len(rows)} kecamatan, {count} desa/kelurahan')
