// Captura una página del banco de pruebas, entera, en móvil y/o escritorio.
//
//   node pruebas-navegador/captura.mjs /admin/seccions/1/edit --admin --movil
//   node pruebas-navegador/captura.mjs /c/cd-pesca-piloto --ambos
//   node pruebas-navegador/captura.mjs /admin/seccions/create --admin --escritorio --clic "text=Suma los puestos"
//
// Opciones: --admin | --socio (login) · --movil | --escritorio | --ambos (por defecto móvil)
//           --clic "selector"  pulsa algo antes de capturar (se puede repetir)
//           --nombre sufijo    para distinguir capturas de la misma ruta
// Deja los archivos en pruebas-navegador/capturas/ e imprime sus rutas.
import { abrir, entrar, ir, nombreArchivo } from './lib.mjs';

const args = process.argv.slice(2);
const ruta = args.find((a) => a.startsWith('/')) ?? '/';
const quien = args.includes('--admin') ? 'admin' : args.includes('--socio') ? 'socio' : null;
const vps = args.includes('--ambos') ? ['movil', 'escritorio'] : args.includes('--escritorio') ? ['escritorio'] : ['movil'];
const clics = args.flatMap((a, i) => (a === '--clic' ? [args[i + 1]] : []));
const sufijo = args.includes('--nombre') ? args[args.indexOf('--nombre') + 1] : '';

for (const vp of vps) {
  const { page, errores, cerrar } = await abrir(vp);
  await entrar(page, quien);
  const resp = await ir(page, ruta);
  for (const c of clics) {
    await page.locator(c).first().click();
    await page.waitForTimeout(600);
  }
  const archivo = nombreArchivo(ruta, vp, sufijo);
  await page.screenshot({ path: archivo, fullPage: true });
  console.log(`${resp?.status()} ${vp} ${ruta} → ${archivo}`);
  errores.forEach((e) => console.log('   ' + e));
  await cerrar();
}
