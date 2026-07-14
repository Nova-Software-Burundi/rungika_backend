import { defineStore } from 'pinia';
import { api, ensureCsrfCookie } from '../../plugins/axios';

export const useAuthStore = defineStore('auth', {
    state: () => ({
        user: null,
        isInitialized: false,
        requiresTwoFactor: false,
        requiresSetup: false,
        tempToken: null,
    }),
    actions: {
        async login(identifier, password) {
            try {
                await ensureCsrfCookie();
                const { data } = await api.post('portal/login', { identifier, password });

                if (data.requires_2fa) {
                    this.requiresTwoFactor = true;
                    this.tempToken = data.temp_token;
                    this.isInitialized = true;
                    return data;
                }

                if (data.requires_2fa_setup) {
                    this.requiresSetup = true;
                    this.tempToken = data.temp_token;
                    this.isInitialized = true;
                    return data;
                }

                this.user = data.user;
                this.isInitialized = true;
                return data;
            } catch (error) {
                this.isInitialized = true;
                throw error;
            }
        },

        async verifyTwoFactor(code) {
            const { data } = await api.post('portal/2fa/verify', {
                temp_token: this.tempToken,
                code,
            });
            this.user = data.user;
            this.requiresTwoFactor = false;
            this.tempToken = null;
            return data;
        },

        async verifyRecoveryCode(code) {
            const { data } = await api.post('portal/2fa/recovery', {
                temp_token: this.tempToken,
                code,
            });
            this.user = data.user;
            this.requiresTwoFactor = false;
            this.tempToken = null;
            return data;
        },

        async initSetup() {
            const { data } = await api.post('portal/2fa/setup-init', {
                temp_token: this.tempToken,
            });

            if (data.user) {
                this.user = data.user;
                this.requiresSetup = false;
                this.tempToken = null;
            }

            return data;
        },

        async confirmSetup(code) {
            const { data } = await api.post('portal/2fa/setup-confirm', {
                temp_token: this.tempToken,
                code,
            });
            this.user = data.user;
            this.requiresSetup = false;
            this.tempToken = null;
            return data;
        },

        async checkAuth() {
            try {
                const { data } = await api.get('user');
                this.user = data;
            } catch (e) {
                this.user = null;
            } finally {
                this.isInitialized = true;
            }
        },

        async logout() {
            await api.post('logout');
            this.user = null;
            this.requiresTwoFactor = false;
            this.requiresSetup = false;
            this.tempToken = null;
            window.location.href = '/portal/login';
        }
    }
});
