<template>
    <div class="fluent-mail-app">
        <div class="fluent-mail-main-menu-items">
            <el-menu
                :router="true"
                mode="horizontal"
                class="fluent-mail-navigation"
                :default-active="active"
            >
                <el-menu-item index="dashboard" :route="{ name: 'dashboard' }">
                    <span v-html="logo"></span>
                </el-menu-item>

                <el-menu-item
                    :key="item.route"
                    :index="item.route"
                    v-for="item in items"
                    :route="{ name: item.route }"
                >
                    <span v-html="item.title"></span>
                </el-menu-item>
            </el-menu>

            <!--
                The theme control sits beside the menu for now. Phase 4 replaces this
                whole bar with the app bar from the redesign, where it moves to the
                right-hand actions group alongside Send Test Email and the help link.
            -->
            <div class="fluent-mail-menu-actions">
                <theme-switch/>
            </div>
        </div>

        <div class="fluent-mail-body">
            <router-view :key="$route.name"></router-view>
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
                logo: '',
                items: [],
                active: null
            }
        },
        watch: {
            '$route'(to, from) {
                if (this.$route.name) {
                    this.setActive();
                }
            }
        },
        methods: {
            defaultRoutes() {
                return [
                    {
                        route: 'connections',
                        title: this.$t('Settings')
                    },
                    {
                        route: 'test',
                        title: this.$t('Email Test')
                    },
                    {
                        route: 'logs',
                        title: this.$t('Email Logs')
                    },
                    {
                        route: 'notification_settings',
                        title: this.$t('Alerts')
                    },
                    {
                        route: 'support',
                        title: this.$t('About')
                    },
                    {
                        route: 'docs',
                        title: this.$t('Documentation')
                    }
                ];
            },
            setMenus() {
                this.items = this.applyFilters('fluent_mail_top_menus', this.defaultRoutes());
                this.setActive();
            },
            setActive() {
                this.active = this.$route.meta.parent || this.$route.name;
            }
        },
        computed: {
            brandLogo() {
                const src = this.appVars.brand_logo;
                return `<img style="width:140px;" src="${src}" />`;
            }
        },
        created() {
            jQuery('.update-nag,.notice:not(.fluentsmtp_urgent), #wpbody-content > .updated, #wpbody-content > .error').remove();
            this.logo = `<div class='logo'>${this.brandLogo}</div>`;
            this.setMenus();
        }
    };
</script>
