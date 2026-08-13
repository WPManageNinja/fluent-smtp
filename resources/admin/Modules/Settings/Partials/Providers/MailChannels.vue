<template>
    <div>
        <h3 class="fs_config_title">{{ $t('MailChannels Email API Settings') }}</h3>

        <el-alert type="info" :closable="false" style="margin-bottom: 15px;">
            <div style="line-height: 1.6;">
                {{ $t('Create an Email API key in the MailChannels Console, then publish the SPF, DKIM, and DMARC records for the sender domain.') }}
                <a target="_blank" rel="noopener" href="https://console.mailchannels.net/">{{ $t('Open MailChannels Console') }}</a>
            </div>
        </el-alert>

        <el-radio-group size="mini" v-model="connection.key_store">
            <el-radio-button label="db">{{ $t('Store API Keys in DB') }}</el-radio-button>
            <el-radio-button label="wp_config">{{ $t('Store API Keys in Config File') }}</el-radio-button>
        </el-radio-group>

        <template v-if="connection.key_store == 'db'">
            <el-form-item>
                <label for="mailchannels-key">{{ $t('API Key') }}</label>
                <InputPassword
                    id="mailchannels-key"
                    v-model="connection.api_key"
                    :disable_help="connection.disable_encryption === 'yes'"
                />
                <error :error="errors.get('api_key')"/>
            </el-form-item>
            <el-form-item>
                <el-checkbox true-label="yes" false-label="no" v-model="connection.disable_encryption">
                    {{ $t('Disable Encryption for API Key (Not Recommended)') }}
                </el-checkbox>
            </el-form-item>
        </template>

        <div class="fss_condesnippet_wrapper" v-else-if="connection.key_store == 'wp_config'">
            <el-form-item>
                <label>{{ $t('__WP_CONFIG_INSTRUCTION') }}</label>
                <div class="code_snippet">
                    <textarea readonly style="width: 100%;">define( 'FLUENTMAIL_MAILCHANNELS_API_KEY', '********************' );
// Or use the shared MAILCHANNELS_API_KEY constant.</textarea>
                </div>
                <error :error="errors.get('api_key')"/>
            </el-form-item>
        </div>

        <el-form-item :label="$t('Submission mode')">
            <el-radio-group v-model="connection.send_mode">
                <el-radio label="direct">{{ $t('Direct — wait for per-message acceptance results') }}</el-radio>
                <el-radio label="queued">{{ $t('Queued — return after MailChannels accepts the request') }}</el-radio>
            </el-radio-group>
            <p class="small-help-text">
                {{ $t('Both modes use HTTPS only. FluentSMTP does not automatically retry a failed submission.') }}
            </p>
            <error :error="errors.get('send_mode')"/>
        </el-form-item>
    </div>
</template>

<script>
import InputPassword from '@/Pieces/InputPassword';
import Error from '@/Pieces/Error';

export default {
    name: 'MailChannels',
    props: ['connection', 'errors'],
    components: {
        InputPassword,
        Error
    },
    created() {
        if (!this.connection.send_mode) {
            this.$set(this.connection, 'send_mode', 'direct');
        }
    },
    watch: {
        'connection.key_store'(value) {
            if (value === 'wp_config') {
                this.connection.api_key = '';
            }
        }
    }
};
</script>
