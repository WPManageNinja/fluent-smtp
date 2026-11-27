<template>
    <div>
        <h3 class="fs_config_title">{{ $t('Mailtrap API Settings') }}</h3>
        <el-radio-group size="small" v-model="connection.key_store">
            <el-radio-button value="db">{{ $t('Store in Database') }}</el-radio-button>
            <el-radio-button value="wp_config">{{ $t('Store in wp-config.php') }}</el-radio-button>
        </el-radio-group>

        <template v-if="connection.key_store == 'db'">
            <el-form-item>
                <label for="mailtrap-key">
                    {{ $t('API Token') }}
                </label>
                <InputPassword
                    id="mailtrap-key"
                    v-model="connection.api_key"
                    :disable_help="connection.disable_encryption === 'yes'"
                />
                <error :error="errors.get('api_key')"/>
            </el-form-item>
            <el-form-item>
                <el-checkbox true-value="yes" false-value="no" v-model="connection.disable_encryption">
                    {{ $t('Disable Encryption for API Token (Not Recommended)') }}
                </el-checkbox>
                <p style="color: var(--fsm-danger-fg); margin-top: 0;" v-if="connection.disable_encryption === 'yes'">
                    {{
                        $t('Your API token will be stored as readable text in the database. Only turn this on if a security plugin on this site rotates the WordPress SALT keys, which would otherwise invalidate the encrypted value.')
                    }}
                </p>
            </el-form-item>
        </template>

        <div class="fss_condesnippet_wrapper" v-else-if="connection.key_store == 'wp_config'">
            <el-form-item>
                <label>{{ $t('__WP_CONFIG_INSTRUCTION') }}</label>
                <div class="code_snippet">
                    <textarea readonly style="width: 100%;">define( 'FLUENTMAIL_MAILTRAP_API_KEY', '********************' );</textarea>
                </div>
                <error :error="errors.get('api_key')"/>
            </el-form-item>
        </div>

        <span class="small-help-text" style="display:block;margin-top:-10px">
            {{ $t('Get an API token from Mailtrap:') }}
            <a target="_blank" rel="noopener" href="https://mailtrap.io/settings/api-tokens">{{ $t('Create API Token.') }}</a>
        </span>

        <el-row class="fsmtp_compact" :gutter="30">
            <el-col :span="12">
                <el-form-item :label="$t('Message Stream')">
                    <el-radio-group v-model="connection.message_stream">
                        <el-radio value="transactional">{{ $t('Transactional') }}</el-radio>
                        <el-radio value="bulk">{{ $t('Bulk') }}</el-radio>
                    </el-radio-group>
                </el-form-item>
            </el-col>
        </el-row>
    </div>
</template>

<script>
import InputPassword from '@/Pieces/InputPassword';
import Error from '@/Pieces/Error';

export default {
    name: 'Mailtrap',
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
