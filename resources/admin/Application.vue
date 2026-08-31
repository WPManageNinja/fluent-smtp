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
                    this plugin, so it is a button in the bar rather than an item buried
                    inside Settings - even though the screen it opens is also listed in
                    the settings sidebar, because that is where it belongs in the IA.
                -->
                <el-button type="primary" size="small" class="fsm_app_bar_cta"
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
            Settings is a pinned pane with its own sidebar; everything else is an
            ordinary page under the bar. Which one a route gets is decided by
            `meta.active`, so no screen has to know about the chrome around it.
        -->
        <div v-if="isSettings" class="fsm_settings">
            <nav class="fsm_settings_nav" :aria-label="$t('Settings')">
                <div class="fsm_settings_nav_title">{{ $t('Settings') }}</div>
                <ul>
                    <li v-for="item in settingsNav" :key="item.route"
                        :class="{'is-active': $route.name === item.route}">
                        <router-link :to="{name: item.route}">{{ item.title }}</router-link>

                        <ul v-if="item.children && $route.name === item.route"
                            class="fsm_settings_subnav">
                            <li v-for="child in item.children" :key="child.target">
                                <a href="#" @click.prevent="scrollToSection(child.target)">
                                    {{ child.title }}
                                </a>
                            </li>
                        </ul>
                    </li>
                </ul>
            </nav>

            <div class="fsm_settings_body" ref="settingsBody">
                <div class="fsm_settings_content">
                    <router-view :key="$route.name"></router-view>
                </div>
            </div>
        </div>

        <div v-else class="fsm_page">
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
            isSettings() {
                return this.$route.meta.active === 'settings';
            },
            /*
             * Everything configurable, in one place.
             *
             * The old bar listed Settings, Email Test, Alerts and About as peers of
             * Dashboard and Email Logs - three things you go to *change*, presented
             * alongside the two you go to *look at*. These are those three, plus About,
             * gathered behind one door.
             *
             * Every route here already existed. This is a change to where a link appears
             * in the chrome, not to what any path resolves to - see routes.js.
             */
            settingsNav() {
                return [
                    {
                        route: 'connections',
                        title: this.$t('Connections'),
                        /*
                         * The General Settings panel lives on the same screen. It gets a
                         * name of its own here because it is a separate subject, and an
                         * anchor rather than a route of its own because splitting that
                         * screen in two is Phase 5's job, not the shell's.
                         */
                        children: [
                            {title: this.$t('General'), target: '.fsm_general_settings'}
                        ]
                    },
                    {route: 'notification_settings', title: this.$t('Alerts & Notifications')},
                    {route: 'test', title: this.$t('Email Test')},
                    {route: 'support', title: this.$t('About')}
                ];
            }
        },
        watch: {
            $route(to) {
                this.navOpen = false;
                this.syncSettingsClass();

                if (to.meta.title) {
                    document.title = this.$t(to.meta.title) + ' - FluentSMTP';
                }
            }
        },
        methods: {
            /*
             * Only destinations - the places you go to look at something. Kept behind
             * the `fluent_mail_top_menus` filter it has always been behind, so an add-on
             * that called registerTopMenu() still lands its item in the bar.
             */
            defaultRoutes() {
                return [
                    {route: 'dashboard', title: this.$t('Dashboard'), match: 'dashboard'},
                    {route: 'logs', title: this.$t('Email Logs'), match: 'logs'},
                    {route: 'connections', title: this.$t('Settings'), match: 'settings'}
                ];
            },
            setMenus() {
                this.items = this.applyFilters('fluent_mail_top_menus', this.defaultRoutes());
            },
            /*
             * `match` is what keeps Settings lit while any of the screens behind it is
             * open. An item registered by an add-on has no `match`, so it falls back to
             * being active only on its own route.
             */
            isActive(item) {
                const active = this.$route.meta ? this.$route.meta.active : '';

                return item.match ? active === item.match : this.$route.name === item.route;
            },
            scrollToSection(selector) {
                const pane = this.$refs.settingsBody;
                const target = pane ? pane.querySelector(selector) : null;

                if (target) {
                    target.scrollIntoView({behavior: 'smooth', block: 'start'});
                }
            },
            /*
             * The settings pane is pinned to the viewport, which puts it over the strip
             * wp-admin reserves for its own footer. That strip has to be reclaimed or the
             * pane comes up 65px short - but only while the pane is on screen, so the
             * plugin's footer credit survives on every other screen. #wpfooter is outside
             * the app root, so the class goes on <body> rather than on the shell.
             */
            syncSettingsClass() {
                document.body.classList.toggle('fsm_settings_open', this.isSettings);
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
            this.syncSettingsClass();

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
            document.body.classList.remove('fsm_settings_open');

            window.removeEventListener('scroll', this.onScroll);
            window.removeEventListener('resize', this.measureShell);

            if (this.shellObserver) {
                this.shellObserver.disconnect();
            }
        }
    };
</script>
