<template>
    <div>
        <el-form-item>
            <label for="pepipost-key">
                API Key
            </label>

            <InputPassword
                id="pepipost-key"
                v-model="connection.api_key"
            />
            
            <error :error="errors.get('api_key')" />

            <span class="small-help-text" style="display:block;margin-top:-10px">
                Follow this link to get an API Key from Pepipost (Click Show button on Settings Page):
                <a target="_blank" href="https://app.pepipost.com/app/settings/integration">Get API Key.</a>
            </span>
        </el-form-item>
    </div>
</template>

<script>
    import InputPassword from '@/Pieces/InputPassword';
    import Error from '@/Pieces/Error';

    export default {
        name: 'PepiPost',
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
