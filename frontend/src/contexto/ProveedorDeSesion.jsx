import { useMemo } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import {
  esNoAutenticado,
  ingresar as ingresarEnLaApi,
  obtenerPerfil,
  registrarse as registrarseEnLaApi,
  salir as salirDeLaApi,
} from '../lib/api'
import { ContextoDeSesion } from './contextoDeSesion'

const ROLES_DE_STAFF = ['admin', 'empleado']

export function ProveedorDeSesion({ children }) {
  const cliente = useQueryClient()

  const consulta = useQuery({
    queryKey: ['sesion'],
    // No estar autenticado no es un error: es simplemente una visita anónima.
    queryFn: async () => {
      try {
        return await obtenerPerfil()
      } catch (error) {
        if (esNoAutenticado(error)) return null

        throw error
      }
    },
    retry: false,
    staleTime: 5 * 60 * 1000,
  })

  const ingresar = useMutation({
    mutationFn: ingresarEnLaApi,
    onSuccess: (usuario) => cliente.setQueryData(['sesion'], usuario),
  })

  const registrarse = useMutation({
    mutationFn: registrarseEnLaApi,
    onSuccess: (usuario) => cliente.setQueryData(['sesion'], usuario),
  })

  const salir = useMutation({
    mutationFn: salirDeLaApi,
    // Al cerrar sesión se vacía toda la caché: los datos del panel anterior no
    // pueden quedar visibles para quien entre después en el mismo navegador.
    onSettled: () => {
      cliente.setQueryData(['sesion'], null)
      cliente.clear()
    },
  })

  const valor = useMemo(() => {
    const usuario = consulta.data ?? null
    const rol = usuario?.rol?.valor ?? null

    return {
      usuario,
      rol,
      cargandoSesion: consulta.isPending,
      hayError: consulta.isError,
      estaAutenticado: usuario !== null,
      esStaff: ROLES_DE_STAFF.includes(rol),
      esAdministrador: rol === 'admin',
      ingresar,
      registrarse,
      salir,
    }
  }, [consulta.data, consulta.isPending, consulta.isError, ingresar, registrarse, salir])

  return <ContextoDeSesion.Provider value={valor}>{children}</ContextoDeSesion.Provider>
}
