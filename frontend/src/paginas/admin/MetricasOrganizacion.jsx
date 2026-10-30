import { lazy, Suspense } from 'react'
import { useQuery } from '@tanstack/react-query'
import { RefreshCw, ShieldCheck } from 'lucide-react'
import { mensajeDeError, obtenerMetricasAdmin } from '../../lib/api'
import { Aviso, Boton, Cargando } from '../../componentes/ui/Basicos'
import { NavegacionDelPanel } from '../panel/NavegacionDelPanel'
import { ENLACES_DE_ORGANIZACION } from '../panel/enlacesDelPanel'

const PanelDeMetabase = lazy(() => import('../../componentes/diseno/PanelDeMetabase').then((modulo) => ({ default: modulo.PanelDeMetabase })))

export default function MetricasOrganizacion() {
  const panel = useQuery({ queryKey: ['metricas-admin'], queryFn: obtenerMetricasAdmin, refetchInterval: 5 * 60 * 1000, staleTime: 60000, retry: false })
  const ultima = panel.data?.actualizado_en
  const atrasadas = ultima && panel.dataUpdatedAt - new Date(ultima).getTime() > 15 * 60 * 1000

  return (
    <div className="pila pila--chica metricas-pagina">
      <header className="encabezado-pagina">
        <span className="rotulo">Inteligencia de la plataforma</span>
        <h1 className="titulo-pagina">Métricas</h1>
        <p className="entradilla">Conoce cómo se usa NexZone y cómo crece la competición.</p>
      </header>
      <NavegacionDelPanel enlaces={ENLACES_DE_ORGANIZACION} />
      <div className="metricas-contexto">
        <div><ShieldCheck size={18} aria-hidden="true" /><span>Datos agregados · Solo organización</span></div>
        <Boton variante="fantasma" iconoIzquierda={<RefreshCw size={16} />} cargando={panel.isFetching} onClick={() => panel.refetch()}>Actualizar panel</Boton>
      </div>
      <p className="apagado">Tráfico y actividad: últimos 30 días. Totales: estado actual. Zona horaria: Bogotá. Actualización cada 5 minutos. Los pagos son virtuales.</p>
      {ultima && <p className="apagado">Última sincronización: {new Intl.DateTimeFormat('es-CO', { dateStyle: 'medium', timeStyle: 'short', timeZone: 'America/Bogota' }).format(new Date(ultima))}</p>}
      {atrasadas && <Aviso tono="atencion">Los datos llevan más de 15 minutos sin actualizarse. Comprueba el planificador de métricas.</Aviso>}
      {panel.isPending ? <Cargando /> : panel.isError ? <Aviso tono="error">{mensajeDeError(panel.error)}</Aviso> : !panel.data.configurado ? (
        <Aviso>El panel todavía no está configurado. Sigue la guía de métricas del proyecto para iniciar Metabase.</Aviso>
      ) : (
        <Suspense fallback={<Cargando />}><PanelDeMetabase url={panel.data.url} jwt={panel.data.jwt} /></Suspense>
      )}
      <details className="metricas-lectura">
        <summary>Cómo leer estas métricas</summary>
        <p>Las visitas solo incluyen navegadores que aceptaron la medición. Una sesión termina tras 30 minutos de inactividad o al cerrar la pestaña. El acumulado diario no representa personas únicas del mes.</p>
        <p>El rendimiento muestra el percentil 75 diario y el número de muestras por carga de documento, sin inventar ceros cuando falta información. Valores de referencia buenos: LCP ≤ 2.500 ms, INP ≤ 200 ms y CLS ≤ 0,1.</p>
        <p>Las inscripciones por estado son un inventario actual, no un embudo de conversión. Los pagos muestran órdenes que siguen pagadas; las devoluciones las excluyen. Ningún indicador representa dinero real.</p>
      </details>
    </div>
  )
}
