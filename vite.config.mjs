import { defineConfig } from 'vite';
import vue2 from '@vitejs/plugin-vue2';
import { viteStaticCopy } from 'vite-plugin-static-copy';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const root = path.dirname(fileURLToPath(import.meta.url));

/*
 * One Vite build per enqueued script, selected with --mode.
 *
 * Rollup refuses `iife` for any build with more than one input, and `es` is not
 * an alternative here: AdminMenuHandler enqueues these through wp_enqueue_script,
 * which emits a plain <script src>, and an `import` statement in a classic script
 * is a syntax error. Splitting into two single-input builds is what buys us the
 * iife format, and with it the guarantee that the enqueue keeps working untouched.
 *
 * The output paths below are the paths that ship today. fluentMailMix() in
 * app/Functions/helpers.php just prefixes the assets URL, so as long as these
 * names hold, no PHP has to know the build tool changed.
 */
const builds = {
    // window.FluentMail and everything on it. Enqueued first, in the header,
    // because wp_localize_script hangs FluentMailAdmin off this handle.
    boot: {
        input: path.resolve(root, 'resources/admin/boot.js'),
        entryFileNames: 'admin/js/boot.js',
        // The image and library trees are plain copies, not build inputs. They
        // ride along with the first build so a full run touches them once.
        copy: [
            { src: 'resources/images/*', dest: 'images' },
            { src: 'resources/libs/*', dest: 'libs' }
        ]
    },

    /*
     * The Vue app. Reads window.FluentMail, so it must load second.
     *
     * start.js imports the stylesheet, which makes this build the producer of
     * assets/admin/css/fluent-mail-admin.css as well. That is deliberate rather
     * than incidental: Laravel Mix injected each SFC's <style> block at runtime
     * through vue-style-loader, so component styles were never in a file at all.
     * Vite extracts them, and they have to be extracted into the one stylesheet
     * wp_enqueue_style already asks for, or they are silently dropped.
     */
    app: {
        input: path.resolve(root, 'resources/admin/start.js'),
        entryFileNames: 'admin/js/fluent-mail-admin-app.js',
        // Rollup has no concept of a stylesheet entry point, so Vite names the
        // extracted file `style.css` regardless. Put it where PHP looks for it.
        cssFileName: 'admin/css/fluent-mail-admin.css',
        // Element UI's theme-chalk CSS points at its icon font with a relative
        // url(). Emitting those next to the stylesheet keeps that reference a
        // sibling lookup, which is what it already is today.
        assetFileNames: 'admin/css/[name][extname]'
    }
};

export default defineConfig(({ mode }) => {
    const build = builds[mode];
    if (!build) {
        throw new Error(
            `vite.config.mjs: unknown mode "${mode}". ` +
            `Run one of: ${Object.keys(builds).map((m) => `vite build --mode ${m}`).join(', ')}`
        );
    }

    return {
        // Relative asset URLs. The plugin directory is not at a fixed web path.
        base: '',
        mode: 'production',
        plugins: [
            vue2(),
            ...(build.copy ? [viteStaticCopy({ targets: build.copy })] : []),
            ...(build.cssFileName ? [renameExtractedCss(build.cssFileName)] : [])
        ],
        css: {
            preprocessorOptions: {
                scss: {
                    api: 'modern-compiler',
                    // resources/scss/vendor.scss is a wall of @import statements
                    // for Element UI's stylesheets. Silencing the deprecation
                    // keeps the build output readable; Phase 3 deletes the file.
                    silenceDeprecations: ['import', 'legacy-js-api']
                }
            }
        },
        resolve: {
            alias: {
                '@': path.resolve(root, 'resources/admin'),
                /*
                 * Pin Vue to one file, and specifically to the ESM build.
                 *
                 * Without this the bundle ends up with two Vues: our own
                 * `import Vue from 'vue'` takes the package's `module` entry
                 * (dist/vue.runtime.esm.js), while Element UI's CommonJS
                 * `require('vue')` takes `main` (dist/vue.runtime.common.js).
                 * Two Vues means two independent reactivity systems, and the
                 * failure is quiet: el-table's column store is a Vue instance
                 * built from Element's copy, so the app's copy never collects a
                 * dependency on it. The table renders its rows and every <td>
                 * is missing, with no console error to say why.
                 *
                 * `dedupe` is not enough here - both specifiers already resolve
                 * inside the same installed package, just to different files.
                 */
                vue: path.resolve(root, 'node_modules/vue/dist/vue.runtime.esm.js')
            },
            dedupe: ['vue', 'vue-router'],
            extensions: ['.js', '.mjs', '.vue', '.json']
        },
        build: {
            outDir: 'assets',
            // Each mode writes different files into the same directory, so no
            // build may clear it - the second would delete the first's output.
            emptyOutDir: false,
            // One stylesheet, matching the one wp_enqueue_style call.
            cssCodeSplit: false,
            manifest: false,
            sourcemap: false,
            target: 'es2018',
            // Both bundles are large and neither is split. The warning is noise.
            chunkSizeWarningLimit: 1200,
            rollupOptions: {
                input: build.input,
                output: {
                    format: 'iife',
                    entryFileNames: build.entryFileNames,
                    assetFileNames: build.assetFileNames || 'admin/[ext]/[name][extname]',
                    // Every build here has a single input, so there are no shared
                    // chunks to name. Kept explicit so a stray dynamic import fails
                    // loudly rather than landing somewhere nothing enqueues.
                    inlineDynamicImports: true
                }
            }
        }
    };
});

/*
 * Rename Vite's extracted `style.css` to the name wp_enqueue_style() uses.
 *
 * `order: 'post'` is load-bearing: Vite's own css plugin emits the extracted
 * stylesheet from its generateBundle hook, so a plugin that runs first sees a
 * bundle with no CSS in it at all and silently does nothing.
 */
function renameExtractedCss(cssFileName) {
    return {
        name: 'fluent-smtp:rename-extracted-css',
        generateBundle: {
            order: 'post',
            handler(_options, bundle) {
                for (const [fileName, output] of Object.entries(bundle)) {
                    if (output.type !== 'asset' || !fileName.endsWith('.css')) {
                        continue;
                    }
                    delete bundle[fileName];
                    output.fileName = cssFileName;
                    output.names = [cssFileName];
                    bundle[cssFileName] = output;
                }
            }
        }
    };
}
