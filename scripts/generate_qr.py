#!/usr/bin/env python3
"""Buat QR promosi untuk kode penempatan yang sudah dicatat di database."""

import argparse
import re
from pathlib import Path

import qrcode
from qrcode.image.svg import SvgPathImage


def main():
    parser = argparse.ArgumentParser()
    parser.add_argument("code", help="Kode penempatan pada mkt_qr_placements")
    args = parser.parse_args()
    if not re.fullmatch(r"[a-z0-9-]{1,40}", args.code):
        parser.error("kode QR tidak valid")

    url = f"https://umkm.projectpintar.com/q/{args.code}"
    output = Path(__file__).resolve().parents[1] / "public" / "assets" / f"qr-{args.code}.svg"
    qr = qrcode.QRCode(error_correction=qrcode.constants.ERROR_CORRECT_Q, border=4)
    qr.add_data(url)
    qr.make(fit=True)
    qr.make_image(image_factory=SvgPathImage).save(output)
    print(f"{output}: {url}")


if __name__ == "__main__":
    main()
