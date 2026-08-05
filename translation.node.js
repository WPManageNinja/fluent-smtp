const fs = require('fs');
const path = require('path');

let reservedStrings = require('./resources/reservedStrings.json'); // Load reserved words from JSON file
let reservedPhpStrings = require('./resources/reservedPhpStrings.json'); // Load reserved words from JSON file

const targetDir = 'resources'; // Define the starting directory
// const targetDir = 'research'; // Define the starting directory
const namespace = 'fluent-smtp'; // Define the namespace for the translation strings

const finalFile = 'app/Services/TransStrings.php'; // Define the file to replace the translation strings

/**
 * Turn the raw source text between two quotes into the string the browser
 * actually sees at runtime, which is the key $t() looks up.
 *
 * `$t('It\'s here')` carries a backslash in the source that is not part of the
 * string, so it has to come out before the key is written.
 */
function decodeJsLiteral(raw) {
    return raw.replace(/\\(['"\\])/g, '$1');
}

/**
 * Quote a value for a single-quoted PHP string.
 *
 * Only a backslash and a single quote mean anything inside '...', so those are
 * the only two that need escaping. Every string is normalized through
 * decodeJsLiteral() first, so a value that already carries PHP-ready escaping
 * cannot end up double-escaped here.
 */
function phpQuote(value) {
    return decodeJsLiteral(String(value))
        .replace(/\\/g, '\\\\')
        .replace(/'/g, "\\'");
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

/*
 * Matches one quoted argument, honouring whichever quote style opened it.
 *
 * The previous pattern excluded both quote characters from the body, so
 * `$t("Don't have an account yet?")` matched nothing and the string was
 * dropped without a word — it simply never became translatable. Backreferencing
 * the opening quote means an apostrophe inside a double-quoted string, or a
 * double quote inside a single-quoted one, is ordinary content.
 */
const QUOTED_ARG = `(['"])((?:\\\\.|(?!\\1)[^\\\\])*)\\1`;

// Function to extract strings from $t() in file content
function extractStrings(files) {
    const results = {};
    const unparsed = [];

    const regex = new RegExp(`\\$t\\(\\s*${QUOTED_ARG}`, 'g');
    // Any $t( whose first argument is not a plain quoted literal - a template
    // literal or a variable - which this script cannot resolve statically.
    const dynamicCall = /\$t\(\s*[^'"\s)]/g;

    files.forEach(file => {
        const content = fs.readFileSync(file, 'utf8');
        let match;

        while ((match = regex.exec(content)) !== null) {
            results[decodeJsLiteral(match[2])] = true; // keyed to de-duplicate
        }

        while ((match = dynamicCall.exec(content)) !== null) {
            // `$t(string) {` is the helper's own definition, not a call site.
            if (/^\$t\(\s*[A-Za-z_$][\w$]*\s*\)\s*\{/.test(content.slice(match.index))) {
                continue;
            }

            const line = content.slice(0, match.index).split('\n').length;
            unparsed.push(`${file}:${line}`);
        }
    });

    // Extract the strings from $_n('string 1', 'string 2', var) calls
    const nRegex = new RegExp(`\\$_n\\(\\s*${QUOTED_ARG}\\s*,\\s*${QUOTED_ARG}`, 'g');
    files.forEach(file => {
        const content = fs.readFileSync(file, 'utf8');
        let match;

        while ((match = nRegex.exec(content)) !== null) {
            results[decodeJsLiteral(match[2])] = true;
            results[decodeJsLiteral(match[4])] = true;
        }
    });

    if (unparsed.length) {
        console.warn(
            `\nWarning: ${unparsed.length} $t() call(s) do not use a literal string and were not extracted.\n` +
            `They will render untranslated. Pass a quoted string instead:\n` +
            unparsed.map(u => `    ${u}`).join('\n') + '\n'
        );
    }

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
        const key = phpQuote(str);

        if(reservedPhpStrings[str]) {
            return `            '${key}' => ${reservedPhpStrings[str]}`;
        }

        if(reservedStrings[str]) {
            return `            '${key}' => __('${phpQuote(reservedStrings[str])}', '${namespace}')`;
        }

        return `            '${key}' => __('${phpQuote(str)}', '${namespace}')`;
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
