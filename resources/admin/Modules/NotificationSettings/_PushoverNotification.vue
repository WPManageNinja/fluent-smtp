<template>
    <div class="fss_alert_settings">
        <div v-if="!isConfigured">
            <div>
                <p class="fss_alert_settings__intro">
                    {{ $t('__PUSHOVER_INTRO') }} <a target="_blank" rel="noopener" href="https://fluentsmtp.com/docs/email-sending-error-notification-pushover/">{{ $t('Read the documentation') }}</a>.
                </p>
                <el-form class="fss_compact_form fss_alert_settings__form" :data="newForm" label-position="top">
                    <el-form-item :label="$t('API Token')">
                        <el-input size="small" v-model="newForm.api_token" :placeholder="$t('Pushover API Token')"/>
                    </el-form-item>

                    <el-form-item :label="$t('User Key')">
                        <el-input size="small" v-model="newForm.user_key" :placeholder="$t('Pushover User Key')"/>
                    </el-form-item>

                    <el-form-item>
                        <el-button @click="registerSite()" v-loading="processing"
                                   :disabled="!newForm.api_token || !newForm.user_key"
                                   type="primary">
                            {{ $t('Configure Pushover Notification') }}
                        </el-button>
                    </el-form-item>
                </el-form>
            </div>
        </div>
        <div v-else>
            <pushover-info :notification_settings="notification_settings" :channel_config="channel_config" @back="$emit('back')"/>
        </div>
    </div>
</template>

<script type="text/babel">
import PushoverInfo from './_PushoverWebhookInfo.vue';

export default {
    name: 'PushoverNotification',
    components: {PushoverInfo},
    props: {
        notification_settings: {
            type: Object,
            default: () => {
                return {}
            }
        },
        channel_key: {
            type: String,
            default: 'pushover'
        },
        channel_config: {
            type: Object,
            default: () => ({})
        }
    },
    computed: {
        isConfigured() {
            return this.notification_settings.pushover && this.notification_settings.pushover.status == 'yes' && this.notification_settings.pushover.api_token && this.notification_settings.pushover.user_key;
        }
    },
    data() {
        return {
            configure_state: 'form',
            processing: false,
            newForm: {
                api_token: '',
                user_key: ''
            },
        }
    },
    methods: {
        registerSite() {
            this.processing = true;
            this.$post('settings/pushover/register', {
                settings: this.newForm
            })
                .then((response) => {
                    this.$notify.success(response.data.message);
                    window.location.reload();
                })
                .catch((errors) => {
                    this.$notify.error(errors.responseJSON.data.message);
                })
                .always(() => {
                    this.processing = false;
                });
        }
    }
}
</script>
