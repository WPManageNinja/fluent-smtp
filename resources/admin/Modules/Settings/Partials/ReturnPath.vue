<template>
    <div>
        <el-checkbox
            true-label="yes"
            false-label="no"
            v-model="schema.provider.options.return_path"
        >{{ $t('Set the return-path to match the From Email') }}</el-checkbox>

        <el-alert :closable="false" style="margin:20px 0 20px;">
            {{ $t('__RETURN_PATH_ALERT') }}
        </el-alert>
    </div>
</template>

<script>
    export default {
        name: 'ReturnPath',
        props: ['schema'],
        data() {
            return {
                
            };
        }
    };
</script>
