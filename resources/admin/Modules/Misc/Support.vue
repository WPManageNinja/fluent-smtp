<template>
    <div class="fss_support">
        <el-row :gutter="20">
            <el-col :sm="24" :md="12">
                <div class="fss_about">
                    <div class="fss_header">About</div>
                    <div class="fss_content">
                        <p>
                            <a :href="appVars.plugin_url" target="_blank" rel="noopener">FluentSMTP</a>{{ $t(' is a free and opensource WordPress Plugin. Our mission is to provide the ultimate email delivery solution with your favorite Email sending service.FluentSMTP is built for performance and speed.') }}
                        </p>
                        <p>
                            {{ $t('FluentSMTP is free and will be always free.This is our pledge to WordPress community from WPManageNinja LLC.') }}
                        </p>
                        <div>
                            <p>{{ $t('FluentSMTP is built using the following opensorce libraries and softwares') }}</p>
                            <ul style="list-style: disc;margin-left: 30px;">
                                <li>VueJS</li>
                                <li>ChartJS</li>
                                <li>Lodash</li>
                                <li>WordPress API</li>
                            </ul>
                            <p>
                                {{ $t('If you find an issue or have a suggestion please ') }}<a target="_blank" rel="nofollow"
                                                                                    href="https://github.com/WPManageNinja/fluent-smtp/issues">{{
                                    $t('open an issue on GitHub')
                                }}</a>.
                                <br/>{{ $t('If you are a developer and would like to contribute to the project, Please ') }}<a
                                target="_blank" rel="nofollow" href="https://github.com/WPManageNinja/fluent-smtp/">{{ $t('contribute on GitHub') }}</a>.
                            </p>
                            <p>{{ $t('Please ') }}<a target="_blank" rel="noopener" href="http://fluentsmtp.com/docs">{{ $t('read the documentation here') }}</a></p>
                        </div>
                    </div>
                </div>
                <div class="fss_about">
                    <div class="fss_header">{{ $t('Contributors') }}</div>
                    <div class="fss_content">
                        <p>{{ $t('FluentSMTP is powered by it\'s users like you. Feel free to contribute on Github. Thanks to all of our contributors.') }}</p>

                        <el-skeleton :rows="4" :animated="true" v-if="contributorsLoading" />

                        <a target="_blank" href="https://github.com/WPManageNinja/fluent-smtp/graphs/contributors">

                            <ul v-if="contributors.length > 0" v-loading="contributorsLoading" style="list-style: none; display: flex; flex-direction: row; flex-wrap: wrap; ">
                                <li v-for="contributor in contributors" :key="contributor.id" class="" >
                                    <p :title="contributor.login">
                                        <img :src="contributor.avatar_url" :alt="contributor.login" style="width: 60px; height: 60px; border-radius: 50%;"/>
                                    </p>
                                </li>
                            </ul>
                            <div v-else-if="!contributorsLoading && !contributors.length" style="text-align: center;">
                                <img title="Contributors" :src="appVars.images_url + 'contributors.png'"/>
                            </div>
                        </a>

                    </div>
                </div>
            </el-col>
            <el-col :sm="24" :md="12">
                <div v-if="plugin || installed_info">
                    <div v-loading="installing" element-loading-text="Installing... Please wait" class="fss_about">
                        <div class="fss_header">Recommended Plugin</div>
                        <div class="fss_content">
                            <div v-if="installed_info" class="install_success">
                                <h3>{{ installed_message }}</h3>
                                <a class="el-button el-button--success installed_dashboard_url"
                                   :href="installed_info.admin_url">{{ installed_info.title }}</a>
                            </div>
                            <div v-else class="fss_plugin_block">
                                <div class="fss_plugin_title">
                                    <h3>{{ plugin.title }}</h3>
                                    <p>{{ plugin.subtitle }}</p>
                                </div>
                                <div class="fss_plugin_body">
                                    <div v-html="plugin.description"></div>
                                    <div class="fss_install_btn">
                                        <el-button v-if="!appVars.disable_installation" @click="installPlugin(plugin.slug)"
                                                   :class="plugin.btn_class" type="success">{{ plugin.btn_text }}
                                        </el-button>
                                        <a v-else :href="plugin.plugin_url" target="_blank" rel="noopener"
                                           class="el-button el-button--success fss_ninjatables_btn">
                                            <span>View {{ plugin.title }}</span>
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="fss_about">
                    <div class="fss_header">Community</div>
                    <div class="fss_content">
                        <p>{{ $t('FluentSMTP is powered by community.We listen to our community users and build products that add values to businesses and save time.') }}</p>
                        <p>{{ $t('Join our communities and participate in great conversations.') }}</p>
                        <ul style="list-style: disc;margin-left: 30px;">
                            <li>
                                <a target="_blank" rel="nofollow" href="https://www.facebook.com/groups/fluentforms">{{ $t('Join FluentForms Facebook Community') }}</a>
                            </li>
                            <li>
                                <a target="_blank" rel="nofollow" href="https://www.facebook.com/groups/fluentcrm">{{ $t('Join FluentCRM Facebook Community') }}</a>
                            </li>
                            <li>
                                <a target="_blank" rel="nofollow"
                                   href="https://wordpress.org/support/plugin/fluent-smtp/reviews/?filter=5">{{ $t('Write a review (really appreciate 😊)') }}</a>
                            </li>
                            <li>
                                <a target="_blank" rel="noopener" href="http://fluentsmtp.com/docs">{{ $t('Read the documentation') }}</a>
                            </li>
                        </ul>
                    </div>
                </div>
            </el-col>
        </el-row>
    </div>
</template>

<script type="text/babel">
import sample from 'lodash/sample';

export default {
    name: 'FluentMailSupport',
    data() {
        return {
            plugins: {
                fluentform: {
                    slug: 'fluentform',
                    title: 'Fluent Forms',
                    subtitle: this.$t('Fastest Contact Form Builder Plugin for WordPress'),
                    description: `<p><a href="https://wordpress.org/plugins/fluentform" target="_blank" rel="nofollow">Fluent Forms</a> ${ this.$t(' is the ultimate user-friendly, fast, customizable drag-and-drop WordPress Contact Form Plugin that offers you all the premium features, plus many more completely unique additional features.')}</p>`,
                    btn_text: this.$t('Install Fluent Forms (Free)'),
                    btn_class: '',
                    plugin_url: 'https://wordpress.org/plugins/fluentform'
                },
                fluent_crm: {
                    slug: 'fluent-crm',
                    title: 'FluentCRM',
                    subtitle: this.$t('Email Marketing Automation and CRM Plugin for WordPress'),
                    description: `<p><a href="https://wordpress.org/plugins/fluent-crm/" target="_blank" rel="nofollow">FluentCRM</a> ${this.$t(' is the best and complete feature-rich Email Marketing & CRM solution. It is also the simplest and fastest CRM and Marketing Plugin on WordPress. Manage your customer relationships, build your email lists, send email campaigns, build funnels, and make more profit and increase your conversion rates. (Yes, It’s Free!)')}</p>`,
                    btn_text: this.$t('Install FluentCRM (Free)'),
                    btn_class: 'fss_fluentcrm_btn',
                    plugin_url: 'https://wordpress.org/plugins/fluent-crm/'
                },
                ninja_tables: {
                    slug: 'ninja-tables',
                    title: 'Ninja Tables',
                    subtitle: this.$t('Best WP DataTables Plugin for WordPress'),
                    description: `<p>${ this.$t('Looking for a WordPress table plugin for your website? Then you’re in the right place.')}</p><p>${ this.$t('Meet ')}<a href="https://wordpress.org/plugins/ninja-tables/" target="_blank" rel="nofollow">Ninja Tables</a>,${ this.$t(' the best WP table plugin that comes with all the solutions to the problems you face while creating tables on your posts/pages.')}</p>`,
                    btn_text: this.$t('Install Ninja Tables (Free)'),
                    btn_class: 'fss_ninjatables_btn',
                    plugin_url: 'https://wordpress.org/plugins/ninja-tables/'
                }
            },
            installing: false,
            installed_info: false,
            installed_message: '',
            contributors: [],
            contributorsLoading: false
        }
    },
    mounted() {
        this.fetchContributors();
    },
    computed: {
        plugin() {
            if (this.appVars.disable_recommendation) {
                return false;
            }
            const recommended = [];
            if (!this.appVars.has_fluentform) {
                recommended.push(this.plugins.fluentform)
            }
            if (!this.appVars.has_ninja_tables) {
                recommended.push(this.plugins.ninja_tables)
            }
            if (!this.appVars.has_fluentcrm) {
                recommended.push(this.plugins.fluent_crm)
            }
            if (!recommended.length) {
                return false;
            }
            return sample(recommended);
        }
    },
    methods: {
        installPlugin(slug) {
            this.installing = true;
            this.$post('install_plugin', {
                plugin_slug: slug
            })
                .then(response => {
                    this.installed_info = response.info;
                    this.installed_message = response.message;
                })
                .fail((error) => {
                    this.$notify.error(error.responseJSON.data.message);
                    alert(error.responseJSON.data.message);
                })
                .always(() => {
                    this.installing = false;
                });
        },
        async fetchContributors() {
            this.contributorsLoading = true;
            try {
                await fetch('https://api.github.com/repos/WPManageNinja/fluent-smtp/contributors')
                    .then(response => response.json())
                    .then(data => {
                        this.contributors = data.slice(0, 20);
                        this.contributorsLoading = false;
                    })
            } catch (e) {
                this.contributorsLoading = false;
            }
        }
    }
}
</script>
