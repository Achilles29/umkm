#!/usr/bin/env python3
"""Bangun seed KBLI 2025 dari PDF resmi BPS yang telah diperiksa.

Sumber: https://ppid.bps.go.id/upload/doc/Klasifikasi_Baku_Lapangan_Usaha_Indonesia_2025_1769138593.pdf
"""
import hashlib
import re
import subprocess
import sys
from pathlib import Path

EXPECTED_SHA256 = "239cf6e6aa7a341e761bde3788e9623fc09612ec7f9cf64529de02e933896f44"
WATERMARK = {"o", ".id", ".g", "ps", "s:", "b", "w", "//w", "tp", "ht", "s", "g", "id"}


def extract(pdf: Path):
    if hashlib.sha256(pdf.read_bytes()).hexdigest() != EXPECTED_SHA256:
        raise ValueError("PDF tidak sama dengan sumber BPS yang telah diperiksa")
    raw = subprocess.check_output(["pdftotext", "-layout", str(pdf), "-"])
    lines = raw.decode("utf-8").splitlines()
    # Uraian kode dimulai sesudah tabel kesesuaian, pada halaman 231 PDF.
    start = next(i for i, line in enumerate(lines) if re.match(r"\s*A\s+PERTANIAN, KEHUTANAN, DAN PERIKANAN\s*$", line) and i > 20000)
    rows = []
    for i, line in enumerate(lines[start:], start):
        match = re.match(r"^\s*([0-9]{5})\s{2,}(.*)$", line)
        if not match:
            continue
        code, title = match.groups()
        if code == "46736":
            title = ""  # watermark pada baris kode memindahkan judul ke baris berikut
        title = re.sub(r"\s{3,}[a-z./:]+$", "", title.strip()).strip()
        parts = [title] if title and re.search("[A-Z]", title) else []
        for next_line in lines[i + 1 : i + 22]:
            text = next_line.strip()
            if not text or text in WATERMARK or re.fullmatch(r"[./a-z: ]{1,12}", text):
                continue
            if re.match(r"^\s*[0-9]{2,5}\s{2,}", next_line):
                break
            if re.match(r"^\s{7,}", next_line):
                text = re.sub(r"\s{3,}[a-z./:]+$", "", text).strip()
                if re.fullmatch(r"[A-Z][A-Z0-9 ,()/:;.&+\-–]+", text) and len(text) > 3:
                    parts.append(text)
                    continue
            break
        title = re.sub(r"\s+", " ", " ".join(parts)).strip()
        title = title.replace("PERAWATANUNTUK", "PERAWATAN UNTUK")
        rows.append((code, title))
    if len(rows) != 1559 or len({code for code, _ in rows}) != 1559:
        raise ValueError("Jumlah kode lima digit tidak sama dengan 1.559")
    if any(not title or len(title) > 255 or re.search(r"\b(DAN|YANG|UNTUK|DARI|DI|ATAU|OLEH|SELAIN|SERTA)$", title) for _, title in rows):
        raise ValueError("Ada judul yang terpotong atau terlalu panjang")
    return rows


def quote(value):
    return "'" + value.replace("\\", "\\\\").replace("'", "''") + "'"


def main():
    if len(sys.argv) != 3:
        raise SystemExit("Gunakan: build_kbli_2025_seed.py sumber.pdf keluaran.sql")
    rows = extract(Path(sys.argv[1]))
    out = Path(sys.argv[2])
    lines = [
        "USE db_umkm;",
        "-- Sumber: BPS, KBLI 2025, Peraturan BPS Nomor 7 Tahun 2025.",
        "-- PDF: https://ppid.bps.go.id/upload/doc/Klasifikasi_Baku_Lapangan_Usaha_Indonesia_2025_1769138593.pdf",
        "-- 1.559 kode kelompok lima digit. Judul diekstrak dan diperiksa jumlah serta keunikannya.",
        "START TRANSACTION;",
        "INSERT INTO ref_kbli_versions (version_label,legal_basis,effective_date,is_current)",
        "VALUES ('KBLI 2025','Peraturan BPS Nomor 7 Tahun 2025',NULL,1)",
        "ON DUPLICATE KEY UPDATE id=LAST_INSERT_ID(id),legal_basis=VALUES(legal_basis),is_current=1;",
        "SET @kbli_version_id=LAST_INSERT_ID();",
    ]
    for start in range(0, len(rows), 200):
        batch = rows[start : start + 200]
        lines.append("INSERT INTO ref_kbli_codes (version_id,code,title,parent_code) VALUES")
        lines.append(",\n".join("(@kbli_version_id,%s,%s,%s)" % (quote(code), quote(title), quote(code[:4])) for code, title in batch))
        lines.append("ON DUPLICATE KEY UPDATE title=VALUES(title),parent_code=VALUES(parent_code);")
    lines += [
        "INSERT IGNORE INTO ops_schema_migrations (version_no,name) VALUES (23,'kbli_2025_bps_seed');",
        "COMMIT;",
    ]
    out.write_text("\n".join(lines) + "\n", encoding="utf-8")
    print(f"{len(rows)} kode KBLI 2025 disiapkan pada {out}")


if __name__ == "__main__":
    main()
