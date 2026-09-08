<template>
    <div>
        <h3 class="fs_config_title">{{ $t('Sendinblue API Settings') }}</h3>
        <el-radio-group size="small" v-model="connection.key_store">
            <el-radio-button value="db">{{ $t('Store in Database') }}</el-radio-button>
            <el-radio-button value="wp_config">{{ $t('Store in wp-config.php') }}</el-radio-button>
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
                <el-checkbox true-value="yes" false-value="no" v-model="connection.disable_encryption">
                    {{ $t('Disable Encryption for API Key (Not Recommended)') }}
                </el-checkbox>
                <p style="color: var(--fsm-danger-fg); margin-top: 0;" v-if="connection.disable_encryption === 'yes'">
                    {{
                        $t('Your API key will be stored as readable text in the database. Only turn this on if a security plugin on this site rotates the WordPress SALT keys, which would otherwise invalidate the encrypted value.')
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
            {{ $t('Get an API key:') }}
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
        /*
         * Vue 3 ignores an unknown top-level option, so this handler sat here for
         * years never running: choosing the config-file key store left the typed key
         * in the form and it was posted and stored in the database anyway.
         */
        watch: {
            'connection.key_store'(value) {
                if (value === 'wp_config') {
                    this.connection.api_key = '';
                }
            }
        },
        data() {
            return {
                // ...
            };
        }
    };
</script>
