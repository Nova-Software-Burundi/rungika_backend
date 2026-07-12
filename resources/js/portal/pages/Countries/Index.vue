<template>
    <div class="p-6 space-y-6 bg-slate-50 min-h-screen">
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div>
                <h1 class="text-2xl font-black text-slate-800 flex items-center gap-3 tracking-tighter uppercase">
                    <Globe class="text-emerald-600 w-8 h-8" /> Countries
                </h1>
            </div>
            <div class="flex items-center gap-3">
                <button @click="runSeeder" :disabled="seeding" class="px-6 py-3 bg-indigo-600 text-white rounded-2xl shadow-xl font-black text-xs flex items-center gap-2 uppercase">
                    <RefreshCw class="w-4 h-4" :class="{ 'animate-spin': seeding }" /> {{ seeding ? 'Seeding...' : 'Seed Countries' }}
                </button>
                <button @click="openCreateModal" class="px-6 py-3 bg-emerald-600 text-white rounded-2xl shadow-xl font-black text-xs flex items-center gap-2 uppercase">
                    <Plus class="w-4 h-4" /> Add Country
                </button>
            </div>
        </div>

        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
            <table class="w-full text-left">
                <thead class="bg-slate-50/50 text-[10px] font-black text-slate-400 uppercase border-b">
                    <tr>
                        <th class="p-5">Flag</th>
                        <th class="p-5">Code</th>
                        <th class="p-5">Name</th>
                        <th class="p-5">Phone Code</th>
                        <th class="p-5">Active</th>
                        <th class="p-5 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-50">
                    <tr v-for="country in countries" :key="country.id" class="hover:bg-emerald-50/30 transition-colors">
                        <td class="p-5">
                            <img v-if="country.flag_url"
                                 :src="country.flag_url"
                                 :alt="country.code + ' flag'"
                                 class="w-10 h-7 rounded object-cover shadow-sm border border-slate-200"
                                 @error="onFlagError($event)"
                            />
                            <span v-else class="text-lg font-black text-slate-300">{{ country.code }}</span>
                        </td>
                        <td class="p-5">
                            <span class="font-black text-slate-800 text-sm tracking-tight">{{ country.code }}</span>
                        </td>
                        <td class="p-5 text-sm font-bold text-slate-600">{{ country.name }}</td>
                        <td class="p-5 text-sm font-bold text-slate-700">{{ country.phone_code || '—' }}</td>
                        <td class="p-5">
                            <span v-if="country.is_active" class="rounded-full bg-emerald-100 text-emerald-700 px-3 py-1 text-[10px] font-black uppercase tracking-widest">Active</span>
                            <span v-else class="rounded-full bg-slate-100 text-slate-400 px-3 py-1 text-[10px] font-black uppercase tracking-widest">Inactive</span>
                        </td>
                        <td class="p-5 text-right">
                            <div class="inline-flex items-center gap-2">
                                <button @click="openEditModal(country)" class="rounded-lg bg-slate-800 px-3 py-2 text-[10px] font-black uppercase tracking-wider text-white hover:bg-slate-700">Edit</button>
                                <button @click="deleteCountry(country)" class="rounded-lg bg-rose-600 px-3 py-2 text-[10px] font-black uppercase tracking-wider text-white hover:bg-rose-700">Delete</button>
                            </div>
                        </td>
                    </tr>
                    <tr v-if="!countries.length">
                        <td colspan="6" class="p-16 text-center text-xs font-black uppercase tracking-widest text-slate-400">No countries found</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <Transition name="slide">
            <div v-if="showModal" class="fixed inset-0 z-[100] flex items-center justify-center">
                <div class="absolute inset-0 bg-slate-900/60 backdrop-blur-sm" @click="showModal = false"></div>
                <div class="relative bg-white w-full max-w-md rounded-2xl shadow-2xl p-8 border-t-4" :class="editing ? 'border-slate-800' : 'border-emerald-600'">
                    <h2 class="text-xl font-black text-slate-900 uppercase mb-6">{{ editing ? 'Edit Country' : 'Add Country' }}</h2>

                    <form @submit.prevent="saveCountry" class="space-y-4">
                        <label class="block">
                            <span class="text-[10px] font-black uppercase tracking-widest text-slate-400">Name</span>
                            <input v-model="form.name" required class="mt-1 w-full rounded-xl border-slate-200 bg-slate-50 px-4 py-3 text-sm font-bold focus:border-emerald-500 focus:ring-emerald-500" />
                        </label>
                        <div class="grid grid-cols-2 gap-4">
                            <label class="block">
                                <span class="text-[10px] font-black uppercase tracking-widest text-slate-400">ISO Code (2 letters)</span>
                                <input v-model="form.code" required maxlength="2" class="mt-1 w-full rounded-xl border-slate-200 bg-slate-50 px-4 py-3 text-sm font-bold uppercase focus:border-emerald-500 focus:ring-emerald-500" />
                            </label>
                            <label class="block">
                                <span class="text-[10px] font-black uppercase tracking-widest text-slate-400">Phone Code</span>
                                <input v-model="form.phone_code" placeholder="+260" class="mt-1 w-full rounded-xl border-slate-200 bg-slate-50 px-4 py-3 text-sm font-bold focus:border-emerald-500 focus:ring-emerald-500" />
                            </label>
                        </div>
                        <label class="block">
                            <span class="text-[10px] font-black uppercase tracking-widest text-slate-400">Flag URL</span>
                            <input v-model="form.flag_url" placeholder="https://flagcdn.com/w80/zm.png" class="mt-1 w-full rounded-xl border-slate-200 bg-slate-50 px-4 py-3 text-sm font-bold focus:border-emerald-500 focus:ring-emerald-500" />
                        </label>
                        <label class="flex items-center gap-3">
                            <input v-model="form.is_active" type="checkbox" class="rounded border-slate-300 text-emerald-600 focus:ring-emerald-500" />
                            <span class="text-xs font-black uppercase tracking-widest text-slate-500">Active</span>
                        </label>

                        <p v-if="formError" class="rounded-xl bg-rose-50 px-4 py-3 text-sm font-bold text-rose-700">{{ formError }}</p>

                        <div class="flex gap-3 justify-end pt-4">
                            <button type="button" @click="showModal = false" class="rounded-xl bg-slate-100 px-5 py-3 text-xs font-black uppercase tracking-wider text-slate-600">Cancel</button>
                            <button type="submit" :disabled="saving" class="rounded-xl bg-slate-800 px-5 py-3 text-xs font-black uppercase tracking-wider text-white hover:bg-slate-700 disabled:opacity-50">
                                {{ editing ? 'Update' : 'Create' }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </Transition>
    </div>
</template>

<script setup>
import { onMounted, ref } from 'vue';
import { Globe, Plus, RefreshCw } from 'lucide-vue-next';
import { api } from '../../../plugins/axios';

const countries = ref([]);
const showModal = ref(false);
const editing = ref(false);
const saving = ref(false);
const seeding = ref(false);
const formError = ref('');
const editingId = ref(null);

const form = ref({
    name: '',
    code: '',
    phone_code: '',
    flag_url: '',
    is_active: true,
});

const fetchCountries = async () => {
    const { data } = await api.get('/portal/countries');
    countries.value = data;
};

const openCreateModal = () => {
    editing.value = false;
    editingId.value = null;
    form.value = { name: '', code: '', phone_code: '', flag_url: '', is_active: true };
    formError.value = '';
    showModal.value = true;
};

const openEditModal = (country) => {
    editing.value = true;
    editingId.value = country.id;
    form.value = {
        name: country.name,
        code: country.code,
        phone_code: country.phone_code || '',
        flag_url: country.flag_url || '',
        is_active: country.is_active,
    };
    formError.value = '';
    showModal.value = true;
};

const saveCountry = async () => {
    saving.value = true;
    formError.value = '';
    try {
        if (editing.value) {
            await api.put(`/portal/countries/${editingId.value}`, form.value);
        } else {
            await api.post('/portal/countries', form.value);
        }
        showModal.value = false;
        await fetchCountries();
    } catch (err) {
        formError.value = err.response?.data?.message || 'Failed to save country.';
    } finally {
        saving.value = false;
    }
};

const deleteCountry = async (country) => {
    if (!confirm(`Delete ${country.name}?`)) return;
    try {
        await api.delete(`/portal/countries/${country.id}`);
        await fetchCountries();
    } catch (err) {
        alert(err.response?.data?.message || 'Failed to delete country.');
    }
};

const runSeeder = async () => {
    seeding.value = true;
    try {
        await api.post('/portal/countries/seed');
        await fetchCountries();
    } catch (err) {
        alert(err.response?.data?.message || 'Failed to seed countries.');
    } finally {
        seeding.value = false;
    }
};

const onFlagError = (e) => {
    e.target.style.display = 'none';
};

onMounted(fetchCountries);
</script>
