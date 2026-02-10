/**
 * Final pass: Fix remaining TypeScript errors using vue-tsc output.
 *
 * Strategy: For each error, apply targeted fix based on error code.
 * Process file by file, sorting errors from bottom to top to preserve line numbers.
 */

import fs from 'fs';
import { execSync } from 'child_process';

console.log('Running vue-tsc to collect errors...');
let output;
try {
    output = execSync('npx vue-tsc --noEmit', {
        encoding: 'utf-8',
        maxBuffer: 50 * 1024 * 1024,
        timeout: 300000,
        stdio: ['pipe', 'pipe', 'pipe']
    });
} catch (e) {
    output = (e.stdout || '') + '\n' + (e.stderr || '');
}

// Parse errors
const errors = [];
for (const line of output.split(/\r?\n/)) {
    const match = line.match(/^(.+?)\((\d+),(\d+)\): error (TS\d+): (.+)$/);
    if (match) {
        errors.push({
            file: match[1],
            line: parseInt(match[2]),
            col: parseInt(match[3]),
            code: match[4],
            message: match[5],
        });
    }
}

console.log(`Found ${errors.length} errors.`);

// Group by file
const byFile = new Map();
for (const err of errors) {
    if (!byFile.has(err.file)) byFile.set(err.file, []);
    byFile.get(err.file).push(err);
}

let totalFixed = 0;
let filesFixed = 0;

for (const [filePath, fileErrors] of byFile) {
    if (!fs.existsSync(filePath)) continue;

    let fileLines = fs.readFileSync(filePath, 'utf-8').split('\n');
    const originalLines = [...fileLines];
    let fixes = 0;

    // Process errors from bottom to top
    const sorted = [...fileErrors].sort((a, b) => b.line - a.line || b.col - a.col);

    // Track which lines already have // @ts-expect-error above them
    const tsExpectErrorLines = new Set();

    for (const err of sorted) {
        const lineIdx = err.line - 1;
        if (lineIdx >= fileLines.length || lineIdx < 0) continue;

        const line = fileLines[lineIdx];

        // === TS7053: Element implicitly has 'any' type (indexing) ===
        // Common pattern: obj[key] where obj doesn't have index signature
        // Fix: add `as any` before [key] → (obj as any)[key]
        // Too risky with regex, skip

        // === TS18047: 'X' is possibly 'null' ===
        // === TS18048: 'X' is possibly 'undefined' ===
        if (err.code === 'TS18047' || err.code === 'TS18048') {
            const varMatch = err.message.match(/'([^']+)' is possibly/);
            if (varMatch) {
                const varName = varMatch[1];
                const col = err.col - 1;

                // In template section: add `!` after the variable (non-null assertion)
                // In script section: add `!` or optional chaining
                // This is risky, skip automatic fix for now
            }
        }

        // === TS2362/TS2363: Left/right side of arithmetic must be number ===
        if (err.code === 'TS2362' || err.code === 'TS2363') {
            // These are often: item.price * item.quantity where item is any
            // If item is already any, these shouldn't occur. Skip.
        }

        // === TS2740: Type '{}' is missing properties from type ===
        if (err.code === 'TS2740') {
            // These are from assigning {} to a typed variable. Already handled by ref<Record<string, any>>.
            // Skip remaining.
        }

        // === TS2339: Property 'X' does not exist on type 'Y' ===
        if (err.code === 'TS2339') {
            // Check if this is from a 'never' type (data arrays not properly typed)
            if (err.message.includes("type 'never'")) {
                // These need the source array to be typed. Can't fix from error location.
                // Skip.
            }
            // Check for EventTarget/Element issues
            if (err.message.includes("type 'EventTarget'") || err.message.includes("type 'Element'")) {
                // Common fix: ($event.target as HTMLInputElement).value
                // Skip for automatic fix
            }
        }

        // === TS2551: Did you mean? ===
        if (err.code === 'TS2551') {
            // Skip — these might be real issues, don't auto-fix
        }

        // === TS7005: Variable implicitly has 'any' type ===
        if (err.code === 'TS7005') {
            const varMatch = err.message.match(/Variable '(\w+)' implicitly/);
            if (varMatch) {
                const varName = varMatch[1];
                // Add : any to the variable declaration
                const declMatch = line.match(new RegExp(`(let|var|const)\\s+${varName}\\s*(?=;|=)`));
                if (declMatch && !line.includes(': any')) {
                    fileLines[lineIdx] = line.replace(
                        new RegExp(`(let|var|const)\\s+${varName}\\s*`),
                        `$& : any `
                    );
                    fixes++;
                }
            }
        }
    }

    // For files with many remaining errors, add @ts-nocheck to suppress all
    // Only do this for files with > 20 errors that are mostly in templates
    const remainingErrors = fileErrors.length - fixes;
    if (remainingErrors > 20 && filePath.endsWith('.vue')) {
        // Check if most errors are in template section (high line numbers relative to script)
        const scriptMatch = fs.readFileSync(filePath, 'utf-8').match(/<script[^>]*>/);
        if (scriptMatch) {
            // Don't add @ts-nocheck — it's too aggressive and hides real issues
        }
    }

    if (fixes > 0) {
        const newContent = fileLines.join('\n');
        if (newContent !== originalLines.join('\n')) {
            fs.writeFileSync(filePath, newContent, 'utf-8');
            filesFixed++;
            totalFixed += fixes;
        }
    }
}

console.log(`Fixed ${totalFixed} errors in ${filesFixed} files.`);

// === Phase 2: Fix remaining patterns across all files ===
console.log('\nPhase 2: Fixing remaining patterns...');

import { glob } from 'glob';

const allFiles = [
    ...await glob('resources/js/**/*.vue'),
    ...await glob('resources/js/**/*.ts', { ignore: ['**/__tests__/**', '**/node_modules/**'] }),
];

let phase2Fixes = 0;

for (const filePath of allFiles) {
    let content = fs.readFileSync(filePath, 'utf-8');
    const original = content;

    if (filePath.endsWith('.vue') && !content.includes('lang="ts"') && !content.includes("lang='ts'")) continue;

    // Fix: window.ymaps → (window as any).ymaps (already handled by global.d.ts)

    // Fix: $event.target.value in script → ($event.target as HTMLInputElement).value
    content = content.replace(/\$event\.target\.value/g, '($event.target as HTMLInputElement).value');
    content = content.replace(/event\.target\.value(?!\s*\))/g, '(event.target as HTMLInputElement).value');

    // Fix: e.target.value → (e.target as HTMLInputElement).value
    content = content.replace(/(?<!\()e\.target\.value/g, '(e.target as HTMLInputElement).value');

    // Fix: (X as any).Y patterns already correct

    // Fix: Number(x) for arithmetic — skip, too risky

    // Fix: catch blocks that still have untyped error
    // Already handled in previous pass

    if (content !== original) {
        fs.writeFileSync(filePath, content, 'utf-8');
        const diff = content.length - original.length;
        if (Math.abs(diff) > 10) phase2Fixes++;
    }
}

console.log(`Phase 2: Fixed patterns in ${phase2Fixes} files.`);
console.log(`\nTotal: ${totalFixed + phase2Fixes} fixes applied.`);
