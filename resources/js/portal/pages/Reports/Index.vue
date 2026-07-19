<template>
    <div class="p-6 space-y-6">
        <div class="flex items-center justify-between">
            <h1 class="text-2xl font-bold text-slate-800 flex items-center gap-3">
                <FileText class="text-indigo-600 w-7 h-7" /> Reports
            </h1>
        </div>

        <div class="flex gap-4 border-b">
            <button v-for="t in tabs" :key="t.key"
                @click="activeTab = t.key; fetchData()"
                :class="[activeTab === t.key ? 'border-indigo-600 text-indigo-600' : 'border-transparent text-slate-500 hover:text-slate-700']"
                class="pb-3 border-b-2 text-sm font-semibold transition">
                {{ t.label }}
            </button>
        </div>

        <!-- Summary Cards -->
        <div v-if="summary" class="grid grid-cols-5 gap-4">
            <div v-for="(val, key) in summary" :key="key" class="bg-white border rounded-xl p-4 shadow-sm">
                <p class="text-[10px] font-bold uppercase tracking-widest text-slate-400">{{ formatLabel(key) }}</p>
                <p class="text-2xl font-bold text-slate-800 mt-1">{{ formatValue(key, val) }}</p>
            </div>
        </div>

        <!-- Filters -->
        <div class="flex flex-wrap items-center gap-3 bg-white border rounded-xl p-4 shadow-sm">
            <div class="flex items-center gap-2">
                <label class="text-[10px] font-bold text-slate-500 uppercase">From</label>
                <input type="date" v-model="filters.from" @change="fetchData"
                    class="border rounded-lg px-2 py-1.5 text-xs w-36">
            </div>
            <div class="flex items-center gap-2">
                <label class="text-[10px] font-bold text-slate-500 uppercase">To</label>
                <input type="date" v-model="filters.to" @change="fetchData"
                    class="border rounded-lg px-2 py-1.5 text-xs w-36">
            </div>
            <div class="flex items-center gap-2" v-if="activeTab !== 'summary'">
                <label class="text-[10px] font-bold text-slate-500 uppercase">Status</label>
                <select v-model="filters.status" @change="fetchData" class="border rounded-lg px-2 py-1.5 text-xs">
                    <option value="">All</option>
                    <option value="pending">Pending</option>
                    <option value="accepted">Accepted</option>
                    <option value="executed">Executed</option>
                    <option value="completed">Completed</option>
                    <option value="disputed">Disputed</option>
                    <option value="cancelled">Cancelled</option>
                </select>
            </div>
            <div class="flex items-center gap-2" v-if="activeTab === 'debts'">
                <label class="text-[10px] font-bold text-slate-500 uppercase">Side</label>
                <select v-model="filters.side" @change="fetchData" class="border rounded-lg px-2 py-1.5 text-xs">
                    <option value="">All Debts</option>
                    <option value="my_debts">Requester Debt</option>
                    <option value="owed_to_me">Executor Debt</option>
                </select>
            </div>
            <div v-if="activeTab !== 'summary'" class="flex items-center gap-2">
                <label class="text-[10px] font-bold text-slate-500 uppercase">Has Debt</label>
                <select v-model="filters.has_debt" @change="fetchData" class="border rounded-lg px-2 py-1.5 text-xs">
                    <option value="">All</option>
                    <option value="1">With Debt</option>
                </select>
            </div>
            <div class="flex-1"></div>
            <div class="flex items-center gap-2">
                <button @click="exportReport('xlsx')"
                    class="bg-emerald-600 text-white px-3 py-1.5 rounded-lg text-xs font-semibold hover:bg-emerald-700 transition flex items-center gap-1.5">
                    <Download class="w-3.5 h-3.5" /> XLSX
                </button>
                <button @click="exportReport('csv')"
                    class="bg-sky-600 text-white px-3 py-1.5 rounded-lg text-xs font-semibold hover:bg-sky-700 transition flex items-center gap-1.5">
                    <Download class="w-3.5 h-3.5" /> CSV
                </button>
                <button @click="exportReport('pdf')" v-if="activeTab === 'remittances'"
                    class="bg-rose-600 text-white px-3 py-1.5 rounded-lg text-xs font-semibold hover:bg-rose-700 transition flex items-center gap-1.5">
                    <FileText class="w-3.5 h-3.5" /> PDF
                </button>
            </div>
        </div>

        <!-- Table -->
        <div class="bg-white border rounded-xl shadow-sm overflow-hidden">
            <table class="w-full text-left">
                <thead class="bg-slate-50 text-xs font-semibold text-slate-500 uppercase border-b">
                    <tr>
                        <th class="p-3" v-for="col in columns" :key="col.key">{{ col.label }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <tr v-for="row in rows" :key="row.id || row.reference" class="hover:bg-indigo-50/40 transition-colors">
                        <td class="p-3 text-sm" v-for="col in columns" :key="col.key">
                            <template v-if="col.key === 'status'">
                                <span :class="statusClass(row.status)" class="text-[10px] font-bold px-2 py-1 rounded uppercase">{{ row.status }}</span>
                            </template>
                            <template v-else-if="col.key === 'amount'">
                                {{ formatNumber(row.send_amount) }} {{ row.send_currency || 'USD' }}
                            </template>
                            <template v-else-if="col.key === 'reference'">
                                <span class="text-xs font-semibold text-slate-700 bg-slate-100 px-2 py-1 rounded-lg">#{{ row.reference }}</span>
                            </template>
                            <template v-else-if="col.key === 'debt_side'">
                                <span v-if="row.requester_debt && row.executor_debt" class="text-rose-600 font-bold text-xs">Both</span>
                                <span v-else-if="row.requester_debt" class="text-amber-600 font-bold text-xs">Requester</span>
                                <span v-else-if="row.executor_debt" class="text-orange-600 font-bold text-xs">Executor</span>
                                <span v-else class="text-slate-400 text-xs">None</span>
                            </template>
                            <template v-else-if="col.key === 'completion_rate'">
                                <span :class="row.completion_rate >= 80 ? 'text-emerald-600' : row.completion_rate >= 50 ? 'text-amber-600' : 'text-rose-600'" class="font-bold text-sm">{{ row.completion_rate }}%</span>
                            </template>
                            <template v-else-if="col.key === 'average_rating'">
                                <span class="text-sm">{{ row.average_rating || '—' }}</span>
                            </template>
                            <template v-else-if="col.key === 'created_at'">
                                <span class="text-sm text-slate-700">{{ formatDate(row.created_at) }}</span>
                            </template>
                            <template v-else>
                                <span class="text-sm text-slate-700">{{ row[col.key] || '—' }}</span>
                            </template>
                        </td>
                    </tr>
                    <tr v-if="loading">
                        <td :colspan="columns.length" class="p-12 text-center text-slate-400 text-sm">Loading...</td>
                    </tr>
                    <tr v-if="!loading && rows.length === 0">
                        <td :colspan="columns.length" class="p-12 text-center text-slate-400 text-sm font-medium">No data found</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div v-if="lastPage > 1" class="flex justify-center gap-2">
            <button @click="changePage(currentPage - 1)" :disabled="currentPage <= 1"
                class="px-3 py-1.5 rounded border text-xs font-medium disabled:opacity-30 bg-white hover:bg-slate-50 transition">Prev</button>
            <span class="px-3 py-1.5 text-xs text-slate-500">Page {{ currentPage }} of {{ lastPage }}</span>
            <button @click="changePage(currentPage + 1)" :disabled="currentPage >= lastPage"
                class="px-3 py-1.5 rounded border text-xs font-medium disabled:opacity-30 bg-white hover:bg-slate-50 transition">Next</button>
        </div>
    </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue';
import { FileText, Download } from 'lucide-vue-next';
import { api } from '../../../plugins/axios';

const tabs = [
    { key: 'remittances', label: 'Remittances', columnSets: { remittances: [
        { key: 'reference', label: 'Ref' },
        { key: 'initiator.name', label: 'Requester' },
        { key: 'agent.name', label: 'Agent' },
        { key: 'amount', label: 'Amount' },
        { key: 'status', label: 'Status' },
        { key: 'debt_side', label: 'Debt' },
        { key: 'created_at', label: 'Date' },
    ]}},
    { key: 'debts', label: 'Debts', columnSets: { debts: [
        { key: 'reference', label: 'Ref' },
        { key: 'initiator.name', label: 'Requester' },
        { key: 'agent.name', label: 'Agent' },
        { key: 'amount', label: 'Amount' },
        { key: 'debt_side', label: 'Debt Side' },
        { key: 'status', label: 'Status' },
        { key: 'created_at', label: 'Date' },
    ]}},
    { key: 'agent-performance', label: 'Agent Performance', columnSets: { 'agent-performance': [
        { key: 'name', label: 'Name' },
        { key: 'total_jobs', label: 'Total Jobs' },
        { key: 'completion_rate', label: 'Completion' },
        { key: 'active_debts', label: 'Active Debts' },
        { key: 'average_rating', label: 'Rating' },
        { key: 'is_agent_available', label: 'Available' },
    ]}},
    { key: 'summary', label: 'Platform Summary', columnSets: { summary: [] }},
];

const activeTab = ref('remittances');
const rows = ref([]);
const summary = ref(null);
const loading = ref(true);
const currentPage = ref(1);
const lastPage = ref(1);
const currentTabConfig = ref(tabs[0]);

const filters = reactive({
    from: '',
    to: '',
    status: '',
    side: '',
    has_debt: '',
});

const columns = ref(currentTabConfig.value.columnSets.remittances);

onMounted(() => {
    fetchData();
});

async function fetchData() {
    loading.value = true;
    const tab = tabs.find(t => t.key === activeTab.value);
    currentTabConfig.value = tab;
    columns.value = tab?.columnSets?.[tab.key] || [];

    if (activeTab.value === 'summary') {
        await fetchSummary();
        loading.value = false;
        return;
    }

    const params = { page: currentPage.value, ...buildParams() };

    try {
        const { data } = await api.get(`portal/reports/${activeTab.value}`, { params });
        rows.value = data.data || [];
        currentPage.value = data.current_page || 1;
        lastPage.value = data.last_page || 1;
        summary.value = extractSummary(data);
    } catch (e) {
        console.error('Failed to fetch report', e);
        rows.value = [];
    } finally {
        loading.value = false;
    }
}

async function fetchSummary() {
    try {
        const params = {};
        if (filters.from) params.from = filters.from;
        if (filters.to) params.to = filters.to;
        const { data } = await api.get('portal/reports/platform-summary', { params });
        summary.value = data;
        rows.value = [];
    } catch (e) {
        console.error('Failed to fetch summary', e);
    }
}

function buildParams() {
    const p = {};
    if (filters.from) p.from = filters.from;
    if (filters.to) p.to = filters.to;
    if (filters.status) p.status = filters.status;
    if (filters.side) p.side = filters.side;
    if (filters.has_debt) p.has_debt = filters.has_debt;
    return p;
}

function extractSummary(data) {
    return {
        total: data.summary_total,
        volume: data.summary_volume,
        completed: data.summary_completed,
        completion_rate: data.summary_completion_rate,
        debts: data.summary_debts,
    };
}

function exportReport(format) {
    const params = new URLSearchParams(buildParams());
    params.set('format', format);
    window.open(`/api/portal/reports/${activeTab.value}/export?${params}`, '_blank');
}

function changePage(page) {
    currentPage.value = page;
    fetchData();
}

function formatLabel(key) {
    return key.replace(/summary_/, '').replace(/_/g, ' ');
}

function formatValue(key, val) {
    if (key.includes('volume') || key.includes('debt') || key === 'requester_debt_volume' || key === 'executor_debt_volume') {
        return '$' + formatNumber(val);
    }
    if (key.includes('rate')) return val + '%';
    if (key === 'total_agents' || key === 'active_agents' || key === 'total_remittances' || key === 'debts_count' || key === 'completed_count') return val;
    return val;
}

function formatDate(d) {
    if (!d) return '—';
    const date = new Date(d);
    return date.toLocaleDateString('en-US', {
        year: 'numeric', month: 'short', day: 'numeric',
        hour: '2-digit', minute: '2-digit',
    });
}

function formatNumber(n) {
    if (!n && n !== 0) return '—';
    return Number(n).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function statusClass(s) {
    return {
        pending: 'bg-sky-100 text-sky-700',
        accepted: 'bg-amber-100 text-amber-700',
        executed: 'bg-indigo-100 text-indigo-700',
        completed: 'bg-emerald-100 text-emerald-700',
        disputed: 'bg-rose-100 text-rose-700',
        cancelled: 'bg-slate-200 text-slate-500',
    }[s] || 'bg-slate-100 text-slate-600';
}
</script>
