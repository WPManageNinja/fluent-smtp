<template>
    <div>
        <div v-if="notification_settings.telegram_notify_status != 'yes'">
            <div v-if="configure_state == 'intro'">
                <p>Get real-time notification on your <a target="_blank" rel="noopener" href="https://telegram.org/">Telegram
                    Messenger</a> on any email sending failure. Configure notification with FluentSMTP's
                    <a target="_blank" rel="noopener" href="https://t.me/fluentsmtp_bot">
                        official telegram bot
                    </a> to start getting real time notifications. <a target="_blank" rel="noopener" href="https://fluentsmtp.com/docs/email-sending-error-notification-telegram/">Read the documentation</a>.
                </p>
                <el-button @click="configure_state = 'form'" size="small" type="info">
                    Configure Telegram Notification
                </el-button>
            </div>
            <div v-else-if="configure_state == 'form'">
                <el-form class="fss_compact_form" :data="newForm" label-position="top">
                    <el-form-item label="Your Email Address">
                        <el-input size="small" v-model="newForm.user_email" placeholder="Email Address"/>
                    </el-form-item>
                    <el-form-item>
                        <el-checkbox v-model="newForm.terms" true-label="yes" false-label="no">
                            I agree to the <a target="_blank" rel="noopener"
                                              href="https://fluentsmtp.com/terms-and-conditions/">
                            terms and conditions</a> of this telegram integration.
                        </el-checkbox>
                    </el-form-item>
                    <el-form-item>
                        <el-button @click="issuePinCode()" v-loading="processing"
                                   :disabled="newForm.terms != 'yes' || !newForm.user_email || processing"
                                   type="primary">
                            Continue
                        </el-button>
                    </el-form-item>
                    <p>FluentSMTP does not store your email notifications data. <a target="_blank" rel="noopener" href="https://fluentsmtp.com/docs/email-sending-error-notification-telegram/">Read the documentation</a>.</p>
                </el-form>
            </div>
            <div v-else-if="configure_state == 'pin'">
                <h3>Last step!</h3>
                <p>Please find <a target="_blank" rel="noopener" href="https://t.me/fluentsmtp_bot"><span
                    class="tele_bot">@fluentsmtp_bot</span></a> on telegram and send following text to activate this
                    connection.</p>
                <h3>Activation Pin</h3>
                <p style="font-size: 20px;font-weight: bold;padding: 10px; margin: 15px 0; background: rgb(248 250 252);border-radius: 5px;border: 2px dashed #e8d100;">
                    activate {{ newForm.site_pin }}
                    <span @click="copyPin()"
                          style="float: right; user-select: none; font-size: 14px;border: 1px solid #f0f0f1;padding: 2px 10px;line-height: 14px;background: white;border-radius: 5px;cursor: pointer;">copy</span>
                </p>
                <el-button :disabled="processing" v-loading="processing" @click="confirmConnection()" size="medium" type="success">I have sent the code</el-button>
            </div>
        </div>
        <div v-else>
            <connection-info/>
        </div>
    </div>
</template>

<script type="text/babel">
import ConnectionInfo from './_TelegramConnectionInfo.vue';
export default {
    name: 'TelegramNotification',
    components: { ConnectionInfo },
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
            configure_state: 'intro',
            processing: false,
            newForm: {
                user_email: '',
                terms: 'no',
                site_pin: 'wp.lab-327372',
                site_token: ''
            },
        }
    },
    methods: {
        issuePinCode() {
            this.processing = true;
            this.$post('settings/telegram/issue-pin-code', {
                settings: this.newForm
            })
                .then((response) => {
                    this.$notify.success(response.data.message);
                    this.newForm.site_token = response.data.site_token;
                    this.newForm.site_pin = response.data.site_pin;
                    this.configure_state = 'pin';
                })
                .catch((errors) => {
                    this.$notify.error(errors.responseJSON.data.message);
                })
                .always(() => {
                    this.processing = false;
                });
        },
        confirmConnection() {
            this.processing = true;
            this.$post('settings/telegram/confirm', {
                site_token: this.newForm.site_token
            })
                .then((response) => {
                    this.$notify.success(response.data.message);
                    // reload the page
                    window.location.reload();
                })
                .catch((errors) => {
                    this.$notify.error(errors.responseJSON.data.message);
                })
                .always(() => {
                    this.processing = false;
                });
        },
        copyPin() {
            const el = document.createElement('textarea');
            el.value = 'activate ' + this.newForm.site_pin;
            document.body.appendChild(el);
            el.select();
            document.execCommand('copy');
            document.body.removeChild(el);
            this.$notify.success('Pin copied to clipboard');
        }
    },
    mounted() {
        this.newForm.user_email = this.appVars.user_email;
    }
}
</script>
