<template>
    <div class="p-6 space-y-6 bg-slate-50 min-h-screen">
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div>
                <h1 class="text-2xl font-black text-slate-800 flex items-center gap-3 tracking-tighter uppercase">
                    <ShieldCheck class="text-emerald-600 w-8 h-8" /> User Management
                </h1>
            </div>
        </div>

        <div class="grid gap-4 sm:grid-cols-3">
            <div v-for="card in statCards" :key="card.label" class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <p class="text-[10px] font-black uppercase tracking-widest text-slate-400">{{ card.label }}</p>
                <p class="mt-2 text-2xl font-black" :class="card.color">{{ card.value }}</p>
            </div>
        </div>

        <div class="bg-white p-4 rounded-2xl border border-slate-200 shadow-sm">
            <div class="flex flex-col sm:flex-row gap-3">
                <div class="relative flex-1">
                    <Search class="absolute left-3 top-2.5 w-4 h-4 text-slate-400" />
                    <input v-model="filters.q" @input="debouncedFetch" type="text" placeholder="Search by name, email, phone..." class="w-full pl-10 p-2 bg-slate-50 border-none rounded-xl text-xs font-bold outline-none">
                </div>
                <select v-model="filters.role" @change="fetchUsers" class="rounded-xl bg-slate-50 px-4 py-2 text-xs font-black border-none outline-none">
                    <option value="">All Roles</option>
                    <option value="Customer">Customer</option>
                    <option value="Agent">Agent</option>
                    <option value="Driver">Driver</option>
                    <option value="Admin">Admin</option>
                    <option value="super_admin">Super Admin</option>
                </select>
                <select v-model="filters.kyc_status" @change="fetchUsers" class="rounded-xl bg-slate-50 px-4 py-2 text-xs font-black border-none outline-none">
                    <option value="">All KYC Status</option>
                    <option value="pending">Pending</option>
                    <option value="verified">Verified</option>
                    <option value="suspended">Suspended</option>
                </select>
            </div>
        </div>

        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
            <table class="w-full text-left">
                <thead class="bg-slate-50/50 text-[10px] font-black text-slate-400 uppercase border-b">
                    <tr>
                        <th class="p-5">User</th>
                        <th class="p-5">Contact</th>
                        <th class="p-5">Role</th>
                        <th class="p-5">KYC Status</th>
                        <th class="p-5 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-50">
                    <tr v-for="user in users.data" :key="user.id" class="hover:bg-emerald-50/30 transition-colors">
                        <td class="p-5">
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 rounded-xl bg-slate-800 text-white flex items-center justify-center font-black text-xs shadow-lg">
                                    {{ user.name.substring(0, 2).toUpperCase() }}
                                </div>
                                <div>
                                    <p class="font-black text-slate-800 text-sm tracking-tight">{{ user.name }}</p>
                                    <p class="text-[10px] font-bold text-slate-400">{{ user.email }}</p>
                                </div>
                            </div>
                        </td>
                        <td class="p-5">
                            <p class="text-xs font-black text-slate-700">{{ user.phone || '—' }}</p>
                        </td>
                        <td class="p-5">
                            <span v-if="user.roles?.length" class="rounded-full px-3 py-1 text-[10px] font-black uppercase tracking-widest" :class="roleBadge(user.roles[0].name)">
                                {{ user.roles[0].name }}
                            </span>
                            <span v-else class="text-xs font-bold text-slate-400">None</span>
                        </td>
                        <td class="p-5">
                            <span class="rounded-full px-3 py-1 text-[10px] font-black uppercase tracking-widest" :class="kycBadge(user.kyc_status)">
                                {{ user.kyc_status }}
                            </span>
                        </td>
                        <td class="p-5 text-right">
                            <div class="inline-flex items-center gap-2">
                                <button v-if="user.kyc_status === 'pending'" @click="approveKyc(user)" class="rounded-lg bg-emerald-600 px-3 py-2 text-[10px] font-black uppercase tracking-wider text-white hover:bg-emerald-700">
                                    Approve
                                </button>
                                <button v-if="user.kyc_status !== 'suspended'" @click="suspendUser(user)" class="rounded-lg bg-rose-600 px-3 py-2 text-[10px] font-black uppercase tracking-wider text-white hover:bg-rose-700">
                                    Suspend
                                </button>
                                <button @click="openRoleModal(user)" class="rounded-lg bg-slate-800 px-3 py-2 text-[10px] font-black uppercase tracking-wider text-white hover:bg-slate-700">
                                    Role
                                </button>
                            </div>
                        </td>
                    </tr>
                    <tr v-if="!users.data?.length">
                        <td colspan="5" class="p-16 text-center text-xs font-black uppercase tracking-widest text-slate-400">No users found</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div v-if="users.last_page > 1" class="flex justify-center gap-2">
            <button v-for="page in users.last_page" :key="page" @click="goToPage(page)" class="rounded-xl px-4 py-2 text-xs font-black" :class="page === users.current_page ? 'bg-slate-800 text-white' : 'bg-white text-slate-600 border border-slate-200'">
                {{ page }}
            </button>
        </div>

        <Transition name="slide">
            <div v-if="showRoleModal" class="fixed inset-0 z-[100] flex items-center justify-center">
                <div class="absolute inset-0 bg-slate-900/60 backdrop-blur-sm" @click="showRoleModal = false"></div>
                <div class="relative bg-white w-full max-w-md rounded-2xl shadow-2xl p-8 border-t-4 border-slate-800">
                    <h2 class="text-xl font-black text-slate-900 uppercase mb-2">Assign Role</h2>
                    <p class="text-sm font-semibold text-slate-500 mb-6">{{ roleTarget?.name }}</p>

                    <select v-model="selectedRole" class="w-full rounded-xl border-slate-200 bg-slate-50 px-4 py-3 text-sm font-bold mb-6">
                        <option value="Customer">Customer</option>
                        <option value="Agent">Agent</option>
                        <option value="Driver">Driver</option>
                        <option value="Admin">Admin</option>
                        <option value="super_admin">Super Admin</option>
                    </select>

                    <div class="flex gap-3 justify-end">
                        <button @click="showRoleModal = false" class="rounded-xl bg-slate-100 px-5 py-3 text-xs font-black uppercase tracking-wider text-slate-600">Cancel</button>
                        <button @click="assignRole" class="rounded-xl bg-slate-800 px-5 py-3 text-xs font-black uppercase tracking-wider text-white hover:bg-slate-700">Save</button>
                    </div>
                </div>
            </div>
        </Transition>
    </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue';
import { ShieldCheck, Search } from 'lucide-vue-next';
import { api } from '../../../plugins/axios';

const users = ref({ data: [], current_page: 1, last_page: 1 });
const stats = ref({});
const filters = ref({ q: '', role: '', kyc_status: '' });
const debounceTimer = ref(null);
const showRoleModal = ref(false);
const roleTarget = ref(null);
const selectedRole = ref('Customer');

const statCards = computed(() => [
    { label: 'Total', value: stats.value.total || 0, color: 'text-slate-900' },
    { label: 'Pending KYC', value: stats.value.pending || 0, color: 'text-amber-600' },
    { label: 'Verified', value: stats.value.verified || 0, color: 'text-emerald-600' },
    { label: 'Suspended', value: stats.value.suspended || 0, color: 'text-rose-600' },
    { label: 'Customers', value: stats.value.customers || 0, color: 'text-blue-600' },
    { label: 'Agents', value: stats.value.agents || 0, color: 'text-purple-600' },
]);

const roleBadge = (role) => {
    const map = {
        super_admin: 'bg-rose-100 text-rose-700',
        Admin: 'bg-blue-100 text-blue-700',
        Operator: 'bg-indigo-100 text-indigo-700',
        Agent: 'bg-purple-100 text-purple-700',
        Driver: 'bg-orange-100 text-orange-700',
        Customer: 'bg-slate-100 text-slate-700',
    };
    return map[role] || 'bg-slate-100 text-slate-700';
};

const kycBadge = (status) => {
    const map = {
        pending: 'bg-amber-100 text-amber-700',
        verified: 'bg-emerald-100 text-emerald-700',
        suspended: 'bg-rose-100 text-rose-700',
    };
    return map[status] || 'bg-slate-100 text-slate-600';
};

const fetchUsers = async (page = 1) => {
    const params = { ...filters.value, page };
    const { data } = await api.get('/portal/users', { params });
    users.value = data;
};

const fetchStats = async () => {
    const { data } = await api.get('/portal/users/stats');
    stats.value = data;
};

const refresh = async () => {
    await Promise.all([fetchUsers(), fetchStats()]);
};

const debouncedFetch = () => {
    clearTimeout(debounceTimer.value);
    debounceTimer.value = setTimeout(() => fetchUsers(), 300);
};

const goToPage = (page) => {
    fetchUsers(page);
};

const approveKyc = async (user) => {
    await api.post(`/portal/users/${user.id}/approve-kyc`);
    await refresh();
};

const suspendUser = async (user) => {
    await api.post(`/portal/users/${user.id}/suspend`);
    await refresh();
};

const openRoleModal = (user) => {
    roleTarget.value = user;
    selectedRole.value = user.roles?.[0]?.name || 'Customer';
    showRoleModal.value = true;
};

const assignRole = async () => {
    if (!roleTarget.value) return;
    await api.post(`/portal/users/${roleTarget.value.id}/assign-role`, { role: selectedRole.value });
    showRoleModal.value = false;
    await refresh();
};

onMounted(refresh);
</script>
