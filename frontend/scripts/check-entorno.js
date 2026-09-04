// Detiene la instalación cuando npm se está ejecutando con el Node de Windows
// sobre una carpeta que vive dentro de WSL.
//
// Es una combinación que no falla de golpe: instala los binarios nativos
// equivocados (Vite trae uno distinto por sistema operativo), tarda minutos en
// vez de segundos porque cada archivo viaja por el recurso de red
// \\wsl.localhost, y deja a Vite arrancando sin leer vite.config.js.

const enWindows = process.platform === 'win32'
const rutaUNC = /^\\\\wsl(\.localhost|\$)/i.test(process.cwd())

if (enWindows && rutaUNC) {
  console.error(`
  ┌──────────────────────────────────────────────────────────────┐
  │  Estás usando el Node de Windows sobre una carpeta de WSL.   │
  └──────────────────────────────────────────────────────────────┘

  Ruta actual: ${process.cwd()}

  Hay que usar el Node instalado DENTRO de Ubuntu. Para comprobarlo,
  desde la terminal de Ubuntu:

      which -a node npm

  Si alguna línea empieza por /mnt/c/, se está colando el de Windows.
  Instala Node dentro de Ubuntu y vuelve a intentarlo:

      curl -o- https://raw.githubusercontent.com/nvm-sh/nvm/v0.40.1/install.sh | bash
      exec bash
      nvm install 22

  Después borra lo que quedó instalado con los binarios equivocados:

      rm -rf node_modules
      npm install
`)
  process.exit(1)
}
