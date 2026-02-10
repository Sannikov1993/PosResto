/**
 * Fix lines where the variable name was lost by the regex replacement.
 * Pattern: `( as any)?.prop` should be `(varName as any)?.prop`
 * Uses git to find original variable names.
 */
import { execSync } from 'child_process';
import fs from 'fs';

// Get all lines with broken pattern
const brokenOutput = execSync('grep -rn "( as any)?\\\\." resources/js/ --include="*.vue" --include="*.ts"', { encoding: 'utf-8' }).trim();
const brokenLines = brokenOutput.split('\n');

let fixed = 0;

for (const bl of brokenLines) {
    const match = bl.match(/^([^:]+):(\d+):/);
    if (!match) continue;
    const [, file, lineNumStr] = match;
    const lineNum = parseInt(lineNumStr);

    try {
        const gitPath = file.replace(/\\/g, '/');
        const origContent = execSync(`git show HEAD:${gitPath}`, { encoding: 'utf-8' });
        const origLines = origContent.split('\n');

        // Line numbers may have shifted, search nearby
        let origLine = origLines[lineNum - 1] || '';

        // Read current file
        const lines = fs.readFileSync(file, 'utf-8').split('\n');
        const currentLine = lines[lineNum - 1];
        if (!currentLine) continue;

        // Find '( as any)?.' in current
        const brokenIdx = currentLine.indexOf('( as any)?.');
        if (brokenIdx === -1) continue;

        // Get property after '( as any)?.'
        const afterStr = currentLine.substring(brokenIdx + '( as any)?.'.length);
        const afterMatch = afterStr.match(/^(\w+)/);
        if (!afterMatch) continue;
        const prop = afterMatch[1];

        // Also look at what's before the broken pattern to narrow search
        const beforeBroken = currentLine.substring(0, brokenIdx).trimEnd();

        // In original line, find: someExpression?.prop
        // Search in nearby lines in case line numbers shifted
        let varName = null;
        for (let offset = -2; offset <= 2; offset++) {
            const checkLine = origLines[lineNum - 1 + offset] || '';
            // Look for ?.prop pattern
            const regex = new RegExp('([\\w.$]+(?:\\?\\.\\w+)*)\\?\\.' + prop.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'));
            const m = checkLine.match(regex);
            if (m) {
                varName = m[1];
                break;
            }
        }

        if (varName) {
            const fixedLine = currentLine.replace('( as any)?.' + prop, `(${varName} as any)?.${prop}`);
            if (fixedLine !== currentLine) {
                lines[lineNum - 1] = fixedLine;
                fs.writeFileSync(file, lines.join('\n'), 'utf-8');
                console.log(`Fixed ${file}:${lineNum} → (${varName} as any)?.${prop}`);
                fixed++;
            }
        } else {
            console.log(`WARN: Could not find original var for ${file}:${lineNum} prop=${prop}`);
        }
    } catch (e) {
        console.log(`SKIP: ${file}:${lineNum} - ${e.message?.substring(0, 50)}`);
    }
}

console.log(`\nFixed ${fixed} broken lines.`);
