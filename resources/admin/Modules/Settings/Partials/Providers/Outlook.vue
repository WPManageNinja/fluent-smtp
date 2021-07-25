<template>
    <div>
        <h3>Outlook/Office365 API Settings</h3>
        <p>Please <a target="_blank" rel="nofollow" href="https://fluentsmtp.com/docs/setup-outlook-emails-with-fluentsmtp/">check the documentation first</a> or <b><a target="_blank" rel="nofollow" href="https://www.youtube.com/watch?v=_d78bscNaX8">Watch the video tutorial</a></b> to create API keys at Google</p>
        <el-radio-group size="mini" v-model="connection.key_store">
            <el-radio-button value="db" label="db">Store Application Keys in DB</el-radio-button>
            <el-radio-button value="wp_config" label="wp_config">Application Keys in Config File</el-radio-button>
        </el-radio-group>

        <el-row :gutter="20" v-if="connection.key_store == 'db'">
            <el-col :span="12">
                <el-form-item>
                    <label for="client_id">
                        Application Client ID
                    </label>

                    <InputPassword
                        id="client_id"
                        v-model="connection.client_id"
                    />

                    <error :error="errors.get('client_id')" />
                </el-form-item>
            </el-col>

            <el-col :span="12">
                <el-form-item>
                    <label for="client_secret">
                        Application Client Secret
                    </label>

                    <InputPassword
                        id="client_secret"
                        v-model="connection.client_secret"
                    />
                    <error :error="errors.get('client_secret')" />
                </el-form-item>
            </el-col>
        </el-row>

        <div class="fss_condesnippet_wrapper" v-else-if="connection.key_store == 'wp_config'">
            <el-form-item>
                <label>Simply copy the following snippet and replace the stars with the corresponding credential. Then simply paste to wp-config.php file of your WordPress installation</label>
                <div class="code_snippet">
                    <textarea readonly style="width: 100%;">define( 'FLUENTMAIL_OUTLOOK_CLIENT_ID', '********************' );
define( 'FLUENTMAIL_OUTLOOK_CLIENT_SECRET', '********************' );</textarea>
                </div>
                <error :error="errors.get('client_id')" />
                <error :error="errors.get('client_secret')" />
            </el-form-item>
        </div>

        <el-form-item>
            <label>App Callback URL (Use this URL to your APP)</label>
            <el-input :readonly="true" v-model="provider.callback_url" />
        </el-form-item>

        <div v-if="!connection.access_token">
            <div style="text-align: center;">
                <h3>Please authenticate with Office365 to get <b>Access Token</b></h3>
                <el-button v-loading="gettingRedirect" @click="redirectToMS()" type="danger">Authenticate with Office365 & Get Access Token</el-button>
            </div>
            <el-row v-if="redirectUrl" :gutter="20">
                <el-col :span="12">
                    <el-form-item>
                        <label for="application_token">
                            Access Token
                        </label>
                        <InputPassword
                            id="application_token"
                            v-model="connection.auth_token"
                        />
                        <error :error="errors.get('auth_token')" />
                        <p>Please send test email to confirm if the connection is working or not.</p>
                    </el-form-item>
                </el-col>
            </el-row>
        </div>
        <div style="text-align: center;" v-else>
            <h3>Your Outlook/Office365 Authentication has been enabled. No further action is needed. If you want to re-authenticate, <a @click.prevent="connection.access_token = ''" href="#">click here</a></h3>
        </div>

    </div>
</template>

<script type="text/babel">
    import InputPassword from '@/Pieces/InputPassword';
    import Error from '@/Pieces/Error';

    export default {
        name: 'OutLook',
        props: ['connection', 'provider', 'errors'],
        components: {
            InputPassword,
            Error
        },
        data() {
            return {
                app_ready: false,
                gettingRedirect: false,
                redirectUrl: ''
            };
        },
        watch: {
            'connection.key_store'(value) {
                if (value === 'wp_config') {
                    this.connection.client_id = '';
                    this.connection.client_secret = '';
                }
            }
        },
        methods: {
            redirectToMS() {
                this.gettingRedirect = true;
                this.$post('settings/outlook_auth_url', {
                    connection: this.connection
                })
                    .then(response => {
                        this.redirectUrl = response.data.auth_url;
                        window.open(response.data.auth_url, '_blank');
                    })
                    .catch(errors => {
                        this.errors.record(errors.responseJSON.data);
                    })
                    .always(() => {
                        this.gettingRedirect = false;
                    });
            }
        },
        mounted() {
            if (!this.connection.key_store) {
                this.$set(this.connection, 'key_store', 'db');
            }
        }
    };
</script>
