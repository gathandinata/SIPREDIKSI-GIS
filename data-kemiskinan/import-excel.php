<?php
require_once __DIR__ . "/../auth.php";
require_once __DIR__ . "/../koneksi.php";

/** @var mysqli $koneksi */

function setImportFlash($type, $message)
{
    $_SESSION["flash"] = [
        "type" => $type,
        "message" => $message
    ];
}

function redirectToDataKemiskinan()
{
    header("Location: index.php");
    exit;
}

function normalizeText($text)
{
    $text = (string) $text;
    $text = trim($text);
    $text = strtoupper($text);
    $text = str_replace([".", ",", "-", "_", "/", "\\", "(", ")", "[", "]"], " ", $text);
    $text = preg_replace("/\s+/", " ", $text);

    return trim($text);
}

function normalizeCompactText($text)
{
    $text = normalizeText($text);
    $text = str_replace(" ", "", $text);

    return trim($text);
}

function cleanString($value)
{
    return trim((string) $value);
}

function toInteger($value)
{
    if ($value === null) {
        return 0;
    }

    if (is_numeric($value)) {
        return (int) round((float) $value);
    }

    $value = trim((string) $value);

    if ($value === "") {
        return 0;
    }

    $value = preg_replace("/[^0-9\-]/", "", $value);

    if ($value === "" || $value === "-") {
        return 0;
    }

    return (int) $value;
}

function getKecamatanMaps(mysqli $koneksi)
{
    $byName = [];
    $byCode = [];
    $byCompactName = [];

    $query = "
        SELECT 
            id_kecamatan, 
            kode_kecamatan, 
            nama_kecamatan 
        FROM kecamatan
    ";

    $result = mysqli_query($koneksi, $query);

    if (!$result) {
        return [$byName, $byCode, $byCompactName];
    }

    while ($row = mysqli_fetch_assoc($result)) {
        $nameKey = normalizeText($row["nama_kecamatan"]);
        $codeKey = normalizeText($row["kode_kecamatan"]);
        $compactNameKey = normalizeCompactText($row["nama_kecamatan"]);

        if ($nameKey !== "") {
            $byName[$nameKey] = $row;
        }

        if ($codeKey !== "") {
            $byCode[$codeKey] = $row;
        }

        if ($compactNameKey !== "") {
            $byCompactName[$compactNameKey] = $row;
        }
    }

    return [$byName, $byCode, $byCompactName];
}

function findKecamatan($namaKecamatan, $kodeKecamatan, array $byName, array $byCode, array $byCompactName)
{
    $namaKey = normalizeText($namaKecamatan);
    $kodeKey = normalizeText($kodeKecamatan);
    $compactNameKey = normalizeCompactText($namaKecamatan);

    if ($kodeKey !== "" && isset($byCode[$kodeKey])) {
        return $byCode[$kodeKey];
    }

    if ($namaKey !== "" && isset($byName[$namaKey])) {
        return $byName[$namaKey];
    }

    if ($compactNameKey !== "" && isset($byCompactName[$compactNameKey])) {
        return $byCompactName[$compactNameKey];
    }

    return null;
}

function upsertDataKemiskinan(
    mysqli $koneksi,
    int $idKecamatan,
    int $idAdmin,
    int $tahun,
    int $jumlahMiskin,
    string $sumberData,
    string $keterangan
) {
    $query = "
        INSERT INTO data_kemiskinan
            (
                id_kecamatan, 
                id_admin, 
                tahun, 
                jumlah_penduduk_miskin, 
                sumber_data, 
                keterangan
            )
        VALUES
            (?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            id_admin = VALUES(id_admin),
            jumlah_penduduk_miskin = VALUES(jumlah_penduduk_miskin),
            sumber_data = VALUES(sumber_data),
            keterangan = VALUES(keterangan)
    ";

    $stmt = mysqli_prepare($koneksi, $query);

    if (!$stmt) {
        throw new Exception("Query simpan data kemiskinan gagal disiapkan.");
    }

    mysqli_stmt_bind_param(
        $stmt,
        "iiiiss",
        $idKecamatan,
        $idAdmin,
        $tahun,
        $jumlahMiskin,
        $sumberData,
        $keterangan
    );

    if (!mysqli_stmt_execute($stmt)) {
        $error = mysqli_stmt_error($stmt);
        mysqli_stmt_close($stmt);
        throw new Exception("Data kemiskinan gagal disimpan. " . $error);
    }

    mysqli_stmt_close($stmt);
}

function upsertDataPenduduk(
    mysqli $koneksi,
    int $idKecamatan,
    int $idAdmin,
    int $tahun,
    int $jumlahPendudukKecamatan,
    int $jumlahPendudukKabupaten,
    string $sumberData,
    string $keterangan
) {
    if ($jumlahPendudukKecamatan <= 0 && $jumlahPendudukKabupaten <= 0) {
        return;
    }

    $jenisData = "kecamatan";

    $query = "
        INSERT INTO data_penduduk
            (
                id_kecamatan,
                id_admin,
                tahun,
                jenis_data,
                jumlah_penduduk_kecamatan,
                jumlah_penduduk_kabupaten,
                sumber_data,
                keterangan
            )
        VALUES
            (?, ?, ?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            id_admin = VALUES(id_admin),
            jenis_data = VALUES(jenis_data),
            jumlah_penduduk_kecamatan = VALUES(jumlah_penduduk_kecamatan),
            jumlah_penduduk_kabupaten = VALUES(jumlah_penduduk_kabupaten),
            sumber_data = VALUES(sumber_data),
            keterangan = VALUES(keterangan)
    ";

    $stmt = mysqli_prepare($koneksi, $query);

    if (!$stmt) {
        throw new Exception("Query simpan data penduduk gagal disiapkan.");
    }

    mysqli_stmt_bind_param(
        $stmt,
        "iiisiiss",
        $idKecamatan,
        $idAdmin,
        $tahun,
        $jenisData,
        $jumlahPendudukKecamatan,
        $jumlahPendudukKabupaten,
        $sumberData,
        $keterangan
    );

    if (!mysqli_stmt_execute($stmt)) {
        $error = mysqli_stmt_error($stmt);
        mysqli_stmt_close($stmt);
        throw new Exception("Data penduduk gagal disimpan. " . $error);
    }

    mysqli_stmt_close($stmt);
}

function selectSheetByYear($spreadsheet, int $tahun)
{
    $sheetNames = $spreadsheet->getSheetNames();

    foreach ($sheetNames as $index => $sheetName) {
        $upperName = strtoupper($sheetName);

        if (strpos($upperName, (string) $tahun) !== false && strpos($upperName, "PDF") === false) {
            return $spreadsheet->getSheet($index);
        }
    }

    foreach ($sheetNames as $index => $sheetName) {
        $upperName = strtoupper($sheetName);

        if (strpos($upperName, (string) $tahun) !== false) {
            return $spreadsheet->getSheet($index);
        }
    }

    return $spreadsheet->getActiveSheet();
}

function importTemplateSistem(
    mysqli $koneksi,
    $spreadsheet,
    int $tahunImport,
    int $idAdmin,
    array $byName,
    array $byCode,
    array $byCompactName
) {
    $sheet = $spreadsheet->getActiveSheet();
    $rows = $sheet->toArray(null, true, true, true);

    if (count($rows) < 2) {
        throw new Exception("File template kosong atau tidak memiliki data.");
    }

    $headerRow = reset($rows);
    $headerMap = [];

    foreach ($headerRow as $column => $value) {
        $header = normalizeText($value);

        if ($header !== "") {
            $headerMap[$header] = $column;
        }
    }

    $getColumn = function (array $possibleHeaders) use ($headerMap) {
        foreach ($possibleHeaders as $header) {
            $key = normalizeText($header);

            if (isset($headerMap[$key])) {
                return $headerMap[$key];
            }
        }

        return null;
    };

    $colKode = $getColumn(["kode_kecamatan", "kode kecamatan", "kode"]);
    $colKecamatan = $getColumn(["nama_kecamatan", "nama kecamatan", "kecamatan"]);
    $colTahun = $getColumn(["tahun", "tahun_data", "tahun data"]);
    $colMiskin = $getColumn([
        "jumlah_penduduk_miskin",
        "jumlah penduduk miskin",
        "penduduk miskin",
        "jumlah miskin"
    ]);
    $colPendudukKecamatan = $getColumn([
        "jumlah_penduduk_kecamatan",
        "jumlah penduduk kecamatan",
        "penduduk kecamatan",
        "total penduduk kecamatan"
    ]);
    $colPendudukKabupaten = $getColumn([
        "jumlah_penduduk_kabupaten",
        "jumlah penduduk kabupaten",
        "penduduk kabupaten",
        "total penduduk kabupaten"
    ]);
    $colSumber = $getColumn(["sumber_data", "sumber data", "sumber"]);
    $colKeterangan = $getColumn(["keterangan", "catatan"]);

    if ($colKecamatan === null && $colKode === null) {
        throw new Exception("Template harus memiliki kolom nama_kecamatan atau kode_kecamatan.");
    }

    if ($colMiskin === null) {
        throw new Exception("Template harus memiliki kolom jumlah_penduduk_miskin.");
    }

    $processed = 0;
    $processedPenduduk = 0;
    $skipped = 0;
    $unmatched = [];
    $rowNumber = 0;

    foreach ($rows as $row) {
        $rowNumber++;

        if ($rowNumber === 1) {
            continue;
        }

        $kodeKecamatan = $colKode ? cleanString($row[$colKode] ?? "") : "";
        $namaKecamatan = $colKecamatan ? cleanString($row[$colKecamatan] ?? "") : "";

        if ($kodeKecamatan === "" && $namaKecamatan === "") {
            $skipped++;
            continue;
        }

        $kecamatan = findKecamatan($namaKecamatan, $kodeKecamatan, $byName, $byCode, $byCompactName);

        if (!$kecamatan) {
            $unmatched[] = $namaKecamatan !== "" ? $namaKecamatan : $kodeKecamatan;
            $skipped++;
            continue;
        }

        $tahun = $tahunImport;

        if ($colTahun !== null) {
            $tahunFromFile = toInteger($row[$colTahun] ?? 0);

            if ($tahunFromFile >= 1901 && $tahunFromFile <= 2155) {
                $tahun = $tahunFromFile;
            }
        }

        $jumlahMiskin = toInteger($row[$colMiskin] ?? 0);
        $jumlahPendudukKecamatan = $colPendudukKecamatan ? toInteger($row[$colPendudukKecamatan] ?? 0) : 0;
        $jumlahPendudukKabupaten = $colPendudukKabupaten ? toInteger($row[$colPendudukKabupaten] ?? 0) : 0;

        $sumberData = $colSumber ? cleanString($row[$colSumber] ?? "") : "Template Sistem";

        if ($sumberData === "") {
            $sumberData = "Template Sistem";
        }

        $keterangan = $colKeterangan ? cleanString($row[$colKeterangan] ?? "") : "Import dari template sistem.";

        upsertDataKemiskinan(
            $koneksi,
            (int) $kecamatan["id_kecamatan"],
            $idAdmin,
            $tahun,
            $jumlahMiskin,
            $sumberData,
            $keterangan
        );

        if ($jumlahPendudukKecamatan > 0 || $jumlahPendudukKabupaten > 0) {
            upsertDataPenduduk(
                $koneksi,
                (int) $kecamatan["id_kecamatan"],
                $idAdmin,
                $tahun,
                $jumlahPendudukKecamatan,
                $jumlahPendudukKabupaten,
                $sumberData,
                "Import data penduduk dari template sistem."
            );

            $processedPenduduk++;
        }

        $processed++;
    }

    return [
        "processed" => $processed,
        "penduduk" => $processedPenduduk,
        "skipped" => $skipped,
        "unmatched" => array_values(array_unique($unmatched))
    ];
}

function importDtks(
    mysqli $koneksi,
    $spreadsheet,
    int $tahunImport,
    int $idAdmin,
    array $byName,
    array $byCode,
    array $byCompactName
) {
    $sheet = selectSheetByYear($spreadsheet, $tahunImport);
    $rows = $sheet->toArray(null, true, true, true);

    $currentKecamatan = "";
    $dataGrandTotal = [];
    $fallbackData = [];

    foreach ($rows as $row) {
        $colB = cleanString($row["B"] ?? "");
        $colC = cleanString($row["C"] ?? "");
        $colD = toInteger($row["D"] ?? 0);

        $normB = normalizeText($colB);
        $normC = normalizeText($colC);

        if ($normB === "" && $normC === "") {
            continue;
        }

        if (
            strpos($normB, "NAMA KECAMATAN") !== false ||
            strpos($normC, "NAMA DESA") !== false ||
            (
                strpos($normB, "KECAMATAN") !== false &&
                strpos($normC, "DESA") !== false
            )
        ) {
            continue;
        }

        if ($normB !== "" && strpos($normB, "GRAND TOTAL") === false && !is_numeric($colB)) {
            $currentKecamatan = $colB;
        }

        if (strpos($normC, "GRAND TOTAL") !== false || strpos($normB, "GRAND TOTAL") !== false) {
            if ($currentKecamatan !== "" && $colD > 0) {
                $dataGrandTotal[$currentKecamatan] = $colD;
            }

            continue;
        }

        if ($currentKecamatan !== "" && $colD > 0) {
            if (!isset($fallbackData[$currentKecamatan])) {
                $fallbackData[$currentKecamatan] = 0;
            }

            $fallbackData[$currentKecamatan] += $colD;
        }
    }

    $dataToImport = !empty($dataGrandTotal) ? $dataGrandTotal : $fallbackData;

    if (empty($dataToImport)) {
        throw new Exception("Data DTKS tidak ditemukan. Pastikan file memiliki kolom kecamatan dan baris GRAND TOTAL.");
    }

    $processed = 0;
    $skipped = 0;
    $unmatched = [];

    foreach ($dataToImport as $namaKecamatan => $jumlahMiskin) {
        $kecamatan = findKecamatan($namaKecamatan, "", $byName, $byCode, $byCompactName);

        if (!$kecamatan) {
            $unmatched[] = $namaKecamatan;
            $skipped++;
            continue;
        }

        upsertDataKemiskinan(
            $koneksi,
            (int) $kecamatan["id_kecamatan"],
            $idAdmin,
            $tahunImport,
            (int) $jumlahMiskin,
            "DTKS",
            "Import Excel DTKS tahun " . $tahunImport . "."
        );

        $processed++;
    }

    return [
        "processed" => $processed,
        "penduduk" => 0,
        "skipped" => $skipped,
        "unmatched" => array_values(array_unique($unmatched))
    ];
}

function importDtsen(
    mysqli $koneksi,
    $spreadsheet,
    int $tahunImport,
    int $idAdmin,
    array $byName,
    array $byCode,
    array $byCompactName
) {
    $sheet = $spreadsheet->getActiveSheet();
    $rows = $sheet->toArray(null, true, true, true);

    $currentKecamatan = "";
    $aggregate = [];
    $totalKabupaten = 0;

    foreach ($rows as $row) {
        $colA = cleanString($row["A"] ?? "");
        $colB = cleanString($row["B"] ?? "");

        $normA = normalizeText($colA);
        $normB = normalizeText($colB);

        if ($normA === "" && $normB === "") {
            continue;
        }

        if (
            strpos($normA, "KECAMATAN") !== false ||
            strpos($normB, "KELURAHAN") !== false ||
            strpos($normA, "NO") === 0
        ) {
            continue;
        }

        $jumlahIndividu = toInteger($row["D"] ?? 0);
        $desil1Individu = toInteger($row["F"] ?? 0);
        $desil2Individu = toInteger($row["H"] ?? 0);
        $desil3Individu = toInteger($row["J"] ?? 0);
        $desil4Individu = toInteger($row["L"] ?? 0);
        $desil5Individu = toInteger($row["N"] ?? 0);

        if (
            $normA === "TOTAL" ||
            $normA === "GRAND TOTAL" ||
            $normB === "TOTAL" ||
            $normB === "GRAND TOTAL"
        ) {
            if ($jumlahIndividu > 0) {
                $totalKabupaten = $jumlahIndividu;
            }

            continue;
        }

        if ($colA !== "") {
            $currentKecamatan = $colA;
        }

        if ($currentKecamatan === "") {
            continue;
        }

        $jumlahDesilSatuSampaiLima =
            $desil1Individu +
            $desil2Individu +
            $desil3Individu +
            $desil4Individu +
            $desil5Individu;

        if ($jumlahIndividu <= 0 && $jumlahDesilSatuSampaiLima <= 0) {
            continue;
        }

        if (!isset($aggregate[$currentKecamatan])) {
            $aggregate[$currentKecamatan] = [
                "jumlah_penduduk_kecamatan" => 0,
                "jumlah_penduduk_miskin" => 0
            ];
        }

        $aggregate[$currentKecamatan]["jumlah_penduduk_kecamatan"] += $jumlahIndividu;
        $aggregate[$currentKecamatan]["jumlah_penduduk_miskin"] += $jumlahDesilSatuSampaiLima;
    }

    if (empty($aggregate)) {
        throw new Exception("Data DTSEN tidak ditemukan. Pastikan file memiliki kolom Kecamatan, Jumlah Individu, dan Desil 1 sampai Desil 5.");
    }

    if ($totalKabupaten <= 0) {
        foreach ($aggregate as $item) {
            $totalKabupaten += (int) $item["jumlah_penduduk_kecamatan"];
        }
    }

    $processed = 0;
    $processedPenduduk = 0;
    $skipped = 0;
    $unmatched = [];

    foreach ($aggregate as $namaKecamatan => $data) {
        $kecamatan = findKecamatan($namaKecamatan, "", $byName, $byCode, $byCompactName);

        if (!$kecamatan) {
            $unmatched[] = $namaKecamatan;
            $skipped++;
            continue;
        }

        $jumlahMiskin = (int) $data["jumlah_penduduk_miskin"];
        $jumlahPendudukKecamatan = (int) $data["jumlah_penduduk_kecamatan"];

        upsertDataKemiskinan(
            $koneksi,
            (int) $kecamatan["id_kecamatan"],
            $idAdmin,
            $tahunImport,
            $jumlahMiskin,
            "DTSEN",
            "Import Excel DTSEN tahun " . $tahunImport . ". Jumlah miskin/rentan miskin dihitung dari individu Desil 1 sampai Desil 5."
        );

        upsertDataPenduduk(
            $koneksi,
            (int) $kecamatan["id_kecamatan"],
            $idAdmin,
            $tahunImport,
            $jumlahPendudukKecamatan,
            $totalKabupaten,
            "DTSEN",
            "Import jumlah penduduk dari total individu DTSEN tahun " . $tahunImport . "."
        );

        $processed++;
        $processedPenduduk++;
    }

    return [
        "processed" => $processed,
        "penduduk" => $processedPenduduk,
        "skipped" => $skipped,
        "unmatched" => array_values(array_unique($unmatched))
    ];
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    setImportFlash("error", "Akses import tidak valid.");
    redirectToDataKemiskinan();
}

$autoloadPath = __DIR__ . "/../vendor/autoload.php";

if (!file_exists($autoloadPath)) {
    setImportFlash("error", "Library PhpSpreadsheet belum tersedia. Jalankan: composer require phpoffice/phpspreadsheet");
    redirectToDataKemiskinan();
}

require_once $autoloadPath;

$action = $_POST["action"] ?? "";

if ($action !== "import_excel") {
    setImportFlash("error", "Action import tidak valid.");
    redirectToDataKemiskinan();
}

$idAdmin = (int) ($_SESSION["id_admin"] ?? 0);

if ($idAdmin <= 0) {
    setImportFlash("error", "Session admin tidak valid. Silakan login ulang.");
    redirectToDataKemiskinan();
}

$jenisImport = strtolower(trim($_POST["jenis_import"] ?? ""));
$tahunImport = (int) ($_POST["tahun_import"] ?? 0);

if (!in_array($jenisImport, ["dtks", "dtsen", "template"], true)) {
    setImportFlash("error", "Jenis data import tidak valid.");
    redirectToDataKemiskinan();
}

if ($tahunImport < 1901 || $tahunImport > 2155) {
    setImportFlash("error", "Tahun import tidak valid.");
    redirectToDataKemiskinan();
}

if (!isset($_FILES["file_excel"]) || $_FILES["file_excel"]["error"] !== UPLOAD_ERR_OK) {
    setImportFlash("error", "File Excel belum dipilih atau gagal diunggah.");
    redirectToDataKemiskinan();
}

$file = $_FILES["file_excel"];
$extension = strtolower(pathinfo($file["name"], PATHINFO_EXTENSION));
$allowedExtensions = ["xls", "xlsx"];

if (!in_array($extension, $allowedExtensions, true)) {
    setImportFlash("error", "Format file tidak valid. Gunakan file .xls atau .xlsx.");
    redirectToDataKemiskinan();
}

$maxSize = 15 * 1024 * 1024;

if ((int) $file["size"] > $maxSize) {
    setImportFlash("error", "Ukuran file terlalu besar. Maksimal 15 MB.");
    redirectToDataKemiskinan();
}

try {
    [$byName, $byCode, $byCompactName] = getKecamatanMaps($koneksi);

    if (empty($byName)) {
        throw new Exception("Data master kecamatan belum tersedia. Tambahkan kecamatan terlebih dahulu.");
    }

    $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($file["tmp_name"]);

    mysqli_begin_transaction($koneksi);

    if ($jenisImport === "dtks") {
        $result = importDtks(
            $koneksi,
            $spreadsheet,
            $tahunImport,
            $idAdmin,
            $byName,
            $byCode,
            $byCompactName
        );

        $label = "DTKS";
    } elseif ($jenisImport === "dtsen") {
        $result = importDtsen(
            $koneksi,
            $spreadsheet,
            $tahunImport,
            $idAdmin,
            $byName,
            $byCode,
            $byCompactName
        );

        $label = "DTSEN";
    } else {
        $result = importTemplateSistem(
            $koneksi,
            $spreadsheet,
            $tahunImport,
            $idAdmin,
            $byName,
            $byCode,
            $byCompactName
        );

        $label = "Template Sistem";
    }

    mysqli_commit($koneksi);

    $message = "Import Excel {$label} berhasil. ";
    $message .= "Data kemiskinan diproses: {$result["processed"]}. ";
    $message .= "Data penduduk diproses: {$result["penduduk"]}. ";
    $message .= "Data dilewati: {$result["skipped"]}.";

    if (!empty($result["unmatched"])) {
        $list = implode(", ", array_slice($result["unmatched"], 0, 8));
        $message .= " Kecamatan tidak cocok dengan master data: " . $list;

        if (count($result["unmatched"]) > 8) {
            $message .= ", dan lainnya.";
        }
    }

    setImportFlash("success", $message);
    redirectToDataKemiskinan();
} catch (Throwable $e) {
    if (mysqli_errno($koneksi) === 0) {
        mysqli_rollback($koneksi);
    } else {
        @mysqli_rollback($koneksi);
    }

    setImportFlash("error", "Import Excel gagal. " . $e->getMessage());
    redirectToDataKemiskinan();
}