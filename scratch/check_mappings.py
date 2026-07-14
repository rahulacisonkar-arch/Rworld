import pandas as pd
import re
import json

df = pd.read_excel(r'C:\Users\Artee Admin\Downloads\store utility elec and gas analysis file.xlsx', header=None)

month_arg = 5 # May
m_row_idx = 3 + month_arg # Row 8 for May

active_stores = {
    '62': {'code': '62', 'name': 'Richmond'},
    '63': {'code': '63', 'name': 'Wilmington'},
    '64': {'code': '64', 'name': 'Virginia Beach'},
    '67': {'code': '67', 'name': 'Metairie'},
    '70': {'code': '70', 'name': 'Cincinnati'},
    '82': {'code': '82', 'name': 'Raleigh'},
    '83': {'code': '03', 'name': 'Burlington'}
}

years = [2024, 2025, 2026]
rows_data = []

# Each store starts at columns 1, 11, 21, ...
for k in range(13):
    c_start = 1 + k * 10
    if c_start >= df.shape[1]:
        break
    
    store_val = df.iloc[0, c_start]
    if pd.isna(store_val) or str(store_val).strip() == '':
        continue
        
    store_str = str(store_val).strip()
    match = re.search(r'-?\s*(\d+)$', store_str)
    if not match:
        continue
    excel_code = match.group(1)
    
    if excel_code not in active_stores:
        continue
        
    store_info = active_stores[excel_code]
    store_name = store_info['name']
    store_code = store_info['code']
    
    yoy_values = {y: 0.0 for y in years}
    
    # 3 triplets of columns for years 2024, 2025, 2026
    triplets = [
        (c_start, 2024),
        (c_start + 3, 2025),
        (c_start + 6, 2026)
    ]
    
    for start_col, default_year in triplets:
        if start_col >= df.shape[1]:
            continue
            
        # Determine the year for this triplet
        triplet_year = None
        for col_offset in range(3):
            col_idx = start_col + col_offset
            if col_idx < df.shape[1]:
                year_val = df.iloc[2, col_idx]
                if pd.notna(year_val) and str(year_val).strip() != '':
                    try:
                        triplet_year = int(float(year_val))
                        break
                    except:
                        pass
        if triplet_year is None:
            triplet_year = default_year
            
        # Parse utilities and sum values
        for col_offset in range(3):
            col_idx = start_col + col_offset
            if col_idx < df.shape[1]:
                util_val = df.iloc[3, col_idx]
                if pd.notna(util_val) and str(util_val).strip() != '':
                    util_str = str(util_val).strip().lower()
                    if 'elect' in util_str or 'elec' in util_str:
                        utility = 'Electricity'
                    elif 'gas' in util_str:
                        utility = 'Gas'
                    else:
                        utility = None
                else:
                    utility = None
                    
                if utility in ['Gas', 'Electricity']:
                    val = df.iloc[m_row_idx, col_idx]
                    try:
                        val_float = float(val) if pd.notna(val) and str(val).strip() != '' else 0.0
                    except:
                        val_float = 0.0
                    yoy_values[triplet_year] = yoy_values.get(triplet_year, 0.0) + val_float
                    
    record = {
        'code': store_code,
        'store': store_name
    }
    for y in years:
        record[str(y)] = yoy_values[y]
    rows_data.append(record)

print(json.dumps({'years': [str(y) for y in years], 'data': rows_data}, indent=2))
