<template>
    <div class="fsm_chart_tabs" role="group" :aria-label="$t('Chart type')">
        <button v-for="tab in tabs" :key="tab.type" type="button"
                class="fsm_chart_tab"
                :class="{is_active: chartType.current === tab.type}"
                :aria-pressed="chartType.current === tab.type"
                :title="tab.label"
                :aria-label="tab.label"
                @click="chartType.set(tab.type)">
            <svg v-if="tab.type === 'bar'" width="14" height="14" viewBox="0 0 14 14" aria-hidden="true"
                 fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round">
                <path d="M2.5 11.5V7"/>
                <path d="M7 11.5V2.5"/>
                <path d="M11.5 11.5V5.5"/>
            </svg>
            <svg v-else width="14" height="14" viewBox="0 0 14 14" aria-hidden="true"
                 fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"
                 stroke-linejoin="round">
                <path d="M1.5 10L5 6.5L7.5 9L12.5 3.5"/>
            </svg>
        </button>
    </div>
</template>

<script type="text/babel">
import chartType from './chartType';

export default {
    name: 'ChartTypeToggle',
    data() {
        return {
            chartType,
            tabs: [
                {type: 'bar', label: this.$t('Bar chart')},
                {type: 'line', label: this.$t('Line chart')}
            ]
        };
    }
};
</script>

<style lang="scss">
.fsm_chart_tabs {
    display: inline-flex;
    align-items: center;
    gap: 2px;
    padding: 2px;
    border: 1px solid var(--fsm-border);
    border-radius: 6px;
    background: var(--fsm-surface-sunk);

    .fsm_chart_tab {
        display: flex;
        align-items: center;
        justify-content: center;
        width: 26px;
        height: 22px;
        padding: 0;
        border: 0;
        border-radius: 4px;
        background: none;
        color: var(--fsm-text-light);
        cursor: pointer;

        &:hover {
            color: var(--fsm-text);
        }

        &.is_active {
            background: var(--fsm-surface);
            color: var(--fsm-text);
            box-shadow: var(--fsm-shadow-card);
        }

        &:focus-visible {
            outline: 2px solid var(--fsm-accent);
            outline-offset: 1px;
        }
    }
}
</style>
