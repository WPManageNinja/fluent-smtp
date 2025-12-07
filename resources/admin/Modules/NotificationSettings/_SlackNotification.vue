<template>
    <div class="fss_alert_settings">
        <el-button @click="goBack()" size="mini" type="text" class="fss_alert_settings__back-button">
            <i class="el-icon-arrow-left"></i> {{ $t('Back to Alerts') }}
        </el-button>
        <h3 class="fss_alert_settings__title">{{ channelTitle }} {{ $t('Settings') }}</h3>
        <div v-if="!isConfigured">
            <div v-if="configure_state == 'form'">
                <p class="fss_alert_settings__intro">
                    {{ $t('__SLACK_INTRO') }} <a target="_blank" rel="noopener" href="https://fluentsmtp.com/docs/email-sending-error-notification-slack/">{{ $t('Read the documentation') }}</a>.
                </p>

                <el-form class="fss_compact_form fss_alert_settings__form" :data="newForm" label-position="top">
                    <el-form-item label="Your Email Address">
                        <el-input size="small" v-model="newForm.user_email" :placeholder="$t('Email Address')"/>
                    </el-form-item>
                    <el-form-item>
                        <el-checkbox v-model="newForm.terms" true-label="yes" false-label="no">
                            <div v-html="$t('__SLACK_TERMS')"></div>
                        </el-checkbox>
                    </el-form-item>
                    <el-form-item>
                        <el-button @click="registerSite()" v-loading="processing"
                                   :disabled="newForm.terms != 'yes' || !newForm.user_email || processing"
                                   type="primary">
                            {{ $t('Continue to Slack') }}
                        </el-button>
                    </el-form-item>
                </el-form>
                <p class="fss_alert_settings__privacy-note">{{ $t('FluentSMTP does not store your email notifications data. ')}} <a target="_blank" rel="noopener" href="https://fluentsmtp.com/docs/email-sending-error-notification-slack/">{{ $t('Read the documentation') }}</a>.</p>
            </div>
        </div>
        <div v-else>
            <slack-info :notification_settings="notification_settings"/>
        </div>
    </div>
</template>

<script type="text/babel">
import SlackInfo from './_SlackWebhookInfo.vue';

export default {
    name: 'SlackNotification',
    components: {SlackInfo},
    props: {
        notification_settings: {
            type: Object,
            default: () => {
                return {}
            }
        },
        channel_key: {
            type: String,
            default: 'slack'
        }
    },
    computed: {
        isConfigured() {
            return this.notification_settings.slack && this.notification_settings.slack.status == 'yes' && this.notification_settings.slack.webhook_url;
        }
    },
    data() {
        return {
            configure_state: 'form',
            processing: false,
            channelTitle: 'Slack',
            newForm: {
                user_email: '',
                terms: 'no',
                site_pin: '',
                site_token: ''
            },
        }
    },
    methods: {
        goBack() {
            this.$emit('back');
        },
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
        },
        loadChannelConfig() {
            this.$get('settings/notification-channels')
                .then((response) => {
                    const channels = response.data.channels || {};
                    const channel = channels[this.channel_key];
                    if (channel && channel.title) {
                        this.channelTitle = channel.title;
                    }
                })
                .catch(() => {
                    // Fallback to default if API fails
                });
        }
    },
    mounted() {
        this.newForm.user_email = this.appVars.user_email;
        this.loadChannelConfig();
    }
}
</script>
