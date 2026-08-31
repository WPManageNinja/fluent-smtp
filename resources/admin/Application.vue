<template>
    <!--
        `.fluent-mail-app` stays on the shell root. tests/browser/admin-screen-smoke.mjs
        locates the app by it and asserts exactly one visible match on every screen.
    -->
    <div class="fluent-mail-app fsm_app">
        <div class="fsm_app_bar" :class="{'is-scrolled': scrolled}">
            <div class="fsm_app_logo">
                <router-link :to="{name: 'dashboard'}" :aria-label="$t('Dashboard')">
                    <!--
                        Two files rather than a filter. The wordmark is a pink mark beside
                        near-black lettering, so flattening and inverting it the way a
                        single-colour logo can be handled would lose the brand colour. The
                        plugin already ships a white build of it for exactly this.
                    -->
                    <img class="fsm_logo_light" :src="appVars.brand_logo" alt="FluentSMTP"/>
                    <img class="fsm_logo_dark" :src="appVars.images_url + 'fluentsmtp-white.png'"
                         alt="" aria-hidden="true"/>
                </router-link>
            </div>

            <button class="fsm_app_bar_toggle" type="button" @click="navOpen = !navOpen"
                    :aria-label="$t('Menu')" :aria-expanded="navOpen ? 'true' : 'false'">
                <span class="dashicons dashicons-menu-alt3"></span>
            </button>

            <ul class="fsm_app_nav" :class="{'is-open': navOpen}">
                <li v-for="item in items" :key="item.route">
                    <router-link :to="{name: item.route}"
                                 :class="{'router-link-active': isActive(item)}">
                        <span v-html="item.title"></span>
                    </router-link>
                </li>
            </ul>

            <div class="fsm_app_bar_actions">
                <!--
                    Sending a test email is the single most common thing an admin does on
                    this plugin, so it is a button in the bar rather than a screen buried
                    behind Settings, which is where it used to live.
                -->
                <el-button type="primary" size="small" class="fsm_app_bar_cta"
                           :class="{'is_current': onTestScreen}"
                           @click="$router.push({name: 'test'})">
                    {{ $t('Send Test Email') }}
                </el-button>

                <router-link :to="{name: 'docs'}" class="fsm_app_bar_help"
                             :title="$t('Documentation')" :aria-label="$t('Documentation')">
                    <el-icon><FsmIconInfo/></el-icon>
                </router-link>

                <theme-switch/>
            </div>
        </div>

        <!--
            One chrome for every screen. There used to be a second one - Settings was a
            pinned pane with a sidebar of its own, holding Connections, Alerts, Email
            Test and About - which meant the screen this plugin exists for was two
            clicks in. Settings is a destination in the bar now, reached in one, so the
            pane has nothing left to hold and every route is a page.
        -->
        <div class="fsm_page">
            <div class="fsm_page_inner">
                <router-view :key="$route.name"></router-view>
            </div>
        </div>
    </div>
</template>

<script type="text/babel">
    import ThemeSwitch from './Bits/ThemeSwitch.vue';

    export default {
        name: 'FluentMailApplication',
        components: {
            ThemeSwitch
        },
        data() {
            return {
                items: [],
                scrolled: false,
                navOpen: false
            }
        },
        computed: {
            /* Lit while the Email Test screen is open, since no nav item covers it. */
            onTestScreen() {
                return this.$route.name === 'test';
            }
        },
        watch: {
            $route(to) {
                this.navOpen = false;

                if (to.meta.title) {
                    document.title = this.$t(to.meta.title) + ' - FluentSMTP';
                }
            }
        },
        methods: {
            /*
             * Only destinations - the places you go to look at something. Settings is
             * the connections screen, under the name it has always had in this bar and
             * in the docs; its route stays `connections` because that is what is linked
             * from wp-admin and from the alerts the plugin has already sent. Alerts is
             * a destination of its own rather than a card on the dashboard to click
             * through: it is where a failing site gets told about, and something you go
             * to set up on purpose. The dashboard still links to it. About is last,
             * where a plugin's own page belongs - after everything the plugin is for.
             * Kept behind the `fluent_mail_top_menus` filter it has always been behind,
             * so an add-on that called registerTopMenu() still lands its item in the bar.
             */
            defaultRoutes() {
                return [
                    {route: 'dashboard', title: this.$t('Dashboard'), match: 'dashboard'},
                    {route: 'connections', title: this.$t('Settings'), match: 'connections'},
                    {route: 'logs', title: this.$t('Email Logs'), match: 'logs'},
                    {route: 'notification_settings', title: this.$t('Alerts'), match: 'alerts'},
                    {route: 'support', title: this.$t('About'), match: 'about'}
                ];
            },
            setMenus() {
                this.items = this.applyFilters('fluent_mail_top_menus', this.defaultRoutes());
            },
            /*
             * `match` is what keeps Settings lit while the add and edit screens
             * behind it are open. An item registered by an add-on has no `match`, so it
             * falls back to being active only on its own route.
             */
            isActive(item) {
                const active = this.$route.meta ? this.$route.meta.active : '';

                return item.match ? active === item.match : this.$route.name === item.route;
            },
            onScroll() {
                this.scrolled = window.scrollY > 10;
            },
            /**
             * Publishes the width of wp-admin's menu as a CSS variable.
             *
             * The app bar and the settings pane are pinned to the viewport, which means
             * they cannot inherit the page's left offset the way an in-flow element does
             * - they have to be told where the menu ends. Measuring it beats hard-coding
             * 160px: collapsing the menu, the automatic fold on a narrow window and the
             * off-canvas menu on a phone all land on different widths, and all of them
             * show up here.
             */
            measureShell() {
                const content = document.getElementById('wpcontent');
                const left = content ? content.getBoundingClientRect().left : 0;

                document.documentElement.style.setProperty('--fsm-shell-left', left + 'px');
            }
        },
        created() {
            jQuery('.update-nag,.notice:not(.fluentsmtp_urgent), #wpbody-content > .updated, #wpbody-content > .error').remove();
            this.setMenus();
        },
        mounted() {
            window.addEventListener('scroll', this.onScroll);
            this.onScroll();

            this.measureShell();

            /*
             * Folding the menu changes the width of #wpcontent, so watching its size
             * catches the fold, the automatic fold at narrow widths and an ordinary
             * window resize without listening for any of them by name.
             */
            const content = document.getElementById('wpcontent');

            if (content && window.ResizeObserver) {
                this.shellObserver = new ResizeObserver(this.measureShell);
                this.shellObserver.observe(content);
            } else {
                window.addEventListener('resize', this.measureShell);
            }
        },
        beforeUnmount() {
            window.removeEventListener('scroll', this.onScroll);
            window.removeEventListener('resize', this.measureShell);

            if (this.shellObserver) {
                this.shellObserver.disconnect();
            }
        }
    };
</script>
