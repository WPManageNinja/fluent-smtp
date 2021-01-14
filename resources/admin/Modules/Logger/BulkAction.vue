<template>
    <div style="float:left;margin-left:10px;">
        <el-row :gutter="10">
            <el-col :span="12">
                <el-select
                    clearable
                    v-model="action"
                    size="small"
                    placeholder="Bulk Action"
                    :disabled="!haslogs"
                >
                    <el-option value="deleteall" label="Delete All" />
                    <el-option
                        value="deleteselected"
                        label="Delete Selected"
                        v-if="selected.length"
                    />
                </el-select>
            </el-col>

            <el-col :span="2">
                <el-button
                    plain
                    size="small"
                    type="primary"
                    :disabled="!action"
                    @click="applyBulkAction"
                >Apply</el-button>
            </el-col>
        </el-row>
    </div>
</template>

<script>
    export default {
        name: 'BulkAction',
        props: ['selected', 'haslogs'],
        data() {
            return {
                action: ''
            };
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
