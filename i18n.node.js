const fs = require('fs');
const path = require('path');

const targetDir = 'resources'; // Define the starting directory


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
    // Updated regex to capture strings with mixed quotes
    const regex = /\$t\(['"]([^'"]*?(?:\\['"][^'"]*?)*?)['"]\)/g;

    files.forEach(file => {
        const content = fs.readFileSync(file, 'utf8');
        let match;

        while ((match = regex.exec(content)) !== null) {
            results[match[1]] = true; // Use the match as a key to avoid duplicates
        }
    });

    return Object.keys(results); // Return unique strings only
}

// Write results to a text file in PHP array format
function writeResults(strings) {
    const sortedStrings = strings.sort(); // Sort strings in ascending order
    const formattedStrings = sortedStrings.map((str) => {
        return `'${str}' => __('${str}', 'fluent-smtp')`;
    }).join(",\n");
    const finalData = "<?php [\n" + formattedStrings + "\n];";

    fs.writeFile('translationStrings.php', finalData, err => {
        if (err) {
            console.error('Error writing to file:', err);
        } else {
            console.log('Saved translation strings to translationStrings.php');
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
