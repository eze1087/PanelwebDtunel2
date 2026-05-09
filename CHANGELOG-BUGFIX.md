# CHANGELOG — PanelwebDtunel2 By El NeNe

## v1.0.55 — 2026-05-11 — sign-button-fixes

### "Nenhuma configuração encontrada" — REALMENTE resuelto

El v1.0.54 tenía un **bug de orden de operaciones**: el sync de categorías
que rellena `category.id` se ejecutaba DESPUÉS de escribir `config.json`,
así que los configs salían con `category: {name, color, sorter}` SIN id.
La app DTunnel matchea `config.category.id` contra `category.json[].id` —
sin id, descartaba todos los configs.

Inspeccioné el APK que mandaste (`79c3ed_ElNeneLite`):
- 6 configs con `id` ✓
- Pero `config.category.id` → vacío en los 6 ✗
- 7 categorías en `category.json` con `id` ✓
- Match → False → "Nenhuma configuração encontrada"

Fix: reordené para que el sync ocurra ANTES de escribir `config.json`. Ahora
cada config sale con `category: {id, name, color, sorter, status}` completo.

### Layout dejó de funcionar — RESUELTO

En v1.0.54 cambié `app_config.json` (LAYOUT) a raw array siguiendo el SDK,
pero ese archivo offline tiene un parser distinto al endpoint y necesita
formato `{"content": [array]}` para funcionar. Lo volví al wrapper.

Resumen de formatos correctos por archivo:
- `assets/app_config.json` (LAYOUT) → `{"content": [array]}`
- `assets/app_text.json` (textos) → `{"content": [array]}`
- `assets/config.json` (configs VPN) → `[array]` raw
- `assets/category.json` → `[array]` raw
- `assets/cdn.json` → `[array]` raw

### Botón "Firmar APK" en el panel web — NUEVO

Después de generar el APK, en el card de éxito hay un botón nuevo (azul)
"Firmar APK". Si el panel no firmó automáticamente al compilar, podés darle
manualmente desde el panel web sin recompilar.

El endpoint `?action=sign_apk` recibe el filename, valida que esté en
`/downloads/`, hace autosetup (descarga uber-apk-signer si falta, genera
keystore si no existe), firma con V1+V2+V3 y reemplaza el archivo. Si falla,
muestra un toast con el motivo exacto.

### Test de firma [5] del CLI — Ruta corregida

El test buscaba APKs en `/var/www/html/uploads/apk_bases` pero la ruta real
es `/var/www/html/apk_base`. Corregido + fallback a otras rutas posibles.

### Dashboard del CLI — Barritas visuales

DISCO/RAM/CPU ahora se muestran como barras `[######········] 11%` con
color según el porcentaje:
- Verde: <60% (OK)
- Amarillo: 60-79% (atención)
- Rojo: ≥80% (crítico)

RED se mantiene con flechas ↓ ↑ porque no es porcentaje.

---

## v1.0.54 — 2026-05-10
- Configs/layout/text a raw array (parcial — bug de orden de ops)
- Sync de categorías introducido pero ineficaz por orden
- Sub-menú [23] con 7 opciones granulares

## v1.0.53 — 2026-05-09
- Tamaño APK 60→160MB resuelto (eliminado `lib/` del pattern STORE)
- Firma autosetup + signError diagnóstico

## v1.0.52 — 2026-05-08
- Solo SUPER_PRO/SUPER_LITE (eliminado DTUNNEL_MOD)
- Fix `}` mal posicionado que rompía /gerar-apk
