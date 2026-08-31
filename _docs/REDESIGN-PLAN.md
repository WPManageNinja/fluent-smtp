# FluentSMTP UI/UX Redesign Plan

Bringing the FluentSMTP admin onto the shared Fluent design system — the one FluentCart
and FluentAuth (fluent-security) already render: same greys, same spacing steps, same
radii, same top bar, same dark theme, same container widths.

**Status:** plan only. Nothing in this document has been built yet.
**Branch:** `redesign` (currently identical to `master`).
**Audit date:** 2026-08-31.

---

## 1. What this is and what "done" looks like

FluentSMTP's admin is five years old. It is Vue 2 + Element UI 2 + hand-written SCSS,
and it looks nothing like the plugins that now sit beside it in the same WordPress admin.
The goal is that a person moving from FluentCart to FluentSMTP does not feel they have
changed products.

Done means all of the following are true:

1. The app runs on **Vue 3 + Element Plus + Tailwind 3**, built by **Vite**.
2. Every colour, space, radius and font size comes from the **shared token files**
   copied from FluentCart — not from approximations.
3. The shell is a **fixed top app bar** with destinations only; everything configurable
   lives behind **Settings** in one left sidebar.
4. **Light and dark** both work, and the dark toggle is the *same* toggle as
   FluentCart's — switching in one moves the other.
5. Content is capped at **1350px** (1600px on very wide screens) like FluentCart, not
   stretched edge to edge.
6. **Every existing hash route still resolves.** This is non-negotiable — see §3.4.
7. `tests/bin/run-all.sh` passes, including the browser smoke across all 8 screens.

### Non-goals

- No PHP/API redesign. The request layer stays on `admin-ajax.php` via
  `window.FluentMail.$get/$post`. Endpoint names, payloads and nonces are untouched.
- No feature additions. Same screens, same capabilities, new skin and new IA.
- No menu relocation. FluentSMTP stays at **Settings → FluentSMTP**.

---

## 2. Decisions already taken

These were settled before planning and are not open for re-litigation during execution.

| # | Decision | Consequence |
|---|---|---|
| D1 | **Build with Vite**, not Laravel Mix | `webpack.mix.js` deleted; `vite.config.mjs` added; `build.sh` reworked |
| D2 | **Stay under Settings → FluentSMTP** | URL `options-general.php?page=fluent-mail` unchanged; theme scopes to `body.settings_page_fluent-mail` |
| D3 | **Destinations in the top bar, everything configurable behind Settings** | New shell; routes preserved but regrouped |
| D4 | **Dark mode built in from day one** | Every colour is a CSS variable with two values; no literal hex in components |

A note on D1: FluentCart's Vite setup is large — a port finder, a dev/prod env switcher,
a manifest→PHP config plugin, per-chunk cache-busting, and ~90 entry points. FluentSMTP
has **two JS entries and one stylesheet**. Do not copy that machinery. §5 specifies a
deliberately small Vite config whose output filenames are fixed, so the existing PHP
enqueue keeps working almost unchanged.

---

## 3. Current state — the audit

### 3.1 Shape and size

| Thing | Value |
|---|---|
| Vue SFCs | 59 files, 6,914 lines |
| Admin JS (excl. vendored libs) | 476 lines |
| SCSS | 886 lines across 5 files |
| Vue routes | 8 |
| Element UI components registered | 38 |
| Distinct `<el-*>` tags actually used | 28 |
| Entry points | `boot.js`, `start.js`, `fluent-mail-admin.scss` |

### 3.2 Stack today

- **Vue 2.6** — the constructor is published as `window.FluentMail.Vue`
- **Element UI 2.15** — CSS imported piecemeal in `resources/scss/vendor.scss` (182 lines)
- **vue-router 3**, hash history
- **Chart.js 2.7.1 + vue-chartjs 2** — *vendored* at `resources/libs/chartjs/` and loaded
  as globals (`window.VueChartJs`), despite `package.json` listing chart.js ^3.4.1 and
  vue-chartjs ^3.5.1 as dependencies. The declared deps are not what ships.
- **Laravel Mix 6 / webpack 5**
- Requests go through **jQuery `$.get`/`$.post` to `admin-ajax.php`**

### 3.3 Migration hotspots — measured, not estimated

Counts from `resources/admin` on 2026-08-31. These are the concrete work items for §6.

| Pattern | Count | Why it breaks |
|---|---|---|
| `<el-col>` | 110 | Replaced by Tailwind grid in the redesign |
| `<el-row>` | 54 | Same |
| `$notify` | 55 | Element Plus `ElNotification` is imported, not on the prototype |
| `el-icon-*` classes | 47 | Element Plus dropped the icon font entirely — SVG components from `@element-plus/icons-vue` |
| `size="mini"` | 30 | Element Plus removed `mini`; only `small` / `default` / `large` |
| `slot="…"` | 29 | Old slot syntax → `#name` |
| `v-html` | 26 | Still valid, but each one needs a sanitisation check |
| `slot-scope` | 11 | → `#default="scope"` |
| `type="text"` on `el-button` | 9 | Element Plus uses `link` |
| `<el-dialog>` | 6 | `:visible.sync` → `v-model` |
| `.sync` modifier | 4 | → `v-model:prop` |
| `$confirm` | 4 | `ElMessageBox.confirm` is imported |
| `:visible` | 3 | See dialogs |
| `picker-options` | 2 | Element Plus splits into `:shortcuts` / `:disabled-date` |
| `value-format="yyyy-MM-dd"` | 2 | Element Plus uses day.js tokens: `YYYY-MM-DD` |
| `$bus` event bus | 1 | `new Vue()` as a bus is gone in Vue 3 — use mitt or props |

### 3.4 Hard contracts that must survive — read this before touching routes

**Hash routes appear in messages already delivered to users.** `NotificationHelper.php`
embeds deep links into the Slack, Telegram and Discord alerts it sends:

```
options-general.php?page=fluent-mail#/logs?per_page=10&page=1&status=failed&search=…
```

Those messages are sitting in people's Slack history right now. The same is true of
`#/connections` (admin bar node, `app/Hooks/filters.php`) and `#/notification-settings`,
which is where the **Slack OAuth round trip returns to** after
`AdminMenuHandler::addFluentMailMenu()` redirects.

Full list of PHP-referenced hashes: `#/`, `#/connections`, `#/notification-settings`,
`#/logs?…`.

> **Rule: all 8 route `path` values in `resources/admin/routes.js` stay byte-identical.**
> The IA change in D3 is about *where a link to a route appears in the chrome*, not about
> renaming routes. Adding new child routes under Settings is fine; changing or removing
> an existing path is not.

`tests/lint/browser-route-coverage.php` enforces that `routes.js` and
`tests/browser/admin-screen-smoke.mjs` stay in sync, and will fail the build if a path
is added or removed on one side only.

### 3.5 Other contracts

- **`.fluent-mail-app`** — the browser smoke (`admin-screen-smoke.mjs`) locates the app
  by this class and asserts exactly one visible match. Keep it on the shell root.
- **`#fluent_mail_app`** — the mount node from `app/views/admin/menu.php`. See §4.3;
  this becomes load-bearing for Tailwind scoping.
- **JS extension API** — `registerTopMenu()`, and the filters `fluent_mail_top_menus` /
  `fluent_mail_global_routes`, plus the PHP action `fluent_mail_loading_app`. Nothing in
  the WPManageNinja repo set consumes these (checked across all 27 sibling plugins;
  the other plugins each carry their own `FluentFramework.js` copy of the pattern), but
  they are a public surface on a 700k-install plugin. **Preserve all three**, and keep
  `window.FluentMail.applyFilters` working. `window.FluentMail.Vue` cannot survive as a
  Vue 2 constructor — see §6.4 for the shim.
- **Screen text markers** — the smoke test asserts literal strings per screen, e.g.
  `'Sending Stats'`, `'Quick Overview'`, `'Active Email Connections'`, `'Send Test Email'`.
  If a redesign renames a heading, update `adminScreens[]` in the same commit.
- **`fluentMailMix()`** (`app/Functions/helpers.php:71`) — currently just prefixes the
  assets URL. §5 keeps it viable by fixing Vite's output filenames.

---

## 4. Target design system

### 4.1 Tokens — copy, do not approximate

Create `resources/admin/styles/tokens/` with four files copied **verbatim** from
`fluent-security/src/admin/styles/tokens/`, which in turn copied them from FluentCart:

| File | Contents |
|---|---|
| `color.js` | neutral / gray / blue / primary / success / warning / red / dark / system / report / information / feature / highlighted ramps |
| `spacing.js` | 2px steps from `0.5` (2px) to `18` (72px), plus `180` |
| `borderRadius.js` | `xs` 4px, `sm` 6px, **DEFAULT 8px**, `lg` 16px, `xl` 18px |
| `fontSize.js` | `xs` 12, `sm` 14, `base` 16, `lg` 18, `xl` 20, `2sm` 28 |

Carry the header comment across too — it is the thing that stops the palettes drifting
apart in six months:

> *The tokens are copied from FluentCart rather than approximated, so a user moving
> between the two plugins is looking at the same greys, the same spacing steps and the
> same radii. If that palette changes there, it should be copied again rather than
> drifted towards.*

### 4.2 Semantic layer — `--fsm-*`

Literal ramp values are for things that do not change between themes (a chart series, a
brand colour). **Everything the chrome paints with is a semantic variable with two
values.** FluentAuth uses the `--fls-` prefix and FluentCart uses `--fct-`; FluentSMTP
takes **`--fsm-`**.

Create `resources/admin/styles/_theme.scss` modelled on
`fluent-security/src/admin/styles/_theme.scss`. Same names, same two-value structure:

| Group | Names |
|---|---|
| Surfaces | `--fsm-body-bg`, `--fsm-surface`, `--fsm-surface-sunk`, `--fsm-surface-raised` |
| Borders | `--fsm-border`, `--fsm-border-strong` |
| Text | `--fsm-heading`, `--fsm-text`, `--fsm-text-mid`, `--fsm-text-light`, `--fsm-link` |
| Accent | `--fsm-accent`, `--fsm-accent-contrast`, `--fsm-accent-wash` |
| Shadows | `--fsm-card-line`, `--fsm-shadow-card`, `--fsm-shadow-lift`, `--fsm-shadow-pop` |
| Status | `--fsm-{danger,warning,success}-{wash,bg,line,fg}`, `--fsm-neutral-{bg,fg}` |

Light / dark values, taken from FluentCart's own stylesheet:

| Token | Light | Dark |
|---|---|---|
| `body-bg` | `#F3F5FA` | `#11171D` |
| `surface` | `#FFFFFF` | `#1C2732` |
| `surface-sunk` | `#F9FAFB` | `#151D26` |
| `surface-raised` | `#EAECF0` | `#253241` |
| `border` | `#EAECF0` | `#2C3C4E` |
| `heading` | `#11171D` | `#F5F6F7` |
| `text` | `#2F3448` | `#F5F6F7` |
| `text-mid` | `#565865` | `#C1C7D1` |
| `text-light` | `#9D9FAC` | `#8E99AA` |
| `accent` | `#253241` | `#D9DADC` *(inverts — a near-black button on a near-black card is not a button)* |
| `accent-contrast` | `#FFFFFF` | `#2F3448` |

Status tints in dark are the same hues at low alpha (`…29` / `…40` suffixes), never the
light theme's solid pastels — a pastel chip on a dark card glows and stops reading as a
quiet label.

**Scope.** FluentAuth scopes to `body.toplevel_page_fluent-auth`. FluentSMTP is a
Settings submenu, so the selector is:

```scss
body.settings_page_fluent-mail { /* light tokens */ }

.fluent_theme_dark body.settings_page_fluent-mail,
body.settings_page_fluent-mail.fluent_theme_dark { /* dark tokens */ }
```

Scoping to `<body>` rather than to the app root is deliberate: Element Plus teleports
dropdowns, dialogs and notifications to `<body>`, and they must inherit these.

### 4.3 Tailwind config

`tailwind.config.js` at the repo root, modelled on fluent-security's:

```js
important: '#fluent_mail_app',
darkMode: ['selector', '.fluent_theme_dark'],
corePlugins: { preflight: false },   // WordPress supplies base styles
content: ['./resources/admin/**/*.{vue,js}', './app/views/**/*.php'],
```

> **This is the one behavioural difference between Vue 2 and Vue 3 that works in our
> favour.** Today, `new Vue({el: '#fluent_mail_app', render})` *replaces* the mount node,
> so `#fluent_mail_app` does not exist in the DOM after boot. In Vue 3,
> `app.mount('#fluent_mail_app')` renders *inside* the node and the id survives — which
> is exactly what Tailwind's `important` selector needs. Do not change the mount markup
> in `app/views/admin/menu.php`.

Because preflight is off, `box-sizing: border-box` must be set by hand on the app root,
or every `h-*` and `min-h-*` utility inherits wp-admin's `content-box` and adds padding
on top of the height (a 36px nav link renders at 52px).

Also set `--fsm-content-width: 1040px` on `#fluent_mail_app` for the settings column.

### 4.4 Layout and measurements

Copied from FluentCart's rendered dashboard, confirmed against fluent-security:

| Element | Value |
|---|---|
| Page container | `max-width: 1350px`, centred; `1600px` above the `3xl` breakpoint |
| Page gutter | 30px (`px-7.5`) |
| First row below the bar | 28px |
| App bar height | 56px, `position: fixed`, `top: 32px` (under the WP admin bar) |
| Dashboard split | `grid-template-columns: minmax(0, 1fr) 380px`, 24px gutter |
| Aside collapse | below 1180px it becomes a block at the end of the page |
| Settings sidebar | 260px wide |
| Settings content | capped at `--fsm-content-width` (1040px), centred in the pane |
| Card radius | 8px (token DEFAULT) |
| Card | `bg-surface`, 1px hairline border, `--fsm-shadow-card` |

**The app bar must be `fixed`, not `sticky`**, and pinned to
`left: var(--fsm-shell-left)`. The settings pane below it is also pinned, which leaves
the bar's container only as tall as the bar — and a sticky element whose container is its
own height has no range to stick over, so it scrolls away the moment wp-admin's menu makes
the page scroll.

`--fsm-shell-left` is the width of wp-admin's own menu, **measured at runtime** from
`#wpcontent`'s bounding rect, not hard-coded to 160px. Collapsing the menu, the automatic
fold on a narrow window, and the off-canvas menu on a phone all land on different widths.
Copy `measureShell()` from `fluent-security/src/admin/App.vue`.

wp-admin reserves a 65px strip under the content for its footer, which is where a pinned
sidebar comes unstuck at the bottom of a long page. Reclaim it:

```scss
body.settings_page_fluent-mail {
  background-color: var(--fsm-body-bg);
  #wpbody-content { padding-bottom: 0; }
  #wpfooter { display: none; }
}
```

### 4.5 Dark mode

Three parts, all lifted from fluent-security:

1. **`ThemeSwitch.vue`** (`src/admin/Bits/ThemeSwitch.vue`) — light / dark / system, with
   a `BroadcastChannel` so an open FluentCart tab moves too. Copy essentially verbatim.
2. **The shared keys.** `localStorage['fluent_theme_mode']`, legacy fallback
   `fcart_admin_theme`, class `fluent_theme_dark`, channel
   `fluent_theme_changed:<origin>`. These are **FluentCart's, deliberately** — sharing the
   key is what makes "I chose dark once" true across the family without either plugin
   knowing the other is installed. Do not rename them to `fsm_*`.
3. **A pre-paint script.** Add `printThemeClass()` to `AdminMenuHandler`, hooked on
   `admin_head`, gated on `$_GET['page'] === 'fluent-mail'`. Without it the screen paints
   light and then flips, which reads as a flash. Copy from
   `fluent-security/app/Hooks/Handlers/AdminMenuHandler.php:35`.

`system` is stored as `system:dark` / `system:light` — again FluentCart's shape — so the
pre-paint script can tell which way a system preference resolved last time without
waiting for `matchMedia`.

Element Plus is themed by **setting its variables**, not by restyling its components. Set
`--el-color-primary` in *both* themes, not just dark: `ElementPlusResolver` injects
component CSS into `<head>` at runtime, *after* our stylesheet, so a `:root` rule loses on
source order regardless of content. The body-class selector supplies the specificity to
beat it without leaking into any other plugin.

---

## 5. Build migration: Laravel Mix → Vite

### 5.1 Principle — keep the output paths

FluentCart needs a manifest→PHP bridge because it has ~90 entry points and hashed chunk
names. FluentSMTP has two JS entries and one stylesheet. **Fix the output filenames to
exactly what ships today**, and `fluentMailMix()`, the enqueue handler and the asset URLs
all keep working with no PHP change at all.

Current outputs that must be reproduced byte-for-byte in path:

```
assets/admin/js/boot.js
assets/admin/js/fluent-mail-admin-app.js
assets/admin/css/fluent-mail-admin.css
assets/images/…      (copied from resources/images)
assets/libs/…        (copied from resources/libs)
```

### 5.2 `vite.config.mjs`

```js
import { defineConfig } from 'vite';
import vue from '@vitejs/plugin-vue';
import { viteStaticCopy } from 'vite-plugin-static-copy';
import path from 'path';

export default defineConfig({
    base: '',
    plugins: [
        vue(),
        viteStaticCopy({ targets: [
            { src: 'resources/images', dest: '' },
            { src: 'resources/libs',   dest: '' },
        ]}),
    ],
    css: { preprocessorOptions: { scss: { api: 'modern' } } },
    resolve: { alias: { '@': path.resolve(__dirname, 'resources/admin') } },
    build: {
        outDir: 'assets',
        emptyOutDir: true,
        manifest: false,          // not needed: no hashed names, no dynamic imports
        cssCodeSplit: false,
        rollupOptions: {
            input: {
                'admin/js/boot':                    'resources/admin/boot.js',
                'admin/js/fluent-mail-admin-app':   'resources/admin/start.js',
                'admin/css/fluent-mail-admin':      'resources/scss/fluent-mail-admin.scss',
            },
            output: {
                entryFileNames: '[name].js',
                assetFileNames: '[name][extname]',
                chunkFileNames: 'admin/js/chunks/[name].js',
            },
        },
    },
});
```

Verify early that the SCSS entry emits to `assets/admin/css/fluent-mail-admin.css` and not
to an `assets/` subfolder — Rollup treats a CSS entry as an asset, and `assetFileNames`
governs it. If the name comes out wrong, a five-line `renameBundle` plugin in
`closeBundle()` is a smaller price than reworking the PHP.

**Do not introduce dynamic imports.** The whole app is ~7k lines; code splitting buys
nothing here and would drag in the manifest machinery this section exists to avoid.

**Element Plus auto-import.** Use `unplugin-vue-components/vite` +
`unplugin-auto-import/vite` with `ElementPlusResolver`, as fluent-security does. That
deletes `resources/scss/vendor.scss` (182 lines of hand-maintained CSS imports) outright.
Keep explicit imports only for the handful that the resolver misses — `el-notification`,
`el-loading`, `el-message-box` are used imperatively, not as tags, so import their CSS by
hand as fluent-security does in `app.scss`.

### 5.3 `package.json`

Remove: `laravel-mix`, `webpack`, `webpack-cli`, `vue-loader@15`, `vue-template-compiler`,
`sass-loader`, `css-loader`, `resolve-url-loader`, `element-ui`, `babel-plugin-import`,
`@babel/plugin-syntax-dynamic-import`, and the `pnpm.overrides.webpack` pin.

Add: `vite`, `@vitejs/plugin-vue`, `vite-plugin-static-copy`, `@vue/compiler-sfc`,
`unplugin-vue-components`, `unplugin-auto-import`, `tailwindcss@^3.4`, `postcss`,
`autoprefixer`, `element-plus@^2.9`, `@element-plus/icons-vue`.

Upgrade: `vue` 2.6 → `^3.5`, `vue-router` 3 → `^4`, `chart.js` → `^4.4`,
`vue-chartjs` → `^5.3`.

Scripts: `"dev": "vite"`, `"build": "vite build"`, keep `"prod": "npm run build"` as an
alias so muscle memory and any CI referring to it keep working.

Match the versions fluent-security and fluent-cart are already on rather than picking
latest independently — the point of this exercise is that the three stay together.

### 5.4 `build.sh`

Three changes:

1. `npx mix --production` → `npx vite build`
2. Add `-x "_docs/*"` and `-x "vite.config.mjs"` and `-x "tailwind.config.js"` and
   `-x "postcss.config.js"` to the zip exclusion list.
3. Leave the two verification gates alone — the leaked-dev-files check and the
   "assets/ is empty" check are exactly what protects a build-tool swap. Extend the
   `LEAKED` grep pattern with `_docs/`.

> `internal_docs/` and `research/` are **gitignored**, which is why this plan lives in
> `_docs/` instead — it needs to be committed and shared. `_docs/` matches
> fluent-security's convention. It is *not* currently excluded from the zip, so the
> `build.sh` change above must land with this file, not after it.

### 5.5 Dev server (optional, second pass)

A Vite dev server against wp-admin needs an origin switch in the PHP enqueue and a CORS
allowance. It is genuinely nice, and it is also the part of FluentCart's setup that
carries the port finder and the env switcher. **Ship the production build first.** Add
the dev server afterwards, behind a `FLUENTMAIL_VITE_DEV` constant, only if the team wants
it. `vite build --watch` is good enough to start with and needs no PHP change.

---

## 6. Vue 2 → Vue 3 migration mechanics

### 6.1 App bootstrap

`boot.js` currently constructs a `FluentMail` class that mutates the global Vue
constructor — `Vue.use()` × 38, `Vue.mixin()`, `Vue.filter()` × 3, `Vue.prototype.$x`.
None of that exists in Vue 3.

New shape, following `fluent-security/src/admin/app.js`:

```js
const app = createApp(Application);
app.use(router);
app.use(ElLoading);
app.config.globalProperties.appVars = window.FluentMailAdmin;
app.config.globalProperties.$notify  = ElNotification;
app.config.globalProperties.$confirm = ElMessageBox.confirm;
app.mixin({ data() { … }, methods: { $get, $post, $t, $dateFormat, … } });
app.mount('#fluent_mail_app');
```

The `app.mixin` keeps `$get` / `$post` / `$t` / `ucFirst` / `slugify` / `escapeHtml`
available exactly as today, so **no component's script block needs to change for the
helpers** — that is the single biggest lever for keeping this diff small.

### 6.2 Vue 2 filters — already clear

Grep found **zero** uses of `| dateFormat`, `| ucFirst`, `| ucWords` in templates. The
three `Vue.filter()` registrations can be dropped without touching a single template. The
same functions remain available as mixin methods.

### 6.3 The event bus

One `$bus` usage. Vue 3 has no `new Vue()` to use as an emitter. Given it is a single
site, replace it with a direct prop/emit rather than adding `mitt` as a dependency.

### 6.4 The `window.FluentMail.Vue` shim

`window.FluentMail.Vue` is a Vue 2 constructor and cannot be preserved as such. Keep the
`FluentMail` global with everything else intact — `applyFilters`, `addFilter`,
`registerTopMenu`, `registerBlock`, `$get`, `$post`, `appVars` — and replace `.Vue` with
the Vue 3 module namespace (`{ createApp, ref, computed, … }`).

Nothing in the WPManageNinja repo set reads it. A third-party addon that does will break,
and there is no shim that can honestly prevent that — a Vue 2 constructor and a Vue 3 app
factory are not substitutable. Note it in the release changelog under a "for developers"
heading; do not pretend it is backwards compatible.

### 6.5 Element UI → Element Plus

| Element UI 2 | Element Plus | Sites |
|---|---|---|
| `slot="reference"` | `#reference` | 29 |
| `slot-scope="scope"` | `#default="scope"` | 11 |
| `:visible.sync="x"` | `v-model="x"` | 3 |
| `size="mini"` | `size="small"` | 30 |
| `<el-button type="text">` | `<el-button link>` | 9 |
| `icon="el-icon-edit"` | `:icon="Edit"` from `@element-plus/icons-vue` | 47 |
| `:picker-options="{…}"` | `:shortcuts` / `:disabled-date` | 2 |
| `value-format="yyyy-MM-dd"` | `value-format="YYYY-MM-DD"` | 2 |
| `<el-row>` / `<el-col>` | Tailwind `grid` / `flex` | 164 |

The `el-icon-*` count is the one that will surprise people: Element Plus dropped the icon
font completely. Each becomes a named SVG component import. Build a small
`resources/admin/Bits/icons.js` that re-exports just the icons this app uses, so 47 call
sites import from one place rather than 47 separate `@element-plus/icons-vue` imports.

`el-row`/`el-col` are the largest raw count but the *easiest* work — they are being
replaced by the new layout anyway (§4.4), so most disappear as a side effect of
redesigning the screen rather than as a separate migration step.

### 6.6 Charts — a rewrite, not a migration

The vendored `resources/libs/chartjs/Chart.min.js` is **Chart.js 2.7.1**, and
`_chart.js` is written against the v2 options API: `scales.yAxes[]` / `xAxes[]` arrays,
`gridLines`, `ticks.userCallback`, the `reactiveProp` mixin, and `this.renderChart()`.
None of that exists in Chart.js 4.

Plan:

1. Delete `resources/libs/chartjs/` and the two `wp_enqueue_script` calls for it in
   `AdminMenuHandler::enqueueAssets()` (lines 205–206). They become bundle imports.
2. Rewrite `Modules/Dashboard/Charts/_chart.js` against Chart.js 4 — `scales` becomes a
   keyed object, `gridLines` → `grid`, `userCallback` → `ticks.callback`.
3. Rewrite `Emails.vue` and `ByDayTimeSending.vue` (239 lines, the larger of the two) to
   use `vue-chartjs` 5's `<Bar :data :options />` component form. `reactiveProp` is gone;
   reactivity is just a bound prop now.
4. Colour the series from the **`report` ramp** in `color.js` (`royal_blue`,
   `medium_turquoise`, `golden_rod`, …) — these are the values FluentCart's own charts
   use, and they are theme-independent so they need no dark variant.
5. Chart.js 4 does not read CSS variables. Grid and tick colours must be passed in JS and
   recomputed when the theme changes — read them with `getComputedStyle` on the app root
   and re-render on the theme-change event.

This is the highest-risk item in the plan and the one most likely to be under-estimated.
Budget it as its own phase.

### 6.7 The vitest suite

`tests/js/request-layer.test.js` mocks `vue`, `vue-router`, `element-ui` and
`element-ui/lib/locale` module-by-module, listing all 38 registered components. Every one
of those mocks is invalidated. Rewrite the mocks against `element-plus`; the assertions
about the request layer itself (`$get`/`$post` action names, nonce attachment) should
survive unchanged, since §1's non-goals keep that layer as it is.

---

## 7. The new shell and information architecture

### 7.1 Today

Seven flat items in a horizontal `el-menu`: Dashboard · Settings · Email Test · Email
Logs · Alerts · About · Documentation. Three of those seven ("Settings", "Alerts",
"Email Test") are all things you go to *change* or *do*, presented as peers of the two
things you go to *look at*.

### 7.2 Target

```
┌──────────────────────────────────────────────────────────────────────────┐
│ [FluentSMTP]  Dashboard   Email Logs   Settings      [Send Test] [?] [☾] │
└──────────────────────────────────────────────────────────────────────────┘
```

**Top bar — destinations only.** Dashboard, Email Logs, Settings.

**Right-hand actions.** A primary *Send Test Email* button (it is the single most common
thing an admin does on this plugin, and burying it in a settings sidebar would be a
regression), a Documentation link, and the theme switch.

**Settings sidebar** — 260px, everything configurable in one place:

| Sidebar item | Existing route (unchanged) |
|---|---|
| Connections | `/connections`, `/connection` |
| General | `/connections` (the General Settings panel, promoted to its own section) |
| Alerts & Notifications | `/notification-settings` |
| Email Test | `/test` |
| About | `/support` |

Documentation (`/documentation`) stays a route and is reachable from the top bar's help
affordance.

> Re-read §3.4 before implementing. Sidebar grouping is a chrome change. `path` values
> stay identical, and `#/notification-settings` in particular must land on the Alerts
> section without a redirect, because Slack's OAuth flow returns to it.

Set `meta.active` on each route so the top bar can highlight *Settings* while any of its
children is open — the same `isActive(item)` / `item.match` pattern as
`fluent-security/src/admin/App.vue`.

### 7.3 Screen-by-screen

**Dashboard** (`Dashboard.vue`, 223 lines). Currently `el-row` 16/8 with `.fss_header` +
`.fss_content` widget boxes. Target: the `minmax(0,1fr) 380px` grid from §4.4. Left —
sending stats chart, by-day/time chart. Right — a sticky aside carrying Quick Overview,
using `.fsm_aside_block` (dashed-rule separated subjects) rather than today's
`ul.fss_dash_lists` with `float: right` counts.

The four headline numbers (Sent, Failed, Active Connections, Active Senders) should become
**stat tiles** using the `--fsm-tile-*` ramp, one hue per status, matching FluentCart's
dashboard. Today they are list rows with a floated span, and "Failed" is coloured with an
inline `style="color: red"`.

Keep the literal strings `Sending Stats` and `Quick Overview`, or update the smoke
manifest in the same commit.

**Email Logs** (`Logs.vue`, 495 lines — the largest component). Currently an `el-table`
with `stripe border` and full-row background tinting for failures
(`.row_type_failed` paints rows `rgb(253,226,226)`). Target: adopt fluent-security's
`.fls_tag` pattern — a one-word status chip (`is_success` / `is_failed` / `is_blocked` /
`is_neutral`) instead of a tinted row. Three coloured bands across a full-width table is a
lot of colour for a fact that fits in one word, and it leaves no way to colour anything
else in the row. Table sits flush inside its card; the card draws the frame, so drop
`border` from `el-table`.

**Connections** (`Connections.vue`, 181 lines). The provider column already renders logos.
Move to the card + flush-table pattern; replace the three `size="mini"` icon buttons
(`el-icon-edit` / `el-icon-view` / `el-icon-delete`) with Element Plus icon components at
`size="small"`. Replace the inline-styled float header with `.fsm_card_head`.

**Connection wizard** (`ConnectionWizard.vue`, 218 lines + 15 provider partials). The
provider picker is an `el-radio-group` of images with per-provider `:is-active` colour
overrides hardcoded in SCSS (`.con_gmail`, `.con_outlook`, `.con_postmark`). Replace with
a token-based selected state — one rule, not one per provider. The 15 provider partials
under `Settings/Partials/Providers/` are mostly `el-form-item` stacks; they convert
mechanically via the §6.5 table and should adopt `.fsm_row` (label + description left,
control pinned right) rather than Element's default label column.

**Alerts** (`NotificationSettings/`, 13 files). Channel cards for Telegram / Slack /
Discord / Pushover. Good candidate for the `.fsm_toggle` pattern — switch beside the name,
explanation indented underneath to the switch's width so the text forms one column and the
switches a second, narrower one.

**Email Test** (`Test.vue`, 172 lines) and **About** (`Support.vue`, 294 lines) and
**Docs** (`Docs.vue`, 133 lines) — straight reskins, no structural change.

### 7.4 Shell components to create

Under `resources/admin/` (**keep this directory — do not move to `src/`**; `build.sh`
excludes `resources/*` from the zip, and moving it means re-deriving that exclusion):

```
resources/admin/
  App.vue                     ← rewrite of Application.vue: app bar + nav + theme switch
  Bits/ThemeSwitch.vue        ← copied from fluent-security
  Bits/icons.js               ← curated @element-plus/icons-vue re-exports
  styles/
    _theme.scss               ← light + dark token declarations
    _layout.scss              ← app bar, settings shell, cards, rows, tags
    tokens/{color,spacing,borderRadius,fontSize}.js
```

`Application.vue` keeps its filename and its `.fluent-mail-app` root class (§3.5) even as
its contents are replaced.

---

## 8. Risk register

| Risk | Severity | Mitigation |
|---|---|---|
| A hash route changes and breaks links already sent to users via Slack/Telegram/Discord | **Critical** | §3.4 rule; `browser-route-coverage.php` lint is already wired to catch it |
| Chart.js 2 → 4 rewrite under-scoped | High | Own phase; two components, both rewritten not ported |
| Vite CSS entry emits to the wrong filename, breaking the enqueue | Medium | Verify in Phase 1 before any component work; `renameBundle` fallback in §5.2 |
| `window.FluentMail.Vue` breaks a third-party addon | Medium | Cannot be prevented; document in changelog (§6.4) |
| Smoke-test text markers drift as headings are redesigned | Medium | Update `adminScreens[]` in the same commit as the heading |
| Tailwind utilities leak into wp-admin | Medium | `important: '#fluent_mail_app'` + preflight off; spot-check other plugins' settings screens |
| Dark theme half-applied (a white card on a dark page) | Medium | Ban literal hex in components; every colour via `--fsm-*` |
| `_docs/` ships to wordpress.org | Low | `build.sh` exclusion lands with this file (§5.4) |
| Element Plus popups unthemed because they teleport to `<body>` | Low | Tokens declared on `body.settings_page_fluent-mail`, not the app root (§4.2) |

---

## 9. Phased execution

Each phase ends green — `tests/bin/run-all.sh` passes, and the plugin loads in wp-admin.
Do not start a phase before its predecessor is green.

### Phase 0 — Groundwork *(no visual change)*
- Add `_docs/` exclusion to `build.sh`, extend the `LEAKED` grep.
- Land this plan on `redesign`.

### Phase 1 — Build swap *(no visual change)*
- Add `vite.config.mjs`, `tailwind.config.js`, `postcss.config.js`.
- Rewrite `package.json` deps and scripts; delete `webpack.mix.js`.
- Update `build.sh` step 1.
- **Gate:** `npx vite build` produces the three expected output paths byte-for-byte;
  the plugin loads and every screen still renders on Vue 2 via `@vitejs/plugin-vue2`,
  *or* fold Phase 1 and 2 together if that shim proves more trouble than it saves.
- **Gate:** `./build.sh` passes both of its own verification checks.

### Phase 2 — Vue 3 + Element Plus, no redesign
- Bootstrap rewrite (§6.1), `FluentMail` global shim (§6.4).
- Mechanical codemods from the §6.5 table across all 59 SFCs.
- Charts rewritten (§6.6).
- Vitest mocks rewritten (§6.7).
- **Gate:** all 8 browser smoke screens pass. The app should look *approximately as
  ugly as it does today* at the end of this phase — that is the point. Do not start
  restyling here; a broken screen must be attributable to either the framework move or
  the redesign, never to both at once.

### Phase 3 — Design system in
- Token files, `_theme.scss`, `_layout.scss`.
- `ThemeSwitch.vue` + `printThemeClass()`.
- Delete `resources/scss/vendor.scss`.
- **Gate:** light and dark both render; toggling in a FluentCart tab moves this one.

### Phase 4 — Shell and IA
- `App.vue` rewrite: fixed app bar, `measureShell()`, settings sidebar, `meta.active`.
- **Gate:** `#/notification-settings` lands on Alerts with no redirect; `#/logs?status=failed`
  still filters.

### Phase 5 — Screens
In descending order of user-facing weight: Dashboard → Logs → Connections → Wizard +
provider partials → Alerts → Test / About / Docs.
- **Gate after each:** that screen's smoke markers pass.

### Phase 6 — Polish and release prep
- Responsive pass at 1180px, 960px, 782px (see fluent-security's breakpoints — those
  three are where its layout actually changes).
- Keyboard focus: move wp-admin's blue ring to `:focus-visible` and recolour, do not
  remove it.
- Changelog, including the `window.FluentMail.Vue` developer note.

---

## 10. Acceptance criteria

- [ ] `./build.sh` completes and its two self-checks pass
- [ ] `tests/bin/run-all.sh` green, including `browser-route-coverage` and all 8 smoke screens
- [ ] All 8 route paths in `routes.js` are byte-identical to `master`
- [ ] `#/`, `#/connections`, `#/notification-settings`, `#/logs?status=failed` resolve correctly
- [ ] Slack OAuth round trip returns to a working Alerts screen
- [ ] No literal hex colours in any `.vue` file; every colour resolves through `--fsm-*`
- [ ] Dark mode complete on all 8 screens — no white card on a dark page
- [ ] Switching theme in FluentCart moves an open FluentSMTP tab, and the reverse
- [ ] No Tailwind utility leaks onto another plugin's settings screen
- [ ] `.fluent-mail-app` present exactly once and visible on every screen
- [ ] `registerTopMenu()`, `fluent_mail_top_menus`, `fluent_mail_global_routes` still function
- [ ] `_docs/` absent from `fluent-smtp.zip`

---

## 11. Reference files

Read these before starting — they are the same problem, already solved:

| Purpose | Path |
|---|---|
| The whole pattern, at FluentSMTP's scale | `fluent-security/src/admin/` |
| Token files to copy verbatim | `fluent-security/src/admin/styles/tokens/` |
| Light + dark token declarations | `fluent-security/src/admin/styles/_theme.scss` |
| App bar, settings shell, cards, rows, tags | `fluent-security/src/admin/styles/_layout.scss` |
| Shell component + `measureShell()` | `fluent-security/src/admin/App.vue` |
| Vue 3 bootstrap | `fluent-security/src/admin/app.js` |
| Cross-plugin theme switch | `fluent-security/src/admin/Bits/ThemeSwitch.vue` |
| Pre-paint theme script | `fluent-security/app/Hooks/Handlers/AdminMenuHandler.php:35` |
| Tailwind config shape | `fluent-security/tailwind.config.js` |
| Container widths (1350 / 1600, `px-7.5`) | `fluent-cart/resources/styles/tailwind/menu.scss:163` |
| Card anatomy | `fluent-cart/resources/styles/tailwind/card.scss` |
| Vite config (reference only — larger than we need) | `fluent-cart/vite.config.mjs` |

---

## 12. Decisions taken during execution

Where reality disagreed with §1-§11, this is what was done and why. Written as the
work happened, newest phase last.

### Phase 1 — build swap

**Two Vite builds, not one.** §5.2 specifies a single `vite build` with three inputs.
Rollup refuses `output.format: 'iife'` for any build with more than one input, and `es`
is not an alternative: `AdminMenuHandler::enqueueAssets()` emits plain `<script src>`
tags, where an `import` statement is a syntax error. The config therefore takes a
`--mode` (`boot` or `app`) and `package.json`'s `build` script runs both in sequence.
`emptyOutDir` is `false` for the same reason — the second build would otherwise delete
the first one's output.

**The stylesheet is emitted by the `app` build, not by a third build of its own.**
Laravel Mix pushed each SFC's `<style>` block through `vue-style-loader`, which injects
it at runtime, so component styles were never in a file. Vite extracts them, and they
have to be extracted into the one file `wp_enqueue_style` already asks for or they are
silently dropped — a 2.5 kB hole in the styling with nothing in the console to say so.
`start.js` therefore imports `resources/scss/fluent-mail-admin.scss`, which puts the
vendor CSS ahead of the component CSS in the cascade, matching today's load order. This
is also the shape §11's reference (`fluent-security/src/admin/app.js`) already uses.

**The `renameBundle` fallback in §5.2 was needed.** Rollup has no concept of a
stylesheet entry point, so Vite names the extracted file `style.css` regardless of the
input's name and leaves behind an empty JS chunk. `renameExtractedCss()` in
`vite.config.mjs` renames the first and drops the second. Its `order: 'post'` matters:
Vite's own css plugin emits the stylesheet from *its* `generateBundle`, so a plugin that
runs first sees a bundle with no CSS in it and quietly does nothing.

**Vue 2.6.14 → 2.7.16, inside Phase 1.** `@vitejs/plugin-vue2` declares a peer of
`vue ^2.7.0-0` at every version, so keeping the Vue 2 shim at all requires 2.7. Vue 2.7
is Vue 2's terminal release and Element UI 2.15 supports it; templates and render output
are unchanged. This is throwaway — Phase 2 replaces it with Vue 3.

**Vite 7, not 5.** `vitest@4` (already in the repo, driving `tests/js/`) declares a peer
of `vite ^6 || ^7 || ^8`. Pinning Vite 5 to match the plan would have left the JS test
tier running against a Vite the suite does not support.

**`resolve.alias.vue` pins Vue to one file.** This is the one real bug the phase turned
up, and it is worth writing down because the failure is silent. Vue's package.json has
`main: dist/vue.runtime.common.js` and `module: dist/vue.runtime.esm.js`. Our
`import Vue from 'vue'` takes `module`; Element UI's CommonJS `require('vue')` takes
`main`. Two Vues means two independent reactivity systems. `el-table` builds its column
store as a Vue instance from Element's copy, so the app's copy never collects a
dependency on it: the table rendered ten `<tr>` elements with **no `<td>` in any of
them**, and nothing in the console. The browser smoke passed throughout, because its
markers ('Email Logs', 'Filter') live in the header above the table. Caught by comparing
screenshots against the pre-migration baseline, which is the argument for taking those.

`resolve.dedupe` does not fix this — both specifiers already resolve inside the same
installed package, just to different files in it.

**`tailwind.config.js` and `postcss.config.js` land here structurally, not fully.**
Scoping (`important: '#fluent_mail_app'`), `darkMode`, `content` and `preflight: false`
are set now so the pipeline is proven wired; `theme.extend` and the token files arrive in
Phase 3 with the `@tailwind` directives that make them do anything. Tailwind is a no-op
until then. Autoprefixer is not: it rewrites Element UI's vendor prefixes against the
default browserslist, which is add-and-remove only — verified by diffing the selector
sets before and after, 28 prefixed selectors out and 6 in, no unprefixed rule touched.

**Deleted as dead laravel-mix scaffolding:** `webpack.config.js` (a one-line re-export of
`laravel-mix/setup/webpack.config.js`) and `resources/admin/Bits/mix.js` (an unreferenced
second mix config, still wired to `eslint-loader`).

**Direction confirmed mid-phase:** Vue 3 + Element Plus, and Vue 3 written in the
**Options API** throughout — no `<script setup>`, no Composition API rewrite. This is
also what keeps §6.1's `app.mixin` lever working: the helpers stay on the instance, so
no component's script block has to change to reach `$get`/`$post`/`$t`.

### Phase 2 — Vue 3 + Element Plus, no redesign

**Options API throughout, confirmed during the phase.** No `<script setup>`, no
Composition API rewrite. This is also what keeps §6.1's `app.mixin` lever working: the
helpers stay on the instance, so not one of the 59 component script blocks had to change
to keep reaching `$get` / `$post` / `$t` / `ucFirst`.

**`window.FluentMail.Vue` and `.Router` are published from `start.js`, not from the
class.** §6.4 has them as constructor fields. Importing Vue inside `Bits/FluentMail.js`
puts a *second* Vue in boot.js - 150 kB of duplicate payload, and, worse, a second
reactivity system sitting on the public API under the name of the first. Assigning them
in `start.js` means what an extension reaches through `.Vue` is the very module the app
is running on. boot.js went from 952 kB to 13 kB as a side effect.

**`ByDayTimeSending.vue` needed no chart work.** §6.6 budgets it as the larger of the two
Chart.js rewrites. It is not a Chart.js chart at all - it is a hand-built heatmap of
divs, and the only Chart.js consumer in the app is `Charts/_chart.js` plus `Emails.vue`.
The chart rewrite came in far under its estimate as a result. Two behaviours had to be
asked for explicitly that Chart.js 2 gave by default: `Filler` has to be registered, and
the cumulative dataset needs `fill: true`, or the wash under the line disappears. The
canvas also needs a parent with a height, because vue-chartjs 5 dropped the default
`height` prop that vue-chartjs 2 put on the canvas.

**Vendored Chart.js deleted, and its two `wp_enqueue_script` calls with it.** The
vendored build was 2.7.1 while `package.json` declared `^3.4.1`, so the version anyone
read was never the version that shipped.

**The icon components are `FsmIcon*`, not `ElIcon*`.** `ElementPlusResolver` claims the
`ElIcon*` namespace: `<el-icon-info />` makes it try to auto-import an `Info` export from
`@element-plus/icons-vue`, which does not exist - the icon is `InfoFilled` - and the build
fails. They are registered globally in `start.js` so that `icon="FsmIconSearch"` keeps
working as a plain string attribute, including `_AlertListTable.vue`'s case where the
icon is chosen by an expression at runtime.

**Element Plus's variables are re-asserted under `body.settings_page_fluent-mail`.**
FluentCart injects an inline `<style>` into *every* wp-admin page mapping 31 Element Plus
variables onto its own `--fc-*` palette on `:root`. Inline styles in `<head>` win on
source order, so FluentSMTP silently wore FluentCart's colours: the unselected status
filters rendered white text on white, and every `type="info"` button came out near-black.
§4.2 already prescribes the fix - declare on the body class, which beats `:root` on
specificity - and Phase 2 puts the block in with Element Plus's own default values.
Phase 3 changes the values; the selector is the part that matters.

One trap inside the trap: `--el-button-text-color` must be set to
`var(--el-text-color-regular)`, not to a literal. Element Plus never declares it at root
scope - components read `var(--el-button-text-color, var(--el-text-color-regular))` and
each coloured variant sets it on itself - so a literal would repaint every variant.

**Two bugs the screenshots could not have caught, and the smoke did not:**

- `this.$set` throws in Vue 3, at 11 call sites. The important one is
  `ConnectionWizard.vue`'s watcher on `connection.provider`, which copies the provider's
  defaults out of `config.php` into the form. It threw, so **a newly picked provider
  received none of its defaults** - `auth` and `auto_tls` should both start on, and
  started off. The screen looked entirely plausible. Found in the browser console, and
  only because the build was temporarily made unminified with Vue's warnings on;
  the production build strips them.
- `el-radio` / `el-checkbox` `label`-as-value is deprecated in Element Plus 2.6 and
  removed in 3.0. Converted to `value` / `true-value` / `false-value` at 25 files.

The lesson worth keeping: a production Vue build is silent about exactly the class of
problem a framework migration produces. Building once with `mode: 'development'` and
walking all eight screens took two minutes and was the highest-yield check in the phase.

**Pre-existing bug fixed because Vue 3's compiler will not tolerate it:** `Test.vue` had
`v-else` twice on the same element. Vue 2's compiler accepted it silently.

**Layout differences from the component swap, fixed rather than left:**

- Element Plus lays a form item's content out with `display: flex; flex-wrap: wrap`,
  where Element UI's was a plain block. A help line after a narrow control sat *beside*
  it, reading as "OnSend this email in HTML...". `.small-help-text` now claims
  `flex-basis: 100%`.
- Element Plus's `el-select` is `width: 100%`, where Element UI's was content-sized.
  Inside the floated (shrink-to-fit) widget header on the dashboard, the two collapsed
  each other and the control rendered as a bare chevron.
- An Element Plus button is a few pixels wider than the Element UI `mini` it replaced.
  Three of them in the logs table came to 179px inside a 176px content box and wrapped
  onto two lines; the Actions column went from 200px to 220px.

**`tests/js/request-layer.test.js` lost its mocks entirely** rather than having them
rewritten against `element-plus`. `Bits/FluentMail.js` no longer imports a framework at
all - the wiring moved to `start.js` - so there is nothing to stub. Added one test that
the mixin still exposes every helper, since that is the assumption holding 59 untouched
component script blocks up.

### Phase 3 — design system in

**The legacy stylesheet was tokenised here rather than left for Phase 5.** §9 puts the
screen work in Phase 5, but Phase 3's gate is "light and dark both render", and 55 literal
colours across `fluent-mail-admin.scss`, `_docs.scss` and `_alerts.scss` are exactly what
stops that being true. Tokenising them now makes the gate mean something, and it means
Phase 5 changes structure rather than structure *and* colour. Brand colours stay literal,
as §4.2 allows: FluentCRM's purple on its own button, and the five filled levels of the
sending heatmap, which are a data ramp.

**The heatmap's empty cell is chrome, not data.** Levels 1-5 carry the same five colours
in both themes - a count of emails does not mean something different in the dark - but
level 0 is the cell behind them, so `--fsm-heat-0` flips and `--fsm-heat-1..5` do not.

**The per-provider selected states became one rule.** §7.3 schedules this for Phase 5 with
the wizard, but `.con_gmail`, `.con_outlook` and `.con_postmark` each painted the chosen
tile in that service's brand colour with `!important`, which is three light chips on a
dark card. Which provider it is, is already said by the logo inside the tile; what the
tile needs to say is that it is the chosen one, and that is one fact and one rule.

**Four things Element Plus needed told outright, none of which a token reaches:**

- `--el-menu-bg-color` is declared as a literal `#fff` in Element Plus's own CSS rather
  than derived from `--el-bg-color`, so setting the background variables did not reach the
  nav and the bar stayed white on a dark page.
- A checkbox's tick is `--el-color-white`. In dark the accent it is drawn on is the *pale*
  one, so the box read as filled but empty. The tick takes `--fsm-accent-contrast`.
- wp-admin colours its own headings outright (`#1d2327`), which an inherited colour cannot
  beat - a card title inside the app stayed near-black on a near-black card.
- Provider logos are brand marks drawn for a white page: the Postmark wordmark and the
  "Other SMTP" envelope are black on transparent and vanish. They keep the light backing
  they were drawn for, matched on the image path so no component markup changes.

**Chart.js gets its colours as values and a signal to recompute them.** §6.6 item 5 called
this: Chart.js paints to a canvas and cannot read a CSS variable. `themeColours()` reads
`--fsm-border` and `--fsm-text-light` off the live app root, and `ThemeSwitch` dispatches a
`fluent_theme_applied` event on every apply - including one that arrived from another tab
over the BroadcastChannel - which the chart listens for. Options are computed from that,
so vue-chartjs redraws on its own.

**The email body frame is the light island.** fluent-security has one for its login
preview; FluentSMTP's is the sanitised email body in the log viewer. It is a picture of
what the *recipient* saw, and the recipient's mail client was not running this plugin's
dark theme. Without pinning `color-scheme: light` on the frame's document it inherits the
page's dark scheme, the browser paints its canvas near-black and flips default text to
light, and any message written for a white background stops being legible.

**Where the theme switch sits, for now.** Phase 4 owns the app bar, so Phase 3 hangs the
control off the right-hand end of the existing horizontal menu. Its styles are written as
plain declarations rather than with `@apply`, because the dropdown half is teleported to
`<body>` by Element Plus - and Tailwind's `important: '#fluent_mail_app'` prefixes every
utility with that selector, which a popup outside the app root no longer matches.

**Verified both ways across plugins:** setting dark in FluentSMTP and loading FluentCart
gives a dark FluentCart; writing the key from FluentCart and loading FluentSMTP gives a
dark FluentSMTP, applied by `printThemeClass()` in `<head>` before the page paints.

### Phase 4 — shell and IA

**All eight paths are byte-identical to master.** The diff on `routes.js` touches `meta`
and nothing else; `browser-route-coverage.php` confirms 8 declared, 8 manifested.
`meta.active` is chrome, not routing - it tells the app bar which of its three
destinations to light up, so Settings stays marked while any screen behind it is open.

**The settings shell is chosen by `meta.active`, not by a route hierarchy.** §7.2 allows
adding child routes; none were needed. `Application.vue` renders the pinned pane and its
sidebar when `meta.active === 'settings'` and an ordinary page otherwise, which means no
screen component knows anything about the chrome around it and no path had to move.

**General is a subnav anchor, not a route.** §7.3's table maps it to `/connections`, the
same path as Connections, because it is the General Settings panel on that screen. Two
sidebar items pointing at one route would be a nav that lies, so it is a child item that
scrolls to the panel. Splitting that screen properly belongs to Phase 5, where it is
rebuilt anyway.

**`#wpfooter` is hidden only while the settings pane is open.** §4.4 says to reclaim
wp-admin's 65px footer strip, which is where a pinned pane comes up short. Hiding it
outright would also delete FluentSMTP's own footer credit and its review link on every
screen, so `Application.vue` toggles `fsm_settings_open` on `<body>` and the rule is
scoped to that. `#wpfooter` is outside the app root, which is why the class goes on the
body rather than on the shell.

**`registerTopMenu()` still lands an add-on's item in the bar.** The default list dropped
from six items to three, but it is still passed through `fluent_mail_top_menus`, and an
item registered without a `match` falls back to being active on its own route only.

**Two small things the rewrite turned up:**

- `logo.svg` is authored with `width="100%" height="100%"`. Inside the bar's flex row that
  resolves against nothing and the mark collapses to a 0x0 box, which is why the bar
  rendered with an empty space where the logo should be. It needs an explicit width.
- The wordmark is a pink mark beside near-black lettering, so it cannot be flattened and
  inverted for dark the way fluent-security's single-colour logo is. The plugin already
  ships `fluentsmtp-white.png`; both are in the markup and CSS picks one.

**The old `.fluent-mail-body` wrapper is gone from the markup**, but the rules nested
inside it are not - they are the screen styling Phase 5 will replace. The selector became
a bare `&`, lifting its children one level, rather than being rewritten twice.
