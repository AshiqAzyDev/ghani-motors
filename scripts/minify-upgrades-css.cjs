const fs = require('fs');
const path = require('path');

const src = path.join(__dirname, '..', 'public', 'css', 'upgrades.css');
const dest = path.join(__dirname, '..', 'public', 'css', 'upgrades.min.css');

const css = fs.readFileSync(src, 'utf8');
const clean = css
    .replace(/\/\*[\s\S]*?\*\//g, '')
    .replace(/\s+/g, ' ')
    .replace(/\s*([{}:;,])\s*/g, '$1')
    .replace(/;}/g, '}')
    .trim();

fs.writeFileSync(dest, clean);
console.log('Wrote', dest, 'bytes', clean.length);
console.log('Preview:', clean.slice(0, 120));
