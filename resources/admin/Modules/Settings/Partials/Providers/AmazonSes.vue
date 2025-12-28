<template>
    <div>
        <el-radio-group size="mini" v-model="connection.key_store">
            <el-radio-button label="db">{{ $t('Store Access Keys in DB') }}</el-radio-button>
            <el-radio-button label="wp_config">{{ $t('Access Keys in Config File') }}</el-radio-button>
        </el-radio-group>
        <el-row v-if="connection.key_store == 'db'" :gutter="20">
            <el-col :md="12" :sm="24">
                <el-form-item for="access_key">
                    <label for="access_key">
                        {{ $t('Access Key') }}
                    </label>
                    
                    <InputPassword
                        id="access_key"
                        v-model="connection.access_key"
                        :disable_help="connection.disable_encryption === 'yes'"
                    />

                    <error :error="errors.get('access_key')" />
                </el-form-item>
            </el-col>
            <el-col  :md="12" :sm="24">
                <el-form-item>
                    <label for="ses-key">
                        {{ $t('Secret Key') }}
                    </label>

                    <InputPassword
                        id="ses-key"
                        v-model="connection.secret_key"
                        :disable_help="connection.disable_encryption === 'yes'"
                    />

                    <error :error="errors.get('secret_key')" />
                </el-form-item>
            </el-col>

            <el-col :span="24">
                <el-form-item>
                    <el-checkbox true-label="yes" false-label="no" v-model="connection.disable_encryption">
                        {{ $t('Disable Encryption for Secret Key (Not Recommended)') }}
                    </el-checkbox>
                    <p style="color: red; margin-top: 0;" v-if="connection.disable_encryption === 'yes'">
                        {{
                            $t('By disabling encryption, your Secret Key will be stored in plain text in the database. This is not recommended for security reasons. Enable only if your security plugin rotate WP SALTS frequently.')
                        }}
                    </p>
                </el-form-item>
            </el-col>
        </el-row>
        <div class="fss_condesnippet_wrapper" v-else-if="connection.key_store == 'wp_config'">
            <el-form-item>
                <label>{{ $t('__WP_CONFIG_INSTRUCTION') }}</label>
                <div class="code_snippet">
                    <textarea readonly style="width: 100%;">define( 'FLUENTMAIL_AWS_ACCESS_KEY_ID', '********************' );
define( 'FLUENTMAIL_AWS_SECRET_ACCESS_KEY', '********************' );</textarea>
                </div>
                <error :error="errors.get('access_key')" />
                <error :error="errors.get('secret_key')" />
            </el-form-item>
        </div>

        <el-form-item>
            <label for="ses-region">
                {{ $t('Region ') }}<span
                    class="small-help-text"
                >{{ $t('(Default: US East(N.Virginia) / us - east - 1)') }}</span>
            </label>

            <el-select
                id="ses-region"
                v-model="connection.region"
                :placeholder="$t('Select Region')"
            >
                <el-option
                    v-for="(label, value) in provider.regions"
                    :key="value"
                    :label="label"
                    :value="value">
                </el-option>
            </el-select>
            <span
                class="el-form-item__error"
                style="margin-top: 10px;"
            >{{ errors.errors.api_error }}</span>
        </el-form-item>
    </div>
</template>

<script>
    import InputPassword from '@/Pieces/InputPassword';
    import Error from '@/Pieces/Error';

    export default {
        name: 'AmazonSes',
        props: ['connection', 'provider', 'errors'],
        components: {
            InputPassword,
            Error
        },
        watch: {
            'connection.key_store'(value) {
                if (value === 'wp_config') {
                    this.connection.access_key = '';
                    this.connection.secret_key = '';
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
