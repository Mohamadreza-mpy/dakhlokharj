import http from './http';

export async function getRooms() {
    const { data } = await http.get('/api/v1/rooms');
    return data.data ?? data;
}

export async function createRoom(payload) {
    const { data } = await http.post('/api/v1/rooms', payload);
    return data.data;
}

export async function getRoom(roomId) {
    const { data } = await http.get(`/api/v1/rooms/${roomId}`);
    return data.data;
}

export async function getMembers(roomId, active = true) {
    const params = { per_page: 100 };
    if (active !== null) params.active = active;
    const { data } = await http.get(`/api/v1/rooms/${roomId}/members`, { params });
    return data.data ?? data;
}

export async function createMember(roomId, payload) {
    const { data } = await http.post(`/api/v1/rooms/${roomId}/members`, payload);
    return data.data;
}

export async function updateMember(roomId, memberId, payload) {
    const { data } = await http.patch(`/api/v1/rooms/${roomId}/members/${memberId}`, payload);
    return data.data;
}

export async function getDashboard(roomId) {
    const { data } = await http.get(`/api/v1/rooms/${roomId}/dashboard`);
    return data.data;
}
