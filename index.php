<?php
// index.php - Question 1: Display result for any individual polling unit
require_once __DIR__ . '/includes/db.php';

$page_title = "Polling Unit Results";
$active_tab = "q1";

// Fetch all LGAs for Delta State (state_id = 25 or all available)
$lgas = [];
try {
    $stmt = $pdo->query("SELECT lga_id, lga_name FROM lga WHERE state_id = 25 ORDER BY lga_name ASC");
    $lgas = $stmt->fetchAll();
    if (empty($lgas)) {
        // Fallback in case state_id differs
        $stmt = $pdo->query("SELECT lga_id, lga_name FROM lga ORDER BY lga_name ASC");
        $lgas = $stmt->fetchAll();
    }
} catch (PDOException $e) {
    $error_msg = "Could not load LGAs: " . $e->getMessage();
}

// Check if a polling unit uniqueid is submitted
$selected_pu_uniqueid = filter_input(INPUT_GET, 'pu_uniqueid', FILTER_VALIDATE_INT);
$pu_details = null;
$pu_results = [];
$total_pu_votes = 0;

if ($selected_pu_uniqueid) {
    try {
        // Fetch Polling Unit Information
        $pu_stmt = $pdo->prepare("
            SELECT pu.*, l.lga_name, w.ward_name
            FROM polling_unit pu
            LEFT JOIN lga l ON pu.lga_id = l.lga_id
            LEFT JOIN ward w ON (pu.ward_id = w.ward_id AND pu.lga_id = w.lga_id)
            WHERE pu.uniqueid = ?
            LIMIT 1
        ");
        $pu_stmt->execute([$selected_pu_uniqueid]);
        $pu_details = $pu_stmt->fetch();

        // Fetch Results: JOIN key is polling_unit.uniqueid = announced_pu_results.polling_unit_uniqueid
        $res_stmt = $pdo->prepare("
            SELECT result_id, party_abbreviation, party_score, entered_by_user, date_entered
            FROM announced_pu_results
            WHERE polling_unit_uniqueid = ?
            ORDER BY party_score DESC
        ");
        $res_stmt->execute([(string)$selected_pu_uniqueid]);
        $pu_results = $res_stmt->fetchAll();

        foreach ($pu_results as $row) {
            $total_pu_votes += (int)$row['party_score'];
        }
    } catch (PDOException $e) {
        $error_msg = "Error retrieving results: " . $e->getMessage();
    }
}

require_once __DIR__ . '/includes/header.php';
?>

<div class="row mb-4">
    <div class="col-12">
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
            <div>
                <h2 class="fw-bold mb-1"><i class="bi bi-card-checklist text-primary me-2"></i>Question 1: Polling Unit Results</h2>
                <p class="text-muted mb-0">Select an LGA, Ward, and Polling Unit using the chained filters below to display announced results.</p>
            </div>
            <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-3 py-2 fs-6">
                <i class="bi bi-shield-check me-1"></i> Official Collation Data
            </span>
        </div>
    </div>
</div>

<?php if (isset($error_msg)): ?>
    <div class="alert alert-danger alert-dismissible fade show shadow-sm" role="alert">
        <i class="bi bi-exclamation-triangle-fill me-2"></i> <?= htmlspecialchars($error_msg) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<!-- Filter Card with Chained Dropdowns -->
<div class="card shadow-sm mb-4">
    <div class="card-header bg-white py-3">
        <div class="d-flex align-items-center justify-content-between">
            <span class="fw-bold"><i class="bi bi-funnel-fill text-primary me-1"></i> Select Location (Chained Dropdowns)</span>
            <small class="text-muted">Pick LGA &rarr; Ward &rarr; Polling Unit</small>
        </div>
    </div>
    <div class="card-body p-4">
        <form method="GET" action="index.php" id="filterForm">
            <div class="row g-3 align-items-end">
                <div class="col-md-4">
                    <label for="lgaSelect" class="form-label fw-semibold">1. Local Government Area (LGA)</label>
                    <select class="form-select form-select-lg" id="lgaSelect" name="lga_id" required>
                        <option value="">-- Choose LGA --</option>
                        <?php foreach ($lgas as $lga): ?>
                            <option value="<?= htmlspecialchars($lga['lga_id']) ?>" <?= (isset($pu_details['lga_id']) && $pu_details['lga_id'] == $lga['lga_id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($lga['lga_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-4">
                    <label for="wardSelect" class="form-label fw-semibold">2. Ward</label>
                    <select class="form-select form-select-lg" id="wardSelect" name="ward_id" <?= empty($pu_details) ? 'disabled' : '' ?>>
                        <option value="">-- Choose Ward --</option>
                    </select>
                </div>

                <div class="col-md-4">
                    <label for="puSelect" class="form-label fw-semibold">3. Polling Unit</label>
                    <select class="form-select form-select-lg" id="puSelect" name="pu_uniqueid" required <?= empty($pu_details) ? 'disabled' : '' ?>>
                        <option value="">-- Choose Polling Unit --</option>
                    </select>
                </div>
            </div>

            <div class="mt-3 text-end">
                <a href="index.php" class="btn btn-outline-secondary me-2">
                    <i class="bi bi-arrow-counterclockwise me-1"></i> Reset
                </a>
                <button type="submit" class="btn btn-primary btn-lg px-4" id="viewBtn" <?= empty($selected_pu_uniqueid) ? 'disabled' : '' ?>>
                    <i class="bi bi-search me-1"></i> View Results
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Results Section -->
<?php if ($selected_pu_uniqueid): ?>
    <?php if ($pu_details): ?>
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                <div>
                    <h5 class="fw-bold mb-0 text-dark"><?= htmlspecialchars($pu_details['polling_unit_name'] ?: 'Polling Unit #' . $pu_details['polling_unit_id']) ?></h5>
                    <small class="text-muted">
                        <strong>LGA:</strong> <?= htmlspecialchars($pu_details['lga_name'] ?? 'N/A') ?> | 
                        <strong>Ward:</strong> <?= htmlspecialchars($pu_details['ward_name'] ?? ('Ward ID ' . $pu_details['ward_id'])) ?> | 
                        <strong>PU Number:</strong> <?= htmlspecialchars($pu_details['polling_unit_number'] ?: 'N/A') ?>
                    </small>
                </div>
                <div class="text-end">
                    <span class="badge bg-secondary">Unique ID: <?= htmlspecialchars($pu_details['uniqueid']) ?></span>
                </div>
            </div>

            <div class="card-body p-4">
                <?php if (!empty($pu_results)): ?>
                    <div class="row mb-4">
                        <div class="col-md-4 mb-2">
                            <div class="p-3 bg-primary-subtle rounded border border-primary-subtle stat-card">
                                <small class="text-primary fw-semibold d-block">TOTAL VOTES RECORDED</small>
                                <span class="fs-3 fw-bold text-primary"><?= number_format($total_pu_votes) ?></span>
                            </div>
                        </div>
                        <div class="col-md-4 mb-2">
                            <div class="p-3 bg-success-subtle rounded border border-success-subtle">
                                <small class="text-success fw-semibold d-block">LEADING PARTY</small>
                                <span class="fs-3 fw-bold text-success">
                                    <?= htmlspecialchars($pu_results[0]['party_abbreviation']) ?> (<?= number_format($pu_results[0]['party_score']) ?>)
                                </span>
                            </div>
                        </div>
                        <div class="col-md-4 mb-2">
                            <div class="p-3 bg-light rounded border">
                                <small class="text-muted fw-semibold d-block">PARTIES CONTESTING</small>
                                <span class="fs-3 fw-bold text-dark"><?= count($pu_results) ?></span>
                            </div>
                        </div>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-hover align-middle border">
                            <thead class="table-light">
                                <tr>
                                    <th style="width: 80px;">#</th>
                                    <th>Party</th>
                                    <th class="text-end">Score / Votes</th>
                                    <th style="width: 35%;">Vote Share</th>
                                    <th class="text-center">Entered By</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $idx = 1;
                                foreach ($pu_results as $res): 
                                    $pct = $total_pu_votes > 0 ? round(($res['party_score'] / $total_pu_votes) * 100, 1) : 0;
                                    $party_lower = strtolower(trim($res['party_abbreviation']));
                                ?>
                                    <tr>
                                        <td class="fw-semibold text-muted"><?= $idx++ ?></td>
                                        <td>
                                            <span class="party-badge party-<?= htmlspecialchars($party_lower) ?> border">
                                                <?= htmlspecialchars($res['party_abbreviation']) ?>
                                            </span>
                                        </td>
                                        <td class="text-end fw-bold fs-5 text-dark">
                                            <?= number_format($res['party_score']) ?>
                                        </td>
                                        <td>
                                            <div class="d-flex align-items-center gap-2">
                                                <div class="progress flex-grow-1" style="height: 10px;">
                                                    <div class="progress-bar bg-primary" role="progressbar" style="width: <?= $pct ?>%;" aria-valuenow="<?= $pct ?>" aria-valuemin="0" aria-valuemax="100"></div>
                                                </div>
                                                <small class="text-muted fw-bold" style="min-width: 45px;"><?= $pct ?>%</small>
                                            </div>
                                        </td>
                                        <td class="text-center small text-muted">
                                            <?= htmlspecialchars($res['entered_by_user'] ?: 'Official Admin') ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="text-center py-5">
                        <i class="bi bi-inbox text-muted display-4 d-block mb-3"></i>
                        <h5 class="fw-semibold text-secondary">No Results Announced Yet</h5>
                        <p class="text-muted">There are no announced results recorded in the database for Polling Unit Unique ID <strong><?= htmlspecialchars($selected_pu_uniqueid) ?></strong>.</p>
                        <a href="new_polling_unit.php" class="btn btn-outline-primary btn-sm">
                            <i class="bi bi-plus-circle me-1"></i> Record Results for this Unit
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    <?php else: ?>
        <div class="alert alert-warning shadow-sm">
            <i class="bi bi-exclamation-circle me-2"></i> Selected polling unit not found.
        </div>
    <?php endif; ?>
<?php else: ?>
    <!-- Quick Showcase of Available Polling Units with results -->
    <div class="card shadow-sm border-0 bg-white p-4">
        <h5 class="fw-bold mb-3"><i class="bi bi-lightning-charge-fill text-warning me-1"></i> Quick Test Units (Units with sample results in DB):</h5>
        <div class="d-flex flex-wrap gap-2">
            <a href="index.php?pu_uniqueid=8" class="btn btn-outline-primary btn-sm">
                <i class="bi bi-box-arrow-in-up-right me-1"></i> PU #8: Sapele Ward 8 PU
            </a>
            <a href="index.php?pu_uniqueid=9" class="btn btn-outline-primary btn-sm">
                <i class="bi bi-box-arrow-in-up-right me-1"></i> PU #9: Primary School in Aghara
            </a>
            <a href="index.php?pu_uniqueid=10" class="btn btn-outline-primary btn-sm">
                <i class="bi bi-box-arrow-in-up-right me-1"></i> PU #10: Ishere Primary School
            </a>
            <a href="index.php?pu_uniqueid=13" class="btn btn-outline-primary btn-sm">
                <i class="bi bi-box-arrow-in-up-right me-1"></i> PU #13: Church in Effurun1
            </a>
            <a href="index.php?pu_uniqueid=22" class="btn btn-outline-primary btn-sm">
                <i class="bi bi-box-arrow-in-up-right me-1"></i> PU #22: Polling 3 in Agbara
            </a>
        </div>
    </div>
<?php endif; ?>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const lgaSelect = document.getElementById('lgaSelect');
    const wardSelect = document.getElementById('wardSelect');
    const puSelect = document.getElementById('puSelect');
    const viewBtn = document.getElementById('viewBtn');

    // Preloaded selections if available
    const initialLgaId = '<?= isset($pu_details['lga_id']) ? $pu_details['lga_id'] : '' ?>';
    const initialWardId = '<?= isset($pu_details['ward_id']) ? $pu_details['ward_id'] : '' ?>';
    const initialPuUniqueId = '<?= $selected_pu_uniqueid ?: '' ?>';

    async function loadWards(lgaId, preselectWardId = null) {
        wardSelect.innerHTML = '<option value="">Loading wards...</option>';
        wardSelect.disabled = true;
        puSelect.innerHTML = '<option value="">-- Choose Polling Unit --</option>';
        puSelect.disabled = true;
        viewBtn.disabled = true;

        if (!lgaId) {
            wardSelect.innerHTML = '<option value="">-- Choose Ward --</option>';
            return;
        }

        try {
            const res = await fetch(`/api/get_wards.php?lga_id=${encodeURIComponent(lgaId)}`);
            const result = await res.json();
            if (result.success && result.data.length > 0) {
                wardSelect.innerHTML = '<option value="">-- Choose Ward --</option>';
                result.data.forEach(ward => {
                    const opt = document.createElement('option');
                    opt.value = ward.ward_id;
                    opt.textContent = ward.ward_name;
                    if (preselectWardId && ward.ward_id == preselectWardId) {
                        opt.selected = true;
                    }
                    wardSelect.appendChild(opt);
                });
                wardSelect.disabled = false;
            } else {
                wardSelect.innerHTML = '<option value="">No wards found</option>';
                wardSelect.disabled = false;
            }

            // Also load all polling units for this LGA
            loadPollingUnits(lgaId, preselectWardId, initialPuUniqueId);
        } catch (err) {
            console.error(err);
            wardSelect.innerHTML = '<option value="">Error loading wards</option>';
        }
    }

    async function loadPollingUnits(lgaId, wardId = null, preselectPuId = null) {
        puSelect.innerHTML = '<option value="">Loading polling units...</option>';
        puSelect.disabled = true;

        if (!lgaId) return;

        let url = `/api/get_polling_units.php?lga_id=${encodeURIComponent(lgaId)}`;
        if (wardId) {
            url += `&ward_id=${encodeURIComponent(wardId)}`;
        }

        try {
            const res = await fetch(url);
            const result = await res.json();
            if (result.success && result.data.length > 0) {
                puSelect.innerHTML = '<option value="">-- Choose Polling Unit --</option>';
                result.data.forEach(pu => {
                    const opt = document.createElement('option');
                    opt.value = pu.uniqueid;
                    const name = pu.polling_unit_name ? pu.polling_unit_name : `Polling Unit #${pu.polling_unit_id}`;
                    opt.textContent = `${name} (ID: ${pu.uniqueid})`;
                    if (preselectPuId && pu.uniqueid == preselectPuId) {
                        opt.selected = true;
                        viewBtn.disabled = false;
                    }
                    puSelect.appendChild(opt);
                });
                puSelect.disabled = false;
            } else {
                puSelect.innerHTML = '<option value="">No polling units found</option>';
                puSelect.disabled = false;
            }
        } catch (err) {
            puSelect.innerHTML = '<option value="">Error loading units</option>';
        }
    }

    lgaSelect.addEventListener('change', function () {
        loadWards(this.value);
    });

    wardSelect.addEventListener('change', function () {
        loadPollingUnits(lgaSelect.value, this.value);
    });

    puSelect.addEventListener('change', function () {
        viewBtn.disabled = !this.value;
    });

    // If preloaded with initial data
    if (initialLgaId) {
        loadWards(initialLgaId, initialWardId);
    }
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
