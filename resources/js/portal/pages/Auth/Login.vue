<template>
    <div class="min-h-screen flex items-center justify-center bg-gray-100 px-4">
        <form @submit.prevent="submit" class="bg-white p-8 rounded shadow-md w-full max-w-md">
            <h2 class="text-2xl font-semibold mb-6 text-center text-gray-800">Portal Login</h2>

            <div v-if="!store.requiresTwoFactor && !store.requiresSetup">
                <div class="mb-4">
                    <input v-model="identifier"
                           type="text"
                           placeholder="Email or Phone"
                           :disabled="loading"
                           class="border w-full p-3 rounded focus:ring-2 focus:ring-blue-500 outline-none"
                           required />
                </div>

                <div class="mb-6">
                    <input v-model="password"
                           type="password"
                           placeholder="Password"
                           :disabled="loading"
                           class="border w-full p-3 rounded focus:ring-2 focus:ring-blue-500 outline-none"
                           required />
                </div>

                <button type="submit"
                        :disabled="loading"
                        class="w-full bg-blue-600 text-white p-3 rounded font-medium hover:bg-blue-700 transition disabled:bg-blue-300">
                    <span v-if="loading">Authenticating...</span>
                    <span v-else>Log In</span>
                </button>
            </div>

            <div v-if="store.requiresTwoFactor">
                <h3 class="text-lg font-medium mb-4 text-center text-gray-700">Two-Factor Authentication</h3>
                <p class="text-sm text-gray-600 mb-4 text-center">Enter the 6-digit code from your authenticator app.</p>

                <div class="mb-4">
                    <input v-model="code"
                           type="text"
                           inputmode="numeric"
                           maxlength="6"
                           placeholder="000000"
                           :disabled="loading"
                           class="border w-full p-3 rounded text-center text-lg tracking-widest focus:ring-2 focus:ring-blue-500 outline-none"
                           required />
                </div>

                <button @click="submitCode"
                        :disabled="loading"
                        class="w-full bg-blue-600 text-white p-3 rounded font-medium hover:bg-blue-700 transition disabled:bg-blue-300">
                    <span v-if="loading">Verifying...</span>
                    <span v-else>Verify</span>
                </button>

                <p class="mt-4 text-center">
                    <button @click="showRecovery = true"
                            class="text-sm text-blue-600 hover:underline">
                        Use a recovery code instead
                    </button>
                </p>
            </div>

            <div v-if="store.requiresTwoFactor && showRecovery">
                <h3 class="text-lg font-medium mb-4 text-center text-gray-700">Recovery Code</h3>

                <div class="mb-4">
                    <input v-model="recoveryCode"
                           type="text"
                           placeholder="Enter recovery code"
                           :disabled="loading"
                           class="border w-full p-3 rounded text-center focus:ring-2 focus:ring-blue-500 outline-none"
                           required />
                </div>

                <button @click="submitRecoveryCode"
                        :disabled="loading"
                        class="w-full bg-blue-600 text-white p-3 rounded font-medium hover:bg-blue-700 transition disabled:bg-blue-300">
                    <span v-if="loading">Verifying...</span>
                    <span v-else>Verify Recovery Code</span>
                </button>
            </div>

            <p v-if="error" class="text-red-500 mt-4 text-sm text-center font-medium bg-red-50 p-2 rounded">
                {{ error }}
            </p>
        </form>
    </div>
</template>

<script setup>
import { ref } from 'vue'
import { useAuthStore } from '../../store/authStore'
import { useRouter } from 'vue-router'

const identifier = ref('')
const password = ref('')
const code = ref('')
const recoveryCode = ref('')
const showRecovery = ref(false)
const error = ref('')
const loading = ref(false)

const router = useRouter()
const store = useAuthStore()

const submit = async () => {
    try {
        error.value = ''
        loading.value = true

        const data = await store.login(identifier.value, password.value)

        if (data.requires_2fa) {
            return
        }

        if (data.requires_2fa_setup) {
            router.push('/2fa/setup')
            return
        }

        router.push('/transfers')

    } catch (e) {
        if (e.response) {
            const msg = e.response.data?.message
            if (msg) {
                error.value = msg
            } else {
                const firstError = Object.values(e.response.data?.errors || {}).flat()
                error.value = firstError?.[0] || 'Invalid credentials.'
            }
        } else if (e.request) {
            error.value = 'The server did not respond. Check your internet or CORS settings.'
        } else {
            error.value = 'Request setup error: ' + e.message
        }
    } finally {
        loading.value = false
    }
}

const submitCode = async () => {
    try {
        error.value = ''
        loading.value = true
        const data = await store.verifyTwoFactor(code.value)
        router.push('/transfers')
    } catch (e) {
        error.value = e.response?.data?.message || 'Invalid code. Please try again.'
    } finally {
        loading.value = false
    }
}

const submitRecoveryCode = async () => {
    try {
        error.value = ''
        loading.value = true
        const data = await store.verifyRecoveryCode(recoveryCode.value)
        router.push('/transfers')
    } catch (e) {
        error.value = e.response?.data?.message || 'Invalid recovery code.'
    } finally {
        loading.value = false
    }
}
</script>
