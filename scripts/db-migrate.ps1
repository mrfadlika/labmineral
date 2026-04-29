param(
    [string]$MysqlUser = "root",
    [string]$MysqlPassword = "",
    [string]$Database = "labmineral",
    [switch]$IncludeBase
)

$ErrorActionPreference = "Stop"

$projectRoot = Split-Path -Parent $PSScriptRoot
$mysqlExe = (Get-Command mysql -ErrorAction Stop).Source

function Get-MysqlArgs {
    param(
        [string]$User,
        [string]$Password
    )

    $args = @("--default-character-set=utf8mb4", "-u$User")
    if ($Password -ne "") {
        $args += "-p$Password"
    }
    return $args
}

function Invoke-SqlFile {
    param(
        [string]$FilePath,
        [string[]]$MysqlArgs
    )

    Write-Host ("`n==> Menjalankan {0}" -f (Resolve-Path -LiteralPath $FilePath))
    Get-Content -LiteralPath $FilePath -Raw | & $mysqlExe @MysqlArgs
    if ($LASTEXITCODE -ne 0) {
        throw "Migrasi gagal saat menjalankan $FilePath"
    }
}

$mysqlArgs = Get-MysqlArgs -User $MysqlUser -Password $MysqlPassword

$dbExistsRaw = & $mysqlExe @mysqlArgs -N -e "SHOW DATABASES LIKE '$Database';"
$dbExists = if ($null -eq $dbExistsRaw) { "" } else { "$dbExistsRaw".Trim() }

$baseMigrations = @(
    (Join-Path $projectRoot "scripts/sql/create_database_labmineral.sql"),
    (Join-Path $projectRoot "labmineral.sql"),
    (Join-Path $projectRoot "batch_sql.sql"),
    (Join-Path $projectRoot "fix.sql"),
    (Join-Path $projectRoot "modul_baru.sql"),
    (Join-Path $projectRoot "invoice.sql")
)

$postMigrations = @(
    (Join-Path $projectRoot "fix_work_order_batch.sql"),
    (Join-Path $projectRoot "scripts/sql/patch_work_order_nullable.sql"),
    (Join-Path $projectRoot "scripts/sql/patch_supervisor_role.sql"),
    (Join-Path $projectRoot "update_user_roles.sql")
)

if (-not $dbExists -or $IncludeBase) {
    Write-Host "Database labmineral belum ada. Menjalankan import schema dasar + migrasi lanjutan."
    foreach ($file in $baseMigrations + $postMigrations) {
        Invoke-SqlFile -FilePath $file -MysqlArgs $mysqlArgs
    }
} else {
    Write-Host "Database labmineral sudah ada. Runner ini aman untuk patch lanjutan saja."
    foreach ($file in $postMigrations) {
        Invoke-SqlFile -FilePath $file -MysqlArgs $mysqlArgs
    }
}

Write-Host "`n==> Verifikasi akhir"
& $mysqlExe @mysqlArgs -D $Database -e @"
SELECT 'pengguna' AS tabel, COUNT(*) AS total FROM pengguna
UNION ALL
SELECT 'sampel', COUNT(*) FROM sampel
UNION ALL
SELECT 'penerimaan_sampel', COUNT(*) FROM penerimaan_sampel
UNION ALL
SELECT 'work_order', COUNT(*) FROM work_order
UNION ALL
SELECT 'work_order_sampel', COUNT(*) FROM work_order_sampel
UNION ALL
SELECT 'preparasi_sampel', COUNT(*) FROM preparasi_sampel
UNION ALL
SELECT 'qc_sampel', COUNT(*) FROM qc_sampel
UNION ALL
SELECT 'invoice', COUNT(*) FROM invoice;
"@

if ($LASTEXITCODE -ne 0) {
    throw "Verifikasi akhir gagal."
}

Write-Host "`nMigrasi database selesai."
