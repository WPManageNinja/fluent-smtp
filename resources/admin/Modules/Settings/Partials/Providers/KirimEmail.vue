<template>
    <div>
        <h3 class="fs_config_title">Kirim Email API Settings</h3>
        <el-radio-group size="mini" v-model="connection.key_store">
            <el-radio-button label="db">Store API Keys in DB</el-radio-button>
            <el-radio-button label="wp_config">Store API Keys in Config File</el-radio-button>
        </el-radio-group>

        <el-row v-if="connection.key_store == 'db'" :gutter="20">
            <el-col :span="12">
                <el-form-item>
                    <label for="key">
                        Private API Key
                    </label>

                    <InputPassword
                        id="key"
                        v-model="connection.api_key"
                    />
                    
                    <error :error="errors.get('api_key')" />

                    <span class="small-help-text">
                        Follow this link to get an API Key from Kirim.Email:
                        <a
                            target="_blank"
                            href="https://docs.kirim.email/en/kb/private-api-keys/"
                        >Get a Private API Key.</a>
                    </span>
                </el-form-item>
            </el-col>

            <el-col :span="12">
                <el-form-item for="domain">
                    <label for="domain">
                        Domain Name
                    </label>
                    
                    <el-input id="domain" v-model="connection.domain_name" />
                    <error :error="errors.get('domain_name')" />

                    <span class="small-help-text">
                        Follow this link to get a Domain Name from Kirim.Email:
                        <a target="_blank" href="https://docs.kirim.email/en/kb/add-domain-for-transactional-email/">
                            Get a Domain Name.
                        </a>
                    </span>
                </el-form-item>
            </el-col>
        </el-row>

        <div class="fss_condesnippet_wrapper" v-else-if="connection.key_store == 'wp_config'">
            <el-form-item>
                <label>Simply copy the following snippet and replace the stars with the corresponding credential. Then simply paste to wp-config.php file of your WordPress installation</label>
                <div class="code_snippet">
                    <textarea readonly style="width: 100%;">define( 'FLUENTMAIL_KIRIMEMAIL_API_KEY', '********************' );
define( 'FLUENTMAIL_KIRIMEMAIL_DOMAIN', '********************' );</textarea>
                </div>
                <error :error="errors.get('api_key')" />
                <error :error="errors.get('domain_name')" />
            </el-form-item>
        </div>
    </div>
</template>

<script>
    import InputPassword from '@/Pieces/InputPassword';
    import Error from '@/Pieces/Error';

    export default {
        name: 'KirimEmail',
        props: ['connection', 'errors'],
        components: {
            InputPassword,
            Error
        },
        watch: {
            'connection.key_store'(value) {
                if (value === 'wp_config') {
                    this.connection.api_key = '';
                    this.connection.domain_name = '';
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
