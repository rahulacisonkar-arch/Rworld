import pandas as pd
import datetime
import re

file_path = r"C:\Users\Artee Admin\Downloads\TELE & INT EXP 2026 MAY MONTH .xlsx"
df = pd.read_excel(file_path, sheet_name=0)

def parse_col_header(col):
    if isinstance(col, datetime.datetime):
        month = col.month
        if col.day in [23, 24, 25, 26]:
            year = 2000 + col.day
        else:
            year = col.year
        return month, year
        
    col_str = str(col).strip()
    match = re.match(r'^([A-Za-z]+)-(\d{2})(?:\.\d+)?$', col_str)
    if match:
        mon_name = match.group(1).upper()
        yr_short = int(match.group(2))
        year = 2000 + yr_short
        months_abbrev = {
            'JAN': 1, 'FEB': 2, 'MAR': 3, 'APR': 4, 'MAY': 5, 'JUN': 6,
            'JUL': 7, 'AUG': 8, 'SEP': 9, 'OCT': 10, 'NOV': 11, 'DEC': 12
        }
        if mon_name in months_abbrev:
            return months_abbrev[mon_name], year
            
    try:
        dt = pd.to_datetime(col_str)
        if pd.notna(dt) and dt.year > 100:
            month = dt.month
            if dt.day in [23, 24, 25, 26]:
                year = 2000 + dt.day
            else:
                year = dt.year
            return month, year
    except:
        pass
        
    return None, None

for i, col in enumerate(df.columns):
    m, y = parse_col_header(col)
    if m is not None:
        print(f"Col {i:3d}: Raw={col} -> M={m}, Y={y}")
