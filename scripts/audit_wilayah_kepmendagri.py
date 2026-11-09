#!/usr/bin/env python3
"""Bandingkan master Rembang dengan lampiran Kepmendagri 300.2.2-2138/2025."""
import argparse
import json
import re
import subprocess
import unicodedata
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]


def normalized(value):
    return ' '.join(unicodedata.normalize('NFKD', value).encode('ascii', 'ignore').decode().lower().split())


def main():
    parser = argparse.ArgumentParser()
    parser.add_argument('lampiran_pdf', type=Path)
    args = parser.parse_args()
    source = subprocess.run(['pdftotext', '-layout', str(args.lampiran_pdf), '-'],
                            check=True, capture_output=True, text=True).stdout
    begin = source.index('C.m.17) Kabupaten Rembang')
    end = source.index('33.18           Kabupaten Pati', begin)
    section = source[begin:end]
    official_districts, official_villages = {}, {}
    for line in section.splitlines():
        village = re.match(r'^(33\.17\.\d{2}\.\d{4})\s+\d+\s+(.+?)(?:\s{2,}|$)', line)
        if village:
            official_villages[village.group(1)] = village.group(2).strip()
            continue
        district = re.match(r'^(33\.17\.\d{2})\s+\d+\s+(.+?)(?:\s{2,}|$)', line)
        if district:
            official_districts[district.group(1)] = district.group(2).strip()

    master = json.loads((ROOT / 'database/seeds/wilayah_rembang_2025.json').read_text())
    local_districts = {d['code']: d['name'] for d in master['districts']}
    local_villages = {v['code']: v['name'] for d in master['districts'] for v in d['villages']}
    differences = []
    for label, local, official in (('kecamatan', local_districts, official_districts),
                                    ('desa/kelurahan', local_villages, official_villages)):
        if set(local) != set(official):
            differences.append(f'{label}: kode berbeda: lokal saja {sorted(set(local)-set(official))}, resmi saja {sorted(set(official)-set(local))}')
        for code in local.keys() & official.keys():
            if normalized(local[code]) != normalized(official[code]):
                differences.append(f'{label} {code}: {local[code]} / {official[code]}')
    print(f'Resmi: {len(official_districts)} kecamatan, {len(official_villages)} desa/kelurahan')
    print(f'Lokal: {len(local_districts)} kecamatan, {len(local_villages)} desa/kelurahan')
    if differences:
        print('\n'.join(differences))
        raise SystemExit(1)
    print('PASS: semua kode dan nama sama')


if __name__ == '__main__':
    main()
