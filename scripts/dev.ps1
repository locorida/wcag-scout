# Runs a command in the dev container with the repository mounted at /app.
#
#   .\scripts\dev.ps1 composer install
#   .\scripts\dev.ps1 composer ci
#   .\scripts\dev.ps1 bin/wcag-scout audit http://host.docker.internal:8000/
#
# Builds the dev image on first use (or with -Build).

param(
    [switch] $Build,
    [Parameter(ValueFromRemainingArguments)] [string[]] $Command
)

$ErrorActionPreference = 'Stop'
$root = Split-Path -Parent $PSScriptRoot
$image = 'wcag-scout:dev'

& docker image inspect $image *> $null
if ($Build -or $LASTEXITCODE -ne 0) {
    & docker build --target dev -t $image $root
    if ($LASTEXITCODE -ne 0) { exit $LASTEXITCODE }
}

if (-not $Command) { $Command = @('bash') }
& docker run --rm -it -v "${root}:/app" --add-host host.docker.internal:host-gateway $image @Command
exit $LASTEXITCODE
