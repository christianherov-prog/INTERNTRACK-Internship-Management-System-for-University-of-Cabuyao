import os, glob

files = glob.glob('frontend/src/**/*.jsx', recursive=True)
c = 0

for f in files:
    with open(f, 'r', encoding='utf8') as file:
        content = file.read()
    
    original = content
    content = content.replace('? "Programs" : p', '? "All Programs" : p')
    content = content.replace('? \'Programs\' : p', '? \'All Programs\' : p')
    
    if content != original:
        with open(f, 'w', encoding='utf8') as file:
            file.write(content)
        c += 1

print(f'Fixed {c} files')
