import { defineStore } from 'pinia';
import * as authApi from '../api/auth.api';

export const useAuthStore = defineStore('auth', {
    state: () => ({ user: null, loaded: false, loading: false }),
    actions: {
        async loadUser() {
            if (this.loaded || this.loading) return this.user;
            this.loading = true;
            try {
                this.user = await authApi.fetchAuthenticatedUser();
            } catch (error) {
                if (![401, 419].includes(error.response?.status)) throw error;
                this.user = null;
            } finally {
                this.loading = false;
                this.loaded = true;
            }
            return this.user;
        },
        async signIn(credentials) {
            this.user = await authApi.login(credentials);
            this.loaded = true;
            return this.user;
        },
        async signUp(userData) {
            this.user = await authApi.register(userData);
            this.loaded = true;
            return this.user;
        },
        async signOut() {
            await authApi.logout();
            this.user = null;
            this.loaded = true;
        },
    },
});
