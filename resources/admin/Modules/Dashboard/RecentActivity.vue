<template>
    <div class="fsm_card fsm_activity">
        <div class="fsm_card_head">
            <h3>{{ $t('Recent Activity') }}</h3>
            <div class="fsm_card_head_actions">
                <router-link :to="{name: 'logs'}">{{ $t('View All') }}</router-link>
            </div>
        </div>

        <div class="fsm_card_body">
            <!--
                Four ranges rather than a date picker. The aside is a glance, not a
                report - the chart beside it is where a range gets chosen - so the
                filter is the three questions an admin actually asks of a log they
                are only passing over: today, yesterday, the last seven days, or
                everything.
            -->
            <div class="fsm_activity_filters" role="tablist">
                <button v-for="range in ranges" :key="range.key" type="button"
                        role="tab" :aria-selected="active === range.key ? 'true' : 'false'"
                        class="fsm_activity_filter"
                        :class="{'is_active': active === range.key}"
                        @click="select(range.key)">
                    {{ range.label }}
                </button>
            </div>

            <el-skeleton v-if="loading" :rows="4" animated/>

            <!--
                Its own state, ahead of the empty one. A request that never came back used
                to fall through to "No activity found.", which tells the admin the site
                sent nothing when what actually happened is that nobody knows.
            -->
            <p v-else-if="failed" class="fsm_activity_empty">
                {{ $t('Could not load recent activity.') }}
                <el-button link @click="fetch">{{ $t('Retry') }}</el-button>
            </p>

            <p v-else-if="!logs.length" class="fsm_activity_empty">
                {{ $t('No activity found.') }}
            </p>

            <ul v-else class="fsm_activity_list">
                <li v-for="log in logs" :key="log.id" class="fsm_activity_item"
                    :class="log.status === 'failed' ? 'is_failed' : 'is_sent'">
                    <span class="fsm_activity_icon">
                        <el-icon>
                            <FsmIconWarning v-if="log.status === 'failed'"/>
                            <FsmIconSPromotion v-else/>
                        </el-icon>
                    </span>
                    <div class="fsm_activity_body">
                        <span class="fsm_activity_subject">{{ log.subject || $t('(no subject)') }}</span>
                        <span class="fsm_activity_meta">{{ recipient(log) }}</span>
                        <span class="fsm_activity_time">{{ $dateFormat(log.created_at, 'DD MMM YYYY LT') }}</span>
                    </div>
                </li>
            </ul>
        </div>
    </div>
</template>

<script type="text/babel">

    export default {
        name: 'RecentActivity',
        data() {
            return {
                loading: true,
                failed: false,
                active: 'all',
                logs: []
            }
        },
        computed: {
            ranges() {
                return [
                    {key: 'all', label: this.$t('All')},
                    {key: 'today', label: this.$t('Today')},
                    {key: 'yesterday', label: this.$t('Yesterday')},
                    {key: 'week', label: this.$t('Last 7 Days')}
                ];
            }
        },
        methods: {
            select(key) {
                if (this.active === key) {
                    return;
                }
                this.active = key;
                this.fetch();
            },

            /*
             * The log's `date_range` filter is inclusive on both ends and takes plain
             * dates, so a single day is that day twice rather than a day and its
             * successor. `all` sends no range at all.
             */
            dateRange() {
                // The site's clock, not the browser's - the logs are filtered on the
                // site's, so an administrator abroad was asking for the wrong day.
                const today = this.$siteNow().format('YYYY-MM-DD');

                if (this.active === 'today') {
                    return [today, today];
                }

                if (this.active === 'yesterday') {
                    const yesterday = this.$siteNow().subtract(1, 'day').format('YYYY-MM-DD');
                    return [yesterday, yesterday];
                }

                // Six days back plus today is seven days, which is what the label says.
                // It used to say "This Week", which this is not: on a Tuesday it reaches
                // back into the previous calendar week.
                if (this.active === 'week') {
                    return [this.$siteNow().subtract(6, 'day').format('YYYY-MM-DD'), today];
                }

                return null;
            },

            /*
             * `to` is the raw [{name, email}] the logger stored, kept unescaped so every
             * consumer can decide how to render it (see Logger::formatResult). Here it is
             * one line in a narrow column, so it is the first address and a count of the
             * rest rather than a wrapped list.
             */
            recipient(log) {
                const to = Array.isArray(log.to) ? log.to : [];

                if (!to.length) {
                    return '';
                }

                const first = to[0].email || to[0].name || '';

                if (to.length === 1) {
                    return first;
                }

                return `${first} +${to.length - 1}`;
            },

            fetch() {
                this.loading = true;
                this.failed = false;

                const data = {per_page: 5, page: 1};
                const range = this.dateRange();

                if (range) {
                    data.date_range = range;
                }

                this.$get('logs', data)
                    .then(res => {
                        this.logs = res.data || [];
                    })
                    .fail(error => {
                        this.failed = true;
                        this.logs = [];
                    })
                    .always(() => {
                        this.loading = false;
                    });
            }
        },
        mounted() {
            this.fetch();
        }
    }
</script>
