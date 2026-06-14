<template>
    <div class="p-6 space-y-6 bg-slate-50 min-h-screen">
        <div class="flex items-center justify-between">
            <h1 class="text-2xl font-black text-slate-800 tracking-tighter uppercase flex items-center gap-3">
                <Star class="w-8 h-8 text-amber-500" /> Ratings
            </h1>
        </div>

        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
            <table class="w-full text-left">
                <thead class="bg-slate-50/50 text-[10px] font-black text-slate-400 uppercase border-b">
                    <tr>
                        <th class="p-5">Trade</th>
                        <th class="p-5">Rater</th>
                        <th class="p-5">Rated User</th>
                        <th class="p-5">Rating</th>
                        <th class="p-5">Comment</th>
                        <th class="p-5">Date</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-50">
                    <tr v-for="rating in ratings.data" :key="rating.id" class="hover:bg-slate-50/50 transition-colors">
                        <td class="p-5 font-black text-sm text-slate-800">{{ rating.trade?.reference || '—' }}</td>
                        <td class="p-5 font-bold text-sm text-slate-700">{{ rating.rater?.name }}</td>
                        <td class="p-5 font-bold text-sm text-slate-700">{{ rating.rated_user?.name }}</td>
                        <td class="p-5">
                            <span class="font-black text-sm" :class="ratingClass(rating.rating)">{{ rating.rating }}/5</span>
                        </td>
                        <td class="p-5 text-xs text-slate-500 max-w-xs truncate">{{ rating.comment || '—' }}</td>
                        <td class="p-5 text-xs text-slate-400">{{ timeAgo(rating.created_at) }}</td>
                    </tr>
                    <tr v-if="!ratings.data?.length">
                        <td colspan="6" class="p-16 text-center text-xs font-black uppercase tracking-widest text-slate-400">No ratings yet</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div v-if="ratings.last_page > 1" class="flex justify-center gap-2">
            <button v-for="page in ratings.last_page" :key="page" @click="fetchRatings(page)" class="rounded-xl px-4 py-2 text-xs font-black" :class="page === ratings.current_page ? 'bg-slate-800 text-white' : 'bg-white text-slate-600 border border-slate-200'">{{ page }}</button>
        </div>
    </div>
</template>

<script setup>
import { onMounted, ref } from 'vue';
import { Star } from 'lucide-vue-next';
import { api } from '../../../plugins/axios';

const ratings = ref({ data: [], current_page: 1, last_page: 1 });

const fetchRatings = async (page = 1) => {
    const { data } = await api.get('/portal/ratings', { params: { page, per_page: 20 } });
    ratings.value = data;
};

const ratingClass = (r) => ({
    1: 'text-rose-600',
    2: 'text-orange-500',
    3: 'text-amber-500',
    4: 'text-lime-500',
    5: 'text-emerald-600',
}[r] || 'text-slate-500');

const timeAgo = (date) => {
    const diff = Date.now() - new Date(date).getTime();
    const mins = Math.floor(diff / 60000);
    if (mins < 1) return 'just now';
    if (mins < 60) return `${mins}m ago`;
    const hrs = Math.floor(mins / 60);
    if (hrs < 24) return `${hrs}h ago`;
    return `${Math.floor(hrs / 24)}d ago`;
};

onMounted(() => fetchRatings());
</script>
