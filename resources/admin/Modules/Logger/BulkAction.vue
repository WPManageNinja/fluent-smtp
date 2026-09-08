<template>
    <!--
        A flex pair, not an el-row.

        This used to be a 24-column el-row - the select on `span=12`, Apply on `span=2` -
        inside a floated div. A row's columns are percentages of the row's own width, and
        the row's width came from a float sitting in the page head's flex line, which
        gives a float the width of its content. So the select asked for half of nothing
        and rendered about 60px wide, showing "B." where "Bulk Action" should be.
    -->
    <div class="fsm_log_bulk">
        <el-select
            clearable
            v-model="action"
            class="fsm_log_bulk_select"
            :placeholder="$t('Bulk Action')"
        >
            <el-option
                value="deleteselected"
                :label="$t('Delete Selected')"
                v-if="selected.length"
            />
            <el-option v-if="is_failed_selected" value="resend_selected" :label="$t('Resend Selected')" />
        </el-select>

        <el-button
            plain
            type="primary"
            :disabled="!action"
            @click="applyBulkAction"
        >{{ $t('Apply') }}</el-button>
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
