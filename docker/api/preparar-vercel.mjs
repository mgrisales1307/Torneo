import { cpSync, existsSync, mkdirSync, mkdtempSync, readFileSync, readdirSync } from 'node:fs'
import { tmpdir } from 'node:os'
import { basename, join, relative, resolve } from 'node:path'
import { fileURLToPath } from 'node:url'

// Ejecutar con Node en el sistema desde el que se utilizará Vercel CLI.
const proyecto = fileURLToPath(new URL('../../', import.meta.url))
const backend = join(proyecto, 'backend')
const frontend = join(proyecto, 'frontend', 'dist')
if (!existsSync(join(frontend, 'index.html'))) {
  throw new Error('Primero compila el frontend: cd frontend && npm ci && npm run build')
}
const destino = process.argv[2]
  ? resolve(process.argv[2])
  : mkdtempSync(join(tmpdir(), 'nexzone-vercel-'))
if (existsSync(destino) && readdirSync(destino).length !== 0) {
  throw new Error('El directorio de destino debe estar vacío para evitar archivos de otro despliegue.')
}
mkdirSync(destino, { recursive: true })
const excluidos = new Set(['.git', '.vercel', '.claude', '.ai', '.phpunit.cache', 'vendor', 'node_modules', 'tests', 'database', 'storage'])
cpSync(backend, destino, {
  recursive: true,
  filter: (ruta) => {
    const partes = relative(backend, ruta).split(/[\\/]/)
    return !partes.some((parte) => excluidos.has(parte) || parte.startsWith('.env'))
      && !/\.(sqlite|sqlite3)(-|$)/.test(basename(ruta))
      && !(partes[0] === 'bootstrap' && partes[1] === 'cache' && ruta.endsWith('.php'))
      && relative(backend, ruta).replaceAll('\\', '/') !== 'public/hot'
  },
})
cpSync(frontend, join(destino, 'public'), { recursive: true })
const vinculacion = join(proyecto, '.vercel', 'project.json')
if (existsSync(vinculacion)) {
  JSON.parse(readFileSync(vinculacion, 'utf8'))
  mkdirSync(join(destino, '.vercel'), { recursive: true })
  cpSync(vinculacion, join(destino, '.vercel', 'project.json'))
}
console.log(`Preparado para Vercel: ${destino}`)
