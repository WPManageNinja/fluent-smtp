<template>
    <el-popover
        width="170"
        @hide="cancel"
        v-model="visible"
        :placement="placement">

        <p v-html="message"></p>

        <div class="action-buttons">
            <el-button
                size="mini"
                type="text"
                @click="cancel()">
                {{$t('cancel')}}
            </el-button>

            <el-button
                type="primary"
                size="mini"
                @click="confirm()">
                {{ $t('confirm') }}
            </el-button>
        </div>

        <template slot="reference">
            <slot name="reference">
                <i class="el-icon-delete"/>
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
