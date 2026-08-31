<template>
    <div class="dashboard">
        <div v-if="is_new" class="fss_content">
            <div class="fss_connection_intro">
                <div class="fss_intro">
                    <h1>{{ $t('__wizard_title') }}</h1>
                    <p>{{ $t('__wizard_sub') }}</p>
                </div>

                <div v-if="recommended && !skip_recommended" class="fsmtp_recommended">
                    <h2>{{ recommended.title }}</h2>
                    <p>{{ recommended.subtitle }}</p>
                    <el-button @click="setRecommendation()" type="primary">{{ recommended.button_text }}</el-button>
                    <el-button @click="skip_recommended = true" type="info">Skip</el-button>
                </div>
                <template v-else>
                    <h2>{{ $t('__wizard_instruction') }}</h2>
                    <connection-wizard
                        :connection="new_connection"
                        :is_new="true"
                        :connection_key="false"
                        :providers="settings.providers">
                    </connection-wizard>
                </template>
            </div>
        </div>
        <div v-else>
            <el-alert
                v-for="connection in unhealthy_settings"
                :key="connection.sender_email"
                type="error"
                :closable="false"
                show-icon
                style="margin-bottom: 15px;"
                :title="$t('Connection needs attention') + ': ' + connection.sender_email + ' (' + connection.provider + ')'"
                :description="connection.message"
            />
            <el-row :gutter="20">
                <el-col :sm="24" :md="16">
                    <div class="fss_dashboard_widget">
                        <div class="fss_header fss_widget_header">
                            <h3>{{ $t('Sending Stats') }}</h3>
                            <div class="fss_to_right">
                                <el-date-picker
                                    size="small"
                                    v-model="date_range"
                                    type="daterange"
                                    :shortcuts="shortcuts"
                                    :disabled-date="disabledDate"
                                    range-separator="To"
                                    :start-placeholder="$t('Start date')"
                                    :end-placeholder="$t('End date')"
                                    value-format="YYYY-MM-DD"
                                ></el-date-picker>
                                <el-button style="padding: 8px 15px;" size="small" @click="filterReport" type="primary" plain>Apply</el-button>
                            </div>
                        </div>
                        <div class="fss_content">
                            <emails-chart v-if="showing_chart" :date_range="date_range"/>
                        </div>
                    </div>
                    <div class="fss_dashboard_widget">
                        <ByDayTimeSending/>
                    </div>

                </el-col>
                <el-col :sm="24" :md="8">
                    <div class="fsm_card">
                        <div class="fss_header">
                            {{ $t('Quick Overview') }}
                        </div>
                        <div class="fss_content" v-if="!loading">
                            <ul class="fss_dash_lists">
                                <li v-if="settings_stat.log_enabled == 'yes'">
                                    {{ $t('Total Email Sent (Logged):') }} <span>{{ stats.sent }}</span>
                                </li>
                                <li style="color: var(--fsm-danger-fg)" v-if="stats.failed > 0">
                                    <router-link style="color: var(--fsm-danger-fg)"
                                                 :to="{ name: 'logs', query: { filterBy: 'status', filterValue: 'failed' } }">
                                        {{ $t('Email Failed:') }} <span>{{ stats.failed }}</span>
                                    </router-link>
                                </li>
                                <li>
                                    {{ $t('Active Connections:') }} <span>{{ settings_stat.connection_counts }}</span>
                                </li>
                                <li>
                                    {{ $t('Active Senders:') }} <span>{{ settings_stat.active_senders }}</span>
                                </li>
                                <li>
                                    {{ $t('Save Email Logs:') }}
                                    <span style="text-transform: capitalize;">
                                        {{ settings_stat.log_enabled }}
                                    </span>
                                </li>
                                <li v-if="settings_stat.log_enabled == 'yes'">
                                    {{ $t('Delete Logs:') }}
                                    <span>After {{ settings_stat.auto_delete_days }} {{ $t('Days') }}</span>
                                </li>
                            </ul>
                        </div>
                        <el-skeleton v-else class="fss_content" :rows="8"></el-skeleton>
                    </div>
                    <div v-if="appVars.require_optin == 'yes' && stats.sent > 9" style="margin-top: 20px;"
                         class="fsm_card">
                        <div class="fss_header">
                            {{ $t('Subscribe To Updates') }}
                            <span class="fss_header_action_right">
                                <subscribe-dismiss/>
                            </span>
                        </div>
                        <div class="fss_content">
                            <email-subscriber/>
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
import EmailSubscriber from '../../Pieces/_Subscribe';
import SubscribeDismiss from '../../Pieces/_SubscribeDismiss';
import ByDayTimeSending from "./Charts/ByDayTimeSending.vue";

export default {
    name: 'Dashboard',
    components: {
        ConnectionWizard,
        EmailsChart,
        EmailSubscriber,
        SubscribeDismiss,
        ByDayTimeSending
    },
    data() {
        return {
            stats: {},
            new_connection: {},
            settings_stat: {},
            unhealthy_settings: [],
            date_range: '',
            showing_chart: true,
            shortcuts: [
                { text: this.$t('Last week'), value: () => this.daysAgoRange(7) },
                { text: this.$t('Last month'), value: () => this.daysAgoRange(30) },
                { text: this.$t('Last 3 months'), value: () => this.daysAgoRange(90) }
            ],
            loading: true,
            skip_recommended: false
        };
    },
    computed: {
        is_new() {
            return isEmpty(this.settings.connections);
        },
        recommended() {
            if (!this.is_new) {
                return false;
            }
            return this.appVars.recommended;
        }
    },
    methods: {
        /*
         * Element Plus split Element UI's `picker-options` object into separate
         * :shortcuts and :disabled-date props, and changed a shortcut's shape:
         * it now returns the range as a `value`, where the old one reached into
         * the picker instance and did `picker.$emit('pick', ...)`.
         */
        daysAgoRange(days) {
            const end = new Date();
            const start = new Date();
            start.setTime(start.getTime() - 3600 * 1000 * 24 * days);
            return [start, end];
        },
        disabledDate(date) {
            return date.getTime() > Date.now();
        },
        fetch() {
            this.loading = true;
            this.$get('/').then(res => {
                this.stats = res.stats;
                this.settings_stat = res.settings_stat;
                this.unhealthy_settings = res.unhealthy_settings || [];
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
        },
        setRecommendation() {
            this.new_connection = JSON.parse(JSON.stringify(this.recommended.settings));
            this.skip_recommended = true;
        }
    },
    created() {
        this.fetch();
    }
};
</script>
