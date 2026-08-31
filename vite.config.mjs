import { defineConfig } from 'vite';
import vue from '@vitejs/plugin-vue';
import AutoImport from 'unplugin-auto-import/vite';
import Components from 'unplugin-vue-components/vite';
import { ElementPlusResolver } from 'unplugin-vue-components/resolvers';
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
            vue(),
            /*
             * Element Plus components are pulled in on demand, along with their
             * stylesheets, by the resolver. That is what let resources/scss/vendor.scss
             * - 182 lines of hand-maintained CSS imports that had to be edited every
             * time a component was added - be deleted outright.
             *
             * Components used imperatively rather than as tags (ElNotification,
             * ElLoading, ElMessageBox) are invisible to the resolver, so their CSS is
             * imported by hand at the top of fluent-mail-admin.scss.
             */
            AutoImport({ resolvers: [ElementPlusResolver()] }),
            Components({ resolvers: [ElementPlusResolver()], directives: false }),
            ...(build.copy ? [viteStaticCopy({ targets: build.copy })] : []),
            ...(build.cssFileName ? [renameExtractedCss(build.cssFileName)] : [])
        ],
        css: {
            preprocessorOptions: {
                scss: {
                    api: 'modern-compiler',
                    // Element Plus's own stylesheets still use @import.
                    silenceDeprecations: ['import', 'legacy-js-api', 'global-builtin']
                }
            }
        },
        resolve: {
            alias: {
                '@': path.resolve(root, 'resources/admin'),
                /*
                 * The app is split across two bundles that must share one Vue.
                 *
                 * Under Vue 2 this was a hard bug: Element UI's CommonJS
                 * `require('vue')` resolved to the package's `main` while our
                 * `import` resolved to its `module`, giving two reactivity
                 * systems and an el-table whose every row rendered with no cells
                 * in it, silently. Vue 3 and Element Plus are both ESM-first so
                 * the same trap is unlikely, but a second copy of Vue would fail
                 * just as quietly - boot.js and the app bundle would each have
                 * their own - so it stays pinned.
                 */
                vue: path.resolve(root, 'node_modules/vue/dist/vue.runtime.esm-bundler.js')
            },
            dedupe: ['vue', 'vue-router', 'element-plus'],
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
