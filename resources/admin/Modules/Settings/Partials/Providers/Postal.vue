<template>
    <div>
        <h2 class="fs_config_title">{{ $t('Postal API Settings') }}</h2>
        <el-radio-group size="small" v-model="connection.key_store">
            <el-radio-button value="db">{{ $t('Store in Database') }}</el-radio-button>
            <el-radio-button value="wp_config">{{ $t('Store in wp-config.php') }}</el-radio-button>
        </el-radio-group>

        <el-form-item>
            <label for="postal-server-url">
                {{ $t('Postal Server URL') }}
            </label>
            <el-input
                id="postal-server-url"
                type="url"
                :placeholder="$t('https://postal.example.com')"
                v-model="connection.server_url"
            />
            <error :error="errors.get('server_url')"/>
            <span class="small-help-text">
                {{ $t('__POSTAL_URL_HELP') }}
            </span>
        </el-form-item>

        <template v-if="connection.key_store == 'db'">
            <el-form-item>
                <label for="postal-key">
                    {{ $t('API Key') }}
                </label>

                <InputPassword
                    id="postal-key"
                    v-model="connection.api_key"
                    :disable_help="connection.disable_encryption === 'yes'"
                />
                <error :error="errors.get('api_key')"/>
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
                    <textarea readonly style="width: 100%;">define( 'FLUENTMAIL_POSTAL_API_KEY', '********************' );</textarea>
                </div>
                <error :error="errors.get('api_key')"/>
            </el-form-item>
        </div>

        <span class="small-help-text" style="display:block;margin-top:-10px">
            {{ $t('__POSTAL_HELP') }}
            <a target="_blank" rel="noopener" href="https://docs.postalserver.io/developer/api/">{{ $t('Postal API credentials.') }}</a>
        </span>
    </div>
</template>

<script>
import InputPassword from '@/Pieces/InputPassword';
import Error from '@/Pieces/Error';

export default {
    name: 'Postal',
    props: ['connection', 'errors'],
    components: {
        InputPassword,
        Error
    },
    watch: {
        'connection.key_store'(value) {
            if (value === 'wp_config') {
                this.connection.api_key = '';
            }
        }
    },
    data() {
        return {};
    }
};
</script>
