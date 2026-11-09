from pathlib import Path
import sys

import pypdfium2 as pdfium


def render(pdf_path: Path, output_dir: Path, scale: float = 2.0) -> None:
    output_dir.mkdir(parents=True, exist_ok=True)
    pdf = pdfium.PdfDocument(str(pdf_path))
    for index in range(len(pdf)):
        page = pdf[index]
        bitmap = page.render(scale=scale)
        image = bitmap.to_pil()
        image.save(output_dir / f"page-{index + 1}.png")
        page.close()
    pdf.close()


if __name__ == "__main__":
    render(Path(sys.argv[1]), Path(sys.argv[2]))
