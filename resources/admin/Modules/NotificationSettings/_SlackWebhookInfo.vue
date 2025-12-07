<template>
    <div class="fss_alert_info">
        <img class="fss_alert_info__logo" :src="`${appVars.images_url}slack.svg`"/>
        <h3 class="fss_alert_info__title">{{ $t('Slack Notifications Enabled') }}</h3>
        <p class="fss_alert_info__description">
            {{$t('__SLACK_NOTIFICATION_ENABLED')}}
        </p>
        <p class="fss_alert_info__details">{{ $t('Slack Channel Details: ') }}@{{ notification_settings.slack.slack_team }}</p>
        <p class="fss_alert_info__actions">
            <el-button @click="sendTest()" :disabled="sending_test" v-loading="sending_test" size="mini" type="text">
                {{ $t('Send Test Message') }}
            </el-button>
            <el-button v-loading="disconnecting" @click="disconnect()" class="fss_alert_info__actions__disconnect" size="mini" type="text">
                {{ $t('Disconnect') }}
            </el-button>
        </p>
    </div>
</template>

<script type="text/babel">
export default {
    name: 'TelegramConnectionInfo',
    props: {
        notification_settings: {
            type: Object,
            default: () => {
                return {}
            }
        }
    },
    data() {
        return {
            disconnecting: false,
            sending_test: false
        }
    },
    methods: {
        disconnect() {
            this.$confirm(this.$t('Are you sure you want to disconnect Slack notifications?'), 'Warning', {
                confirmButtonText: this.$t('Yes, Disconnect'),
                cancelButtonText: this.$t('Cancel'),
                type: 'warning'
            })
                .then(() => {
                    this.disconnecting = true;
                    this.$post('settings/slack/disconnect')
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
            this.$post('settings/slack/send-test')
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
    }
}
</script>
