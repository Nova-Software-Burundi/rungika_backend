<template>
    <header class="h-16 bg-white border-b border-gray-200 flex items-center justify-between px-6 sticky top-0 z-40">
        <div class="flex-1">
            <p class="text-xs font-black uppercase tracking-widest text-slate-400">Remittance Operations</p>
            <p class="text-sm font-semibold text-slate-700">Agent Marketplace</p>
        </div>

        <div class="flex items-center gap-4">
            <button class="text-gray-500 hover:text-gray-700 p-2 rounded-lg hover:bg-gray-50"><Bell class="w-5 h-5" /></button>

            <div class="relative">
                <button @click="isDropdownOpen = !isDropdownOpen" class="flex items-center gap-3 p-1 rounded-xl hover:bg-gray-50 transition-all">
                    <div class="w-10 h-10 rounded-xl bg-orange-600 text-white flex items-center justify-center font-black text-xs shadow-md">
                        {{ userInitials }}
                    </div>
                    <div class="hidden md:flex flex-col text-left">
                        <span class="text-sm font-semibold text-gray-700 leading-none">{{ authStore.user?.name }}</span>
                        <span class="text-[10px] text-gray-500 font-bold uppercase tracking-tighter">Agent</span>
                    </div>
                    <ChevronDown class="w-4 h-4 text-gray-400 transition-transform" :class="{'rotate-180': isDropdownOpen}" />
                </button>

                <div v-if="isDropdownOpen" class="absolute right-0 mt-2 w-48 bg-white rounded-xl shadow-xl border border-gray-100 py-2 z-50">
                    <button class="w-full flex items-center gap-3 px-4 py-2 text-sm text-gray-600 hover:bg-gray-50"><User class="w-4 h-4" /> My Profile</button>
                    <button class="w-full flex items-center gap-3 px-4 py-2 text-sm text-gray-600 hover:bg-gray-50"><Settings class="w-4 h-4" /> Settings</button>
                    <hr class="my-2 border-gray-100" />
                    <button @click="showLogoutModal = true; isDropdownOpen = false" class="w-full flex items-center gap-3 px-4 py-2 text-sm text-red-600 hover:bg-red-50">
                        <LogOut class="w-4 h-4" /> Sign Out
                    </button>
                </div>
            </div>
        </div>
    </header>

    <LogoutModal
        :show="showLogoutModal"
        @close="showLogoutModal = false"
        @confirm="confirmLogout"
    />
</template>

<script setup>
import { ref, computed } from 'vue';
import { useAuthStore } from "../../store/authStore";
import LogoutModal from "../modals/LogoutModal.vue";
import { Bell, LogOut, ChevronDown, User, Settings } from 'lucide-vue-next';

const authStore = useAuthStore();
const isDropdownOpen = ref(false);
const showLogoutModal = ref(false);

const userInitials = computed(() => {
    const name = authStore.user?.name || 'Agent';
    return name.split(' ').map(n => n[0]).join('').toUpperCase().slice(0, 2);
});

const confirmLogout = async () => {
    showLogoutModal.ref = false;
    await authStore.logout();
};
</script>
