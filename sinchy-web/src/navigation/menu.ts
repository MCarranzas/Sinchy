import type { NombreIcono } from '@/components/AppIcon.vue'

export interface ItemMenu {
  titulo: string
  ruta: string
  icono: NombreIcono
  /**
   * Permiso(s) requeridos para ver el item y entrar a la ruta. Si es un
   * array basta con tener cualquiera de ellos (p. ej. `ver` o `ver_todas`
   * abren el listado). Si falta, el item es publico dentro de la sesion
   * (p. ej. el tablero de inicio).
   */
  permiso?: string | string[]
  /** Descripcion corta que se muestra en la vista provisional del modulo. */
  descripcion: string
}

/**
 * Menu principal de la aplicacion. Es la unica fuente de verdad: de aqui
 * salen tanto los enlaces del sidebar como las rutas provisionales de los
 * modulos que se construiran en las fases siguientes. Filtrar por `permiso`
 * es lo que hace que cada rol vea solo lo suyo.
 */
export const menuPrincipal: ItemMenu[] = [
  {
    titulo: 'Inicio',
    ruta: '/',
    icono: 'inicio',
    descripcion: 'Resumen general y accesos rápidos.',
  },
  {
    titulo: 'Liquidaciones',
    ruta: '/liquidaciones',
    icono: 'documento',
    permiso: ['liquidaciones.ver', 'liquidaciones.ver_todas'],
    descripcion: 'Listado, consulta y descarga de liquidaciones registradas.',
  },
  {
    titulo: 'Nueva liquidación',
    ruta: '/liquidaciones/nueva',
    icono: 'mas',
    permiso: 'liquidaciones.crear',
    descripcion: 'Captura de una nueva liquidación de venta de mineral.',
  },
  {
    titulo: 'Mi bandeja',
    ruta: '/bandeja',
    icono: 'bandeja',
    descripcion: 'Mis liquidaciones, las que esperan revisión y las listas para aprobar.',
  },
  {
    titulo: 'Reportes',
    ruta: '/reportes',
    icono: 'grafico',
    permiso: 'reportes.ver',
    descripcion: 'Resúmenes y exportaciones de liquidaciones.',
  },
  {
    titulo: 'Parámetros',
    ruta: '/parametros',
    icono: 'ajustes',
    permiso: 'parametros.gestionar',
    descripcion: 'Parámetros generales del sistema.',
  },
  {
    titulo: 'Catálogos',
    ruta: '/catalogos',
    icono: 'libro',
    permiso: 'catalogos.gestionar',
    descripcion: 'Departamentos, provincias, municipios, áreas mineras y presentaciones.',
  },
  {
    titulo: 'Usuarios',
    ruta: '/usuarios',
    icono: 'usuarios',
    permiso: 'usuarios.gestionar',
    descripcion: 'Altas, bajas y roles de usuarios.',
  },
]