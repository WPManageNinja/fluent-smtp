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
            <div class="fsm_split">
                <div class="fsm_split_main">
                    <div class="fsm_card">
                        <div class="fsm_card_head">
                            <h3>{{ $t('Sending Stats') }}</h3>
                            <div class="fsm_card_head_actions">
                                <el-date-picker
                                    size="small"
                                    v-model="date_range"
                                    type="daterange"
                                    :shortcuts="shortcuts"
                                    :disabled-date="disabledDate"
                                    :range-separator="$t('To')"
                                    :start-placeholder="$t('Start date')"
                                    :end-placeholder="$t('End date')"
                                    value-format="YYYY-MM-DD"
                                ></el-date-picker>
                                <el-button size="small" @click="filterReport" type="primary" plain>
                                    {{ $t('Apply') }}
                                </el-button>
                            </div>
                        </div>
                        <div class="fsm_card_body">
                            <emails-chart v-if="showing_chart" :date_range="date_range"/>
                        </div>
                    </div>

                    <ByDayTimeSending/>
                </div>

                <div class="fsm_split_aside">
                    <div class="fsm_card">
                        <div class="fsm_card_head">
                            <h3>{{ $t('Quick Overview') }}</h3>
                        </div>

                        <div class="fsm_card_body" v-if="!loading">
                            <!--
                                The four headline numbers, as tiles rather than as rows in
                                a list with a floated count. Sent and Failed are the pair
                                an admin is actually here to check, so they lead; Failed
                                carries a link into the log filtered to failures, which is
                                the next thing you want when the number is not zero.
                            -->
                            <div class="fsm_aside_block">
                                <div class="fsm_tiles">
                                    <div v-if="settings_stat.log_enabled == 'yes'"
                                         class="fsm_tile is_sent">
                                        <span class="fsm_tile_icon"><el-icon><FsmIconSPromotion/></el-icon></span>
                                        <span class="fsm_tile_label">{{ stripColon($t('Total Email Sent (Logged):')) }}</span>
                                        <span class="fsm_tile_value">{{ stats.sent }}</span>
                                    </div>

                                    <router-link
                                        class="fsm_tile is_failed"
                                        :to="{ name: 'logs', query: { filterBy: 'status', filterValue: 'failed' } }">
                                        <span class="fsm_tile_icon"><el-icon><FsmIconWarning/></el-icon></span>
                                        <span class="fsm_tile_label">{{ stripColon($t('Email Failed:')) }}</span>
                                        <span class="fsm_tile_value">{{ stats.failed || 0 }}</span>
                                    </router-link>

                                    <div class="fsm_tile is_connections">
                                        <span class="fsm_tile_icon"><el-icon><FsmIconLink/></el-icon></span>
                                        <span class="fsm_tile_label">{{ stripColon($t('Active Connections:')) }}</span>
                                        <span class="fsm_tile_value">{{ settings_stat.connection_counts }}</span>
                                    </div>

                                    <div class="fsm_tile is_senders">
                                        <span class="fsm_tile_icon"><el-icon><FsmIconUser/></el-icon></span>
                                        <span class="fsm_tile_label">{{ stripColon($t('Active Senders:')) }}</span>
                                        <span class="fsm_tile_value">{{ settings_stat.active_senders }}</span>
                                    </div>
                                </div>
                            </div>

                            <div class="fsm_aside_block">
                                <h3>{{ $t('Email Logs') }}</h3>
                                <ul class="fsm_fact_list">
                                    <li>
                                        <span>{{ $t('Save Email Logs:') }}</span>
                                        <span style="text-transform: capitalize;">{{ settings_stat.log_enabled }}</span>
                                    </li>
                                    <li v-if="settings_stat.log_enabled == 'yes'">
                                        <span>{{ $t('Delete Logs:') }}</span>
                                        <span>{{ $t('After') }} {{ settings_stat.auto_delete_days }} {{ $t('Days') }}</span>
                                    </li>
                                </ul>
                            </div>
                        </div>

                        <el-skeleton v-else class="fsm_card_body" :rows="8"></el-skeleton>
                    </div>

                    <div v-if="appVars.require_optin == 'yes' && stats.sent > 9" class="fsm_card">
                        <div class="fsm_card_head">
                            <h3>{{ $t('Subscribe To Updates') }}</h3>
                            <div class="fsm_card_head_actions">
                                <subscribe-dismiss/>
                            </div>
                        </div>
                        <div class="fsm_card_body">
                            <email-subscriber/>
                        </div>
                    </div>
                </div>
            </div>
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
         * The overview's labels were written as list rows - "Active Senders:" - and the
         * colon is part of the translated string, so every locale already carries it. A
         * tile's label does not introduce a value that follows it, so the colon comes off
         * here rather than by rewording the keys, which would drop the translations too.
         *
         * It takes the already-translated text rather than the key on purpose:
         * translation.node.js finds strings by scanning the source for $t call sites, so
         * hiding the call inside a helper would drop all four from TransStrings.php.
         * (Writing one out in this comment adds it to the file, which is how that was
         * found - the extractor does not know a comment from code.)
         */
        stripColon(text) {
            return (text || '').replace(/\s*[:\uFF1A]\s*$/, '');
        },
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
