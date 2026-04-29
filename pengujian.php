<?php
// ============================================================
//  pengujian.php — Pusat Manajemen Pengujian Sampel
//  UPDATE: Tambah fungsi edit hasil uji
// ============================================================
session_start();
require_once __DIR__ . '/config/db.php';
cekLogin();
$pageTitle = 'Pengujian';

$msg = $_SESSION['msg'] ?? ''; unset($_SESSION['msg']);
$tab = $_GET['tab'] ?? 'hasil';

// ── Pre-fill dari sampel.php (klik tombol Uji) ───────────────
$preSampelId = (int)($_GET['sampel_id'] ?? 0);

// ── Data untuk form ──────────────────────────────────────────
// Sampel aktif — exclude yang sudah ada hasil_uji
$sampelAktif = $pdo->query("
    SELECT s.id, s.kode_sampel, s.jenis_material, s.klien,
           rec.nomor_penerimaan,
           CONCAT(s.kode_sampel,
               CASE WHEN rec.nomor_penerimaan IS NOT NULL
                    THEN CONCAT(' [', rec.nomor_penerimaan, ']')
                    ELSE '' END
           ) AS label_lengkap
    FROM sampel s
    LEFT JOIN penerimaan_sampel rec ON s.penerimaan_id = rec.id
    WHERE s.status IN ('antrian','diuji')
      AND s.id NOT IN (SELECT DISTINCT sampel_id FROM hasil_uji)
    ORDER BY rec.nomor_penerimaan, s.kode_sampel
")->fetchAll();

// Kelompokkan sampel per batch untuk dropdown grouped
$sampelGrouped = [];
foreach ($sampelAktif as $s) {
    $grp = $s['nomor_penerimaan'] ?? 'Tanpa Batch';
    $sampelGrouped[$grp][] = $s;
}

$alatList   = $pdo->query("SELECT id, kode_alat, nama FROM peralatan WHERE status='tersedia' ORDER BY nama")->fetchAll();
$analisList = $pdo->query("SELECT id, nama FROM pengguna WHERE role IN('admin','analis') AND status='aktif'")->fetchAll();

// ── Daftar WO aktif — untuk selector batch di tab input & batch ──
$woAktifList = $pdo->query("
    SELECT w.id, w.nomor_wo, w.metode, w.parameter, w.prioritas,
           rec.nomor_penerimaan, rec.klien,
           COUNT(wos.sampel_id) AS jumlah_sampel
    FROM work_order w
    LEFT JOIN penerimaan_sampel rec ON w.penerimaan_id = rec.id
    LEFT JOIN work_order_sampel wos ON wos.wo_id = w.id
    WHERE w.status = 'aktif'
    GROUP BY w.id
    ORDER BY FIELD(w.prioritas,'urgent','tinggi','normal'), w.jadwal_mulai ASC
")->fetchAll();

// ── Daftar hasil uji (dengan filter) ────────────────────────
$fSampel = trim($_GET['fsampel'] ?? '');
$fKes    = $_GET['fkes']    ?? '';
$fBatch  = trim($_GET['fbatch']  ?? '');

$sqlH = "SELECT h.*, s.kode_sampel, s.jenis_material, s.klien,
                rec.nomor_penerimaan,
                p.nama AS analis_nama,
                alat.nama AS alat_nama
         FROM hasil_uji h
         JOIN sampel s ON h.sampel_id = s.id
         LEFT JOIN penerimaan_sampel rec ON s.penerimaan_id = rec.id
         LEFT JOIN pengguna p ON h.analis_id = p.id
         LEFT JOIN peralatan alat ON h.alat_id = alat.id
         WHERE 1=1";
$prmH = [];
if ($fSampel) { $sqlH.=" AND (s.kode_sampel LIKE ? OR s.klien LIKE ? OR h.parameter LIKE ?)"; $prmH=array_merge($prmH,["%$fSampel%","%$fSampel%","%$fSampel%"]); }
if ($fKes)    { $sqlH.=" AND h.kesimpulan=?"; $prmH[]=$fKes; }
if ($fBatch)  { $sqlH.=" AND rec.nomor_penerimaan=?"; $prmH[]=$fBatch; }
$sqlH .= " ORDER BY h.created_at DESC";
$stH  = $pdo->prepare($sqlH); $stH->execute($prmH);
$hasilList = $stH->fetchAll();

$total  = count($hasilList);
$lulus  = count(array_filter($hasilList, fn($r)=>$r['kesimpulan']==='lulus'));
$tLulus = count(array_filter($hasilList, fn($r)=>$r['kesimpulan']==='tidak_lulus'));
$pct    = $total>0 ? round($lulus/$total*100) : 0;

// ── Daftar batch untuk filter ────────────────────────────────
$batchAll = $pdo->query("SELECT nomor_penerimaan, klien FROM penerimaan_sampel ORDER BY created_at DESC")->fetchAll();

$metodeOpts = ['AAS','XRF','ICP-OES','Gravimetri','Fire Assay','Volumetri'];
$satuanOpts = ['g/t','%','mg/L','ppm','ppb','mg/kg'];

// Cek apakah user bisa edit (semua role bisa edit pengujian)
$canEdit = canEditPengujian();

require_once __DIR__ . '/includes/header.php';
?>

<style>
.tabs{display:flex;gap:4px;margin-bottom:16px;border-bottom:1px solid var(--border);}
.tab-btn{padding:8px 18px;background:none;border:none;border-bottom:3px solid transparent;color:var(--text3);font-size:.82rem;cursor:pointer;font-weight:600;transition:.2s;margin-bottom:-1px;}
.tab-btn:hover{color:var(--text2);}
.tab-btn.active{color:var(--gold);border-bottom-color:var(--gold);}
.tab-pane{display:none;}.tab-pane.active{display:block;}

/* Badge batch */
.batch-badge-sm{display:inline-block;background:#162e1a;color:var(--green);border:1px solid var(--green3);border-radius:10px;font-size:.65rem;padding:1px 7px;}

/* Highlight batch info di form */
.batch-info-box{background:#0d2318;border:1px solid var(--green3);border-radius:6px;padding:8px 12px;margin-bottom:10px;font-size:.78rem;display:none;}
.batch-info-box.show{display:block;}
.batch-info-row{display:flex;gap:16px;flex-wrap:wrap;}
.batch-info-item .lbl{font-size:.68rem;color:var(--text3);}
.batch-info-item .val{color:var(--gold);font-weight:700;}

/* No. Ref highlight */
.ref-badge{display:inline-block;background:#1a2e0a;color:#a8d58a;border:1px solid #2e5018;border-radius:4px;font-size:.68rem;padding:1px 8px;font-family:monospace;}

/* Modal Edit */
.modal-edit {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0,0,0,0.7);
    z-index: 1000;
    justify-content: center;
    align-items: center;
}
.modal-edit-content {
    background: var(--bg2);
    border-radius: 12px;
    width: 550px;
    max-width: 90%;
    padding: 20px;
    border: 1px solid var(--gold);
    box-shadow: 0 4px 20px rgba(0,0,0,0.3);
}
.modal-edit-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 16px;
    padding-bottom: 10px;
    border-bottom: 1px solid var(--border);
}
.modal-edit-header h3 {
    color: var(--gold);
    margin: 0;
}
.modal-edit-close {
    background: none;
    border: none;
    color: var(--text3);
    font-size: 1.5rem;
    cursor: pointer;
}
.modal-edit-close:hover {
    color: var(--red);
}
.modal-edit-footer {
    margin-top: 20px;
    display: flex;
    justify-content: flex-end;
    gap: 10px;
}
.form-control {
    width: 100%;
    background: var(--bg3);
    border: 1px solid var(--border);
    color: var(--text);
    padding: 8px 10px;
    border-radius: 6px;
    font-size: .8rem;
    outline: none;
}
.form-control:focus {
    border-color: var(--gold);
}
.form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 12px;
    margin-bottom: 12px;
}
.form-group {
    margin-bottom: 12px;
}
.form-group label {
    display: block;
    font-size: .75rem;
    color: var(--text3);
    margin-bottom: 4px;
}
</style>

<div class="sec-title">Pengujian Sampel</div>

<?php if ($msg): ?>
    <div class="alert-box <?= str_starts_with($msg,'ERROR')?'alert-red':'alert-green' ?>" style="margin-bottom:14px">
        <?= str_starts_with($msg,'ERROR')?'&#9888;':'&#10003;' ?> <?= bersihkan($msg) ?>
    </div>
<?php endif; ?>

<!-- TABS -->
<div class="tabs">
    <button class="tab-btn <?= $tab==='hasil'?'active':'' ?>"  onclick="switchTab('hasil',this)">&#128300; Hasil Uji</button>
    <button class="tab-btn <?= $tab==='batch'?'active':'' ?>"  onclick="switchTab('batch',this)">&#128230; Input Batch Uji</button>
    <button class="tab-btn <?= $tab==='stat'?'active':'' ?>"   onclick="switchTab('stat',this)">&#128202; Statistik</button>
</div>

<!-- ══════════════════════════════════════════════
     TAB 1 — DAFTAR HASIL UJI
══════════════════════════════════════════════ -->
<div id="tab-hasil" class="tab-pane <?= $tab==='hasil'?'active':'' ?>">

    <!-- Filter -->
    <form method="GET" style="display:flex;gap:8px;margin-bottom:12px;flex-wrap:wrap">
        <input type="hidden" name="tab" value="hasil"/>
        <input name="fsampel" value="<?= bersihkan($fSampel) ?>" placeholder="&#128269; Cari kode / klien / parameter..."
               style="flex:1;min-width:180px;background:var(--bg3);border:1px solid var(--border);color:var(--text);padding:7px 12px;border-radius:6px;font-size:.8rem;outline:none"/>
        <select name="fkes" style="background:var(--bg3);border:1px solid var(--border);color:var(--text);padding:7px 10px;border-radius:6px;font-size:.8rem;outline:none">
            <option value="">Semua Kesimpulan</option>
            <option value="lulus" <?= $fKes==='lulus'?'selected':'' ?>>&#10003; Lulus</option>
            <option value="tidak_lulus" <?= $fKes==='tidak_lulus'?'selected':'' ?>>&#10007; Tidak Lulus</option>
            <option value="pending" <?= $fKes==='pending'?'selected':'' ?>>Pending</option>
        </select>
        <select name="fbatch" style="background:var(--bg3);border:1px solid var(--border);color:var(--text);padding:7px 10px;border-radius:6px;font-size:.8rem;outline:none">
            <option value="">Semua Batch</option>
            <?php foreach ($batchAll as $b): ?>
                <option value="<?= bersihkan($b['nomor_penerimaan']) ?>" <?= $fBatch===$b['nomor_penerimaan']?'selected':'' ?>><?= bersihkan($b['nomor_penerimaan']) ?> — <?= bersihkan($b['klien']) ?></option>
            <?php endforeach; ?>
        </select>
        <button type="submit" class="btn btn-green btn-sm">Terapkan</button>
        <a href="?tab=hasil" class="btn btn-sm" style="background:var(--bg3);color:var(--text2);border:1px solid var(--border)">Reset</a>
        <a href="<?= BASE_URL ?>/exports/export_excel.php" class="btn btn-green btn-sm">&#128202; Excel</a>
        <a href="<?= BASE_URL ?>/exports/export_pdf.php" target="_blank" class="btn btn-red btn-sm">&#128196; PDF</a>
    </form>

    <!-- Tabel hasil uji -->
    <div class="card" style="margin-bottom:16px">
        <div class="card-title">&#128300; Hasil Uji Kimia &amp; Mineral <span><?= $total ?> data</span></div>
        <div style="overflow-x:auto">
        <table class="data-table">
            <thead>
                    <th>ID Uji</th>
                    <th>No. Referensi Batch</th>
                    <th>Sampel</th>
                    <th>Material</th>
                    <th>Klien</th>
                    <th>Parameter</th>
                    <th>Hasil</th>
                    <th>Satuan</th>
                    <th>Metode</th>
                    <th>Alat</th>
                    <th>Analis</th>
                    <th>Tgl Uji</th>
                    <th>Kesimpulan</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($hasilList as $h): ?>
            <tr>
                <td style="color:var(--text3);font-size:.75rem"><?= bersihkan($h['kode_uji']) ?></td>
                <td>
                    <?php if ($h['nomor_penerimaan']): ?>
                        <span class="ref-badge">&#128230; <?= bersihkan($h['nomor_penerimaan']) ?></span>
                    <?php else: ?>
                        <span style="color:var(--text3);font-size:.75rem">—</span>
                    <?php endif; ?>
                </td>
                <td><strong style="color:var(--gold)"><?= bersihkan($h['kode_sampel']) ?></strong></td>
                <td><?= bersihkan($h['jenis_material']) ?></td>
                <td style="font-size:.75rem"><?= bersihkan($h['klien']) ?></td>
                <td><strong><?= bersihkan($h['parameter']) ?></strong></td>
                <td><strong><?= $h['nilai'] ?></strong></td>
                <td><?= bersihkan($h['satuan']) ?></td>
                <td><?= bersihkan($h['metode']) ?></td>
                <td style="font-size:.75rem"><?= bersihkan($h['alat_nama'] ?? '—') ?></td>
                <td style="font-size:.75rem"><?= bersihkan($h['analis_nama'] ?? '—') ?></td>
                <td style="font-size:.75rem"><?= $h['tanggal_uji'] ? date('d/m/Y',strtotime($h['tanggal_uji'])) : '—' ?></td>
                <td><?= badgeStatus($h['kesimpulan']) ?></td>
                <td style="white-space:nowrap">
                    <?php if ($canEdit): ?>
                        <button onclick="openEditModal(<?= $h['id'] ?>)" 
                                class="btn btn-gold btn-sm" 
                                style="font-size:.68rem;padding:3px 8px"
                                title="Edit hasil uji">
                            ✏️ Edit
                        </button>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php if (!$hasilList): ?>
                <tr><td colspan="14" style="text-align:center;color:var(--text3);padding:24px">Belum ada data.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
        </div>
    </div>

    <!-- Ringkasan -->
    <div class="grid2">
        <div class="card">
            <div class="card-title">&#128202; Distribusi Hasil</div>
            <div class="prog-wrap" style="margin-bottom:10px"><div class="prog-label"><span>&#10003; Lulus</span><span><?= $lulus ?> (<?= $pct ?>%)</span></div><div class="prog"><div class="prog-bar pb-green" style="width:<?= $pct ?>%"></div></div></div>
            <div class="prog-wrap" style="margin-bottom:10px"><div class="prog-label"><span>&#10007; Tidak Lulus</span><span><?= $tLulus ?></span></div><div class="prog"><div class="prog-bar pb-red" style="width:<?= $total>0?round($tLulus/$total*100):0 ?>%"></div></div></div>
            <div class="prog-wrap"><div class="prog-label"><span>&#8987; Pending</span><span><?= $total-$lulus-$tLulus ?></span></div><div class="prog"><div class="prog-bar pb-yellow" style="width:<?= $total>0?round(($total-$lulus-$tLulus)/$total*100):0 ?>%"></div></div></div>
        </div>
        <div class="card">
            <div class="card-title">&#128230; Ringkasan per Batch</div>
            <?php
            $batchStat = $pdo->query("
                SELECT rec.nomor_penerimaan, rec.klien,
                       COUNT(h.id) AS total,
                       SUM(h.kesimpulan='lulus') AS lulus,
                       SUM(h.kesimpulan='tidak_lulus') AS tlulus
                FROM hasil_uji h
                JOIN sampel s ON h.sampel_id=s.id
                JOIN penerimaan_sampel rec ON s.penerimaan_id=rec.id
                GROUP BY rec.id ORDER BY rec.created_at DESC LIMIT 5
            ")->fetchAll();
            if ($batchStat): ?>
            <table class="data-table">
                <thead><tr><th>No. Referensi</th><th>Klien</th><th>Total Uji</th><th>Lulus</th><th>Tdk Lulus</th></tr></thead>
                <tbody>
                <?php foreach ($batchStat as $bs): ?>
                <tr>
                    <td><span class="ref-badge"><?= bersihkan($bs['nomor_penerimaan']) ?></span></td>
                    <td style="font-size:.75rem"><?= bersihkan($bs['klien']) ?></td>
                    <td style="text-align:center"><?= $bs['total'] ?></td>
                    <td style="text-align:center;color:var(--green)"><?= $bs['lulus'] ?></td>
                    <td style="text-align:center;color:var(--red)"><?= $bs['tlulus'] ?></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php else: ?><div style="color:var(--text3);font-size:.78rem;text-align:center;padding:14px">Belum ada data batch.</div><?php endif; ?>
        </div>
    </div>
</div>

<!-- ══════════════════════════════════════════════
     TAB 2 — INPUT BATCH UJI
══════════════════════════════════════════════ -->
<div id="tab-batch" class="tab-pane <?= $tab==='batch'?'active':'' ?>">
    <div class="card">
        <div class="card-title">&#128230; Input Batch Hasil Uji</div>
        <p style="font-size:.78rem;color:var(--text3);margin-bottom:14px">
            Pilih <strong style="color:var(--gold)">Work Order</strong> — baris otomatis muncul sesuai
            <strong style="color:var(--gold)">jumlah sampel × jumlah parameter</strong> yang terdaftar di WO.
            Atau pilih No. Referensi Batch / tambah baris manual.
        </p>

        <!-- Pilih WO (utama) atau Batch Ref (alternatif) -->
        <div class="form-row" style="margin-bottom:14px">
            <div class="form-group">
                <label>&#128203; Pilih Work Order <small style="color:var(--text3)">(auto sampel × parameter)</small></label>
                <select id="batchWoSel" onchange="loadBatchFromWo(this)">
                    <option value="">— Pilih WO atau isi manual —</option>
                    <?php foreach ($woAktifList as $wo): ?>
                        <option value="<?= $wo['id'] ?>"
                                data-ref="<?= bersihkan($wo['nomor_penerimaan'] ?? '') ?>"
                                data-metode="<?= bersihkan($wo['metode'] ?? '') ?>"
                                data-param="<?= bersihkan($wo['parameter'] ?? '') ?>">
                            <?= bersihkan($wo['nomor_wo']) ?>
                            <?php if ($wo['nomor_penerimaan']): ?>
                                — <?= bersihkan($wo['nomor_penerimaan']) ?>
                            <?php endif; ?>
                            (<?= $wo['jumlah_sampel'] ?> sampel<?= $wo['parameter'] ? ' · '.bersihkan($wo['parameter']) : '' ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>&#128230; atau No. Referensi Batch</label>
                <select id="batchRefSel" onchange="loadBatchSampel(this.value)">
                    <option value="">— Pilih Batch atau isi manual —</option>
                    <?php foreach ($batchAll as $b): ?>
                        <option value="<?= bersihkan($b['nomor_penerimaan']) ?>" data-klien="<?= bersihkan($b['klien']) ?>">
                            <?= bersihkan($b['nomor_penerimaan']) ?> — <?= bersihkan($b['klien']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Parameter manual <small style="color:var(--text3)">(opsional)</small></label>
                <input id="batchParam" placeholder="Au, Fe, Ni..."/>
            </div>
        </div>

        <form method="POST" action="<?= BASE_URL ?>/actions/simpan_hasil_uji_batch.php">
            <div style="border:1px solid var(--border);border-radius:8px;padding:14px;margin-bottom:14px">
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px">
                    <span style="font-size:.8rem;font-weight:600;color:var(--gold)">&#128300; Baris Hasil Uji</span>
                    <button type="button" class="btn btn-green btn-sm" onclick="tambahBarisUji()">&#10133; Tambah Baris</button>
                </div>
                <div style="overflow-x:auto">
                <table style="width:100%;border-collapse:collapse;font-size:.78rem" id="batchUjiTable">
                    <thead>
                        <tr>
                            <th style="padding:6px;text-align:left;color:var(--text3);font-size:.72rem;border-bottom:1px solid var(--border)">No. Ref. Batch</th>
                            <th style="padding:6px;text-align:left;color:var(--text3);font-size:.72rem;border-bottom:1px solid var(--border)">Sampel</th>
                            <th style="padding:6px;text-align:left;color:var(--text3);font-size:.72rem;border-bottom:1px solid var(--border)">Parameter</th>
                            <th style="padding:6px;text-align:left;color:var(--text3);font-size:.72rem;border-bottom:1px solid var(--border)">Nilai</th>
                            <th style="padding:6px;text-align:left;color:var(--text3);font-size:.72rem;border-bottom:1px solid var(--border)">Satuan</th>
                            <th style="padding:6px;text-align:left;color:var(--text3);font-size:.72rem;border-bottom:1px solid var(--border)">Metode</th>
                            <th style="padding:6px;border-bottom:1px solid var(--border);width:36px"></th>
                        </tr>
                    </thead>
                    <tbody id="batchUjiRows"></tbody>
                </table>
                </div>
                <div style="font-size:.72rem;color:var(--text3);margin-top:6px">Total: <strong id="totalUjiBatch" style="color:var(--gold)">0</strong> baris</div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>Analis (berlaku semua baris)</label>
                    <select name="analis_id_all">
                        <?php foreach ($analisList as $a): ?><option value="<?= $a['id'] ?>"><?= bersihkan($a['nama']) ?></option><?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Tanggal Uji (berlaku semua baris)</label>
                    <input type="date" name="tanggal_uji_all" value="<?= date('Y-m-d') ?>" required/>
                </div>
            </div>
            <button type="submit" class="btn btn-gold">&#128190; Simpan Semua Hasil Uji</button>
        </form>
    </div>
</div>

<!-- ══════════════════════════════════════════════
     TAB 3 — STATISTIK
══════════════════════════════════════════════ -->
<div id="tab-stat" class="tab-pane <?= $tab==='stat'?'active':'' ?>">
    <div class="grid2">
        <div class="card">
            <div class="card-title">&#128202; Per Parameter</div>
            <?php $sByParam=$pdo->query("SELECT parameter, COUNT(*) n, SUM(kesimpulan='lulus') l FROM hasil_uji GROUP BY parameter ORDER BY n DESC LIMIT 8")->fetchAll();
            $mx=max(array_column($sByParam,'n')?:[1]);
            foreach ($sByParam as $r): ?>
            <div class="prog-wrap" style="margin-bottom:8px">
                <div class="prog-label">
                    <span><?= bersihkan($r['parameter']) ?></span>
                    <span><?= $r['n'] ?> uji &nbsp;·&nbsp; <span style="color:var(--green)"><?= $r['l'] ?> lulus</span></span>
                </div>
                <div class="prog"><div class="prog-bar pb-green" style="width:<?= round($r['n']/$mx*100) ?>%"></div></div>
            </div>
            <?php endforeach; ?>
        </div>
        <div class="card">
            <div class="card-title">&#128202; Per Metode</div>
            <?php $sByMet=$pdo->query("SELECT metode, COUNT(*) n FROM hasil_uji GROUP BY metode ORDER BY n DESC")->fetchAll();
            $mx2=max(array_column($sByMet,'n')?:[1]);
            foreach ($sByMet as $r): ?>
            <div class="prog-wrap" style="margin-bottom:8px">
                <div class="prog-label"><span><?= bersihkan($r['metode']) ?></span><span><?= $r['n'] ?></span></div>
                <div class="prog"><div class="prog-bar pb-gold" style="width:<?= round($r['n']/$mx2*100) ?>%"></div></div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<!-- MODAL EDIT HASIL UJI -->
<div id="editModal" class="modal-edit">
    <div class="modal-edit-content">
        <div class="modal-edit-header">
            <h3>✏️ Edit Hasil Uji</h3>
            <button class="modal-edit-close" onclick="closeEditModal()">&times;</button>
        </div>
        <form id="editForm" method="POST" action="<?= BASE_URL ?>/actions/simpan_hasil_uji.php">
            <input type="hidden" name="action" value="edit"/>
            <input type="hidden" name="id" id="edit_id"/>
            <input type="hidden" name="redirect" value="<?= BASE_URL ?>/pengujian.php?tab=hasil"/>
            
            <div class="form-group">
                <label>Kode Uji</label>
                <input type="text" id="edit_kode_uji" class="form-control" readonly disabled style="background:var(--bg3);"/>
            </div>
            
            <div class="form-group">
                <label>Sampel</label>
                <input type="text" id="edit_sampel" class="form-control" readonly disabled style="background:var(--bg3);"/>
            </div>
            
            <div class="form-group">
                <label>Parameter</label>
                <input type="text" name="parameter" id="edit_parameter" class="form-control" required/>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label>Nilai</label>
                    <input type="number" step="0.0001" name="nilai" id="edit_nilai" class="form-control" required/>
                </div>
                <div class="form-group">
                    <label>Satuan</label>
                    <select name="satuan" id="edit_satuan" class="form-control">
                        <?php foreach ($satuanOpts as $sat): ?>
                            <option value="<?= $sat ?>"><?= $sat ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label>Metode</label>
                    <select name="metode" id="edit_metode" class="form-control">
                        <?php foreach ($metodeOpts as $met): ?>
                            <option value="<?= $met ?>"><?= $met ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Alat</label>
                    <select name="alat_id" id="edit_alat_id" class="form-control">
                        <option value="">— Pilih Alat —</option>
                        <?php foreach ($alatList as $a): ?>
                            <option value="<?= $a['id'] ?>"><?= bersihkan($a['nama']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label>Analis</label>
                    <select name="analis_id" id="edit_analis_id" class="form-control">
                        <option value="">— Pilih Analis —</option>
                        <?php foreach ($analisList as $a): ?>
                            <option value="<?= $a['id'] ?>"><?= bersihkan($a['nama']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Tanggal Uji</label>
                    <input type="date" name="tanggal_uji" id="edit_tanggal_uji" class="form-control" required/>
                </div>
            </div>
            
            <div class="form-group">
                <label>Kesimpulan</label>
                <select name="kesimpulan" id="edit_kesimpulan" class="form-control">
                    <option value="lulus">Lulus</option>
                    <option value="tidak_lulus">Tidak Lulus</option>
                    <option value="pending">Pending</option>
                </select>
            </div>
            
            <div class="form-group">
                <label>Catatan</label>
                <textarea name="catatan" id="edit_catatan" rows="2" class="form-control" placeholder="Catatan tambahan..."></textarea>
            </div>
            
            <div class="modal-edit-footer">
                <button type="button" class="btn btn-sm" onclick="closeEditModal()">Batal</button>
                <button type="submit" class="btn btn-gold">💾 Simpan Perubahan</button>
            </div>
        </form>
    </div>
</div>

<script>
// ── TAB ───────────────────────────────────────────────────────
function switchTab(name, el) {
    document.querySelectorAll('.tab-pane').forEach(p=>p.classList.remove('active'));
    document.querySelectorAll('.tab-btn').forEach(b=>b.classList.remove('active'));
    document.getElementById('tab-'+name).classList.add('active');
    if (el) el.classList.add('active');
    history.replaceState(null,'','?tab='+name);
}

// ── ON SAMPEL CHANGE → isi No. Referensi + info box ──────────
const batchData = <?= json_encode(array_column(
    $pdo->query("SELECT nomor_penerimaan, klien, (SELECT COUNT(*) FROM sampel WHERE penerimaan_id=p.id) jml FROM penerimaan_sampel p")->fetchAll(),
    null, 'nomor_penerimaan'
)) ?>;

function onSampelChange(sel) {
    const opt  = sel.options[sel.selectedIndex];
    const rec  = opt.dataset.batch  || '';
    const klien= opt.dataset.klien  || '';
    const mat  = opt.dataset.mat    || '';
    const infoBox = document.getElementById('batchInfoBox');
    const noRef   = document.getElementById('noRefDisplay');
    const noRefH  = document.getElementById('noRefHidden');

    if (rec) {
        noRef.value  = rec;
        noRefH.value = rec;
        document.getElementById('biRec').textContent   = rec;
        document.getElementById('biKlien').textContent = klien;
        document.getElementById('biMat').textContent   = mat;
        const bd = batchData[rec];
        document.getElementById('biTotal').textContent = bd ? bd.jml + ' sampel' : '—';
        infoBox.classList.add('show');
    } else {
        noRef.value  = '—';
        noRefH.value = '';
        infoBox.classList.remove('show');
    }
}

// Auto-trigger jika ada pre-fill sampel
<?php if ($preSampelId): ?>
window.addEventListener('load', () => {
    const sel = document.getElementById('selSampel');
    if (sel) { onSampelChange(sel); switchTab('input', document.getElementById('tabInputBtn')); }
});
<?php endif; ?>

// ── BATCH UJI TABLE ───────────────────────────────────────────
const sampelOpts = <?= json_encode(array_map(fn($s)=>['id'=>$s['id'],'label'=>$s['label_lengkap'],'batch'=>$s['nomor_penerimaan']??'','klien'=>$s['klien']], $sampelAktif)) ?>;
const metOpts    = <?= json_encode($metodeOpts) ?>;
const satOpts    = <?= json_encode($satuanOpts) ?>;

// Map wo_id → { sampelList, paramList, metode, ref }
const woDetailMap = <?= json_encode(
    array_reduce($woAktifList, function($carry, $wo) use ($pdo) {
        $st = $pdo->prepare("
            SELECT s.id, s.kode_sampel, s.jenis_material,
                   CONCAT(s.kode_sampel,
                       CASE WHEN rec.nomor_penerimaan IS NOT NULL
                            THEN CONCAT(' [', rec.nomor_penerimaan, ']')
                            ELSE '' END
                   ) AS label_lengkap,
                   rec.nomor_penerimaan
            FROM work_order_sampel wos
            JOIN sampel s ON wos.sampel_id = s.id
            LEFT JOIN penerimaan_sampel rec ON s.penerimaan_id = rec.id
            WHERE wos.wo_id = ?
              AND s.id NOT IN (SELECT DISTINCT sampel_id FROM hasil_uji)
            ORDER BY s.kode_sampel
        ");
        $st->execute([$wo['id']]);
        $sampelList = $st->fetchAll(PDO::FETCH_ASSOC);

        $paramStr  = trim($wo['parameter'] ?? '');
        $paramList = $paramStr
            ? array_values(array_filter(array_map('trim', explode(',', $paramStr))))
            : [];

        $carry[$wo['id']] = [
            'sampelList' => $sampelList,
            'paramList'  => $paramList,
            'metode'     => $wo['metode'] ?? '',
            'ref'        => $wo['nomor_penerimaan'] ?? '',
            'nomor_wo'   => $wo['nomor_wo'],
        ];
        return $carry;
    }, [])
) ?>;

let ujiRowCnt = 0;

// ── Load dari WO: baris = sampel × parameter ─────────────────
function loadBatchFromWo(sel) {
    const woId = sel.value;
    document.getElementById('batchUjiRows').innerHTML = '';
    ujiRowCnt = 0;
    document.getElementById('batchRefSel').value = '';

    if (!woId) { updateUjiCount(); return; }

    const wo = woDetailMap[woId];
    if (!wo) { updateUjiCount(); return; }

    const paramList  = wo.paramList.length ? wo.paramList : [''];
    const sampelList = wo.sampelList;
    const metode     = wo.metode || '';
    const ref        = wo.ref   || '';

    if (!sampelList.length) {
        alert('Semua sampel dalam WO ini sudah memiliki hasil uji.');
        updateUjiCount(); return;
    }

    sampelList.forEach(s => {
        paramList.forEach(param => {
            tambahBarisUji(s.id, ref, param, metode);
        });
    });

    updateUjiCount();
}

// ── Load dari No. Referensi Batch ─────────────────────────────────
function loadBatchSampel(noRec) {
    document.getElementById('batchUjiRows').innerHTML = '';
    ujiRowCnt = 0;
    document.getElementById('batchWoSel').value = '';
    if (!noRec) { updateUjiCount(); return; }
    const gParam = document.getElementById('batchParam').value;
    sampelOpts.filter(s => s.batch === noRec).forEach(s => {
        tambahBarisUji(s.id, noRec, gParam, '');
    });
    updateUjiCount();
}

function tambahBarisUji(sampelId='', batchRef='', paramDef='', metodeDef='') {
    ujiRowCnt++;
    const i = ujiRowCnt;
    const sOpts = sampelOpts.map(s =>
        `<option value="${s.id}" data-batch="${s.batch}" ${s.id==sampelId?'selected':''}>${s.label}</option>`
    ).join('');
    const mOpts = metOpts.map(m =>
        `<option ${m===metodeDef?'selected':''}>${m}</option>`
    ).join('');
    const satO  = satOpts.map(s => `<option>${s}</option>`).join('');

    const row = `<tr id="ubr${i}">
        <td style="padding:4px">
            <span class="ref-badge" id="ubr-ref-${i}">${batchRef||'—'}</span>
            <input type="hidden" name="rows[${i}][no_referensi]" id="ubr-refh-${i}" value="${batchRef}"/>
         </td>
        <td style="padding:4px">
            <select name="rows[${i}][sampel_id]" style="background:var(--bg3);border:1px solid var(--border);color:var(--text);padding:4px 6px;border-radius:4px;font-size:.75rem;width:160px"
                    onchange="onBatchRowSampelChange(${i},this)">
                <option value="">— Pilih —</option>${sOpts}
            </select>
         </td>
        <td style="padding:4px"><input name="rows[${i}][parameter]" value="${paramDef}" placeholder="Au, Fe..." style="background:var(--bg3);border:1px solid var(--border);color:var(--text);padding:4px 6px;border-radius:4px;font-size:.75rem;width:80px"/></td>
        <td style="padding:4px"><input type="number" step="0.0001" name="rows[${i}][nilai]" placeholder="0.0000" style="background:var(--bg3);border:1px solid var(--border);color:var(--text);padding:4px 6px;border-radius:4px;font-size:.75rem;width:80px"/></td>
        <td style="padding:4px"><select name="rows[${i}][satuan]" style="background:var(--bg3);border:1px solid var(--border);color:var(--text);padding:4px 6px;border-radius:4px;font-size:.75rem">${satO}</select></td>
        <td style="padding:4px"><select name="rows[${i}][metode]" style="background:var(--bg3);border:1px solid var(--border);color:var(--text);padding:4px 6px;border-radius:4px;font-size:.75rem">${mOpts}</select></td>
        <td style="padding:4px"><button type="button" onclick="document.getElementById('ubr${i}').remove();updateUjiCount()"
               style="background:var(--red);color:#fff;border:none;border-radius:4px;padding:3px 8px;cursor:pointer;font-size:.7rem">&#10005;</button></td>
     </tr>`;
    document.getElementById('batchUjiRows').insertAdjacentHTML('beforeend', row);
}

function onBatchRowSampelChange(i, sel) {
    const opt   = sel.options[sel.selectedIndex];
    const batch = opt.dataset.batch || '';
    document.getElementById('ubr-ref-'+i).textContent = batch || '—';
    document.getElementById('ubr-refh-'+i).value = batch;
}

function updateUjiCount() {
    document.getElementById('totalUjiBatch').textContent =
        document.getElementById('batchUjiRows').querySelectorAll('tr').length;
}

// ── EDIT HASIL UJI ────────────────────────────────────────────
function openEditModal(id) {
    fetch('<?= BASE_URL ?>/actions/get_hasil_uji.php?id=' + id)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                document.getElementById('edit_id').value = data.id;
                document.getElementById('edit_kode_uji').value = data.kode_uji;
                document.getElementById('edit_sampel').value = data.kode_sampel + ' - ' + data.jenis_material;
                document.getElementById('edit_parameter').value = data.parameter;
                document.getElementById('edit_nilai').value = data.nilai;
                document.getElementById('edit_satuan').value = data.satuan;
                document.getElementById('edit_metode').value = data.metode;
                document.getElementById('edit_alat_id').value = data.alat_id || '';
                document.getElementById('edit_analis_id').value = data.analis_id || '';
                document.getElementById('edit_tanggal_uji').value = data.tanggal_uji;
                document.getElementById('edit_kesimpulan').value = data.kesimpulan;
                document.getElementById('edit_catatan').value = data.catatan || '';
                
                document.getElementById('editModal').style.display = 'flex';
            } else {
                alert('Gagal mengambil data: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Terjadi kesalahan saat mengambil data.');
        });
}

function closeEditModal() {
    document.getElementById('editModal').style.display = 'none';
}

// Auto 1 baris kosong saat pertama load
tambahBarisUji();

// Tutup modal jika klik di luar
document.addEventListener('DOMContentLoaded', function() {
    const modal = document.getElementById('editModal');
    if (modal) {
        modal.addEventListener('click', function(e) {
            if (e.target === this) {
                closeEditModal();
            }
        });
    }
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>