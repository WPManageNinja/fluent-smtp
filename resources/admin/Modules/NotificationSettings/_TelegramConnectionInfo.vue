<template>
    <div>
        <el-skeleton :animated="true" v-if="loading" :rows="3"></el-skeleton>
        <template v-else>
            <channel-header
                v-if="status == 'yes'"
                :channel-title="channel_config.title || 'Telegram'"
                :logo="channel_config.logo"
                :connected="true"
                @back="$emit('back')"
            />
            <div v-if="status == 'yes'" class="fss_alert_info">
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
                        <el-button @click="getInfo()" :disabled="loading" v-loading="loading" type="primary" size="small">
                            <i class="el-icon-refresh"></i> {{ $t('Try Again') }}
                        </el-button>
                    </div>
                    <div class="fss_alert_info__actions__disconnect">
                        <channel-actions
                            :channel_key="'telegram'"
                            :channel_title="channel_config.title || 'Telegram'"
                            :disconnect_label="$t('Disconnect & Reconnect')"
                            :show-test-button="false"
                        />
                    </div>
                </div>
            </div>
        </template>
    </div>
</template>

<script type="text/babel">
import ChannelHeader from './_ChannelHeader.vue';
import ChannelActions from './_ChannelActions.vue';

export default {
    name: 'TelegramConnectionInfo',
    components: { ChannelHeader, ChannelActions },
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
                        this.errors = errors.responseJSON.data.errors;
                    }
                })
                .catch((errors) => {
                    this.$notify.error(errors.responseJSON.data.message);
                    this.errors = errors.responseJSON.data.errors;
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
