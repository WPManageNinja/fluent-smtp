<template>
    <div class="fss_alert_settings">
        <div v-if="!isConfigured">
            <div>
                <p class="fss_alert_settings__intro">
                    {{ $t('__GOTIFY_INTRO') }} <a target="_blank" rel="noopener" href="https://gotify.net/docs/pushmsg">{{ $t('Read the documentation') }}</a>.
                </p>
                <el-form class="fss_compact_form fss_alert_settings__form" :data="newForm" label-position="top">
                    <el-form-item :label="$t('Server URL')">
                        <el-input size="small" v-model="newForm.server_url" placeholder="https://gotify.example.com"/>
                    </el-form-item>

                    <el-form-item :label="$t('Application Token')">
                        <el-input size="small" v-model="newForm.app_token" :placeholder="$t('Gotify Application Token')"/>
                    </el-form-item>

                    <el-form-item>
                        <el-button @click="registerSite()" v-loading="processing"
                                   :disabled="!newForm.server_url || !newForm.app_token"
                                   type="primary">
                            {{ $t('Configure Gotify Notification') }}
                        </el-button>
                    </el-form-item>
                </el-form>
            </div>
        </div>
        <div v-else>
            <gotify-info :notification_settings="notification_settings" :channel_config="channel_config" @back="$emit('back')"/>
        </div>
    </div>
</template>

<script type="text/babel">
import GotifyInfo from './_GotifyConnectionInfo.vue';

export default {
    name: 'GotifyNotification',
    components: {GotifyInfo},
    props: {
        notification_settings: {
            type: Object,
            default: () => {
                return {}
            }
        },
        channel_key: {
            type: String,
            default: 'gotify'
        },
        channel_config: {
            type: Object,
            default: () => ({})
        }
    },
    computed: {
        isConfigured() {
            return this.notification_settings.gotify && this.notification_settings.gotify.status == 'yes' && this.notification_settings.gotify.server_url && this.notification_settings.gotify.app_token;
        }
    },
    data() {
        return {
            configure_state: 'form',
            processing: false,
            newForm: {
                server_url: '',
                app_token: ''
            },
        }
    },
    methods: {
        registerSite() {
            this.processing = true;
            this.$post('settings/gotify/register', {
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
