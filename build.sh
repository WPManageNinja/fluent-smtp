#!/bin/bash

# Exit immediately if a command exits with a non-zero status
set -e

echo "🚀 Starting Build Process..."

# 1. Build Frontend
if [ -f "package.json" ]; then
    echo "📦 Building Frontend..."
    npx mix --production
else
    echo "⚠️ package.json not found, skipping frontend build."
fi

# 2. Optimize Backend (Remove Dev Dependencies)
echo "🧹 Optimizing Composer for Production..."
# Using --no-dev to remove phpstan etc.
# Using --classmap-authoritative for maximum performance as discussed
composer install --no-dev --optimize-autoloader --classmap-authoritative

# 3. Create Zip
ZIP_NAME="fluent-smtp.zip"
echo "🤐 Creating $ZIP_NAME..."

# Remove previous build if exists
rm -f $ZIP_NAME

# Create the zip file excluding development files and folders
# We use -r for recursive, and -x to exclude patterns
zip -r $ZIP_NAME . \
    -x "*.git*" \
    -x "node_modules/*" \
    -x "tests/*" \
    -x "svn/*" \
    -x "resources/*" \
    -x "build.sh" \
    -x "phpstan.neon" \
    -x ".editorconfig" \
    -x ".eslintrc.js" \
    -x ".babelrc" \
    -x "webpack.config.js" \
    -x "translation.node.js" \
    -x "webpack.mix.js" \
    -x "vitest.config.mjs" \
    -x "package.json" \
    -x "package-lock.json" \
    -x "pnpm-lock.yaml" \
    -x "*.DS_Store" \
    -x ".gitignore" \
    -x ".gitattributes" \
    -x "composer.lock" \
    -x "README.md" \
    -x "CLAUDE.md" \
    -x ".claude/*" \
    -x "research/*"

echo "✅ Build Created: $ZIP_NAME"

# 4. Restore Dev Dependencies
echo "🔄 Restoring Dev Dependencies..."
composer install

echo "🎉 Build Complete! You can now upload $ZIP_NAME to WordPress."
