<template>
    <div>
        <h3 class="fs_config_title">{{ $t('ElasticEmail API Settings') }}</h3>
        <el-radio-group size="mini" v-model="connection.key_store">
            <el-radio-button label="db">{{ $t('Store API Keys in DB') }}</el-radio-button>
            <el-radio-button label="wp_config">{{ $t('Store API Keys in Config File') }}</el-radio-button>
        </el-radio-group>

        <el-form-item v-if="connection.key_store == 'db'">
            <label for="elasticmail-key">
                {{ $t('API Key') }}
            </label>

            <InputPassword
                id="elasticmail-key"
                v-model="connection.api_key"
            />
            
            <error :error="errors.get('api_key')" />

        </el-form-item>

        <div class="fss_condesnippet_wrapper" v-else-if="connection.key_store == 'wp_config'">
            <el-form-item>
                <label>{{ $t('__WP_CONFIG_INSTRUCTION') }}</label>
                <div class="code_snippet">
                    <textarea readonly style="width: 100%;">define( 'FLUENTMAIL_ELASTICMAIL_API_KEY', '********************' );</textarea>
                </div>
                <error :error="errors.get('api_key')" />
            </el-form-item>
        </div>

        <span class="small-help-text" style="display:block;margin-top:-10px">
            {{ $t('Follow this link to get an API Key from ElasticEmail: ') }}
            <a target="_blank" href="https://elasticemail.com/account#/settings/new/manage-api">{{ ('Get API Key.') }}</a>
        </span>

        <el-row class="fsmtp_compact" :gutter="30">
            <el-col :span="12">
                <el-form-item :label="$t('Email Type')">
                    <el-radio-group
                        v-model="connection.mail_type"
                    >
                        <el-radio label="transactional">{{ $t('Transactional') }}</el-radio>
                        <el-radio label="marketing">{{ $t('Marketing') }}</el-radio>
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
