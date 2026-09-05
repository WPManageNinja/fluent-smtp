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
                    <el-button @click="skip_recommended = true" type="info">{{ $t('Skip') }}</el-button>
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
            <!--
                The greeting is FluentCart's, kept deliberately close - same avatar,
                same "Good <part of day> <name>", a one-line subtitle under it - because
                the two dashboards are the first screen of the same admin and a user
                moving between them should not be met by two different openings. The
                subtitle says what the screen is about rather than "Welcome to <site>":
                the admin already knows which site they are on, and the line reads as
                filler when it tells them.

                What is not copied is FluentCart's "Last 30 Days" line and its CTA. The
                range here is chosen on the chart below rather than fixed, so a label
                stating one would be wrong half the time; and the action a new install
                needs is the connection wizard, which this screen already shows instead
                of the dashboard when there is nothing connected.
            -->
            <div class="fsm_greeting">
                <img class="fsm_greeting_avatar" :src="appVars.user_avatar" alt=""
                     width="48" height="48"/>
                <div class="fsm_greeting_text">
                    <h2>{{ greeting }} {{ appVars.user_display_name }} 👋</h2>
                    <p>{{ $t("Here's how your site's email sending is doing.") }}</p>
                </div>
            </div>

            <div class="fsm_split">
                <div class="fsm_split_main">
                    <!--
                        The four headline numbers, across the top of the column rather
                        than stacked two-by-two in the aside. They are the first thing
                        the screen is read for, and a full-width row is what FluentCart
                        gives them. Sent and Failed are the pair an admin is actually
                        here to check, so they lead; Failed carries a link into the log
                        filtered to failures, which is the next thing you want when the
                        number is not zero.
                    -->
                    <el-alert v-if="load_error" type="error" :closable="false"
                              class="fsm_load_error" show-icon>
                        <p>{{ load_error }}</p>
                        <el-button size="small" @click="fetch()">{{ $t('Retry') }}</el-button>
                    </el-alert>

                    <div v-if="!loading && !load_error" class="fsm_tiles">
                        <div v-if="settings_stat.log_enabled == 'yes'" class="fsm_tile is_sent">
                            <span class="fsm_tile_icon"><el-icon><FsmIconSPromotion/></el-icon></span>
                            <span class="fsm_tile_label">{{ $t('Emails sent') }}</span>
                            <span class="fsm_tile_value">{{ stats.sent }}</span>
                        </div>

                        <!--
                            `status`, which is the query the log screen reads in created()
                            and writes back on every fetch. This used to link with
                            `filterBy`/`filterValue`, a pair nothing on that screen has
                            read since before the redesign - so clicking the failed count
                            opened the log with no filter on it at all.
                        -->
                        <router-link
                            class="fsm_tile is_failed"
                            :to="{ name: 'logs', query: { status: 'failed' } }">
                            <span class="fsm_tile_icon"><el-icon><FsmIconWarning/></el-icon></span>
                            <span class="fsm_tile_label">{{ $t('Emails failed') }}</span>
                            <span class="fsm_tile_value">{{ stats.failed || 0 }}</span>
                        </router-link>

                        <div class="fsm_tile is_connections">
                            <span class="fsm_tile_icon"><el-icon><FsmIconLink/></el-icon></span>
                            <span class="fsm_tile_label">{{ $t('Active connections') }}</span>
                            <span class="fsm_tile_value">{{ settings_stat.connection_counts }}</span>
                        </div>

                        <div class="fsm_tile is_senders">
                            <span class="fsm_tile_icon"><el-icon><FsmIconUser/></el-icon></span>
                            <span class="fsm_tile_label">{{ $t('Active senders') }}</span>
                            <span class="fsm_tile_value">{{ settings_stat.active_senders }}</span>
                        </div>
                    </div>

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
                                    :range-separator="$t('to')"
                                    :start-placeholder="$t('Start date')"
                                    :end-placeholder="$t('End date')"
                                    value-format="YYYY-MM-DD"
                                ></el-date-picker>
                                <el-button size="small" @click="filterReport" type="primary" plain>
                                    {{ $t('Apply') }}
                                </el-button>
                                <chart-type-toggle/>
                            </div>
                        </div>
                        <div class="fsm_card_body">
                            <emails-chart v-if="showing_chart" :date_range="date_range"/>
                        </div>
                    </div>

                    <ByDayTimeSending/>
                </div>

                <div class="fsm_split_aside">
                    <alerts-card/>

                    <div class="fsm_card">
                        <div class="fsm_card_head">
                            <h3>{{ $t('Email Logs') }}</h3>
                        </div>

                        <div class="fsm_card_body" v-if="!loading">
                            <ul class="fsm_fact_list">
                                <li>
                                    <span>{{ $t('Logging') }}</span>
                                    <span>{{ settings_stat.log_enabled == 'yes' ? $t('On') : $t('Off') }}</span>
                                </li>
                                <li v-if="settings_stat.log_enabled == 'yes'">
                                    <span>{{ $t('Kept for') }}</span>
                                    <span>{{ $t('{days} days', {days: settings_stat.auto_delete_days}) }}</span>
                                </li>
                            </ul>
                        </div>

                        <el-skeleton v-else class="fsm_card_body" :rows="3"></el-skeleton>
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

                    <!--
                        Last in the column. The three cards above it answer a question
                        each - is anything broken, is anything being logged, is this
                        install signed up - and are the same height every time the screen
                        loads. The activity list is the one card whose height depends on
                        what happened, so anything after it would move down the page as
                        the site sends mail; nothing is after it.
                    -->
                    <recent-activity v-if="settings_stat.log_enabled == 'yes'"/>
                </div>
            </div>
        </div>
    </div>
</template>

<script type="text/babel">
import isEmpty from 'lodash/isEmpty';
import ConnectionWizard from '../Settings/ConnectionWizard';
import EmailsChart from './Charts/Emails';
import ChartTypeToggle from './Charts/ChartTypeToggle.vue';
import EmailSubscriber from '../../Pieces/_Subscribe';
import SubscribeDismiss from '../../Pieces/_SubscribeDismiss';
import ByDayTimeSending from "./Charts/ByDayTimeSending.vue";
import RecentActivity from "./RecentActivity.vue";
import AlertsCard from "./AlertsCard.vue";

export default {
    name: 'Dashboard',
    components: {
        ConnectionWizard,
        EmailsChart,
        ChartTypeToggle,
        EmailSubscriber,
        SubscribeDismiss,
        ByDayTimeSending,
        RecentActivity,
        AlertsCard
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
                { text: this.$t('Last 7 Days'), value: () => this.daysAgoRange(7) },
                { text: this.$t('Last 30 Days'), value: () => this.daysAgoRange(30) },
                { text: this.$t('Last 90 Days'), value: () => this.daysAgoRange(90) }
            ],
            loading: true,
            load_error: '',
            skip_recommended: false
        };
    },
    computed: {
        /*
         * Read off the browser's clock rather than the site's. The greeting is about
         * where the person reading it is, not where the server is - an admin in Dhaka
         * looking at a site hosted in Frankfurt is having an afternoon regardless.
         */
        greeting() {
            const hour = new Date().getHours();

            if (hour < 12) {
                return this.$t('Good morning');
            }

            if (hour < 18) {
                return this.$t('Good afternoon');
            }

            return this.$t('Good evening');
        },
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
        /*
         * The site's clock, matching the log filter. This screen kept the browser's,
         * so the chart's own range shortcuts disagreed with the log screen's by a day
         * whenever the two clocks were on different sides of midnight.
         */
        daysAgoRange(days) {
            return [this.$siteCalendarDate(days), this.$siteCalendarDate(0)];
        },
        disabledDate(date) {
            return date.getTime() > this.$siteCalendarDate(0).getTime();
        },
        fetch() {
            this.loading = true;
            this.load_error = '';
            this.$get('/').then(res => {
                this.stats = res.stats;
                this.settings_stat = res.settings_stat;
                this.unhealthy_settings = res.unhealthy_settings || [];
            }).fail(error => {
                /*
                 * The tiles are hidden rather than left at their initial values.
                 *
                 * `stats` starts empty, so a failed request used to render "Email
                 * Failed: 0" - an affirmative claim that nothing had bounced, on the
                 * screen an admin opens specifically to check that. Zero is a real
                 * answer here and must only ever come from the server.
                 */
                this.load_error = this.$errorMessage(error);
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
