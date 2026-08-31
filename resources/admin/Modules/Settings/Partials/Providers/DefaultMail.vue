<template>
    <div>
        <ReturnPath :schema="schema" />

        <el-alert type="warning" :closable="false" style="margin:0px 0px 20px;">
            <el-icon style="font-size:15px;"><FsmIconWarning /></el-icon>
            {{ $t('__DEFAULT_MAIl_WARNING') }}
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
