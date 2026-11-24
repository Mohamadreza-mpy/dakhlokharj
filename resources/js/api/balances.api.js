import http from './http';

export async function getBalances(roomId, memberId = null) {
    const { data } = await http.get(`/api/v1/rooms/${roomId}/balances`, {
        params: memberId ? { member_id: memberId } : {},
    });
    return data.data;
}

export async function getMemberBalance(roomId, memberId) {
    const { data } = await http.get(`/api/v1/rooms/${roomId}/members/${memberId}/balance`);
    return data.data;
}

export async function getMemberLedger(roomId, memberId, params = {}) {
    const { data } = await http.get(`/api/v1/rooms/${roomId}/members/${memberId}/ledger`, { params });
    return data;
}
