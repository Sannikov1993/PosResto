/**
 * Third pass: Fix Options API TypeScript errors and remaining patterns.
 *
 * 1. data() return { prop: [] } → { prop: [] as any[] }
 * 2. data() return { prop: {} } → { prop: {} as Record<string, any> }
 * 3. data() return { prop: null } → { prop: null as any }
 * 4. props type: Array → type: Array as PropType<any[]>
 * 5. props type: Object → type: Object as PropType<Record<string, any>>
 * 6. computed return [] → return [] as any[]
 * 7. Template v-for with (item) of/in pattern - add type assertion in script
 * 8. Add PropType import if needed
 */

import fs from 'fs';
import { glob } from 'glob';

const vueFiles = await glob('resources/js/**/*.vue');
const tsFiles = await glob('resources/js/**/*.ts', { ignore: ['**/__tests__/**', '**/node_modules/**'] });

let totalFixed = 0;
let filesModified = 0;

function fixFile(filePath) {
    let content = fs.readFileSync(filePath, 'utf-8');
    const original = content;
    let fixes = 0;

    const isVue = filePath.endsWith('.vue');
    const isTs = filePath.endsWith('.ts');

    // Only process TS-enabled files
    if (isVue && !content.includes('lang="ts"') && !content.includes("lang='ts'")) return;

    // === Fix 1-3: data() return values ===
    // Pattern: inside data() { return { ... } }, fix array/object/null literals
    // Regex-based approach: find lines that end with `: [],` or `: {},` or `: null,`

    // Fix `: [],` or `: []` (end of object literal)
    content = content.replace(/^(\s+\w+:\s*)\[\](,?)$/gm, (match, prefix, comma) => {
        if (match.includes('as ')) return match; // already has assertion
        fixes++;
        return `${prefix}[] as any[]${comma}`;
    });

    // Fix `: {},` or `: {}`
    content = content.replace(/^(\s+\w+:\s*)\{\}(,?)$/gm, (match, prefix, comma) => {
        if (match.includes('as ')) return match;
        fixes++;
        return `${prefix}{} as Record<string, any>${comma}`;
    });

    // Fix `: null,` in data return (only in Options API context)
    // Be careful not to match ref(null) which is already handled
    content = content.replace(/^(\s+\w+:\s*)null(,?)$/gm, (match, prefix, comma) => {
        if (match.includes('as ')) return match;
        fixes++;
        return `${prefix}null as any${comma}`;
    });

    // === Fix 4-5: Props type declarations ===
    let needsPropType = false;

    // type: Array → type: Array as PropType<any[]>
    content = content.replace(/type:\s*Array(?!\s*as)/g, (match) => {
        fixes++;
        needsPropType = true;
        return 'type: Array as PropType<any[]>';
    });

    // type: Object → type: Object as PropType<Record<string, any>>
    content = content.replace(/type:\s*Object(?!\s*as)/g, (match) => {
        fixes++;
        needsPropType = true;
        return 'type: Object as PropType<Record<string, any>>';
    });

    // type: Function → type: Function as PropType<(...args: any[]) => any>
    content = content.replace(/type:\s*Function(?!\s*as)/g, (match) => {
        fixes++;
        needsPropType = true;
        return 'type: Function as PropType<(...args: any[]) => any>';
    });

    // type: [Array, Object] → type: [Array, Object] as PropType<any>
    content = content.replace(/type:\s*\[(Array|Object|Function)(?:,\s*(?:Array|Object|Function|String|Number|Boolean))*\](?!\s*as)/g, (match) => {
        fixes++;
        needsPropType = true;
        return `${match} as PropType<any>`;
    });

    // Add PropType import if needed
    if (needsPropType && !content.includes('PropType')) {
        // Options API — add import at top of script section
        content = content.replace(
            /(<script\s+lang="ts"[^>]*>)\s*\n/,
            `$1\nimport type { PropType } from 'vue';\n`
        );
        // For setup scripts
        content = content.replace(
            /(<script\s+setup\s+lang="ts"[^>]*>)\s*\n/,
            `$1\nimport type { PropType } from 'vue';\n`
        );
    }

    // === Fix 6: computed/methods that return [] ===
    // return []; → return [] as any[];
    // return {}; → return {} as Record<string, any>;
    content = content.replace(/^(\s+return\s+)\[\];$/gm, (match, prefix) => {
        if (match.includes('as ')) return match;
        fixes++;
        return `${prefix}[] as any[];`;
    });

    content = content.replace(/^(\s+return\s+)\{\};$/gm, (match, prefix) => {
        if (match.includes('as ')) return match;
        fixes++;
        return `${prefix}{} as Record<string, any>;`;
    });

    // === Fix 7: as unknown → as any for common casts ===
    // $event.target.value → ($event.target as any).value
    // This pattern is from template v-on handlers, hard to fix with regex

    // === Fix 8: Remaining patterns ===

    // Fix: `(e: any)` in event handlers but e.target.value access
    // These are EventTarget issues — skip for now

    // Fix: `Object.entries(x)` producing [string, unknown][]
    // → add `as [string, any][]` or use different pattern

    // Fix: response.data access on AxiosResponse — need to type API calls
    // Skip — requires understanding of each API

    if (fixes > 0 && content !== original) {
        fs.writeFileSync(filePath, content, 'utf-8');
        filesModified++;
        totalFixed += fixes;
        if (fixes >= 5) {
            console.log(`  Fixed ${fixes} in ${filePath}`);
        }
    }

    return fixes;
}

console.log('Pass 3: Fixing Options API + remaining patterns...\n');

for (const f of [...vueFiles, ...tsFiles]) {
    fixFile(f);
}

console.log(`\nDone! Modified ${filesModified} files with ${totalFixed} fixes.`);
