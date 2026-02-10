/**
 * Add missing PropType imports to Vue files that use PropType but don't import it.
 */
import fs from 'fs';
import { glob } from 'glob';

const vueFiles = await glob('resources/js/**/*.vue');
let fixed = 0;

for (const filePath of vueFiles) {
    let content = fs.readFileSync(filePath, 'utf-8');

    // Skip if doesn't use PropType or already imports it
    if (!content.includes('PropType')) continue;
    if (content.includes("import type { PropType }") || content.includes("import { PropType }")) continue;

    // Check if PropType is imported as part of another import from 'vue'
    if (content.match(/import\s+(?:type\s+)?{[^}]*PropType[^}]*}\s+from\s+['"]vue['"]/)) continue;

    // Need to add the import. Find the <script> tag with lang="ts"
    const scriptMatch = content.match(/<script\b[^>]*lang=["']ts["'][^>]*>/);
    if (!scriptMatch) continue;

    const scriptTag = scriptMatch[0];
    const scriptIdx = content.indexOf(scriptTag);
    const afterScript = scriptIdx + scriptTag.length;

    // Check if there's already a vue import we can add PropType to
    const vueImportMatch = content.match(/import\s+(?:type\s+)?{([^}]*)}\s+from\s+['"]vue['"]/);
    if (vueImportMatch) {
        const existingImports = vueImportMatch[1];
        const newImports = existingImports.trim() + ', PropType';
        content = content.replace(vueImportMatch[0], vueImportMatch[0].replace(existingImports, ` ${newImports} `));
    } else {
        // Add new import line after script tag
        const insertPos = content.indexOf('\n', afterScript);
        if (insertPos !== -1) {
            content = content.substring(0, insertPos + 1) +
                      "import type { PropType } from 'vue';\n" +
                      content.substring(insertPos + 1);
        }
    }

    fs.writeFileSync(filePath, content, 'utf-8');
    fixed++;
}

console.log(`Added PropType import to ${fixed} files.`);
