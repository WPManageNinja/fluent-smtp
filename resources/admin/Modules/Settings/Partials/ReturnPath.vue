<template>
    <div>
        <el-checkbox
            true-label="yes"
            false-label="no"
            v-model="schema.provider.options.return_path"
        >Set the return-path to match the From Email</el-checkbox>

        <el-alert :closable="false" style="margin:20px 0 20px;">
            Return Path indicates where non-delivery receipts - or bounce messages -
            are to be sent. If unchecked, bounce messages may be lost. With this enabled,
            you’ll be emailed using "From Email" if any messages bounce as a result of issues with the recipient’s email.
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
