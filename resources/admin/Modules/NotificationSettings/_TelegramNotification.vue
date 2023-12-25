<template>
    <div>
        <div v-if="notification_settings.telegram_notify_status != 'yes'">
            <div v-if="configure_state == 'intro'" style="text-align: center;">
                <p>Get real-time notification on your <a target="_blank" rel="noopener" href="https://telegram.org/">Telegram
                    Messenger</a> on any email sending failure. Configure notification with our <a target="_blank"
                                                                                                   rel="noopener"
                                                                                                   href="https://t.me/fluentsmtp_bot">official
                    telegram bot</a> to start getting real time notifications.</p>
                <el-button @click="configure_state = 'form'" size="small" type="info">Configure Telegram Notification
                </el-button>
            </div>
            <div v-else-if="configure_state == 'form'">
                <el-form :data="newForm" label-position="top">
                    <el-form-item label="Your Email Address">
                        <el-input size="small" v-model="newForm.user_email" placeholder="Email Address"/>
                    </el-form-item>
                    <el-form-item>
                        <el-checkbox v-model="newForm.terms" true-label="yes" false-label="no">
                            I agree to the <a target="_blank" rel="noopener"
                                              href="https://fluentsmtp.com/terms-and-conditions/">terms
                            and conditions</a> of this telegram integration.
                        </el-checkbox>
                    </el-form-item>
                    <el-form-item>
                        <el-button @click="issuePinCode()" v-loading="processing"
                                   :disabled="newForm.terms != 'yes' || !newForm.user_email || processing"
                                   type="primary">Continue
                        </el-button>
                    </el-form-item>
                    <p>FluentSMTP does not store your email notifications data. Feel free to check the project at <a
                        target="_blank" href="https://github.com/WPManageNinja/fluent-smtp">Github</a></p>
                </el-form>
            </div>
        </div>
    </div>
</template>

<script type="text/babel">
export default {
    name: 'TelegramNotification',
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
            configure_state: 'form',
            processing: false,
            newForm: {
                user_email: '',
                terms: 'no',
                notification_pin: ''
            },
        }
    },
    methods: {
        issuePinCode() {
            this.processing = true;
            this.$post('settings/telegram/configure-connection', {
                settings: this.newForm
            })
                .then((response) => {
                    this.newForm.notification_pin = response.data.pin;
                    this.configure_state = 'pin';
                })
                .catch((errors) => {
                    this.$notify.error(errors.responseJSON.data.message);
                })
                .always(() => {
                    this.processing = false;
                });
        }
    },
    mounted() {
        this.newForm.user_email = this.appVars.user_email;
    }
}
</script>
