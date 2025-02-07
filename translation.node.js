const fs = require('fs');
const path = require('path');

let reservedStrings = require('./resources/reservedStrings.json'); // Load reserved words from JSON file
let reservedPhpStrings = require('./resources/reservedPhpStrings.json'); // Load reserved words from JSON file

const targetDir = 'resources'; // Define the starting directory
// const targetDir = 'research'; // Define the starting directory
const namespace = 'fluent-smtp'; // Define the namespace for the translation strings

const finalFile = 'app/Services/TransStrings.php'; // Define the file to replace the translation strings

function modifyAndReconstructSprintf(s) {
    // Extract the entire sprintf call excluding 'sprintf(' and the closing ')'
    const fullPattern = /sprintf\((.*)\)$/;
    const fullMatch = s.match(fullPattern);

    if (!fullMatch) {
        return null; // Return null if the format doesn't match expected
    }

    // Split the entire content by ', ' but only outside of quotes to avoid breaking URLs and functions
    const args = [];
    let depth = 0;
    let lastIndex = 0;

    for (let i = 0; i < fullMatch[1].length; i++) {
        const char = fullMatch[1][i];
        if (char === '(') depth++;
        if (char === ')') depth--;
        if (char === '\'' && fullMatch[1][i - 1] !== '\\') {
            // Toggle in or out of a single quote
            depth = depth === 0 ? 1000 : 0;
        }
        if (char === ',' && depth === 0) {
            args.push(fullMatch[1].substring(lastIndex, i).trim());
            lastIndex = i + 1;
        }
    }
    // Add the last argument
    args.push(fullMatch[1].substring(lastIndex).trim());

    if (args.length === 0) {
        return null; // No arguments found, unlikely scenario
    }

    // Replace escaped single quotes in the first argument
    args[0] = args[0].replace(/\\'/g, "'");

    // Reconstruct the full sprintf string using modified arguments
    return `sprintf(${args.map(arg => arg).join(', ')})`;
}


// Function to read directory contents recursively
function readDirRecursively(dir, allFiles = []) {
    const files = fs.readdirSync(dir);

    files.forEach(file => {
        const filepath = path.join(dir, file);
        if (fs.statSync(filepath).isDirectory()) {
            readDirRecursively(filepath, allFiles);
        } else if (path.extname(file) === '.vue' || path.extname(file) === '.js') { // Check for .vue and .js files
            allFiles.push(filepath);
        }
    });

    return allFiles;
}

// Function to extract strings from $t() in file content
function extractStrings(files) {
    const results = {};
    // Updated regex to capture the first argument of $t() with multiple arguments
    const regex = /\$t\(\s*['"]([^'"]*?(?:\\['"][^'"]*?)*?)['"]\s*(?:,\s*[^)]*)?\)/g;

    files.forEach(file => {
        const content = fs.readFileSync(file, 'utf8');
        let match;

        while ((match = regex.exec(content)) !== null) {
            results[match[1]] = true; // Use the match as a key to avoid duplicates
        }
    });

    // Extract the strings from $_n('string 1', 'string 2', var) calls
    const nRegex = /\$_n\(['"]([^'"]*?(?:\\['"][^'"]*?)*?)['"],\s*['"]([^'"]*?(?:\\['"][^'"]*?)*?)['"]/g;
    files.forEach(file => {
        const content = fs.readFileSync(file, 'utf8');
        let match;

        while ((match = nRegex.exec(content)) !== null) {
            results[match[1]] = true; // Use the match as a key to avoid duplicates
            results[match[2]] = true; // Use the match as a key to avoid duplicates
        }
    });

    return Object.keys(results); // Return unique strings only
}

// Write results to a text file in PHP array format
function writeResults(strings) {
    // add reservedWords if not exist
    for (const key in reservedStrings) {
        if (!strings.includes(key)) {
            strings.push(key);
        }
    }

    const sortedStrings = strings.sort(); // Sort strings in ascending order
    const formattedStrings = sortedStrings.map((str) => {
        if(reservedPhpStrings[str]) {
            return `            '${str}' => ${reservedPhpStrings[str]}`;
        }

        if(reservedStrings[str]) {
            return `            '${str}' => __('${reservedStrings[str]}', '${namespace}')`;
        }

        return `            '${str}' => __('${str}', '${namespace}')`;
    }).join(",\n");

    const finalData = "<?php \n\nnamespace FluentMail\\App\\Services;\n\n//This is a auto-generated file. Please do not modify\nclass TransStrings\n{\n    public static function getStrings()\n    {\n        return [\n" + formattedStrings + "\n];\n    }\n}";

    fs.writeFile(finalFile, finalData, err => {
        if (err) {
            console.error('Error writing to file:', err);
        } else {
            console.log('Saved translation strings to ' + finalFile);
        }
    });
}

// Main process function
function processVueFiles() {
    const vueFiles = readDirRecursively(targetDir);
    const uniqueStrings = extractStrings(vueFiles);

    writeResults(uniqueStrings);
}

processVueFiles();
