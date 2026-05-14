<#
scripts/bootstrap-windows.ps1
Bootstrap sencillo para desarrolladores Windows con XAMPP.
- Detecta C:\xampp\php\php.exe (pregunta ruta alternativa si no lo encuentra)
- Descarga composer.phar si falta
- Ejecuta composer install
#>

param(
    [string]$PhpPath = "C:\\xampp\\php\\php.exe",
    [switch]$ForceDownload
)

function Write-Ok($msg){ Write-Host "[OK] $msg" -ForegroundColor Green }
function Write-Err($msg){ Write-Host "[ERR] $msg" -ForegroundColor Red }

Push-Location (Split-Path -Path $MyInvocation.MyCommand.Definition -Parent) | Out-Null
# mover al root del repo (scripts/..)
Set-Location ..
$root = Get-Location
Write-Host "Repositorio: $root"

# comprobar php
if (-not (Test-Path $PhpPath)) {
    Write-Host "No encontré PHP en '$PhpPath'."
    $alt = Read-Host "Introduce la ruta completa a php.exe (o pulsa Enter para abortar)"
    if ([string]::IsNullOrWhiteSpace($alt)) { Write-Err "PHP no encontrado. Aborting."; exit 1 }
    $PhpPath = $alt
}
Write-Ok "Usando PHP: $PhpPath"

# composer.phar
$composer = Join-Path $root "composer.phar"
if ((Test-Path $composer) -and (-not $ForceDownload)) {
    Write-Host "composer.phar ya existe en el proyecto."
} else {
    Write-Host "Descargando composer.phar..."
    try {
        Invoke-WebRequest 'https://getcomposer.org/composer.phar' -OutFile $composer -UseBasicParsing -ErrorAction Stop
        Write-Ok "composer.phar descargado"
    } catch {
        Write-Err "No se pudo descargar composer.phar: $_"
        exit 1
    }
}

# ejecutar composer install
Write-Host "Ejecutando: $PhpPath composer.phar install --no-interaction --no-progress"
& $PhpPath composer.phar install --no-interaction --no-progress
if ($LASTEXITCODE -eq 0) {
    Write-Ok "Dependencias instaladas (vendor/ creado o actualizado)."
    Write-Host "Si quieres vendorizar (commitear vendor/), revisa docs/SETUP.md para los pasos y consideraciones."
} else {
    Write-Err "composer install devolvió código $LASTEXITCODE. Revisa la salida para más detalles."
}

Pop-Location | Out-Null
