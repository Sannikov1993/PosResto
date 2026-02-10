/**
 * Replace `unknown` type annotations with `any` in refs, reactive, etc.
 * Also fix remaining patterns:
 * - <unknown[]> → <any[]>
 * - <unknown> → <any>
 * - as unknown[] → as any[]
 * - as unknown → as any (when used in type assertions for data)
 * - : unknown → : any (in function return types and variable declarations)
 * - Record<string, unknown> → Record<string, any>
 */

import fs from 'fs';
import { glob } from 'glob';

const files = [
    ...await glob('resources/js/**/*.ts', { ignore: ['**/__tests__/**', '**/node_modules/**', '**/types/**'] }),
    ...await glob('resources/js/**/*.vue'),
];

let totalFixed = 0;
let filesFixed = 0;

for (const filePath of files) {
    let content = fs.readFileSync(filePath, 'utf-8');
    const original = content;

    if (filePath.endsWith('.vue') && !content.includes('lang="ts"') && !content.includes("lang='ts'")) continue;

    let fixes = 0;

    // <unknown[]> → <any[]>
    const r1 = content.replace(/<unknown\[\]>/g, '<any[]>');
    if (r1 !== content) { fixes += (content.match(/<unknown\[\]>/g) || []).length; content = r1; }

    // <unknown> → <any> (in generics)
    const r2 = content.replace(/(?<=ref|Ref|shallowRef|reactive|computed|watch)<unknown>/g, '<any>');
    if (r2 !== content) { fixes += (content.match(/(?<=ref|Ref|shallowRef|reactive|computed|watch)<unknown>/g) || []).length; content = r2; }

    // as unknown[] → as any[]
    const r3 = content.replace(/\bas unknown\[\]/g, 'as any[]');
    if (r3 !== content) { fixes += (content.match(/\bas unknown\[\]/g) || []).length; content = r3; }

    // as unknown → as any (be careful not to replace 'as unknown as Type' patterns)
    // Only replace `as unknown` when not followed by ` as `
    const r4 = content.replace(/\bas unknown(?!\s+as\b)/g, 'as any');
    if (r4 !== content) { fixes += (content.match(/\bas unknown(?!\s+as\b)/g) || []).length; content = r4; }

    // Record<string, unknown> → Record<string, any>
    const r5 = content.replace(/Record<string,\s*unknown>/g, 'Record<string, any>');
    if (r5 !== content) { fixes += (content.match(/Record<string,\s*unknown>/g) || []).length; content = r5; }

    // Array<Record<string, unknown>> → Array<Record<string, any>>
    const r6 = content.replace(/Array<Record<string,\s*unknown>>/g, 'Array<Record<string, any>>');
    if (r6 !== content) { fixes += (content.match(/Array<Record<string,\s*unknown>>/g) || []).length; content = r6; }

    if (fixes > 0 && content !== original) {
        fs.writeFileSync(filePath, content, 'utf-8');
        filesFixed++;
        totalFixed += fixes;
    }
}

console.log(`Replaced unknown→any in ${filesFixed} files (${totalFixed} replacements).`);
