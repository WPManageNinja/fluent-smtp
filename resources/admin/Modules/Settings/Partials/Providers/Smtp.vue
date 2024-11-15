<template>
    <div>
        <el-row :gutter="20">
            <el-col :md="12" :sm="24">
                <el-form-item>
                    <label for="host">
                        {{ $t('SMTP Host') }}
                    </label>
                    <el-input :placeholder="$t('SMTP Host')" id="host" v-model="connection.host"/>
                    <error :error="errors.get('host')"/>
                </el-form-item>
            </el-col>
            <el-col :md="12" :sm="24">
                <el-form-item>
                    <label for="port">
                        {{ $t('SMTP Port') }}
                    </label>

                    <el-input :placeholder="$t('SMTP Port')" id="port" v-model="connection.port"/>
                    <error :error="errors.get('port')"/>
                </el-form-item>
            </el-col>
        </el-row>

        <el-row :gutter="20">
            <el-col :span="24">
                <el-form-item style="margin: 20px 0">
                    <label>
                        {{ $t('Encryption') }}
                    </label>

                    <div class="small-help-text" style="display:inline-block;">
                        Select <strong>ssl</strong> on port <strong>465</strong>, or <strong>tls</strong> on port <strong>25</strong> or <strong>587</strong>
                    </div>

                    <div style="display:inline-block;margin-left: 20px;">
                        <el-radio v-model="connection.encryption" label="none">{{ $t('None') }}</el-radio>
                        <el-radio v-model="connection.encryption" label="ssl">{{ $t('SSL') }}</el-radio>
                        <el-radio v-model="connection.encryption" label="tls">{{ $t('TLS') }}</el-radio>
                    </div>
                </el-form-item>
            </el-col>
        </el-row>

        <el-row :gutter="20">
            <el-col :span="24">
                <el-form-item>
                    <label for="auth">
                        {{ $t('Use Auto TLS') }}
                    </label>

                    <el-switch
                        v-model="connection.auto_tls"
                        active-value="yes"
                        inactive-value="no">
                    </el-switch>

                    <span class="small-help-text">
                        {{ $t('__TLS_HELP') }}
                    </span>
                </el-form-item>
            </el-col>
        </el-row>

        <el-row :gutter="20">
            <el-col :span="24">
                <el-form-item>
                    <label for="auth">
                        {{ $t('Authentication') }}
                    </label>

                    <el-switch
                        v-model="connection.auth"
                        active-value="yes"
                        inactive-value="no">
                    </el-switch>

                    <span class="small-help-text">
                        {{ $t('__SMTP_CRED_HELP') }}
                    </span>
                </el-form-item>
            </el-col>
        </el-row>

        <template v-if="connection.auth == 'yes'">
            <el-radio-group size="mini" v-model="connection.key_store">
                <el-radio-button value="db" label="db">{{ $t('Store Access Keys in DB') }}</el-radio-button>
                <el-radio-button value="wp_config" label="wp_config">{{ $t('Access Keys in Config File') }}</el-radio-button>
            </el-radio-group>

            <el-row :gutter="20" v-if="connection.key_store == 'db'" :class="{ disabled: connection.auth==='no' }">
                <el-col :span="12">
                    <el-form-item>
                        <label for="username">
                            {{ $t('SMTP Username') }}
                        </label>

                        <el-input type="text"
                                  id="username"
                                  :placeholder="$t('Your SMTP Username')"
                                  v-model="connection.username"
                                  :disabled="isDisabledUsername"
                        />

                        <error :error="errors.get('username')"/>
                    </el-form-item>
                </el-col>

                <el-col :span="12">
                    <el-form-item>
                        <label for="smtp-password">
                            {{ $t('SMTP Password') }}
                        </label>

                        <InputPassword
                            id="smtp-password"
                            v-model="connection.password"
                            :disabled="isDisabledPassword"
                        />
                        <error :error="errors.get('password')"/>
                    </el-form-item>
                </el-col>
            </el-row>

            <div class="fss_condesnippet_wrapper" v-else-if="connection.key_store == 'wp_config'">
                <el-form-item>
                    <label>{{ $t('__WP_CONFIG_INSTRUCTION') }}</label>
                    <div class="code_snippet">
                        <textarea readonly style="width: 100%;">define( 'FLUENTMAIL_SMTP_USERNAME', '********************' );
define( 'FLUENTMAIL_SMTP_PASSWORD', '********************' );</textarea>
                    </div>
                    <error :error="errors.get('username')"/>
                    <error :error="errors.get('password')"/>
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
