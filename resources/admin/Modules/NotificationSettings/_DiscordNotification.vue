<template>
    <div>
        <div v-if="!isConfigured">
            <div>
                <p>
                    {{ $t('__DISCORD_INTRO') }}<a target="_blank" rel="noopener" href="https://fluentsmtp.com/docs/email-sending-error-notification-discord/">{{ $t('Read the documentation') }}</a>.
                </p>
                <el-form class="fss_compact_form" :data="newForm" label-position="top">
                    <el-form-item :label="$t('Your Discord Channel Name (For Internal Use)')">
                        <el-input size="small" v-model="newForm.channel_name"/>
                    </el-form-item>

                    <el-form-item :label="$t('Your Discord Channel Webhook URL')">
                        <el-input size="small" v-model="newForm.webhook_url" :placeholder="$t('Discord Webhook URL')"/>
                    </el-form-item>

                    <el-form-item>
                        <el-button @click="registerSite()" v-loading="processing"
                                   :disabled="!newForm.webhook_url || !newForm.channel_name"
                                   type="primary">
                            {{ $t('Configure Discord Notification') }}
                        </el-button>
                    </el-form-item>
                </el-form>
            </div>
        </div>
        <div v-else>
            <discord-info :notification_settings="notification_settings"/>
        </div>
    </div>
</template>

<script type="text/babel">
import DiscordInfo from './_DiscordWebhookInfo.vue';

export default {
    name: 'SlackNotification',
    components: {DiscordInfo},
    props: {
        notification_settings: {
            type: Object,
            default: () => {
                return {}
            }
        }
    },
    computed: {
        isConfigured() {
            return this.notification_settings.discord && this.notification_settings.discord.status == 'yes' && this.notification_settings.discord.webhook_url;
        }
    },
    data() {
        return {
            configure_state: 'form',
            processing: false,
            newForm: {
                webhook_url: '',
                channel_name: ''
            },
        }
    },
    methods: {
        registerSite() {
            this.processing = true;
            this.$post('settings/discord/register', {
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
