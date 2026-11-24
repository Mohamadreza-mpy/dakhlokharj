import http from './http';

export async function getConsumptions(roomId, purchaseId, params = {}) {
    const { data } = await http.get(`/api/v1/rooms/${roomId}/purchases/${purchaseId}/consumptions`, { params });
    return data.data ?? data;
}

export async function createConsumption(roomId, purchaseId, payload) {
    const { data } = await http.post(`/api/v1/rooms/${roomId}/purchases/${purchaseId}/consumptions`, payload);
    return data.data;
}

export async function reverseConsumption(roomId, entryId, payload) {
    const { data } = await http.post(`/api/v1/rooms/${roomId}/consumptions/${entryId}/reverse`, payload);
    return data.data;
}
