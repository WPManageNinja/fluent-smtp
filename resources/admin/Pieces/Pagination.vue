<template>
    <!--
        `slot` rather than Element Plus's own `total`, which prints the raw number
        ("Total 13761") in its own locale. This one is grouped like every other count
        in the app and goes through the plugin's translations.
    -->
    <el-pagination class="fluentcrm-pagination"
                   :background="false"
                   layout="slot, sizes, prev, pager, next"
                   @current-change="changePage"
                   @size-change="changeSize"
                   :hide-on-single-page="false"
                   v-model:current-page="pagination.current_page"
                   :page-sizes="page_sizes"
                   :page-size="pagination.per_page"
                   :total="pagination.total"
    >
        <span class="el-pagination__total">{{ $t('Total %s', formatCount(pagination.total)) }}</span>
    </el-pagination>
</template>

<script>
    export default {
        name: 'Pagination',
        props: {
            pagination: {
                required: true,
                type: Object
            }
        },
        computed: {
            page_sizes() {
                return [
                    10,
                    20,
                    50,
                    80,
                    100,
                    120,
                    150
                ];
            }
        },
        methods: {
            changePage(page) {
                this.pagination.current_page = page;

                this.$emit('fetch');
            },
            changeSize(size) {
                this.pagination.per_page = size;

                this.$emit('fetch');
            }
        }
    };
</script>
