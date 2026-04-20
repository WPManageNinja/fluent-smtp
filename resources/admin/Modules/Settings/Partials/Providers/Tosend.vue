<template>
    <div>
        <h3 class="fs_config_title">{{ $t('toSend Api Settings') }}</h3>

        <div v-if="is_new" class="tosend_signup_hint" style="padding: 10px 14px; margin-bottom: 15px; background: #f6fbff; border: 1px solid #cfe5ff; border-radius: 4px; font-size: 13px; line-height: 1.55; color: #2c3e50;">
            {{ $t("Don't have a toSend account yet?") }}
            <a target="_blank" rel="noopener" href="https://tosend.com/?fluent-smtp=connect" style="font-weight: 600;">
                {{ $t('Create one free') }}
            </a>
            — {{ $t('includes 10,000 emails/month at no cost, then $3 per 10,000 after that. No credit card required to start.') }}
        </div>

        <el-alert type="info" :closable="false" style="margin-bottom: 15px;">
            <div style="line-height: 1.6;">
                <strong>{{ $t('How to connect toSend:') }}</strong>
                <ol style="margin: 6px 0 0 18px; padding: 0;">
                    <li>
                        {{ $t('Sign in to') }}
                        <a target="_blank" rel="noopener" href="https://dash.tosend.com/">{{ $t('your toSend dashboard') }}</a>
                        {{ $t('and add your sending domain.') }}
                    </li>
                    <li>{{ $t('Publish the SPF, DKIM, and DMARC DNS records toSend shows for that domain and wait for verification.') }}</li>
                    <li>
                        {{ $t('Open') }}
                        <a target="_blank" rel="noopener" href="https://dash.tosend.com/app/api-keys">{{ $t('API Keys') }}</a>
                        {{ $t('and create a new key, then copy its value.') }}
                    </li>
                    <li>{{ $t('Paste the key below, enter a From Email on the verified domain, and save.') }}</li>
                </ol>
                <p style="margin: 8px 0 0;">
                    {{ $t('Full guide:') }}
                    <a target="_blank" rel="noopener" href="https://tosend.com/docs/guide/wordpress/">tosend.com/docs/guide/wordpress</a>
                </p>
            </div>
        </el-alert>

        <el-radio-group size="mini" v-model="connection.key_store">
            <el-radio-button label="db">{{ $t('Store API Keys in DB') }}</el-radio-button>
            <el-radio-button label="wp_config">{{ $t('Store API Keys in Config File') }}</el-radio-button>
        </el-radio-group>

        <template v-if="connection.key_store == 'db'">
            <el-form-item>
                <label for="fluentmailer-key">
                    {{ $t('API Key') }}
                </label>
                <InputPassword
                    id="fluentmailer-key"
                    placeholder="tosend_xxxxxxx"
                    v-model="connection.api_key"
                    :disable_help="connection.disable_encryption === 'yes'"
                />
                <error :error="errors.get('api_key')"/>
            </el-form-item>
            <el-form-item>
                <el-checkbox true-label="yes" false-label="no" v-model="connection.disable_encryption">
                    {{ $t('Disable Encryption for API Key (Not Recommended)') }}
                </el-checkbox>
                <p style="color: red; margin-top: 0;" v-if="connection.disable_encryption === 'yes'">
                    {{
                        $t('By disabling encryption, your API key will be stored in plain text in the database. This is not recommended for security reasons. Enable only if your security plugin rotate WP SALTS frequently.')
                    }}
                </p>
            </el-form-item>
        </template>

        <div class="fss_condesnippet_wrapper" v-else-if="connection.key_store == 'wp_config'">
            <el-form-item>
                <label>{{ $t('__WP_CONFIG_INSTRUCTION') }}</label>
                <div class="code_snippet">
                    <textarea readonly style="width: 100%;">define( 'FLUENTMAIL_TOSEND_API_KEY', '********************' );</textarea>
                </div>
                <error :error="errors.get('api_key')"/>
            </el-form-item>
        </div>

        <el-form-item>
            <label>{{ $t('Additional Sender Emails') }}</label>
            <div style="display:flex; gap:8px; flex-wrap:wrap; align-items:flex-start; margin-bottom:6px;">
                <el-tag
                    v-for="email in additional_senders"
                    :key="email"
                    closable
                    @close="removeAdditional(email)"
                    style="margin:0;"
                >{{ email }}</el-tag>
            </div>
            <div style="display:flex; gap:8px;">
                <el-input
                    v-model="newAdditional"
                    :placeholder="$t('sender@yourdomain.com')"
                    size="small"
                    style="max-width: 320px;"
                    @keyup.enter.native="addAdditional"
                />
                <el-button size="small" @click="addAdditional" :disabled="!newAdditional">
                    {{ $t('Add') }}
                </el-button>
            </div>
            <p v-if="addError" style="color:#dc3232; font-size:12px; margin:4px 0 0;">{{ addError }}</p>
            <p class="small-help-text" style="font-size:12px; margin:6px 0 0; color:#909399;">
                {{ $t('Add more From addresses that route through this toSend connection. Each email must be on a domain verified in your toSend account — this is checked when you save.') }}
            </p>
            <error :error="errors.get('additional_senders')"/>
        </el-form-item>

    </div>
</template>

<script>
import InputPassword from '@/Pieces/InputPassword';
import Error from '@/Pieces/Error';

export default {
    name: 'ToSend',
    props: ['connection', 'errors', 'is_new'],
    components: {
        InputPassword,
        Error
    },
    data() {
        return {
            newAdditional: '',
            addError: ''
        };
    },
    created() {
        if (!Array.isArray(this.connection.additional_senders)) {
            this.$set(this.connection, 'additional_senders', []);
        }
    },
    computed: {
        additional_senders() {
            return Array.isArray(this.connection.additional_senders) ? this.connection.additional_senders : [];
        }
    },
    watch: {
        'connection.key_store'(value) {
            if (value === 'wp_config') {
                this.connection.api_key = '';
            }
        }
    },
    methods: {
        addAdditional() {
            this.addError = '';
            const email = (this.newAdditional || '').trim().toLowerCase();
            if (!email) return;

            if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
                this.addError = this.$t('Please enter a valid email address.');
                return;
            }

            if (this.connection.sender_email && email === String(this.connection.sender_email).toLowerCase()) {
                this.addError = this.$t('This email is already the primary sender.');
                return;
            }

            if (this.additional_senders.includes(email)) {
                this.addError = this.$t('This email is already in the list.');
                return;
            }

            this.connection.additional_senders = [...this.additional_senders, email];
            this.newAdditional = '';
        },
        removeAdditional(email) {
            this.connection.additional_senders = this.additional_senders.filter(e => e !== email);
        }
    }
};
</script>
