const fs = require('fs');
const path = require('path');

const src = path.join(__dirname, '..', 'public', 'js', 'site.js');
const dest = path.join(__dirname, '..', 'public', 'js', 'site.min.js');
const js = fs.readFileSync(src, 'utf8');

// Lightweight minify: drop block comments and collapse whitespace outside strings.
let out = '';
let i = 0;
let inSingle = false;
let inDouble = false;
let inTemplate = false;
let inLineComment = false;
let inBlockComment = false;

while (i < js.length) {
    const ch = js[i];
    const next = js[i + 1];

    if (inLineComment) {
        if (ch === '\n') {
            inLineComment = false;
            out += '\n';
        }
        i += 1;
        continue;
    }
    if (inBlockComment) {
        if (ch === '*' && next === '/') {
            inBlockComment = false;
            i += 2;
            continue;
        }
        i += 1;
        continue;
    }
    if (!inSingle && !inDouble && !inTemplate) {
        if (ch === '/' && next === '/') {
            inLineComment = true;
            i += 2;
            continue;
        }
        if (ch === '/' && next === '*') {
            inBlockComment = true;
            i += 2;
            continue;
        }
        if (ch === "'") {
            inSingle = true;
            out += ch;
            i += 1;
            continue;
        }
        if (ch === '"') {
            inDouble = true;
            out += ch;
            i += 1;
            continue;
        }
        if (ch === '`') {
            inTemplate = true;
            out += ch;
            i += 1;
            continue;
        }
        if (/\s/.test(ch)) {
            if (out.length && !/\s/.test(out[out.length - 1])) {
                out += ' ';
            }
            i += 1;
            continue;
        }
        out += ch;
        i += 1;
        continue;
    }

    out += ch;
    if (ch === '\\') {
        out += next ?? '';
        i += 2;
        continue;
    }
    if (inSingle && ch === "'") {
        inSingle = false;
    } else if (inDouble && ch === '"') {
        inDouble = false;
    } else if (inTemplate && ch === '`') {
        inTemplate = false;
    }
    i += 1;
}

fs.writeFileSync(dest, out.trim());
console.log('Wrote', dest, 'bytes', out.trim().length);
