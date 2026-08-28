with open('C:\\xampp\\htdocs\\CCC\\go\\internal\\web\\auth.go', 'rb') as f:
    a = f.read()
with open('C:\\xampp\\htdocs\\CCC\\go\\test4.go', 'rb') as f:
    t4 = f.read()

for i in range(230, 250):
    ca = a[i] if i < len(a) else 0
    ct = t4[i] if i < len(t4) else 0
    ca_char = chr(ca) if 32 <= ca < 127 else f'0x{ca:02X}'
    ct_char = chr(ct) if 32 <= ct < 127 else f'0x{ct:02X}'
    print(f'Pos {i}: auth={ca_char} test4={ct_char}')