import pandas as pd
import glob
import os

downloads_dir = r"C:\Users\Artee Admin\Downloads"
for f in glob.glob(os.path.join(downloads_dir, "*.xlsx")):
    if "~$" in f:
        continue
    try:
        xl = pd.ExcelFile(f)
        print(f"\nFile: {os.path.basename(f)}")
        print("  Sheets:", xl.sheet_names)
        # print first few rows of first sheet
        df = xl.parse(xl.sheet_names[0])
        print("  Columns:", df.columns.tolist())
        print("  Head:\n", df.head(3))
    except Exception as e:
        print(f"Error reading {f}: {e}")
