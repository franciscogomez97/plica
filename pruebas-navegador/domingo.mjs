// El domingo del presidente: entra en el móvil, encuentra la manga de ayer, pasa lista,
// teclea las plicas, cierra la manga y la comparte. Cuenta toques y pantallas y deja
// una captura por paso en capturas/domingo-N.png.
//
//   node pruebas-navegador/domingo.mjs            (usa la manga programada más reciente con fecha pasada)
import { abrir, entrar, ir, CAPTURAS, BASE } from './lib.mjs';

const { page, errores, cerrar } = await abrir('movil');
let toques = 0;
let paso = 0;
const foto = async (nombre) => { paso++; const f = `${CAPTURAS}/domingo-${String(paso).padStart(2, '0')}-${nombre}.png`; await page.screenshot({ path: f, fullPage: false }); return f; };
const toca = async (loc, que) => { toques++; await loc.click(); await page.waitForTimeout(700); console.log(`   toque ${toques}: ${que}`); };
const texto = async (sel = 'main') => (await page.locator(sel).innerText()).replace(/\s+/g, ' ').trim();

console.log('1. Entra en el panel');
await entrar(page, 'admin'); toques += 3; // email, contraseña, entrar
await ir(page, '/admin');
console.log('   Inicio dice:', (await texto()).slice(0, 220));
await foto('inicio');

console.log('2. Busca la manga de ayer');
const aviso = page.locator('text=/por gestionar/i').first();
console.log('   ¿hay aviso de manga por gestionar?', await aviso.count() > 0 ? 'sí' : 'NO');
const avisoTexto = await aviso.count() ? (await page.locator('text=/por gestionar/i').first().locator('xpath=ancestor::section[1]').innerText()).replace(/\s+/g, ' ').slice(0, 200) : '';
console.log('   el aviso dice:', avisoTexto);
const enlaceManga = page.getByRole('link', { name: 'Pesaje' }).first();
await toca(enlaceManga, 'el botón «Pesaje» del aviso');
console.log('   lleva a:', page.url().replace(BASE, ''));
await foto('manga');

console.log('3. Pasa lista');
await toca(page.getByRole('button', { name: 'Marcar asistencia' }), 'Marcar asistencia');
await foto('asistencia-modal');
const casillas = page.locator('[role="dialog"] input[type="checkbox"]');
console.log(`   casillas: ${await casillas.count()}, marcadas de antemano: ${await page.locator('[role="dialog"] input[type="checkbox"]:checked').count()}`);
for (let i = 0; i < 3; i++) { if (!(await casillas.nth(i).isChecked())) await toca(casillas.nth(i), `marcar socio ${i + 1}`); }
await toca(page.getByRole('button', { name: 'Guardar asistencia' }), 'Guardar asistencia');
console.log('   tras guardar:', (await texto()).match(/\d+ participantes?/)?.[0], '·', (await texto()).match(/Asistencia guardada[^.]*/)?.[0] ?? '(sin notificación visible)');
await foto('lista-pasada');

console.log('4. Teclea las plicas');
const filas = page.locator('[data-fila]');
const n = await filas.count();
const plicas = [['4', '5200', '1800'], ['2', '3100', '2000'], null];
for (let i = 0; i < Math.min(n, 3); i++) {
  const id = await filas.nth(i).getAttribute('data-fila');
  const nombre = (await filas.nth(i).innerText()).split('\n')[0].trim();
  if (!plicas[i]) { console.log(`   ${nombre}: bolo, no se toca nada`); continue; }
  const [pz, g, mayor] = plicas[i];
  toques++; await page.fill(`#piezas-${id}`, pz); await page.keyboard.press('Enter');
  toques++; await page.fill(`#peso-${id}`, g); await page.keyboard.press('Enter');
  toques++; await page.fill(`#mayor-${id}`, mayor); await page.keyboard.press('Enter');
  await page.waitForTimeout(600);
  console.log(`   ${nombre}: ${pz} pzs · ${g} g · mayor ${mayor} → «${(await filas.nth(i).locator('.pesaje-estado, small, .text-xs').first().innerText().catch(() => '?')).trim()}»`);
  console.log(`   foco tras Enter en «mayor»: ${await page.evaluate(() => document.activeElement?.id || document.activeElement?.tagName)}`);
}
await foto('plicas');
const errorVisible = await page.locator('.pesaje-estado.error').count();
console.log('   errores en pantalla:', errorVisible);

console.log('5. Cierra la manga');
const cerrar_ = page.getByRole('button', { name: /Cerrar la manga/ });
console.log('   ¿botón de cerrar visible sin hacer scroll?', await cerrar_.evaluate((el) => { const r = el.getBoundingClientRect(); return r.top >= 0 && r.bottom <= innerHeight; }).catch(() => 'no existe'));
await cerrar_.scrollIntoViewIfNeeded();
await foto('boton-cerrar');
await toca(cerrar_, 'Cerrar la manga');
await foto('confirmar');
const confirmar = page.getByRole('button', { name: 'Sí, cerrar la manga' });
await confirmar.waitFor({ timeout: 5000 });
console.log('   modal:', (await page.getByText('¿Marcar la manga como celebrada?').locator('xpath=ancestor::*[self::div][3]').innerText()).replace(/\s+/g, ' ').slice(0, 200));
await toca(confirmar, 'confirmar');
await page.waitForTimeout(1200);
console.log('   lleva a:', page.url().replace(BASE, ''));
await foto('clasificacion');
console.log('   pantalla:', (await texto()).slice(0, 260));

console.log('6. Comparte');
const wa = page.locator('a[href*="wa.me"], a[href*="whatsapp"]').first();
const compartir = page.getByRole('button', { name: /Compartir/ }).or(page.getByRole('link', { name: /Compartir/ })).first();
console.log('   botones de compartir:', await page.locator('a, button').filter({ hasText: /Compartir|WhatsApp|Imagen/ }).allInnerTexts());
if (await wa.count()) console.log('   WhatsApp lleva:', decodeURIComponent(await wa.getAttribute('href')).slice(0, 220).replace(/\n/g, ' ⏎ '));
else if (await compartir.count()) { await toca(compartir, 'Compartir'); await foto('compartir'); console.log('   tras pulsar:', (await texto()).slice(0, 200)); }

console.log(`\nTotal: ${toques} toques (sin contar teclear números), ${paso} pantallas. Errores JS/HTTP: ${errores.length}`);
errores.forEach((e) => console.log('   ' + e));
await cerrar();
