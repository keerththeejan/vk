# Build print-ready company stamp from uploaded artwork (no redesign).
# Output: 69mm x 25mm @ 600 DPI (1630 x 590 px), transparent PNG.

Add-Type -AssemblyName System.Drawing

$sourcePath = Join-Path $PSScriptRoot 'company-stamp.png'
$backupPath = Join-Path $PSScriptRoot 'company-stamp.backup.png'
$outputPath = Join-Path $PSScriptRoot 'company-stamp.png'

$targetDpi = 600
$targetWidth = [int][math]::Round(69 / 25.4 * $targetDpi)   # 1630
$targetHeight = [int][math]::Round(25 / 25.4 * $targetDpi)  # 590

if (-not (Test-Path $sourcePath)) {
    throw "Source stamp not found: $sourcePath"
}

if (-not (Test-Path $backupPath)) {
    Copy-Item $sourcePath $backupPath -Force
}

function Get-InkAlpha([byte]$r, [byte]$g, [byte]$b) {
    $luma = 0.2126 * $r + 0.7152 * $g + 0.0722 * $b
    if ($luma -ge 248) { return 0 }
    if ($luma -le 8) { return 255 }

    $alpha = [int][math]::Round(255 - ($luma * 255 / 248))
    if ($alpha -lt 0) { return 0 }
    if ($alpha -gt 255) { return 255 }
    return $alpha
}

function Enhance-Ink([byte]$r, [byte]$g, [byte]$b, [double]$contrast, [double]$saturation, [double]$brightness) {
    $nr = [math]::Max(0, [math]::Min(255, (($r / 255.0 - 0.5) * $contrast + 0.5) * 255 * $brightness))
    $ng = [math]::Max(0, [math]::Min(255, (($g / 255.0 - 0.5) * $contrast + 0.5) * 255 * $brightness))
    $nb = [math]::Max(0, [math]::Min(255, (($b / 255.0 - 0.5) * $contrast + 0.5) * 255 * $brightness))

    $gray = 0.299 * $nr + 0.587 * $ng + 0.114 * $nb
    $nr = [math]::Max(0, [math]::Min(255, $gray + ($nr - $gray) * $saturation))
    $ng = [math]::Max(0, [math]::Min(255, $gray + ($ng - $gray) * $saturation))
    $nb = [math]::Max(0, [math]::Min(255, $gray + ($nb - $gray) * $saturation))

    return @([byte][math]::Round($nr), [byte][math]::Round($ng), [byte][math]::Round($nb))
}

$src = [System.Drawing.Image]::FromFile($sourcePath)
$scaled = New-Object System.Drawing.Bitmap($targetWidth, $targetHeight, [System.Drawing.Imaging.PixelFormat]::Format32bppArgb)
$scaled.SetResolution($targetDpi, $targetDpi)

$graphics = [System.Drawing.Graphics]::FromImage($scaled)
$graphics.Clear([System.Drawing.Color]::FromArgb(0, 0, 0, 0))
$graphics.InterpolationMode = [System.Drawing.Drawing2D.InterpolationMode]::HighQualityBicubic
$graphics.PixelOffsetMode = [System.Drawing.Drawing2D.PixelOffsetMode]::HighQuality
$graphics.SmoothingMode = [System.Drawing.Drawing2D.SmoothingMode]::HighQuality
$graphics.CompositingQuality = [System.Drawing.Drawing2D.CompositingQuality]::HighQuality
$graphics.DrawImage($src, 0, 0, $targetWidth, $targetHeight)
$graphics.Dispose()
$src.Dispose()

$rect = New-Object System.Drawing.Rectangle(0, 0, $targetWidth, $targetHeight)
$data = $scaled.LockBits($rect, [System.Drawing.Imaging.ImageLockMode]::ReadWrite, [System.Drawing.Imaging.PixelFormat]::Format32bppArgb)
$bytes = New-Object byte[] ($data.Stride * $data.Height)
[System.Runtime.InteropServices.Marshal]::Copy($data.Scan0, $bytes, 0, $bytes.Length)

$rng = New-Object System.Random(42)
$contrast = 1.38
$saturation = 1.22
$brightness = 1.04

for ($y = 0; $y -lt $targetHeight; $y++) {
    $row = $y * $data.Stride
    for ($x = 0; $x -lt $targetWidth; $x++) {
        $i = $row + ($x * 4)
        $b = $bytes[$i]
        $g = $bytes[$i + 1]
        $r = $bytes[$i + 2]
        $a = $bytes[$i + 3]

        if ($a -eq 0) { continue }

        $alpha = Get-InkAlpha $r $g $b
        if ($alpha -le 3) {
            $bytes[$i] = 0
            $bytes[$i + 1] = 0
            $bytes[$i + 2] = 0
            $bytes[$i + 3] = 0
            continue
        }

        $enhanced = Enhance-Ink $r $g $b $contrast $saturation $brightness
        $r = $enhanced[0]
        $g = $enhanced[1]
        $b = $enhanced[2]

        # Subtle natural ink texture (alpha variation only).
        $texture = 0.94 + ($rng.NextDouble() * 0.06)
        $alpha = [int][math]::Round($alpha * $texture)
        if ($alpha -gt 255) { $alpha = 255 }
        if ($alpha -lt 0) { $alpha = 0 }

        $bytes[$i] = $b
        $bytes[$i + 1] = $g
        $bytes[$i + 2] = $r
        $bytes[$i + 3] = [byte]$alpha
    }
}

[System.Runtime.InteropServices.Marshal]::Copy($bytes, 0, $data.Scan0, $bytes.Length)
$scaled.UnlockBits($data)

$tempPath = Join-Path $PSScriptRoot 'company-stamp.tmp.png'
$scaled.Save($tempPath, [System.Drawing.Imaging.ImageFormat]::Png)
$scaled.Dispose()

Move-Item $tempPath $outputPath -Force

Write-Output "Created $outputPath at ${targetWidth}x${targetHeight}px (${targetDpi} DPI, 69mm x 25mm)"
