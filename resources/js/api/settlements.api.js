import http from './http';

export async function getSettlements(roomId, params = {}) {
    const { data } = await http.get(`/api/v1/rooms/${roomId}/settlements`, { params });
    return data;
}

export async function createSettlement(roomId, payload) {
    const { data } = await http.post(`/api/v1/rooms/${roomId}/settlements`, payload);
    return data.data;
}

export async function reverseSettlement(roomId, settlementId, payload) {
    const { data } = await http.post(`/api/v1/rooms/${roomId}/settlements/${settlementId}/reverse`, payload);
    return data.data;
}
