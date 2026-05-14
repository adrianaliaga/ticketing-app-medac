# Setup rápido para desarrolladores (Windows / XAMPP)

Este proyecto usa Composer para gestionar dependencias (Dompdf, Endroid QR, ...).
A continuación tienes instrucciones y un script para dejar el entorno listo con un solo paso.

## Opción recomendada: ejecutar el bootstrap (Windows + XAMPP)
Abre PowerShell en la raíz del repositorio y ejecuta:

```pwsh
# desde la raíz del repo
scripts\bootstrap-windows.ps1
```

El script intentará detectar `C:\xampp\php\php.exe` (si no lo encuentra te pedirá la ruta), descargará `composer.phar` si falta y ejecutará `php composer.phar install`.

## Instrucción manual (alternativa)
Si prefieres hacerlo manualmente:

```pwsh
# descarga composer.phar (solo si no tienes composer global)
Invoke-WebRequest 'https://getcomposer.org/composer.phar' -OutFile 'composer.phar' -UseBasicParsing
# instala dependencias con el PHP de XAMPP
C:\xampp\php\php.exe composer.phar install
```

Si tienes Composer instalado globalmente, simplemente:

```pwsh
composer install
```

## ¿Qué pasa al hacer git pull? ¿se descargan dependencias?
- No: `git pull` únicamente actualiza archivos versionados. No ejecuta Composer automáticamente.
- Para que las dependencias estén disponibles tras un `git pull`, cada desarrollador debe ejecutar el `scripts\bootstrap-windows.ps1` o `composer install`.

## Vendorizar (commitear `vendor/`) — tradeoffs
Si algunos miembros del equipo no pueden ejecutar Composer, puedes optar por "vendorizar": commitear la carpeta `vendor/` al repositorio.

Pros:
- Después de `git pull` tendrás las dependencias ya en el repo y todo funcionará sin pasos adicionales.

Contras:
- Aumenta el tamaño del repositorio.
- Actualizar dependencias crea commits grandes.
- Requiere cuidado para no mezclar `vendor/` con instalaciones parciales.

Si decides vendorizar, pasos sugeridos para quien prepare el commit (hazlo en una rama nueva):

```pwsh
# preparar vendor (en tu máquina local con composer disponible)
C:\xampp\php\php.exe composer.phar install --no-dev --optimize-autoloader
# revisar cambios, añadir vendor
git add vendor/
git commit -m "Vendor: agregar dependencias para facilitar entornos sin composer"
git push origin tu-rama
```

Después de revisar y aprobar, se puede mergear a `main`.

> Nota: Si optas por vendorizar, añade `docs/SETUP.md` una sección grande que explique cómo actualizar vendor y que no se haga `composer update` directamente en `main`.

## Requisitos PHP mínimos
Recomiendo tener PHP >= 8.0 (según Dompdf y Endroid) y las extensiones:
- `mbstring`
- `gd` (opcional; usamos SVG si GD no está disponible)
- `xml`
- `zip`

Puedes comprobar desde PHP con un pequeño script `php -r "print_r(get_loaded_extensions());"` o ejecutar `docs/check_requirements.php` (si se añade).

## Si quieres, lo hago por ti
Puedo:
- Añadir `scripts/bootstrap-windows.ps1` (ya creado).
- Añadir `docs/SETUP.md` (ya creado).
- Preparar un commit de `vendor/` para vendorizar y dejarlo listo (si confirmas, lo haré y te mostraré el tamaño/impacto).

Dime si quieres que proceda a commitear `vendor/` (vendorizar) o prefieres que dejemos el bootstrap + README y cada dev ejecute el script.