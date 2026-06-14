<template>
    <div class="p-6 space-y-6 bg-slate-50 min-h-screen">
        <div class="flex items-center justify-between">
            <h1 class="text-2xl font-black text-slate-800 tracking-tighter uppercase flex items-center gap-3">
                <Megaphone class="w-8 h-8 text-blue-500" /> Announcements
            </h1>
            <button @click="openCreateModal" class="rounded-xl bg-slate-800 px-5 py-3 text-xs font-black uppercase tracking-wider text-white hover:bg-slate-700">+ New</button>
        </div>

        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
            <table class="w-full text-left">
                <thead class="bg-slate-50/50 text-[10px] font-black text-slate-400 uppercase border-b">
                    <tr>
                        <th class="p-5">Title</th>
                        <th class="p-5">Body</th>
                        <th class="p-5">Author</th>
                        <th class="p-5">Status</th>
                        <th class="p-5">Date</th>
                        <th class="p-5 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-50">
                    <tr v-for="a in announcements.data" :key="a.id" class="hover:bg-slate-50/50 transition-colors">
                        <td class="p-5 font-black text-sm text-slate-800">{{ a.title }}</td>
                        <td class="p-5 text-xs text-slate-500 max-w-xs truncate">{{ a.body }}</td>
                        <td class="p-5 text-sm font-bold text-slate-700">{{ a.user?.name }}</td>
                        <td class="p-5">
                            <span class="px-3 py-1 rounded-full text-[10px] font-black uppercase" :class="a.published ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-400'">{{ a.published ? 'Published' : 'Draft' }}</span>
                        </td>
                        <td class="p-5 text-xs text-slate-400">{{ a.published_at || a.created_at }}</td>
                        <td class="p-5 text-right">
                            <button @click="openEditModal(a)" class="rounded-lg bg-slate-100 px-3 py-2 text-[10px] font-black text-slate-600 hover:bg-slate-200">Edit</button>
                            <button @click="togglePublish(a)" class="rounded-lg px-3 py-2 text-[10px] font-black" :class="a.published ? 'bg-amber-100 text-amber-600 hover:bg-amber-200' : 'bg-emerald-100 text-emerald-600 hover:bg-emerald-200'">{{ a.published ? 'Unpublish' : 'Publish' }}</button>
                        </td>
                    </tr>
                    <tr v-if="!announcements.data?.length">
                        <td colspan="6" class="p-16 text-center text-xs font-black uppercase tracking-widest text-slate-400">No announcements</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div v-if="announcements.last_page > 1" class="flex justify-center gap-2">
            <button v-for="page in announcements.last_page" :key="page" @click="fetchAnnouncements(page)" class="rounded-xl px-4 py-2 text-xs font-black" :class="page === announcements.current_page ? 'bg-slate-800 text-white' : 'bg-white text-slate-600 border border-slate-200'">{{ page }}</button>
        </div>

        <Transition name="slide">
            <div v-if="showModal" class="fixed inset-0 z-[100] flex items-center justify-center">
                <div class="absolute inset-0 bg-slate-900/60 backdrop-blur-sm" @click="showModal = false"></div>
                <div class="relative bg-white w-full max-w-md rounded-2xl shadow-2xl p-8 border-t-4 border-blue-500">
                    <h2 class="text-xl font-black text-slate-900 uppercase mb-6">{{ editing ? 'Edit' : 'New' }} Announcement</h2>
                    <div class="space-y-4">
                        <input v-model="form.title" placeholder="Title" class="w-full rounded-xl border-slate-200 bg-slate-50 px-4 py-3 text-sm font-bold" />
                        <textarea v-model="form.body" placeholder="Body" rows="5" class="w-full rounded-xl border-slate-200 bg-slate-50 px-4 py-3 text-sm font-bold"></textarea>
                    </div>
                    <div class="flex gap-3 justify-end mt-6">
                        <button @click="showModal = false" class="rounded-xl bg-slate-100 px-5 py-3 text-xs font-black uppercase tracking-wider text-slate-600">Cancel</button>
                        <button @click="saveAnnouncement" class="rounded-xl bg-blue-600 px-5 py-3 text-xs font-black uppercase tracking-wider text-white hover:bg-blue-500">{{ editing ? 'Update' : 'Create' }}</button>
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

const announcements = ref({ data: [], current_page: 1, last_page: 1 });
const showModal = ref(false);
const editing = ref(false);
const form = ref({ title: '', body: '' });

const fetchAnnouncements = async (page = 1) => {
    const { data } = await api.get('/portal/announcements', { params: { page, per_page: 20 } });
    announcements.value = data;
};

const openCreateModal = () => {
    editing.value = false;
    form.value = { title: '', body: '' };
    showModal.value = true;
};

const openEditModal = (a) => {
    editing.value = true;
    form.value = { ...a };
    showModal.value = true;
};

const saveAnnouncement = async () => {
    if (editing.value) {
        await api.put(`/portal/announcements/${form.value.id}`, form.value);
    } else {
        await api.post('/portal/announcements', form.value);
    }
    showModal.value = false;
    await fetchAnnouncements();
};

const togglePublish = async (a) => {
    await api.post(`/portal/announcements/${a.id}/toggle-publish`);
    await fetchAnnouncements();
};

onMounted(() => fetchAnnouncements());
</script>
