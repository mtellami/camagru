import { parse } from 'acorn';
import { readFileSync, readdirSync } from 'node:fs';
import { execFileSync } from 'node:child_process';

function walk(path) {
  return readdirSync(path, { withFileTypes: true }).flatMap(entry => entry.isDirectory() ? walk(`${path}/${entry.name}`) : [`${path}/${entry.name}`]);
}
const files = ['src', 'tools', 'public', 'tests'].flatMap(walk);
for (const file of files.filter(file => file.endsWith('.php'))) execFileSync('php', ['-l', file], { stdio: 'pipe' });
parse(readFileSync('public/assets/js/app.js', 'utf8'), { ecmaVersion: 5 });
const manifest = JSON.parse(readFileSync('package.json', 'utf8'));
if (manifest.dependencies && Object.keys(manifest.dependencies).length) throw new Error('No application JavaScript packages are permitted.');
console.log('PHP syntax, ES5 syntax, and application dependency checks passed.');
