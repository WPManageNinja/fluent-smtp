<template>
    <div class="element-with-slot">
        <el-row>
            <el-col :span="4" class="left-slot">
                <slot name="label">Default Text</slot>
            </el-col>

            <el-col :span="20" class="right-slot">
                <slot name="element"></slot>
            </el-col>
        </el-row>
    </div>
</template>

<style>
    .element-with-slot {
        width:100%;
    }

    .element-with-slot .left-slot {
        background-color: #f5f7fa;
        color: #909399;
        border: 1px solid #dcdfe6;
        border-radius: 4px 0 0 4px;
        padding: 0px 20px;
        height: 40px;
    }

    .element-with-slot .el-select input,
    .element-with-slot .el-input input {
        background: transparent;
        border-radius: 0 4px 4px 0;
        margin-left: -1px;
    }
</style>
