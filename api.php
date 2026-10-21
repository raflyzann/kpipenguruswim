<?php
/**
 * ============================================================
 * Backend API KPI Pengurus Wahdah Islamiyah Makassar
 * Drop-in replacement untuk Code.gs (Google Apps Script)
 * Berbasis PHP + MySQL (Laragon & TablePlus)
 * ============================================================
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// ------------------------------------------------------------
// KONFIGURASI DATABASE
// ------------------------------------------------------------
define('DB_HOST', '127.0.0.1');
define('DB_NAME', 'db_kpi_wahdah');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_PORT', '3306');

define('UPLOAD_DIR_FOTO', __DIR__ . '/uploads/foto/');
define('UPLOAD_DIR_DOKUMEN', __DIR__ . '/uploads/dokumen/');

function getBaseUrl() {
    $scheme = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https://' : 'http://';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $scriptDir = dirname($_SERVER['SCRIPT_NAME'] ?? '/kpi-pengurus-wim');
    $scriptDir = rtrim(str_replace('\\', '/', $scriptDir), '/');
    return $scheme . $host . ($scriptDir ? $scriptDir . '/' : '/');
}

define('JWT_SECRET', 'kpi-wahdah-makassar-secret-key-2026');
define('SESSION_DURATION_SEC', 86400 * 7); // 7 hari
define('FORMAT_NOTULEN_URL', 'https://docs.google.com/document/d/contoh-format-notulen');

const KPI_SCORE_MAP = [
    'Rapat Rutin Departemen' => 5,
    'Taklim Rutin' => 5,
    'Keaktifan Tarbiyah' => 5,
    'Kegiatan Event' => 10,
    'Olahraga Rutin' => 5,
    'Pencapaian GSD' => 15,
    'Keaktifan Departemen' => 0
];

const KEAKTIFAN_DEPT_POIN = [
    'Aktif' => 100,
    'Kurang Aktif' => 50,
    'Tidak Aktif' => 0
];

// Pastikan folder upload ada
if (!is_dir(UPLOAD_DIR_FOTO)) {
    @mkdir(UPLOAD_DIR_FOTO, 0777, true);
}
if (!is_dir(UPLOAD_DIR_DOKUMEN)) {
    @mkdir(UPLOAD_DIR_DOKUMEN, 0777, true);
}

// ------------------------------------------------------------
// KONEKSI DATABASE
// ------------------------------------------------------------
function getDb() {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = 'mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NAME . ';charset=utf8mb4';
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ];
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
            seedInitialPengurusIfEmpty($pdo);
        } catch (PDOException $e) {
            echo json_encode([
                'success' => false,
                'message' => 'Gagal koneksi database MySQL: ' . $e->getMessage()
            ]);
            exit;
        }
    }
    return $pdo;
}

function seedInitialPengurusIfEmpty($pdo) {
    $count = (int)$pdo->query("SELECT COUNT(*) FROM pengurus")->fetchColumn();
    if ($count === 0) {
        $dummy = [
            ['Ahmad Fauzi', 'Departemen Dakwah', 'Anggota', '081234567890', 1, 0],
            ['Muhammad Rizal', 'Departemen Dakwah', 'Anggota', '081234567891', 0, 0],
            ['Abdullah Hakim', 'Departemen Pendidikan', 'Anggota', '081234567892', 1, 0],
            ['Ilham Maulana', 'Departemen Pendidikan', 'Anggota', '081234567893', 0, 0],
            ['Sultan Baharuddin', 'Departemen Sosial', 'Anggota', '081234567894', 1, 0],
            ['Rahmat Hidayat', 'Departemen Sosial', 'Anggota', '081234567895', 0, 0],
            ['Fajar Nur Ikhsan', 'Departemen Ekonomi', 'Anggota', '081234567896', 1, 0],
            ['Andi Muh. Yusuf', 'Departemen Ekonomi', 'Anggota', '081234567897', 0, 0],
            ['Zulkifli Amir', 'Departemen Kaderisasi', 'Anggota', '081234567898', 1, 0],
            ['Hasanuddin Rahman', 'Departemen Kaderisasi', 'Anggota', '081234567899', 0, 0],
            ['Muh. Iqbal Saputra', 'Departemen Humas', 'Anggota', '081234567800', 1, 0],
            ['Wahyu Ramadhan', 'Departemen Humas', 'Anggota', '081234567801', 0, 0],
            ['Taufik Akbar', 'Departemen Dakwah', 'Anggota', '081234567802', 0, 0],
            ['Syamsul Bahri', 'Departemen Pendidikan', 'Anggota', '081234567803', 0, 0],
            ['Arfan Nugraha', 'Departemen Sosial', 'Anggota', '081234567804', 0, 0],
            ['Muh. Alif Pratama', 'Departemen Ekonomi', 'Anggota', '081234567805', 0, 0],
            ['Reza Aditya', 'Departemen Kaderisasi', 'Anggota', '081234567806', 0, 0],
            ['Yusril Firmansyah', 'Departemen Humas', 'Anggota', '081234567807', 0, 0]
        ];
        $stmt = $pdo->prepare("INSERT INTO pengurus (nama, departemen, status, no_hp, sekretaris, ketua_departemen) VALUES (?, ?, ?, ?, ?, ?)");
        foreach ($dummy as $row) {
            $stmt->execute($row);
        }
    }
}

// ------------------------------------------------------------
// HELPER OTENTIKASI & TOKEN
// ------------------------------------------------------------
function normalisasiNoHp($v) {
    $s = preg_replace('/\D/', '', (string)$v);
    if (strpos($s, '62') === 0) {
        $s = substr($s, 2);
    }
    $s = ltrim($s, '0');
    return $s;
}

function generateToken($kunciHp, $exp) {
    $payload = $kunciHp . '.' . $exp;
    $sig = hash_hmac('sha256', 'tok:' . $payload, JWT_SECRET);
    return $payload . '.' . $sig;
}

function verifyToken($token) {
    if (!$token || !is_string($token)) return ['valid' => false];
    $parts = explode('.', $token);
    if (count($parts) !== 3) return ['valid' => false];

    $kunciHp = $parts[0];
    $exp = (int)$parts[1];
    $sig = $parts[2];

    if ($exp < time()) {
        return ['valid' => false, 'message' => 'Sesi berakhir'];
    }

    $expectedSig = hash_hmac('sha256', 'tok:' . $kunciHp . '.' . $exp, JWT_SECRET);
    if (!hash_equals($expectedSig, $sig)) {
        return ['valid' => false, 'message' => 'Token tidak valid'];
    }

    $pdo = getDb();
    $stmt = $pdo->prepare("SELECT * FROM pengurus WHERE no_hp IS NOT NULL AND no_hp != ''");
    $stmt->execute();
    $all = $stmt->fetchAll();

    $matched = [];
    foreach ($all as $p) {
        if (normalisasiNoHp($p['no_hp']) === $kunciHp) {
            $matched[] = $p;
        }
    }

    if (empty($matched)) {
        return ['valid' => false, 'message' => 'Akun tidak ditemukan'];
    }

    $nama = $matched[0]['nama'];
    $akun = array_map(function($a) {
        $akses = [];
        if (!empty($a['akses_departemen'])) {
            $akses = array_map('trim', preg_split('/[,;\n]+/', $a['akses_departemen']));
        }
        if (empty($akses) && !empty($a['departemen'])) {
            $akses = [$a['departemen']];
        }
        return [
            'nama' => $a['nama'],
            'departemen' => $a['departemen'],
            'status' => $a['status'] ?: 'Anggota',
            'sekretaris' => (bool)$a['sekretaris'],
            'ketuaDepartemen' => (bool)$a['ketua_departemen'],
            'aksesDepartemen' => $akses
        ];
    }, $matched);

    return [
        'valid' => true,
        'nama' => $nama,
        'kunciHp' => $kunciHp,
        'akun' => $akun,
        'exp' => $exp * 1000
    ];
}

function saveBase64File($base64Data, $originalName, $subDir, $urlPrefix) {
    if (!$base64Data) return '';
    $cleanName = preg_replace('/[^a-zA-Z0-9_\.-]/', '_', $originalName ?: 'file');
    $ext = pathinfo($cleanName, PATHINFO_EXTENSION);
    $filename = date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . ($ext ? '.' . $ext : '');
    $filePath = $subDir . $filename;

    $binary = base64_decode($base64Data);
    if ($binary === false) return '';

    file_put_contents($filePath, $binary);
    return $urlPrefix . $filename;
}

// ------------------------------------------------------------
// WHITELIST API FUNCTIONS
// ------------------------------------------------------------

function getPengurusData() {
    $pdo = getDb();
    $stmt = $pdo->query("SELECT nama, departemen, status FROM pengurus ORDER BY nama ASC");
    return $stmt->fetchAll();
}

function getDepartemenList() {
    $pdo = getDb();
    $stmt = $pdo->query("SELECT DISTINCT departemen FROM pengurus WHERE departemen IS NOT NULL AND departemen != '' ORDER BY departemen ASC");
    return $stmt->fetchAll(PDO::FETCH_COLUMN);
}

function getEventList() {
    $pdo = getDb();
    $stmt = $pdo->query("SELECT nama_event FROM event ORDER BY id ASC");
    return $stmt->fetchAll(PDO::FETCH_COLUMN);
}

function loginPengurus($noHp) {
    $kunci = normalisasiNoHp($noHp);
    if (strlen($kunci) < 8 || strlen($kunci) > 13) {
        return ['success' => false, 'message' => 'Format nomor HP tidak valid.'];
    }

    $pdo = getDb();
    $stmt = $pdo->query("SELECT * FROM pengurus WHERE no_hp IS NOT NULL AND no_hp != ''");
    $all = $stmt->fetchAll();

    $matched = [];
    foreach ($all as $p) {
        if (normalisasiNoHp($p['no_hp']) === $kunci) {
            $matched[] = $p;
        }
    }

    if (empty($matched)) {
        return ['success' => false, 'message' => 'Nomor HP tidak terdaftar sebagai pengurus. Hubungi admin untuk mendaftarkan nomor Anda.'];
    }

    $namaList = array_unique(array_map(function($a) { return strtolower($a['nama']); }, $matched));
    if (count($namaList) > 1) {
        return ['success' => false, 'message' => 'Nomor HP ini terdaftar pada lebih dari satu nama pengurus. Hubungi admin untuk merapikan data.'];
    }

    $expSec = time() + SESSION_DURATION_SEC;
    $token = generateToken($kunci, $expSec);
    $nama = $matched[0]['nama'];

    $akun = array_map(function($a) {
        $akses = [];
        if (!empty($a['akses_departemen'])) {
            $akses = array_map('trim', preg_split('/[,;\n]+/', $a['akses_departemen']));
        }
        if (empty($akses) && !empty($a['departemen'])) {
            $akses = [$a['departemen']];
        }
        return [
            'nama' => $a['nama'],
            'departemen' => $a['departemen'],
            'status' => $a['status'] ?: 'Anggota',
            'sekretaris' => (bool)$a['sekretaris'],
            'ketuaDepartemen' => (bool)$a['ketua_departemen'],
            'aksesDepartemen' => $akses
        ];
    }, $matched);

    $isSekretaris = false;
    foreach ($akun as $a) {
        if ($a['sekretaris']) { $isSekretaris = true; break; }
    }

    return [
        'success' => true,
        'token' => $token,
        'exp' => $expSec * 1000,
        'nama' => $nama,
        'akun' => $akun,
        'linkNotulen' => $isSekretaris ? FORMAT_NOTULEN_URL : ''
    ];
}

function cekSesiPengurus($token) {
    $ver = verifyToken($token);
    if (!$ver['valid']) {
        return ['success' => false, 'message' => 'Sesi login berakhir. Silakan login ulang.'];
    }
    $isSekretaris = false;
    foreach ($ver['akun'] as $a) {
        if ($a['sekretaris']) { $isSekretaris = true; break; }
    }

    return [
        'success' => true,
        'exp' => $ver['exp'],
        'nama' => $ver['nama'],
        'akun' => $ver['akun'],
        'linkNotulen' => $isSekretaris ? FORMAT_NOTULEN_URL : ''
    ];
}

function getRapatKeBerikutnya($departemen) {
    $pdo = getDb();
    $stmt = $pdo->prepare("SELECT MAX(rapat_ke) as maks FROM laporan WHERE jenis_kegiatan = 'Rapat Rutin Departemen' AND departemen = ?");
    $stmt->execute([$departemen]);
    $maks = (int)$stmt->fetchColumn();
    return [
        'rapatKe' => $maks + 1,
        'terakhir' => $maks
    ];
}

function validateAdminLogin($userId, $pin) {
    $pdo = getDb();
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM admin WHERE user_id = ? AND pin = ?");
    $stmt->execute([$userId, $pin]);
    return ((int)$stmt->fetchColumn()) > 0;
}

function submitLaporan($formData) {
    $pdo = getDb();
    $jenis = $formData['jenisKegiatan'] ?? '';
    if (!$jenis) {
        return ['success' => false, 'message' => 'Jenis kegiatan tidak valid.'];
    }

    $sesi = verifyToken($formData['sessionToken'] ?? '');
    if (!$sesi['valid']) {
        return ['success' => false, 'message' => 'Sesi login tidak valid atau sudah berakhir. Silakan login ulang dengan nomor HP.'];
    }

    $loginNama = $sesi['nama'];
    $departemen = trim($formData['departemen'] ?? '');

    // Simpan foto kegiatan jika ada
    $linkFoto = '';
    if (!empty($formData['fotoKegiatanBase64'])) {
        $linkFoto = saveBase64File($formData['fotoKegiatanBase64'], $formData['fotoKegiatanName'] ?? 'foto.jpg', UPLOAD_DIR_FOTO, getBaseUrl() . 'uploads/foto/');
    }

    // Simpan hasil rapat jika ada
    $linkNotulen = '';
    if (!empty($formData['fileHasilRapatBase64'])) {
        $linkNotulen = saveBase64File($formData['fileHasilRapatBase64'], $formData['fileHasilRapatName'] ?? 'notulen.pdf', UPLOAD_DIR_DOKUMEN, getBaseUrl() . 'uploads/dokumen/');
    }

    $linkParts = array_filter([$linkFoto, $linkNotulen]);
    $linkLampiran = implode(' | ', $linkParts);

    // Ambil status pengurus
    $stmtStatus = $pdo->prepare("SELECT status FROM pengurus WHERE nama = ? AND departemen = ? LIMIT 1");
    $stmtStatus->execute([$loginNama, $departemen]);
    $userStatus = $stmtStatus->fetchColumn() ?: 'Anggota';

    if ($jenis === 'Rapat Rutin Departemen') {
        $pesertaAbsensi = $formData['pesertaAbsensi'] ?? [];
        if (!is_array($pesertaAbsensi) || empty($pesertaAbsensi)) {
            return ['success' => false, 'message' => 'Daftar peserta absensi rapat tidak boleh kosong.'];
        }
        $rapatKe = (int)($formData['rapatKe'] ?? 1);
        $lokasiRapat = $formData['lokasiRapat'] ?? '';
        $tanggalRapat = $formData['tanggalRapat'] ?? date('Y-m-d');
        $skor = KPI_SCORE_MAP['Rapat Rutin Departemen'] ?? 5;

        $insertStmt = $pdo->prepare("INSERT INTO laporan (timestamp, jenis_kegiatan, nama, departemen, status, tanggal_kegiatan, rapat_ke, lokasi, skor_kpi, link_lampiran) VALUES (NOW(), ?, ?, ?, ?, ?, ?, ?, ?, ?)");

        foreach ($pesertaAbsensi as $peserta) {
            $peserta = trim($peserta);
            if (!$peserta) continue;
            // Ambil status peserta
            $stmtP = $pdo->prepare("SELECT status FROM pengurus WHERE nama = ? AND departemen = ? LIMIT 1");
            $stmtP->execute([$peserta, $departemen]);
            $pStatus = $stmtP->fetchColumn() ?: 'Anggota';

            $insertStmt->execute([
                $jenis, $peserta, $departemen, $pStatus, $tanggalRapat, $rapatKe, $lokasiRapat, $skor, $linkLampiran
            ]);
        }
        return ['success' => true, 'message' => 'Laporan Rapat Rutin berhasil disimpan.', 'skor' => $skor];

    } elseif ($jenis === 'Keaktifan Departemen') {
        $penilaian = $formData['penilaian'] ?? [];
        if (!is_array($penilaian) || empty($penilaian)) {
            return ['success' => false, 'message' => 'Data penilaian anggota tidak boleh kosong.'];
        }
        $tanggal = date('Y-m-d');
        $insertStmt = $pdo->prepare("INSERT INTO laporan (timestamp, jenis_kegiatan, nama, departemen, status, tanggal_kegiatan, saran_catatan, skor_kpi) VALUES (NOW(), ?, ?, ?, ?, ?, ?, ?)");

        foreach ($penilaian as $p) {
            $namaAnggota = trim($p['nama'] ?? '');
            if (!$namaAnggota || strtolower($namaAnggota) === strtolower($loginNama)) continue;
            $nilai = $p['nilai'] ?? 'Aktif';
            $poin = KEAKTIFAN_DEPT_POIN[$nilai] ?? 0;

            $stmtP = $pdo->prepare("SELECT status FROM pengurus WHERE nama = ? AND departemen = ? LIMIT 1");
            $stmtP->execute([$namaAnggota, $departemen]);
            $pStatus = $stmtP->fetchColumn() ?: 'Anggota';

            $insertStmt->execute([
                $jenis, $namaAnggota, $departemen, $pStatus, $tanggal, $nilai, $poin
            ]);
        }
        return ['success' => true, 'message' => 'Penilaian Keaktifan Departemen berhasil disimpan.'];

    } else {
        $skor = KPI_SCORE_MAP[$jenis] ?? 0;
        $tanggalKegiatan = $formData['tanggalKegiatan'] ?? date('Y-m-d');
        $lokasi = $formData['lokasiTaklim'] ?? '';
        $pemateri = $formData['pemateriTaklim'] ?? '';
        $namaEvent = is_array($formData['namaEvent'] ?? null) ? implode(', ', $formData['namaEvent']) : ($formData['namaEvent'] ?? null);
        $jenisOlahraga = $formData['jenisOlahraga'] ?? null;
        $namaHasilGsd = $formData['namaHasilGSD'] ?? null;
        $kategoriPembinaan = $formData['kategoriPembinaan'] ?? null;
        $noHpGsd = $formData['noHpGSD'] ?? null;
        $saranCatatan = $formData['saranCatatan'] ?? null;

        $insertStmt = $pdo->prepare("INSERT INTO laporan (
            timestamp, jenis_kegiatan, nama, departemen, status, tanggal_kegiatan,
            lokasi, pemateri_taklim, nama_event, jenis_olahraga,
            nama_hasil_gsd, kategori_pembinaan, no_hp_hasil_gsd, saran_catatan,
            skor_kpi, link_lampiran
        ) VALUES (NOW(), ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

        $insertStmt->execute([
            $jenis, $loginNama, $departemen, $userStatus, $tanggalKegiatan,
            $lokasi, $pemateri, $namaEvent, $jenisOlahraga,
            $namaHasilGsd, $kategoriPembinaan, $noHpGsd, $saranCatatan,
            $skor, $linkLampiran
        ]);

        return ['success' => true, 'message' => 'Laporan berhasil disimpan.', 'skor' => $skor];
    }
}

function getRiwayatSaya($token, $page = 1, $pageSize = 20) {
    $sesi = verifyToken($token);
    if (!$sesi['valid']) {
        return ['success' => false, 'code' => 'UNAUTHENTICATED', 'message' => 'Sesi login berakhir. Silakan login ulang.'];
    }

    $pdo = getDb();
    $nama = $sesi['nama'];

    $stmt = $pdo->prepare("SELECT * FROM laporan WHERE nama = ? ORDER BY tanggal_kegiatan DESC, id DESC");
    $stmt->execute([$nama]);
    $rows = $stmt->fetchAll();

    $data = [];
    foreach ($rows as $row) {
        $jenis = $row['jenis_kegiatan'];
        $detail = [];
        if ($jenis === 'Taklim Rutin') {
            $detail = ['Lokasi Taklim' => $row['lokasi'] ?: '', 'Pemateri' => $row['pemateri_taklim'] ?: '', 'Catatan' => $row['saran_catatan'] ?: ''];
        } elseif ($jenis === 'Keaktifan Tarbiyah') {
            $detail = ['Lokasi Tarbiyah' => $row['lokasi'] ?: '', 'Murobbi' => $row['pemateri_taklim'] ?: '', 'Catatan' => $row['saran_catatan'] ?: ''];
        } elseif ($jenis === 'Kegiatan Event') {
            $detail = ['Nama Event' => $row['nama_event'] ?: '', 'Catatan' => $row['saran_catatan'] ?: ''];
        } elseif ($jenis === 'Olahraga Rutin') {
            $detail = ['Jenis Olahraga' => $row['jenis_olahraga'] ?: '', 'Catatan' => $row['saran_catatan'] ?: ''];
        } elseif ($jenis === 'Pencapaian GSD') {
            $detail = ['Nama Hasil GSD' => $row['nama_hasil_gsd'] ?: '', 'Kategori Pembinaan' => $row['kategori_pembinaan'] ?: '', 'No. HP' => $row['no_hp_hasil_gsd'] ?: ''];
        } elseif ($jenis === 'Keaktifan Departemen') {
            $detail = ['Penilaian' => $row['saran_catatan'] ?: ''];
        } elseif ($jenis === 'Rapat Rutin Departemen') {
            $detail = ['Rapat Ke-' => $row['rapat_ke'] ?: '', 'Lokasi Rapat' => $row['lokasi'] ?: ''];
        }

        $data[] = [
            'tanggalKegiatan' => substr($row['tanggal_kegiatan'], 0, 10),
            'jenis' => $row['jenis_kegiatan'],
            'nama' => $row['nama'],
            'departemen' => $row['departemen'],
            'status' => $row['status'] ?: '',
            'skor' => (int)$row['skor_kpi'],
            'linkLampiran' => $row['link_lampiran'] ?: '',
            'detail' => $detail
        ];
    }

    return [
        'success' => true,
        'data' => $data,
        'total' => count($data),
        'page' => 1,
        'pageSize' => count($data)
    ];
}

function getRiwayatLaporanAdmin($userId, $pin, $page = 1, $pageSize = 20) {
    if (!validateAdminLogin($userId, $pin)) {
        return ['success' => false, 'message' => 'User ID atau PIN salah.'];
    }

    $pdo = getDb();
    $stmt = $pdo->query("SELECT * FROM laporan ORDER BY tanggal_kegiatan DESC, id DESC");
    $rows = $stmt->fetchAll();

    $data = [];
    foreach ($rows as $row) {
        $jenis = $row['jenis_kegiatan'];
        $detail = [];
        if ($jenis === 'Taklim Rutin') {
            $detail = ['Lokasi Taklim' => $row['lokasi'] ?: '', 'Pemateri' => $row['pemateri_taklim'] ?: '', 'Catatan' => $row['saran_catatan'] ?: ''];
        } elseif ($jenis === 'Keaktifan Tarbiyah') {
            $detail = ['Lokasi Tarbiyah' => $row['lokasi'] ?: '', 'Murobbi' => $row['pemateri_taklim'] ?: '', 'Catatan' => $row['saran_catatan'] ?: ''];
        } elseif ($jenis === 'Kegiatan Event') {
            $detail = ['Nama Event' => $row['nama_event'] ?: '', 'Catatan' => $row['saran_catatan'] ?: ''];
        } elseif ($jenis === 'Olahraga Rutin') {
            $detail = ['Jenis Olahraga' => $row['jenis_olahraga'] ?: '', 'Catatan' => $row['saran_catatan'] ?: ''];
        } elseif ($jenis === 'Pencapaian GSD') {
            $detail = ['Nama Hasil GSD' => $row['nama_hasil_gsd'] ?: '', 'Kategori Pembinaan' => $row['kategori_pembinaan'] ?: '', 'No. HP' => $row['no_hp_hasil_gsd'] ?: ''];
        } elseif ($jenis === 'Keaktifan Departemen') {
            $detail = ['Penilaian' => $row['saran_catatan'] ?: ''];
        } elseif ($jenis === 'Rapat Rutin Departemen') {
            $detail = ['Rapat Ke-' => $row['rapat_ke'] ?: '', 'Lokasi Rapat' => $row['lokasi'] ?: ''];
        }

        $data[] = [
            'tanggalKegiatan' => substr($row['tanggal_kegiatan'], 0, 10),
            'jenis' => $row['jenis_kegiatan'],
            'nama' => $row['nama'],
            'departemen' => $row['departemen'],
            'status' => $row['status'] ?: '',
            'skor' => (int)$row['skor_kpi'],
            'linkLampiran' => $row['link_lampiran'] ?: '',
            'detail' => $detail
        ];
    }

    return [
        'success' => true,
        'data' => $data,
        'total' => count($data),
        'page' => (int)$page,
        'pageSize' => (int)$pageSize
    ];
}

function getDashboardData($userId, $pin, $startDateStr = null, $endDateStr = null) {
    if (!validateAdminLogin($userId, $pin)) {
        return ['success' => false, 'message' => 'User ID atau PIN salah.'];
    }

    $pdo = getDb();
    $sql = "SELECT * FROM laporan WHERE 1=1";
    $params = [];

    if (!empty($startDateStr)) {
        $sql .= " AND tanggal_kegiatan >= ?";
        $params[] = substr($startDateStr, 0, 10);
    }
    if (!empty($endDateStr)) {
        $sql .= " AND tanggal_kegiatan <= ?";
        $params[] = substr($endDateStr, 0, 10);
    }
    $sql .= " ORDER BY tanggal_kegiatan DESC, id DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    $rekap = [];
    foreach ($rows as $row) {
        $nama = $row['nama'];
        if (!$nama) continue;
        $departemen = $row['departemen'];
        $skor = (int)$row['skor_kpi'];
        $jenis = $row['jenis_kegiatan'];
        $tgl = substr($row['tanggal_kegiatan'], 0, 10);

        if (!isset($rekap[$nama])) {
            $rekap[$nama] = [
                'nama' => $nama,
                'departemenSet' => [],
                'totalSkor' => 0,
                'jumlahKegiatan' => 0,
                'detail' => []
            ];
        }
        if ($departemen) {
            $rekap[$nama]['departemenSet'][$departemen] = true;
        }
        $rekap[$nama]['totalSkor'] += $skor;
        $rekap[$nama]['jumlahKegiatan'] += 1;
        $rekap[$nama]['detail'][] = [
            'jenis' => $jenis,
            'tanggal' => date('d/m/Y', strtotime($tgl)),
            'skor' => $skor,
            'timestamp' => strtotime($row['timestamp']) * 1000
        ];
    }

    $leaderboard = [];
    foreach ($rekap as $item) {
        $item['departemen'] = implode(', ', array_keys($item['departemenSet']));
        unset($item['departemenSet']);
        $leaderboard[] = $item;
    }

    usort($leaderboard, function($a, $b) {
        return $b['totalSkor'] - $a['totalSkor'];
    });

    return [
        'success' => true,
        'leaderboard' => $leaderboard
    ];
}

function getRekapRapatDepartemen($userId, $pin, $startDateStr = null, $endDateStr = null) {
    if (!validateAdminLogin($userId, $pin)) {
        return ['success' => false, 'message' => 'User ID atau PIN salah.'];
    }

    $pdo = getDb();
    $departemenList = getDepartemenList();
    $rekap = [];
    foreach ($departemenList as $dep) {
        $rekap[$dep] = 0;
    }

    $sql = "SELECT DISTINCT departemen, rapat_ke FROM laporan WHERE jenis_kegiatan = 'Rapat Rutin Departemen'";
    $params = [];
    if (!empty($startDateStr)) {
        $sql .= " AND tanggal_kegiatan >= ?";
        $params[] = substr($startDateStr, 0, 10);
    }
    if (!empty($endDateStr)) {
        $sql .= " AND tanggal_kegiatan <= ?";
        $params[] = substr($endDateStr, 0, 10);
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    foreach ($rows as $r) {
        $dep = $r['departemen'];
        if (isset($rekap[$dep])) {
            $rekap[$dep]++;
        } else {
            $rekap[$dep] = 1;
        }
    }

    $rekapRapat = [];
    foreach ($rekap as $dep => $jml) {
        $rekapRapat[] = [
            'departemen' => $dep,
            'jumlahRapat' => $jml
        ];
    }

    usort($rekapRapat, function($a, $b) {
        if ($b['jumlahRapat'] !== $a['jumlahRapat']) {
            return $b['jumlahRapat'] - $a['jumlahRapat'];
        }
        return strcmp($a['departemen'], $b['departemen']);
    });

    return [
        'success' => true,
        'rekapRapat' => $rekapRapat
    ];
}

// ------------------------------------------------------------
// DISPATCHER ROUTER
// ------------------------------------------------------------
$rawInput = file_get_contents('php://input');
$body = json_decode($rawInput, true);

$fnName = $body['fn'] ?? $_GET['fn'] ?? $_POST['fn'] ?? '';
$args = $body['args'] ?? [];
if (!is_array($args)) {
    $args = [];
}

$whitelist = [
    'getPengurusData',
    'getDepartemenList',
    'getEventList',
    'loginPengurus',
    'cekSesiPengurus',
    'getRapatKeBerikutnya',
    'submitLaporan',
    'getRiwayatSaya',
    'validateAdminLogin',
    'getDashboardData',
    'getRekapRapatDepartemen',
    'getRiwayatLaporanAdmin'
];

if (!in_array($fnName, $whitelist, true)) {
    echo json_encode([
        'success' => false,
        'message' => 'Fungsi tidak dikenal atau tidak diizinkan: ' . $fnName
    ]);
    exit;
}

try {
    $result = call_user_func_array($fnName, $args);
    echo json_encode([
        'success' => true,
        'result' => $result
    ]);
} catch (Throwable $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Server Error: ' . $e->getMessage()
    ]);
}
