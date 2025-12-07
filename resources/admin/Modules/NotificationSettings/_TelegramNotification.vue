<template>
    <div class="fss_alert_settings">
        <div v-if="!notification_settings.telegram || notification_settings.telegram.status != 'yes'">
            <div v-if="configure_state == 'form'">
                <p class="fss_alert_settings__intro--compact" v-html="$t('__TELE_INTRO')"></p>
                <p class="fss_alert_settings__intro">
                    <a target="_blank" rel="noopener" href="https://fluentsmtp.com/docs/email-sending-error-notification-telegram/">{{ $t('Read the documentation') }}</a>.
                </p>

                <el-form class="fss_compact_form fss_alert_settings__form" :data="newForm" label-position="top">
                    <el-form-item :label="$t('Your Email Address')">
                        <el-input size="small" v-model="newForm.user_email" :placeholder="$t('Email Address')"/>
                    </el-form-item>
                    <el-form-item>
                        <el-checkbox v-model="newForm.terms" true-label="yes" false-label="no">
                            <div v-html="$t('__TELE_TERMS')"></div>
                        </el-checkbox>
                    </el-form-item>
                    <el-form-item>
                        <el-button @click="issuePinCode()" v-loading="processing"
                                   :disabled="newForm.terms != 'yes' || !newForm.user_email || processing"
                                   type="primary">
                            {{ $t('Continue') }}
                        </el-button>
                    </el-form-item>
                </el-form>
                <p class="fss_alert_settings__privacy-note">{{ $t('FluentSMTP does not store your email notifications data.') }}</p>
            </div>
            <div v-else-if="configure_state == 'pin'">
                <h3 class="fss_alert_settings__section-title">{{ $t('Last step!') }}</h3>
                <p class="fss_alert_settings__intro" v-html="$t('__TELE_LAST_STEP')"></p>
                <h3 class="fss_alert_settings__section-title">{{ $t('Activation Pin') }}</h3>
                <p class="fss_alert_settings__pin-container">
                    {{ $t('activate ') }} {{ newForm.site_pin }}
                    <span @click="copyPin()" class="fss_alert_settings__pin-container__copy-button">{{ $t('copy') }}</span>
                </p>
                <el-button :disabled="processing" v-loading="processing" @click="confirmConnection()" size="medium" type="success">{{ $t('I have sent the code') }}</el-button>
            </div>
        </div>
        <div v-else>
            <connection-info :channel_config="channel_config" @back="$emit('back')"/>
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
        },
        channel_key: {
            type: String,
            default: 'telegram'
        },
        channel_config: {
            type: Object,
            default: () => ({})
        }
    },
    data() {
        return {
            configure_state: 'form',
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
            this.$notify.success(this.$t('Pin copied to clipboard'));
        },
    },
    mounted() {
        this.newForm.user_email = this.appVars.user_email;
    }
}
</script>
