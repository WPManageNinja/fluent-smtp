<template>
    <div>
        <el-form-item>
            <label for="sendinblue-key">
                API Key
            </label>
            
            <InputPassword
                id="sendinblue-key"
                v-model="connection.api_key"
            />

            <error :error="errors.get('api_key')" />

            <span class="small-help-text" style="display:block;margin-top:-10px">
                Follow this link to get an API Key:
                <a target="_blank" href="https://account.sendinblue.com/advanced/api">Get v3 API Key.</a>
            </span>
        </el-form-item>
    </div>
</template>

<script>
    import InputPassword from '@/Pieces/InputPassword';
    import Error from '@/Pieces/Error';

    export default {
        name: 'SendInBlue',
        props: ['connection', 'errors'],
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
