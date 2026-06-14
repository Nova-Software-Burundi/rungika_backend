<template>
    <div class="p-6 space-y-6 bg-slate-50 min-h-screen">
        <div class="flex items-center justify-between">
            <h1 class="text-2xl font-black text-slate-800 tracking-tighter uppercase flex items-center gap-3">
                <Coins class="w-8 h-8 text-amber-500" /> Assets
            </h1>
            <button @click="openCreate" class="rounded-xl bg-slate-800 px-5 py-3 text-xs font-black uppercase tracking-wider text-white hover:bg-slate-700">New Asset</button>
        </div>

        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
            <table class="w-full text-left">
                <thead class="bg-slate-50/50 text-[10px] font-black text-slate-400 uppercase border-b">
                    <tr>
                        <th class="p-5">Code</th>
                        <th class="p-5">Name</th>
                        <th class="p-5">Decimals</th>
                        <th class="p-5">Enabled</th>
                        <th class="p-5 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-50">
                    <tr v-for="asset in assets" :key="asset.id" class="hover:bg-slate-50/50 transition-colors">
                        <td class="p-5 font-black text-sm text-slate-800">{{ asset.code }}</td>
                        <td class="p-5 text-sm text-slate-600">{{ asset.name }}</td>
                        <td class="p-5 text-sm text-slate-600">{{ asset.decimals }}</td>
                        <td class="p-5">
                            <span class="px-3 py-1 rounded-full text-[10px] font-black uppercase" :class="asset.enabled ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-400'">
                                {{ asset.enabled ? 'Yes' : 'No' }}
                            </span>
                        </td>
                        <td class="p-5 text-right">
                            <button @click="openEdit(asset)" class="rounded-lg bg-slate-100 px-3 py-2 text-[10px] font-black text-slate-600 hover:bg-slate-200">Edit</button>
                        </td>
                    </tr>
                    <tr v-if="!assets.length">
                        <td colspan="5" class="p-16 text-center text-xs font-black uppercase tracking-widest text-slate-400">No assets</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <Transition name="slide">
            <div v-if="showModal" class="fixed inset-0 z-[100] flex items-center justify-center">
                <div class="absolute inset-0 bg-slate-900/60 backdrop-blur-sm" @click="showModal = false"></div>
                <div class="relative bg-white w-full max-w-md rounded-2xl shadow-2xl p-8 border-t-4 border-slate-800">
                    <h2 class="text-xl font-black text-slate-900 uppercase mb-6">{{ editing ? 'Edit' : 'New' }} Asset</h2>
                    <div class="space-y-4">
                        <div>
                            <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Code</label>
                            <input v-model="form.code" class="w-full rounded-xl border-slate-200 bg-slate-50 px-4 py-3 text-sm font-bold mt-1" placeholder="USDT">
                        </div>
                        <div>
                            <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Name</label>
                            <input v-model="form.name" class="w-full rounded-xl border-slate-200 bg-slate-50 px-4 py-3 text-sm font-bold mt-1" placeholder="Tether USD">
                        </div>
                        <div>
                            <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Decimals</label>
                            <input v-model.number="form.decimals" type="number" class="w-full rounded-xl border-slate-200 bg-slate-50 px-4 py-3 text-sm font-bold mt-1">
                        </div>
                        <div class="flex items-center gap-3">
                            <input v-model="form.enabled" type="checkbox" class="w-5 h-5 rounded">
                            <label class="text-xs font-black text-slate-600">Enabled</label>
                        </div>
                    </div>
                    <div class="flex gap-3 justify-end mt-8">
                        <button @click="showModal = false" class="rounded-xl bg-slate-100 px-5 py-3 text-xs font-black uppercase tracking-wider text-slate-600">Cancel</button>
                        <button @click="save" class="rounded-xl bg-slate-800 px-5 py-3 text-xs font-black uppercase tracking-wider text-white hover:bg-slate-700">Save</button>
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

const assets = ref([]);
const showModal = ref(false);
const editing = ref(null);
const form = ref({ code: '', name: '', decimals: 6, enabled: true });

const fetchAssets = async () => {
    const { data } = await api.get('/portal/assets');
    assets.value = data;
};

const openCreate = () => {
    editing.value = null;
    form.value = { code: '', name: '', decimals: 6, enabled: true };
    showModal.value = true;
};

const openEdit = (asset) => {
    editing.value = asset;
    form.value = { ...asset };
    showModal.value = true;
};

const save = async () => {
    if (editing.value) {
        await api.put(`/portal/assets/${editing.value.id}`, form.value);
    } else {
        await api.post('/portal/assets', form.value);
    }
    showModal.value = false;
    await fetchAssets();
};

onMounted(fetchAssets);
</script>
