<template>
    <div>
        <h3 class="fs_config_title">{{ $t('Postmark API Settings') }}</h3>
        <el-radio-group size="small" v-model="connection.key_store">
            <el-radio-button value="db">{{ $t('Store API Keys in DB') }}</el-radio-button>
            <el-radio-button value="wp_config">{{ $t('Store API Keys in Config File') }}</el-radio-button>
        </el-radio-group>

        <template v-if="connection.key_store == 'db'">
            <el-form-item>
                <label for="postmark-key">
                    {{ $t('API Key') }}
                </label>

                <InputPassword
                    id="postmark-key"
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
                        $t('By disabling encryption, your API key will be stored in plain text in the database. This is not recommended for security reasons. Enable only if your security plugin rotate WP SALTS frequently.')
                    }}
                </p>
            </el-form-item>
        </template>

        <div class="fss_condesnippet_wrapper" v-else-if="connection.key_store == 'wp_config'">
            <el-form-item>
                <label>{{ $t('__WP_CONFIG_INSTRUCTION') }}</label>
                <div class="code_snippet">
                    <textarea readonly style="width: 100%;">define( 'FLUENTMAIL_POSTMARK_API_KEY', '********************' );</textarea>
                </div>
                <error :error="errors.get('api_key')"/>
            </el-form-item>
        </div>

        <span class="small-help-text" style="display:block;margin-top:-10px">
            {{ $t('__POSTMARK_HELP') }}
            <a target="_blank" href="https://account.postmarkapp.com/servers">Postmark Server.</a>
        </span>

        <el-row class="fsmtp_compact" :gutter="30">
            <el-col :md="12" :sm="24">
                <el-form-item :label="$t('Track Opens')">
                    <el-checkbox
                        true-value="yes"
                        false-value="no"
                        v-model="connection.track_opens"
                    >
                        {{ $t('Enable email opens tracking on postmark(For HTML Emails only).') }}
                        <el-tooltip effect="dark" placement="top-start">
                            <template #content>
                                <div>
                                    {{ $t('__POSTMARK_OPEN') }}
                                </div>
                            </template>
                            <el-icon><FsmIconInfo /></el-icon>
                        </el-tooltip>
                    </el-checkbox>
                </el-form-item>
                <el-form-item :label="$t('Message Stream')">
                    <el-input type="text" v-model="connection.message_stream"/>
                </el-form-item>
            </el-col>
            <el-col :md="12" :sm="24">
                <el-form-item label="Track Links">
                    <el-checkbox
                        true-value="yes"
                        false-value="no"
                        v-model="connection.track_links"
                    >
                        {{ $t('Enable link tracking on postmark (For HTML Emails only).') }}
                        <el-tooltip effect="dark" placement="top-start">
                            <template #content>
                                <div>
                                    {{ $t('__POSTMARK_CLICK') }}
                                </div>
                            </template>
                            <el-icon><FsmIconInfo /></el-icon>
                        </el-tooltip>
                    </el-checkbox>
                </el-form-item>
            </el-col>
        </el-row>
    </div>
</template>

<script>
import InputPassword from '@/Pieces/InputPassword';
import Error from '@/Pieces/Error';

export default {
    name: 'PostMark',
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
