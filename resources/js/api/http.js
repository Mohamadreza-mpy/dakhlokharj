import axios from 'axios';

const http = axios.create({
    baseURL: '/',
    headers: { Accept: 'application/json' },
    withCredentials: true,
    withXSRFToken: true,
});

http.interceptors.response.use(
    (response) => response,
    (error) => {
        if (error.response?.status === 419) {
            error.userMessage = 'نشست شما پایان یافته است. دوباره وارد شوید.';
        } else if (error.response?.status === 409) {
            error.userMessage = error.response.data?.message || 'اطلاعات تغییر کرده است؛ صفحه را تازه‌سازی کنید.';
        } else if (error.response?.status === 401) {
            error.userMessage = 'برای ادامه دوباره وارد شوید.';
        }
        return Promise.reject(error);
    },
);

export function apiError(error, fallback = 'در ارتباط با سرور مشکلی پیش آمد.') {
    const validationErrors = error.response?.data?.errors;
    const firstValidationError = validationErrors ? Object.values(validationErrors).flat()[0] : null;
    return error.userMessage || firstValidationError || error.response?.data?.message || fallback;
}

export default http;
