<template>
    <div>
        <img style="max-height: 50px;" :src="`${appVars.images_url}disc.svg`"/>
        <h3>Discord Notifications Enabled</h3>
        <p>
            Your FluentSMTP plugin is currently integrated with your Discord Channel. Receive timely notifications on
            Discord for any email sending issues from your website. This ongoing connection ensures you're always
            informed about your email delivery status.
        </p>
        <p>Discord Channel Details: {{ notification_settings.discord.channel_name }}</p>
        <p>
            <el-button @click="sendTest()" :disabled="sending_test" v-loading="sending_test" size="mini" type="text">
                Send Test Message
            </el-button>
            <el-button v-loading="disconnecting" @click="disconnect()" style="float: right;" size="mini" type="text">
                Disconnect
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
            this.$confirm('Are you sure you want to disconnect Slack notifications?', 'Warning', {
                confirmButtonText: 'Yes, Disconnect',
                cancelButtonText: 'cancel',
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
