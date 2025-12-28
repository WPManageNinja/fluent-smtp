<template>
    <div>
        <h3 class="fs_config_title">{{ $t('Sendinblue API Settings') }}</h3>
        <el-radio-group size="mini" v-model="connection.key_store">
            <el-radio-button label="db">{{ $t('Store API Keys in DB') }}</el-radio-button>
            <el-radio-button label="wp_config">{{ $t('Store API Keys in Config File') }}</el-radio-button>
        </el-radio-group>

        <template v-if="connection.key_store == 'db'">
            <el-form-item>
                <label for="sendinblue-key">
                    {{ $t('API Key') }}
                </label>
                <InputPassword
                    id="sendinblue-key"
                    v-model="connection.api_key"
                    :disable_help="connection.disable_encryption === 'yes'"
                />
                <error :error="errors.get('api_key')" />
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
                    <textarea readonly style="width: 100%;">define( 'FLUENTMAIL_SENDINBLUE_API_KEY', '********************' );</textarea>
                </div>
                <error :error="errors.get('api_key')" />
            </el-form-item>
        </div>

        <span class="small-help-text" style="display:block;margin-top:-10px">
            {{ $t('Follow this link to get an API Key:') }}
            <a target="_blank" href="https://app.brevo.com/settings/keys/api">{{ $t('Get v3 API Key.') }}</a>
        </span>
    </div>
</template>

<script>
    import InputPassword from '@/Pieces/InputPassword';
    import Error from '@/Pieces/Error';

    export default {
        name: 'SendInBlue',
        props: ['connection', 'errors'],
        components: {
            InputPassword,
            Error
        },
        'connection.key_store'(value) {
            if (value === 'wp_config') {
                this.connection.api_key = '';
            }
        },
        data() {
            return {
                // ...
            };
        }
    };
</script>
