import { defineStore } from 'pinia';
import { getRoom, getRooms } from '../api/rooms.api';

export const useRoomStore = defineStore('room', {
    state: () => ({ rooms: [], currentRoom: null, loadedRooms: false }),
    actions: {
        async loadRooms() {
            const page = await getRooms();
            this.rooms = Array.isArray(page) ? page : page.data ?? [];
            this.loadedRooms = true;
            return this.rooms;
        },
        async loadRoom(roomId) {
            this.currentRoom = await getRoom(roomId);
            return this.currentRoom;
        },
        clear() {
            this.rooms = [];
            this.currentRoom = null;
            this.loadedRooms = false;
        },
    },
});
