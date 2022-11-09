<template>
    <div>
        <el-input
            :id="id"
            :type="type"
            :value="value"
            :place-holder="placeholder"
            :disabled="disabled"
            @input="$emit('input', $event)"
        >
        </el-input>
    </div>
</template>

<script>
    export default {
        name: 'InputPassword',
        props: ['value', 'id', 'placeholder', 'disabled'],
        data() {
            return {
                type: 'password',
                styleObject: {
                    'text-decoration': 'line-through'
                },
                src: window.FluentMail.appVars.image_url + '/eye-cross.png'
            };
        },
        methods: {
            toggle() {
                this.type = this.type === 'text' ? 'password' : 'text';
                this.styleObject['text-decoration'] = this.type === 'text' ? 'none' : 'line-through';
            }
        }
    };
</script>
