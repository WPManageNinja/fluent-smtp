<template>
    <div class="dashboard">
        <div class="header">
            Dashboard
        </div>
        <div class="content">
            <div v-if="is_new" class="fss_connection_intro">
                <div class="fss_intro">
                    <h1>Welcome to FluentSMTP & SES</h1>
                    <p>Thank you for installing FluentSMTP - The ultimate SMTP & Email Service Connection Plugin for WordPress</p>
                </div>
                <h2>Please configure your first email service provider connection</h2>
                <connection-wizard
                    :connection="new_connection"
                    :is_new="true"
                    :connection_key="false"
                    :providers="settings.providers">
                </connection-wizard>
            </div>
            <div v-else>
                <h1>Dashboard Data will be shown here</h1>
            </div>
        </div>
    </div>
</template>

<script type="text/babel">
    import isEmpty from 'lodash/isEmpty';
    import ConnectionWizard from '../Settings/ConnectionWizard';

    export default {
        name: 'Dashboard',
        components: {
            ConnectionWizard
        },
        data() {
            return {
                stats: [],
                new_connection: {}
            };
        },
        computed: {
            is_new() {
                return isEmpty(this.settings.connections);
            }
        },
        methods: {
            fetch() {
                this.loading = true;
                this.$get('/').then(res => {
                    this.stats = res.stats;
                }).fail(error => {
                    console.log(error);
                }).always(() => {
                    this.loading = false;
                });
            },
            gotoSettings(mailer) {
                this.$router.push({
                    name: 'settings',
                    query: {
                        name: mailer.key
                    }
                });
            },
            gotoLogs(key) {
                if (Number(this.stats[key]) === 0) return;
                const val = key === 'successful' ? 'sent' : (key === 'unsuccessful' ? 'failed' : 'resent');
                this.$router.push({
                    name: 'logs',
                    query: {
                        page: 1,
                        filterBy: 'status',
                        filterValue: val
                    }
                });
            }
        },
        created() {
            this.fetch();
        }
    };
</script>

<style>
    .fluent-mail-app .dashboard .content .mailer-block {
        display: inline-block;
        width: 140px;
        margin: 0 5px;
        position: relative;
        /*float: left;*/
    }

    .fluent-mail-app .dashboard .content .fluent-mail-mailer-image {
        background: #fff;
        text-align: center;
        cursor: auto;
        border: 1px solid #ccc;
        box-shadow: 0 2px 12px 0 rgba(0,0,0,.1);
        border-radius: 4px;
        height: 76px;
        position: relative;
        margin-bottom: 10px;
        -webkit-transition: all 0.2s ease-in-out;
        -moz-transition: all 0.2s ease-in-out;
        -ms-transition: all 0.2s ease-in-out;
        transition: all 0.2s ease-in-out;
        cursor: pointer;
    }

    .fluent-mail-app .dashboard .content .fluent-mail-mailer-image:hover {
        box-shadow: 0 0 5px 5px #ccc;
    }

    .fluent-mail-app .dashboard .content .fluent-mail-mailer-image img {
        max-width: 90%;
        max-height: 40px;
        display: block;
        position: relative;
        top: 20px !important;
        left: 50%;
        transform: translate(-50%, -50%);
        opacity: 0.7;
        -webkit-transition: all 0.2s ease-in-out;
        -moz-transition: all 0.2s ease-in-out;
        -ms-transition: all 0.2s ease-in-out;
        transition: all 0.2s ease-in-out;
    }

    .fluent-mail-app .dashboard .content .email-stat {
        padding: 20px;
        font-weight: 700;
        font-size: 15px;
        font-weight: 500;
        cursor: pointer;
        text-align: center;
    }

    .fluent-mail-app .dashboard .content .email-stat:hover {
        background: #ecf5ff;
        opacity: .7;
    }
</style>
