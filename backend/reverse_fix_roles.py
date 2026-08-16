import os
import re

def process_file(filepath):
    with open(filepath, 'r', encoding='utf-8') as f:
        content = f.read()
    
    original = content
    
    # 1. Reverse `->role(...)` back to `->where('role', ...)`
    # This is tricky because `->role($role)` should be `->where('role', $role)`.
    # Let's target specific patterns we changed:
    # We changed `->where('role', ...)` to `->role(...)`.
    # We can reverse it with a regex for `->role(`
    # Since Spatie `role()` takes a string or array, it maps well to `where('role', ...)` or `whereIn('role', ...)`
    
    # Simple substitution for ->role('something')
    content = re.sub(
        r'->role\(\s*([\'"][^\'"]+[\'"])\s*\)',
        r"->where('role', \1)",
        content
    )
    
    content = re.sub(
        r'->role\(\s*(\$role)\s*\)',
        r"->where('role', \1)",
        content
    )

    # Static call `User::role(...)` to `User::where('role', ...)`
    content = re.sub(
        r'([a-zA-Z0-9_\\]+)::role\(\s*([\'"][^\'"]+[\'"])\s*\)',
        r"\1::where('role', \2)",
        content
    )

    if content != original:
        with open(filepath, 'w', encoding='utf-8') as f:
            f.write(content)
        print(f"Reversed: {filepath}")

def main():
    directory = 'app'
    for root, dirs, files in os.walk(directory):
        for file in files:
            if file.endswith('.php'):
                process_file(os.path.join(root, file))

if __name__ == '__main__':
    main()
