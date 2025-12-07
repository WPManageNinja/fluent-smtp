<template>
    <div class="fss_alert_info">
        <img class="fss_alert_info__logo" :src="`${appVars.images_url}disc.svg`"/>
        <h3 class="fss_alert_info__title">{{ $t('Discord Notifications Enabled') }}</h3>
        <p class="fss_alert_info__description">
            {{ $t('__DISCORD_NOTIFICATION_ENABLED') }}
        </p>
        <p class="fss_alert_info__details">{{ $t('Discord Channel Details: ') }}{{ notification_settings.discord.channel_name }}</p>
        <div class="fss_alert_info__actions">
            <div class="fss_alert_info__actions__test-button">
                <el-button @click="sendTest()" :disabled="sending_test" v-loading="sending_test" type="text">
                    <i class="el-icon-message"></i> {{ $t('Send Test Message') }}
                </el-button>
            </div>
            <div class="fss_alert_info__actions__disconnect">
                <el-button v-loading="disconnecting" @click="disconnect()" type="text">
                    <i class="el-icon-delete"></i> {{ $t('Disconnect') }}
                </el-button>
            </div>
        </div>
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
            this.$confirm(this.$t('Are you sure you want to disconnect Discord notifications?'), 'Warning', {
                confirmButtonText: this.$t('Yes, Disconnect'),
                cancelButtonText: this.$t('Cancel'),
                type: 'warning'
            })
                .then(() => {
                    this.disconnecting = true;
                    this.$post('settings/discord/disconnect')
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
            this.$post('settings/discord/send-test')
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
