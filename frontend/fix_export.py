import re

with open("src/pages/student/portfolio/COEDPortfolioPreview.jsx", "r", encoding="utf-8") as f:
    content = f.read()

content = content.replace("export default COEDPortfolioPreview;", "")
content += "\nexport default COEDPortfolioPreview;\n"

with open("src/pages/student/portfolio/COEDPortfolioPreview.jsx", "w", encoding="utf-8") as f:
    f.write(content)
