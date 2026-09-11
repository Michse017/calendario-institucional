#!/usr/bin/env node
/**
 * Comprueba que el CSS compilado no se ha editado a mano.
 *
 * El riesgo que vigila es concreto y ya ocurrió en el proyecto del que nace
 * este: alguien añade una regla directamente en `public/assets/css/app.css`,
 * funciona, y la siguiente vez que alguien ejecuta `npm run build` esa regla
 * desaparece sin que nadie se entere.
 *
 * Comparar los dos archivos byte a byte sería lo ideal, pero la salida de
 * Tailwind depende del sistema de archivos y no coincide entre Windows y Linux.
 * Lo que sí es estable es el CONJUNTO de clases propias, así que se compara eso:
 * si el compilado tiene alguna clase `.cro-` que el fuente no genera, es que se
 * escribió a mano y se perderá.
 *
 * Uso:  node bin/verificar_css.mjs <compilado-actual> <compilado-recien-generado>
 */

import { readFileSync } from 'node:fs';

const [, , rutaComprometido, rutaGenerado] = process.argv;

if (!rutaComprometido || !rutaGenerado) {
  console.error('Uso: node bin/verificar_css.mjs <compilado-actual> <compilado-generado>');
  process.exit(2);
}

/** Devuelve el conjunto de clases propias del proyecto (las que empiezan por cro-). */
function clasesPropias(ruta) {
  const css = readFileSync(ruta, 'utf8');
  return new Set([...css.matchAll(/\.(cro-[a-z0-9-]+)/g)].map((m) => m[1]));
}

const enComprometido = clasesPropias(rutaComprometido);
const enGenerado = clasesPropias(rutaGenerado);

const perdidas = [...enComprometido].filter((c) => !enGenerado.has(c)).sort();
const nuevas = [...enGenerado].filter((c) => !enComprometido.has(c)).sort();

console.log(`Clases propias en el archivo versionado: ${enComprometido.size}`);
console.log(`Clases propias que genera el fuente:     ${enGenerado.size}`);

if (perdidas.length > 0) {
  console.error('\nEstas clases están en el CSS compilado pero el fuente NO las genera.');
  console.error('Se escribieron a mano en el compilado y se perderán en la próxima compilación.');
  console.error('Muévelas a public/assets/css/src/app.css:\n');
  perdidas.forEach((c) => console.error(`  .${c}`));
  process.exit(1);
}

if (nuevas.length > 0) {
  console.error('\nEl fuente genera clases que el compilado versionado no tiene:\n');
  nuevas.forEach((c) => console.error(`  .${c}`));
  console.error('\nEjecuta "npm run build" y vuelve a enviar el cambio.');
  process.exit(1);
}

console.log('\nCorrecto: el compilado y el fuente declaran las mismas clases propias.');
