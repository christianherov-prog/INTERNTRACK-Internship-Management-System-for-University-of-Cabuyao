const fs = require('fs');
const path = require('path');

function walkDir(dir, callback) {
    fs.readdirSync(dir).forEach(f => {
        let dirPath = path.join(dir, f);
        let isDirectory = fs.statSync(dirPath).isDirectory();
        isDirectory ? walkDir(dirPath, callback) : callback(path.join(dir, f));
    });
}

walkDir('frontend/src', function(filePath) {
    if (!filePath.endsWith('.jsx')) return;
    
    let content = fs.readFileSync(filePath, 'utf8');
    let original = content;

    const regex1 = /([a-zA-Z0-9_\.\?]+)\.program\?\.code\s*\|\|\s*\1\.program/g;
    
    content = content.replace(regex1, (match, p1) => {
        return `(typeof ${p1}.program === 'string' ? ${p1}.program : ${p1}.program?.code || ${p1}.program?.name)`;
    });

    if (content !== original) {
        fs.writeFileSync(filePath, content, 'utf8');
        console.log('Fixed ' + filePath);
    }
});
