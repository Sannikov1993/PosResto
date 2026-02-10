/**
 * Phase 2 bulk fix script for remaining vue-tsc errors.
 * Targets: TS7053, TS18046, TS18047, TS18048, TS2740, TS2362/TS2363, TS2304, TS7005, TS7034
 *
 * Strategy: Parse vue-tsc output and apply targeted fixes per error code.
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
    const originalContent = fileLines.join('\n');
    let fixes = 0;

    // Process errors from bottom to top to preserve line numbers
    const sorted = [...fileErrors].sort((a, b) => b.line - a.line || b.col - a.col);

    // Track lines we've already modified to avoid double-fixing
    const modifiedLines = new Set();

    for (const err of sorted) {
        const lineIdx = err.line - 1;
        if (lineIdx >= fileLines.length || lineIdx < 0) continue;
        if (modifiedLines.has(lineIdx)) continue;

        const line = fileLines[lineIdx];

        // === TS7053: Element implicitly has 'any' type (indexing with 'any' into typed object) ===
        // Pattern: someObj[key] where key is any and obj has specific type
        // Fix: add `as Record<string, any>` or cast the indexing expression
        if (err.code === 'TS7053') {
            // The most common pattern: a lookup object like statusMap[value]
            // We need to add `: Record<string, any>` to the object literal or `as any` to indexing
            // Best approach: find the object being indexed and add `as any` to the key
            // Or add [key: string]: ... to the object
            // Safest: wrap with (obj as any)[key]
            const col = err.col - 1;

            // Find the bracket expression at the error position
            // The error points to the start of the expression
            // Look for pattern: identifier[expr] or expression[expr]
            const before = line.substring(0, col);
            const after = line.substring(col);

            // Find the object and the bracket
            const bracketMatch = after.match(/^(\w+(?:\.\w+)*)\[/);
            if (bracketMatch) {
                const objName = bracketMatch[1];
                const bracketStart = col + objName.length;
                // Find matching ]
                let depth = 0;
                let bracketEnd = -1;
                for (let i = bracketStart; i < line.length; i++) {
                    if (line[i] === '[') depth++;
                    if (line[i] === ']') { depth--; if (depth === 0) { bracketEnd = i; break; } }
                }
                if (bracketEnd !== -1) {
                    const key = line.substring(bracketStart + 1, bracketEnd);
                    fileLines[lineIdx] = line.substring(0, col) +
                        `(${objName} as Record<string, any>)[${key}]` +
                        line.substring(bracketEnd + 1);
                    modifiedLines.add(lineIdx);
                    fixes++;
                }
            }
        }

        // === TS18046: 'X' is of type 'unknown' ===
        if (err.code === 'TS18046') {
            const varMatch = err.message.match(/'([^']+)' is of type 'unknown'/);
            if (varMatch) {
                const varName = varMatch[1];
                const col = err.col - 1;

                // Check if this is something like `response.data` - find the inject/provide or variable declaration
                // Simplest fix: add `as any` after the expression at error location
                // Find the expression boundaries
                const exprEnd = col + varName.length;

                // Check if the variable appears in a meaningful expression
                if (line.substring(col).startsWith(varName)) {
                    // Don't modify if already cast
                    if (!line.substring(exprEnd).match(/^\s*as\s/)) {
                        fileLines[lineIdx] = line.substring(0, exprEnd) + ' as any' + line.substring(exprEnd);
                        modifiedLines.add(lineIdx);
                        fixes++;
                    }
                }
            }
        }

        // === TS18047: 'X' is possibly 'null' ===
        // === TS18048: 'X' is possibly 'undefined' ===
        if (err.code === 'TS18047' || err.code === 'TS18048') {
            const varMatch = err.message.match(/'([^']+)' is possibly '(null|undefined)'/);
            if (varMatch) {
                const varName = varMatch[1];
                const col = err.col - 1;

                // Add non-null assertion `!` after the expression
                const exprEnd = col + varName.length;
                if (line.substring(col, exprEnd) === varName) {
                    // Check if already has ! or if it's a type assertion
                    const charAfter = line[exprEnd];
                    if (charAfter !== '!' && charAfter !== ')' && !line.substring(exprEnd).match(/^\s*as\s/)) {
                        fileLines[lineIdx] = line.substring(0, exprEnd) + '!' + line.substring(exprEnd);
                        modifiedLines.add(lineIdx);
                        fixes++;
                    }
                }
            }
        }

        // === TS2740: Type '{}' is missing properties from type 'any[]' ===
        // These are assignments like: someArray = {} (should be someArray = [])
        if (err.code === 'TS2740' && err.message.includes("from type 'any[]'")) {
            const col = err.col - 1;
            // Find `= {}` before the col position on this line
            const assignMatch = line.match(/=\s*\{\}\s*$/);
            if (assignMatch) {
                fileLines[lineIdx] = line.replace(/=\s*\{\}\s*$/, '= []');
                modifiedLines.add(lineIdx);
                fixes++;
            } else {
                // Check for `= {}` followed by semicolon or comma
                const replaced = line.replace(/=\s*\{\}(\s*[;,])/, '= []$1');
                if (replaced !== line) {
                    fileLines[lineIdx] = replaced;
                    modifiedLines.add(lineIdx);
                    fixes++;
                }
            }
        }

        // === TS2362/TS2363: arithmetic on non-number ===
        if (err.code === 'TS2362' || err.code === 'TS2363') {
            const col = err.col - 1;
            // Find the expression at error position, wrap with Number()
            const after = line.substring(col);

            // Common patterns: item.price * item.quantity, a - b, etc
            // Find the operand at this position
            const operandMatch = after.match(/^([a-zA-Z_$][\w$.]*(?:\?\.\w+)*)/);
            if (operandMatch) {
                const operand = operandMatch[1];
                // Don't wrap if already Number() or parseFloat()
                const before = line.substring(0, col);
                if (!before.endsWith('Number(') && !before.endsWith('parseFloat(') && !before.endsWith('parseInt(')) {
                    fileLines[lineIdx] = before + `Number(${operand})` + line.substring(col + operand.length);
                    modifiedLines.add(lineIdx);
                    fixes++;
                }
            }
        }

        // === TS7005: Variable 'X' implicitly has an 'any' type ===
        // === TS7034: Variable 'X' implicitly has type 'any' in some locations ===
        if (err.code === 'TS7005' || err.code === 'TS7034') {
            const varMatch = err.message.match(/Variable '(\w+)' implicitly/);
            if (varMatch) {
                const varName = varMatch[1];
                // Add `: any` to the variable declaration
                const declRegex = new RegExp(`(let|var|const)\\s+${varName}\\s*(?=;|=)`);
                const declMatch = line.match(declRegex);
                if (declMatch && !line.includes(`: any`) && !line.match(new RegExp(`${varName}\\s*:`))) {
                    fileLines[lineIdx] = line.replace(
                        declRegex,
                        `$1 ${varName}: any `
                    );
                    modifiedLines.add(lineIdx);
                    fixes++;
                }
            }
        }

        // === TS2304: Cannot find name 'X' ===
        if (err.code === 'TS2304') {
            // Common: `$t`, `$route`, `$router`, `$refs` in Options API
            // Also: missing imports
            // Skip most — too risky for automatic fix
        }

        // === TS2551: Did you mean? (similar property) ===
        if (err.code === 'TS2551') {
            // Skip — these need manual review
        }
    }

    if (fixes > 0) {
        const newContent = fileLines.join('\n');
        if (newContent !== originalContent) {
            fs.writeFileSync(filePath, newContent, 'utf-8');
            filesFixed++;
            totalFixed += fixes;
            if (fixes >= 3) console.log(`  Fixed ${fixes} in ${filePath}`);
        }
    }
}

console.log(`\nPhase 2 script: Fixed ${totalFixed} errors in ${filesFixed} files.`);
