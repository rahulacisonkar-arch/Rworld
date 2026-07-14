import glob
from inspect_pages import parse_pdf_media_box

if __name__ == "__main__":
    files = glob.glob("secure_uploads/label_*.pdf")
    for f in files:
        parse_pdf_media_box(f)
