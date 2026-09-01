<template>
    <div>
        <el-skeleton :animated="true" v-if="loading" :rows="3"></el-skeleton>
        <template v-else>
            <!--
                Gated on `receiver`, not on `status`.

                The two do not always agree: the endpoint can report a connected status
                while returning no receiver - which is precisely the half-broken
                connection this card exists to show - and the username line then threw
                on a null. Requiring the object it actually reads keeps the connected
                branch to the case where there is something to print.
            -->
            <div v-if="status == 'yes' && receiver" class="fss_alert_info">
                <p class="fss_alert_info__description" v-html="$t('__TELEGRAM_NOTIFICATION_ENABLED')">
                </p>
                <p class="fss_alert_info__details">{{ $t('Receiver\'s Telegram Username: ') }}@{{ receiver.username }}</p>
                <channel-actions
                    :channel_key="'telegram'"
                    :channel_title="channel_config.title || 'Telegram'"
                />
            </div>
            <div v-else class="fss_alert_info">
                <h3 class="fss_alert_info__title">{{ $t('Telegram Connection Status: ') }}{{ status }}</h3>
                <p class="fss_alert_info__description">{{ $t('__TELE_RESPONSE_ERROR') }}</p>
                <pre class="fss_alert_info__error-pre">{{errors}}</pre>
                <div class="fss_alert_info__actions">
                    <div class="fss_alert_info__actions__test-button">
                        <el-button @click="getInfo()" :disabled="loading" v-loading="loading"
                                   type="primary" icon="FsmIconRefresh">
                            {{ $t('Try Again') }}
                        </el-button>
                    </div>
                    <div class="fss_alert_info__actions__disconnect">
                        <channel-actions
                            :channel_key="'telegram'"
                            :channel_title="channel_config.title || 'Telegram'"
                            :disconnect_label="$t('Disconnect & Reconnect')"
                            :show_test_button="false"
                        />
                    </div>
                </div>
            </div>
        </template>
    </div>
</template>

<script type="text/babel">
import ChannelActions from './_ChannelActions.vue';

export default {
    name: 'TelegramConnectionInfo',
    components: { ChannelActions },
    props: {
        channel_config: {
            type: Object,
            default: () => ({})
        }
    },
    data() {
        return {
            status: '',
            receiver: null,
            loading: false,
            errors: null
        }
    },
    methods: {
        getInfo() {
            this.loading = true;
            this.$get('settings/telegram/info')
                .then((response) => {
                    this.status = response.data.telegram_notify_status;
                    if (response.data.telegram_receiver) {
                        this.receiver = response.data.telegram_receiver;
                    } else {
                        /*
                         * A success carrying no receiver is the broken connection this
                         * panel exists for. It used to read `errors` from the catch
                         * handler's parameter, which is not in scope here, so the one
                         * case that needed explaining rendered an empty box.
                         */
                        this.errors = response.data.errors || null;
                    }
                })
                .catch((errors) => {
                    const data = errors.responseJSON ? errors.responseJSON.data : null;

                    this.$notify.error(
                        (data && data.message) || this.$t('Could not reach Telegram. Please try again.')
                    );
                    this.errors = (data && data.errors) || null;
                })
                .always(() => {
                    this.loading = false;
                });
        }
    },
    mounted() {
        this.getInfo();
    }
}
</script>
