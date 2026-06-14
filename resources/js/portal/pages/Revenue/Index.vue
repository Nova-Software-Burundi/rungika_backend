<template>
    <div class="p-6 space-y-6 bg-slate-50 min-h-screen">
        <div class="flex items-center justify-between">
            <h1 class="text-2xl font-black text-slate-800 tracking-tighter uppercase flex items-center gap-3">
                <BarChart3 class="w-8 h-8 text-blue-500" /> Revenue Dashboard
            </h1>
        </div>

        <div class="grid grid-cols-4 gap-4">
            <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-6">
                <p class="text-[10px] font-black uppercase tracking-widest text-slate-400 mb-1">Total Trades</p>
                <p class="text-3xl font-black text-slate-800">{{ totals.total_trades || 0 }}</p>
            </div>
            <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-6">
                <p class="text-[10px] font-black uppercase tracking-widest text-slate-400 mb-1">Total Volume</p>
                <p class="text-3xl font-black text-slate-800">{{ formatNum(totals.total_volume) }}</p>
            </div>
            <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-6">
                <p class="text-[10px] font-black uppercase tracking-widest text-slate-400 mb-1">Total Fees</p>
                <p class="text-3xl font-black text-emerald-600">{{ formatNum(totals.total_fees) }}</p>
            </div>
            <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-6">
                <p class="text-[10px] font-black uppercase tracking-widest text-slate-400 mb-1">Buyer / Seller Fees</p>
                <p class="text-sm font-black text-slate-800">B: {{ formatNum(totals.buyer_fees) }} / S: {{ formatNum(totals.seller_fees) }}</p>
            </div>
        </div>

        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-6">
            <div class="flex items-center justify-between mb-6">
                <p class="text-[10px] font-black uppercase tracking-widest text-slate-400">Revenue by Pair</p>
            </div>
            <table class="w-full text-left">
                <thead class="bg-slate-50/50 text-[10px] font-black text-slate-400 uppercase border-b">
                    <tr>
                        <th class="p-4">Pair</th>
                        <th class="p-4 text-right">Trades</th>
                        <th class="p-4 text-right">Volume</th>
                        <th class="p-4 text-right">Fees</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-50">
                    <tr v-for="pair in byPair" :key="pair.asset_id + '-' + pair.fiat_currency_id" class="hover:bg-slate-50/50">
                        <td class="p-4 font-black text-sm text-slate-800">{{ pair.asset?.code }} / {{ pair.fiat_currency?.code }}</td>
                        <td class="p-4 text-sm font-bold text-slate-700 text-right">{{ pair.trade_count }}</td>
                        <td class="p-4 text-sm font-bold text-slate-700 text-right">{{ formatNum(pair.volume) }}</td>
                        <td class="p-4 text-sm font-black text-emerald-600 text-right">{{ formatNum(pair.total_fees) }}</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-6">
            <div class="flex items-center justify-between mb-6">
                <p class="text-[10px] font-black uppercase tracking-widest text-slate-400">Daily Revenue</p>
            </div>
            <table class="w-full text-left">
                <thead class="bg-slate-50/50 text-[10px] font-black text-slate-400 uppercase border-b">
                    <tr>
                        <th class="p-4">Date</th>
                        <th class="p-4 text-right">Trades</th>
                        <th class="p-4 text-right">Volume</th>
                        <th class="p-4 text-right">Buyer Fees</th>
                        <th class="p-4 text-right">Seller Fees</th>
                        <th class="p-4 text-right">Total Fees</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-50">
                    <tr v-for="row in summary.data" :key="row.date" class="hover:bg-slate-50/50">
                        <td class="p-4 font-bold text-sm text-slate-800">{{ row.date }}</td>
                        <td class="p-4 text-sm font-bold text-slate-700 text-right">{{ row.trade_count }}</td>
                        <td class="p-4 text-sm font-bold text-slate-700 text-right">{{ formatNum(row.volume) }}</td>
                        <td class="p-4 text-sm text-right">{{ formatNum(row.buyer_fees) }}</td>
                        <td class="p-4 text-sm text-right">{{ formatNum(row.seller_fees) }}</td>
                        <td class="p-4 text-sm font-black text-emerald-600 text-right">{{ formatNum(row.total_fees) }}</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>

<script setup>
import { onMounted, ref } from 'vue';
import { BarChart3 } from 'lucide-vue-next';
import { api } from '../../../plugins/axios';

const totals = ref({});
const byPair = ref([]);
const summary = ref({ data: [] });

const fetchData = async () => {
    const [t, p, s] = await Promise.all([
        api.get('/portal/revenue/totals'),
        api.get('/portal/revenue/by-pair'),
        api.get('/portal/revenue/summary', { params: { group_by: 'day', per_page: 60 } }),
    ]);
    totals.value = t.data;
    byPair.value = p.data;
    summary.value = s.data;
};

const formatNum = (n) => {
    if (!n && n !== 0) return '—';
    return parseFloat(n).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
};

onMounted(() => fetchData());
</script>
