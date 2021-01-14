<template>
    <span v-if="error" class="el-form-item__error">
        {{ error }}
    </span>
</template>
<script>
    export default {
        name: 'Error',
        props: ['error']
    }
</script>
