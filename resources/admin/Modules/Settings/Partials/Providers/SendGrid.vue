<template>
    <div>
        <el-form-item>
            <label for="sendgrid-key">
                API Key
            </label>
            
            <InputPassword
                id="sendgrid-key"
                v-model="connection.api_key"
            />

            <error :error="errors.get('api_key')" />

            <span class="small-help-text" style="display:block;margin-top:-10px">
                Follow this link to get an API Key from SendGrid:
                <a target="_blank" href="https://app.sendgrid.com/settings/api_keys">Create API Key.</a>
                To send emails you will need only a Mail Send access level for this API key.
            </span>
        </el-form-item>
    </div>
</template>

<script>
    import InputPassword from '@/Pieces/InputPassword';
    import Error from '@/Pieces/Error';

    export default {
        name: 'SendGrid',
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
