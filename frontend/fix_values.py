import re

with open("src/pages/student/portfolio/COEDPortfolioBuilder.jsx", "r", encoding="utf-8") as f:
    content = f.read()

# Replace value={form.xxx} with value={form.xxx || ''}
content = re.sub(r'value=\{form\.([a-zA-Z0-9_]+)\}', r'value={form.\1 || ""}', content)

with open("src/pages/student/portfolio/COEDPortfolioBuilder.jsx", "w", encoding="utf-8") as f:
    f.write(content)
