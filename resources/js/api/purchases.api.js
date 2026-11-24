import http from './http';

export async function getPurchases(roomId, params = {}) {
    const { data } = await http.get(`/api/v1/rooms/${roomId}/purchases`, { params });
    return data;
}

export async function getPurchase(roomId, purchaseId) {
    const { data } = await http.get(`/api/v1/rooms/${roomId}/purchases/${purchaseId}`);
    return data.data;
}

export async function createPurchase(roomId, payload) {
    const { data } = await http.post(`/api/v1/rooms/${roomId}/purchases`, payload);
    return data.data;
}

export async function voidPurchase(roomId, purchaseId, payload) {
    const { data } = await http.post(`/api/v1/rooms/${roomId}/purchases/${purchaseId}/void`, payload);
    return data.data;
}
