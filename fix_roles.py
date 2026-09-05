import os
import re

def process_directory(directory):
    pattern = re.compile(r"->where\('role',\s*(.*?)\)")
    pattern2 = re.compile(r"::where\('role',\s*(.*?)\)")
    
    for root, _, files in os.walk(directory):
        for file in files:
            if file.endswith('.php'):
                filepath = os.path.join(root, file)
                with open(filepath, 'r', encoding='utf-8') as f:
                    content = f.read()
                
                new_content = pattern.sub(r"->role(\1)", content)
                new_content = pattern2.sub(r"::role(\1)", new_content)
                
                if new_content != content:
                    print(f"Updated {filepath}")
                    with open(filepath, 'w', encoding='utf-8') as f:
                        f.write(new_content)

if __name__ == "__main__":
    process_directory('backend/app')
