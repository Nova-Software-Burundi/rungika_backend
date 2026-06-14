<template>
    <div class="p-6 space-y-6 bg-slate-50 min-h-screen">
        <div class="flex items-center justify-between">
            <h1 class="text-2xl font-black text-slate-800 tracking-tighter uppercase flex items-center gap-3">
                <Coins class="w-8 h-8 text-blue-500" /> Platform Fees
            </h1>
            <button @click="openCreateModal" class="rounded-xl bg-slate-800 px-5 py-3 text-xs font-black uppercase tracking-wider text-white hover:bg-slate-700">+ New Fee</button>
        </div>

        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
            <table class="w-full text-left">
                <thead class="bg-slate-50/50 text-[10px] font-black text-slate-400 uppercase border-b">
                    <tr>
                        <th class="p-5">Asset / Fiat</th>
                        <th class="p-5">Buyer Fee</th>
                        <th class="p-5">Seller Fee</th>
                        <th class="p-5">Min / Max</th>
                        <th class="p-5">Status</th>
                        <th class="p-5 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-50">
                    <tr v-for="fee in fees.data" :key="fee.id" class="hover:bg-slate-50/50 transition-colors">
                        <td class="p-5 font-black text-sm text-slate-800">{{ fee.asset?.code }} / {{ fee.fiat_currency?.code }}</td>
                        <td class="p-5 text-sm font-bold text-slate-700">{{ fee.buyer_fee_value }}{{ fee.buyer_fee_type === 'percentage' ? '%' : '' }}</td>
                        <td class="p-5 text-sm font-bold text-slate-700">{{ fee.seller_fee_value }}{{ fee.seller_fee_type === 'percentage' ? '%' : '' }}</td>
                        <td class="p-5 text-xs text-slate-600">{{ fee.min_fee ?? '—' }} / {{ fee.max_fee ?? '—' }}</td>
                        <td class="p-5">
                            <span class="px-3 py-1 rounded-full text-[10px] font-black uppercase" :class="fee.enabled ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-400'">{{ fee.enabled ? 'Active' : 'Disabled' }}</span>
                        </td>
                        <td class="p-5 text-right">
                            <button @click="openEditModal(fee)" class="rounded-lg bg-slate-100 px-3 py-2 text-[10px] font-black text-slate-600 hover:bg-slate-200">Edit</button>
                        </td>
                    </tr>
                    <tr v-if="!fees.data?.length">
                        <td colspan="6" class="p-16 text-center text-xs font-black uppercase tracking-widest text-slate-400">No fee configurations</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div v-if="fees.last_page > 1" class="flex justify-center gap-2">
            <button v-for="page in fees.last_page" :key="page" @click="fetchFees(page)" class="rounded-xl px-4 py-2 text-xs font-black" :class="page === fees.current_page ? 'bg-slate-800 text-white' : 'bg-white text-slate-600 border border-slate-200'">{{ page }}</button>
        </div>

        <Transition name="slide">
            <div v-if="showModal" class="fixed inset-0 z-[100] flex items-center justify-center">
                <div class="absolute inset-0 bg-slate-900/60 backdrop-blur-sm" @click="showModal = false"></div>
                <div class="relative bg-white w-full max-w-md rounded-2xl shadow-2xl p-8 border-t-4 border-slate-800 max-h-[90vh] overflow-y-auto">
                    <h2 class="text-xl font-black text-slate-900 uppercase mb-6">{{ editing ? 'Edit' : 'New' }} Fee Configuration</h2>
                    <div class="space-y-4">
                        <select v-model="form.asset_id" class="w-full rounded-xl border-slate-200 bg-slate-50 px-4 py-3 text-sm font-bold" :disabled="editing">
                            <option value="">Select Asset</option>
                            <option v-for="a in assets" :key="a.id" :value="a.id">{{ a.code }}</option>
                        </select>
                        <select v-model="form.fiat_currency_id" class="w-full rounded-xl border-slate-200 bg-slate-50 px-4 py-3 text-sm font-bold" :disabled="editing">
                            <option value="">Select Fiat</option>
                            <option v-for="c in currencies" :key="c.id" :value="c.id">{{ c.code }}</option>
                        </select>
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="text-[10px] font-black uppercase text-slate-400 mb-1 block">Buyer Type</label>
                                <select v-model="form.buyer_fee_type" class="w-full rounded-xl border-slate-200 bg-slate-50 px-4 py-3 text-sm font-bold">
                                    <option value="percentage">%</option>
                                    <option value="fixed">Fixed</option>
                                </select>
                            </div>
                            <div>
                                <label class="text-[10px] font-black uppercase text-slate-400 mb-1 block">Buyer Value</label>
                                <input v-model="form.buyer_fee_value" type="number" step="0.0001" class="w-full rounded-xl border-slate-200 bg-slate-50 px-4 py-3 text-sm font-bold" />
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="text-[10px] font-black uppercase text-slate-400 mb-1 block">Seller Type</label>
                                <select v-model="form.seller_fee_type" class="w-full rounded-xl border-slate-200 bg-slate-50 px-4 py-3 text-sm font-bold">
                                    <option value="percentage">%</option>
                                    <option value="fixed">Fixed</option>
                                </select>
                            </div>
                            <div>
                                <label class="text-[10px] font-black uppercase text-slate-400 mb-1 block">Seller Value</label>
                                <input v-model="form.seller_fee_value" type="number" step="0.0001" class="w-full rounded-xl border-slate-200 bg-slate-50 px-4 py-3 text-sm font-bold" />
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="text-[10px] font-black uppercase text-slate-400 mb-1 block">Min Fee</label>
                                <input v-model="form.min_fee" type="number" step="0.0001" placeholder="Optional" class="w-full rounded-xl border-slate-200 bg-slate-50 px-4 py-3 text-sm font-bold" />
                            </div>
                            <div>
                                <label class="text-[10px] font-black uppercase text-slate-400 mb-1 block">Max Fee</label>
                                <input v-model="form.max_fee" type="number" step="0.0001" placeholder="Optional" class="w-full rounded-xl border-slate-200 bg-slate-50 px-4 py-3 text-sm font-bold" />
                            </div>
                        </div>
                        <label class="flex items-center gap-3">
                            <input v-model="form.enabled" type="checkbox" class="rounded border-slate-300" />
                            <span class="text-sm font-bold text-slate-700">Enabled</span>
                        </label>
                    </div>
                    <div class="flex gap-3 justify-end mt-6">
                        <button @click="showModal = false" class="rounded-xl bg-slate-100 px-5 py-3 text-xs font-black uppercase tracking-wider text-slate-600">Cancel</button>
                        <button @click="saveFee" class="rounded-xl bg-slate-800 px-5 py-3 text-xs font-black uppercase tracking-wider text-white hover:bg-slate-700">{{ editing ? 'Update' : 'Create' }}</button>
                    </div>
                </div>
            </div>
        </Transition>
    </div>
</template>

<script setup>
import { onMounted, ref } from 'vue';
import { Coins } from 'lucide-vue-next';
import { api } from '../../../plugins/axios';

const fees = ref({ data: [], current_page: 1, last_page: 1 });
const assets = ref([]);
const currencies = ref([]);
const showModal = ref(false);
const editing = ref(false);
const form = ref({ asset_id: '', fiat_currency_id: '', buyer_fee_type: 'percentage', buyer_fee_value: 0, seller_fee_type: 'percentage', seller_fee_value: 0, min_fee: null, max_fee: null, enabled: true });

const fetchFees = async (page = 1) => {
    const { data } = await api.get('/portal/platform-fees', { params: { page, per_page: 20 } });
    fees.value = data;
};

const fetchReferences = async () => {
    const [a, c] = await Promise.all([
        api.get('/portal/assets', { params: { per_page: 100 } }),
        api.get('/portal/currencies', { params: { per_page: 100 } }),
    ]);
    assets.value = a.data.data || a.data;
    currencies.value = c.data.data || c.data;
};

const openCreateModal = async () => {
    editing.value = false;
    form.value = { asset_id: '', fiat_currency_id: '', buyer_fee_type: 'percentage', buyer_fee_value: 0, seller_fee_type: 'percentage', seller_fee_value: 0, min_fee: null, max_fee: null, enabled: true };
    showModal.value = true;
};

const openEditModal = (fee) => {
    editing.value = true;
    form.value = { ...fee, min_fee: fee.min_fee ?? null, max_fee: fee.max_fee ?? null };
    showModal.value = true;
};

const saveFee = async () => {
    const payload = { ...form.value };
    if (!payload.min_fee && payload.min_fee !== 0) delete payload.min_fee;
    if (!payload.max_fee && payload.max_fee !== 0) delete payload.max_fee;
    if (editing.value) {
        await api.put(`/portal/platform-fees/${form.value.id}`, payload);
    } else {
        await api.post('/portal/platform-fees', payload);
    }
    showModal.value = false;
    await fetchFees();
};

onMounted(() => { fetchFees(); fetchReferences(); });
</script>
