import { createRouter, createWebHistory } from 'vue-router';
import { useAuthStore } from '../stores/auth';
import LoginPage from '../pages/LoginPage.vue';
import RoomsPage from '../pages/RoomsPage.vue';
import AppShell from '../components/layout/AppShell.vue';
import DashboardPage from '../pages/DashboardPage.vue';
import MembersPage from '../pages/MembersPage.vue';
import PurchasesPage from '../pages/PurchasesPage.vue';
import PurchaseCreatePage from '../pages/PurchaseCreatePage.vue';
import PurchaseDetailsPage from '../pages/PurchaseDetailsPage.vue';
import ConsumptionCreatePage from '../pages/ConsumptionCreatePage.vue';
import BalancesPage from '../pages/BalancesPage.vue';
import MemberBalancePage from '../pages/MemberBalancePage.vue';
import SettlementsPage from '../pages/SettlementsPage.vue';

const router = createRouter({
    history: createWebHistory(),
    scrollBehavior: () => ({ top: 0 }),
    routes: [
        { path: '/login', name: 'login', component: LoginPage, meta: { guestOnly: true } },
        { path: '/register', name: 'register', component: LoginPage, props: { registerMode: true }, meta: { guestOnly: true } },
        { path: '/rooms', name: 'rooms', component: RoomsPage, meta: { requiresAuth: true } },
        {
            path: '/rooms/:roomId',
            component: AppShell,
            meta: { requiresAuth: true },
            children: [
                { path: '', redirect: { name: 'dashboard' } },
                { path: 'dashboard', name: 'dashboard', component: DashboardPage },
                { path: 'members', name: 'members', component: MembersPage },
                { path: 'purchases', name: 'purchases', component: PurchasesPage },
                { path: 'purchases/new', name: 'purchase-create', component: PurchaseCreatePage },
                { path: 'purchases/:purchaseId', name: 'purchase-details', component: PurchaseDetailsPage },
                { path: 'purchases/:purchaseId/consume', name: 'consumption-create', component: ConsumptionCreatePage },
                { path: 'balances', name: 'balances', component: BalancesPage },
                { path: 'members/:memberId/balance', name: 'member-balance', component: MemberBalancePage },
                { path: 'settlements', name: 'settlements', component: SettlementsPage },
            ],
        },
        { path: '/:pathMatch(.*)*', redirect: { name: 'rooms' } },
    ],
});

router.beforeEach(async (to) => {
    const auth = useAuthStore();
    if (!auth.loaded) await auth.loadUser();
    if (to.meta.requiresAuth && !auth.user) return { name: 'login', query: { redirect: to.fullPath } };
    if (to.meta.guestOnly && auth.user) return { name: 'rooms' };
});

export default router;
