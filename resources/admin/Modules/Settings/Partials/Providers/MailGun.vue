<template>
    <div>
        <h3 class="fs_config_title">Mailgun API Settings</h3>
        <el-radio-group size="mini" v-model="connection.key_store">
            <el-radio-button label="db">Store API Keys in DB</el-radio-button>
            <el-radio-button label="wp_config">Store API Keys in Config File</el-radio-button>
        </el-radio-group>

        <el-row v-if="connection.key_store == 'db'" :gutter="20">
            <el-col :md="12" :sm="24">
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
                        Follow this link to get an API Key from Mailgun:
                        <a
                            target="_blank"
                            href="https://app.mailgun.com/app/account/security/api_keys"
                        >Get a Private API Key.</a>
                    </span>
                </el-form-item>
            </el-col>

            <el-col :md="12" :sm="24">
                <el-form-item for="domain">
                    <label for="domain">
                        Domain Name
                    </label>
                    
                    <el-input id="domain" v-model="connection.domain_name" />
                    <error :error="errors.get('domain_name')" />

                    <span class="small-help-text">
                        Follow this link to get a Domain Name from Mailgun:
                        <a target="_blank" href="https://app.mailgun.com/app/domains">
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
                    <textarea readonly style="width: 100%;">define( 'FLUENTMAIL_MAILGUN_API_KEY', '********************' );
define( 'FLUENTMAIL_MAILGUN_DOMAIN', '********************' );</textarea>
                </div>
                <error :error="errors.get('api_key')" />
                <error :error="errors.get('domain_name')" />
            </el-form-item>
        </div>

        <el-form-item>
            <label for="region" style="vertical-align:baseline;">
                Select Region&nbsp;&nbsp;&nbsp;&nbsp;
            </label>

            <el-radio v-model="connection.region" label="us">US</el-radio>
            <el-radio v-model="connection.region" label="eu">EU</el-radio>
            
            <el-alert :closable="false">
                <span>
                    Define which endpoint you want to use for sending messages.
                </span>
                
                <span>
                    If you are operating under EU laws, you may be required to use EU region.
                    <a target="_blank" href="https://www.mailgun.com/regions">More information</a>
                    on Mailgun.com.
                </span>
            </el-alert>
        </el-form-item>
    </div>
</template>

<script>
    import InputPassword from '@/Pieces/InputPassword';
    import Error from '@/Pieces/Error';

    export default {
        name: 'MailGun',
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
