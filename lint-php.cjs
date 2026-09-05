/**
 * GodsForum - PHP syntax checker.
 *
 * Parses every .php file in the project with php-parser and reports any
 * syntax error. Run it with:  npm run lint:php
 */

const fs = require('fs');
const path = require('path');
const Engine = require('php-parser');

const parser = new Engine({
  parser: { extractDoc: false, suppressErrors: false, version: 803 },
  ast: { withPositions: true },
});

const IGNORED = new Set(['node_modules', '.git', 'vendor']);

function collect(dir, found = []) {
  for (const entry of fs.readdirSync(dir)) {
    if (IGNORED.has(entry)) continue;
    const full = path.join(dir, entry);
    if (fs.statSync(full).isDirectory()) collect(full, found);
    else if (entry.endsWith('.php')) found.push(full);
  }
  return found;
}

const files = collect(process.cwd());
let failed = 0;

for (const file of files) {
  try {
    parser.parseCode(fs.readFileSync(file, 'utf8'), file);
  } catch (error) {
    failed++;
    console.error('SYNTAX ERROR  ' + path.relative(process.cwd(), file));
    console.error('              ' + error.message);
  }
}

if (failed === 0) {
  console.log(`${files.length} PHP files parsed, no syntax errors.`);
  process.exit(0);
}

console.error(`${failed} of ${files.length} PHP files failed to parse.`);
process.exit(1);
