# CHANGELOG — PanelwebDtunel2 By El NeNe

## v1.0.56 — 2026-05-12 — cli-polish

### Bug del rendering: `\033[2m` literal — RESUELTO

Las definiciones de colores usaban comillas simples (`DIM='\033[2m'`) que
bash guarda como string literal. Cuando se hacía `printf "%s" "$DIM"` el
literal salía sin interpretar.

Cambio a ANSI-C quoting (`DIM=$'\033[2m'`) que guarda el byte ESC real.
Ahora `printf` y `echo` lo interpretan correctamente.

### Dashboard limpio

Antes:
```
DISCO  : [######····]  53%  7.7G / 15G  (libre 7.1G)
RAM    : [###·······]  19%  185MB / 957MB  (libre 73MB)
CPU    : [··········]   0%  1 cores  load: 0.08, 0.02, 0.01
RED    : ↓ 25.54 GB  ↑ 6.06 GB  (desde boot)
```

Ahora:
```
DISCO  ████████████░░░░░░░░░░  40G total
RAM    ████░░░░░░░░░░░░░░░░░░  1939 MB
CPU    ░░░░░░░░░░░░░░░░░░░░░░  2 cores
RED    ↓ 25.54 GB   ↑ 6.06 GB
```

Lo redundante (uso/libre/load/desde boot) sale fuera, solo queda lo
esencial. Las barras usan █ y ░ — estilo "monitor recargable".

### Menú principal en una sola columna

Cada opción con descripción dim al lado:

```
  [1] 🛠️   Panel              — Apache, logs, actualizar
  [2] 👥  Usuarios (3)         — listar, editar, bloqueos
  [3] 📱  APK & Firma         — bases, firma, autosetup
  ...
```

Submenús reformateados igual: una columna por línea con descripción,
mucho más fácil de leer y profesional.

---

## v1.0.55 — 2026-05-11
- Sync categorías ANTES de escribir config.json (orden de ops correcto)
- app_config.json (LAYOUT) vuelve a {content:[]} wrapped
- Botón "Firmar APK" en panel web post-compilación
- Test [5] del CLI con ruta APK base corregida

## v1.0.54 — 2026-05-10
- Configs/layout/text a raw array (parcial — bug de orden de ops)
