<template>
    <div class="p-6 space-y-6">
        <div class="flex items-center justify-between">
            <h1 class="text-2xl font-bold text-slate-800 flex items-center gap-3">
                <LifeBuoy class="text-indigo-600 w-7 h-7" /> Support
            </h1>
            <div class="flex items-center gap-3">
                <button @click="showCreateModal = true" v-if="tab === 'tickets'" class="bg-indigo-600 text-white px-4 py-2 rounded-lg text-sm font-semibold hover:bg-indigo-700 transition">
                    New Ticket
                </button>
                <button @click="showCategoryModal = true" v-if="tab === 'categories'" class="bg-indigo-600 text-white px-4 py-2 rounded-lg text-sm font-semibold hover:bg-indigo-700 transition">
                    New Category
                </button>
            </div>
        </div>

        <div class="flex gap-4 border-b">
            <button @click="tab = 'tickets'" :class="[tab === 'tickets' ? 'border-indigo-600 text-indigo-600' : 'border-transparent text-slate-500 hover:text-slate-700']" class="pb-3 border-b-2 text-sm font-semibold transition">
                Tickets
            </button>
            <button @click="tab = 'categories'; fetchCategories()" :class="[tab === 'categories' ? 'border-indigo-600 text-indigo-600' : 'border-transparent text-slate-500 hover:text-slate-700']" class="pb-3 border-b-2 text-sm font-semibold transition">
                Categories
            </button>
        </div>

        <div v-if="tab === 'tickets'">
            <div class="flex flex-wrap gap-2 mb-4">
                <button
                    @click="activeCategoryId = null"
                    :class="[!activeCategoryId ? 'bg-slate-900 text-white' : 'bg-white text-slate-600 border hover:border-indigo-300']"
                    class="px-4 py-2 rounded-lg border text-xs font-semibold transition">
                    All <span class="opacity-60 ml-1">{{ allTickets.length }}</span>
                </button>
                <button
                    v-for="cat in categories"
                    :key="cat.id"
                    @click="activeCategoryId = cat.id"
                    :class="[activeCategoryId === cat.id ? 'bg-indigo-600 text-white border-indigo-600' : 'bg-white text-slate-600 border hover:border-indigo-300']"
                    class="px-4 py-2 rounded-lg border text-xs font-semibold transition">
                    {{ cat.name }}
                    <span :class="activeCategoryId === cat.id ? 'bg-white text-indigo-600' : 'bg-slate-100 text-slate-500'" class="ml-1 px-1.5 py-0.5 rounded text-[10px]">{{ categoryCount(cat.id) }}</span>
                </button>
            </div>

            <div class="bg-white rounded-xl border shadow-sm overflow-hidden">
                <table class="w-full text-left">
                    <thead class="bg-slate-50 text-xs font-semibold text-slate-500 uppercase border-b">
                        <tr>
                            <th class="p-4">Reference</th>
                            <th class="p-4">User</th>
                            <th class="p-4">Issue</th>
                            <th class="p-4">Priority</th>
                            <th class="p-4">Status</th>
                            <th class="p-4 text-right">Created</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr v-for="t in filteredTickets" :key="t.id" @click="openTicket(t)" class="hover:bg-indigo-50/40 transition-colors cursor-pointer">
                            <td class="p-4">
                                <span class="text-xs font-semibold text-slate-700 bg-slate-100 px-2.5 py-1.5 rounded-lg">#{{ t.reference }}</span>
                            </td>
                            <td class="p-4">
                                <div class="flex items-center gap-2">
                                    <div class="w-8 h-8 rounded-lg bg-slate-700 text-white flex items-center justify-center text-xs font-bold">
                                        {{ (t.user?.name || '?').substring(0, 2).toUpperCase() }}
                                    </div>
                                    <span class="text-sm font-medium text-slate-700">{{ t.user?.name || 'Unknown' }}</span>
                                </div>
                            </td>
                            <td class="p-4">
                                <p class="text-sm font-medium text-slate-800">{{ t.title }}</p>
                                <p class="text-[10px] font-semibold text-slate-400 uppercase mt-0.5">{{ t.category?.name || 'Uncategorized' }}</p>
                            </td>
                            <td class="p-4">
                                <span :class="priorityClass(t.priority)" class="text-[10px] font-bold px-2 py-1 rounded uppercase">{{ t.priority }}</span>
                            </td>
                            <td class="p-4">
                                <span :class="statusClass(t.status)" class="text-[10px] font-bold px-2 py-1 rounded uppercase">{{ t.status }}</span>
                            </td>
                            <td class="p-4 text-right">
                                <span class="text-xs text-slate-500">{{ timeAgo(t.created_at) }}</span>
                            </td>
                        </tr>
                        <tr v-if="loading && allTickets.length === 0">
                            <td colspan="6" class="p-12 text-center text-slate-400 text-sm">Loading tickets...</td>
                        </tr>
                        <tr v-if="!loading && allTickets.length === 0">
                            <td colspan="6" class="p-12 text-center text-slate-400 text-sm font-medium">No tickets found</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div v-if="allTickets.length && lastPage > 1" class="flex justify-center gap-2 mt-4">
                <button @click="changePage(currentPage - 1)" :disabled="currentPage <= 1" class="px-3 py-1.5 rounded border text-xs font-medium disabled:opacity-30 bg-white hover:bg-slate-50 transition">Prev</button>
                <span class="px-3 py-1.5 text-xs text-slate-500">Page {{ currentPage }} of {{ lastPage }}</span>
                <button @click="changePage(currentPage + 1)" :disabled="currentPage >= lastPage" class="px-3 py-1.5 rounded border text-xs font-medium disabled:opacity-30 bg-white hover:bg-slate-50 transition">Next</button>
            </div>
        </div>

        <div v-if="tab === 'categories'">
            <div class="bg-white rounded-xl border shadow-sm overflow-hidden">
                <table class="w-full text-left">
                    <thead class="bg-slate-50 text-xs font-semibold text-slate-500 uppercase border-b">
                        <tr>
                            <th class="p-4">Name</th>
                            <th class="p-4">Description</th>
                            <th class="p-4">Active</th>
                            <th class="p-4 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr v-for="cat in allCategories" :key="cat.id">
                            <td class="p-4 text-sm font-medium text-slate-800">{{ cat.name }}</td>
                            <td class="p-4 text-sm text-slate-500">{{ cat.description || '—' }}</td>
                            <td class="p-4">
                                <span :class="cat.is_active ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-500'" class="text-[10px] font-bold px-2 py-1 rounded uppercase">{{ cat.is_active ? 'Yes' : 'No' }}</span>
                            </td>
                            <td class="p-4 text-right">
                                <button @click="editCategory(cat)" class="text-xs font-semibold text-indigo-600 hover:text-indigo-800 transition mr-3">Edit</button>
                                <button @click="deleteCategory(cat)" class="text-xs font-semibold text-rose-600 hover:text-rose-800 transition">Delete</button>
                            </td>
                        </tr>
                        <tr v-if="allCategories.length === 0">
                            <td colspan="4" class="p-12 text-center text-slate-400 text-sm font-medium">No categories found</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <Transition name="slide">
            <div v-if="activeTicket" class="fixed inset-0 z-[100] flex justify-end">
                <div class="absolute inset-0 bg-slate-900/40 backdrop-blur-sm" @click="activeTicket = null"></div>
                <div class="relative bg-white w-full max-w-3xl h-full shadow-2xl flex flex-col">
                    <div class="p-5 border-b flex items-center justify-between bg-white sticky top-0 z-10">
                        <div>
                            <h2 class="text-lg font-bold text-slate-900">{{ activeTicket.title }}</h2>
                            <p class="text-xs text-slate-500">#{{ activeTicket.reference }} &middot; {{ activeTicket.category?.name }} &middot; {{ activeTicket.user?.name }}</p>
                        </div>
                        <div class="flex items-center gap-2">
                            <select v-model="statusUpdate" @change="updateTicketStatus" class="text-xs border rounded-lg px-2 py-1.5 bg-white">
                                <option value="open">Open</option>
                                <option value="in_progress">In Progress</option>
                                <option value="waiting">Waiting</option>
                                <option value="resolved">Resolved</option>
                                <option value="closed">Closed</option>
                            </select>
                            <button @click="activeTicket = null" class="p-1.5 rounded-lg hover:bg-slate-100 transition-colors">
                                <X class="w-5 h-5 text-slate-400" />
                            </button>
                        </div>
                    </div>

                    <div class="flex-1 overflow-y-auto p-6 space-y-4">
                        <div class="bg-slate-50 rounded-xl p-4 border">
                            <p class="text-sm text-slate-700 whitespace-pre-wrap">{{ activeTicket.description }}</p>
                        </div>

                        <div v-for="msg in activeTicket.messages" :key="msg.id"
                             :class="['flex flex-col max-w-[85%]', String(msg.user_id) === String(currentUserId) ? 'ml-auto items-end' : 'items-start']">
                            <div :class="[
                                'p-3 rounded-2xl text-sm',
                                String(msg.user_id) === String(currentUserId) ? 'bg-indigo-600 text-white' : 'bg-slate-100 text-slate-700'
                            ]">
                                {{ msg.message }}
                            </div>
                            <div class="flex items-center gap-2 mt-1 px-1">
                                <span class="text-[10px] text-slate-400">{{ msg.author?.name || 'Unknown' }}</span>
                                <span class="text-[10px] text-slate-400">{{ timeAgo(msg.created_at) }}</span>
                            </div>
                        </div>

                        <div v-if="activeTicket.events?.length" class="border-t pt-4 mt-4">
                            <h4 class="text-xs font-semibold text-slate-400 uppercase mb-3">Event Log</h4>
                            <div v-for="ev in activeTicket.events" :key="ev.id" class="flex items-center gap-2 text-xs text-slate-500 mb-2">
                                <span class="w-1.5 h-1.5 rounded-full bg-slate-300"></span>
                                <span class="font-medium">{{ ev.actor?.name || 'System' }}</span>
                                <span>{{ ev.type.replace('_', ' ') }}</span>
                                <span v-if="ev.payload?.from">&mdash; {{ ev.payload.from }} &rarr; {{ ev.payload.to }}</span>
                                <span class="ml-auto">{{ timeAgo(ev.created_at) }}</span>
                            </div>
                        </div>
                    </div>

                    <div class="p-4 border-t bg-white">
                        <form @submit.prevent="sendReply" class="flex gap-2">
                            <input v-model="replyText" placeholder="Type a message..." class="flex-1 border rounded-lg px-4 py-2.5 text-sm focus:ring-1 focus:ring-indigo-500">
                            <button type="submit" class="bg-indigo-600 text-white px-4 py-2.5 rounded-lg text-sm font-semibold hover:bg-indigo-700 transition flex items-center gap-2">
                                <Send class="w-4 h-4" /> Send
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </Transition>

        <Transition name="fade">
            <div v-if="showCreateModal" class="fixed inset-0 z-[100] flex items-center justify-center">
                <div class="absolute inset-0 bg-slate-900/40 backdrop-blur-sm" @click="showCreateModal = false"></div>
                <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-lg p-6">
                    <h2 class="text-lg font-bold text-slate-900 mb-4">New Support Ticket</h2>
                    <form @submit.prevent="createTicket">
                        <div class="space-y-4">
                            <div>
                                <label class="text-xs font-semibold text-slate-500 uppercase">Category</label>
                                <select v-model="form.category_id" required class="w-full mt-1 border rounded-lg px-3 py-2.5 text-sm">
                                    <option value="" disabled>Select category</option>
                                    <option v-for="cat in categories" :key="cat.id" :value="cat.id">{{ cat.name }}</option>
                                </select>
                            </div>
                            <div>
                                <label class="text-xs font-semibold text-slate-500 uppercase">Priority</label>
                                <select v-model="form.priority" class="w-full mt-1 border rounded-lg px-3 py-2.5 text-sm">
                                    <option value="normal">Normal</option>
                                    <option value="high">High</option>
                                    <option value="urgent">Urgent</option>
                                    <option value="low">Low</option>
                                </select>
                            </div>
                            <div>
                                <label class="text-xs font-semibold text-slate-500 uppercase">Title</label>
                                <input v-model="form.title" required class="w-full mt-1 border rounded-lg px-3 py-2.5 text-sm" placeholder="Brief summary">
                            </div>
                            <div>
                                <label class="text-xs font-semibold text-slate-500 uppercase">Description</label>
                                <textarea v-model="form.description" required rows="4" class="w-full mt-1 border rounded-lg px-3 py-2.5 text-sm" placeholder="Describe the issue..."></textarea>
                            </div>
                        </div>
                        <div class="flex justify-end gap-3 mt-6">
                            <button @click="showCreateModal = false" type="button" class="px-4 py-2 text-sm font-medium text-slate-600 hover:text-slate-800 transition">Cancel</button>
                            <button type="submit" class="bg-indigo-600 text-white px-4 py-2 rounded-lg text-sm font-semibold hover:bg-indigo-700 transition">Submit</button>
                        </div>
                    </form>
                </div>
            </div>
        </Transition>

        <Transition name="fade">
            <div v-if="showCategoryModal" class="fixed inset-0 z-[100] flex items-center justify-center">
                <div class="absolute inset-0 bg-slate-900/40 backdrop-blur-sm" @click="closeCategoryModal"></div>
                <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-lg p-6">
                    <h2 class="text-lg font-bold text-slate-900 mb-4">{{ editingCategory ? 'Edit Category' : 'New Category' }}</h2>
                    <form @submit.prevent="saveCategory">
                        <div class="space-y-4">
                            <div>
                                <label class="text-xs font-semibold text-slate-500 uppercase">Name</label>
                                <input v-model="catForm.name" required class="w-full mt-1 border rounded-lg px-3 py-2.5 text-sm" placeholder="e.g. Fuel">
                            </div>
                            <div>
                                <label class="text-xs font-semibold text-slate-500 uppercase">Description</label>
                                <textarea v-model="catForm.description" rows="3" class="w-full mt-1 border rounded-lg px-3 py-2.5 text-sm" placeholder="Optional description"></textarea>
                            </div>
                            <div v-if="editingCategory" class="flex items-center gap-2">
                                <input v-model="catForm.is_active" type="checkbox" id="is_active" class="rounded border-slate-300">
                                <label for="is_active" class="text-sm text-slate-700">Active</label>
                            </div>
                        </div>
                        <div class="flex justify-end gap-3 mt-6">
                            <button @click="closeCategoryModal" type="button" class="px-4 py-2 text-sm font-medium text-slate-600 hover:text-slate-800 transition">Cancel</button>
                            <button type="submit" class="bg-indigo-600 text-white px-4 py-2 rounded-lg text-sm font-semibold hover:bg-indigo-700 transition">{{ editingCategory ? 'Update' : 'Create' }}</button>
                        </div>
                    </form>
                </div>
            </div>
        </Transition>
    </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { LifeBuoy, X, Send } from 'lucide-vue-next';
import { api } from '../../../plugins/axios';

const tab = ref('tickets');
const categories = ref([]);
const allCategories = ref([]);
const allTickets = ref([]);
const activeCategoryId = ref(null);
const activeTicket = ref(null);
const replyText = ref('');
const showCreateModal = ref(false);
const showCategoryModal = ref(false);
const editingCategory = ref(null);
const loading = ref(true);
const currentPage = ref(1);
const lastPage = ref(1);
const currentUserId = ref(null);
const statusUpdate = ref('open');

const form = ref({
    category_id: '',
    priority: 'normal',
    title: '',
    description: '',
});

const catForm = ref({
    name: '',
    description: '',
    is_active: true,
});

onMounted(async () => {
    try {
        const [catRes, userRes] = await Promise.all([
            api.get('portal/support/categories'),
            api.get('user'),
        ]);
        categories.value = catRes.data;
        currentUserId.value = userRes.data.id;
    } catch (e) {
        console.error('Failed to load categories', e);
    }
    await fetchTickets();
});

async function fetchTickets() {
    loading.value = true;
    try {
        const params = { page: currentPage.value };
        if (activeCategoryId.value) {
            params.category_id = activeCategoryId.value;
        }
        const { data } = await api.get('portal/support/tickets', { params });
        allTickets.value = data.data;
        currentPage.value = data.current_page;
        lastPage.value = data.last_page;
    } catch (e) {
        console.error('Failed to load tickets', e);
    } finally {
        loading.value = false;
    }
}

async function fetchCategories() {
    try {
        const { data } = await api.get('portal/support/categories/all');
        allCategories.value = data;
    } catch (e) {
        console.error('Failed to load categories', e);
    }
}

function changePage(page) {
    currentPage.value = page;
    fetchTickets();
}

const categoryCount = (id) => allTickets.value.filter(t => t.support_category_id === id).length;

const filteredTickets = computed(() => {
    if (!activeCategoryId.value) return allTickets.value;
    return allTickets.value.filter(t => t.support_category_id === activeCategoryId.value);
});

function priorityClass(p) {
    return {
        urgent: 'bg-rose-100 text-rose-700',
        high: 'bg-orange-100 text-orange-700',
        normal: 'bg-blue-100 text-blue-700',
        low: 'bg-slate-100 text-slate-600',
    }[p] || 'bg-slate-100 text-slate-600';
}

function statusClass(s) {
    return {
        open: 'bg-sky-100 text-sky-700',
        in_progress: 'bg-amber-100 text-amber-700',
        waiting: 'bg-purple-100 text-purple-700',
        resolved: 'bg-emerald-100 text-emerald-700',
        closed: 'bg-slate-200 text-slate-500',
    }[s] || 'bg-slate-100 text-slate-600';
}

function timeAgo(date) {
    if (!date) return '';
    const ms = Date.now() - new Date(date).getTime();
    const mins = Math.floor(ms / 60000);
    if (mins < 1) return 'just now';
    if (mins < 60) return mins + 'm ago';
    const hrs = Math.floor(mins / 60);
    if (hrs < 24) return hrs + 'h ago';
    const days = Math.floor(hrs / 24);
    return days + 'd ago';
}

async function openTicket(t) {
    statusUpdate.value = t.status;
    try {
        const { data } = await api.get(`portal/support/tickets/${t.id}`);
        activeTicket.value = data;
    } catch (e) {
        console.error('Failed to load ticket', e);
    }
}

async function sendReply() {
    if (!replyText.value || !activeTicket.value) return;
    try {
        await api.post(`portal/support/tickets/${activeTicket.value.id}/messages`, {
            message: replyText.value,
        });
        const { data } = await api.get(`portal/support/tickets/${activeTicket.value.id}`);
        activeTicket.value = data;
        replyText.value = '';
    } catch (e) {
        console.error('Failed to send reply', e);
    }
}

async function updateTicketStatus() {
    if (!activeTicket.value) return;
    try {
        await api.patch(`portal/support/tickets/${activeTicket.value.id}/status`, {
            status: statusUpdate.value,
        });
        const { data } = await api.get(`portal/support/tickets/${activeTicket.value.id}`);
        activeTicket.value = data;
        await fetchTickets();
    } catch (e) {
        console.error('Failed to update status', e);
    }
}

async function createTicket() {
    try {
        await api.post('portal/support/tickets', {
            support_category_id: form.value.category_id,
            title: form.value.title,
            description: form.value.description,
            priority: form.value.priority,
        });
        showCreateModal.value = false;
        form.value = { category_id: '', priority: 'normal', title: '', description: '' };
        currentPage.value = 1;
        await fetchTickets();
    } catch (e) {
        console.error('Failed to create ticket', e);
    }
}

function editCategory(cat) {
    editingCategory.value = cat;
    catForm.value = {
        name: cat.name,
        description: cat.description || '',
        is_active: cat.is_active,
    };
    showCategoryModal.value = true;
}

function closeCategoryModal() {
    showCategoryModal.value = false;
    editingCategory.value = null;
    catForm.value = { name: '', description: '', is_active: true };
}

async function saveCategory() {
    try {
        if (editingCategory.value) {
            await api.put(`portal/support/categories/${editingCategory.value.id}`, catForm.value);
        } else {
            await api.post('portal/support/categories', catForm.value);
        }
        closeCategoryModal();
        await fetchCategories();
        const { data } = await api.get('portal/support/categories');
        categories.value = data;
    } catch (e) {
        console.error('Failed to save category', e);
    }
}

async function deleteCategory(cat) {
    if (!confirm(`Delete category "${cat.name}"?`)) return;
    try {
        await api.delete(`portal/support/categories/${cat.id}`);
        await fetchCategories();
        const { data } = await api.get('portal/support/categories');
        categories.value = data;
    } catch (e) {
        console.error('Failed to delete category', e);
    }
}
</script>

<style scoped>
.slide-enter-active, .slide-leave-active { transition: transform 0.3s ease; }
.slide-enter-from, .slide-leave-to { transform: translateX(100%); }
.fade-enter-active, .fade-leave-active { transition: opacity 0.2s ease; }
.fade-enter-from, .fade-leave-to { opacity: 0; }
</style>
