/** @type {import('tailwindcss').Config} */

/*
 * The tokens are copied from FluentCart rather than approximated, so a user moving
 * between the two plugins is looking at the same greys, the same spacing steps and the
 * same radii. If that palette changes there, it should be copied again rather than
 * drifted towards.
 */
const colors = require('./resources/admin/styles/tokens/color');
const spacing = require('./resources/admin/styles/tokens/spacing');
const borderRadius = require('./resources/admin/styles/tokens/borderRadius');
const fontSize = require('./resources/admin/styles/tokens/fontSize');

/*
 * The colours the app actually paints with, named for what they are for.
 *
 * Each one is a CSS variable rather than a value, because it has two values - see
 * resources/admin/styles/_theme.scss, where both themes are declared. That is the whole
 * mechanism: `@apply bg-surface` is correct in light and dark, `@apply bg-white` is
 * correct in one of them.
 *
 * The ramps above are still here and still used for anything that does not change between
 * themes - a chart series, a brand colour, a shadow. Reach for these first.
 */
const themed = {
    // Surfaces, from the page up: the page itself, a card on it, a well in the card.
    surface: 'var(--fsm-surface)',
    'surface-sunk': 'var(--fsm-surface-sunk)',
    'surface-raised': 'var(--fsm-surface-raised)',
    'body-bg': 'var(--fsm-body-bg)',

    // Rules and outlines.
    hairline: 'var(--fsm-border)',
    'hairline-strong': 'var(--fsm-border-strong)',

    // Text, from the loudest to the quietest.
    'ink-head': 'var(--fsm-heading)',
    ink: 'var(--fsm-text)',
    'ink-mid': 'var(--fsm-text-mid)',
    'ink-light': 'var(--fsm-text-light)',
    'ink-link': 'var(--fsm-link)',

    // The brand colour, what goes on top of it, and a tint of it.
    accent: 'var(--fsm-accent)',
    'accent-on': 'var(--fsm-accent-contrast)',
    'accent-wash': 'var(--fsm-accent-wash)',

    // Statuses: a band's fill and border, a chip's fill, and the text for all three.
    'danger-wash': 'var(--fsm-danger-wash)',
    'danger-bg': 'var(--fsm-danger-bg)',
    'danger-line': 'var(--fsm-danger-line)',
    'danger-fg': 'var(--fsm-danger-fg)',

    'caution-wash': 'var(--fsm-warning-wash)',
    'caution-bg': 'var(--fsm-warning-bg)',
    'caution-line': 'var(--fsm-warning-line)',
    'caution-fg': 'var(--fsm-warning-fg)',

    'ok-wash': 'var(--fsm-success-wash)',
    'ok-bg': 'var(--fsm-success-bg)',
    'ok-line': 'var(--fsm-success-line)',
    'ok-fg': 'var(--fsm-success-fg)',

    'quiet-bg': 'var(--fsm-neutral-bg)',
    'quiet-fg': 'var(--fsm-neutral-fg)'
};

module.exports = {
    /*
     * The dark class is FluentCart's, deliberately. Sharing it - along with the
     * localStorage key and the BroadcastChannel name - is what makes "I chose
     * dark once" true across the whole plugin family without either plugin
     * knowing the other is installed.
     */
    darkMode: ['selector', '.fluent_theme_dark'],

    /*
     * Every utility is scoped under the app's own mount node. This runs inside
     * wp-admin next to whatever else the site has installed, so utilities must
     * not escape into the surrounding page.
     *
     * The id survives only because Vue 3 renders *inside* the mount node, where
     * Vue 2's `new Vue({el})` replaced it. Do not change the mount markup in
     * app/views/admin/menu.php.
     */
    important: '#fluent_mail_app',

    content: [
        './resources/admin/**/*.{vue,js}',
        './app/views/**/*.php'
    ],

    corePlugins: {
        // WordPress supplies its own base styles; resetting them breaks wp-admin.
        preflight: false
    },

    theme: {
        extend: {
            colors: { ...colors, ...themed },
            borderRadius: borderRadius,
            borderWidth: {
                '0.5': '.5px'
            },
            screens: {
                '1xl': '1360px'
            }
        },
        spacing: spacing,
        fontSize: fontSize
    },

    plugins: []
};
