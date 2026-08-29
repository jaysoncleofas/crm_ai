import { createRouter, createWebHistory } from 'vue-router'
import { useAuth } from '@/composables/useAuth'

const routes = [
  {
    path: '/login',
    name: 'login',
    component: () => import('@/pages/LoginPage.vue'),
    meta: { guestOnly: true, title: 'Sign in' },
  },
  {
    path: '/register',
    name: 'register',
    component: () => import('@/pages/RegisterPage.vue'),
    meta: { guestOnly: true, title: 'Create account' },
  },
  {
    path: '/',
    component: () => import('@/components/AppLayout.vue'),
    meta: { auth: true },
    children: [
      { path: '', name: 'dashboard', component: () => import('@/pages/DashboardPage.vue'), meta: { title: 'Dashboard' } },
      { path: 'contacts', name: 'contacts', component: () => import('@/pages/contacts/ContactsIndex.vue'), meta: { title: 'Contacts', permission: 'contacts.view' } },
      { path: 'contacts/:id', name: 'contact', component: () => import('@/pages/contacts/ContactShow.vue'), meta: { title: 'Contact', permission: 'contacts.view' }, props: true },
      { path: 'companies', name: 'companies', component: () => import('@/pages/companies/CompaniesIndex.vue'), meta: { title: 'Companies', permission: 'companies.view' } },
      { path: 'companies/:id', name: 'company', component: () => import('@/pages/companies/CompanyShow.vue'), meta: { title: 'Company', permission: 'companies.view' }, props: true },
      { path: 'deals', name: 'deals', component: () => import('@/pages/deals/DealsIndex.vue'), meta: { title: 'Deals', permission: 'deals.view' } },
      { path: 'deals/:id', name: 'deal', component: () => import('@/pages/deals/DealShow.vue'), meta: { title: 'Deal', permission: 'deals.view' }, props: true },
      { path: 'pipeline', name: 'pipeline', component: () => import('@/pages/deals/PipelineBoard.vue'), meta: { title: 'Pipeline', permission: 'deals.view' } },
      { path: 'activities', name: 'activities', component: () => import('@/pages/activities/ActivitiesIndex.vue'), meta: { title: 'Activities', permission: 'activities.view' } },
      { path: 'users', name: 'users', component: () => import('@/pages/admin/UsersIndex.vue'), meta: { title: 'Team', permission: 'users.view' } },
      { path: 'audit-log', name: 'audit-log', component: () => import('@/pages/admin/AuditLogPage.vue'), meta: { title: 'Audit log', permission: 'audit-log.view' } },
    ],
  },
  { path: '/:pathMatch(.*)*', name: 'not-found', component: () => import('@/pages/NotFoundPage.vue'), meta: { title: 'Not found' } },
]

const router = createRouter({
  history: createWebHistory(),
  routes,
  scrollBehavior: (to, from, saved) => saved ?? { top: 0 },
})

router.beforeEach(async (to) => {
  const { ready, isAuthenticated, fetchUser, can } = useAuth()

  // Resolve the session once per page load before the first guarded navigation.
  if (!ready.value) {
    await fetchUser()
  }

  if (to.meta.auth && !isAuthenticated.value) {
    return { name: 'login', query: to.fullPath === '/' ? {} : { redirect: to.fullPath } }
  }

  if (to.meta.guestOnly && isAuthenticated.value) {
    return { name: 'dashboard' }
  }

  if (to.meta.permission && !can(to.meta.permission)) {
    return { name: 'dashboard' }
  }

  return true
})

router.afterEach((to) => {
  const base = 'Jayson CRM'
  document.title = to.meta.title ? `${to.meta.title} · ${base}` : base
})

export default router
