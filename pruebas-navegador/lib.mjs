// Utilidades comunes del banco de pruebas: navegador, viewports, login y rutas.
import { chromium } from 'playwright';
import fs from 'node:fs';
import path from 'node:path';

export const BASE = process.env.PLICA_URL ?? 'http://127.0.0.1:8123';
export const CAPTURAS = path.join(path.dirname(new URL(import.meta.url).pathname), 'capturas');
fs.mkdirSync(CAPTURAS, { recursive: true });

export const VIEWPORTS = {
  movil: { viewport: { width: 390, height: 844 }, isMobile: true, hasTouch: true, deviceScaleFactor: 2 },
  escritorio: { viewport: { width: 1280, height: 800 } },
};

export const USUARIOS = {
  admin: { login: '/admin/login', email: 'admin@plica.test', password: 'plica2026' },
  socio: { login: '/app/login', email: 'socio@plica.test', password: 'plica2026' },
};

export async function abrir(vp = 'movil') {
  const browser = await chromium.launch();
  const ctx = await browser.newContext({ ...VIEWPORTS[vp], locale: 'es-ES' });
  const page = await ctx.newPage();
  const errores = [];
  page.on('pageerror', (e) => errores.push('pageerror: ' + e.message));
  page.on('console', (m) => { if (m.type() === 'error') errores.push('console: ' + m.text().slice(0, 160)); });
  page.on('response', (r) => { if (r.status() >= 500) errores.push(`HTTP ${r.status()} ${r.url()}`); });
  return { browser, ctx, page, errores, cerrar: () => browser.close() };
}

export async function entrar(page, quien) {
  const u = USUARIOS[quien];
  if (!u) return;
  await page.goto(BASE + u.login, { waitUntil: 'networkidle' });
  await page.fill('input[type="email"]', u.email);
  await page.fill('input[type="password"]', u.password);
  await Promise.all([
    page.waitForURL((url) => !url.pathname.endsWith('/login'), { timeout: 20000 }),
    page.click('button[type="submit"]'),
  ]);
}

export async function ir(page, ruta) {
  const resp = await page.goto(BASE + ruta, { waitUntil: 'networkidle', timeout: 30000 });
  await page.waitForTimeout(250);
  return resp;
}

export function nombreArchivo(ruta, vp, sufijo = '') {
  const limpio = ruta.replace(/^\//, '').replace(/[^a-z0-9]+/gi, '-').replace(/^-|-$/g, '') || 'inicio';
  return path.join(CAPTURAS, `${vp}-${limpio}${sufijo ? '-' + sufijo : ''}.png`);
}
