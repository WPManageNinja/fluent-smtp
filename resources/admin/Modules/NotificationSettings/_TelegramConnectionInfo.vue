<template>
    <div>
        <el-skeleton :animated="true" v-if="loading" :rows="3"></el-skeleton>
        <template v-else>
            <channel-header
                v-if="status == 'yes'"
                :title="channelTitle"
                :logo="channel_config.logo"
                :connected="true"
                @back="$emit('back')"
            />
            <div v-if="status == 'yes'" class="fss_alert_info">
                <p class="fss_alert_info__description" v-html="$t('__TELEGRAM_NOTIFICATION_ENABLED')">
                </p>
                <p class="fss_alert_info__details">{{ $t('Receiver\'s Telegram Username: ') }}@{{ receiver.username }}</p>
                <div class="fss_alert_info__actions">
                    <div class="fss_alert_info__actions__test-button">
                        <el-button @click="sendTest()" :disabled="sending_test" v-loading="sending_test" type="primary" size="small">
                            <i class="el-icon-message"></i> {{ $t('Send Test Message') }}
                        </el-button>
                    </div>
                    <div class="fss_alert_info__actions__disconnect">
                        <el-button v-loading="disconnecting" @click="disconnect()" type="danger" size="small">
                            <i class="el-icon-delete"></i> {{ $t('Disconnect') }}
                        </el-button>
                    </div>
                </div>
            </div>
            <div v-else class="fss_alert_info">
                <h3 class="fss_alert_info__title">{{ $t('Telegram Connection Status: ') }}{{ status }}</h3>
                <p class="fss_alert_info__description">{{ $t('__TELE_RESPONSE_ERROR') }}</p>
                <pre class="fss_alert_info__error-pre">{{errors}}</pre>
                <div class="fss_alert_info__actions">
                    <div class="fss_alert_info__actions__test-button">
                        <el-button @click="getInfo()" :disabled="sending_test" v-loading="sending_test" type="primary" size="small">
                            <i class="el-icon-refresh"></i> {{ $t('Try Again') }}
                        </el-button>
                    </div>
                    <div class="fss_alert_info__actions__disconnect">
                        <el-button v-loading="disconnecting" @click="disconnect()" type="danger" size="small">
                            <i class="el-icon-delete"></i> {{ $t('Disconnect & Reconnect') }}
                        </el-button>
                    </div>
                </div>
            </div>
        </template>
    </div>
</template>

<script type="text/babel">
import ChannelHeader from './_ChannelHeader.vue';

export default {
    name: 'TelegramConnectionInfo',
    components: { ChannelHeader },
    props: {
        channel_config: {
            type: Object,
            default: () => ({})
        }
    },
    computed: {
        channelTitle() {
            return (this.channel_config.title || 'Telegram') + ' ' + this.$t('Notifications');
        }
    },
    data() {
        return {
            status: '',
            receiver: null,
            loading: false,
            disconnecting: false,
            sending_test: false,
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
        },
        disconnect() {
            this.$confirm(this.$t('Are you sure you want to disconnect Telegram notifications?'), 'Warning', {
                confirmButtonText: this.$t('Yes, Disconnect'),
                cancelButtonText: this.$t('Cancel'),
                type: 'warning'
            })
                .then(() => {
                    this.disconnecting = true;
                    this.$post('settings/telegram/disconnect')
                        .then((response) => {
                            this.$notify.success(response.data.message);
                            window.location.reload();
                        })
                        .catch((errors) => {
                            this.$notify.error(errors.responseJSON.data.message);
                        })
                        .always(() => {
                            this.disconnecting = false;
                        });
                });
        },
        sendTest() {
            this.sending_test = true;
            this.$post('settings/telegram/send-test')
                .then((response) => {
                    this.$notify.success(response.data.message);
                })
                .catch((errors) => {
                    this.$notify.error(errors.responseJSON.data.message);
                })
                .always(() => {
                    this.sending_test = false;
                });
        }
    },
    mounted() {
        this.getInfo();
    }
}
</script>
