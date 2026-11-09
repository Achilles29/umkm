Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

$BaseDir = 'C:\xampp\htdocs\umkm\docs'
$TemplatePath = Join-Path $BaseDir 'Revisi Penawaran Koperasi\KOP PT.ASJ.docx'
$OutputDir = Join-Path $BaseDir 'Penawaran UMKM PT ASJ'
New-Item -ItemType Directory -Force -Path $OutputDir | Out-Null

$DateText = '9 November 2026'
$Company = 'PT. Alam Sejahtera Jaya Computindo'
$Director = 'JOKO PRAYITNO'
$PackageName = 'Pengadaan Sistem Informasi Promosi dan Direktori UMKM Kabupaten Rembang'
$ProductName = 'Rembang UMKM Hub'
$HpsTotal = 70000000
$OfferTotal = 69600000

function Rgb([int]$r, [int]$g, [int]$b) { return $r + (256 * $g) + (65536 * $b) }
$Black = Rgb 0 0 0
$White = Rgb 255 255 255
$Green = Rgb 30 112 54
$GreenDark = Rgb 20 85 42
$GreenPale = Rgb 239 247 241
$Orange = Rgb 241 108 23
$Blue = Rgb 31 78 121
$BluePale = Rgb 234 242 248
$Gray = Rgb 217 217 217
$GrayPale = Rgb 247 247 247
$GrayMid = Rgb 235 235 235

function Rupiah([long]$value) {
    return 'Rp' + ([string]::Format([Globalization.CultureInfo]::GetCultureInfo('id-ID'), '{0:N0}', $value))
}

$Modules = @(
    [pscustomobject]@{No=1; Name='Portal publik dan identitas layanan'; Spec='Beranda responsif, profil Bidang UKM, banner, kanal kontak, kategori unggulan, informasi kegiatan, dan tautan layanan.'; Hps=2750000},
    [pscustomobject]@{No=2; Name='Akses pengunjung dan database member'; Spec='Masuk etalase dengan nomor HP dan nama, sesi terbatas, profil member, pencarian, pagination, dan pencatatan sumber kunjungan.'; Hps=2250000},
    [pscustomobject]@{No=3; Name='Etalase toko dan penemuan produk'; Spec='Daftar toko dan produk, pencarian, kategori, filter kecamatan/desa, toko unggulan, halaman detail, serta tampilan ponsel dan desktop.'; Hps=4250000},
    [pscustomobject]@{No=4; Name='Pengelolaan katalog produk'; Spec='Produk, harga dasar, varian, rincian, galeri foto, status draf, penarikan dari publikasi, dan pengajuan peninjauan.'; Hps=4500000},
    [pscustomobject]@{No=5; Name='Keranjang dan kontak WhatsApp'; Spec='Keranjang per toko, perubahan jumlah, template pesan, nomor referensi, kontak langsung, dan pencatatan minat tanpa memproses pembayaran.'; Hps=3000000},
    [pscustomobject]@{No=6; Name='Registri pelaku kegiatan dan toko'; Spec='Pemisahan pelaku, kegiatan usaha, dan profil toko; detail bertab, hubungan pemilik, NIB, komoditas, KBLI, arsip, dan pencarian data.'; Hps=5500000},
    [pscustomobject]@{No=7; Name='Klaim usaha dan pemulihan akun'; Spec='Pengajuan klaim dengan bukti privat, antrean keputusan, akses editor toko, pembaruan profil impor, dan pemulihan akun terkendali.'; Hps=3250000},
    [pscustomobject]@{No=8; Name='Verifikasi toko produk dan kategori'; Spec='Pemeriksaan NIB dan kunjungan, aktivasi toko, keputusan produk, pengajuan kategori, alasan keputusan, serta jejak perubahan.'; Hps=4250000},
    [pscustomobject]@{No=9; Name='GIS registri dan persetujuan lokasi'; Spec='Peta internal dan publik, klaster titik, filter, area kecamatan, pengajuan koordinat, pilihan titik persis/area/internal, dan persetujuan petugas.'; Hps=4500000},
    [pscustomobject]@{No=10; Name='Kegiatan dan studio formulir'; Spec='Daftar kegiatan, halaman detail, kuota, pendaftaran, status peserta, kehadiran dasar, serta studio komponen dan formulir berversi.'; Hps=4500000},
    [pscustomobject]@{No=11; Name='Tiket konsultasi fasilitasi dan pengaduan'; Spec='Nomor referensi, penugasan, status, catatan internal, balasan pemohon, riwayat tindak lanjut, dan pembatasan wilayah petugas.'; Hps=3750000},
    [pscustomobject]@{No=12; Name='Akun peran dan cakupan wilayah'; Spec='Superadmin, admin Dinas, admin kecamatan, pengelola UMKM, dan masyarakat; otorisasi per aksi dan pembatasan data kecamatan.'; Hps=3750000},
    [pscustomobject]@{No=13; Name='Master wilayah kategori KBLI dan skala'; Spec='Wilayah berjenjang, kategori etalase, KBLI 2025, skala usaha, komoditas, serta referensi yang dipakai lintas registri dan katalog.'; Hps=3250000},
    [pscustomobject]@{No=14; Name='QR promosi banner dan kontak'; Spec='QR umum/tambahan, pencatatan kunjungan, unduhan QR, pengaturan banner, nomor WhatsApp uji, dan informasi kontak pengelola.'; Hps=2550000},
    [pscustomobject]@{No=15; Name='Dashboard inbox laporan dan audit'; Spec='Ringkasan operasional, inbox notifikasi, daftar minat, filter dan rekap, status data, catatan sumber, serta audit tindakan penting.'; Hps=3750000}
)

$SupportItems = @(
    [pscustomobject]@{No=16; Group='B'; Name='Konfigurasi basis data dan migrasi'; Spec='Penerapan skema, migrasi, karakter utf8mb4, akun terbatas, pemeriksaan integritas, dan uji koneksi aplikasi.'; Unit='Paket'; Vol=1; Price=1400000; Identity='Penyiapan basis data'},
    [pscustomobject]@{No=17; Group='B'; Name='Konfigurasi aplikasi web dan HTTPS'; Spec='Penempatan aplikasi, izin direktori, rewrite URL, log, domain, HTTPS pada fasilitas yang tersedia, dan pembatasan berkas privat.'; Unit='Paket'; Vol=1; Price=1300000; Identity='Penyiapan server aplikasi'},
    [pscustomobject]@{No=18; Group='B'; Name='Pemetaan data awal dan master referensi'; Spec='Pemuatan master wilayah, kategori, KBLI, serta pemetaan data sumber UMKM yang disetujui tanpa keputusan identitas otomatis.'; Unit='Paket'; Vol=1; Price=1600000; Identity='Inisialisasi data'},
    [pscustomobject]@{No=19; Group='B'; Name='Versi rilis dan rancangan pencadangan'; Spec='Repositori Git, nomor versi, catatan perubahan, prosedur rilis, serta rancangan cadangan basis data dan berkas.'; Unit='Paket'; Vol=1; Price=900000; Identity='Pengelolaan rilis'},
    [pscustomobject]@{No=20; Group='C'; Name='Helpdesk penggunaan'; Spec='Pencatatan pertanyaan dan kendala, konsultasi penggunaan, triase, dan tindak lanjut selama 12 bulan.'; Unit='Bulan'; Vol=12; Price=250000; Identity='Layanan dukungan'},
    [pscustomobject]@{No=21; Group='C'; Name='Pemeliharaan aplikasi'; Spec='Perbaikan bug dan pembaruan yang masih berada dalam lingkup fungsi yang diserahkan, disertai catatan pemeliharaan.'; Unit='Bulan'; Vol=12; Price=225000; Identity='Layanan pemeliharaan'},
    [pscustomobject]@{No=22; Group='C'; Name='Orientasi pengelola'; Spec='Dua sesi untuk admin Dinas/admin kecamatan dan pengelola UMKM, meliputi alur data, katalog, verifikasi, layanan, dan laporan.'; Unit='Sesi'; Vol=2; Price=750000; Identity='Orientasi pengguna'},
    [pscustomobject]@{No=23; Group='D'; Name='Panduan pengguna berbasis peran'; Spec='Panduan ringkas bagi administrator, petugas wilayah, dan pengelola toko, termasuk tindakan umum dan penanganan kendala.'; Unit='Dokumen'; Vol=1; Price=450000; Identity='Dokumentasi pengguna'},
    [pscustomobject]@{No=24; Group='D'; Name='Kamus data dan dokumentasi sistem'; Spec='Struktur data utama, relasi, status, konfigurasi, dependensi, prosedur pemasangan, pembaruan, dan pencadangan.'; Unit='Dokumen'; Vol=1; Price=450000; Identity='Dokumentasi teknis'},
    [pscustomobject]@{No=25; Group='D'; Name='Pemeriksaan fungsi dan hak akses'; Spec='Skenario uji fungsi inti, pembatasan peran/wilayah, berkas privat, alur verifikasi, dan daftar hasil pemeriksaan.'; Unit='Paket'; Vol=1; Price=550000; Identity='Uji penerimaan'},
    [pscustomobject]@{No=26; Group='D'; Name='Paket serah terima'; Spec='Daftar versi, salinan konfigurasi yang disepakati, daftar dokumen, catatan akses administrasi, dan berita acara.'; Unit='Paket'; Vol=1; Price=350000; Identity='Serah terima'}
)

function Set-PageSystem($doc) {
    foreach ($section in $doc.Sections) {
        $ps = $section.PageSetup
        $ps.PageWidth = 595.3
        $ps.PageHeight = 935.5
        $ps.Orientation = 0
        $ps.LeftMargin = 56.7
        $ps.RightMargin = 56.7
        $ps.TopMargin = 56.7
        $ps.BottomMargin = 51
        $ps.HeaderDistance = 22.7
        $ps.FooterDistance = 22.7
    }
}

function Set-Styles($doc) {
    $normal = $doc.Styles.Item('Normal')
    $normal.Font.Name = 'Arial'; $normal.Font.Size = 10.5; $normal.Font.Color = $Black
    $normal.ParagraphFormat.SpaceAfter = 5
    $normal.ParagraphFormat.LineSpacingRule = 0
    $title = $doc.Styles.Item('Title')
    $title.Font.Name = 'Arial'; $title.Font.Size = 15; $title.Font.Bold = -1; $title.Font.Color = $Black
    $title.ParagraphFormat.Alignment = 1; $title.ParagraphFormat.SpaceAfter = 5
    $h1 = $doc.Styles.Item('Heading 1')
    $h1.Font.Name = 'Arial'; $h1.Font.Size = 12; $h1.Font.Bold = -1; $h1.Font.Color = $Black
    $h1.ParagraphFormat.SpaceBefore = 10; $h1.ParagraphFormat.SpaceAfter = 4; $h1.ParagraphFormat.KeepWithNext = -1
    $h2 = $doc.Styles.Item('Heading 2')
    $h2.Font.Name = 'Arial'; $h2.Font.Size = 11; $h2.Font.Bold = -1; $h2.Font.Color = $Black
    $h2.ParagraphFormat.SpaceBefore = 7; $h2.ParagraphFormat.SpaceAfter = 3; $h2.ParagraphFormat.KeepWithNext = -1
}

function Add-PageField($footer) {
    $footer.Range.Text = 'Halaman '
    $footer.Range.Font.Name = 'Arial'; $footer.Range.Font.Size = 8.5
    $footer.Range.ParagraphFormat.Alignment = 2
    $r = $footer.Range.Duplicate
    $r.Collapse(0)
    $footer.Range.Fields.Add($r, 33) | Out-Null
}

function Configure-HeaderFooter($doc, [string]$runningHeader, [bool]$letterheadFirst) {
    foreach ($section in $doc.Sections) {
        $section.PageSetup.DifferentFirstPageHeaderFooter = $(if ($letterheadFirst) {-1} else {0})
        $primary = $section.Headers.Item(1)
        $primary.Range.Text = $runningHeader
        $primary.Range.Font.Name = 'Arial'; $primary.Range.Font.Size = 8.5; $primary.Range.Font.Color = $Black
        $primary.Range.ParagraphFormat.Alignment = 2
        if ($letterheadFirst) { $section.Headers.Item(2).Range.Text = '' }
        Add-PageField $section.Footers.Item(1)
        if ($letterheadFirst) { Add-PageField $section.Footers.Item(2) }
    }
}

function Add-Paragraph($doc, [string]$text, [string]$style='Normal', [int]$align=0, [double]$before=0, [double]$after=5, [bool]$bold=$false, [double]$size=0) {
    $last = $doc.Paragraphs.Item($doc.Paragraphs.Count)
    $lastText = ($last.Range.Text -replace '[\r\a]+$','').Trim()
    if ($lastText.Length -eq 0 -and -not ($doc.Paragraphs.Count -eq 1 -and $doc.Shapes.Count -gt 0)) {
        $p = $last
    } else {
        $insert = $doc.Range($doc.Content.End - 1, $doc.Content.End - 1)
        $insert.InsertParagraphAfter()
        $p = $doc.Paragraphs.Item($doc.Paragraphs.Count)
    }
    $p.Range.Text = $text
    try { $p.Range.Style = $style } catch { $p.Range.Style = 'Normal' }
    $p.Alignment = $align
    $p.Format.SpaceBefore = $before
    $p.Format.SpaceAfter = $after
    if ($bold) { $p.Range.Font.Bold = -1 }
    if ($size -gt 0) { $p.Range.Font.Size = $size }
    return $p
}

function Add-Blank($doc, [double]$after=3) {
    $insert = $doc.Range($doc.Content.End - 1, $doc.Content.End - 1)
    $insert.InsertParagraphAfter()
    $p = $doc.Paragraphs.Item($doc.Paragraphs.Count); $p.Range.Text=''; $p.Format.SpaceAfter=$after; return $p
}

function Add-PageBreak($doc) {
    $r = $doc.Range($doc.Content.End - 1, $doc.Content.End - 1)
    $r.InsertBreak(7)
}

function Set-Cell($cell, [string]$text, [int]$align, [double]$size, [bool]$bold, [int]$fill, [int]$fontColor) {
    $cell.Range.Text = $text
    $cell.Range.Font.Name = 'Arial'; $cell.Range.Font.Size = $size; $cell.Range.Font.Color = $fontColor
    $cell.Range.Font.Bold = $(if ($bold) {-1} else {0})
    $cell.Range.ParagraphFormat.Alignment = $align
    $cell.Range.ParagraphFormat.SpaceBefore = 0; $cell.Range.ParagraphFormat.SpaceAfter = 0
    $cell.VerticalAlignment = 1
    $cell.Shading.BackgroundPatternColor = $fill
}

function Add-Table($doc, $rows, [double[]]$widths, [int[]]$aligns, [int]$accent, [int]$pale, [double]$fontSize=9.5) {
    $cols = $widths.Count
    $r = $doc.Range($doc.Content.End - 1, $doc.Content.End - 1)
    $table = $doc.Tables.Add($r, $rows.Count, $cols)
    $table.AllowAutoFit = 0
    $table.TopPadding = 4; $table.BottomPadding = 4; $table.LeftPadding = 5; $table.RightPadding = 5
    foreach ($borderId in @(-1,-2,-3,-4,-5,-6)) {
        try {
            $table.Borders.Item($borderId).LineStyle = 1
            $table.Borders.Item($borderId).Color = $Gray
        } catch {}
    }
    for ($c=1; $c -le $cols; $c++) { $table.Columns.Item($c).PreferredWidthType = 3; $table.Columns.Item($c).PreferredWidth = $widths[$c-1] }
    $rowIndex = 0
    foreach ($spec in $rows) {
        $rowIndex++
        $kind = $spec.Kind
        if ($kind -eq 'group') {
            $table.Cell($rowIndex,1).Merge($table.Cell($rowIndex,$cols))
            Set-Cell $table.Cell($rowIndex,1) $spec.Cells[0] 0 $fontSize $true $pale $Black
            $table.Rows.Item($rowIndex).Range.ParagraphFormat.KeepWithNext = -1
            continue
        }
        if ($kind -eq 'total') {
            $mergeTo = $spec.MergeTo
            if ($mergeTo -gt 1) { $table.Cell($rowIndex,1).Merge($table.Cell($rowIndex,$mergeTo)) }
            Set-Cell $table.Cell($rowIndex,1) $spec.Cells[0] 2 $fontSize $true $pale $Black
            $remaining = $cols - $mergeTo
            for ($k=1; $k -le $remaining; $k++) { Set-Cell $table.Cell($rowIndex,1+$k) $spec.Cells[$k] 2 $fontSize $true $pale $Black }
            continue
        }
        for ($c=1; $c -le $cols; $c++) {
            $fill = if ($kind -eq 'header') {$accent} elseif (($rowIndex % 2) -eq 0) {$pale} else {$White}
            $fontColor = if ($kind -eq 'header') {$White} else {$Black}
            Set-Cell $table.Cell($rowIndex,$c) ([string]$spec.Cells[$c-1]) $aligns[$c-1] $fontSize ($kind -eq 'header') $fill $fontColor
        }
        if ($kind -eq 'header') { $table.Rows.Item($rowIndex).HeadingFormat = -1; $table.Rows.Item($rowIndex).Range.ParagraphFormat.KeepWithNext = -1 }
    }
    $table.Range.ParagraphFormat.WidowControl = -1
    $table.Rows.AllowBreakAcrossPages = -1
    $end = $doc.Range($doc.Content.End - 1, $doc.Content.End - 1)
    $end.InsertParagraphAfter()
    return $table
}

function Add-Signature($doc, [string]$organization, [string]$name, [string]$role) {
    $rows = @(
        [pscustomobject]@{Kind='body'; Cells=@('',('Rembang, ' + $DateText))},
        [pscustomobject]@{Kind='body'; Cells=@('',$organization)},
        [pscustomobject]@{Kind='body'; Cells=@('','')},
        [pscustomobject]@{Kind='body'; Cells=@('','')},
        [pscustomobject]@{Kind='body'; Cells=@('',$name)},
        [pscustomobject]@{Kind='body'; Cells=@('',$role)}
    )
    $t = Add-Table $doc $rows @(240,240) @(0,1) $White $White 10.5
    $t.Borders.Enable = 0
    $t.Cell(5,2).Range.Font.Bold = -1
    return $t
}

function New-LetterheadDocument($word, [string]$outputPath) {
    $doc = $word.Documents.Open($TemplatePath, $false, $false)
    $doc.SaveAs2($outputPath, 16)
    Set-PageSystem $doc
    Set-Styles $doc
    return $doc
}

function New-BlankDocument($word, [string]$outputPath) {
    $doc = $word.Documents.Add()
    $doc.SaveAs2($outputPath, 16)
    Set-PageSystem $doc
    Set-Styles $doc
    return $doc
}

function Build-OfferLetter($word) {
    $path = Join-Path $OutputDir '01. Penawaran Sistem Informasi UMKM PT ASJ.docx'
    $doc = New-LetterheadDocument $word $path
    Configure-HeaderFooter $doc 'Penawaran Rembang UMKM Hub' $true

    Add-Paragraph $doc 'SURAT PENAWARAN PENGADAAN SISTEM INFORMASI UMKM' 'Title' 1 55 3 | Out-Null
    Add-Paragraph $doc 'Rembang UMKM Hub' 'Normal' 1 0 10 $true 11.5 | Out-Null
    Add-Paragraph $doc ('Rembang, ' + $DateText) 'Normal' 2 0 7 | Out-Null
    Add-Paragraph $doc ('Perihal: Penawaran ' + $PackageName) 'Normal' 0 0 1 | Out-Null
    Add-Paragraph $doc 'Lampiran: Formulir Penawaran Teknis dan Spesifikasi Teknis' 'Normal' 0 0 8 | Out-Null
    Add-Paragraph $doc 'Yth. Kepala Dinas Perdagangan, Koperasi dan UKM Kabupaten Rembang' 'Normal' 0 0 1 | Out-Null
    Add-Paragraph $doc 'di Rembang' 'Normal' 0 0 8 | Out-Null
    Add-Paragraph $doc 'Dengan hormat,' 'Normal' 0 0 6 | Out-Null
    Add-Paragraph $doc ('PT. Alam Sejahtera Jaya Computindo menyampaikan penawaran untuk ' + $PackageName + '. Solusi yang kami tawarkan adalah ' + $ProductName + ', yaitu aplikasi web yang menghubungkan promosi usaha lokal, registri UMKM, katalog produk, kegiatan, layanan Dinas, dan peta dalam satu ruang kerja berbasis peran.') | Out-Null
    Add-Paragraph $doc ('Total penawaran sebesar ' + (Rupiah $OfferTotal) + ' (enam puluh sembilan juta enam ratus ribu rupiah), termasuk pelaksanaan seluruh keluaran dalam ruang lingkup serta pajak yang berlaku. Nilai tersebut berada di bawah pagu dan HPS sebesar Rp70.000.000.') | Out-Null
    Add-Paragraph $doc 'Penawaran mencakup 15 kelompok fungsi aplikasi, penyiapan basis data dan server, pemetaan data awal, pengelolaan rilis, dukungan selama 12 bulan, dua sesi orientasi, dokumentasi, pemeriksaan fungsi, dan paket serah terima.' | Out-Null
    Add-Paragraph $doc 'Aplikasi berfungsi sebagai media promosi dan penghubung transaksi. Pemesanan akhir, pembayaran, dan pengiriman disepakati langsung antara pembeli dan pelaku UMKM di luar sistem. Batas layanan, ketergantungan infrastruktur, serta kriteria penerimaan dijelaskan dalam Spesifikasi Teknis.' | Out-Null
    Add-Paragraph $doc 'Kami berharap penawaran ini dapat menjadi dasar pembahasan pengadaan. Atas perhatian dan kesempatan yang diberikan, kami mengucapkan terima kasih.' | Out-Null
    Add-Signature $doc $Company $Director 'Direktur' | Out-Null

    Add-PageBreak $doc
    Add-Paragraph $doc 'Ikhtisar Solusi dan Hasil yang Diserahkan' 'Heading 1' 0 0 5 | Out-Null
    Add-Paragraph $doc 'Rembang UMKM Hub menempatkan satu data usaha sebagai rujukan bagi katalog, lokasi, kegiatan, layanan, dan proses verifikasi. Pemisahan pelaku, kegiatan usaha, dan toko menjaga agar satu orang dapat memiliki lebih dari satu kegiatan tanpa menggandakan identitas.' | Out-Null
    Add-Paragraph $doc 'Hasil utama yang diserahkan adalah aplikasi yang siap dikonfigurasi pada lingkungan instansi, basis data dan migrasi, master referensi, akun sesuai peran, panduan operasional, dokumentasi teknis, serta catatan hasil pemeriksaan bersama.' | Out-Null
    Add-Paragraph $doc 'Pengguna dan ruang kerjanya' 'Heading 2' | Out-Null
    $userRows = @(
        [pscustomobject]@{Kind='header'; Cells=@('Pengguna','Ruang kerja dan hasil yang diperoleh')},
        [pscustomobject]@{Kind='body'; Cells=@('Masyarakat','Menemukan toko dan produk, menyusun keranjang per toko, menghubungi penjual melalui WhatsApp, serta melihat kegiatan dan peta yang dipublikasikan.')},
        [pscustomobject]@{Kind='body'; Cells=@('Pengelola UMKM','Mengelola profil toko dan produk, mengajukan kategori atau lokasi, memantau peninjauan, mendaftar kegiatan, dan menggunakan layanan Dinas.')},
        [pscustomobject]@{Kind='body'; Cells=@('Admin kecamatan','Mengelola data pelaku dan kegiatan di wilayah tugas, menyiapkan toko dan produk draf, serta menangani tiket wilayah tanpa mengambil keputusan verifikasi Dinas.')},
        [pscustomobject]@{Kind='body'; Cells=@('Admin Dinas','Memeriksa NIB, toko, produk, kategori dan lokasi; mengelola kegiatan, QR, layanan, registri, master data, dan laporan lintas kecamatan.')},
        [pscustomobject]@{Kind='body'; Cells=@('Superadmin','Mengelola seluruh modul, pengaturan, akun, peran, cakupan wilayah, dan pemulihan akses dengan pencatatan tindakan penting.')}
    )
    Add-Table $doc $userRows @(115,365) @(0,0) $GreenDark $GreenPale 9.5 | Out-Null
    Add-Paragraph $doc 'Alur data utama' 'Heading 2' | Out-Null
    Add-Paragraph $doc 'Data sumber dipetakan ke registri kanonis. Petugas meninjau hubungan pelaku dan kegiatan, melengkapi NIB dan klasifikasi, lalu mengaktifkan toko setelah syarat dan hasil kunjungan dicatat. Produk, kategori, dan lokasi publik mengikuti keputusan masing-masing; informasi yang belum disetujui tetap draf atau internal.' | Out-Null
    Add-Paragraph $doc 'Kontak dari produk, toko, atau keranjang menghasilkan nomor referensi dan pesan per toko. Catatan tersebut menunjukkan minat pengunjung, bukan bukti penjualan. Data interaksi dapat digunakan sebagai dasar laporan setelah definisi indikator disepakati.' | Out-Null

    Add-PageBreak $doc
    Add-Paragraph $doc 'Lingkup Pekerjaan dan Keluaran' 'Heading 1' 0 0 5 | Out-Null
    $scopeRows = @(
        [pscustomobject]@{Kind='header'; Cells=@('Kelompok','Volume','Keluaran utama')},
        [pscustomobject]@{Kind='body'; Cells=@('Fungsi aplikasi','15 kelompok','Portal dan member, etalase dan katalog, keranjang WhatsApp, registri UMKM, klaim dan verifikasi, GIS, kegiatan, tiket layanan, hak akses, master data, promosi, dashboard, inbox, laporan, dan audit.')},
        [pscustomobject]@{Kind='body'; Cells=@('Implementasi teknis','4 paket','Basis data dan migrasi, aplikasi web dan HTTPS, pemetaan data awal, serta versi rilis dan rancangan pencadangan.')},
        [pscustomobject]@{Kind='body'; Cells=@('Dukungan','12 bulan','Helpdesk dan pemeliharaan untuk fungsi yang diserahkan, dengan kanal dan eskalasi yang ditetapkan dalam kontrak.')},
        [pscustomobject]@{Kind='body'; Cells=@('Orientasi','2 sesi','Sesi untuk administrator/petugas wilayah dan sesi untuk pengelola UMKM yang ditunjuk.')},
        [pscustomobject]@{Kind='body'; Cells=@('Dokumentasi dan penerimaan','4 keluaran','Panduan pengguna, dokumentasi sistem, hasil pemeriksaan fungsi/hak akses, dan paket serah terima.')}
    )
    Add-Table $doc $scopeRows @(115,65,300) @(0,1,0) $GreenDark $GreenPale 9.5 | Out-Null
    Add-Paragraph $doc 'Tahapan pelaksanaan' 'Heading 2' | Out-Null
    foreach ($line in @(
        '1  Konfirmasi ruang lingkup, lingkungan server, data awal, penanggung jawab, dan matriks akses.',
        '2  Pemasangan aplikasi, basis data, migrasi, konfigurasi berkas privat, domain, dan HTTPS pada fasilitas yang tersedia.',
        '3  Pemetaan master referensi dan data sumber, termasuk penandaan data yang masih memerlukan pemeriksaan petugas.',
        '4  Pemeriksaan fungsi dan hak akses melalui skenario yang disepakati, kemudian perbaikan temuan dalam ruang lingkup.',
        '5  Orientasi pengguna, penyerahan panduan, dokumentasi, paket rilis, dan berita acara.',
        '6  Helpdesk dan pemeliharaan selama 12 bulan sejak tanggal mulai layanan yang ditetapkan dalam kontrak.'
    )) { Add-Paragraph $doc $line 'Normal' 0 0 3 | Out-Null }
    Add-Paragraph $doc 'Jadwal rinci disusun setelah kesiapan server, domain, akses, data, dan penanggung jawab dikonfirmasi oleh para pihak.' 'Normal' 0 3 5 | Out-Null

    Add-PageBreak $doc
    Add-Paragraph $doc 'Ketentuan Ruang Lingkup' 'Heading 1' 0 0 5 | Out-Null
    Add-Paragraph $doc 'Batas fungsi transaksi' 'Heading 2' | Out-Null
    Add-Paragraph $doc 'Keranjang dan WhatsApp digunakan untuk menyampaikan minat. Sistem tidak menyediakan pembayaran, rekening bersama, pengiriman, jaminan stok, atau penyelesaian sengketa transaksi.' | Out-Null
    Add-Paragraph $doc 'Data publik dan persetujuan' 'Heading 2' | Out-Null
    Add-Paragraph $doc 'Nomor HP pengunjung belum dianggap terverifikasi sehingga sesi publik tidak memberi akses ke data sensitif. Titik lokasi persis hanya dapat diterbitkan setelah persetujuan pemilik dan keputusan petugas. Dokumen klaim, NIB, dan pemulihan akun tetap berada pada penyimpanan privat.' | Out-Null
    Add-Paragraph $doc 'Data awal dan integrasi' 'Heading 2' | Out-Null
    Add-Paragraph $doc 'Pemetaan awal menggunakan data yang disediakan dan disetujui instansi. Rekonsiliasi identitas berskala besar, digitalisasi berkas historis, verifikasi lapangan, integrasi API eksternal, SSO, OTP, gateway pesan, pembayaran, serta integrasi baru merupakan ruang lingkup terpisah.' | Out-Null
    Add-Paragraph $doc 'Infrastruktur dan layanan pihak ketiga' 'Heading 2' | Out-Null
    Add-Paragraph $doc 'Server atau hosting, domain, sertifikat berbayar, koneksi internet, perangkat keras, penyimpanan cadangan eksternal, biaya WhatsApp atau SMS, dan layanan pihak ketiga disediakan instansi atau dibahas terpisah.' | Out-Null
    Add-Paragraph $doc 'Harga penawaran' 'Heading 2' | Out-Null
    $priceRows = @(
        [pscustomobject]@{Kind='header'; Cells=@('No','Kelompok pekerjaan','Jumlah')},
        [pscustomobject]@{Kind='body'; Cells=@('1','Fungsi aplikasi dan alur bisnis',(Rupiah 55500000))},
        [pscustomobject]@{Kind='body'; Cells=@('2','Implementasi teknis dan data awal',(Rupiah 5150000))},
        [pscustomobject]@{Kind='body'; Cells=@('3','Dukungan dan orientasi',(Rupiah 7150000))},
        [pscustomobject]@{Kind='body'; Cells=@('4','Dokumentasi pemeriksaan dan serah terima',(Rupiah 1800000))},
        [pscustomobject]@{Kind='total'; MergeTo=2; Cells=@('TOTAL PENAWARAN',(Rupiah $OfferTotal))}
    )
    Add-Table $doc $priceRows @(35,315,130) @(1,0,2) $GreenDark $GreenPale 10 | Out-Null
    Add-Paragraph $doc 'Terbilang: Enam puluh sembilan juta enam ratus ribu rupiah.' 'Normal' 0 5 5 $true | Out-Null
    Add-Paragraph $doc 'Nilai tersebut telah memperhitungkan pajak yang berlaku. Dasar pengenaan dan pajak pada dokumen penagihan mengikuti status perpajakan penyedia tanpa menambah total penawaran.' | Out-Null
    Add-Paragraph $doc 'Dokumen yang dibaca bersama' 'Heading 2' | Out-Null
    Add-Paragraph $doc 'Surat penawaran ini, Formulir Penawaran Teknis, daftar kuantitas dan harga, serta Spesifikasi Teknis membentuk satu kesatuan ruang lingkup.' | Out-Null

    $doc.Save()
    $doc.Close(0)
    return $path
}

function Build-Hps($word) {
    $path = Join-Path $OutputDir '02. HPS Sistem Informasi UMKM.docx'
    $doc = New-BlankDocument $word $path
    Configure-HeaderFooter $doc 'Harga Perkiraan Sendiri Sistem Informasi UMKM' $false
    Add-Paragraph $doc 'HARGA PERKIRAAN SENDIRI' 'Title' 1 8 2 | Out-Null
    Add-Paragraph $doc $PackageName 'Normal' 1 0 10 $true 11.5 | Out-Null
    Add-Paragraph $doc 'Dinas Perdagangan, Koperasi dan UKM Kabupaten Rembang' | Out-Null
    Add-Paragraph $doc ('Tahun Anggaran 2026  |  Tanggal Penyusunan ' + $DateText) 'Normal' 0 0 2 | Out-Null
    Add-Paragraph $doc ('Pagu Anggaran ' + (Rupiah $HpsTotal) + '  |  Total HPS ' + (Rupiah $HpsTotal)) 'Normal' 0 0 7 $true | Out-Null
    Add-Paragraph $doc 'Perhitungan dibagi menjadi fungsi aplikasi, implementasi teknis dan data, dukungan pengguna, serta dokumentasi dan penerimaan. Setiap harga sudah memuat biaya pelaksanaan pada komponen terkait dan pajak yang berlaku.' | Out-Null
    $rows = @([pscustomobject]@{Kind='header'; Cells=@('No','Uraian pekerjaan','Satuan','Vol','Harga satuan','Jumlah')},[pscustomobject]@{Kind='group'; Cells=@('A  FUNGSI APLIKASI DAN ALUR BISNIS')})
    foreach ($m in $Modules) { $rows += [pscustomobject]@{Kind='body'; Cells=@([string]$m.No,$m.Name,'Modul','1',(Rupiah $m.Hps),(Rupiah $m.Hps))} }
    $rows += [pscustomobject]@{Kind='total'; MergeTo=5; Cells=@('Subtotal fungsi aplikasi',(Rupiah (($Modules | Measure-Object Hps -Sum).Sum)))}
    Add-Table $doc $rows @(27,190,50,34,84,95) @(1,0,1,1,2,2) $Blue $BluePale 8.7 | Out-Null

    Add-PageBreak $doc
    Add-Paragraph $doc 'Rincian HPS Implementasi dan Layanan' 'Heading 1' 0 0 5 | Out-Null
    $rows2 = @([pscustomobject]@{Kind='header'; Cells=@('No','Uraian pekerjaan','Satuan','Vol','Harga satuan','Jumlah')})
    $groups = @{B='B  IMPLEMENTASI TEKNIS DAN DATA';C='C  DUKUNGAN DAN ORIENTASI';D='D  DOKUMENTASI DAN PENERIMAAN'}
    $last=''
    foreach ($i in $SupportItems) {
        if ($i.Group -ne $last) { $rows2 += [pscustomobject]@{Kind='group'; Cells=@($groups[$i.Group])}; $last=$i.Group }
        $amount = [long]$i.Vol * [long]$i.Price
        $rows2 += [pscustomobject]@{Kind='body'; Cells=@([string]$i.No,$i.Name,$i.Unit,[string]$i.Vol,(Rupiah $i.Price),(Rupiah $amount))}
    }
    $rows2 += [pscustomobject]@{Kind='total'; MergeTo=5; Cells=@('Subtotal implementasi layanan dan dokumentasi',(Rupiah (($SupportItems | ForEach-Object {[long]$_.Vol * [long]$_.Price} | Measure-Object -Sum).Sum)))}
    Add-Table $doc $rows2 @(27,190,50,34,84,95) @(1,0,1,1,2,2) $Blue $BluePale 8.7 | Out-Null

    Add-PageBreak $doc
    Add-Paragraph $doc 'Rekapitulasi HPS' 'Heading 1' 0 0 5 | Out-Null
    $recap = @(
        [pscustomobject]@{Kind='header'; Cells=@('No','Kelompok pekerjaan','Jumlah')},
        [pscustomobject]@{Kind='body'; Cells=@('1','Fungsi aplikasi dan alur bisnis',(Rupiah 55800000))},
        [pscustomobject]@{Kind='body'; Cells=@('2','Implementasi teknis dan data',(Rupiah 5200000))},
        [pscustomobject]@{Kind='body'; Cells=@('3','Dukungan dan orientasi',(Rupiah 7200000))},
        [pscustomobject]@{Kind='body'; Cells=@('4','Dokumentasi dan penerimaan',(Rupiah 1800000))},
        [pscustomobject]@{Kind='total'; MergeTo=2; Cells=@('TOTAL HPS',(Rupiah $HpsTotal))}
    )
    Add-Table $doc $recap @(35,315,130) @(1,0,2) $Blue $BluePale 10 | Out-Null
    Add-Paragraph $doc 'Terbilang: Tujuh puluh juta rupiah.' 'Normal' 0 5 8 $true | Out-Null
    Add-Paragraph $doc 'Dasar penyusunan' 'Heading 2' | Out-Null
    Add-Paragraph $doc 'Rincian disusun dari fungsi yang telah tersedia pada proyek Rembang UMKM Hub, volume dukungan 12 bulan, dua sesi orientasi, dan kebutuhan implementasi aplikasi web beserta data awal. Alokasi memperhitungkan kerumitan katalog, registri kanonis, verifikasi, GIS, event, layanan, otorisasi wilayah, dokumen privat, dan audit.' | Out-Null
    Add-Paragraph $doc 'Harga satuan merupakan perkiraan biaya per komponen. Bukti pembanding dan penetapan kewajaran harga dilengkapi oleh pejabat yang berwenang dalam proses penetapan HPS.' | Out-Null
    Add-Paragraph $doc 'Total telah memperhitungkan pajak yang berlaku. Pemisahan dasar pengenaan dan pajak mengikuti status penyedia dan dokumen perpajakan tanpa melampaui Rp70.000.000.' | Out-Null
    Add-Paragraph $doc 'Hosting, domain, sertifikat berbayar, perangkat keras, internet, gateway pesan, biaya layanan pihak ketiga, verifikasi lapangan, pembersihan data berskala besar, serta integrasi baru tidak termasuk dalam rincian ini.' | Out-Null
    Add-Paragraph $doc 'Total HPS sama dengan pagu anggaran. Nilai kontrak mengikuti hasil proses pengadaan.' | Out-Null
    Add-Signature $doc 'Pejabat Pembuat Komitmen' '(........................................)' 'NIP. ................................' | Out-Null
    $doc.Save(); $doc.Close(0); return $path
}

function TechnicalRows($items, [string]$groupTitle) {
    $rows = @(
        [pscustomobject]@{Kind='header'; Cells=@('No','Uraian pekerjaan','Spesifikasi ringkas','Satuan','Vol','Identitas yang ditawarkan')},
        [pscustomobject]@{Kind='group'; Cells=@($groupTitle)}
    )
    foreach ($i in $items) {
        if ($i.PSObject.Properties.Name -contains 'Hps') {
            $rows += [pscustomobject]@{Kind='body'; Cells=@([string]$i.No,$i.Name,$i.Spec,'Modul','1',$ProductName)}
        } else {
            $rows += [pscustomobject]@{Kind='body'; Cells=@([string]$i.No,$i.Name,$i.Spec,$i.Unit,[string]$i.Vol,$i.Identity)}
        }
    }
    return $rows
}

function Build-TechnicalForm($word) {
    $path = Join-Path $OutputDir '03. Formulir Penawaran Teknis UMKM PT ASJ.docx'
    $doc = New-LetterheadDocument $word $path
    Configure-HeaderFooter $doc 'Formulir Penawaran Teknis Rembang UMKM Hub' $true
    Add-Paragraph $doc 'FORMULIR PENAWARAN TEKNIS' 'Title' 1 55 3 | Out-Null
    Add-Paragraph $doc $PackageName 'Normal' 1 0 10 $true 11.5 | Out-Null
    $page1 = TechnicalRows ($Modules | Select-Object -First 8) 'A  FUNGSI APLIKASI DAN ALUR BISNIS'
    Add-Table $doc $page1 @(25,105,170,48,32,100) @(1,0,0,1,1,0) $GreenDark $GreenPale 8.2 | Out-Null

    Add-PageBreak $doc
    Add-Paragraph $doc 'Formulir Penawaran Teknis Lanjutan' 'Heading 1' 0 0 5 | Out-Null
    $page2items = @($Modules | Select-Object -Skip 8) + @($SupportItems | Where-Object {$_.Group -eq 'B'})
    $rows2 = @([pscustomobject]@{Kind='header'; Cells=@('No','Uraian pekerjaan','Spesifikasi ringkas','Satuan','Vol','Identitas yang ditawarkan')})
    $rows2 += [pscustomobject]@{Kind='group'; Cells=@('A  FUNGSI APLIKASI DAN ALUR BISNIS LANJUTAN')}
    foreach ($i in ($Modules | Select-Object -Skip 8)) { $rows2 += [pscustomobject]@{Kind='body'; Cells=@([string]$i.No,$i.Name,$i.Spec,'Modul','1',$ProductName)} }
    $rows2 += [pscustomobject]@{Kind='group'; Cells=@('B  IMPLEMENTASI TEKNIS DAN DATA')}
    foreach ($i in ($SupportItems | Where-Object {$_.Group -eq 'B'})) { $rows2 += [pscustomobject]@{Kind='body'; Cells=@([string]$i.No,$i.Name,$i.Spec,$i.Unit,[string]$i.Vol,$i.Identity)} }
    Add-Table $doc $rows2 @(25,105,170,48,32,100) @(1,0,0,1,1,0) $GreenDark $GreenPale 8.2 | Out-Null

    Add-PageBreak $doc
    Add-Paragraph $doc 'Dukungan Dokumentasi dan Harga Penawaran' 'Heading 1' 0 0 5 | Out-Null
    $rows3 = @([pscustomobject]@{Kind='header'; Cells=@('No','Uraian pekerjaan','Spesifikasi ringkas','Satuan','Vol','Identitas yang ditawarkan')})
    foreach ($g in @('C','D')) {
        $rows3 += [pscustomobject]@{Kind='group'; Cells=@($(if ($g -eq 'C') {'C  DUKUNGAN DAN ORIENTASI'} else {'D  DOKUMENTASI DAN PENERIMAAN'}))}
        foreach ($i in ($SupportItems | Where-Object {$_.Group -eq $g})) { $rows3 += [pscustomobject]@{Kind='body'; Cells=@([string]$i.No,$i.Name,$i.Spec,$i.Unit,[string]$i.Vol,$i.Identity)} }
    }
    Add-Table $doc $rows3 @(25,105,170,48,32,100) @(1,0,0,1,1,0) $GreenDark $GreenPale 8.2 | Out-Null
    Add-Paragraph $doc 'Daftar kuantitas dan harga penawaran' 'Heading 2' | Out-Null
    $price = @(
        [pscustomobject]@{Kind='header'; Cells=@('No','Kelompok','Satuan','Vol','Harga satuan','Jumlah')},
        [pscustomobject]@{Kind='body'; Cells=@('1','Fungsi aplikasi dan alur bisnis','Paket','1',(Rupiah 55500000),(Rupiah 55500000))},
        [pscustomobject]@{Kind='body'; Cells=@('2','Implementasi teknis dan data awal','Paket','1',(Rupiah 5150000),(Rupiah 5150000))},
        [pscustomobject]@{Kind='body'; Cells=@('3','Dukungan dan orientasi','Paket','1',(Rupiah 7150000),(Rupiah 7150000))},
        [pscustomobject]@{Kind='body'; Cells=@('4','Dokumentasi pemeriksaan dan serah terima','Paket','1',(Rupiah 1800000),(Rupiah 1800000))},
        [pscustomobject]@{Kind='total'; MergeTo=5; Cells=@('JUMLAH TOTAL PENAWARAN',(Rupiah $OfferTotal))}
    )
    Add-Table $doc $price @(25,190,55,35,85,90) @(1,0,1,1,2,2) $GreenDark $GreenPale 8.8 | Out-Null
    Add-Paragraph $doc 'Terbilang: Enam puluh sembilan juta enam ratus ribu rupiah.' 'Normal' 0 4 4 $true | Out-Null
    Add-Paragraph $doc 'Harga meliputi seluruh komponen pada formulir ini dan pajak yang berlaku. Rincian fungsi, batas layanan, infrastruktur yang disediakan instansi, serta kriteria penerimaan mengikuti Spesifikasi Teknis.' 'Normal' 0 0 2 | Out-Null
    Add-Signature $doc $Company $Director 'Direktur' | Out-Null
    $doc.Save(); $doc.Close(0); return $path
}

function Add-SpecItem($doc, [int]$number, [string]$title, [string]$body) {
    Add-Paragraph $doc ($number.ToString() + '  ' + $title) 'Heading 2' 0 6 2 | Out-Null
    Add-Paragraph $doc $body 'Normal' 0 0 4 | Out-Null
}

function Build-Specification($word) {
    $path = Join-Path $OutputDir '04. Spesifikasi Teknis Sistem Informasi UMKM PT ASJ.docx'
    $doc = New-LetterheadDocument $word $path
    Configure-HeaderFooter $doc 'Spesifikasi Teknis Rembang UMKM Hub' $true
    Add-Paragraph $doc 'SPESIFIKASI TEKNIS' 'Title' 1 55 3 | Out-Null
    Add-Paragraph $doc $PackageName 'Normal' 1 0 8 $true 11.5 | Out-Null
    Add-Paragraph $doc 'Dokumen ini menetapkan fungsi, keluaran, persyaratan lingkungan, pemeriksaan penerimaan, dan batas layanan untuk Rembang UMKM Hub. Lingkup teknis terdiri dari 26 komponen dan harus dibaca bersama Formulir Penawaran Teknis.' | Out-Null
    Add-Paragraph $doc 'A  Fungsi aplikasi dan alur bisnis' 'Heading 1' | Out-Null
    $bodies = @{
        1='Portal menampilkan identitas layanan, kategori usaha, toko dan produk pilihan, kegiatan, banner, kontak, serta jalur menuju etalase. Pengaturan visual dan informasi operasional dikelola melalui panel sehingga perubahan rutin tidak memerlukan perubahan kode.'
        2='Pengunjung dapat membuka beranda tanpa akun. Saat memasuki etalase, nomor HP dan nama digunakan untuk sesi terbatas dan pencatatan minat. Karena kepemilikan nomor belum dibuktikan, sesi tersebut tidak membuka data pribadi, riwayat sensitif, atau peran pengelola.'
        3='Etalase menyediakan pencarian, kategori, filter kecamatan dan desa, daftar toko, detail toko, produk, peta area, serta navigasi ponsel. Toko dapat tetap ditemukan ketika produk belum lengkap, dengan penanda informasi yang tersedia.'
        4='Pengelola yang berhak dapat membuat produk draf, menetapkan harga dasar, varian, spesifikasi, kategori, dan beberapa media. Produk diajukan untuk ditinjau sebelum tampil; perubahan pada produk aktif dilakukan setelah produk ditarik dan diajukan ulang.'
        5='Keranjang dipisahkan per toko. Sistem membentuk pesan WhatsApp yang berisi produk, jumlah, dan nomor referensi. Pembukaan kontak dicatat sebagai minat pengguna, bukan transaksi atau penjualan, dan sistem tidak menangani pembayaran maupun pengiriman.'
        6='Registri memisahkan pelaku, kegiatan usaha, dan toko. Satu pelaku dapat terhubung ke beberapa kegiatan, sedangkan satu kegiatan hanya mempunyai satu toko. Profil menyimpan klasifikasi, komoditas, wilayah, NIB, hubungan pelaku, status sumber, dan catatan peninjauan.'
        7='Pengguna dapat mengajukan klaim terhadap kegiatan usaha dengan bukti privat. Petugas memutuskan akses tanpa otomatis mengubah identitas pelaku. Pemulihan akun memakai bukti, pembatasan percobaan, kata sandi sementara, dan pembatalan sesi lama setelah keputusan.'
        8='Aktivasi toko mensyaratkan pelaku utama, NIB yang diperiksa, kategori aktif, serta tanggal dan hasil kunjungan. Produk dan kategori mempunyai antrean keputusan sendiri. Alasan penolakan atau kebutuhan perbaikan dicatat dan disampaikan kepada pengelola.'
        9='Peta registri internal menampilkan koordinat yang tersedia dengan filter dan klaster. Pengelola toko dapat mengajukan titik persis, area kecamatan, atau lokasi internal. Titik persis hanya diterbitkan setelah persetujuan pemilik dan keputusan petugas; alamat rumah tetap internal.'
        10='Modul kegiatan memuat daftar publik, detail, kuota, formulir pendaftaran, status peserta, dan kehadiran dasar. Studio menggunakan komponen yang dibatasi dan menyimpan desain serta skema formulir per versi agar jawaban lama tetap dapat dibaca.'
        11='Akun UMKM dapat membuat tiket konsultasi, fasilitasi, atau pengaduan dengan nomor referensi. Petugas menetapkan status, penanggung jawab, catatan internal, dan balasan pemohon. Admin kecamatan hanya menangani tiket pada wilayah tugasnya.'
        12='Hak akses meliputi superadmin, admin Dinas, admin kecamatan, pengelola UMKM, dan masyarakat. Pemeriksaan dilakukan pada setiap aksi server. Admin kecamatan dibatasi pada wilayah tugas dan tidak dapat mengambil keputusan verifikasi yang menjadi kewenangan Dinas.'
        13='Master data mencakup provinsi, kabupaten/kota, kecamatan, desa/kelurahan, kategori etalase, KBLI 2025, skala usaha, dan referensi komoditas. Hubungan wilayah divalidasi dan master dipakai bersama oleh registri, katalog, peta, dan laporan.'
        14='Admin dapat membuat QR menuju halaman yang ditentukan, mengunduh hasilnya, mengaktifkan atau menonaktifkan QR, dan melihat kunjungan agregat. Banner, kontak, serta nomor WhatsApp pengujian dikelola melalui panel.'
        15='Dashboard menampilkan ringkasan yang dapat ditindaklanjuti, termasuk data tanpa pelaku utama atau wilayah yang belum pasti. Inbox menggabungkan pemberitahuan produk, klaim, kategori, tiket, dan kontak. Audit mencatat tindakan penting dan laporan memakai filter yang jelas.'
    }
    foreach ($n in 1..7) { $m=$Modules[$n-1]; Add-SpecItem $doc $n $m.Name $bodies[$n] }

    Add-PageBreak $doc
    Add-Paragraph $doc 'A  Fungsi aplikasi dan alur bisnis lanjutan' 'Heading 1' 0 0 4 | Out-Null
    foreach ($n in 8..15) { $m=$Modules[$n-1]; Add-SpecItem $doc $n $m.Name $bodies[$n] }

    Add-PageBreak $doc
    Add-Paragraph $doc 'B  Implementasi teknis dan data' 'Heading 1' 0 0 4 | Out-Null
    Add-SpecItem $doc 16 'Konfigurasi basis data dan migrasi' 'Basis data menggunakan MySQL atau MariaDB dengan karakter utf8mb4. Pekerjaan meliputi pembuatan basis data dan akun terbatas, penerapan skema dan migrasi berurutan, pemeriksaan kunci dan relasi, serta uji koneksi dari aplikasi.'
    Add-SpecItem $doc 17 'Konfigurasi aplikasi web dan HTTPS' 'Aplikasi ditempatkan dengan public sebagai web root. Direktori aplikasi, konfigurasi, sesi, log, dan dokumen privat tidak disajikan langsung. Rewrite URL, izin direktori, log, domain, dan HTTPS dikonfigurasi pada fasilitas yang tersedia.'
    Add-SpecItem $doc 18 'Pemetaan data awal dan master referensi' 'Master wilayah, kategori, KBLI, dan skala dimuat dari berkas yang disetujui. Data UMKM dipetakan ke registri kanonis dengan sidik impor dan catatan sumber. Kecocokan identitas yang belum pasti masuk antrean peninjauan dan tidak diputuskan otomatis.'
    Add-SpecItem $doc 19 'Versi rilis dan rancangan pencadangan' 'Kode dikelola dalam repositori Git dengan nomor versi dan catatan perubahan. Prosedur rilis, migrasi, rollback terbatas, serta cadangan basis data dan berkas didokumentasikan. Jadwal, lokasi, retensi, dan media cadangan mengikuti kebijakan server instansi.'
    Add-Paragraph $doc 'C  Dukungan dan orientasi' 'Heading 1' | Out-Null
    Add-SpecItem $doc 20 'Helpdesk penggunaan' 'Helpdesk mencatat pertanyaan, kendala, konteks pengguna, dan tindak lanjut. Kanal, jam layanan, sasaran waktu respons, prioritas, dan eskalasi ditetapkan dalam kontrak. Helpdesk tidak menggantikan dukungan penyedia infrastruktur atau layanan eksternal.'
    Add-SpecItem $doc 21 'Pemeliharaan aplikasi' 'Pemeliharaan mencakup perbaikan bug dan penyesuaian kecil yang tidak mengubah lingkup fungsi. Perubahan diuji pada fungsi terdampak dan dicatat. Modul baru, integrasi baru, perubahan arsitektur, atau migrasi besar ditetapkan sebagai pekerjaan tambahan.'
    Add-SpecItem $doc 22 'Orientasi pengelola' 'Dua sesi mencakup pengelolaan data, batas peran dan wilayah, katalog dan peninjauan, GIS dan persetujuan lokasi, kegiatan, tiket, laporan, serta prosedur bantuan. Peserta ditunjuk oleh instansi.'

    Add-PageBreak $doc
    Add-Paragraph $doc 'D  Dokumentasi dan penerimaan' 'Heading 1' 0 0 4 | Out-Null
    Add-SpecItem $doc 23 'Panduan pengguna berbasis peran' 'Panduan menjelaskan langkah umum bagi superadmin, admin Dinas, admin kecamatan, dan pengelola UMKM. Materi memuat tindakan yang diperbolehkan, status proses, pengajuan, keputusan, serta cara melaporkan kendala.'
    Add-SpecItem $doc 24 'Kamus data dan dokumentasi sistem' 'Dokumentasi memuat struktur aplikasi, dependensi, konfigurasi, migrasi, elemen data utama, relasi, arti status, lokasi berkas, proses rilis, serta prosedur pencadangan dan pemulihan yang disepakati.'
    Add-SpecItem $doc 25 'Pemeriksaan fungsi dan hak akses' 'Pemeriksaan dilakukan bersama perwakilan instansi. Skenario mencakup fungsi publik, alur pengelola, pembatasan peran/wilayah, berkas privat, keputusan verifikasi, ketepatan status, dan keluaran laporan.'
    Add-SpecItem $doc 26 'Paket serah terima' 'Paket mencakup daftar versi, daftar konfigurasi, dokumentasi, catatan hasil pemeriksaan, daftar akses administrasi yang diserahkan melalui kanal aman, dan berita acara sesuai prosedur pengadaan.'
    Add-Paragraph $doc 'E  Platform dan lingkungan' 'Heading 1' | Out-Null
    $platform = @(
        [pscustomobject]@{Kind='header'; Cells=@('Komponen','Spesifikasi')},
        [pscustomobject]@{Kind='body'; Cells=@('Jenis aplikasi','Aplikasi web responsif untuk peramban ponsel, tablet, dan desktop.')},
        [pscustomobject]@{Kind='body'; Cells=@('Bahasa dan framework','PHP 8.1 dan CodeIgniter 3 sesuai basis proyek; penempatan akhir melalui uji kompatibilitas.')},
        [pscustomobject]@{Kind='body'; Cells=@('Basis data','MySQL/MariaDB, driver MySQLi, karakter utf8mb4, dan migrasi berurutan.')},
        [pscustomobject]@{Kind='body'; Cells=@('Antarmuka dan peta','HTML, CSS, JavaScript, Tabler Core, Leaflet, dan MarkerCluster.')},
        [pscustomobject]@{Kind='body'; Cells=@('Keamanan aplikasi','Sesi, hash kata sandi, CSRF, validasi server, otorisasi per aksi, pembatasan wilayah, dan dokumen privat.')},
        [pscustomobject]@{Kind='body'; Cells=@('Web server','Nginx/rewrite yang setara, public sebagai web root, domain dan HTTPS pada fasilitas yang tersedia.')}
    )
    Add-Table $doc $platform @(125,355) @(0,0) $GreenDark $GreenPale 9.2 | Out-Null

    Add-Paragraph $doc 'F  Pemeriksaan penerimaan' 'Heading 1' 0 0 4 | Out-Null
    Add-Paragraph $doc 'Penerimaan didasarkan pada fungsi yang dapat diperagakan dan hasil yang dapat ditelusuri. Data uji harus dibersihkan atau ditandai setelah pemeriksaan.' | Out-Null
    $tests = @(
        [pscustomobject]@{Kind='header'; Cells=@('Area uji','Hasil yang diperiksa')},
        [pscustomobject]@{Kind='body'; Cells=@('Portal etalase dan WhatsApp','Pencarian/filter berfungsi, keranjang terpisah per toko, pesan sesuai item, dan catatan kontak tidak dinyatakan sebagai penjualan.')},
        [pscustomobject]@{Kind='body'; Cells=@('Registri dan toko','Pelaku, kegiatan, dan toko tidak digandakan; syarat aktivasi dan NIB diperiksa; data sumber dan status terlihat.')},
        [pscustomobject]@{Kind='body'; Cells=@('Produk kategori dan klaim','Pengajuan, antrean, keputusan, alasan, akses setelah persetujuan, dan bukti privat berjalan sesuai peran.')},
        [pscustomobject]@{Kind='body'; Cells=@('GIS dan lokasi','Koordinat internal terpisah dari publik; titik persis memerlukan persetujuan dan keputusan; filter serta klaster bekerja.')},
        [pscustomobject]@{Kind='body'; Cells=@('Kegiatan dan layanan','Formulir berversi, pendaftaran, status peserta, tiket, penugasan, catatan internal, dan balasan pemohon dapat ditelusuri.')},
        [pscustomobject]@{Kind='body'; Cells=@('Peran dan kecamatan','Akses lintas wilayah ditolak, admin kecamatan tidak dapat mengambil keputusan Dinas, dan pencabutan penugasan berlaku pada permintaan berikutnya.')},
        [pscustomobject]@{Kind='body'; Cells=@('Dokumen audit dan pemulihan','Berkas privat tidak dapat diunduh tanpa izin, tindakan penting tercatat, dan sesi lama batal setelah reset akun.')}
    )
    Add-Table $doc $tests @(150,330) @(0,0) $GreenDark $GreenPale 9.2 | Out-Null
    Add-Paragraph $doc 'Kriteria selesai' 'Heading 2' | Out-Null
    Add-Paragraph $doc 'Komponen dinyatakan selesai setelah skenario yang relevan lulus, temuan kritis ditutup, dokumen diserahkan, dan akses administrasi diterima melalui kanal aman. Temuan yang berasal dari data sumber atau fasilitas pihak ketiga dicatat terpisah dari cacat aplikasi.' | Out-Null

    Add-PageBreak $doc
    Add-Paragraph $doc 'G  Arsitektur data dan batas layanan' 'Heading 1' 0 0 4 | Out-Null
    Add-Paragraph $doc 'Alur operasional' 'Heading 2' | Out-Null
    $flow = @([pscustomobject]@{Kind='header'; Cells=@('Data sumber','Registri kanonis','Verifikasi petugas','Publikasi terpilih','Layanan dan laporan')},[pscustomobject]@{Kind='body'; Cells=@('Excel, input manual, klaim','Pelaku > kegiatan > toko','NIB, produk, kategori, lokasi','Toko, produk, peta, kegiatan','Tiket, kontak, inbox, rekap')})
    Add-Table $doc $flow @(96,96,96,96,96) @(1,1,1,1,1) $GreenDark $GreenPale 8.8 | Out-Null
    Add-Paragraph $doc 'Perlindungan data dan keputusan' 'Heading 2' | Out-Null
    Add-Paragraph $doc 'Informasi publik dipisahkan dari data internal. NIK, alamat rinci, dokumen NIB, bukti klaim, dan bukti pemulihan dibatasi bagi petugas yang memerlukan. Sistem membantu pencatatan dan peninjauan, tetapi tidak menggantikan keputusan administratif, verifikasi lapangan, atau penerbitan izin oleh instansi berwenang.' | Out-Null
    Add-Paragraph $doc 'Ketergantungan dan pengecualian' 'Heading 2' | Out-Null
    Add-Paragraph $doc 'Server/hosting, domain, sertifikat berbayar, internet, perangkat keras, layanan penyimpanan eksternal, biaya gateway pesan, dan akun layanan pihak ketiga tidak termasuk. Pembayaran daring, logistik, OTP/SSO, API perizinan, tanda tangan elektronik, dan integrasi baru memerlukan kesepakatan terpisah.' | Out-Null
    Add-Paragraph $doc 'Data awal mengikuti berkas yang disediakan. Pembersihan massal, penetapan identitas yang belum pasti, pengisian produk/foto dalam jumlah besar, kunjungan lapangan, dan digitalisasi arsip tidak termasuk kecuali dinyatakan dalam kontrak.' | Out-Null
    Add-Paragraph $doc 'Dukungan dan hak penggunaan' 'Heading 2' | Out-Null
    Add-Paragraph $doc 'Jam layanan, sasaran respons, eskalasi, garansi, hak penggunaan, mekanisme penyerahan source code, lisensi komponen, penanggung jawab server, dan masa mulai dukungan dituangkan dalam kontrak.' | Out-Null
    Add-Signature $doc $Company $Director 'Direktur' | Out-Null
    $doc.Save(); $doc.Close(0); return $path
}

$initialHash = (Get-FileHash -Algorithm SHA256 -LiteralPath $TemplatePath).Hash
$word = New-Object -ComObject Word.Application
$word.Visible = $false
$word.DisplayAlerts = 0
$outputs = @()
try {
    $outputs += Build-OfferLetter $word
    $outputs += Build-Hps $word
    $outputs += Build-TechnicalForm $word
    $outputs += Build-Specification $word
} finally {
    $word.Quit()
    [Runtime.InteropServices.Marshal]::FinalReleaseComObject($word) | Out-Null
    [gc]::Collect(); [gc]::WaitForPendingFinalizers()
}
$finalHash = (Get-FileHash -Algorithm SHA256 -LiteralPath $TemplatePath).Hash
if ($initialHash -ne $finalHash) { throw 'Letterhead source changed during generation.' }

$expectedHps = ($Modules | Measure-Object Hps -Sum).Sum + (($SupportItems | ForEach-Object {[long]$_.Vol * [long]$_.Price}) | Measure-Object -Sum).Sum
if ($expectedHps -ne $HpsTotal) { throw "HPS reconciliation failed: $expectedHps" }
if ((55500000 + 5150000 + 7150000 + 1800000) -ne $OfferTotal) { throw 'Offer reconciliation failed.' }

$outputs | ForEach-Object { Get-Item -LiteralPath $_ | Select-Object FullName,Length,LastWriteTime }


