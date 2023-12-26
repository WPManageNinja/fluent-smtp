<template>
    <div>
        <div v-if="notification_settings.telegram_notify_status != 'yes'">
            <div v-if="configure_state == 'intro'">
                <p>
                    Get real-time notification on your Slack Channel on any email sending failure. Configure
                    notification with Slack Bot to start getting real time notifications.
                </p>
                <el-button @click="configure_state = 'form'" size="small" type="info">
                    Configure Slack Notification
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
                            terms and conditions</a> of this slack integration.
                        </el-checkbox>
                    </el-form-item>
                    <el-form-item>
                        <el-button @click="registerSite()" v-loading="processing"
                                   :disabled="newForm.terms != 'yes' || !newForm.user_email || processing"
                                   type="primary">
                            Continue to Slack
                        </el-button>
                    </el-form-item>
                    <p>FluentSMTP does not store your email notifications data. Feel free to check the project at <a
                        target="_blank" href="https://github.com/WPManageNinja/fluent-smtp">Github</a></p>
                </el-form>
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
    name: 'SlackNotification',
    components: {ConnectionInfo},
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
        registerSite() {
            this.processing = true;
            this.$post('settings/slack/register', {
                settings: this.newForm
            })
                .then((response) => {
                    this.$notify.success(response.data.message);
                    // redirect to slack
                    window.location.href = response.data.redirect_url;
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
