<template>
    <div class="min-h-screen flex items-center justify-center bg-gray-100 px-4">
        <form @submit.prevent="submit" class="bg-white p-8 rounded shadow-md w-full max-w-md">
            <h2 class="text-2xl font-semibold mb-6 text-center text-gray-800">Portal Login</h2>

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

            <p v-if="error" class="text-red-500 mt-4 text-sm text-center font-medium bg-red-50 p-2 rounded">
                {{ error }}
            </p>
        </form>
    </div>
</template>

<script setup>
import { ref } from 'vue'
import { useAuthStore } from '../../store/authStore' // Ensure this path is correct
import { useRouter } from 'vue-router'

const identifier = ref('')
const password = ref('')
const error = ref('')
const loading = ref(false)

const router = useRouter()
const store = useAuthStore()

const submit = async () => {
    try {
        error.value = ''
        loading.value = true

        // 1. Store login calls ensureCsrfCookie and api.post('/portal/login')
        await store.login(identifier.value, password.value)

        router.push('/transfers')

    } catch (e) {
        // Handle Laravel validation errors (422) or generic errors
        console.error("Full Error Object:", e); // Check your console for this!
        if (e.response) {
            error.value = e.response.data.message || 'Invalid credentials.';
        } else if (e.request) {
            error.value = 'The server did not respond. Check your internet or CORS settings.';
        } else {
            error.value = 'Request setup error: ' + e.message;
        }
    } finally {
        loading.value = false
    }
}
</script>
