<template>
    <el-popover
        width="170"
        @hide="cancel"
        v-model="visible"
        :placement="placement">

        <p v-html="message"></p>

        <div class="action-buttons">
            <el-button
                size="small"
                link
                @click="cancel()">
                {{$t('cancel')}}
            </el-button>

            <el-button
                type="primary"
                size="small"
                @click="confirm()">
                {{ $t('confirm') }}
            </el-button>
        </div>

        <template #reference>
            <slot name="reference">
                <el-icon><FsmIconDelete /></el-icon>
            </slot>
        </template>
    </el-popover>
</template>

<script>
    export default {
        name: 'Confirm',
        props: {
            placement: {
                default: 'top-end'
            },
            message: {
                default: 'Are you sure to delete this?'
            }
        },
        data() {
            return {
                visible: false
            }
        },
        methods: {
            hide() {
                this.visible = false;
            },
            confirm() {
                this.hide();

                this.$emit('yes');
            },
            cancel() {
                this.hide();

                this.$emit('no');
            }
        }
    }
</script>
