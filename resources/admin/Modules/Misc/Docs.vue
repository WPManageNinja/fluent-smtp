<template>
    <div class="fc_docs">
        <div style="max-width: 800px; margin: 50px auto; padding: 0px 20px; text-align: center;" class="fc_doc_header text-align-center">
            <h1>How can we help you?</h1>
            <p>Please view the <a href="https://fluentsmtp.com/docs" target="_blank" rel="noopener">documentation</a> first. If you still can't find the
                answer <a href="https://wpmanageninja.com/support-tickets/" target="_blank" rel="noopener">open a support ticket</a> and we will be
                happy to answer your questions and assist you with any problems.</p>
            <el-input
                v-loading="fetching"
                clearable
                :disabled="fetching"
                size="large"
                v-model="search"
                :placeholder="$t('Search Type and Enter...')"
            >
                <el-button slot="append" icon="el-icon-search"></el-button>
            </el-input>
            <div v-if="search" class="search_result">
                <div class="fc_doc_items">
                    <div class="fc_doc_header">
                        <h3>{{$t('Search Results for')}}: {{ search }}</h3>
                    </div>
                    <div class="fc_doc_lists">
                        <ul v-if="search_items.length">
                            <li v-for="doc in search_items" :key="doc.id">
                                <a target="_blank" :href="doc.link + utl_param" v-html="doc.title"></a>
                            </li>
                        </ul>
                        <p v-else>Sorry! No docs found</p>
                    </div>
                </div>
            </div>
        </div>
        <div v-if="!fetching" class="doc_body">
            <div class="doc_each_items" v-for="(docItems, docIndex) in doc_cats" :key="docIndex">
                <div class="fc_doc_items">
                    <div class="fc_doc_header">
                        <h3>{{ docItems.label }}</h3>
                    </div>
                    <div class="fc_doc_lists">
                        <ul>
                            <li v-for="doc in docItems.docs" :key="doc.id">
                                <a target="_blank" :href="doc.link + utl_param" v-html="doc.title"></a>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>

        <el-skeleton :animated="true" v-else class="doc_body content" :rows="8"></el-skeleton>

    </div>
</template>

<script type="text/babel">
    import each from 'lodash/each';
    import filter from 'lodash/filter';

    export default {
        name: 'Documentations',
        data() {
            return {
                search: '',
                fetching: false,
                docs: [],
                utl_param: '?utm_source=wp&utm_medium=doc&utm_campaign=doc'
            }
        },
        computed: {
            doc_cats() {
                if (!this.docs.length) {
                    return [];
                }

                const items = {
                    item_4: {
                        label: 'Getting Started',
                        docs: []
                    },
                    item_5: {
                        label: 'Connect With Your Email Providers',
                        docs: []
                    },
                    item_6: {
                        label: 'Functionalities',
                        docs: []
                    }
                };
                each(this.docs, (doc) => {
                    const keyName = 'item_' + doc.category.value;
                    if (!items[keyName]) {
                        items[keyName] = {
                            label: doc.category.label,
                            cat_id: doc.category.value,
                            docs: []
                        }
                    }
                    items[keyName].docs.push(doc);
                });
                return Object.values(items);
            },
            search_items() {
                if (!this.search || !this.docs.length) {
                    return [];
                }

                return filter(this.docs, (item) => {
                    return item.title.includes(this.search) || item.content.includes(this.search);
                });
            }
        },
        methods: {
            openSearch() {

            },
            fetchDocs() {
                this.fetching = true;
                this.$get('docs')
                    .then(response => {
                        this.docs = response.docs;
                    })
                    .catch((errors) => {
                        console.log(errors);
                    })
                    .always(() => {
                        this.fetching = false;
                    });
            },
            $t(string) {
                return string;
            }
        },
        mounted() {
            this.fetchDocs();
        }
    }
</script>
