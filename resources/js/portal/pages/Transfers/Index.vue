<template>
    <div class="min-h-screen overflow-x-hidden bg-slate-50 text-slate-900">
        <header class="mb-6 flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
            <div>
                <h1 class="text-3xl font-black uppercase tracking-tight">Transfer Desk</h1>
                <p class="mt-1 text-sm font-semibold text-slate-500">USDT receipt checks and local payout proof tracking</p>
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

        <div class="grid gap-6 xl:grid-cols-[420px_1fr]">
            <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                <div class="mb-5 flex items-center justify-between">
                    <div>
                        <h2 class="text-lg font-black uppercase tracking-tight">New Transfer</h2>
                        <p class="text-xs font-semibold text-slate-400">Capture sender, recipient, and amount</p>
                    </div>
                    <Send class="h-6 w-6 text-emerald-600" />
                </div>

                <form class="space-y-4" @submit.prevent="createTransfer">
                    <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-1">
                        <label class="block relative">
                            <span class="text-[10px] font-black uppercase tracking-widest text-slate-400">Sender</span>
                            <input
                                v-model="form.sender_name"
                                @input="onSenderNameInput"
                                @focus="searchSenders"
                                @blur="hideSenderResults"
                                required
                                placeholder="Type to search existing senders..."
                                class="mt-1 w-full rounded-xl border-slate-200 bg-slate-50 px-4 py-3 text-sm font-bold focus:border-emerald-500 focus:ring-emerald-500"
                            />
                            <ul
                                v-if="senderResults.length && showSenderResults"
                                class="absolute z-50 mt-1 w-full rounded-xl border border-slate-200 bg-white shadow-xl max-h-48 overflow-y-auto"
                            >
                                <li
                                    v-for="s in senderResults"
                                    :key="s.id"
                                    @mousedown.prevent="selectSender(s)"
                                    class="cursor-pointer px-4 py-3 text-sm font-bold hover:bg-emerald-50 border-b border-slate-100 last:border-0"
                                >
                                    <span class="text-slate-800">{{ s.name }}</span>
                                    <span class="ml-2 text-[10px] font-semibold text-slate-400">{{ s.phone || s.email }}</span>
                                </li>
                            </ul>
                        </label>
                        <label class="block">
                            <span class="text-[10px] font-black uppercase tracking-widest text-slate-400">Sender Phone</span>
                            <input v-model="form.sender_phone" class="mt-1 w-full rounded-xl border-slate-200 bg-slate-50 px-4 py-3 text-sm font-bold focus:border-emerald-500 focus:ring-emerald-500" />
                        </label>
                        <label class="block">
                            <span class="text-[10px] font-black uppercase tracking-widest text-slate-400">Recipient</span>
                            <input v-model="form.recipient_name" required class="mt-1 w-full rounded-xl border-slate-200 bg-slate-50 px-4 py-3 text-sm font-bold focus:border-emerald-500 focus:ring-emerald-500" />
                        </label>
                        <label class="block">
                            <span class="text-[10px] font-black uppercase tracking-widest text-slate-400">Recipient Phone</span>
                            <input v-model="form.recipient_phone" class="mt-1 w-full rounded-xl border-slate-200 bg-slate-50 px-4 py-3 text-sm font-bold focus:border-emerald-500 focus:ring-emerald-500" />
                        </label>
                    </div>

                    <label class="block">
                        <span class="text-[10px] font-black uppercase tracking-widest text-slate-400">Recipient Location</span>
                        <input v-model="form.recipient_location" placeholder="Lusaka, Cairo Road" class="mt-1 w-full rounded-xl border-slate-200 bg-slate-50 px-4 py-3 text-sm font-bold focus:border-emerald-500 focus:ring-emerald-500" />
                    </label>

                    <div class="grid grid-cols-[1fr_90px] gap-3">
                        <label class="block">
                            <span class="text-[10px] font-black uppercase tracking-widest text-slate-400">Amount Sent</span>
                            <input v-model="form.send_amount" required type="number" min="0.01" step="0.01" class="mt-1 w-full rounded-xl border-slate-200 bg-slate-50 px-4 py-3 text-sm font-bold focus:border-emerald-500 focus:ring-emerald-500" />
                        </label>
                        <label class="block">
                            <span class="text-[10px] font-black uppercase tracking-widest text-slate-400">Currency</span>
                            <select v-model="form.send_currency" class="mt-1 w-full rounded-xl border-slate-200 bg-slate-50 px-3 py-3 text-sm font-black uppercase focus:border-emerald-500 focus:ring-emerald-500">
                                <option v-for="c in currencies" :key="c.code" :value="c.code">{{ c.code }}</option>
                            </select>
                        </label>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <label class="block">
                            <span class="text-[10px] font-black uppercase tracking-widest text-slate-400">USDT Amount</span>
                            <input v-model="form.usdt_amount" type="number" min="0" step="0.000001" class="mt-1 w-full rounded-xl border-slate-200 bg-slate-50 px-4 py-3 text-sm font-bold focus:border-emerald-500 focus:ring-emerald-500" />
                        </label>
                        <label class="block">
                            <span class="text-[10px] font-black uppercase tracking-widest text-slate-400">Payout Currency</span>
                            <select v-model="form.payout_currency" class="mt-1 w-full rounded-xl border-slate-200 bg-slate-50 px-3 py-3 text-sm font-black uppercase focus:border-emerald-500 focus:ring-emerald-500">
                                <option v-for="c in currencies" :key="c.code" :value="c.code">{{ c.code }}</option>
                            </select>
                        </label>
                    </div>

                    <label class="block">
                        <span class="text-[10px] font-black uppercase tracking-widest text-slate-400">Notes</span>
                        <textarea v-model="form.notes" rows="3" class="mt-1 w-full rounded-xl border-slate-200 bg-slate-50 px-4 py-3 text-sm font-semibold focus:border-emerald-500 focus:ring-emerald-500"></textarea>
                    </label>

                    <p v-if="errorMessage" class="rounded-xl bg-rose-50 px-4 py-3 text-sm font-bold text-rose-700">{{ errorMessage }}</p>
                    <p v-if="successMessage" class="rounded-xl bg-emerald-50 px-4 py-3 text-sm font-bold text-emerald-700">{{ successMessage }}</p>

                    <button
                        type="submit"
                        :disabled="saving"
                        class="w-full rounded-xl bg-emerald-600 px-5 py-4 text-xs font-black uppercase tracking-widest text-white shadow-sm transition hover:bg-emerald-700 disabled:opacity-50"
                    >
                        Create Transfer
                    </button>
                </form>
            </section>

            <section class="min-w-0 space-y-6">
                <div class="rounded-2xl border border-slate-200 bg-white shadow-sm">
                    <div class="flex flex-col gap-3 border-b border-slate-100 p-5">
                        <h2 class="text-lg font-black uppercase tracking-tight">Transactions</h2>
                        <div class="grid w-full gap-3 sm:grid-cols-[minmax(0,1fr)_180px]">
                            <input v-model="filters.q" @input="debouncedFetch" placeholder="Search reference, sender, recipient" class="w-full min-w-0 rounded-xl border-slate-200 bg-slate-50 px-4 py-3 text-sm font-bold focus:border-emerald-500 focus:ring-emerald-500" />
                            <select v-model="filters.status" @change="fetchTransfers" class="w-full rounded-xl border-slate-200 bg-slate-50 px-4 py-3 text-sm font-black focus:border-emerald-500 focus:ring-emerald-500">
                                <option value="">All Statuses</option>
                                <option v-for="status in statuses" :key="status.value" :value="status.value">{{ status.label }}</option>
                            </select>
                        </div>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full text-left">
                            <thead class="bg-slate-50 text-[10px] font-black uppercase tracking-widest text-slate-400">
                                <tr>
                                    <th class="px-5 py-4">Reference</th>
                                    <th class="px-5 py-4">Parties</th>
                                    <th class="px-5 py-4">Amount</th>
                                    <th class="px-5 py-4">Status</th>
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
                                        <p class="text-sm font-black text-slate-800">{{ transfer.sender_name }}</p>
                                        <p class="text-xs font-semibold text-slate-500">{{ transfer.recipient_name }}</p>
                                    </td>
                                    <td class="px-5 py-4">
                                        <p class="text-sm font-black text-slate-900">{{ formatMoney(transfer.send_amount, transfer.send_currency) }}</p>
                                        <p class="text-xs font-semibold text-slate-500">{{ transfer.usdt_amount || '0.000000' }} USDT</p>
                                    </td>
                                    <td class="px-5 py-4">
                                        <span class="rounded-full px-3 py-1 text-[10px] font-black uppercase tracking-widest" :class="statusMeta(transfer.status).class">
                                            {{ statusMeta(transfer.status).label }}
                                        </span>
                                    </td>
                                    <td class="px-5 py-4 text-right text-xs font-bold text-slate-500">
                                        {{ formatDate(transfer.created_at) }}
                                    </td>
                                </tr>
                                <tr v-if="!transfers.length && !loading">
                                    <td colspan="5" class="px-5 py-16 text-center text-xs font-black uppercase tracking-widest text-slate-400">No transfers found</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div v-if="selected" class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                    <div class="mb-6 flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                        <div>
                            <p class="text-[10px] font-black uppercase tracking-widest text-slate-400">Selected Transfer</p>
                            <h2 class="text-2xl font-black tracking-tight">{{ selected.reference }}</h2>
                            <p class="mt-1 text-sm font-semibold text-slate-500">{{ selected.sender_name }} to {{ selected.recipient_name }}</p>
                        </div>
                        <span class="w-fit rounded-full px-3 py-1 text-[10px] font-black uppercase tracking-widest" :class="statusMeta(selected.status).class">
                            {{ statusMeta(selected.status).label }}
                        </span>
                    </div>

                    <div class="grid gap-4 lg:grid-cols-3">
                        <div class="rounded-xl border border-slate-200 p-4">
                            <p class="text-[10px] font-black uppercase tracking-widest text-slate-400">Transfer</p>
                            <p class="mt-2 text-lg font-black">{{ formatMoney(selected.send_amount, selected.send_currency) }}</p>
                            <p class="text-sm font-semibold text-slate-500">{{ selected.usdt_amount || '0.000000' }} USDT</p>
                        </div>
                        <div class="rounded-xl border border-slate-200 p-4">
                            <p class="text-[10px] font-black uppercase tracking-widest text-slate-400">Recipient</p>
                            <p class="mt-2 text-lg font-black">{{ selected.recipient_name }}</p>
                            <p class="text-sm font-semibold text-slate-500">{{ selected.recipient_phone || 'No phone' }}</p>
                        </div>
                        <div class="rounded-xl border border-slate-200 p-4">
                            <p class="text-[10px] font-black uppercase tracking-widest text-slate-400">Agent</p>
                            <p class="mt-2 text-lg font-black">{{ selected.agent?.name || 'Not assigned' }}</p>
                            <p class="text-sm font-semibold text-slate-500">{{ selected.recipient_location || 'No location' }}</p>
                        </div>
                    </div>

                    <div class="mt-6 grid gap-5 xl:grid-cols-3">
                        <form class="rounded-xl border border-slate-200 p-4" @submit.prevent="uploadUsdtProof">
                            <h3 class="mb-3 text-sm font-black uppercase tracking-tight">USDT Screenshot</h3>
                            <input type="file" accept="image/*,.pdf" @change="usdtProofFile = $event.target.files[0]" class="w-full rounded-xl border border-dashed border-slate-300 bg-slate-50 p-3 text-sm font-semibold" />
                            <textarea v-model="proofNotes" rows="2" placeholder="Proof notes" class="mt-3 w-full rounded-xl border-slate-200 bg-slate-50 px-4 py-3 text-sm font-semibold focus:border-emerald-500 focus:ring-emerald-500"></textarea>
                            <a v-if="selected.usdt_proof_path" :href="proofUrl(selected.usdt_proof_path)" target="_blank" class="mt-3 inline-flex text-xs font-black uppercase tracking-widest text-emerald-700">View Current Proof</a>
                            <button type="submit" :disabled="!usdtProofFile || selected.status === 'completed'" class="mt-3 w-full rounded-xl bg-slate-900 px-4 py-3 text-xs font-black uppercase tracking-widest text-white disabled:opacity-40">
                                Upload USDT Proof
                            </button>
                        </form>

                        <form class="rounded-xl border border-slate-200 p-4" @submit.prevent="confirmUsdt">
                            <h3 class="mb-3 text-sm font-black uppercase tracking-tight">Agent Receipt</h3>
                            <textarea v-model="agentNotes" rows="5" placeholder="Agent notes" class="w-full rounded-xl border-slate-200 bg-slate-50 px-4 py-3 text-sm font-semibold focus:border-emerald-500 focus:ring-emerald-500"></textarea>
                            <button type="submit" :disabled="!selected.usdt_proof_path || selected.status === 'usdt_received' || selected.status === 'completed'" class="mt-3 w-full rounded-xl bg-amber-500 px-4 py-3 text-xs font-black uppercase tracking-widest text-white disabled:opacity-40">
                                Confirm USDT Received
                            </button>
                        </form>

                        <form class="rounded-xl border border-slate-200 p-4" @submit.prevent="uploadPayoutProof">
                            <h3 class="mb-3 text-sm font-black uppercase tracking-tight">Payout Proof</h3>
                            <input v-model="payout.reference" placeholder="Payment reference" class="w-full rounded-xl border-slate-200 bg-slate-50 px-4 py-3 text-sm font-semibold focus:border-emerald-500 focus:ring-emerald-500" />
                            <input v-model="payout.amount" type="number" min="0" step="0.01" placeholder="Payout amount" class="mt-3 w-full rounded-xl border-slate-200 bg-slate-50 px-4 py-3 text-sm font-semibold focus:border-emerald-500 focus:ring-emerald-500" />
                            <input type="file" accept="image/*,.pdf" @change="payoutProofFile = $event.target.files[0]" class="mt-3 w-full rounded-xl border border-dashed border-slate-300 bg-slate-50 p-3 text-sm font-semibold" />
                            <a v-if="selected.payout_proof_path" :href="proofUrl(selected.payout_proof_path)" target="_blank" class="mt-3 inline-flex text-xs font-black uppercase tracking-widest text-emerald-700">View Payout Proof</a>
                            <button type="submit" :disabled="selected.status !== 'usdt_received' || !payoutProofFile" class="mt-3 w-full rounded-xl bg-emerald-600 px-4 py-3 text-xs font-black uppercase tracking-widest text-white disabled:opacity-40">
                                Complete Transaction
                            </button>
                        </form>
                    </div>

                    <div v-if="selected.events?.length" class="mt-6 rounded-xl border border-slate-200">
                        <div class="border-b border-slate-100 px-4 py-3 text-xs font-black uppercase tracking-widest text-slate-400">Activity</div>
                        <div class="divide-y divide-slate-100">
                            <div v-for="event in selected.events" :key="event.id" class="flex flex-col gap-1 px-4 py-3 sm:flex-row sm:items-center sm:justify-between">
                                <div>
                                    <p class="text-sm font-black text-slate-800">{{ event.type.replaceAll('_', ' ') }}</p>
                                    <p class="text-xs font-semibold text-slate-500">{{ event.user?.name || 'System' }}</p>
                                </div>
                                <p class="text-xs font-bold text-slate-400">{{ formatDate(event.created_at) }}</p>
                            </div>
                        </div>
                    </div>
                </div>
            </section>
        </div>
    </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue';
import { RefreshCw, Send } from 'lucide-vue-next';
import { api } from '../../../plugins/axios';

const loading = ref(false);
const saving = ref(false);
const transfers = ref([]);
const selected = ref(null);
const stats = ref({});
const currencies = ref([]);
const errorMessage = ref('');
const successMessage = ref('');
const usdtProofFile = ref(null);
const payoutProofFile = ref(null);
const proofNotes = ref('');
const agentNotes = ref('');
const debounceTimer = ref(null);
const senderResults = ref([]);
const showSenderResults = ref(false);
const senderSearchTimer = ref(null);

const filters = ref({
    q: '',
    status: '',
});

const form = ref({
    sender_name: '',
    sender_phone: '',
    sender_user_id: '',
    recipient_name: '',
    recipient_phone: '',
    recipient_location: 'Lusaka',
    send_amount: '',
    send_currency: '',
    usdt_amount: '',
    payout_currency: '',
    payout_amount: '',
    notes: '',
});

const payout = ref({
    reference: '',
    amount: '',
});

const statuses = [
    { value: 'initiated', label: 'Initiated' },
    { value: 'usdt_proof_submitted', label: 'USDT Proof Submitted' },
    { value: 'usdt_received', label: 'USDT Received' },
    { value: 'completed', label: 'Completed' },
    { value: 'cancelled', label: 'Cancelled' },
];

const statCards = computed(() => [
    { label: 'Total', value: stats.value.total || 0, color: 'text-slate-900' },
    { label: 'New', value: stats.value.initiated || 0, color: 'text-blue-600' },
    { label: 'Needs Agent', value: stats.value.awaiting_agent || 0, color: 'text-amber-600' },
    { label: 'Ready Payout', value: stats.value.ready_for_payout || 0, color: 'text-purple-600' },
    { label: 'Completed', value: stats.value.completed || 0, color: 'text-emerald-600' },
]);

const fetchTransfers = async () => {
    loading.value = true;
    try {
        const { data } = await api.get('/portal/transfers', { params: filters.value });
        transfers.value = data.data || [];
        if (selected.value) {
            const updated = transfers.value.find((transfer) => transfer.id === selected.value.id);
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

const fetchCurrencies = async () => {
    const { data } = await api.get('/portal/currencies');
    currencies.value = data;
    if (data.length) {
        form.value.send_currency = data.find(c => c.is_default)?.code || data[0].code;
        const payCurrencies = data.filter(c => c.code !== 'USD');
        form.value.payout_currency = payCurrencies.length ? payCurrencies[0].code : data[0].code;
    }
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
    agentNotes.value = data.agent_notes || '';
    payout.value.reference = data.payout_reference || '';
    payout.value.amount = data.payout_amount || '';
    usdtProofFile.value = null;
    payoutProofFile.value = null;
    proofNotes.value = '';
};

const onSenderNameInput = () => {
    form.value.sender_user_id = '';
    searchSenders();
};

const searchSenders = () => {
    clearTimeout(senderSearchTimer.value);
    const q = form.value.sender_name.trim();
    if (!q) {
        senderResults.value = [];
        showSenderResults.value = false;
        return;
    }
    senderSearchTimer.value = setTimeout(async () => {
        const { data } = await api.get('/portal/users', { params: { q, per_page: 8 } });
        senderResults.value = (data.data || []).filter(u => u.id !== form.value.sender_user_id);
        showSenderResults.value = true;
    }, 250);
};

const hideSenderResults = () => {
    setTimeout(() => { showSenderResults.value = false; }, 200);
};

const selectSender = (user) => {
    form.value.sender_user_id = user.id;
    form.value.sender_name = user.name;
    form.value.sender_phone = user.phone || '';
    showSenderResults.value = false;
};

const createTransfer = async () => {
    saving.value = true;
    errorMessage.value = '';
    successMessage.value = '';

    try {
        const payload = {
            sender_name: form.value.sender_name,
            sender_phone: form.value.sender_phone,
            recipient_name: form.value.recipient_name,
            recipient_phone: form.value.recipient_phone,
            recipient_location: form.value.recipient_location,
            send_amount: form.value.send_amount,
            send_currency: form.value.send_currency,
            usdt_amount: form.value.usdt_amount,
            payout_currency: form.value.payout_currency,
            payout_amount: form.value.payout_amount,
            notes: form.value.notes,
        };
        if (form.value.sender_user_id) {
            payload.sender_user_id = form.value.sender_user_id;
        }
        Object.keys(payload).forEach(k => { if (k !== 'sender_phone' && (payload[k] === '' || payload[k] === null || payload[k] === undefined)) delete payload[k]; });
        const { data } = await api.post('/portal/transfers', payload);
        successMessage.value = `Created ${data.reference}`;
        resetForm();
        await refresh();
        await selectTransfer(data);
    } catch (error) {
        errorMessage.value = error.response?.data?.message || 'Could not create transfer.';
    } finally {
        saving.value = false;
    }
};

const uploadUsdtProof = async () => {
    if (!selected.value || !usdtProofFile.value) return;

    const payload = new FormData();
    payload.append('usdt_proof', usdtProofFile.value);
    if (proofNotes.value) payload.append('notes', proofNotes.value);

    const { data } = await api.post(`/portal/transfers/${selected.value.id}/usdt-proof`, payload);
    selected.value = data;
    usdtProofFile.value = null;
    proofNotes.value = '';
    await refresh();
};

const confirmUsdt = async () => {
    if (!selected.value) return;

    const { data } = await api.post(`/portal/transfers/${selected.value.id}/confirm-usdt`, {
        agent_notes: agentNotes.value,
    });
    selected.value = data;
    await refresh();
};

const uploadPayoutProof = async () => {
    if (!selected.value || !payoutProofFile.value) return;

    const payload = new FormData();
    payload.append('payout_proof', payoutProofFile.value);
    if (payout.value.reference) payload.append('payout_reference', payout.value.reference);
    if (payout.value.amount) payload.append('payout_amount', payout.value.amount);
    if (agentNotes.value) payload.append('agent_notes', agentNotes.value);

    const { data } = await api.post(`/portal/transfers/${selected.value.id}/payout-proof`, payload);
    selected.value = data;
    payoutProofFile.value = null;
    await refresh();
};

const resetForm = () => {
    const defaultCurr = currencies.value.find(c => c.is_default)?.code || currencies.value[0]?.code || 'USD';
    const payCurr = currencies.value.filter(c => c.code !== 'USD');
    const defaultPay = payCurr.length ? payCurr[0].code : defaultCurr;
    form.value = {
        sender_name: '',
        sender_phone: '',
        sender_user_id: '',
        recipient_name: '',
        recipient_phone: '',
        recipient_location: 'Lusaka',
        send_amount: '',
        send_currency: defaultCurr,
        usdt_amount: '',
        payout_currency: defaultPay,
        payout_amount: '',
        notes: '',
    };
    senderResults.value = [];
};

const statusMeta = (status) => {
    const map = {
        initiated: { label: 'Initiated', class: 'bg-blue-100 text-blue-700' },
        usdt_proof_submitted: { label: 'Needs Agent', class: 'bg-amber-100 text-amber-700' },
        usdt_received: { label: 'Ready Payout', class: 'bg-purple-100 text-purple-700' },
        completed: { label: 'Completed', class: 'bg-emerald-100 text-emerald-700' },
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

onMounted(async () => {
    await fetchCurrencies();
    await refresh();
});
</script>
