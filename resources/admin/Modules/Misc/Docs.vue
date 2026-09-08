<template>
    <!--
        The documentation index, in the plugin.

        It used to be a support-page hero - "How can we help you?" centred over a
        search box - with the articles under it as three bordered cards of bullet
        links. The feed the site publishes now carries a one-line description for
        every article, and the categories run from one article to fifteen, which
        three equal cards could not hold: one card stood as tall as its single link
        while the next scrolled. So the page is sections now, one per category in the
        order the site's own sidebar uses, each a grid of tiles that say what the
        article covers before you leave for it.

        The articles are read on the site, not here. The feed carries Markdown, but
        the guides are screenshots and headings that belong on a page with a table of
        contents, and rendering them in a card would mean shipping a parser and a
        sanitiser to show a worse version of the same thing. Every tile opens the
        article in a new tab.
    -->
    <div class="fsm_docs">
        <div class="fsm_page_head">
            <div class="fsm_page_head_text">
                <h1 class="fsm_page_title">{{ $t('Documentation') }}</h1>
                <p class="fsm_page_desc" v-html="$t('__SUPPORT_INTRO')"></p>
            </div>
            <div class="fsm_page_actions">
                <!--
                    A field, not a form. The index is small enough to filter as it is
                    typed in, so there is no button and nothing to submit; the icon is
                    a mark of what the field is for.
                -->
                <el-input
                    class="fsm_docs_search"
                    clearable
                    :disabled="fetching || !!load_error"
                    v-model="search"
                    :placeholder="$t('Search the documentation')"
                    :aria-label="$t('Search the documentation')"
                >
                    <template #prefix>
                        <el-icon aria-hidden="true"><FsmIconSearch/></el-icon>
                    </template>
                </el-input>
            </div>
        </div>

        <el-alert v-if="load_error" type="error" :closable="false" show-icon class="fsm_load_error">
            <p>{{ load_error }}</p>
            <el-button size="small" @click="fetchDocs()">{{ $t('Retry') }}</el-button>
        </el-alert>

        <el-skeleton v-else-if="fetching" :animated="true" :rows="8"/>

        <!--
            Results replace the index rather than sitting above it. A query narrows
            the page to what matched; leaving the whole index under three hits is
            the old page's habit of showing everything at once.
        -->
        <div v-else-if="query" class="fsm_card fsm_docs_results">
            <div class="fsm_card_head">
                <div class="fsm_card_head_text">
                    <h2>{{ $t('Search Results for') }}: {{ search.trim() }}</h2>
                    <p v-if="search_items.length">
                        {{ search_items.length === 1 ? $t('1 article') : $t('%s articles', search_items.length) }}
                    </p>
                </div>
            </div>
            <div class="fsm_card_body">
                <ul v-if="search_items.length" class="fsm_docs_result_list">
                    <li v-for="doc in search_items" :key="doc.id">
                        <a target="_blank" rel="noopener" :href="doc.link + utl_param" class="fsm_docs_result">
                            <span class="fsm_docs_result_head">
                                <span class="fsm_docs_result_title">{{ doc.title }}</span>
                                <span class="fsm_tag is_neutral">{{ doc.category.label }}</span>
                            </span>
                            <span class="fsm_docs_result_desc">{{ doc.description }}</span>
                        </a>
                    </li>
                </ul>
                <p v-else class="fsm_docs_empty">
                    {{ $t('No documentation matched that.') }}
                    <a target="_blank" rel="noopener" :href="'https://fluentsmtp.com/docs/' + utl_param">
                        {{ $t('Browse the documentation site') }}
                    </a>
                </p>
            </div>
        </div>

        <template v-else>
            <!--
                The guides for the services this site sends through, first. The site
                cannot know which of fifteen provider guides you want; the plugin does,
                because it is holding the connection. The tile carries the provider's
                logo rather than a category label so the row reads as "your Gmail,
                your Outlook" and not as two more articles.
            -->
            <section v-if="suggested_docs.length" class="fsm_docs_section is_suggested">
                <div class="fsm_docs_section_head">
                    <h2>{{ $t('For your connections') }}</h2>
                    <p>{{ $t('The guides for the services this site sends through.') }}</p>
                </div>
                <div class="fsm_docs_grid">
                    <a v-for="doc in suggested_docs" :key="doc.id"
                       target="_blank" rel="noopener" :href="doc.link + utl_param" class="fsm_docs_tile">
                        <span class="fsm_docs_tile_logo">
                            <img v-if="doc.provider.image" :src="doc.provider.image" :alt="doc.provider.title"/>
                            <span v-else>{{ doc.provider.title }}</span>
                        </span>
                        <span class="fsm_docs_tile_title">{{ doc.title }}</span>
                        <span class="fsm_docs_tile_desc">{{ doc.description }}</span>
                    </a>
                </div>
            </section>

            <section v-for="cat in doc_cats" :key="cat.value" class="fsm_docs_section">
                <div class="fsm_docs_section_head">
                    <h2>{{ cat.label }}</h2>
                    <span class="fsm_docs_section_count">{{ cat.docs.length }}</span>
                </div>
                <div class="fsm_docs_grid">
                    <a v-for="doc in cat.docs" :key="doc.id"
                       target="_blank" rel="noopener" :href="doc.link + utl_param" class="fsm_docs_tile">
                        <span class="fsm_docs_tile_title">{{ doc.title }}</span>
                        <span class="fsm_docs_tile_desc">{{ doc.description }}</span>
                    </a>
                </div>
            </section>
        </template>
    </div>
</template>

<script type="text/babel">
    export default {
        name: 'Documentations',
        data() {
            return {
                search: '',
                fetching: false,
                load_error: '',
                docs: [],
                suggested: {},
                providers: {},
                utl_param: '?utm_source=wp&utm_medium=doc&utm_campaign=doc'
            }
        },
        computed: {
            query() {
                return this.search.trim().toLocaleLowerCase();
            },
            /*
             * Categories in the order the feed gives them, which is the order of the
             * site's sidebar. There used to be three hard-coded category ids here with
             * translated labels; the feed now names its own categories, and a list
             * that only knew three of them filed the other two under "Other".
             */
            doc_cats() {
                const cats = [];
                const byValue = {};
                this.docs.forEach((doc) => {
                    let cat = byValue[doc.category.value];
                    if (!cat) {
                        cat = byValue[doc.category.value] = {
                            value: doc.category.value,
                            label: doc.category.label,
                            docs: []
                        };
                        cats.push(cat);
                    }
                    cat.docs.push(doc);
                });
                return cats;
            },
            suggested_docs() {
                return this.docs
                    .filter(doc => this.suggested[doc.id])
                    .map(doc => ({
                        ...doc,
                        provider: this.providers[this.suggested[doc.id]] || {title: this.suggested[doc.id], image: ''}
                    }));
            },
            /*
             * Ranked, not just filtered: an article whose title carries the word comes
             * before one that only mentions it in the body, so "gmail" puts the Gmail
             * guide first rather than wherever the sidebar happened to put it.
             */
            search_items() {
                if (!this.query) {
                    return [];
                }
                const query = this.query;
                const rank = (doc) => {
                    if ((doc.title || '').toLocaleLowerCase().includes(query)) return 0;
                    if ((doc.description || '').toLocaleLowerCase().includes(query)) return 1;
                    if ((doc.content || '').toLocaleLowerCase().includes(query)) return 2;
                    return -1;
                };
                return this.docs
                    .map((doc, index) => ({doc, index, rank: rank(doc)}))
                    .filter(item => item.rank >= 0)
                    .sort((a, b) => a.rank - b.rank || a.index - b.index)
                    .map(item => item.doc);
            }
        },
        methods: {
            fetchDocs() {
                this.fetching = true;
                this.load_error = '';
                this.$get('docs')
                    .then(response => {
                        this.docs = response.docs;
                        this.suggested = response.suggested || {};
                        this.providers = response.providers || {};
                    })
                    .catch((errors) => {
                        this.load_error = this.$errorMessage(errors);
                    })
                    .always(() => {
                        this.fetching = false;
                    });
            }
        },
        mounted() {
            this.fetchDocs();
        }
    }
</script>
