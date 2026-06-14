<template>
    <div class="p-6 space-y-6 bg-slate-50 min-h-screen">
        <div class="flex items-center justify-between">
            <h1 class="text-2xl font-black text-slate-800 tracking-tighter uppercase flex items-center gap-3">
                <TrendingUp class="w-8 h-8 text-blue-500" /> Reference Prices
            </h1>
            <button @click="openCreateModal" class="rounded-xl bg-slate-800 px-5 py-3 text-xs font-black uppercase tracking-wider text-white hover:bg-slate-700">+ New Price</button>
        </div>

        <div class="bg-white p-4 rounded-2xl border border-slate-200 shadow-sm flex gap-3">
            <select v-model="filterAsset" @change="fetchPrices" class="rounded-xl bg-slate-50 px-4 py-2 text-xs font-black border-none outline-none">
                <option value="">All Assets</option>
                <option v-for="a in assets" :key="a.id" :value="a.id">{{ a.code }}</option>
            </select>
        </div>

        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
            <table class="w-full text-left">
                <thead class="bg-slate-50/50 text-[10px] font-black text-slate-400 uppercase border-b">
                    <tr>
                        <th class="p-5">Asset</th>
                        <th class="p-5">Fiat</th>
                        <th class="p-5">Price</th>
                        <th class="p-5">Source</th>
                        <th class="p-5">Valid At</th>
                        <th class="p-5 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-50">
                    <tr v-for="price in prices.data" :key="price.id" class="hover:bg-slate-50/50 transition-colors">
                        <td class="p-5 font-black text-sm text-slate-800">{{ price.asset?.code }}</td>
                        <td class="p-5 font-bold text-sm text-slate-700">{{ price.fiat_currency?.code }}</td>
                        <td class="p-5 font-black text-sm text-slate-800">{{ parseFloat(price.price).toFixed(4) }}</td>
                        <td class="p-5 text-xs font-bold text-slate-500 uppercase">{{ price.source }}</td>
                        <td class="p-5 text-xs text-slate-600">{{ price.valid_at }}</td>
                        <td class="p-5 text-right">
                            <button @click="openEditModal(price)" class="rounded-lg bg-slate-100 px-3 py-2 text-[10px] font-black text-slate-600 hover:bg-slate-200">Edit</button>
                        </td>
                    </tr>
                    <tr v-if="!prices.data?.length">
                        <td colspan="6" class="p-16 text-center text-xs font-black uppercase tracking-widest text-slate-400">No reference prices</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div v-if="prices.last_page > 1" class="flex justify-center gap-2">
            <button v-for="page in prices.last_page" :key="page" @click="fetchPrices(page)" class="rounded-xl px-4 py-2 text-xs font-black" :class="page === prices.current_page ? 'bg-slate-800 text-white' : 'bg-white text-slate-600 border border-slate-200'">{{ page }}</button>
        </div>

        <Transition name="slide">
            <div v-if="showModal" class="fixed inset-0 z-[100] flex items-center justify-center">
                <div class="absolute inset-0 bg-slate-900/60 backdrop-blur-sm" @click="showModal = false"></div>
                <div class="relative bg-white w-full max-w-md rounded-2xl shadow-2xl p-8 border-t-4 border-slate-800">
                    <h2 class="text-xl font-black text-slate-900 uppercase mb-6">{{ editing ? 'Edit' : 'New' }} Reference Price</h2>
                    <div class="space-y-4">
                        <select v-model="form.asset_id" class="w-full rounded-xl border-slate-200 bg-slate-50 px-4 py-3 text-sm font-bold" :disabled="editing">
                            <option value="">Select Asset</option>
                            <option v-for="a in assets" :key="a.id" :value="a.id">{{ a.code }}</option>
                        </select>
                        <select v-model="form.fiat_currency_id" class="w-full rounded-xl border-slate-200 bg-slate-50 px-4 py-3 text-sm font-bold" :disabled="editing">
                            <option value="">Select Fiat</option>
                            <option v-for="c in currencies" :key="c.id" :value="c.id">{{ c.code }}</option>
                        </select>
                        <input v-model="form.price" type="number" step="0.0001" placeholder="Price" class="w-full rounded-xl border-slate-200 bg-slate-50 px-4 py-3 text-sm font-bold" />
                        <input v-model="form.source" placeholder="Source (e.g. manual)" class="w-full rounded-xl border-slate-200 bg-slate-50 px-4 py-3 text-sm font-bold" />
                    </div>
                    <div class="flex gap-3 justify-end mt-6">
                        <button @click="showModal = false" class="rounded-xl bg-slate-100 px-5 py-3 text-xs font-black uppercase tracking-wider text-slate-600">Cancel</button>
                        <button @click="savePrice" class="rounded-xl bg-slate-800 px-5 py-3 text-xs font-black uppercase tracking-wider text-white hover:bg-slate-700">{{ editing ? 'Update' : 'Create' }}</button>
                    </div>
                </div>
            </div>
        </Transition>
    </div>
</template>

<script setup>
import { onMounted, ref } from 'vue';
import { TrendingUp } from 'lucide-vue-next';
import { api } from '../../../plugins/axios';

const prices = ref({ data: [], current_page: 1, last_page: 1 });
const assets = ref([]);
const currencies = ref([]);
const filterAsset = ref('');
const showModal = ref(false);
const editing = ref(false);
const form = ref({ asset_id: '', fiat_currency_id: '', price: '', source: 'manual' });

const fetchPrices = async (page = 1) => {
    const params = { page, per_page: 20 };
    if (filterAsset.value) params.asset_id = filterAsset.value;
    const { data } = await api.get('/portal/reference-prices', { params });
    prices.value = data;
};

const fetchReferences = async () => {
    const [a, c] = await Promise.all([
        api.get('/portal/assets', { params: { per_page: 100 } }),
        api.get('/portal/currencies', { params: { per_page: 100 } }),
    ]);
    assets.value = a.data.data || a.data;
    currencies.value = c.data.data || c.data;
};

const openCreateModal = () => {
    editing.value = false;
    form.value = { asset_id: '', fiat_currency_id: '', price: '', source: 'manual' };
    showModal.value = true;
};

const openEditModal = (price) => {
    editing.value = true;
    form.value = { ...price };
    showModal.value = true;
};

const savePrice = async () => {
    if (editing.value) {
        await api.put(`/portal/reference-prices/${form.value.id}`, form.value);
    } else {
        await api.post('/portal/reference-prices', form.value);
    }
    showModal.value = false;
    await fetchPrices();
};

onMounted(() => { fetchPrices(); fetchReferences(); });
</script>
