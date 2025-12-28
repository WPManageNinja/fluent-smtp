<template>
    <div class="fss_alert_info__actions">
        <div v-if="show_test_button !== false" class="fss_alert_info__actions__test-button">
            <el-button @click="sendTest()" :disabled="sending_test" v-loading="sending_test" type="primary" size="small">
                <i class="el-icon-message"></i> {{ $t('Send Test Message') }}
            </el-button>
        </div>
        <div class="fss_alert_info__actions__disconnect">
            <el-button v-loading="disconnecting" @click="disconnect()" type="danger" size="small">
                <i class="el-icon-delete"></i> {{ disconnectLabel }}
            </el-button>
        </div>
    </div>
</template>

<script type="text/babel">
export default {
    name: 'ChannelActions',
    props: {
        channel_key: {
            type: String,
            required: true
        },
        channel_title: {
            type: String,
            default: ''
        },
        disconnect_label: {
            type: String,
            default: ''
        },
        show_test_button: {
            type: Boolean,
            default: true
        }
    },
    data() {
        return {
            disconnecting: false,
            sending_test: false
        }
    },
    computed: {
        disconnectLabel() {
            return this.disconnect_label || this.$t('Disconnect');
        },
        disconnectMessage() {
            const title = this.channel_title || this.channel_key;
            return this.$t('Are you sure you want to disconnect {title} notifications?', { title: title });
        }
    },
    methods: {
        disconnect() {
            this.$confirm(this.disconnectMessage, 'Warning', {
                confirmButtonText: this.$t('Yes, Disconnect'),
                cancelButtonText: this.$t('Cancel'),
                type: 'warning'
            })
                .then(() => {
                    this.disconnecting = true;
                    this.$post(`settings/${this.channel_key}/disconnect`)
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
            this.$post(`settings/${this.channel_key}/send-test`)
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

