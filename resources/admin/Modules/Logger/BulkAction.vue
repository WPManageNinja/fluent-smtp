<template>
    <div style="float:left;margin-left:10px;">
        <el-row :gutter="10">
            <el-col :span="12">
                <el-select
                    clearable
                    v-model="action"
                    size="small"
                    :tplaceholder="$t('Bulk Action')"
                >
                    <el-option
                        value="deleteselected"
                        label="Delete Selected"
                        v-if="selected.length"
                    />
                    <el-option v-if="is_failed_selected" value="resend_selected" :label="$t('Resend Selected Emails')" />
                </el-select>
            </el-col>

            <el-col :span="2">
                <el-button
                    plain
                    size="small"
                    type="primary"
                    :disabled="!action"
                    @click="applyBulkAction"
                >{{$t('Apply')}}</el-button>
            </el-col>
        </el-row>
    </div>
</template>

<script>
    export default {
        name: 'BulkAction',
        props: ['selected'],
        data() {
            return {
                action: '',
                resending: false
            };
        },
        computed: {
            is_failed_selected() {
                return !!this.selected.length;
            }
        },
        methods: {
            applyBulkAction() {
                this.$emit('on-bulk-action', { action: this.action });
                this.action = '';
            }
        },
        watch: {
            selected: function(val) {
                if (this.action === 'deleteselected') {
                    this.action = val.length ? this.action : '';
                }
            }
        }
    };
</script>
