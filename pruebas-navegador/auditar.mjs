// Auditoría completa del banco de pruebas: capturas, axe-core, desbordes, objetivos
// táctiles, textos pequeños y errores de consola en 21 páginas × móvil y escritorio.
// Encender antes: pruebas-navegador/banco.sh encender · Resultado: capturas/resultados.json
import { chromium } from 'playwright';
import { BASE, CAPTURAS, VIEWPORTS, USUARIOS } from './lib.mjs';
import AxeBuilder from '@axe-core/playwright';
import fs from 'node:fs';


const OUT = CAPTURAS + '/';
fs.mkdirSync(OUT, { recursive: true });


const PUBLICAS = [
  ['landing', '/'],
  ['club', '/c/cd-pesca-piloto'],
  ['seccion-orilla', '/c/cd-pesca-piloto/orilla'],
  ['seccion-pato', '/c/cd-pesca-piloto/pato-lucio'],
  ['manga-1', '/c/cd-pesca-piloto/manga/1'],
  ['privacidad', '/privacidad'],
];
const ADMIN = [
  ['admin-inicio', '/admin'],
  ['admin-mangas', '/admin/mangas'],
  ['admin-pesaje-celebrada', '/admin/mangas/1/pesaje'],
  ['admin-pesaje-programada', '/admin/mangas/7/pesaje'],
  ['admin-clasificacion', '/admin/mangas/1/clasificacion'],
  ['admin-manga-edit', '/admin/mangas/1/edit'],
  ['admin-seccion-edit', '/admin/seccions/1/edit'],
  ['admin-socios', '/admin/socios'],
  ['admin-ranking', '/admin/ranking'],
  ['admin-ranking-orilla', '/admin/ranking/1'],
  ['admin-mi-club', '/admin/mi-club'],
  ['admin-equipos', '/admin/equipos'],
];
const SOCIO = [
  ['app-inicio', '/app'],
  ['app-ranking-orilla', '/app/ranking/1'],
  ['app-manga-1', '/app/manga/1'],
];

async function login(page, url, email, password) {
  await page.goto(BASE + url, { waitUntil: 'networkidle' });
  await page.fill('input[type="email"]', email);
  await page.fill('input[type="password"]', password);
  await Promise.all([
    page.waitForURL((u) => !u.pathname.endsWith('/login'), { timeout: 20000 }),
    page.click('button[type="submit"]'),
  ]);
}

async function auditar(page, nombre, url, vp) {
  const consola = [];
  const onConsole = (m) => { if (m.type() === 'error' || m.type() === 'warning') consola.push(`${m.type()}: ${m.text().slice(0, 200)}`); };
  const onError = (e) => consola.push(`pageerror: ${e.message.slice(0, 200)}`);
  page.on('console', onConsole);
  page.on('pageerror', onError);

  const t0 = Date.now();
  const resp = await page.goto(BASE + url, { waitUntil: 'networkidle', timeout: 30000 });
  const ms = Date.now() - t0;
  await page.waitForTimeout(300);

  const metricas = await page.evaluate(() => {
    const doc = document.documentElement;
    const desborde = doc.scrollWidth - doc.clientWidth;
    const interactivos = [...document.querySelectorAll('a[href], button, input, select, textarea, [role="button"]')]
      .filter((el) => {
        const r = el.getBoundingClientRect();
        const cs = getComputedStyle(el);
        return r.width > 0 && r.height > 0 && cs.visibility !== 'hidden' && cs.display !== 'none';
      });
    const pequenos = interactivos
      .filter((el) => {
        const r = el.getBoundingClientRect();
        return (r.height < 32 || r.width < 32) && el.tagName !== 'INPUT' || (el.tagName === 'INPUT' && r.height < 32 && el.type !== 'checkbox' && el.type !== 'radio');
      })
      .map((el) => `${el.tagName.toLowerCase()} "${(el.innerText || el.getAttribute('aria-label') || el.value || '').trim().slice(0, 30)}" ${Math.round(el.getBoundingClientRect().width)}x${Math.round(el.getBoundingClientRect().height)}`);
    const textos = [...document.querySelectorAll('body *')]
      .filter((el) => el.childNodes.length && [...el.childNodes].some((n) => n.nodeType === 3 && n.textContent.trim()))
      .map((el) => parseFloat(getComputedStyle(el).fontSize));
    const menores12 = textos.filter((s) => s < 12).length;
    const h1 = document.querySelectorAll('h1').length;
    const imgsSinAlt = [...document.querySelectorAll('img')].filter((i) => !i.hasAttribute('alt')).length;
    const lang = document.documentElement.lang;
    const titulo = document.title;
    const viewport = document.querySelector('meta[name="viewport"]')?.content ?? null;
    return { desborde, interactivos: interactivos.length, pequenos: pequenos.slice(0, 12), nPequenos: pequenos.length, textos: textos.length, menores12, h1, imgsSinAlt, lang, titulo, viewport, alto: doc.scrollHeight };
  });

  let axe = { violations: [] };
  try {
    axe = await new AxeBuilder({ page }).withTags(['wcag2a', 'wcag2aa', 'wcag21aa', 'best-practice']).analyze();
  } catch (e) {
    consola.push('axe: ' + e.message.slice(0, 100));
  }
  const violaciones = axe.violations.map((v) => ({
    id: v.id, impacto: v.impact, ayuda: v.help, n: v.nodes.length,
    ejemplos: v.nodes.slice(0, 3).map((n) => n.html.slice(0, 140)),
  }));

  const archivo = `${OUT}${vp}-${nombre}.png`;
  await page.screenshot({ path: archivo, fullPage: true });

  page.off('console', onConsole);
  page.off('pageerror', onError);
  return { nombre, url, vp, status: resp?.status(), ms, ...metricas, consola: consola.slice(0, 8), violaciones };
}

const resultados = [];
const browser = await chromium.launch();
for (const [vp, opciones] of Object.entries(VIEWPORTS)) {
  const ctx = await browser.newContext({ ...opciones, locale: 'es-ES' });
  const page = await ctx.newPage();
  for (const [n, u] of PUBLICAS) resultados.push(await auditar(page, n, u, vp));

  await login(page, '/admin/login', 'admin@plica.test', 'plica2026');
  for (const [n, u] of ADMIN) resultados.push(await auditar(page, n, u, vp));
  await ctx.clearCookies();

  await login(page, '/app/login', 'socio@plica.test', 'plica2026');
  for (const [n, u] of SOCIO) resultados.push(await auditar(page, n, u, vp));
  await ctx.close();
}
await browser.close();

fs.writeFileSync(`${OUT}resultados.json`, JSON.stringify(resultados, null, 2));

// Resumen en texto
for (const r of resultados) {
  const graves = r.violaciones.filter((v) => ['serious', 'critical'].includes(v.impacto));
  console.log(`\n[${r.vp}] ${r.nombre} ${r.url} → ${r.status} en ${r.ms} ms · alto ${r.alto}px · desborde ${r.desborde}px · h1:${r.h1} · pequeños ${r.nPequenos}/${r.interactivos} · texto<12px ${r.menores12}/${r.textos} · img sin alt ${r.imgsSinAlt}`);
  for (const v of r.violaciones) console.log(`   axe ${v.impacto}: ${v.id} (${v.n}) — ${v.ayuda}`);
  for (const c of r.consola) console.log(`   consola: ${c}`);
  if (r.vp === 'movil' && r.pequenos.length) console.log(`   pequeños: ${r.pequenos.slice(0, 5).join(' | ')}`);
}
