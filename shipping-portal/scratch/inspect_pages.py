import struct

def parse_pdf_media_box(file_path):
    print(f"Inspecting {file_path}...")
    with open(file_path, 'rb') as f:
        content = f.read()
    
    # Simple search for MediaBox
    idx = 0
    while True:
        idx = content.find(b'/MediaBox', idx)
        if idx == -1:
            break
        
        # Grab the next 100 bytes to see the array
        snippet = content[idx:idx+100].decode('latin1', errors='ignore')
        print(f"Found MediaBox definition: {snippet[:60].strip()}")
        idx += 9

if __name__ == "__main__":
    parse_pdf_media_box("scratch/label_a4.pdf")
    parse_pdf_media_box("scratch/label_4x6.pdf")
