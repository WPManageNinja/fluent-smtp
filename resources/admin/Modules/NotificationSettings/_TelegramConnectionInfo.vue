<template>
    <div>
        <el-skeleton :animated="true" v-if="loading" :rows="3"></el-skeleton>
        <template v-else>
            <div v-if="status == 'yes'">
                <h3>Telegram Notifications Enabled</h3>
                <p>
                    Your FluentSMTP plugin is currently integrated with Telegram. Receive timely notifications from <a
                    target="_blank" rel="noopener" href="https://t.me/fluentsmtp_bot">@fluentsmtp_bot</a> on Telegram
                    for any email sending issues from your website. This ongoing connection ensures you're always
                    informed about your email delivery status.
                </p>
                <p>Receiver's Telegram Username: @{{ receiver.username }}</p>
                <p>
                    <el-button size="mini" type="text">Send Test Message</el-button>
                    <el-button v-loading="disconnecting" @click="disconnect()" style="float: right;" size="mini"
                               type="text">Disconnect
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
            disconnecting: false
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
                    }
                })
                .catch((errors) => {
                    this.$notify.error(errors.responseJSON.data.message);
                })
                .always(() => {
                    this.loading = false;
                });
        },
        disconnect() {
            this.$confirm('Are you sure you want to disconnect Telegram notifications?', 'Warning', {
                confirmButtonText: 'Yes, Disconnect',
                cancelButtonText: 'cancel',
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
        }
    },
    mounted() {
        this.getInfo();
    }
}
</script>
