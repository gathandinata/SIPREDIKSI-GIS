<?php
require_once __DIR__ . "/../auth.php";
require_once __DIR__ . "/../koneksi.php";

/** @var mysqli $koneksi */

$active_menu = "data-kemiskinan";
$base_url = "../";

function setFlash($type, $message)
{
    $_SESSION["flash"] = [
        "type" => $type,
        "message" => $message
    ];
}

function getFlash()
{
    if (!isset($_SESSION["flash"])) {
        return null;
    }

    $flash = $_SESSION["flash"];
    unset($_SESSION["flash"]);

    return $flash;
}

function redirectIndex()
{
    header("Location: index.php");
    exit;
}

function getSingleValue($koneksi, $query, $default = 0)
{
    $result = mysqli_query($koneksi, $query);

    if (!$result) {
        return $default;
    }

    $row = mysqli_fetch_row($result);

    return $row[0] ?? $default;
}

function buildPageUrl($page)
{
    $params = $_GET;
    $params["page"] = $page;

    return "index.php?" . http_build_query($params);
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $action = $_POST["action"] ?? "";
    $id_admin = (int) ($_SESSION["id_admin"] ?? 0);

    if ($action === "create_kecamatan") {
        $kode_kecamatan = trim($_POST["kode_kecamatan"] ?? "");
        $nama_kecamatan = trim($_POST["nama_kecamatan"] ?? "");
        $geojson_wilayah = trim($_POST["geojson_wilayah"] ?? "");

        if ($kode_kecamatan === "" || $nama_kecamatan === "") {
            setFlash("error", "Kode kecamatan dan nama kecamatan wajib diisi.");
            redirectIndex();
        }

        if ($geojson_wilayah !== "") {
            json_decode($geojson_wilayah);

            if (json_last_error() !== JSON_ERROR_NONE) {
                setFlash("error", "Format GeoJSON tidak valid. Kosongkan jika belum tersedia.");
                redirectIndex();
            }
        }

        $geojson_value = $geojson_wilayah !== "" ? $geojson_wilayah : null;

        $query = "INSERT INTO kecamatan 
                  (id_admin, kode_kecamatan, nama_kecamatan, geojson_wilayah)
                  VALUES (?, ?, ?, ?)";

        $stmt = mysqli_prepare($koneksi, $query);

        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "isss", $id_admin, $kode_kecamatan, $nama_kecamatan, $geojson_value);

            if (mysqli_stmt_execute($stmt)) {
                setFlash("success", "Data kecamatan berhasil ditambahkan.");
            } else {
                if (mysqli_errno($koneksi) == 1062) {
                    setFlash("error", "Kode atau nama kecamatan sudah tersedia.");
                } else {
                    setFlash("error", "Data kecamatan gagal ditambahkan.");
                }
            }

            mysqli_stmt_close($stmt);
        } else {
            setFlash("error", "Query tambah kecamatan gagal disiapkan.");
        }

        redirectIndex();
    }

    if ($action === "create_data") {
        $id_kecamatan = (int) ($_POST["id_kecamatan"] ?? 0);
        $tahun = (int) ($_POST["tahun"] ?? 0);
        $jumlah = $_POST["jumlah_penduduk_miskin"] ?? "";

        if ($id_kecamatan <= 0) {
            setFlash("error", "Kecamatan wajib dipilih.");
            redirectIndex();
        }

        if ($tahun < 1901 || $tahun > 2155) {
            setFlash("error", "Tahun tidak valid.");
            redirectIndex();
        }

        if ($jumlah === "" || !is_numeric($jumlah) || (int) $jumlah < 0) {
            setFlash("error", "Jumlah penduduk miskin harus berupa angka dan tidak boleh negatif.");
            redirectIndex();
        }

        $jumlah = (int) $jumlah;

        $query = "INSERT INTO data_kemiskinan 
                  (id_kecamatan, id_admin, tahun, jumlah_penduduk_miskin)
                  VALUES (?, ?, ?, ?)";

        $stmt = mysqli_prepare($koneksi, $query);

        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "iiii", $id_kecamatan, $id_admin, $tahun, $jumlah);

            if (mysqli_stmt_execute($stmt)) {
                setFlash("success", "Data kemiskinan berhasil ditambahkan.");
            } else {
                if (mysqli_errno($koneksi) == 1062) {
                    setFlash("error", "Data kemiskinan untuk kecamatan dan tahun tersebut sudah tersedia.");
                } else {
                    setFlash("error", "Data kemiskinan gagal ditambahkan.");
                }
            }

            mysqli_stmt_close($stmt);
        } else {
            setFlash("error", "Query tambah data gagal disiapkan.");
        }

        redirectIndex();
    }

    if ($action === "update_data") {
        $id_data = (int) ($_POST["id_data"] ?? 0);
        $id_kecamatan = (int) ($_POST["id_kecamatan"] ?? 0);
        $tahun = (int) ($_POST["tahun"] ?? 0);
        $jumlah = $_POST["jumlah_penduduk_miskin"] ?? "";

        if ($id_data <= 0 || $id_kecamatan <= 0) {
            setFlash("error", "Data yang dipilih tidak valid.");
            redirectIndex();
        }

        if ($tahun < 1901 || $tahun > 2155) {
            setFlash("error", "Tahun tidak valid.");
            redirectIndex();
        }

        if ($jumlah === "" || !is_numeric($jumlah) || (int) $jumlah < 0) {
            setFlash("error", "Jumlah penduduk miskin harus berupa angka dan tidak boleh negatif.");
            redirectIndex();
        }

        $jumlah = (int) $jumlah;

        $query = "UPDATE data_kemiskinan
                  SET id_kecamatan = ?,
                      id_admin = ?,
                      tahun = ?,
                      jumlah_penduduk_miskin = ?
                  WHERE id_data = ?";

        $stmt = mysqli_prepare($koneksi, $query);

        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "iiiii", $id_kecamatan, $id_admin, $tahun, $jumlah, $id_data);

            if (mysqli_stmt_execute($stmt)) {
                setFlash("success", "Data kemiskinan berhasil diperbarui.");
            } else {
                if (mysqli_errno($koneksi) == 1062) {
                    setFlash("error", "Data kemiskinan untuk kecamatan dan tahun tersebut sudah tersedia.");
                } else {
                    setFlash("error", "Data kemiskinan gagal diperbarui.");
                }
            }

            mysqli_stmt_close($stmt);
        } else {
            setFlash("error", "Query edit data gagal disiapkan.");
        }

        redirectIndex();
    }


    if ($action === "delete_data") {
        $id_data = (int) ($_POST["id_data"] ?? 0);

        if ($id_data <= 0) {
            setFlash("error", "Data yang akan dihapus tidak valid.");
            redirectIndex();
        }

        $query = "DELETE FROM data_kemiskinan WHERE id_data = ?";
        $stmt = mysqli_prepare($koneksi, $query);

        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "i", $id_data);

            if (mysqli_stmt_execute($stmt)) {
                setFlash("success", "Data kemiskinan berhasil dihapus.");
            } else {
                setFlash("error", "Data kemiskinan gagal dihapus.");
            }

            mysqli_stmt_close($stmt);
        } else {
            setFlash("error", "Query hapus data gagal disiapkan.");
        }

        redirectIndex();
    }
}

$flash = getFlash();

$kecamatanList = [];
$queryKecamatan = mysqli_query(
    $koneksi,
    "SELECT id_kecamatan, kode_kecamatan, nama_kecamatan
     FROM kecamatan
     ORDER BY nama_kecamatan ASC"
);

if ($queryKecamatan) {
    while ($row = mysqli_fetch_assoc($queryKecamatan)) {
        $kecamatanList[] = $row;
    }
}

$tahunList = [];
$queryTahun = mysqli_query(
    $koneksi,
    "SELECT DISTINCT tahun
     FROM data_kemiskinan
     ORDER BY tahun DESC"
);

if ($queryTahun) {
    while ($row = mysqli_fetch_assoc($queryTahun)) {
        $tahunList[] = $row["tahun"];
    }
}

$filter_kecamatan = $_GET["kecamatan"] ?? "";
$filter_tahun = $_GET["tahun"] ?? "";
$keyword = trim($_GET["keyword"] ?? "");

$where = [];

if ($filter_kecamatan !== "") {
    $where[] = "d.id_kecamatan = " . (int) $filter_kecamatan;
}

if ($filter_tahun !== "") {
    $where[] = "d.tahun = " . (int) $filter_tahun;
}

if ($keyword !== "") {
    $safeKeyword = mysqli_real_escape_string($koneksi, $keyword);
    $where[] = "(k.nama_kecamatan LIKE '%$safeKeyword%' OR k.kode_kecamatan LIKE '%$safeKeyword%')";
}

$whereSql = "";

if (!empty($where)) {
    $whereSql = "WHERE " . implode(" AND ", $where);
}


$dataKemiskinan = [];
$queryData = mysqli_query(
    $koneksi,
    "SELECT 
        d.id_data,
        d.id_kecamatan,
        d.tahun,
        d.jumlah_penduduk_miskin,
        k.kode_kecamatan,
        k.nama_kecamatan,
        a.nama_admin
     FROM data_kemiskinan d
     INNER JOIN kecamatan k ON k.id_kecamatan = d.id_kecamatan
     LEFT JOIN admin a ON a.id_admin = d.id_admin
     $whereSql
     ORDER BY d.tahun DESC, k.nama_kecamatan ASC"
);

if ($queryData) {
    while ($row = mysqli_fetch_assoc($queryData)) {
        $dataKemiskinan[] = $row;
    }
}

$totalKecamatan = getSingleValue($koneksi, "SELECT COUNT(*) FROM kecamatan");
$totalData = getSingleValue($koneksi, "SELECT COUNT(*) FROM data_kemiskinan");
$totalTahun = getSingleValue($koneksi, "SELECT COUNT(DISTINCT tahun) FROM data_kemiskinan");
$tahunTerbaru = getSingleValue($koneksi, "SELECT MAX(tahun) FROM data_kemiskinan", "-");

$totalMiskinTerbaru = 0;

if ($tahunTerbaru !== "-" && $tahunTerbaru !== null) {
    $totalMiskinTerbaru = getSingleValue(
        $koneksi,
        "SELECT COALESCE(SUM(jumlah_penduduk_miskin), 0)
         FROM data_kemiskinan
         WHERE tahun = " . (int) $tahunTerbaru
    );
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Data Kemiskinan | SIPREDIKSI GIS</title>

    <link rel="stylesheet" href="../style-sidebar.css">
    <link rel="stylesheet" href="../style-header.css">
    <link rel="stylesheet" href="style-data-kemiskinan.css">
</head>
<body>

<?php include __DIR__ . "/../sidebar.php"; ?>

<main class="main-content">
    <?php
    $pageTitle = 'Data Kemiskinan';
    $pageSubtitle = 'Kelola data kecamatan dan data historis kemiskinan Kabupaten Sumba Timur.';
    $pageIcon = '🧾';
    require_once __DIR__ . '/../template-header.php';
    ?>

    <section class="page-heading">
        <div>
            <h2>Data Historis Kemiskinan</h2>
            <p>Data ini menjadi dasar proses regresi linier, visualisasi peta SIG, dan laporan hasil prediksi.</p>
        </div>

        <div class="heading-actions">
            <!-- <button type="button" class="btn-secondary" data-open-modal="modalTambahKecamatan">
                + Tambah Kecamatan
            </button> -->

            <button type="button" class="btn-secondary" data-open-modal="modalImportExcel">
                ⬆ Upload Excel
            </button>

            <button type="button" class="btn-primary" data-open-modal="modalTambahData">
                + Tambah Data Kemiskinan
            </button>
        </div>
    </section>

    <?php if ($flash) : ?>
        <div class="alert <?= $flash["type"] === "success" ? "alert-success" : "alert-error"; ?>">
            <?= htmlspecialchars($flash["message"]); ?>
        </div>
    <?php endif; ?>

    <section class="summary-grid">
        <div class="summary-card blue">
            <div class="summary-icon">🗺️</div>
            <div>
                <p>Total Kecamatan</p>
                <h3><?= number_format($totalKecamatan, 0, ",", "."); ?></h3>
                <span>Wilayah tersimpan</span>
            </div>
        </div>

        <div class="summary-card green">
            <div class="summary-icon">🧾</div>
            <div>
                <p>Total Data</p>
                <h3><?= number_format($totalData, 0, ",", "."); ?></h3>
                <span>Data historis</span>
            </div>
        </div>

        <div class="summary-card orange">
            <div class="summary-icon">📅</div>
            <div>
                <p>Periode Tahun</p>
                <h3><?= number_format($totalTahun, 0, ",", "."); ?></h3>
                <span>Tahun tersedia</span>
            </div>
        </div>

        <div class="summary-card purple">
            <div class="summary-icon">Σ</div>
            <div>
                <p>Total Tahun <?= htmlspecialchars($tahunTerbaru); ?></p>
                <h3><?= number_format($totalMiskinTerbaru, 0, ",", "."); ?></h3>
                <span>Penduduk miskin</span>
            </div>
        </div>
    </section>

    <section class="filter-card">
        <form method="GET" action="" class="filter-form">
            <div class="form-group">
                <label for="kecamatan">Kecamatan</label>
                <select name="kecamatan" id="kecamatan">
                    <option value="">Semua Kecamatan</option>
                    <?php foreach ($kecamatanList as $kecamatan) : ?>
                        <option 
                            value="<?= (int) $kecamatan["id_kecamatan"]; ?>"
                            <?= (string) $filter_kecamatan === (string) $kecamatan["id_kecamatan"] ? "selected" : ""; ?>
                        >
                            <?= htmlspecialchars($kecamatan["nama_kecamatan"]); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="tahun">Tahun</label>
                <select name="tahun" id="tahun">
                    <option value="">Semua Tahun</option>
                    <?php foreach ($tahunList as $tahun) : ?>
                        <option 
                            value="<?= htmlspecialchars($tahun); ?>"
                            <?= (string) $filter_tahun === (string) $tahun ? "selected" : ""; ?>
                        >
                            <?= htmlspecialchars($tahun); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group search-group">
                <label for="keyword">Pencarian</label>
                <input 
                    type="text" 
                    name="keyword" 
                    id="keyword" 
                    placeholder="Cari nama atau kode kecamatan"
                    value="<?= htmlspecialchars($keyword); ?>"
                >
            </div>

            <div class="filter-actions">
                <button type="submit" class="btn-filter">Tampilkan</button>
                <a href="index.php" class="btn-reset">Reset</a>
            </div>
        </form>
    </section>

    <section class="table-card">
        <div class="table-header">
            <div>
                <h3>Daftar Data Kemiskinan</h3>
                <p>Menampilkan data jumlah penduduk miskin berdasarkan kecamatan dan tahun.</p>
            </div>
        </div>

        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>No</th>
                        <th>Kode</th>
                        <th>Kecamatan</th>
                        <th>Tahun</th>
                        <th>Jumlah Penduduk Miskin</th>
                        <th>Dikelola Oleh</th>
                        <th>Aksi</th>
                    </tr>
                </thead>

                <tbody>
                    <?php if (!empty($dataKemiskinan)) : ?>
                        <?php $no = 1; ?>
                        <?php foreach ($dataKemiskinan as $data) : ?>
                            <tr>
                                <td><?= $no++; ?></td>
                                <td><?= htmlspecialchars($data["kode_kecamatan"]); ?></td>
                                <td><?= htmlspecialchars($data["nama_kecamatan"]); ?></td>
                                <td><?= htmlspecialchars($data["tahun"]); ?></td>
                                <td>
                                    <strong><?= number_format($data["jumlah_penduduk_miskin"], 0, ",", "."); ?></strong>
                                    <span class="unit">jiwa</span>
                                </td>
                                <td><?= htmlspecialchars($data["nama_admin"] ?? "-"); ?></td>
                                <td>
                                    <div class="action-buttons">
                                        <button 
                                            type="button" 
                                            class="btn-action btn-detail"
                                            data-detail
                                            data-kode="<?= htmlspecialchars($data["kode_kecamatan"], ENT_QUOTES); ?>"
                                            data-kecamatan="<?= htmlspecialchars($data["nama_kecamatan"], ENT_QUOTES); ?>"
                                            data-tahun="<?= htmlspecialchars($data["tahun"], ENT_QUOTES); ?>"
                                            data-jumlah="<?= (int) $data["jumlah_penduduk_miskin"]; ?>"
                                            data-admin="<?= htmlspecialchars($data["nama_admin"] ?? "-", ENT_QUOTES); ?>"
                                        >
                                            Detail
                                        </button>

                                        <button 
                                            type="button" 
                                            class="btn-action btn-edit"
                                            data-edit
                                            data-id="<?= (int) $data["id_data"]; ?>"
                                            data-id-kecamatan="<?= (int) $data["id_kecamatan"]; ?>"
                                            data-tahun="<?= htmlspecialchars($data["tahun"], ENT_QUOTES); ?>"
                                            data-jumlah="<?= (int) $data["jumlah_penduduk_miskin"]; ?>"
                                        >
                                            Edit
                                        </button>

                                        <button 
                                            type="button" 
                                            class="btn-action btn-delete"
                                            data-delete
                                            data-id="<?= (int) $data["id_data"]; ?>"
                                            data-kecamatan="<?= htmlspecialchars($data["nama_kecamatan"], ENT_QUOTES); ?>"
                                            data-tahun="<?= htmlspecialchars($data["tahun"], ENT_QUOTES); ?>"
                                        >
                                            Hapus
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else : ?>
                        <tr>
                            <td colspan="7" class="empty-table">
                                Data kemiskinan belum tersedia.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
         </div>
    </section>
</main>


<div class="modal" id="modalImportExcel">
    <div class="modal-content">
        <div class="modal-header">
            <div>
                <h3>Upload Data Excel</h3>
                <p>Unggah file Excel untuk menambahkan data kemiskinan ke sistem.</p>
            </div>
            <button type="button" class="modal-close" data-close-modal>&times;</button>
        </div>

        <form method="POST" action="import-excel.php" enctype="multipart/form-data" class="modal-form">
            <input type="hidden" name="action" value="import_excel">

            <div class="form-group">
                <label for="jenis_import">
                    Jenis Data <span class="required-star">*</span>
                </label>
                <select name="jenis_import" id="jenis_import" required>
                    <option value="">Pilih Jenis Data</option>
                    <option value="dtks">DTKS</option>
                    <option value="dtsen">DTSEN</option>
                    <option value="template">Template Sistem</option>
                </select>
                <small>Pilih jenis data sesuai format file Excel yang akan diunggah.</small>
            </div>

            <div class="form-group">
                <label for="tahun_import">
                    Tahun Data <span class="required-star">*</span>
                </label>
                <input 
                    type="number" 
                    name="tahun_import" 
                    id="tahun_import"
                    min="1901"
                    max="2155"
                    placeholder="Contoh: 2025"
                    required
                >
                <small>Tahun ini akan digunakan sebagai tahun data hasil import.</small>
            </div>

            <div class="form-group">
                <label for="file_excel">
                    File Excel <span class="required-star">*</span>
                </label>
                <input 
                    type="file" 
                    name="file_excel" 
                    id="file_excel"
                    class="file-input"
                    accept=".xlsx,.xls"
                    required
                >
                <small>Format file yang didukung: .xlsx atau .xls.</small>
            </div>

            <div class="upload-note">
                Pastikan nama kecamatan pada file Excel sesuai dengan data kecamatan yang sudah tersimpan di sistem.
            </div>

            <div class="modal-actions">
                <button type="button" class="btn-secondary" data-close-modal>Batal</button>
                <button type="submit" class="btn-primary">Upload Excel</button>
            </div>
        </form>
    </div>
</div>

<div class="modal" id="modalTambahKecamatan">
    <div class="modal-content">
        <div class="modal-header">
            <div>
                <h3>Tambah Kecamatan</h3>
                <p>Tambahkan data master kecamatan sebelum menginput data kemiskinan.</p>
            </div>
            <button type="button" class="modal-close" data-close-modal>&times;</button>
        </div>

        <form method="POST" action="" class="modal-form">
            <input type="hidden" name="action" value="create_kecamatan">

            <div class="form-group">
                <label for="kode_kecamatan">Kode Kecamatan</label>
                <input 
                    type="text" 
                    name="kode_kecamatan" 
                    id="kode_kecamatan" 
                    placeholder="Contoh: KEC001"
                    required
                >
            </div>

            <div class="form-group">
                <label for="nama_kecamatan">Nama Kecamatan</label>
                <input 
                    type="text" 
                    name="nama_kecamatan" 
                    id="nama_kecamatan" 
                    placeholder="Contoh: Kota Waingapu"
                    required
                >
            </div>

            <div class="form-group">
                <label for="geojson_wilayah">GeoJSON Wilayah</label>
                <textarea 
                    name="geojson_wilayah" 
                    id="geojson_wilayah" 
                    rows="5"
                    placeholder="Boleh dikosongkan. Nanti dipakai untuk peta SIG."
                ></textarea>
                <small>Isi hanya jika data batas wilayah kecamatan sudah tersedia.</small>
            </div>

            <div class="modal-actions">
                <button type="button" class="btn-secondary" data-close-modal>Batal</button>
                <button type="submit" class="btn-primary">Simpan Kecamatan</button>
            </div>
        </form>
    </div>
</div>

<div class="modal" id="modalTambahData">
    <div class="modal-content">
        <div class="modal-header">
            <div>
                <h3>Tambah Data Kemiskinan</h3>
                <p>Masukkan jumlah penduduk miskin berdasarkan kecamatan dan tahun.</p>
            </div>
            <button type="button" class="modal-close" data-close-modal>&times;</button>
        </div>

        <form method="POST" action="" class="modal-form">
            <input type="hidden" name="action" value="create_data">

            <div class="form-group">
                <label for="add_id_kecamatan">Kecamatan*</label>
                <select name="id_kecamatan" id="add_id_kecamatan" required>
                    <option value="">Pilih Kecamatan</option>
                    <?php foreach ($kecamatanList as $kecamatan) : ?>
                        <option value="<?= (int) $kecamatan["id_kecamatan"]; ?>">
                            <?= htmlspecialchars($kecamatan["nama_kecamatan"]); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="add_tahun">Tahun*</label>
                <input 
                    type="number" 
                    name="tahun" 
                    id="add_tahun" 
                    min="1901"
                    max="2155"
                    placeholder="Contoh: 2025"
                    required
                >
            </div>

            <div class="form-group">
                <label for="add_jumlah">Jumlah Penduduk Miskin*</label>
                <input 
                    type="number" 
                    name="jumlah_penduduk_miskin" 
                    id="add_jumlah"
                    min="0"
                    placeholder="Contoh: 18500"
                    required
                >
            </div>

            <div class="modal-actions">
                <button type="button" class="btn-secondary" data-close-modal>Batal</button>
                <button type="submit" class="btn-primary">Simpan Data</button>
            </div>
        </form>
    </div>
</div>

<div class="modal" id="modalEditData">
    <div class="modal-content">
        <div class="modal-header">
            <div>
                <h3>Edit Data Kemiskinan</h3>
                <p>Perbarui data historis yang sudah tersimpan.</p>
            </div>
            <button type="button" class="modal-close" data-close-modal>&times;</button>
        </div>

        <form method="POST" action="" class="modal-form">
            <input type="hidden" name="action" value="update_data">
            <input type="hidden" name="id_data" id="edit_id_data">

            <div class="form-group">
                <label for="edit_id_kecamatan">Kecamatan</label>
                <select name="id_kecamatan" id="edit_id_kecamatan" required>
                    <option value="">Pilih Kecamatan</option>
                    <?php foreach ($kecamatanList as $kecamatan) : ?>
                        <option value="<?= (int) $kecamatan["id_kecamatan"]; ?>">
                            <?= htmlspecialchars($kecamatan["nama_kecamatan"]); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="edit_tahun">Tahun</label>
                <input 
                    type="number" 
                    name="tahun" 
                    id="edit_tahun"
                    min="1901"
                    max="2155"
                    required
                >
            </div>

            <div class="form-group">
                <label for="edit_jumlah">Jumlah Penduduk Miskin</label>
                <input 
                    type="number" 
                    name="jumlah_penduduk_miskin" 
                    id="edit_jumlah"
                    min="0"
                    required
                >
            </div>

            <div class="modal-actions">
                <button type="button" class="btn-secondary" data-close-modal>Batal</button>
                <button type="submit" class="btn-primary">Simpan Perubahan</button>
            </div>
        </form>
    </div>
</div>

<div class="modal" id="modalDetailData">
    <div class="modal-content">
        <div class="modal-header">
            <div>
                <h3>Detail Data Kemiskinan</h3>
                <p>Informasi data historis yang dipilih.</p>
            </div>
            <button type="button" class="modal-close" data-close-modal>&times;</button>
        </div>

        <div class="detail-list">
            <div class="detail-item">
                <span>Kode Kecamatan</span>
                <strong id="detail_kode">-</strong>
            </div>

            <div class="detail-item">
                <span>Nama Kecamatan</span>
                <strong id="detail_kecamatan">-</strong>
            </div>

            <div class="detail-item">
                <span>Tahun</span>
                <strong id="detail_tahun">-</strong>
            </div>

            <div class="detail-item">
                <span>Jumlah Penduduk Miskin</span>
                <strong id="detail_jumlah">-</strong>
            </div>

            <div class="detail-item">
                <span>Dikelola Oleh</span>
                <strong id="detail_admin">-</strong>
            </div>
        </div>

        <div class="modal-actions">
            <button type="button" class="btn-primary" data-close-modal>Tutup</button>
        </div>
    </div>
</div>

<form method="POST" action="" id="deleteForm">
    <input type="hidden" name="action" value="delete_data">
    <input type="hidden" name="id_data" id="delete_id_data">
</form>

<script src="../script-sidebar.js"></script>
<script src="script-data-kemiskinan.js"></script>
</body>
</html>