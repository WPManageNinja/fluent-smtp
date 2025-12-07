<template>
    <div>
        <channel-header
            :title="channelTitle"
            :logo="channel_config.logo"
            :connected="true"
            @back="$emit('back')"
        />
        <div class="fss_alert_info">
            <p class="fss_alert_info__description">
            {{$t('__SLACK_NOTIFICATION_ENABLED')}}
        </p>
        <p class="fss_alert_info__details">{{ $t('Slack Channel Details: ') }}@{{ notification_settings.slack.slack_team }}</p>
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
</template>

<script type="text/babel">
import ChannelHeader from './_ChannelHeader.vue';

export default {
    name: 'SlackWebhookInfo',
    components: { ChannelHeader },
    props: {
        notification_settings: {
            type: Object,
            default: () => {
                return {}
            }
        },
        channel_config: {
            type: Object,
            default: () => ({})
        }
    },
    computed: {
        channelTitle() {
            return (this.channel_config.title || 'Slack') + ' ' + this.$t('Notifications Enabled');
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
