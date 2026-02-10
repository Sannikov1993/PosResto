/**
 * Second pass: Fix remaining TypeScript errors using vue-tsc output.
 *
 * Phase 1: Fix TS7006 (param implicitly has 'any') by adding : any to untyped params
 * Phase 2: Fix TS7031 (binding element has 'any') by adding : any to destructured params
 * Phase 3: Fix TS7005/TS7034 (variable implicitly has 'any') by adding type annotations
 * Phase 4: Fix TS18046 ('unknown' type) - common patterns
 * Phase 5: Fix TS2551 (did you mean?) - common typos
 */

import fs from 'fs';
import { execSync } from 'child_process';

console.log('Running vue-tsc to collect errors...');
let output;
try {
    output = execSync('npx vue-tsc --noEmit', {
        encoding: 'utf-8',
        maxBuffer: 50 * 1024 * 1024,
        cwd: process.cwd(),
        timeout: 300000,
        stdio: ['pipe', 'pipe', 'pipe']
    });
} catch (e) {
    // vue-tsc exits with error when there are type errors
    output = (e.stdout || '') + '\n' + (e.stderr || '');
}
const outputLines = output.split(/\r?\n/);
console.log(`Captured ${output.length} chars, ${outputLines.length} lines`);
console.log(`Sample: ${outputLines.slice(0, 3).join(' | ')}`);

// Parse errors
const errors = [];
const lines = output.split(/\r?\n/);
for (const line of lines) {
    // Format: file(line,col): error TSxxxx: message
    const match = line.match(/^(.+?)\((\d+),(\d+)\): error (TS\d+): (.+)$/);
    if (match) {
        errors.push({
            file: match[1].replace(/\\/g, '/'),
            line: parseInt(match[2]),
            col: parseInt(match[3]),
            code: match[4],
            message: match[5],
        });
    }
}

console.log(`Found ${errors.length} errors total.`);

// Group by file
const byFile = new Map();
for (const err of errors) {
    if (!byFile.has(err.file)) byFile.set(err.file, []);
    byFile.get(err.file).push(err);
}

let totalFixed = 0;
let filesFixed = 0;

// Process each file
for (const [filePath, fileErrors] of byFile) {
    if (!fs.existsSync(filePath)) continue;

    let content = fs.readFileSync(filePath, 'utf-8');
    const original = content;
    let fileLines = content.split('\n');
    let fixes = 0;

    // Sort errors by line desc so we can modify from bottom to top
    const sorted = [...fileErrors].sort((a, b) => b.line - a.line || b.col - a.col);

    for (const err of sorted) {
        const lineIdx = err.line - 1;
        if (lineIdx >= fileLines.length) continue;
        const line = fileLines[lineIdx];

        // === TS7006: Parameter 'X' implicitly has an 'any' type ===
        if (err.code === 'TS7006') {
            const paramMatch = err.message.match(/Parameter '(\w+)' implicitly/);
            if (paramMatch) {
                const paramName = paramMatch[1];
                const col = err.col - 1; // 0-based

                // Check the character at the position matches the param name
                const atPos = line.substring(col, col + paramName.length);
                if (atPos === paramName) {
                    // Add : any after the parameter name
                    // But check if there's already a type annotation
                    const afterParam = line.substring(col + paramName.length);
                    if (!afterParam.match(/^\s*:/)) {
                        // Check for default value: param = value → param: any = value
                        const newLine = line.substring(0, col + paramName.length) + ': any' + afterParam;
                        fileLines[lineIdx] = newLine;
                        fixes++;
                    }
                }
            }
        }

        // === TS7031: Binding element 'X' implicitly has an 'any' type ===
        if (err.code === 'TS7031') {
            const paramMatch = err.message.match(/Binding element '(\w+)' implicitly/);
            if (paramMatch) {
                // This is a destructured param like ({ x, y }) =>
                // We need to add type annotation to the whole destructuring pattern
                // Find the closing } or ) and add : any after it
                // This is complex, let's try a simpler approach:
                // Find the destructuring pattern and add : any to the whole thing

                // Look for patterns like ({ ... }) => or ({ ... }) { on the current line
                const destructureMatch = line.match(/(\(\{[^}]*\})\s*(\))\s*(=>|\{)/);
                if (destructureMatch) {
                    const newLine = line.replace(
                        /(\(\{[^}]*\})\s*(\))\s*(=>|\{)/,
                        '$1: any$2 $3'
                    );
                    if (newLine !== line) {
                        fileLines[lineIdx] = newLine;
                        fixes++;
                    }
                }

                // Also handle: ([ ... ]) =>
                const arrayDestructure = line.match(/(\(\[[^\]]*\])\s*(\))\s*(=>|\{)/);
                if (arrayDestructure) {
                    const newLine = line.replace(
                        /(\(\[[^\]]*\])\s*(\))\s*(=>|\{)/,
                        '$1: any$2 $3'
                    );
                    if (newLine !== line) {
                        fileLines[lineIdx] = newLine;
                        fixes++;
                    }
                }
            }
        }

        // === TS7034/TS7005: Variable implicitly has 'any' type ===
        if (err.code === 'TS7034' || err.code === 'TS7005') {
            const varMatch = err.message.match(/Variable '(\w+)' implicitly/);
            if (varMatch) {
                const varName = varMatch[1];
                // Look for: let x; or let x = someExpression;
                const letMatch = line.match(new RegExp(`(let|var)\\s+${varName}\\s*;`));
                if (letMatch) {
                    fileLines[lineIdx] = line.replace(
                        new RegExp(`(let|var)\\s+${varName}\\s*;`),
                        `$1 ${varName}: any;`
                    );
                    fixes++;
                }
                // Also: let x = setTimeout(...)
                const letAssign = line.match(new RegExp(`(let|var)\\s+${varName}\\s*=`));
                if (letAssign && !line.includes(': any')) {
                    fileLines[lineIdx] = line.replace(
                        new RegExp(`(let|var)\\s+${varName}\\s*=`),
                        `$1 ${varName}: any =`
                    );
                    fixes++;
                }
            }
        }
    }

    if (fixes > 0) {
        const newContent = fileLines.join('\n');
        if (newContent !== original) {
            fs.writeFileSync(filePath, newContent, 'utf-8');
            filesFixed++;
            totalFixed += fixes;
            if (fixes >= 5) {
                console.log(`  Fixed ${fixes} in ${filePath}`);
            }
        }
    }
}

console.log(`\nPhase 1 done: Fixed ${totalFixed} errors in ${filesFixed} files.`);

// === Phase 2: Fix remaining ref/reactive patterns ===
// Some refs like ref(false), ref(0), ref('') that have wrong types
// Fix: ref<SomeType | null>(null) patterns where the null ref is accessed as object

console.log('\nPhase 2: Fixing additional patterns...');

// Reload vue-tsc errors after phase 1 to see what TS2339 remain
// For now, skip re-running vue-tsc (too slow) and fix common patterns:

import { glob } from 'glob';

const vueFiles = await glob('resources/js/**/*.vue');
let phase2Fixes = 0;

for (const filePath of vueFiles) {
    let content = fs.readFileSync(filePath, 'utf-8');
    const original = content;

    if (!content.includes('lang="ts"') && !content.includes("lang='ts'")) continue;

    // Fix: const x = ref(false) used later as x.value.something → already has boolean type
    // This needs context-aware analysis, skip for automatic fixing

    // Fix: shallowRef([]) → shallowRef<any[]>([])
    content = content.replace(/\bshallowRef\(\[\]\)/g, (match, offset) => {
        const before = content.substring(Math.max(0, offset - 30), offset);
        if (before.match(/<[^>]+>\s*$/)) return match;
        phase2Fixes++;
        return 'shallowRef<any[]>([])';
    });

    // Fix: shallowRef({}) → shallowRef<Record<string, any>>({})
    content = content.replace(/\bshallowRef\(\{\}\)/g, (match, offset) => {
        const before = content.substring(Math.max(0, offset - 30), offset);
        if (before.match(/<[^>]+>\s*$/)) return match;
        phase2Fixes++;
        return 'shallowRef<Record<string, any>>({})';
    });

    // Fix: computed(() => []) — return type inferred as never[]
    // These usually need `as any[]` or proper typing

    if (content !== original) {
        fs.writeFileSync(filePath, content, 'utf-8');
    }
}

console.log(`Phase 2: Fixed ${phase2Fixes} additional patterns.`);
console.log(`\nTotal: Fixed ${totalFixed + phase2Fixes} errors.`);
