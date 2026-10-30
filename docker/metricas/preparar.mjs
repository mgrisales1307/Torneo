import { randomBytes } from 'node:crypto'
import { writeFileSync, existsSync } from 'node:fs'
import { fileURLToPath } from 'node:url'

const destino = fileURLToPath(new URL('../../.env.metricas', import.meta.url))
if (existsSync(destino)) {
  console.log('.env.metricas ya existe; se conservan las claves y los datos.')
} else {
  const secreto = () => randomBytes(32).toString('hex')
  writeFileSync(destino, [
    `METRICAS_POSTGRES_PASSWORD=${secreto()}`,
    `METRICAS_ESCRITOR_PASSWORD=${secreto()}`,
    `METRICAS_LECTOR_PASSWORD=${secreto()}`,
    `METABASE_SECRET_KEY=${secreto()}`,
    `METABASE_ENCRYPTION_SECRET_KEY=${secreto()}`,
    'METABASE_ADMIN_EMAIL=admin@nexzone.local',
    `METABASE_ADMIN_PASSWORD=Nz!${secreto()}`,
    'METABASE_PORT=3001',
    '',
  ].join('\n'), { mode: 0o600, flag: 'wx' })
  console.log('Creado .env.metricas con claves aleatorias. Las credenciales solo están en ese archivo privado.')
}
