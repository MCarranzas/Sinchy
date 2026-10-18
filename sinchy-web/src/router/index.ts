import { createRouter, createWebHistory, type RouteRecordRaw } from 'vue-router'

import { menuPrincipal } from '@/navigation/menu'
import { useAuthStore } from '@/stores/auth'

import AuthLayout from '@/layouts/AuthLayout.vue'
import MainLayout from '@/layouts/MainLayout.vue'
import CambioClaveView from '@/views/CambioClaveView.vue'
import DashboardView from '@/views/DashboardView.vue'
import EnConstruccionView from '@/views/EnConstruccionView.vue'
import LoginView from '@/views/LoginView.vue'
import NoAutorizadoView from '@/views/NoAutorizadoView.vue'
import NoEncontradoView from '@/views/NoEncontradoView.vue'
import CreateView from '@/views/Liquidaciones/CreateView.vue'
import EditView from '@/views/Liquidaciones/EditView.vue'
import IndexView from '@/views/Liquidaciones/IndexView.vue'
import ShowView from '@/views/Liquidaciones/ShowView.vue'
import BandejaView from '@/views/Liquidaciones/BandejaView.vue'
import ReportesView from '@/views/ReportesView.vue'

/** Rutas (name) que dejan de ser provisionales en esta fase. */
const MODULOS_CONSTRUIDOS = new Set(['liquidaciones', 'liquidaciones-nueva', 'bandeja', 'reportes'])

/**
 * Rutas de los modulos que todavia no se construyen. Se derivan del menu para
 * que cada entrada ya sea navegable y este protegida por su permiso desde este
 * mismo momento; sustituir `EnConstruccionView` por la vista real sera el
 * unico cambio en la fase correspondiente.
 */
const rutasModulos: RouteRecordRaw[] = menuPrincipal
  .filter((item) => item.ruta !== '/')
  .filter((item) => {
    const nombre = item.ruta.slice(1).replace(/\//g, '-')
    return !MODULOS_CONSTRUIDOS.has(nombre)
  })
  .map((item) => ({
    path: item.ruta,
    name: item.ruta.slice(1).replace(/\//g, '-'),
    component: EnConstruccionView,
    meta: {
      titulo: item.titulo,
      descripcion: item.descripcion,
      permiso: item.permiso,
    },
  }))

const rutas: RouteRecordRaw[] = [
  {
    path: '/login',
    component: AuthLayout,
    children: [
      {
        path: '',
        name: 'login',
        component: LoginView,
        meta: { publica: true },
      },
    ],
  },
  {
    path: '/cambio-clave',
    component: AuthLayout,
    children: [
      {
        path: '',
        name: 'cambio-clave',
        component: CambioClaveView,
        meta: { titulo: 'Cambio de contraseña' },
      },
    ],
  },
  {
    path: '/',
    component: MainLayout,
    children: [
      {
        path: '',
        name: 'inicio',
        component: DashboardView,
        meta: { titulo: 'Inicio' },
      },
      // ---------------------------------------------------- Liquidaciones
      {
        path: '/liquidaciones',
        name: 'liquidaciones',
        component: IndexView,
        meta: {
          titulo: 'Liquidaciones',
          permiso: ['liquidaciones.ver', 'liquidaciones.ver_todas'],
        },
      },
      {
        path: '/liquidaciones/nueva',
        name: 'liquidaciones-nueva',
        component: CreateView,
        meta: { titulo: 'Nueva liquidación', permiso: 'liquidaciones.crear' },
      },
      {
        path: '/liquidaciones/:id',
        name: 'liquidaciones-detalle',
        component: ShowView,
        meta: {
          titulo: 'Detalle de liquidación',
          permiso: ['liquidaciones.ver', 'liquidaciones.ver_todas'],
        },
      },
      {
        path: '/liquidaciones/:id/editar',
        name: 'liquidaciones-editar',
        component: EditView,
        meta: { titulo: 'Editar liquidación', permiso: 'liquidaciones.editar' },
      },
      // -------------------------------------------------------- Mi bandeja
      {
        path: '/bandeja',
        name: 'bandeja',
        component: BandejaView,
        meta: { titulo: 'Mi bandeja' },
      },
      // ----------------------------------------------------------- Reportes
      {
        path: '/reportes',
        name: 'reportes',
        component: ReportesView,
        meta: { titulo: 'Reportes', permiso: 'reportes.ver' },
      },
      // -------------------------------------------------------
      ...rutasModulos,
    ],
  },
  {
    path: '/403',
    name: 'no-autorizado',
    component: NoAutorizadoView,
    meta: { publica: true },
  },
  {
    path: '/:pathMatch(.*)*',
    name: 'no-encontrado',
    component: NoEncontradoView,
    meta: { publica: true },
  },
]

export const router = createRouter({
  history: createWebHistory(),
  routes: rutas,
  scrollBehavior: () => ({ top: 0 }),
})

/**
 * Control de acceso en tres pasos.
 *
 * 1. Sesion: se resuelve UNA sola vez (`cargarSesion` memoriza el resultado),
 *    recargando la app o navegando no se vuelve a preguntar al backend.
 * 2. Cambio de contrasena obligatorio: mientras el backend levante la bandera
 *    `debe_cambiar_password`, `/cambio-clave` es el UNICO destino permitido,
 *    sea cual sea la ruta pedida. Sin este bloqueo el usuario entraria con una
 *    clave temporal y la bandera nunca se resolveria.
 * 3. Permiso: la ruta declara `meta.permiso` y aqui se comprueba contra la
 *    lista que entrego el backend. Quien no lo tiene no llega a la vista.
 */
router.beforeEach(async (to) => {
  const auth = useAuthStore()

  await auth.cargarSesion()

  if (!auth.autenticado) {
    if (to.meta.publica === true) {
      return true
    }

    return { name: 'login', query: { redirect: to.fullPath } }
  }

  // Con sesion activa pero clave sin cambiar: no hay otra salida posible.
  if (auth.debeCambiarPassword) {
    return to.name === 'cambio-clave' ? true : { name: 'cambio-clave' }
  }

  // Terminado (o nunca exigido): la vista no debe quedar como un callejon.
  if (to.name === 'cambio-clave') {
    return { name: 'inicio' }
  }

  // Ya autenticado: no tiene sentido volver a mostrar el formulario de login.
  if (to.name === 'login') {
    return { name: 'inicio' }
  }

  const permiso = to.meta.permiso as string | string[] | undefined
  if (!auth.puedeAcceder(permiso)) {
    return { name: 'no-autorizado' }
  }

  return true
})