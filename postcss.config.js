/*
 * Vite picks this up for every stylesheet it processes, including the <style>
 * blocks inside .vue files.
 *
 * Tailwind is a no-op until resources/scss/fluent-mail-admin.scss carries the
 * @tailwind directives; it is wired up here so the pipeline is in place before
 * the design system lands on top of it.
 */
module.exports = {
    plugins: {
        tailwindcss: {},
        autoprefixer: {}
    }
};
