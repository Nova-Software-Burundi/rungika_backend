<template>
    <div class="min-h-screen flex items-center justify-center bg-gray-100 px-4">
        <div class="bg-white p-8 rounded shadow-md w-full max-w-lg">
            <template v-if="!initialized">
                <h2 class="text-2xl font-semibold mb-6 text-center text-gray-800">Setting up...</h2>
            </template>

            <template v-else-if="setupComplete">
                <div class="text-center py-8">
                    <svg class="w-16 h-16 text-emerald-500 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    <h2 class="text-2xl font-semibold text-gray-800 mb-2">All Set!</h2>
                    <p class="text-gray-500 mb-6">Two-factor authentication is now active.</p>
                    <button @click="goHome"
                            class="bg-blue-600 text-white px-8 py-3 rounded font-medium hover:bg-blue-700 transition">
                        Go to Dashboard
                    </button>
                </div>
            </template>

            <template v-else>
                <h2 class="text-2xl font-semibold mb-1 text-center text-gray-800">Secure Your Account</h2>
                <p class="text-sm text-gray-500 text-center mb-6">Your security is important. Set up two-factor authentication to continue.</p>

                <div v-if="error" class="mb-4 text-red-600 text-sm text-center bg-red-50 p-2 rounded font-medium">{{ error }}</div>

                <div class="space-y-6">
                    <div class="bg-slate-50 rounded-xl p-5 border border-slate-200">
                        <p class="text-xs font-bold text-slate-500 uppercase tracking-widest mb-4">Step 1: Scan QR Code</p>
                        <div class="flex items-start gap-6">
                            <div v-if="qrCode" class="bg-white p-2 rounded-lg shadow-sm border border-slate-200 shrink-0" v-html="qrCode"></div>
                            <div class="min-w-0">
                                <p class="text-xs font-bold text-slate-600 mb-2">Or enter this key manually:</p>
                                <code class="block bg-slate-800 text-emerald-300 text-xs font-mono p-2 rounded-lg select-all break-all">{{ secret }}</code>
                                <p class="text-[10px] text-amber-600 font-bold mt-3">
                                    Save your recovery codes below before continuing.
                                </p>
                            </div>
                        </div>
                    </div>

                    <div v-if="recoveryCodes.length" class="bg-amber-50 rounded-xl p-5 border border-amber-200">
                        <p class="text-xs font-bold text-amber-700 uppercase tracking-widest mb-3">Recovery Codes (save these!)</p>
                        <div class="grid grid-cols-2 gap-2">
                            <code v-for="(rc, i) in recoveryCodes" :key="i"
                                  class="bg-white text-amber-900 text-xs font-mono p-2 rounded-lg border border-amber-100 select-all text-center">
                                {{ rc }}
                            </code>
                        </div>
                    </div>

                    <div class="bg-slate-50 rounded-xl p-5 border border-slate-200">
                        <p class="text-xs font-bold text-slate-500 uppercase tracking-widest mb-4">Step 2: Confirm Setup</p>
                        <p class="text-sm font-bold text-slate-600 mb-3">Enter the 6-digit code from your authenticator app</p>
                        <div class="flex gap-3 items-start">
                            <input v-model="confirmCode"
                                   type="text"
                                   inputmode="numeric"
                                   maxlength="6"
                                   placeholder="000 000"
                                   :disabled="loadingConfirm"
                                   class="border-slate-200 px-4 py-3 rounded-lg text-sm font-bold focus:ring-2 focus:ring-blue-500 outline-none bg-white w-44 transition-all text-center text-xl tracking-[0.3em] font-mono" />
                            <button @click="submitConfirm"
                                    :disabled="loadingConfirm || confirmCode.length < 6"
                                    class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-3 rounded-lg text-xs font-bold transition active:scale-95 disabled:opacity-40">
                                <span v-if="loadingConfirm">Verifying...</span>
                                <span v-else>Confirm</span>
                            </button>
                        </div>
                    </div>
                </div>
            </template>
        </div>
    </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '../../store/authStore'

const router = useRouter()
const store = useAuthStore()

const initialized = ref(false)
const qrCode = ref('')
const secret = ref('')
const recoveryCodes = ref([])
const confirmCode = ref('')
const loadingConfirm = ref(false)
const setupComplete = ref(false)
const error = ref('')

const goHome = () => {
    router.push('/transfers')
}

onMounted(async () => {
    if (!store.tempToken) {
        router.push('/login')
        return
    }

    try {
        const data = await store.initSetup()

        if (data.user) {
            setupComplete.value = true
            initialized.value = true
            return
        }

        qrCode.value = data.qr_code
        secret.value = data.secret
        recoveryCodes.value = data.recovery_codes || []
        initialized.value = true
    } catch (e) {
        error.value = e.response?.data?.message || 'Session expired. Please login again.'
        setTimeout(() => router.push('/login'), 2000)
    }
})

const submitConfirm = async () => {
    loadingConfirm.value = true
    error.value = ''
    try {
        await store.confirmSetup(confirmCode.value)
        setupComplete.value = true
    } catch (e) {
        error.value = e.response?.data?.message || 'Invalid code. Try again.'
    } finally {
        loadingConfirm.value = false
    }
}
</script>
