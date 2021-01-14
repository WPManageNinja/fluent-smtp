<template>
    <div>
        <ReturnPath :schema="schema" />

        <el-alert type="warning" :closable="false" style="margin:0px 0px 20px;">
            <i class="el-icon-warning" style="font-size:15px;"></i>
            The Default (none) option does not use SMTP and will not improve email delivery on your site.
        </el-alert>
    </div>
</template>

<script>
    import ReturnPath from '../ReturnPath';
    
    export default {
        name: 'DefaultMail',
        props: ['schema'],
        components: {
            ReturnPath
        },
        data() {
            return {
                // ...
            };
        }
    };
</script>
