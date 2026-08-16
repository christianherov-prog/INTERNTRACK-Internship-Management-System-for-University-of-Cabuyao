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
    
    // Find all { ... } in JSX. We'll use a simple regex that finds { something }
    // It's not a perfect AST parser but it will help us find suspicious { ... program ... }
    const regex = /\{([^}]*program[^}]*)\}/gi;
    let match;
    while ((match = regex.exec(content)) !== null) {
        let expr = match[1].trim();
        // Ignore known safe things
        if (expr.includes('typeof') || expr.includes('getStudentProgram') || expr.includes('setProgramFilter') || expr.includes('target_program_')) continue;
        
        // Find line number
        let lineNumber = content.substring(0, match.index).split('\n').length;
        console.log([:] );
    }
});
