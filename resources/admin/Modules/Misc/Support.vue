<template>
    <div class="fss_support">
        <el-row :gutter="20">
            <el-col :sm="24" :md="12">
                <div class="fss_about">
                    <div class="fss_header">About</div>
                    <div class="fss_content">
                        <p>
                            <a :href="appVars.plugin_url" target="_blank" rel="noopener">FluentSMTP</a> <span> </span>{{ $t('__ABOUT_INTRO') }}
                        </p>
                        <p>
                            {{ $t('__ABOUT_BY') }}
                        </p>
                        <div>
                            <p>{{ $t('FluentSMTP is built using the following open-source libraries and software') }}</p>
                            <ul style="list-style: disc;margin-left: 30px;">
                                <li>VueJS</li>
                                <li>ChartJS</li>
                                <li>Lodash</li>
                                <li>WordPress API</li>
                            </ul>
                            <p>
                                {{ $t('If you find an issue or have a suggestion please ') }}
                                <a target="_blank" rel="nofollow" href="https://github.com/WPManageNinja/fluent-smtp/issues">
                                    {{ $t('open an issue on GitHub') }}
                                </a>.
                                <br/>
                                <p v-html="$t('__GIT_CONTRIBUTE')"></p>
                            </p>
                            <p>{{ $t('Please ') }}<a target="_blank" rel="noopener" href="http://fluentsmtp.com/docs">{{ $t('read the documentation here') }}</a></p>
                        </div>
                    </div>
                </div>
                <div class="fss_about">
                    <div class="fss_header">{{ $t('Contributors') }}</div>
                    <div class="fss_content">
                        <p>{{ $t('__ABOUT_POWERED') }}</p>

                        <el-skeleton :rows="4" :animated="true" v-if="contributorsLoading" />

                        <ul v-if="contributors.length > 0" v-loading="contributorsLoading" class="fss_contributors">
                            <li v-for="contributor in contributors" :key="contributor.id">
                                <a
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    :href="contributor.html_url"
                                    :title="contributor.login"
                                >
                                    <img
                                        :src="contributor.avatar_url"
                                        :alt="contributor.login"
                                        loading="lazy"
                                    />
                                    <span>{{ contributor.login }}</span>
                                </a>
                            </li>
                        </ul>
                        <div v-else-if="!contributorsLoading && !contributors.length" style="text-align: center;">
                            <a target="_blank" rel="noopener noreferrer" href="https://github.com/WPManageNinja/fluent-smtp/graphs/contributors">
                                <img title="Contributors" :src="appVars.images_url + 'contributors.png'"/>
                            </a>
                        </div>

                        <p v-if="contributors.length > 0" class="fss_contributors_all">
                            <a target="_blank" rel="noopener noreferrer" href="https://github.com/WPManageNinja/fluent-smtp/graphs/contributors">
                                {{ $t('View all contributors on GitHub') }}
                            </a>
                        </p>

                    </div>
                </div>
            </el-col>
            <el-col :sm="24" :md="12">
                <div v-if="plugin || installed_info">
                    <div v-loading="installing" :element-loading-text="$t('Installing... Please wait')" class="fss_about">
                        <div class="fss_header">{{ $t('Recommended Plugin') }}</div>
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
                    <div class="fss_header">{{ $t('Community') }}</div>
                    <div class="fss_content">
                        <p>{{ $t('__ABOUT_COMMUNITY') }}</p>
                        <p>{{ $t('__ABOUT_JOIN') }}</p>
                        <ul style="list-style: disc;margin-left: 30px;">
                            <li>
                                <a target="_blank" rel="nofollow" href="https://www.facebook.com/groups/fluentforms">{{ $t('Join FluentForms Facebook Community') }}</a>
                            </li>
                            <li>
                                <a target="_blank" rel="nofollow" href="https://www.facebook.com/groups/fluentcrm">{{ $t('Join FluentCRM Facebook Community') }}</a>
                            </li>
                            <li>
                                <a target="_blank" rel="nofollow"
                                   href="https://wordpress.org/support/plugin/fluent-smtp/reviews/?filter=5">{{ $t('Write a review (really appreciated 😊)') }}</a>
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
                    description: `<p><a href="https://wordpress.org/plugins/fluentform" target="_blank" rel="nofollow">Fluent Forms</a><span> </span> ${ this.$t('__FF_DESC')}</p>`,
                    btn_text: this.$t('Install Fluent Forms (Free)'),
                    btn_class: '',
                    plugin_url: 'https://wordpress.org/plugins/fluentform'
                },
                fluent_crm: {
                    slug: 'fluent-crm',
                    title: 'FluentCRM',
                    subtitle: this.$t('Email Marketing Automation and CRM Plugin for WordPress'),
                    description: `<p><a href="https://wordpress.org/plugins/fluent-crm/" target="_blank" rel="nofollow">FluentCRM</a> ${this.$t('__FC_DESC')}</p>`,
                    btn_text: this.$t('Install FluentCRM (Free)'),
                    btn_class: 'fss_fluentcrm_btn',
                    plugin_url: 'https://wordpress.org/plugins/fluent-crm/'
                },
                ninja_tables: {
                    slug: 'ninja-tables',
                    title: 'Ninja Tables',
                    subtitle: this.$t('Best WP DataTables Plugin for WordPress'),
                    description: `<p>${ this.$t('__NT_DESC')}</p><p>${ this.$t('Meet ')}<a href="https://wordpress.org/plugins/ninja-tables/" target="_blank" rel="nofollow">Ninja Tables</a>,${ this.$t('__NT_DESC_EXT')}</p>`,
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
                /*
                 * per_page is explicit because the API returns 30 by default,
                 * which silently truncated the list as the project grew.
                 * A rate-limited or errored response is an object rather than
                 * an array, so the shape is checked before it is used - that
                 * case falls through to the static contributors image.
                 */
                await fetch('https://api.github.com/repos/WPManageNinja/fluent-smtp/contributors?per_page=100')
                    .then(response => response.json())
                    .then(data => {
                        this.contributors = Array.isArray(data) ? data : [];
                        this.contributorsLoading = false;
                    })
            } catch (e) {
                this.contributors = [];
                this.contributorsLoading = false;
            }
        }
    }
}
</script>

<style lang="scss" scoped>
.fss_contributors {
    list-style: none;
    display: flex;
    flex-wrap: wrap;
    gap: 14px 10px;
    margin: 16px 0 0;
    padding: 0;

    li {
        margin: 0;
        /* wide enough that most GitHub logins sit on one line */
        width: 88px;
    }

    a {
        display: block;
        text-align: center;
        text-decoration: none;
        color: #5a5a5a;

        &:hover {
            color: #1a7efb;

            img {
                border-color: #1a7efb;
            }
        }
    }

    img {
        width: 54px;
        height: 54px;
        border-radius: 50%;
        display: block;
        margin: 0 auto 6px;
        border: 2px solid transparent;
        transition: border-color .15s ease-in-out;
    }

    span {
        display: block;
        font-size: 11px;
        line-height: 1.3;
        /* logins are a single unbroken token and several are long */
        overflow-wrap: anywhere;
    }
}

.fss_contributors_all {
    margin-top: 14px;
    font-size: 13px;
}
</style>
