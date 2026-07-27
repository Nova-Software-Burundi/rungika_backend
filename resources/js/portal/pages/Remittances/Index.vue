<template>
    <div class="min-h-screen overflow-x-hidden bg-slate-50 text-slate-900">
        <header class="mb-6 flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
            <div>
                <h1 class="text-3xl font-black uppercase tracking-tight">Remittances</h1>
                <p class="mt-1 text-sm font-semibold text-slate-500">Remittance lifecycle: pending → accepted → executed → completed</p>
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

        <section class="mb-6 grid gap-4 md:grid-cols-2 xl:grid-cols-5">
            <div v-for="item in statCards" :key="item.label" class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <p class="text-[10px] font-black uppercase tracking-widest text-slate-400">{{ item.label }}</p>
                <p class="mt-2 text-3xl font-black" :class="item.color">{{ item.value }}</p>
            </div>
        </section>

        <div class="space-y-6">
            <div class="rounded-2xl border border-slate-200 bg-white shadow-sm">
                <div class="flex flex-col gap-3 border-b border-slate-100 p-5">
                    <h2 class="text-lg font-black uppercase tracking-tight">Remittances</h2>
                    <div class="grid w-full gap-3 sm:grid-cols-[minmax(0,1fr)_180px_140px]">
                        <input v-model="filters.q" @input="debouncedFetch" placeholder="Search reference, client, agent" class="w-full min-w-0 rounded-xl border-slate-200 bg-slate-50 px-4 py-3 text-sm font-bold focus:border-emerald-500 focus:ring-emerald-500" />
                        <select v-model="filters.status" @change="fetchTransfers" class="w-full rounded-xl border-slate-200 bg-slate-50 px-4 py-3 text-sm font-black focus:border-emerald-500 focus:ring-emerald-500">
                            <option value="">All Statuses</option>
                            <option v-for="status in statuses" :key="status.value" :value="status.value">{{ status.label }}</option>
                        </select>
                        <select v-model="filters.has_debt" @change="fetchTransfers" class="w-full rounded-xl border-slate-200 bg-slate-50 px-4 py-3 text-sm font-black focus:border-emerald-500 focus:ring-emerald-500">
                            <option value="">All</option>
                            <option value="1">Has Debt</option>
                            <option value="0">No Debt</option>
                        </select>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left">
                        <thead class="bg-slate-50 text-[10px] font-black uppercase tracking-widest text-slate-400">
                            <tr>
                                <th class="px-5 py-4">Reference</th>
                                <th class="px-5 py-4">Client → Agent</th>
                                <th class="px-5 py-4">Amount</th>
                                <th class="px-5 py-4">Status</th>
                                <th class="px-5 py-4">Debt</th>
                                <th class="px-5 py-4 text-right">Created</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr v-for="transfer in transfers" :key="transfer.id" @click="selectTransfer(transfer)" class="cursor-pointer transition hover:bg-emerald-50/50" :class="selected?.id === transfer.id ? 'bg-emerald-50/70' : ''">
                                <td class="px-5 py-4">
                                    <p class="font-black text-slate-900">{{ transfer.reference }}</p>
                                    <p class="text-[10px] font-bold uppercase text-slate-400">{{ transfer.agent?.name || 'Unassigned' }}</p>
                                </td>
                                <td class="px-5 py-4">
                                    <p class="text-sm font-black text-slate-800">{{ transfer.initiator?.name || '—' }}</p>
                                    <p class="text-xs font-semibold text-slate-500">→ {{ transfer.destinator_name || '—' }}</p>
                                </td>
                                <td class="px-5 py-4">
                                    <p class="text-sm font-black text-slate-900">{{ formatMoney(transfer.send_amount, transfer.send_currency || 'USD') }}</p>
                                </td>
                                <td class="px-5 py-4">
                                    <span class="rounded-full px-3 py-1 text-[10px] font-black uppercase tracking-widest" :class="statusMeta(transfer.status).class">
                                        {{ statusMeta(transfer.status).label }}
                                    </span>
                                </td>
                                <td class="px-5 py-4">
                                    <span v-if="transfer.requester_debt && transfer.executor_debt" class="rounded-full px-2 py-1 text-[10px] font-black uppercase tracking-widest bg-rose-100 text-rose-700">Both</span>
                                    <span v-else-if="transfer.executor_debt" class="rounded-full px-2 py-1 text-[10px] font-black uppercase tracking-widest bg-orange-100 text-orange-700">Client → Agent</span>
                                    <span v-else-if="transfer.requester_debt" class="rounded-full px-2 py-1 text-[10px] font-black uppercase tracking-widest bg-amber-100 text-amber-700">Agent → Client</span>
                                    <span v-else class="text-[10px] text-slate-300">—</span>
                                </td>
                                <td class="px-5 py-4 text-right text-xs font-bold text-slate-500">
                                    {{ formatDate(transfer.created_at) }}
                                </td>
                            </tr>
                            <tr v-if="!transfers.length && !loading">
                                <td colspan="6" class="px-5 py-16 text-center text-xs font-black uppercase tracking-widest text-slate-400">No remittances found</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div v-if="selected" class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                <div class="mb-6 flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                    <div>
                        <p class="text-[10px] font-black uppercase tracking-widest text-slate-400">Selected Remittance</p>
                        <h2 class="text-2xl font-black tracking-tight">{{ selected.reference }}</h2>
                        <p class="mt-1 text-sm font-semibold text-slate-500">
                            {{ selected.initiator?.name || '—' }} → {{ selected.destinator_name || '—' }}
                            <span v-if="selected.agent"> (Agent: {{ selected.agent.name }})</span>
                        </p>
                    </div>
                    <div class="flex items-center gap-2">
                        <span v-if="selected.hasDebt" class="rounded-full px-2 py-1 text-[10px] font-black uppercase tracking-widest bg-rose-100 text-rose-700">Debt</span>
                        <span class="w-fit rounded-full px-3 py-1 text-[10px] font-black uppercase tracking-widest" :class="statusMeta(selected.status).class">
                            {{ statusMeta(selected.status).label }}
                        </span>
                    </div>
                </div>

                <div class="grid gap-4 lg:grid-cols-4">
                    <div class="rounded-xl border border-slate-200 p-4">
                        <p class="text-[10px] font-black uppercase tracking-widest text-slate-400">Amount</p>
                        <p class="mt-2 text-lg font-black">{{ formatMoney(selected.send_amount, selected.send_currency || 'USD') }}</p>
                    </div>
                    <div class="rounded-xl border border-slate-200 p-4">
                        <p class="text-[10px] font-black uppercase tracking-widest text-slate-400">Recipient</p>
                        <p class="mt-2 text-lg font-black">{{ selected.destinator_name }}</p>
                        <p class="text-sm font-semibold text-slate-500">{{ selected.destinator_phone || 'No phone' }}</p>
                    </div>
                    <div class="rounded-xl border border-slate-200 p-4">
                        <p class="text-[10px] font-black uppercase tracking-widest text-slate-400">Agent</p>
                        <p class="mt-2 text-lg font-black">{{ selected.agent?.name || 'Not assigned' }}</p>
                        <p class="text-sm font-semibold text-slate-500">{{ selected.agent?.phone || '' }}</p>
                    </div>
                    <div class="rounded-xl border border-slate-200 p-4">
                        <p class="text-[10px] font-black uppercase tracking-widest text-slate-400">Debt Status</p>
                        <p class="mt-2 text-sm font-black">
                            <span v-if="selected.executor_debt && selected.requester_debt" class="text-rose-600">Both parties owe</span>
                            <span v-else-if="selected.executor_debt" class="text-orange-600">Client owes Agent (no agent proof)</span>
                            <span v-else-if="selected.requester_debt" class="text-amber-600">Agent owes Client (no client proof)</span>
                            <span v-else class="text-emerald-600">Settled</span>
                        </p>
                    </div>
                </div>

                <div class="mt-6 grid gap-5 xl:grid-cols-3">
                    <form class="rounded-xl border border-slate-200 p-4" @submit.prevent="uploadRequesterProof">
                        <h3 class="mb-3 text-sm font-black uppercase tracking-tight">Client Proof of Payment</h3>
                        <p class="text-[10px] font-semibold text-slate-400 mb-3">Upload proof of payment from the client</p>
                        <input type="file" accept="image/*,.pdf" @change="requesterProofFile = $event.target.files[0]" class="w-full rounded-xl border border-dashed border-slate-300 bg-slate-50 p-3 text-sm font-semibold" />
                        <a v-if="selected.requester_proof_path" :href="proofUrl(selected.requester_proof_path)" target="_blank" class="mt-3 inline-flex text-xs font-black uppercase tracking-widest text-emerald-700">View Client Proof</a>
                        <p v-if="selected.requester_debt" class="mt-2 text-xs font-bold text-amber-600">⚠ Agent has outstanding debt to client</p>
                        <button type="submit" :disabled="!requesterProofFile || selected.isClosed" class="mt-3 w-full rounded-xl bg-slate-900 px-4 py-3 text-xs font-black uppercase tracking-widest text-white disabled:opacity-40">
                            Upload Client Proof
                        </button>
                    </form>

                    <div class="rounded-xl border border-slate-200 p-4">
                        <h3 class="mb-3 text-sm font-black uppercase tracking-tight">Agent Actions</h3>
                        <p class="text-[10px] font-semibold text-slate-400 mb-3">Accept, execute, or confirm the remittance</p>
                        <button @click="markAccepted" :disabled="selected.status !== 'pending'"
                            class="w-full rounded-xl bg-amber-500 px-4 py-3 text-xs font-black uppercase tracking-widest text-white disabled:opacity-40 mb-3">
                            Agent Accepted
                        </button>
                        <button @click="markExecuted" :disabled="selected.status !== 'accepted'"
                            class="w-full rounded-xl bg-indigo-600 px-4 py-3 text-xs font-black uppercase tracking-widest text-white disabled:opacity-40 mb-3">
                            Agent Executed
                        </button>
                        <button @click="markCompleted" :disabled="selected.status !== 'executed'"
                            class="w-full rounded-xl bg-emerald-600 px-4 py-3 text-xs font-black uppercase tracking-widest text-white disabled:opacity-40">
                            Client Confirmed
                        </button>
                    </div>

                    <form class="rounded-xl border border-slate-200 p-4" @submit.prevent="uploadAgentProof">
                        <h3 class="mb-3 text-sm font-black uppercase tracking-tight">Agent Proof of Execution</h3>
                        <p class="text-[10px] font-semibold text-slate-400 mb-3">Upload execution proof from the agent</p>
                        <input v-model="agentProof.reference" placeholder="Payout reference" class="w-full rounded-xl border-slate-200 bg-slate-50 px-4 py-3 text-sm font-semibold focus:border-emerald-500 focus:ring-emerald-500" />
                        <input type="file" accept="image/*,.pdf" @change="agentProofFile = $event.target.files[0]" class="mt-3 w-full rounded-xl border border-dashed border-slate-300 bg-slate-50 p-3 text-sm font-semibold" />
                        <a v-if="selected.executor_proof_path" :href="proofUrl(selected.executor_proof_path)" target="_blank" class="mt-3 inline-flex text-xs font-black uppercase tracking-widest text-emerald-700">View Agent Proof</a>
                        <p v-if="selected.executor_debt" class="mt-2 text-xs font-bold text-orange-600">⚠ Client has outstanding debt to agent</p>
                        <button type="submit" :disabled="!agentProofFile || selected.isClosed" class="mt-3 w-full rounded-xl bg-emerald-600 px-4 py-3 text-xs font-black uppercase tracking-widest text-white disabled:opacity-40">
                            Upload Agent Proof
                        </button>
                    </form>
                </div>

                <div v-if="selected.events?.length" class="mt-6 rounded-xl border border-slate-200">
                    <div class="border-b border-slate-100 px-4 py-3 text-xs font-black uppercase tracking-widest text-slate-400">Activity</div>
                    <div class="divide-y divide-slate-100">
                        <div v-for="event in selected.events" :key="event.id" class="flex flex-col gap-1 px-4 py-3 sm:flex-row sm:items-center sm:justify-between">
                            <div>
                                <p class="text-sm font-black text-slate-800">{{ event.description || event.type.replaceAll('_', ' ') }}</p>
                                <p class="text-xs font-semibold text-slate-500">{{ event.user?.name || 'System' }}</p>
                            </div>
                            <p class="text-xs font-bold text-slate-400">{{ formatDate(event.created_at) }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue';
import { RefreshCw } from 'lucide-vue-next';
import { api } from '../../../plugins/axios';

const loading = ref(false);
const transfers = ref([]);
const selected = ref(null);
const stats = ref({});
const requesterProofFile = ref(null);
const agentProofFile = ref(null);
const debounceTimer = ref(null);

const filters = ref({
    q: '',
    status: '',
    has_debt: '',
});

const agentProof = ref({
    reference: '',
});

const statuses = [
    { value: 'pending', label: 'Pending' },
    { value: 'accepted', label: 'Accepted' },
    { value: 'executed', label: 'Executed' },
    { value: 'completed', label: 'Completed' },
    { value: 'disputed', label: 'Disputed' },
    { value: 'cancelled', label: 'Cancelled' },
];

const statCards = computed(() => [
    { label: 'Total', value: stats.value.total || 0, color: 'text-slate-900' },
    { label: 'Pending', value: stats.value.pending || 0, color: 'text-sky-600' },
    { label: 'Accepted', value: stats.value.accepted || 0, color: 'text-amber-600' },
    { label: 'Executed', value: stats.value.executed || 0, color: 'text-indigo-600' },
    { label: 'Completed', value: stats.value.completed || 0, color: 'text-emerald-600' },
]);

const fetchTransfers = async () => {
    loading.value = true;
    try {
        const params = { ...filters.value };
        if (params.has_debt === '') delete params.has_debt;
        const { data } = await api.get('/portal/transfers', { params });
        transfers.value = data.data || [];
        if (selected.value) {
            const updated = transfers.value.find((t) => t.id === selected.value.id);
            if (updated) await selectTransfer(updated);
        }
    } finally {
        loading.value = false;
    }
};

const fetchStats = async () => {
    const { data } = await api.get('/portal/transfers/stats');
    stats.value = data;
};

const refresh = async () => {
    await Promise.all([fetchTransfers(), fetchStats()]);
};

const debouncedFetch = () => {
    clearTimeout(debounceTimer.value);
    debounceTimer.value = setTimeout(fetchTransfers, 300);
};

const selectTransfer = async (transfer) => {
    const { data } = await api.get(`/portal/transfers/${transfer.id}`);
    selected.value = data;
    agentProof.value.reference = data.payout_reference || '';
    requesterProofFile.value = null;
    agentProofFile.value = null;
};

const markAccepted = async () => {
    if (!selected.value) return;
    const { data } = await api.patch(`/portal/transfers/${selected.value.id}/status`, { status: 'accepted' });
    selected.value = data;
    await refresh();
};

const markExecuted = async () => {
    if (!selected.value) return;
    const { data } = await api.patch(`/portal/transfers/${selected.value.id}/status`, { status: 'executed' });
    selected.value = data;
    await refresh();
};

const markCompleted = async () => {
    if (!selected.value) return;
    const { data } = await api.patch(`/portal/transfers/${selected.value.id}/status`, { status: 'completed' });
    selected.value = data;
    await refresh();
};

const uploadRequesterProof = async () => {
    if (!selected.value || !requesterProofFile.value) return;
    const payload = new FormData();
    payload.append('usdt_proof', requesterProofFile.value);
    const { data } = await api.post(`/portal/transfers/${selected.value.id}/usdt-proof`, payload);
    selected.value = data;
    requesterProofFile.value = null;
    await refresh();
};

const uploadAgentProof = async () => {
    if (!selected.value || !agentProofFile.value) return;
    const payload = new FormData();
    payload.append('payout_proof', agentProofFile.value);
    if (agentProof.value.reference) payload.append('payout_reference', agentProof.value.reference);
    const { data } = await api.post(`/portal/transfers/${selected.value.id}/payout-proof`, payload);
    selected.value = data;
    agentProofFile.value = null;
    await refresh();
};

const statusMeta = (status) => {
    const map = {
        pending: { label: 'Pending', class: 'bg-sky-100 text-sky-700' },
        accepted: { label: 'Accepted', class: 'bg-amber-100 text-amber-700' },
        executed: { label: 'Executed', class: 'bg-indigo-100 text-indigo-700' },
        completed: { label: 'Completed', class: 'bg-emerald-100 text-emerald-700' },
        disputed: { label: 'Disputed', class: 'bg-rose-100 text-rose-700' },
        cancelled: { label: 'Cancelled', class: 'bg-slate-100 text-slate-600' },
    };
    return map[status] || { label: status || 'Unknown', class: 'bg-slate-100 text-slate-600' };
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

const proofUrl = (path) => `/storage/${path}`;

onMounted(refresh);
</script>
