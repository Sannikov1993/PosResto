/**
 * Script to fix common TypeScript errors in Vue components after migration.
 *
 * Fixes:
 * 1. ref([]) → ref<any[]>([])
 * 2. ref({}) → ref<Record<string, any>>({})
 * 3. ref(null) → ref<any>(null)  (only where accessed as object/array later)
 * 4. reactive({...}) → reactive<Record<string, any>>({...})
 * 5. reactive([]) → reactive<any[]>([])
 * 6. Untyped callback params in .map/.filter/.forEach/.find/.some/.every/.sort/.reduce
 * 7. Untyped catch (e) → catch (e: any)
 * 8. Untyped function params where TS7006 occurs
 */

import fs from 'fs';
import path from 'path';
import { glob } from 'glob';

const ROOT = 'resources/js';

// Find all Vue files with <script setup lang="ts">
const vueFiles = await glob(`${ROOT}/**/*.vue`);
// Also find .ts files (non-test, non-types)
const tsFiles = await glob(`${ROOT}/**/*.ts`, { ignore: ['**/__tests__/**', '**/types/**', '**/node_modules/**'] });

let totalFixed = 0;
let filesModified = 0;

function fixFile(filePath) {
    let content = fs.readFileSync(filePath, 'utf-8');
    const original = content;

    // Only process Vue files that have <script setup lang="ts"> or .ts files
    const isVue = filePath.endsWith('.vue');
    const isTs = filePath.endsWith('.ts');

    if (isVue && !content.includes('lang="ts"') && !content.includes("lang='ts'")) {
        return 0;
    }

    let fixes = 0;

    // === Fix 1: ref([]) → ref<any[]>([]) ===
    // Match: ref([]) but not ref<something>([])
    content = content.replace(/\bref\(\[\]\)/g, (match, offset) => {
        // Check if already typed (preceding <...>)
        const before = content.substring(Math.max(0, offset - 20), offset);
        if (before.match(/<[^>]+>\s*$/)) return match;
        fixes++;
        return 'ref<any[]>([])';
    });

    // === Fix 2: ref({}) → ref<Record<string, any>>({}) ===
    content = content.replace(/\bref\(\{\}\)/g, (match, offset) => {
        const before = content.substring(Math.max(0, offset - 30), offset);
        if (before.match(/<[^>]+>\s*$/)) return match;
        fixes++;
        return 'ref<Record<string, any>>({})';
    });

    // === Fix 3: ref(null) → ref<any>(null) ===
    // Only when not already typed
    content = content.replace(/\bref\(null\)/g, (match, offset) => {
        const before = content.substring(Math.max(0, offset - 30), offset);
        if (before.match(/<[^>]+>\s*$/)) return match;
        fixes++;
        return 'ref<any>(null)';
    });

    // === Fix 4: reactive({...multiline...}) without type → add Record<string, any> ===
    // Match reactive({ on same line where it's a complex object (has properties)
    // Only if not already typed
    content = content.replace(/\breactive\(\{/g, (match, offset) => {
        const before = content.substring(Math.max(0, offset - 30), offset);
        if (before.match(/<[^>]+>\s*$/)) return match;
        fixes++;
        return 'reactive<Record<string, any>>({';
    });

    // === Fix 5: reactive([]) → reactive<any[]>([]) ===
    content = content.replace(/\breactive\(\[\]\)/g, (match, offset) => {
        const before = content.substring(Math.max(0, offset - 30), offset);
        if (before.match(/<[^>]+>\s*$/)) return match;
        fixes++;
        return 'reactive<any[]>([])';
    });

    // === Fix 6: Untyped array method callbacks ===
    // .map(item => ...) → .map((item: any) => ...)
    // .filter(item => ...) → .filter((item: any) => ...)
    // etc.
    const arrayMethods = ['map', 'filter', 'forEach', 'find', 'findIndex', 'some', 'every', 'sort', 'flatMap'];
    for (const method of arrayMethods) {
        // Single param without parens: .method(x =>
        const re1 = new RegExp(`\\.${method}\\((\\w+)\\s*=>`, 'g');
        content = content.replace(re1, (match, param) => {
            // Skip if already typed
            if (param === 'any' || param === 'unknown') return match;
            fixes++;
            return `.${method}((${param}: any) =>`;
        });

        // Single param with parens but no type: .method((x) =>
        const re2 = new RegExp(`\\.${method}\\(\\((\\w+)\\)\\s*=>`, 'g');
        content = content.replace(re2, (match, param) => {
            fixes++;
            return `.${method}((${param}: any) =>`;
        });

        // Two params: .method((a, b) =>  — for sort, reduce
        const re3 = new RegExp(`\\.${method}\\(\\((\\w+),\\s*(\\w+)\\)\\s*=>`, 'g');
        content = content.replace(re3, (match, p1, p2) => {
            fixes++;
            return `.${method}((${p1}: any, ${p2}: any) =>`;
        });
    }

    // .reduce((acc, item) => and .reduce((acc, item, index) =>
    content = content.replace(/\.reduce\((\(\w+,\s*\w+(?:,\s*\w+)?\))\s*=>/g, (match, params) => {
        // Add :any to each param if not already typed
        if (params.includes(':')) return match;
        const typed = params.replace(/(\w+)/g, '$1: any');
        fixes++;
        return `.reduce(${typed} =>`;
    });

    // === Fix 7: catch (e) → catch (e: any) ===
    content = content.replace(/\bcatch\s*\((\w+)\)/g, (match, param) => {
        if (param.includes(':')) return match;
        fixes++;
        return `catch (${param}: any)`;
    });

    // === Fix 8: Function params - common event handlers ===
    // @ts-ignore style fixes for common patterns
    // (event) in standalone functions that lack typing
    // This is harder to do safely with regex, skip for now

    if (fixes > 0 && content !== original) {
        fs.writeFileSync(filePath, content, 'utf-8');
        filesModified++;
        totalFixed += fixes;
        console.log(`  Fixed ${fixes} patterns in ${filePath}`);
    }

    return fixes;
}

console.log('Starting TypeScript error fixes...\n');

// Process Vue files
for (const file of vueFiles) {
    fixFile(file);
}

// Process TS files
for (const file of tsFiles) {
    fixFile(file);
}

console.log(`\nDone! Modified ${filesModified} files with ${totalFixed} fixes.`);
