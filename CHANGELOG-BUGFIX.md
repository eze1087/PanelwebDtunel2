# CHANGELOG — PanelwebDtunel2 By El NeNe

## v1.0.54 — 2026-05-10 — configs-rawarray-signing

### "Nenhuma configuração encontrada" — RESUELTO

Análisis del SDK de DTunnel reveló que `assets/config.json` debe ser un
**array de objetos directo**, no `{"content": [array]}` como lo teníamos.
El endpoint `/api/dtunnelmod` con `dtunnel-update: app_config` hace
`response.map(JSON.parse)` y retorna array crudo. Los archivos offline
en `assets/` siguen el mismo formato.

Cambiados a RAW ARRAY:
- `assets/config.json` (configs VPN)
- `assets/app_config.json` (layout)
- `assets/app_text.json` (textos UI)
- `assets/category.json`
- `assets/cdn.json`

### Categorías embebidas con id+status — RESUELTO

Los configs traían `category: {name, color, sorter}` sin `id` ni `status`.
La app DTunnel matchea `config.category.id` con `category.json[].id`. Sin
match, los configs son rechazados → "Nenhuma configuração encontrada".

Fix en dos pasadas:
1. Extraer todas las categorías embebidas y registrarlas en `category.json`
   con `id` estable (hash MD5 del nombre) y `status: ACTIVE`.
2. Rellenar `id` y `status` en cada `config.category` apuntando al objeto
   registrado en la pasada 1.

Resultado: cada `config.category.id` existe en `category.json` → la app
encuentra los configs.

### Sub-menú robusto en opción [23] del CLI

La opción [23] anterior intentaba hacer todo de una y fallaba en silencio
si algo (Java, descarga, permisos) no funcionaba. Ahora muestra:

- **Diagnóstico al entrar** — ✓/✗ por cada componente: Java, keytool,
  jarsigner, keystore, uber-apk-signer.jar, permisos del directorio
- **[1] Hacer todo** — workflow completo recomendado
- **[2] Instalar Java JDK 21** — con fallback a 17 y default-jdk
- **[3] Generar keystore** — paso aislado
- **[4] Descargar uber-apk-signer.jar** — wget con fallback a curl
- **[5] Probar firma con APK base** — test real con un APK real
- **[6] Reset** — borrar y empezar de cero
- **[7] Ver logs Apache** — filtrados por errores de firma

Cada paso reporta el error exacto y muestra los comandos manuales de
recuperación si falla.

---

## v1.0.53 — 2026-05-09 — apk-size-fix
- Tamaño APK 60→160MB resuelto (eliminado `lib/` del pattern STORE)
- Firma autosetup en cada compilación (uber-apk-signer + fallback jarsigner)
- Response include `sign_error` para diagnóstico

## v1.0.52 — 2026-05-08
- Solo SUPER_PRO/SUPER_LITE (eliminado DTUNNEL_MOD)
- Fix structural: `}` mal posicionado en if POST que rompía /gerar-apk
