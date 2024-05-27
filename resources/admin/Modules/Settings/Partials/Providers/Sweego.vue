<template>
    <div>
        <h3 class="fs_config_title">{{ $t('Sweego API Settings') }}</h3>
        <el-radio-group size="mini" v-model="connection.key_store">
            <el-radio-button label="db">{{ $t('Store API Keys in DB') }}</el-radio-button>
            <el-radio-button label="wp_config">{{ $t('Store API Keys in Config File') }}</el-radio-button>
        </el-radio-group>

        <el-form-item v-if="connection.key_store == 'db'">
            <label for="sweego-key">
                {{ $t('API Key') }}
            </label>
            <InputPassword
                id="sweego-key"
                v-model="connection.api_key"
            />
            <error :error="errors.get('api_key')" />
        </el-form-item>
        <div class="fss_condesnippet_wrapper" v-else-if="connection.key_store == 'wp_config'">
            <el-form-item>
                <label>{{ $t('Define in wp-config.php') }}</label>
                <div class="code_snippet">
                    <textarea readonly style="width: 100%;">define( 'SWEEGO_API_KEY', '********************' );</textarea>
                </div>
                <error :error="errors.get('api_key')" />
            </el-form-item>
        </div>

        <span class="small-help-text" style="display:block;margin-top:-10px">
            {{ $t('Follow this link to get an API Key from Sweego:') }}
            <a target="_blank" href="https://learn.sweego.io/docs/api-intro">{{ $t('Create API Key.') }}</a>
            {{ $t('To send emails you will need only a Mail Send access level for this API key.') }}
        </span>
    </div>
</template>

<script>
    import InputPassword from '@/Pieces/InputPassword';
    import Error from '@/Pieces/Error';

    export default {
        name: 'Sweego',
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
            return {
                // ...
            };
        }
    };
</script>
