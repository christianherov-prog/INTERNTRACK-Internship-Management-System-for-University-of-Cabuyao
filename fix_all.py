import os, glob, re

files = glob.glob('frontend/src/**/*.jsx', recursive=True)
c = 0

for f in files:
    with open(f, 'r', encoding='utf8') as file:
        content = file.read()
    
    original = content
    
    # 1. Update "Programs" label
    content = content.replace('? "Programs" : p', '? "All Programs" : p')
    content = content.replace('? \'Programs\' : p', '? \'All Programs\' : p')
    
    # 2. Swap `program?.code || program?.name` to `program?.name || program?.code`
    # We will do this explicitly:
    content = re.sub(r'([a-zA-Z0-9_\.\?]+)\.program\?\.code\s*\|\|\s*\1\.program\?\.name', r'\1.program?.name || \1.program?.code', content)
    
    # 3. For any remaining `something.program?.code` that doesn't have `.name`, let's just replace it with `.program?.name || something.program?.code`
    # Let's use a simpler match: match any word characters and dots/question marks before `.program?.code`
    # Actually, we can just replace `.program?.code` with `.program?.name || MATCHED.program?.code`
    def replacer(m):
        prefix = m.group(1)
        if 'program?.name' in content[max(0, m.start()-30):m.end()+30]:
            return m.group(0) # skip if name is already nearby
        return f"{prefix}.program?.name || {prefix}.program?.code"
        
    content = re.sub(r'([a-zA-Z0-9_]+(?:\.[a-zA-Z0-9_]+|\?[a-zA-Z0-9_]+)*)\.program\?\.code', replacer, content)
    
    if content != original:
        with open(f, 'w', encoding='utf8') as file:
            file.write(content)
        c += 1

print(f'Fixed {c} files')
