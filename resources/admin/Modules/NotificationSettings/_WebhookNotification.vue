<template>
    <div class="fss_alert_settings">
        <!-- Unlike the other channels, the form stays up once configured, so the template can be iterated on. -->
        <webhook-info
            v-if="isConfigured"
            :notification_settings="notification_settings"
            :channel_config="channel_config"
            :unsaved_changes="hasUnsavedChanges"
            @back="$emit('back')"
        />

        <p class="fss_alert_settings__intro">
            {{ $t('__WEBHOOK_INTRO') }}
        </p>

        <el-form class="fss_compact_form fss_alert_settings__form" :model="newForm" label-position="top">
            <el-form-item :label="$t('Name (for your own reference)')">
                <el-input v-model="newForm.label" :placeholder="$t('My automation endpoint')"/>
            </el-form-item>

            <el-form-item :label="$t('Webhook URL')">
                <!-- Never bound to the stored value, which arrives masked. Blank keeps what is stored. -->
                <el-input
                    v-model="newForm.webhook_url"
                    :placeholder="isConfigured ? $t('Saved. Leave blank to keep the current URL.') : 'https://example.com/webhook'"
                />
            </el-form-item>

            <el-form-item :label="$t('Request Body (JSON)')">
                <el-input
                    v-model="newForm.body_template"
                    type="textarea"
                    :rows="12"
                    class="fss_alert_settings__template"
                />
                <p class="fss_alert_settings__hint">
                    {{ $t('__WEBHOOK_TEMPLATE_HELP') }}
                </p>
                <p class="fss_alert_settings__hint">
                    {{ $t('Available placeholders: ') }}<code>{{ placeholderList }}</code>
                </p>
                <p class="fss_alert_settings__hint">
                    {{ $t('For Mattermost, Microsoft Teams, Google Chat and other chat platforms that take a JSON body with a "text" field, use: ') }}<code>{{ chatAppExample }}</code>
                </p>
                <p class="fss_alert_settings__hint">
                    {{ $t('Clear this box and save to restore the default.') }}
                </p>
            </el-form-item>

            <el-form-item>
                <el-button @click="registerSite()" v-loading="processing"
                           :disabled="!newForm.label || (!isConfigured && !newForm.webhook_url)"
                           type="primary">
                    {{ isConfigured ? $t('Save Changes') : $t('Connect Webhook') }}
                </el-button>
            </el-form-item>
        </el-form>
    </div>
</template>

<script type="text/babel">
import WebhookInfo from './_WebhookConnectionInfo.vue';

export default {
    name: 'WebhookNotification',
    components: {WebhookInfo},
    props: {
        notification_settings: {
            type: Object,
            default: () => {
                return {}
            }
        },
        channel_key: {
            type: String,
            default: 'webhook'
        },
        channel_config: {
            type: Object,
            default: () => ({})
        }
    },
    watch: {
        // Saving doesn't re-run data(), so refresh from storage (the server may substitute the default template).
        'notification_settings.webhook': {
            deep: true,
            handler(stored) {
                stored = stored || {};
                this.newForm.label = stored.label || '';
                this.newForm.body_template = stored.body_template || '';
            }
        }
    },
    computed: {
        // The test sends what is stored, so it is disabled while the form differs. Any typed URL counts as a change.
        hasUnsavedChanges() {
            const stored = this.notification_settings.webhook || {};

            return this.newForm.label !== (stored.label || '')
                || this.newForm.body_template !== (stored.body_template || '')
                || !!this.newForm.webhook_url;
        },
        // Built here because Vue would parse a literal {{placeholder}} in the markup.
        placeholderList() {
            return (this.channel_config.placeholders || []).map((key) => '{{' + key + '}}').join(' ');
        },
        isConfigured() {
            return !!(this.notification_settings.webhook
                && this.notification_settings.webhook.status == 'yes'
                && this.notification_settings.webhook.webhook_url);
        }
    },
    data() {
        const stored = this.notification_settings.webhook || {};
        return {
            processing: false,
            chatAppExample: '{"text": "{{message}}"}',
            newForm: {
                label: stored.label || '',
                // Deliberately empty. See the comment on the URL field.
                webhook_url: '',
                body_template: stored.body_template || ''
            },
        }
    },
    methods: {
        registerSite() {
            this.processing = true;
            this.$post('settings/webhook/register', {
                settings: this.newForm
            })
                .then((response) => {
                    this.$notify.success(response.data.message);
                    // Saved, so the box goes back to its "leave blank to keep" state.
                    this.newForm.webhook_url = '';
                    // Not window.location.reload() as other channels use: that returns to the channel list. The parent refetches instead.
                    this.$emit('saved');
                })
                .catch((errors) => {
                    this.$notify.error(this.$errorMessage(errors));
                })
                .always(() => {
                    this.processing = false;
                });
        }
    }
}
</script>
