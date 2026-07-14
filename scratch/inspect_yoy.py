import pandas as pd

file_path = r"C:\Users\Artee Admin\Downloads\TELE & INT EXP 2026 MAY MONTH .xlsx"
df = pd.read_excel(file_path, sheet_name=0)
print("Shape:", df.shape)
print("Columns:", df.columns.tolist()[:15]) # print first 15 columns
# Print first 25 rows with first 5 columns to see store details
print(df.iloc[:25, :6])
