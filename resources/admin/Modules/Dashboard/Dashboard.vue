<template>
    <div class="dashboard">
        <div v-if="is_new" class="content">
            <div class="fss_connection_intro">
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
        </div>
        <div v-else>
            <el-row :gutter="20">
                <el-col :sm="24" :md="16">
                    <div class="header">
                        Sending Stats
                        <span class="fss_to_right">
                            <el-date-picker
                                size="small"
                                v-model="date_range"
                                type="daterange"
                                :picker-options="pickerOptions"
                                range-separator="To"
                                start-placeholder="Start date"
                                end-placeholder="End date"
                                value-format="yyyy-MM-dd"
                            ></el-date-picker>
                            <el-button size="small" @click="filterReport" type="primary" plain>Apply</el-button>
                        </span>
                    </div>
                    <div class="content">
                        <emails-chart v-if="showing_chart" :date_range="date_range" />
                    </div>
                </el-col>
                <el-col :sm="24" :md="8">
                    <div class="fsm_card">
                        <div class="header">
                            Quick Overview
                        </div>
                        <div class="content" v-if="!loading">
                            <ul class="fss_dash_lists">
                                <li v-if="settings_stat.log_enabled == 'yes'">
                                    Total Email Sent (Logged): <span>{{stats.sent}}</span>
                                </li>
                                <li style="color: red" v-if="stats.failed > 0">
                                    <router-link style="color: red"  :to="{ name: 'logs', query: { filterBy: 'status', filterValue: 'failed' } }">
                                        Email Failed: <span>{{stats.failed}}</span>
                                    </router-link>
                                </li>
                                <li>
                                    Active Connections: <span>{{settings_stat.connection_counts}}</span>
                                </li>
                                <li>
                                    Active Senders: <span>{{settings_stat.active_senders}}</span>
                                </li>
                                <li>
                                    Save Email Logs:
                                    <span style="text-transform: capitalize;">
                                        {{settings_stat.log_enabled}}
                                    </span>
                                </li>
                                <li v-if="settings_stat.log_enabled == 'yes'">
                                    Delete Logs:
                                    <span>After {{settings_stat.auto_delete_days}} Days</span>
                                </li>
                            </ul>
                        </div>
                        <el-skeleton v-else class="content" :rows="8"></el-skeleton>
                    </div>
                    <div v-if="appVars.require_optin == 'yes' && stats.sent > 9" style="margin-top: 20px;" class="fsm_card">
                        <div class="header">
                            Subscribe To Updates
                            <span class="header_action_right">
                                <subscribe-dismiss />
                            </span>
                        </div>
                        <div class="content">
                            <email-subscriber />
                        </div>
                    </div>
                </el-col>
            </el-row>
        </div>
    </div>
</template>

<script type="text/babel">
    import isEmpty from 'lodash/isEmpty';
    import ConnectionWizard from '../Settings/ConnectionWizard';
    import EmailsChart from './Charts/Emails';
    import EmailSubscriber from '../../Pieces/_Subscrbe';
    import SubscribeDismiss from '../../Pieces/_SubscribeDismiss';

    export default {
        name: 'Dashboard',
        components: {
            ConnectionWizard,
            EmailsChart,
            EmailSubscriber,
            SubscribeDismiss
        },
        data() {
            return {
                stats: {},
                new_connection: {},
                settings_stat: {},
                date_range: '',
                showing_chart: true,
                pickerOptions: {
                    disabledDate: function (date) {
                        const now = new Date();
                        return date > now;
                    },
                    shortcuts: [
                        {
                            text: 'Last week',
                            onClick(picker) {
                                const end = new Date();
                                const start = new Date();
                                start.setTime(start.getTime() - 3600 * 1000 * 24 * 7);
                                picker.$emit('pick', [start, end]);
                            }
                        },
                        {
                            text: 'Last month',
                            onClick(picker) {
                                const end = new Date();
                                const start = new Date();
                                start.setTime(start.getTime() - 3600 * 1000 * 24 * 30);
                                picker.$emit('pick', [start, end]);
                            }
                        },
                        {
                            text: 'Last 3 months',
                            onClick(picker) {
                                const end = new Date();
                                const start = new Date();
                                start.setTime(start.getTime() - 3600 * 1000 * 24 * 90);
                                picker.$emit('pick', [start, end]);
                            }
                        }
                    ]
                },
                loading: true
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
                    this.settings_stat = res.settings_stat;
                }).fail(error => {
                    console.log(error);
                }).always(() => {
                    this.loading = false;
                });
            },
            filterReport() {
                this.showing_chart = false;
                this.$nextTick(() => {
                    this.showing_chart = true;
                });
            }
        },
        created() {
            this.fetch();
        }
    };
</script>
