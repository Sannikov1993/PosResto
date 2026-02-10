/**
 * Fix function parameters with default value `null` that need explicit `any` type.
 * Pattern: function foo(param = null) → function foo(param: any = null)
 * Also: (param = null) => → (param: any = null) =>
 * Also: (param = {}) => → (param: any = {}) =>
 * Also: (param = []) => → (param: any = []) =>
 * Also: (param = '') → (param: any = '') (only if causing errors)
 * Also: (param = false) → (param: any = false) (only if causing errors)
 */

import fs from 'fs';
import { glob } from 'glob';

const files = [
    ...await glob('resources/js/**/*.vue'),
    ...await glob('resources/js/**/*.ts', { ignore: ['**/__tests__/**', '**/node_modules/**'] }),
];

let totalFixed = 0;
let filesFixed = 0;

for (const filePath of files) {
    let content = fs.readFileSync(filePath, 'utf-8');
    const original = content;

    if (filePath.endsWith('.vue') && !content.includes('lang="ts"') && !content.includes("lang='ts'")) continue;

    let fixes = 0;

    // Fix: (param = null) → (param: any = null)
    // Match word followed by space = null, where word is not preceded by :
    content = content.replace(/(\(|,\s*)(\w+)\s*=\s*null(?=[,\)])/g, (match, prefix, param) => {
        // Check if already typed
        if (match.includes(':')) return match;
        fixes++;
        return `${prefix}${param}: any = null`;
    });

    // Fix: (param = {}) → (param: any = {})
    content = content.replace(/(\(|,\s*)(\w+)\s*=\s*\{\}(?=[,\)])/g, (match, prefix, param) => {
        if (match.includes(':')) return match;
        fixes++;
        return `${prefix}${param}: any = {}`;
    });

    // Fix: (param = []) → (param: any = [])
    content = content.replace(/(\(|,\s*)(\w+)\s*=\s*\[\](?=[,\)])/g, (match, prefix, param) => {
        if (match.includes(':')) return match;
        fixes++;
        return `${prefix}${param}: any = []`;
    });

    // Fix function declarations: function foo(param = null, param2 = null)
    // This is already covered by the regex above since function params use ()

    if (fixes > 0 && content !== original) {
        fs.writeFileSync(filePath, content, 'utf-8');
        filesFixed++;
        totalFixed += fixes;
        if (fixes >= 3) console.log(`  Fixed ${fixes} in ${filePath}`);
    }
}

console.log(`\nFixed ${totalFixed} null-default params in ${filesFixed} files.`);
