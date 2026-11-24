import http from './http';

export async function fetchAuthenticatedUser() {
    const { data } = await http.get('/api/user');
    return data.data.user;
}

export async function login(credentials) {
    await http.get('/sanctum/csrf-cookie');
    const { data } = await http.post('/login', credentials);
    return data.data.user;
}

export async function register(userData) {
    await http.get('/sanctum/csrf-cookie');
    const { data } = await http.post('/register', userData);
    return data.data.user;
}

export async function logout() {
    await http.post('/logout');
}
