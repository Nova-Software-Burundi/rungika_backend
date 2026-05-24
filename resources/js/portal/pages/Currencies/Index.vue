<template>
    <div class="p-6 space-y-6 bg-slate-50 min-h-screen">
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div>
                <h1 class="text-2xl font-black text-slate-800 flex items-center gap-3 tracking-tighter uppercase">
                    <CircleDollarSign class="text-emerald-600 w-8 h-8" /> Currencies
                </h1>
            </div>
            <button @click="openCreateModal" class="px-6 py-3 bg-emerald-600 text-white rounded-2xl shadow-xl font-black text-xs flex items-center gap-2 uppercase">
                <Plus class="w-4 h-4" /> Add Currency
            </button>
        </div>

        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
            <table class="w-full text-left">
                <thead class="bg-slate-50/50 text-[10px] font-black text-slate-400 uppercase border-b">
                    <tr>
                        <th class="p-5">Code</th>
                        <th class="p-5">Name</th>
                        <th class="p-5">Symbol</th>
                        <th class="p-5">Default</th>
                        <th class="p-5 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-50">
                    <tr v-for="currency in currencies" :key="currency.id" class="hover:bg-emerald-50/30 transition-colors">
                        <td class="p-5">
                            <span class="font-black text-slate-800 text-sm tracking-tight">{{ currency.code }}</span>
                        </td>
                        <td class="p-5 text-sm font-bold text-slate-600">{{ currency.name }}</td>
                        <td class="p-5 text-lg font-black text-slate-700">{{ currency.symbol || '—' }}</td>
                        <td class="p-5">
                            <span v-if="currency.is_default" class="rounded-full bg-emerald-100 text-emerald-700 px-3 py-1 text-[10px] font-black uppercase tracking-widest">Default</span>
                            <span v-else class="text-xs font-bold text-slate-400">—</span>
                        </td>
                        <td class="p-5 text-right">
                            <div class="inline-flex items-center gap-2">
                                <button @click="openEditModal(currency)" class="rounded-lg bg-slate-800 px-3 py-2 text-[10px] font-black uppercase tracking-wider text-white hover:bg-slate-700">Edit</button>
                                <button @click="viewRates(currency)" class="rounded-lg bg-indigo-600 px-3 py-2 text-[10px] font-black uppercase tracking-wider text-white hover:bg-indigo-700">Rates</button>
                                <button @click="deleteCurrency(currency)" class="rounded-lg bg-rose-600 px-3 py-2 text-[10px] font-black uppercase tracking-wider text-white hover:bg-rose-700">Delete</button>
                            </div>
                        </td>
                    </tr>
                    <tr v-if="!currencies.length">
                        <td colspan="5" class="p-16 text-center text-xs font-black uppercase tracking-widest text-slate-400">No currencies found</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <Transition name="slide">
            <div v-if="showModal" class="fixed inset-0 z-[100] flex items-center justify-center">
                <div class="absolute inset-0 bg-slate-900/60 backdrop-blur-sm" @click="showModal = false"></div>
                <div class="relative bg-white w-full max-w-md rounded-2xl shadow-2xl p-8 border-t-4" :class="editing ? 'border-slate-800' : 'border-emerald-600'">
                    <h2 class="text-xl font-black text-slate-900 uppercase mb-6">{{ editing ? 'Edit Currency' : 'Add Currency' }}</h2>

                    <form @submit.prevent="saveCurrency" class="space-y-4">
                        <label class="block">
                            <span class="text-[10px] font-black uppercase tracking-widest text-slate-400">ISO Code</span>
                            <input v-model="form.code" required maxlength="3" class="mt-1 w-full rounded-xl border-slate-200 bg-slate-50 px-4 py-3 text-sm font-bold uppercase focus:border-emerald-500 focus:ring-emerald-500" />
                        </label>
                        <label class="block">
                            <span class="text-[10px] font-black uppercase tracking-widest text-slate-400">Name</span>
                            <input v-model="form.name" required class="mt-1 w-full rounded-xl border-slate-200 bg-slate-50 px-4 py-3 text-sm font-bold focus:border-emerald-500 focus:ring-emerald-500" />
                        </label>
                        <label class="block">
                            <span class="text-[10px] font-black uppercase tracking-widest text-slate-400">Symbol</span>
                            <input v-model="form.symbol" maxlength="5" class="mt-1 w-full rounded-xl border-slate-200 bg-slate-50 px-4 py-3 text-sm font-bold focus:border-emerald-500 focus:ring-emerald-500" />
                        </label>
                        <label class="flex items-center gap-3">
                            <input v-model="form.is_default" type="checkbox" class="rounded border-slate-300 text-emerald-600 focus:ring-emerald-500" />
                            <span class="text-xs font-black uppercase tracking-widest text-slate-500">Default Currency</span>
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

        <Transition name="slide">
            <div v-if="showRatesModal" class="fixed inset-0 z-[100] flex items-center justify-center">
                <div class="absolute inset-0 bg-slate-900/60 backdrop-blur-sm" @click="showRatesModal = false"></div>
                <div class="relative bg-white w-full max-w-2xl rounded-2xl shadow-2xl p-8 border-t-4 border-indigo-600 max-h-[80vh] flex flex-col">
                    <div class="flex items-center justify-between mb-6">
                        <div>
                            <h2 class="text-xl font-black text-slate-900 uppercase">Exchange Rates</h2>
                            <p class="text-sm font-semibold text-slate-500">{{ ratesCurrency?.code }} — {{ ratesCurrency?.name }}</p>
                        </div>
                        <button @click="showAddRate = true" class="rounded-lg bg-indigo-600 px-4 py-2 text-[10px] font-black uppercase tracking-wider text-white hover:bg-indigo-700">Add Rate</button>
                    </div>

                    <div v-if="showAddRate" class="mb-6 rounded-xl border border-slate-200 p-4">
                        <form @submit.prevent="saveExchangeRate" class="grid grid-cols-2 gap-3">
                            <label class="block">
                                <span class="text-[10px] font-black uppercase tracking-widest text-slate-400">Base Currency</span>
                                <select v-model="rateForm.base_currency_id" required class="mt-1 w-full rounded-xl border-slate-200 bg-slate-50 px-4 py-3 text-sm font-bold">
                                    <option v-for="c in currencies" :key="c.id" :value="c.id">{{ c.code }} — {{ c.name }}</option>
                                </select>
                            </label>
                            <label class="block">
                                <span class="text-[10px] font-black uppercase tracking-widest text-slate-400">Target Currency</span>
                                <select v-model="rateForm.target_currency_id" required class="mt-1 w-full rounded-xl border-slate-200 bg-slate-50 px-4 py-3 text-sm font-bold">
                                    <option v-for="c in currencies" :key="c.id" :value="c.id">{{ c.code }} — {{ c.name }}</option>
                                </select>
                            </label>
                            <label class="block">
                                <span class="text-[10px] font-black uppercase tracking-widest text-slate-400">Rate</span>
                                <input v-model="rateForm.rate" required type="number" min="0.000001" step="0.000001" class="mt-1 w-full rounded-xl border-slate-200 bg-slate-50 px-4 py-3 text-sm font-bold" />
                            </label>
                            <label class="block">
                                <span class="text-[10px] font-black uppercase tracking-widest text-slate-400">Valid From</span>
                                <input v-model="rateForm.valid_from" required type="datetime-local" class="mt-1 w-full rounded-xl border-slate-200 bg-slate-50 px-4 py-3 text-sm font-bold" />
                            </label>
                            <div class="col-span-2 flex gap-3 justify-end">
                                <button type="button" @click="showAddRate = false" class="rounded-lg bg-slate-100 px-4 py-2 text-[10px] font-black uppercase tracking-wider text-slate-600">Cancel</button>
                                <button type="submit" class="rounded-lg bg-indigo-600 px-4 py-2 text-[10px] font-black uppercase tracking-wider text-white">Save</button>
                            </div>
                        </form>
                    </div>

                    <div class="overflow-y-auto flex-1">
                        <table class="w-full text-left">
                            <thead class="bg-slate-50/50 text-[10px] font-black text-slate-400 uppercase border-b sticky top-0">
                                <tr>
                                    <th class="p-3">Pair</th>
                                    <th class="p-3">Rate</th>
                                    <th class="p-3">Valid From</th>
                                    <th class="p-3">Valid To</th>
                                    <th class="p-3 text-right"></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-50">
                                <tr v-for="rate in exchangeRates" :key="rate.id" class="hover:bg-indigo-50/30">
                                    <td class="p-3">
                                        <span class="font-black text-sm">{{ rate.base_currency }} → {{ rate.target_currency }}</span>
                                    </td>
                                    <td class="p-3 font-bold text-sm">{{ Number(rate.rate).toFixed(6) }}</td>
                                    <td class="p-3 text-xs font-semibold text-slate-500">{{ formatDate(rate.valid_from) }}</td>
                                    <td class="p-3 text-xs font-semibold text-slate-500">{{ rate.valid_to ? formatDate(rate.valid_to) : '—' }}</td>
                                    <td class="p-3 text-right">
                                        <button @click="deleteRate(rate)" class="text-rose-600 text-xs font-black uppercase hover:text-rose-800">Delete</button>
                                    </td>
                                </tr>
                                <tr v-if="!exchangeRates.length">
                                    <td colspan="5" class="p-10 text-center text-xs font-black uppercase tracking-widest text-slate-400">No exchange rates</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </Transition>
    </div>
</template>

<script setup>
import { onMounted, ref } from 'vue';
import { CircleDollarSign, Plus } from 'lucide-vue-next';
import { api } from '../../../plugins/axios';

const currencies = ref([]);
const exchangeRates = ref([]);
const showModal = ref(false);
const showRatesModal = ref(false);
const showAddRate = ref(false);
const editing = ref(false);
const saving = ref(false);
const formError = ref('');
const ratesCurrency = ref(null);

const editingId = ref(null);

const form = ref({
    code: '',
    name: '',
    symbol: '',
    is_default: false,
});

const rateForm = ref({
    base_currency_id: '',
    target_currency_id: '',
    rate: '',
    valid_from: '',
});

const fetchCurrencies = async () => {
    const { data } = await api.get('/portal/currencies');
    currencies.value = data;
};

const openCreateModal = () => {
    editing.value = false;
    editingId.value = null;
    form.value = { code: '', name: '', symbol: '', is_default: false };
    formError.value = '';
    showModal.value = true;
};

const openEditModal = (currency) => {
    editing.value = true;
    editingId.value = currency.id;
    form.value = {
        code: currency.code,
        name: currency.name,
        symbol: currency.symbol || '',
        is_default: currency.is_default,
    };
    formError.value = '';
    showModal.value = true;
};

const saveCurrency = async () => {
    saving.value = true;
    formError.value = '';
    try {
        if (editing.value) {
            await api.put(`/portal/currencies/${editingId.value}`, form.value);
        } else {
            await api.post('/portal/currencies', form.value);
        }
        showModal.value = false;
        await fetchCurrencies();
    } catch (err) {
        formError.value = err.response?.data?.message || 'Failed to save currency.';
    } finally {
        saving.value = false;
    }
};

const deleteCurrency = async (currency) => {
    if (!confirm(`Delete ${currency.code}?`)) return;
    try {
        await api.delete(`/portal/currencies/${currency.id}`);
        await fetchCurrencies();
    } catch (err) {
        alert(err.response?.data?.message || 'Failed to delete currency.');
    }
};

const viewRates = async (currency) => {
    ratesCurrency.value = currency;
    showAddRate.value = false;
    rateForm.value = { base_currency_id: currency.id, target_currency_id: '', rate: '', valid_from: '' };
    const { data } = await api.get(`/portal/currencies/${currency.id}/exchange-rates`);
    exchangeRates.value = data;
    showRatesModal.value = true;
};

const saveExchangeRate = async () => {
    try {
        await api.post('/portal/exchange-rates', {
            ...rateForm.value,
            valid_from: new Date(rateForm.value.valid_from).toISOString(),
        });
        showAddRate.value = false;
        const { data } = await api.get(`/portal/currencies/${ratesCurrency.value.id}/exchange-rates`);
        exchangeRates.value = data;
    } catch (err) {
        alert(err.response?.data?.message || 'Failed to save rate.');
    }
};

const deleteRate = async (rate) => {
    if (!confirm('Delete this exchange rate?')) return;
    try {
        await api.delete(`/portal/exchange-rates/${rate.id}`);
        const { data } = await api.get(`/portal/currencies/${ratesCurrency.value.id}/exchange-rates`);
        exchangeRates.value = data;
    } catch (err) {
        alert(err.response?.data?.message || 'Failed to delete rate.');
    }
};

const formatDate = (date) => {
    if (!date) return '';
    return new Intl.DateTimeFormat(undefined, {
        dateStyle: 'short',
        timeStyle: 'short',
    }).format(new Date(date));
};

onMounted(fetchCurrencies);
</script>
