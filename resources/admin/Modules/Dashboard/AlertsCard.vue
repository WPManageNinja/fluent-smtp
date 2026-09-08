<template>
    <!--
        Alerts, on the dashboard.

        It used to be an item in the settings sidebar, which meant you set up failure
        alerts in the one place on the screen that never mentions a failure. It lives
        here now, beside the Email Failed count - the number that is the reason to want
        them. The screen itself is 1200 lines of channel setup, so this is its state and
        its door, not the thing inlined.
    -->
    <div class="fsm_card fsm_alerts_card">
        <div class="fsm_card_head">
            <h3>{{ $t('Alerts & Notifications') }}</h3>
            <div class="fsm_card_head_actions">
                <!--
                    "Set Up" is a claim that nothing is set up yet, which the card is in no
                    position to make when the requests that would have told it failed.
                -->
                <router-link :to="{name: 'notification_settings'}">
                    {{ configured || failed ? $t('Manage') : $t('Set Up') }}
                </router-link>
            </div>
        </div>

        <div class="fsm_card_body">
            <el-skeleton v-if="loading" :rows="2" animated/>

            <!--
                Not the fact list. Both facts default to off, so a failed request used to
                render "Off" against both of them - the card reporting that this site has
                no alerts and no summary when all it really knows is that it could not ask.
                That is the one wrong answer here an admin would act on.
            -->
            <p v-else-if="failed" class="fsm_alerts_hint">
                {{ $t('Could not load alert settings.') }}
                <el-button link @click="fetch">{{ $t('Retry') }}</el-button>
            </p>

            <template v-else>
                <ul class="fsm_fact_list">
                    <li>
                        <span>{{ $t('Failure Alerts') }}</span>
                        <span class="fsm_tag" :class="channels.length ? 'is_sent' : 'is_neutral'">
                            {{ channels.length ? channels.join(', ') : $t('Off') }}
                        </span>
                    </li>
                    <li>
                        <span>{{ $t('Summary Email') }}</span>
                        <span class="fsm_tag" :class="summary_on ? 'is_sent' : 'is_neutral'">
                            {{ summary_on ? $t('On') : $t('Off') }}
                        </span>
                    </li>
                </ul>

                <p v-if="!configured" class="fsm_alerts_hint">
                    {{ $t('__alerts_hint') }}
                </p>
            </template>
        </div>
    </div>
</template>

<script type="text/babel">
    export default {
        name: 'AlertsCard',
        data() {
            return {
                loading: true,
                failed: false,
                summary_on: false,
                channels: []
            }
        },
        computed: {
            configured() {
                return this.summary_on || this.channels.length > 0;
            }
        },
        methods: {
            /*
             * Two calls because the two answers live in two endpoints: the summary email
             * is part of the notification settings, the channels come back from the
             * channel list with their active state already resolved against
             * `active_channel`. Neither is worth a new endpoint for one card.
             */
            fetch() {
                this.loading = true;
                this.failed = false;

                const settings = this.$get('settings/notification-settings')
                    .then(res => {
                        this.summary_on = res.data.settings.enabled === 'yes';
                    });

                const channels = this.$get('settings/notification-channels')
                    .then(res => {
                        this.channels = Object.values(res.data.channels || {})
                            .filter(channel => channel.is_active)
                            .map(channel => channel.title);
                    });

                // Either one failing means the card does not have the answer, so it says
                // so rather than falling back to its own defaults and calling them facts.
                jQuery.when(settings, channels)
                    .fail(error => {
                        this.failed = true;
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
