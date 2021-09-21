<template>
    <div>
        <h3 class="fs_config_title">ElasticMail API Settings</h3>
        <el-radio-group size="mini" v-model="connection.key_store">
            <el-radio-button label="db">Store API Keys in DB</el-radio-button>
            <el-radio-button label="wp_config">Store API Keys in Config File</el-radio-button>
        </el-radio-group>

        <el-form-item v-if="connection.key_store == 'db'">
            <label for="elasticmail-key">
                API Key
            </label>

            <InputPassword
                id="elasticmail-key"
                v-model="connection.api_key"
            />
            
            <error :error="errors.get('api_key')" />

        </el-form-item>

        <div class="fss_condesnippet_wrapper" v-else-if="connection.key_store == 'wp_config'">
            <el-form-item>
                <label>Simply copy the following snippet and replace the stars with the corresponding credential. Then simply paste to wp-config.php file of your WordPress installation</label>
                <div class="code_snippet">
                    <textarea readonly style="width: 100%;">define( 'FLUENTMAIL_ELASTICMAIL_API_KEY', '********************' );</textarea>
                </div>
                <error :error="errors.get('api_key')" />
            </el-form-item>
        </div>

        <span class="small-help-text" style="display:block;margin-top:-10px">
            Follow this link to get an API Key from ElasticMail:
            <a target="_blank" href="https://elasticemail.com/account#/settings/new/manage-api">Get API Key.</a>
        </span>

        <el-row class="fsmtp_compact" :gutter="30">
            <el-col :span="12">
                <el-form-item label="Email Type">
                    <el-radio-group
                        v-model="connection.mail_type"
                    >
                        <el-radio label="transactional">Transactional</el-radio>
                        <el-radio label="marketing">Marketing</el-radio>
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
