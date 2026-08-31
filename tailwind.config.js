/** @type {import('tailwindcss').Config} */

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

    theme: {},

    plugins: []
};
