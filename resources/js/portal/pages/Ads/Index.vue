<template>
    <div class="p-6 space-y-6 bg-slate-50 min-h-screen">
        <div class="flex items-center justify-between">
            <h1 class="text-2xl font-black text-slate-800 tracking-tighter uppercase flex items-center gap-3">
                <Megaphone class="w-8 h-8 text-blue-500" /> Advertisements
            </h1>
        </div>

        <div class="bg-white p-4 rounded-2xl border border-slate-200 shadow-sm flex gap-3">
            <select v-model="filters.type" @change="fetchAds" class="rounded-xl bg-slate-50 px-4 py-2 text-xs font-black border-none outline-none">
                <option value="">All Types</option>
                <option value="buy">Buy</option>
                <option value="sell">Sell</option>
            </select>
            <select v-model="filters.status" @change="fetchAds" class="rounded-xl bg-slate-50 px-4 py-2 text-xs font-black border-none outline-none">
                <option value="">All Status</option>
                <option value="active">Active</option>
                <option value="paused">Paused</option>
                <option value="closed">Closed</option>
            </select>
        </div>

        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
            <table class="w-full text-left">
                <thead class="bg-slate-50/50 text-[10px] font-black text-slate-400 uppercase border-b">
                    <tr>
                        <th class="p-5">User</th>
                        <th class="p-5">Type</th>
                        <th class="p-5">Asset/Fiat</th>
                        <th class="p-5">Price</th>
                        <th class="p-5">Limits</th>
                        <th class="p-5">Available</th>
                        <th class="p-5">Status</th>
                        <th class="p-5 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-50">
                    <tr v-for="ad in ads.data" :key="ad.id" class="hover:bg-slate-50/50 transition-colors">
                        <td class="p-5">
                            <p class="font-black text-sm text-slate-800">{{ ad.user?.name }}</p>
                            <p class="text-[10px] text-slate-400">{{ ad.user?.email }}</p>
                        </td>
                        <td class="p-5">
                            <span class="px-3 py-1 rounded-full text-[10px] font-black uppercase" :class="ad.type === 'sell' ? 'bg-rose-100 text-rose-700' : 'bg-emerald-100 text-emerald-700'">
                                {{ ad.type }}
                            </span>
                        </td>
                        <td class="p-5 text-sm font-bold text-slate-700">{{ ad.asset?.code }} / {{ ad.fiat_currency?.code }}</td>
                        <td class="p-5">
                            <span v-if="ad.price_type === 'fixed'" class="text-sm font-black text-slate-800">{{ parseFloat(ad.price).toFixed(2) }}</span>
                            <span v-else class="text-sm font-bold text-slate-500">Floating ({{ ad.margin }}%)</span>
                        </td>
                        <td class="p-5 text-xs text-slate-600">
                            {{ parseFloat(ad.min_order).toFixed(2) }} — {{ parseFloat(ad.max_order).toFixed(2) }}
                        </td>
                        <td class="p-5 text-sm font-bold text-slate-700">{{ parseFloat(ad.available_quantity).toFixed(2) }}</td>
                        <td class="p-5">
                            <span class="px-3 py-1 rounded-full text-[10px] font-black uppercase" :class="statusClass(ad.status)">
                                {{ ad.status }}
                            </span>
                        </td>
                        <td class="p-5 text-right">
                            <button @click="openStatusModal(ad)" class="rounded-lg bg-slate-100 px-3 py-2 text-[10px] font-black text-slate-600 hover:bg-slate-200">Status</button>
                        </td>
                    </tr>
                    <tr v-if="!ads.data?.length">
                        <td colspan="8" class="p-16 text-center text-xs font-black uppercase tracking-widest text-slate-400">No advertisements</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div v-if="ads.last_page > 1" class="flex justify-center gap-2">
            <button v-for="page in ads.last_page" :key="page" @click="goToPage(page)" class="rounded-xl px-4 py-2 text-xs font-black" :class="page === ads.current_page ? 'bg-slate-800 text-white' : 'bg-white text-slate-600 border border-slate-200'">
                {{ page }}
            </button>
        </div>

        <Transition name="slide">
            <div v-if="showModal" class="fixed inset-0 z-[100] flex items-center justify-center">
                <div class="absolute inset-0 bg-slate-900/60 backdrop-blur-sm" @click="showModal = false"></div>
                <div class="relative bg-white w-full max-w-md rounded-2xl shadow-2xl p-8 border-t-4 border-slate-800">
                    <h2 class="text-xl font-black text-slate-900 uppercase mb-2">Change Status</h2>
                    <p class="text-sm font-semibold text-slate-500 mb-6">{{ target?.asset?.code }} / {{ target?.fiat_currency?.code }} — {{ target?.user?.name }}</p>
                    <select v-model="statusValue" class="w-full rounded-xl border-slate-200 bg-slate-50 px-4 py-3 text-sm font-bold mb-6">
                        <option value="active">Active</option>
                        <option value="paused">Paused</option>
                        <option value="closed">Closed</option>
                    </select>
                    <div class="flex gap-3 justify-end">
                        <button @click="showModal = false" class="rounded-xl bg-slate-100 px-5 py-3 text-xs font-black uppercase tracking-wider text-slate-600">Cancel</button>
                        <button @click="updateStatus" class="rounded-xl bg-slate-800 px-5 py-3 text-xs font-black uppercase tracking-wider text-white hover:bg-slate-700">Save</button>
                    </div>
                </div>
            </div>
        </Transition>
    </div>
</template>

<script setup>
import { onMounted, ref } from 'vue';
import { Megaphone } from 'lucide-vue-next';
import { api } from '../../../plugins/axios';

const ads = ref({ data: [], current_page: 1, last_page: 1 });
const filters = ref({ type: '', status: '' });
const showModal = ref(false);
const target = ref(null);
const statusValue = ref('active');

const fetchAds = async (page = 1) => {
    const params = { ...filters.value, page, per_page: 20 };
    const { data } = await api.get('/portal/ads', { params });
    ads.value = data;
};

const statusClass = (s) => ({
    active: 'bg-emerald-100 text-emerald-700',
    paused: 'bg-amber-100 text-amber-700',
    closed: 'bg-slate-100 text-slate-400',
}[s] || 'bg-slate-100 text-slate-400');

const goToPage = (page) => fetchAds(page);

const openStatusModal = (ad) => {
    target.value = ad;
    statusValue.value = ad.status;
    showModal.value = true;
};

const updateStatus = async () => {
    await api.put(`/portal/ads/${target.value.id}`, { status: statusValue.value });
    showModal.value = false;
    await fetchAds();
};

onMounted(() => fetchAds());
</script>
