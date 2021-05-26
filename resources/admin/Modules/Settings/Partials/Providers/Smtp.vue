<template>
    <div>
        <el-row :gutter="20">
            <el-col :span="12">
                <el-form-item>
                    <label for="host">
                        SMTP Host
                    </label>
                    <el-input id="host" v-model="connection.host" />
                    <error :error="errors.get('host')" />
                </el-form-item>
            </el-col>
            
            <el-col :span="12">
                <el-form-item>
                    <label for="port">
                        SMTP Port
                    </label>

                    <el-input id="port" v-model="connection.port" />
                    <error :error="errors.get('port')" />
                </el-form-item>
            </el-col>
        </el-row>

        <el-row :gutter="20">
            <el-col :span="24">
                <el-form-item style="margin: 20px 0">
                    <label>
                        Encryption
                    </label>

                    <div class="small-help-text" style="display:inline-block;">
                        (Select <strong>ssl</strong> on port <strong>465</strong>,
                        or <strong>tls</strong> on port <strong>25</strong> or <strong>587</strong>)
                    </div>

                    <div style="display:inline-block;margin-left: 20px;">
                        <el-radio v-model="connection.encryption" label="none">None</el-radio>
                        <el-radio v-model="connection.encryption" label="ssl">SSL</el-radio>
                        <el-radio v-model="connection.encryption" label="tls">TLS</el-radio>
                    </div>
                </el-form-item>
            </el-col>
        </el-row>

        <el-row :gutter="20">
            <el-col :span="24">
                <el-form-item>
                    <label for="auth">
                        Use Auto TLS
                    </label>

                    <el-switch
                        v-model="connection.auto_tls"
                        active-value="yes"
                        inactive-value="no">
                    </el-switch>

                    <span class="small-help-text">
                        (By default, the TLS encryption would be used if the server supports it. On some srvers, it could be a problem and may need to be disabled.)
                    </span>
                </el-form-item>
            </el-col>
        </el-row>

        <el-row :gutter="20">
            <el-col :span="24">
                <el-form-item>
                    <label for="auth">
                        Authentication
                    </label>

                    <el-switch
                        v-model="connection.auth"
                        active-value="yes"
                        inactive-value="no">
                    </el-switch>

                    <span class="small-help-text">
                        (If you need to provide your SMTP server's credentials (username and password) enable the authentication, in most cases this is required.)
                    </span>
                </el-form-item>
            </el-col>
        </el-row>

        <template v-if="connection.auth == 'yes'">
            <el-radio-group size="mini" v-model="connection.key_store">
                <el-radio-button value="db" label="db">Store Access Keys in DB</el-radio-button>
                <el-radio-button value="wp_config" label="wp_config">Access Keys in Config File</el-radio-button>
            </el-radio-group>

            <el-row :gutter="20" v-if="connection.key_store == 'db'" :class="{ disabled: connection.auth==='no' }">
                <el-col :span="12">
                    <el-form-item>
                        <label for="username">
                            SMTP Username
                        </label>

                        <InputPassword
                            id="username"
                            v-model="connection.username"
                            :disabled="isDisabledUsername"
                        />

                        <error :error="errors.get('username')" />
                    </el-form-item>
                </el-col>

                <el-col :span="12">
                    <el-form-item>
                        <label for="smtp-password">
                            SMTP Password
                        </label>

                        <InputPassword
                            id="smtp-password"
                            v-model="connection.password"
                            :disabled="isDisabledPassword"
                        />
                        <error :error="errors.get('password')" />
                    </el-form-item>
                </el-col>
            </el-row>

            <div class="fss_condesnippet_wrapper" v-else-if="connection.key_store == 'wp_config'">
                <el-form-item>
                    <label>Simply copy the following snippet and replace the stars with the corresponding credential. Then simply paste to wp-config.php file of your WordPress installation</label>
                    <div class="code_snippet">
                        <textarea readonly style="width: 100%;">define( 'FLUENTMAIL_SMTP_USERNAME', '********************' );
define( 'FLUENTMAIL_SMTP_PASSWORD', '********************' );</textarea>
                    </div>
                    <error :error="errors.get('username')" />
                    <error :error="errors.get('password')" />
                </el-form-item>
            </div>
        </template>
    </div>
</template>

<script>
    import InputPassword from '@/Pieces/InputPassword';
    import Error from '@/Pieces/Error';

    export default {
        name: 'Smtp',
        props: ['connection', 'errors'],
        components: {
            InputPassword,
            Error
        },
        data() {
            return {
                app_ready: false
            };
        },
        watch: {
            'connection.key_store'(value) {
                if (value === 'wp_config') {
                    this.connection.password = '';
                    this.connection.username = '';
                }
            }
        },
        computed: {
            isDisabledUsername() {
                return this.connection.auth === 'no';
            },
            isDisabledPassword() {
                return this.connection.auth === 'no';
            }
        },
        mounted() {
            if (!this.connection.key_store) {
                this.$set(this.connection, 'key_store', 'db');
            }
        }
    };
</script>
