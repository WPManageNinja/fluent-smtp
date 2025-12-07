<template>
    <div>
        <el-skeleton :animated="true" v-if="loading" :rows="3"></el-skeleton>
        <template v-else>
            <div v-if="status == 'yes'" class="fss_alert_info">
                <img class="fss_alert_info__logo" :src="`${appVars.images_url}tele.svg`"/>
                <h3 class="fss_alert_info__title">{{ $t('Telegram Notifications Enable') }}d</h3>
                <p class="fss_alert_info__description" v-html="$t('__TELEGRAM_NOTIFICATION_ENABLED')">
                </p>
                <p class="fss_alert_info__details">{{ $t('Receiver\'s Telegram Username: ') }}@{{ receiver.username }}</p>
                <p class="fss_alert_info__actions">
                    <el-button @click="sendTest()" :disabled="sending_test" v-loading="sending_test" size="mini"
                               type="text">{{ $t('Send Test Message') }}
                    </el-button>
                    <el-button v-loading="disconnecting" @click="disconnect()" class="fss_alert_info__actions__disconnect" size="mini"
                               type="text">{{ $t('Disconnect') }}
                    </el-button>
                </p>
            </div>
            <div v-else class="fss_alert_info">
                <img class="fss_alert_info__logo" :src="`${appVars.images_url}tele.svg`"/>
                <h3 class="fss_alert_info__title">{{ $t('Telegram Connection Status: ') }}{{ status }}</h3>
                <p class="fss_alert_info__description">{{ $t('__TELE_RESPONSE_ERROR') }}</p>
                <pre class="fss_alert_info__error-pre">{{errors}}</pre>
                <p class="fss_alert_info__actions">
                    <el-button @click="getInfo()" :disabled="sending_test" v-loading="sending_test" size="mini"
                               type="text">
                        {{ $t('Try Again') }}
                    </el-button>
                    <el-button v-loading="disconnecting" @click="disconnect()" class="fss_alert_info__actions__disconnect" size="mini"
                               type="text">{{ $t('Disconnect & Reconnect') }}
                    </el-button>
                </p>
            </div>
        </template>
    </div>
</template>

<script type="text/babel">
export default {
    name: 'TelegramConnectionInfo',
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
