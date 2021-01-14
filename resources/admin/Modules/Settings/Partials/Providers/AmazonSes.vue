<template>
    <div>
        <el-row :gutter="20">
            <el-col :span="12">
                <el-form-item for="access_key">
                    <label for="access_key">
                        Access Key
                    </label>
                    
                    <InputPassword
                        id="access_key"
                        v-model="connection.access_key"
                    />

                    <error :error="errors.get('access_key')" />

                    <span class="small-help-text">
                        Follow this link to get an Access Key from AWS:
                        <a target="_blank" href="#">
                            Get a Access Key.
                        </a>
                    </span>
                </el-form-item>
            </el-col>

            <el-col :span="12">
                <el-form-item>
                    <label for="ses-key">
                        Secret Key
                    </label>

                    <InputPassword
                        id="ses-key"
                        v-model="connection.secret_key"
                    />

                    <error :error="errors.get('secret_key')" />

                    <span class="small-help-text">
                        Follow this link to get a Secret Key from AWS:
                        <a target="_blank" href="#">
                            Get a Secret Key.
                        </a>
                    </span>
                </el-form-item>
            </el-col>
        </el-row>

        <el-form-item>
            <label for="ses-region">
                Region <span
                    class="small-help-text"
                >(Default: US East (N. Virginia)/us-east-1)</span>
            </label>

            <el-select
                id="ses-region"
                v-model="connection.region"
                placeholder="Select Region"
            >
                <el-option
                    v-for="(label, value) in provider.regions"
                    :key="value"
                    :label="label"
                    :value="value">
                </el-option>
            </el-select>
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
        data() {
            return {
                // ...
            };
        }
    };
</script>
