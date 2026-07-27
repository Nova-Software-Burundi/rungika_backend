<template>
    <div class="min-h-screen overflow-x-hidden bg-slate-50 text-slate-900">
        <header class="mb-6 flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
            <div>
                <h1 class="text-3xl font-black uppercase tracking-tight">Dashboard</h1>
                <p class="mt-1 text-sm font-semibold text-slate-500">Remittance operations overview</p>
            </div>
            <button
                @click="refresh"
                :disabled="loading"
                class="inline-flex items-center justify-center gap-2 rounded-xl bg-slate-900 px-4 py-3 text-xs font-black uppercase tracking-wider text-white shadow-sm transition hover:bg-slate-800 disabled:opacity-50"
            >
                <RefreshCw class="h-4 w-4" :class="{ 'animate-spin': loading }" />
                Refresh
            </button>
        </header>

        <section class="mb-6 grid gap-4 md:grid-cols-2 xl:grid-cols-4">
            <div v-for="item in metricCards" :key="item.label" class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <p class="text-[10px] font-black uppercase tracking-widest text-slate-400">{{ item.label }}</p>
                <p class="mt-2 text-3xl font-black" :class="item.color">{{ item.value }}</p>
                <p v-if="item.sub" class="mt-1 text-xs font-semibold text-slate-400">{{ item.sub }}</p>
            </div>
        </section>

        <div class="grid gap-6 xl:grid-cols-[1fr_380px]">
            <section class="rounded-2xl border border-slate-200 bg-white shadow-sm">
                <div class="flex items-center justify-between border-b border-slate-100 p-5">
                    <div>
                        <h2 class="text-lg font-black uppercase tracking-tight">Outstanding Debts</h2>
                        <p class="text-xs font-semibold text-slate-400">Remittances with pending proof of payment or execution</p>
                    </div>
                    <div class="flex items-center gap-2">
                        <select v-model="debtFilter" @change="fetchDebts" class="rounded-xl border-slate-200 bg-slate-50 px-3 py-2 text-xs font-bold focus:border-emerald-500 focus:ring-emerald-500">
                            <option value="">All Debts</option>
                            <option value="client">Client Owes Agent</option>
                            <option value="agent">Agent Owes Client</option>
                        </select>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left">
                        <thead class="bg-slate-50 text-[10px] font-black uppercase tracking-widest text-slate-400">
                            <tr>
                                <th class="px-5 py-4">Reference</th>
                                <th class="px-5 py-4">Client</th>
                                <th class="px-5 py-4">Agent</th>
                                <th class="px-5 py-4 text-right">Amount</th>
                                <th class="px-5 py-4">Who Owes</th>
                                <th class="px-5 py-4">Reason</th>
                                <th class="px-5 py-4 text-right">Created</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr v-for="d in debts" :key="d.id" class="transition hover:bg-emerald-50/50">
                                <td class="px-5 py-4">
                                    <p class="font-black text-slate-900">{{ d.reference }}</p>
                                </td>
                                <td class="px-5 py-4">
                                    <p class="text-sm font-bold text-slate-800">{{ d.initiator?.name || '—' }}</p>
                                </td>
                                <td class="px-5 py-4">
                                    <p class="text-sm font-bold text-slate-800">{{ d.agent?.name || '—' }}</p>
                                </td>
                                <td class="px-5 py-4 text-right">
                                    <p class="text-sm font-black text-slate-900">{{ formatMoney(d.send_amount, d.send_currency) }}</p>
                                </td>
                                <td class="px-5 py-4">
                                    <span v-if="d.requester_debt && d.executor_debt" class="rounded-full px-2 py-1 text-[10px] font-black uppercase tracking-widest bg-rose-100 text-rose-700">Both</span>
                                    <span v-else-if="d.executor_debt" class="rounded-full px-2 py-1 text-[10px] font-black uppercase tracking-widest bg-orange-100 text-orange-700">Client owes Agent</span>
                                    <span v-else-if="d.requester_debt" class="rounded-full px-2 py-1 text-[10px] font-black uppercase tracking-widest bg-amber-100 text-amber-700">Agent owes Client</span>
                                </td>
                                <td class="px-5 py-4">
                                    <p class="text-xs font-semibold text-slate-500">
                                        {{ debtReason(d) }}
                                    </p>
                                </td>
                                <td class="px-5 py-4 text-right text-xs font-bold text-slate-500">
                                    {{ formatDate(d.created_at) }}
                                </td>
                            </tr>
                            <tr v-if="!debts.length && !loading">
                                <td colspan="7" class="px-5 py-16 text-center text-xs font-black uppercase tracking-widest text-emerald-600">
                                    No outstanding debts
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <div class="space-y-6">
                <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                    <h2 class="mb-4 text-lg font-black uppercase tracking-tight">Recent Activity</h2>
                    <div v-if="recentActivity.length" class="divide-y divide-slate-100">
                        <div v-for="event in recentActivity" :key="event.id" class="flex flex-col gap-1 py-3 first:pt-0 last:pb-0">
                            <p class="text-sm font-bold text-slate-800">{{ event.description }}</p>
                            <div class="flex items-center justify-between">
                                <p class="text-xs font-semibold text-slate-500">{{ event.user?.name || 'System' }}</p>
                                <p class="text-[10px] font-bold text-slate-400">{{ formatDate(event.created_at) }}</p>
                            </div>
                        </div>
                    </div>
                    <p v-else class="py-8 text-center text-xs font-bold text-slate-400">No recent activity</p>
                </section>

                <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                    <h2 class="mb-4 text-lg font-black uppercase tracking-tight">Quick Stats</h2>
                    <div class="space-y-3">
                        <div class="flex items-center justify-between">
                            <span class="text-sm font-semibold text-slate-500">Total Remittances</span>
                            <span class="text-sm font-black text-slate-900">{{ stats.total || 0 }}</span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-sm font-semibold text-slate-500">Active (Pending + Accepted)</span>
                            <span class="text-sm font-black text-sky-600">{{ (stats.pending || 0) + (stats.accepted || 0) }}</span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-sm font-semibold text-slate-500">Completed This Month</span>
                            <span class="text-sm font-black text-emerald-600">{{ stats.completed || 0 }}</span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-sm font-semibold text-slate-500">Disputed</span>
                            <span class="text-sm font-black text-rose-600">{{ stats.disputed || 0 }}</span>
                        </div>
                    </div>
                </section>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { RefreshCw } from 'lucide-vue-next';
import { api } from '../../../plugins/axios';

const loading = ref(false);
const stats = ref({});
const debts = ref([]);
const recentActivity = ref([]);
const debtFilter = ref('');

const metricCards = computed(() => {
    const active = (stats.value.pending || 0) + (stats.value.accepted || 0);
    return [
        { label: 'Active Remittances', value: active, color: 'text-sky-600', sub: `${stats.value.pending || 0} pending, ${stats.value.accepted || 0} accepted` },
        { label: 'Client Owes Agent', value: stats.value.client_owes_agent || 0, color: 'text-orange-600', sub: 'Agent executed without proof' },
        { label: 'Agent Owes Client', value: stats.value.agent_owes_client || 0, color: 'text-amber-600', sub: 'Client paid, agent pending' },
        { label: 'Completed', value: stats.value.completed || 0, color: 'text-emerald-600', sub: 'All time' },
    ];
});

const fetchStats = async () => {
    const { data } = await api.get('/portal/transfers/stats');
    stats.value = data;
};

const fetchDebts = async () => {
    const params = { per_page: 50 };
    if (debtFilter.value) params.debt_side = debtFilter.value;
    const { data } = await api.get('/portal/transfers/debts', { params });
    debts.value = data.data || [];
};

const fetchRecentActivity = async () => {
    try {
        const { data } = await api.get('/portal/transfers', { params: { per_page: 10, sort: 'recent' } });
        const transfers = data.data || [];
        const events = [];
        for (const t of transfers) {
            if (t.events) {
                for (const e of t.events) {
                    events.push({ ...e, transfer_ref: t.reference });
                }
            }
        }
        events.sort((a, b) => new Date(b.created_at) - new Date(a.created_at));
        recentActivity.value = events.slice(0, 10);
    } catch {
        recentActivity.value = [];
    }
};

const refresh = async () => {
    loading.value = true;
    try {
        await Promise.all([fetchStats(), fetchDebts(), fetchRecentActivity()]);
    } finally {
        loading.value = false;
    }
};

const debtReason = (d) => {
    if (d.executor_debt && d.requester_debt) return 'Both parties have outstanding proof';
    if (d.executor_debt) return 'Agent executed without proof';
    if (d.requester_debt) return 'Client paid, agent has not executed';
    return '';
};

const formatMoney = (amount, currency) => {
    return `${Number(amount || 0).toLocaleString(undefined, {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    })} ${currency || ''}`.trim();
};

const formatDate = (date) => {
    if (!date) return '';
    return new Intl.DateTimeFormat(undefined, {
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    }).format(new Date(date));
};

onMounted(refresh);
</script>
