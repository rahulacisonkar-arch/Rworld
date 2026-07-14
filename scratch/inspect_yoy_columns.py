import pandas as pd

file_path = r"C:\Users\Artee Admin\Downloads\TELE & INT EXP 2026 MAY MONTH .xlsx"
df = pd.read_excel(file_path, sheet_name=0)
cols = df.columns.tolist()
for i in range(0, len(cols), 10):
    print(cols[i:i+10])
