<template>
    <div class="p-6 space-y-6 bg-slate-50 min-h-screen">
        <div class="flex items-center justify-between">
            <h1 class="text-2xl font-black text-slate-800 tracking-tighter uppercase flex items-center gap-3">
                <ArrowLeftRight class="w-8 h-8 text-blue-500" /> Trades
            </h1>
            <button @click="showDetail = false" v-if="showDetail" class="rounded-xl bg-slate-100 px-5 py-3 text-xs font-black uppercase tracking-wider text-slate-600 hover:bg-slate-200">Back to list</button>
        </div>

        <div v-if="!showDetail">
            <div class="bg-white p-4 rounded-2xl border border-slate-200 shadow-sm flex gap-3 flex-wrap">
                <input v-model="filters.reference" @input="fetchTrades" placeholder="Reference..." class="rounded-xl bg-slate-50 px-4 py-2 text-xs font-black border-none outline-none w-40" />
                <select v-model="filters.status" @change="fetchTrades" class="rounded-xl bg-slate-50 px-4 py-2 text-xs font-black border-none outline-none">
                    <option value="">All Status</option>
                    <option value="pending">Pending</option>
                    <option value="awaiting_payment">Awaiting Payment</option>
                    <option value="payment_sent">Payment Sent</option>
                    <option value="released">Released</option>
                    <option value="completed">Completed</option>
                    <option value="cancelled">Cancelled</option>
                    <option value="disputed">Disputed</option>
                    <option value="resolved">Resolved</option>
                </select>
            </div>

            <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
                <table class="w-full text-left">
                    <thead class="bg-slate-50/50 text-[10px] font-black text-slate-400 uppercase border-b">
                        <tr>
                            <th class="p-5">Reference</th>
                            <th class="p-5">Buyer</th>
                            <th class="p-5">Seller</th>
                            <th class="p-5">Asset/Fiat</th>
                            <th class="p-5">Amount</th>
                            <th class="p-5">Total</th>
                            <th class="p-5">Status</th>
                            <th class="p-5 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-50">
                        <tr v-for="trade in trades.data" :key="trade.id" class="hover:bg-slate-50/50 transition-colors">
                            <td class="p-5">
                                <p class="font-black text-sm text-slate-800">{{ trade.reference }}</p>
                                <p class="text-[10px] text-slate-400">{{ timeAgo(trade.created_at) }}</p>
                            </td>
                            <td class="p-5">
                                <p class="font-black text-sm text-slate-800">{{ trade.buyer?.name }}</p>
                                <p class="text-[10px] text-slate-400">{{ trade.buyer?.email }}</p>
                            </td>
                            <td class="p-5">
                                <p class="font-black text-sm text-slate-800">{{ trade.seller?.name }}</p>
                                <p class="text-[10px] text-slate-400">{{ trade.seller?.email }}</p>
                            </td>
                            <td class="p-5 text-sm font-bold text-slate-700">{{ trade.asset?.code }} / {{ trade.fiat_currency?.code }}</td>
                            <td class="p-5 text-sm font-black text-slate-800">{{ parseFloat(trade.asset_amount).toFixed(4) }}</td>
                            <td class="p-5 text-sm font-black text-slate-800">{{ parseFloat(trade.fiat_amount).toFixed(2) }}</td>
                            <td class="p-5">
                                <span class="px-3 py-1 rounded-full text-[10px] font-black uppercase" :class="statusClass(trade.status)">
                                    {{ statusLabel(trade.status) }}
                                </span>
                            </td>
                            <td class="p-5 text-right">
                                <button @click="viewTrade(trade)" class="rounded-lg bg-slate-100 px-3 py-2 text-[10px] font-black text-slate-600 hover:bg-slate-200 mr-1">View</button>
                                <button v-if="trade.status === 'pending' || trade.status === 'awaiting_payment'" @click="openCancelModal(trade)" class="rounded-lg bg-rose-100 px-3 py-2 text-[10px] font-black text-rose-600 hover:bg-rose-200">Cancel</button>
                                <button v-if="trade.status === 'disputed'" @click="openResolveModal(trade)" class="rounded-lg bg-amber-100 px-3 py-2 text-[10px] font-black text-amber-600 hover:bg-amber-200">Resolve</button>
                            </td>
                        </tr>
                        <tr v-if="!trades.data?.length">
                            <td colspan="8" class="p-16 text-center text-xs font-black uppercase tracking-widest text-slate-400">No trades</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div v-if="trades.last_page > 1" class="flex justify-center gap-2">
                <button v-for="page in trades.last_page" :key="page" @click="goToPage(page)" class="rounded-xl px-4 py-2 text-xs font-black" :class="page === trades.current_page ? 'bg-slate-800 text-white' : 'bg-white text-slate-600 border border-slate-200'">
                    {{ page }}
                </button>
            </div>
        </div>

        <div v-else class="space-y-6 max-w-4xl">
            <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-6 grid grid-cols-3 gap-6">
                <div>
                    <p class="text-[10px] font-black uppercase tracking-widest text-slate-400 mb-1">Reference</p>
                    <p class="font-black text-lg text-slate-800">{{ selected.reference }}</p>
                </div>
                <div>
                    <p class="text-[10px] font-black uppercase tracking-widest text-slate-400 mb-1">Status</p>
                    <span class="px-3 py-1 rounded-full text-[10px] font-black uppercase" :class="statusClass(selected.status)">{{ statusLabel(selected.status) }}</span>
                </div>
                <div>
                    <p class="text-[10px] font-black uppercase tracking-widest text-slate-400 mb-1">Payment Method</p>
                    <p class="font-bold text-sm text-slate-700">{{ selected.payment_method?.name }}</p>
                </div>
                <div>
                    <p class="text-[10px] font-black uppercase tracking-widest text-slate-400 mb-1">Buyer</p>
                    <p class="font-black text-sm text-slate-800">{{ selected.buyer?.name }}</p>
                    <p class="text-xs text-slate-400">{{ selected.buyer?.email }}</p>
                </div>
                <div>
                    <p class="text-[10px] font-black uppercase tracking-widest text-slate-400 mb-1">Seller</p>
                    <p class="font-black text-sm text-slate-800">{{ selected.seller?.name }}</p>
                    <p class="text-xs text-slate-400">{{ selected.seller?.email }}</p>
                </div>
                <div>
                    <p class="text-[10px] font-black uppercase tracking-widest text-slate-400 mb-1">Asset / Fiat</p>
                    <p class="font-black text-sm text-slate-800">{{ selected.asset?.code }} / {{ selected.fiat_currency?.code }}</p>
                </div>
                <div>
                    <p class="text-[10px] font-black uppercase tracking-widest text-slate-400 mb-1">Asset Amount</p>
                    <p class="font-black text-sm text-slate-800">{{ parseFloat(selected.asset_amount).toFixed(4) }} {{ selected.asset?.code }}</p>
                </div>
                <div>
                    <p class="text-[10px] font-black uppercase tracking-widest text-slate-400 mb-1">Fiat Amount</p>
                    <p class="font-black text-sm text-slate-800">{{ parseFloat(selected.fiat_amount).toFixed(2) }} {{ selected.fiat_currency?.code }}</p>
                </div>
                <div>
                    <p class="text-[10px] font-black uppercase tracking-widest text-slate-400 mb-1">Price</p>
                    <p class="font-black text-sm text-slate-800">{{ parseFloat(selected.price).toFixed(4) }}</p>
                </div>
                <div>
                    <p class="text-[10px] font-black uppercase tracking-widest text-slate-400 mb-1">Buyer Fee</p>
                    <p class="font-bold text-sm text-slate-600">{{ parseFloat(selected.fee_buyer).toFixed(4) }}</p>
                </div>
                <div>
                    <p class="text-[10px] font-black uppercase tracking-widest text-slate-400 mb-1">Seller Fee</p>
                    <p class="font-bold text-sm text-slate-600">{{ parseFloat(selected.fee_seller).toFixed(4) }}</p>
                </div>
                <div>
                    <p class="text-[10px] font-black uppercase tracking-widest text-slate-400 mb-1">Created</p>
                    <p class="font-bold text-sm text-slate-600">{{ selected.created_at }}</p>
                </div>
            </div>

            <div v-if="selected.status === 'disputed'" class="space-y-6">
                <div class="bg-amber-50 rounded-2xl border border-amber-200 shadow-sm p-6">
                    <p class="text-[10px] font-black uppercase tracking-widest text-amber-600 mb-2">Dispute Reason</p>
                    <p class="font-bold text-sm text-amber-800">{{ selected.dispute_reason }}</p>
                    <button @click="openResolveModal(selected)" class="mt-4 rounded-xl bg-amber-600 px-5 py-3 text-xs font-black uppercase tracking-wider text-white hover:bg-amber-500">Resolve Dispute</button>
                </div>

                <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-6">
                    <p class="text-[10px] font-black uppercase tracking-widest text-slate-400 mb-4">Dispute Chat</p>
                    <div class="space-y-3 max-h-80 overflow-y-auto mb-4 p-3 bg-slate-50 rounded-xl">
                        <div v-for="msg in disputeMessages" :key="msg.id" class="flex gap-3" :class="msg.user_id === currentUserId ? 'justify-end' : ''">
                            <div class="max-w-[80%] rounded-xl px-4 py-2" :class="msg.user_id === currentUserId ? 'bg-blue-500 text-white' : 'bg-white border border-slate-200'">
                                <p class="text-[10px] font-black uppercase mb-1 opacity-60">{{ msg.user?.name }}</p>
                                <p class="text-sm font-medium">{{ msg.message }}</p>
                                <p class="text-[10px] mt-1 opacity-50">{{ timeAgo(msg.created_at) }}</p>
                            </div>
                        </div>
                        <p v-if="!disputeMessages.length" class="text-xs font-black text-slate-400 text-center py-8">No messages yet.</p>
                    </div>
                    <div class="flex gap-3">
                        <input v-model="newMessage" @keyup.enter="sendMessage" placeholder="Type a message..." class="flex-1 rounded-xl border-slate-200 bg-slate-50 px-4 py-3 text-sm font-bold" />
                        <button @click="sendMessage" class="rounded-xl bg-slate-800 px-5 py-3 text-xs font-black uppercase tracking-wider text-white hover:bg-slate-700">Send</button>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-6">
                <p class="text-[10px] font-black uppercase tracking-widest text-slate-400 mb-4">Timeline</p>
                <div v-if="selected.events?.length" class="space-y-4">
                    <div v-for="event in selected.events" :key="event.id" class="flex gap-4 items-start">
                        <div class="w-2 h-2 rounded-full mt-1.5" :class="eventDot(event.to_status)"></div>
                        <div>
                            <p class="font-bold text-sm text-slate-800">
                                <span class="uppercase text-[10px]" :class="actorClass(event.actor_type)">{{ event.actor_type }}</span>
                                — {{ event.from_status || '—' }} → {{ event.to_status }}
                            </p>
                            <p v-if="event.notes" class="text-xs text-slate-500 mt-0.5">{{ event.notes }}</p>
                            <p class="text-[10px] text-slate-400 mt-0.5">{{ timeAgo(event.created_at) }}</p>
                        </div>
                    </div>
                </div>
                <p v-else class="text-xs font-black text-slate-400">No events recorded.</p>
            </div>
        </div>

        <Transition name="slide">
            <div v-if="showCancelModal" class="fixed inset-0 z-[100] flex items-center justify-center">
                <div class="absolute inset-0 bg-slate-900/60 backdrop-blur-sm" @click="showCancelModal = false"></div>
                <div class="relative bg-white w-full max-w-md rounded-2xl shadow-2xl p-8 border-t-4 border-rose-500">
                    <h2 class="text-xl font-black text-slate-900 uppercase mb-2">Cancel Trade</h2>
                    <p class="text-sm font-semibold text-slate-500 mb-6">Are you sure you want to cancel trade <strong>{{ cancelTarget?.reference }}</strong>?</p>
                    <div class="flex gap-3 justify-end">
                        <button @click="showCancelModal = false" class="rounded-xl bg-slate-100 px-5 py-3 text-xs font-black uppercase tracking-wider text-slate-600">No</button>
                        <button @click="confirmCancel" class="rounded-xl bg-rose-600 px-5 py-3 text-xs font-black uppercase tracking-wider text-white hover:bg-rose-500">Yes, Cancel</button>
                    </div>
                </div>
            </div>
        </Transition>

        <Transition name="slide">
            <div v-if="showResolveModal" class="fixed inset-0 z-[100] flex items-center justify-center">
                <div class="absolute inset-0 bg-slate-900/60 backdrop-blur-sm" @click="showResolveModal = false"></div>
                <div class="relative bg-white w-full max-w-md rounded-2xl shadow-2xl p-8 border-t-4 border-amber-500">
                    <h2 class="text-xl font-black text-slate-900 uppercase mb-2">Resolve Dispute</h2>
                    <p class="text-sm font-semibold text-slate-500 mb-4">Trade <strong>{{ resolveTarget?.reference }}</strong></p>
                    <select v-model="resolveOutcome" class="w-full rounded-xl border-slate-200 bg-slate-50 px-4 py-3 text-sm font-bold mb-4">
                        <option value="released">Release asset to buyer</option>
                        <option value="cancelled">Cancel trade (refund seller)</option>
                    </select>
                    <textarea v-model="resolveReason" placeholder="Resolution notes..." rows="3" class="w-full rounded-xl border-slate-200 bg-slate-50 px-4 py-3 text-sm font-bold mb-4"></textarea>
                    <div class="flex gap-3 justify-end">
                        <button @click="showResolveModal = false" class="rounded-xl bg-slate-100 px-5 py-3 text-xs font-black uppercase tracking-wider text-slate-600">Cancel</button>
                        <button @click="confirmResolve" class="rounded-xl bg-amber-600 px-5 py-3 text-xs font-black uppercase tracking-wider text-white hover:bg-amber-500">Resolve</button>
                    </div>
                </div>
            </div>
        </Transition>
    </div>
</template>

<script setup>
import { onMounted, ref } from 'vue';
import { ArrowLeftRight } from 'lucide-vue-next';
import { api } from '../../../plugins/axios';

const trades = ref({ data: [], current_page: 1, last_page: 1 });
const filters = ref({ reference: '', status: '' });
const showDetail = ref(false);
const selected = ref({});
const disputeMessages = ref([]);
const newMessage = ref('');
const currentUserId = ref(null);
const showCancelModal = ref(false);
const cancelTarget = ref(null);
const showResolveModal = ref(false);
const resolveTarget = ref(null);
const resolveOutcome = ref('released');
const resolveReason = ref('');

const fetchTrades = async (page = 1) => {
    const params = { ...filters.value, page, per_page: 20 };
    Object.keys(params).forEach(k => { if (!params[k]) delete params[k]; });
    const { data } = await api.get('/portal/trades', { params });
    trades.value = data;
};

const statusClass = (s) => ({
    pending: 'bg-blue-100 text-blue-700',
    awaiting_payment: 'bg-amber-100 text-amber-700',
    payment_sent: 'bg-purple-100 text-purple-700',
    released: 'bg-teal-100 text-teal-700',
    completed: 'bg-emerald-100 text-emerald-700',
    cancelled: 'bg-slate-100 text-slate-400',
    disputed: 'bg-rose-100 text-rose-700',
    resolved: 'bg-cyan-100 text-cyan-700',
}[s] || 'bg-slate-100 text-slate-400');

const statusLabel = (s) => ({
    pending: 'Pending',
    awaiting_payment: 'Awaiting Payment',
    payment_sent: 'Payment Sent',
    released: 'Released',
    completed: 'Completed',
    cancelled: 'Cancelled',
    disputed: 'Disputed',
    resolved: 'Resolved',
}[s] || s);

const actorClass = (a) => ({
    buyer: 'text-blue-600',
    seller: 'text-emerald-600',
    system: 'text-slate-500',
    admin: 'text-amber-600',
}[a] || '');

const eventDot = (s) => ({
    pending: 'bg-blue-500',
    awaiting_payment: 'bg-amber-500',
    payment_sent: 'bg-purple-500',
    released: 'bg-teal-500',
    completed: 'bg-emerald-500',
    cancelled: 'bg-slate-300',
    disputed: 'bg-rose-500',
    resolved: 'bg-cyan-500',
}[s] || 'bg-slate-300');

const timeAgo = (date) => {
    const diff = Date.now() - new Date(date).getTime();
    const mins = Math.floor(diff / 60000);
    if (mins < 1) return 'just now';
    if (mins < 60) return `${mins}m ago`;
    const hrs = Math.floor(mins / 60);
    if (hrs < 24) return `${hrs}h ago`;
    return `${Math.floor(hrs / 24)}d ago`;
};

const goToPage = (page) => fetchTrades(page);

const viewTrade = async (trade) => {
    const { data } = await api.get(`/portal/trades/${trade.id}`);
    selected.value = data;
    showDetail.value = true;
    if (data.status === 'disputed') {
        const { data: msgs } = await api.get(`/portal/trades/${trade.id}/dispute-messages`);
        disputeMessages.value = msgs;
    }
    if (!currentUserId.value) {
        const { data: user } = await api.get('/user');
        currentUserId.value = user.id;
    }
};

const openCancelModal = (trade) => {
    cancelTarget.value = trade;
    showCancelModal.value = true;
};

const confirmCancel = async () => {
    await api.post(`/portal/trades/${cancelTarget.value.id}/cancel`);
    showCancelModal.value = false;
    cancelTarget.value = null;
    await fetchTrades();
};

const openResolveModal = (trade) => {
    resolveTarget.value = trade;
    resolveOutcome.value = 'released';
    resolveReason.value = '';
    showResolveModal.value = true;
};

const sendMessage = async () => {
    if (!newMessage.value.trim()) return;
    const { data } = await api.post(`/portal/trades/${selected.value.id}/dispute-messages`, {
        message: newMessage.value,
    });
    disputeMessages.value.push(data);
    newMessage.value = '';
};

const confirmResolve = async () => {
    await api.post(`/portal/trades/${resolveTarget.value.id}/resolve-dispute`, {
        outcome: resolveOutcome.value,
        resolution: resolveReason.value || 'Resolved by admin',
    });
    showResolveModal.value = false;
    resolveTarget.value = null;
    showDetail.value = false;
    await fetchTrades();
};

onMounted(() => fetchTrades());
</script>
