<template>
    <div class="min-h-screen flex items-center justify-center bg-gray-100 px-4">
        <div class="bg-white p-8 rounded shadow-md w-full max-w-md">
            <h2 v-if="allSet" class="text-2xl font-semibold mb-4 text-center text-green-600">All Set!</h2>
            <h2 v-else class="text-2xl font-semibold mb-6 text-center text-gray-800">Set Up Two-Factor Authentication</h2>

            <div v-if="loading" class="text-center py-8">
                <p class="text-gray-600">Loading...</p>
            </div>

            <div v-else-if="allSet">
                <p class="text-center text-gray-600 mb-6">
                    Two-factor authentication has been successfully configured for your account.
                </p>
                <button @click="goToDashboard"
                        class="w-full bg-blue-600 text-white p-3 rounded font-medium hover:bg-blue-700 transition">
                    Go to Dashboard
                </button>
            </div>

            <div v-else>
                <p class="text-sm text-gray-600 mb-4 text-center">
                    Scan this QR code with your authenticator app (Google Authenticator, Authy, etc.):
                </p>

                <div v-if="qrCodeInline" class="flex justify-center mb-4">
                    <img :src="qrCodeInline" alt="QR Code" class="w-48 h-48" />
                </div>

                <div v-if="secret" class="bg-gray-50 p-3 rounded mb-4 text-center">
                    <p class="text-xs text-gray-500 mb-1">Or enter this key manually:</p>
                    <code class="text-sm font-mono bg-gray-200 px-2 py-1 rounded break-all">{{ secret }}</code>
                </div>

                <div v-if="recoveryCodes.length" class="mb-4">
                    <p class="text-sm font-medium text-gray-700 mb-2">Recovery Codes (save these somewhere safe):</p>
                    <div class="bg-yellow-50 border border-yellow-200 p-3 rounded">
                        <code v-for="(code, i) in recoveryCodes" :key="i"
                              class="block text-sm font-mono mb-1">{{ code }}</code>
                    </div>
                </div>

                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Enter the 6-digit code from your app:</label>
                    <input v-model="code"
                           type="text"
                           inputmode="numeric"
                           maxlength="6"
                           placeholder="000000"
                           :disabled="loadingConfirm"
                           class="border w-full p-3 rounded text-center text-lg tracking-widest focus:ring-2 focus:ring-blue-500 outline-none"
                           required />
                </div>

                <button @click="confirm"
                        :disabled="loadingConfirm"
                        class="w-full bg-blue-600 text-white p-3 rounded font-medium hover:bg-blue-700 transition disabled:bg-blue-300">
                    <span v-if="loadingConfirm">Verifying...</span>
                    <span v-else>Confirm & Enable</span>
                </button>

                <p v-if="error" class="text-red-500 mt-4 text-sm text-center font-medium bg-red-50 p-2 rounded">
                    {{ error }}
                </p>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useAuthStore } from '../../store/authStore'
import { useRouter } from 'vue-router'

const store = useAuthStore()
const router = useRouter()

const loading = ref(true)
const loadingConfirm = ref(false)
const error = ref('')
const secret = ref('')
const qrCodeInline = ref('')
const recoveryCodes = ref([])
const code = ref('')
const allSet = ref(false)

onMounted(async () => {
    if (!store.tempToken) {
        router.push('/login')
        return
    }

    try {
        const data = await store.initSetup()

        if (data.user) {
            allSet.value = true
            return
        }

        secret.value = data.secret
        if (data.qr_code_svg) {
            qrCodeInline.value = data.qr_code_inline
        }
        recoveryCodes.value = data.recovery_codes || []
    } catch (e) {
        error.value = e.response?.data?.message || 'Failed to initialize 2FA setup.'
    } finally {
        loading.value = false
    }
})

const confirm = async () => {
    try {
        error.value = ''
        loadingConfirm.value = true
        await store.confirmSetup(code.value)
        allSet.value = true
    } catch (e) {
        error.value = e.response?.data?.message || 'Invalid code. Please try again.'
    } finally {
        loadingConfirm.value = false
    }
}

const goToDashboard = () => {
    router.push('/transfers')
}
</script>
