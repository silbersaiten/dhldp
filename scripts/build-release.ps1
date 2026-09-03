param(
    [string]$PhpPath = "php",
    [switch]$SkipDocumentationBuild
)

$ErrorActionPreference = "Stop"
$moduleRoot = Split-Path -Parent $PSScriptRoot
$moduleName = "dhldp"
$version = "3.2.9"
$distDir = Join-Path $moduleRoot "dist"
$stageRoot = Join-Path $distDir ".build"
$stageModule = Join-Path $stageRoot $moduleName
$zipPath = Join-Path $distDir ("{0}-{1}.zip" -f $moduleName, $version)

if (-not $SkipDocumentationBuild) {
    & $PhpPath (Join-Path $PSScriptRoot "build-html-docs.php")
    if ($LASTEXITCODE -ne 0) {
        throw "Documentation build failed."
    }
}

$required = @(
    "docs\user-guide-en.md",
    "docs\user-guide-en.html",
    "docs\module-description-en.md",
    "docs\module-description-en.html",
    "docs\benutzerhandbuch-de.md",
    "docs\benutzerhandbuch-de.html",
    "docs\modulbeschreibung-de.md",
    "docs\modulbeschreibung-de.html",
    "docs\manual-de-usuario-es.md",
    "docs\manual-de-usuario-es.html",
    "docs\descripcion-del-modulo-es.md",
    "docs\descripcion-del-modulo-es.html",
    "docs\podrecznik-uzytkownika-pl.md",
    "docs\podrecznik-uzytkownika-pl.html",
    "docs\opis-modulu-pl.md",
    "docs\opis-modulu-pl.html",
    "docs\manuale-utente-it.md",
    "docs\manuale-utente-it.html",
    "docs\descrizione-modulo-it.md",
    "docs\descrizione-modulo-it.html",
    "docs\guide-utilisateur-fr.md",
    "docs\guide-utilisateur-fr.html",
    "docs\description-module-fr.md",
    "docs\description-module-fr.html",
    "docs\documentation.css",
    "docs\documentation.js",
    "docs\silbersaiten-logo.jpg",
    "views\css\admin.css",
    "views\templates\admin\quick-start.tpl"
)

foreach ($relativePath in $required) {
    if (-not (Test-Path -LiteralPath (Join-Path $moduleRoot $relativePath) -PathType Leaf)) {
        throw "Required release file is missing: $relativePath"
    }
}

New-Item -ItemType Directory -Force -Path $distDir | Out-Null
$resolvedModuleRoot = (Resolve-Path -LiteralPath $moduleRoot).Path
$resolvedDistDir = (Resolve-Path -LiteralPath $distDir).Path
if (-not $resolvedDistDir.StartsWith($resolvedModuleRoot, [System.StringComparison]::OrdinalIgnoreCase)) {
    throw "Refusing to use a staging directory outside the module root."
}

if (Test-Path -LiteralPath $stageRoot) {
    Remove-Item -LiteralPath $stageRoot -Recurse -Force
}
New-Item -ItemType Directory -Force -Path $stageModule | Out-Null

$excludedNames = @(
    ".git",
    ".github",
    ".idea",
    ".vscode",
    ".env",
    ".DS_Store",
    "dist",
    "node_modules",
    "tests",
    "tmp",
    ".phpunit.cache"
)
Get-ChildItem -LiteralPath $moduleRoot -Force | Where-Object { $excludedNames -notcontains $_.Name } | ForEach-Object {
    Copy-Item -LiteralPath $_.FullName -Destination $stageModule -Recurse -Force
}

if (Test-Path -LiteralPath $zipPath) {
    Remove-Item -LiteralPath $zipPath -Force
}
$tar = Get-Command tar -ErrorAction Stop
& $tar.Source -a -c -f $zipPath -C $stageRoot $moduleName
if ($LASTEXITCODE -ne 0) {
    throw "Release archive creation failed."
}
Remove-Item -LiteralPath $stageRoot -Recurse -Force

Write-Output $zipPath
