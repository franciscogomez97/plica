// Captura una página en trozos del tamaño de la pantalla (legibles de verdad), en vez de
// una tira entera diminuta. Útil para revisar diseño con lupa.
//
//   node pruebas-navegador/trozos.mjs /c/cd-pesca-piloto/orilla --movil [--admin|--socio] [--max 6]
import { abrir, entrar, ir, nombreArchivo } from './lib.mjs';

const args = process.argv.slice(2);
const ruta = args.find((a) => a.startsWith('/')) ?? '/';
const quien = args.includes('--admin') ? 'admin' : args.includes('--socio') ? 'socio' : null;
const vp = args.includes('--escritorio') ? 'escritorio' : 'movil';
const max = args.includes('--max') ? Number(args[args.indexOf('--max') + 1]) : 8;

const { page, errores, cerrar } = await abrir(vp);
await entrar(page, quien);
await ir(page, ruta);
const alto = await page.evaluate(() => document.documentElement.scrollHeight);
const paso = (await page.viewportSize()).height;
const n = Math.min(max, Math.ceil(alto / paso));
for (let i = 0; i < n; i++) {
  await page.evaluate((y) => window.scrollTo(0, y), i * paso);
  await page.waitForTimeout(150);
  const archivo = nombreArchivo(ruta, vp, `trozo${i + 1}`);
  await page.screenshot({ path: archivo, fullPage: false });
  console.log(archivo);
}
errores.forEach((e) => console.log('   ' + e));
await cerrar();
