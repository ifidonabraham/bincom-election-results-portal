<?php
// new_polling_unit.php - Question 3: Create a page to store results for ALL parties for a NEW polling unit.
require_once __DIR__ . '/includes/db.php';

$page_title = "Store New Polling Unit Results";
$active_tab = "q3";

$success_message = "";
$error_message = "";
$created_pu_uniqueid = null;

// 1. Fetch Political Parties
$parties = [];
try {
    $stmt = $pdo->query("SELECT id, partyid, partyname FROM party ORDER BY id ASC");
    $parties = $stmt->fetchAll();
    if (empty($parties)) {
        // Fallback default list if party table is empty
        $default_parties = ['PDP', 'DPP', 'ACN', 'PPA', 'CDC', 'JP', 'ANPP', 'LABOUR', 'CPP'];
        foreach ($default_parties as $p) {
            $parties[] = ['partyid' => $p, 'partyname' => $p];
        }
    }
} catch (PDOException $e) {
    $error_message = "Error loading party list: " . $e->getMessage();
}

// 2. Fetch LGAs for Delta State
$lgas = [];
try {
    $stmt = $pdo->query("SELECT lga_id, lga_name FROM lga WHERE state_id = 25 ORDER BY lga_name ASC");
    $lgas = $stmt->fetchAll();
    if (empty($lgas)) {
        $stmt = $pdo->query("SELECT lga_id, lga_name FROM lga ORDER BY lga_name ASC");
        $lgas = $stmt->fetchAll();
    }
} catch (PDOException $e) {
    $error_message = "Error loading LGAs: " . $e->getMessage();
}

// 3. Process Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $lga_id = filter_input(INPUT_POST, 'lga_id', FILTER_VALIDATE_INT);
    $ward_id = filter_input(INPUT_POST, 'ward_id', FILTER_VALIDATE_INT);
    $polling_unit_name = trim($_POST['polling_unit_name'] ?? '');
    $polling_unit_number = trim($_POST['polling_unit_number'] ?? '');
    $polling_unit_description = trim($_POST['polling_unit_description'] ?? '');
    $entered_by_user = trim($_POST['entered_by_user'] ?? 'Electoral Officer');
    $party_scores = $_POST['party_scores'] ?? [];

    // Validation
    if (!$lga_id) {
        $error_message = "Please select a valid Local Government Area (LGA).";
    } elseif (!$ward_id) {
        $error_message = "Please select a valid Ward.";
    } elseif (empty($polling_unit_name)) {
        $error_message = "Polling Unit name is required.";
    } else {
        // Validate party scores
        $valid_scores = true;
        foreach ($parties as $party) {
            $p_code = $party['partyid'];
            $score = $party_scores[$p_code] ?? null;
            if ($score === null || $score === '' || !is_numeric($score) || (int)$score < 0) {
                $error_message = "Please provide a valid non-negative number of votes for party {$p_code}.";
                $valid_scores = false;
                break;
            }
        }

        if ($valid_scores) {
            try {
                $pdo->beginTransaction();

                // Compute polling_unit_id for this ward or auto-increment pattern
                $id_stmt = $pdo->prepare("SELECT COALESCE(MAX(polling_unit_id), 0) + 1 FROM polling_unit WHERE lga_id = ? AND ward_id = ?");
                $id_stmt->execute([$lga_id, $ward_id]);
                $next_pu_id = (int)$id_stmt->fetchColumn();

                $client_ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
                $now = date('Y-m-d H:i:s');

                // Step A: Insert into polling_unit
                $pu_insert = $pdo->prepare("
                    INSERT INTO polling_unit (
                        polling_unit_id, ward_id, lga_id, uniquewardid,
                        polling_unit_number, polling_unit_name, polling_unit_description,
                        lat, `long`, entered_by_user, date_entered, user_ip_address
                    ) VALUES (
                        ?, ?, ?, ?,
                        ?, ?, ?,
                        ?, ?, ?, ?, ?
                    )
                ");

                $pu_insert->execute([
                    $next_pu_id,
                    $ward_id,
                    $lga_id,
                    $ward_id,
                    $polling_unit_number ?: ('PU-' . rand(100, 999)),
                    $polling_unit_name,
                    $polling_unit_description,
                    '',
                    '',
                    $entered_by_user,
                    $now,
                    $client_ip
                ]);

                // Retrieve newly created uniqueid
                $created_pu_uniqueid = (int)$pdo->lastInsertId();

                // Step B: Insert into announced_pu_results for ALL parties using the NEW uniqueid
                // JOIN KEY: polling_unit.uniqueid = announced_pu_results.polling_unit_uniqueid
                $res_insert = $pdo->prepare("
                    INSERT INTO announced_pu_results (
                        polling_unit_uniqueid, party_abbreviation, party_score,
                        entered_by_user, date_entered, user_ip_address
                    ) VALUES (
                        ?, ?, ?,
                        ?, ?, ?
                    )
                ");

                foreach ($parties as $party) {
                    $p_code = $party['partyid'];
                    $score = (int)($party_scores[$p_code] ?? 0);

                    $res_insert->execute([
                        (string)$created_pu_uniqueid,
                        $p_code,
                        $score,
                        $entered_by_user,
                        $now,
                        $client_ip
                    ]);
                }

                $pdo->commit();

                $success_message = "Successfully created new Polling Unit '{$polling_unit_name}' (Unique ID: {$created_pu_uniqueid}) and saved results for all " . count($parties) . " political parties!";

            } catch (PDOException $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $error_message = "Database Error: " . $e->getMessage();
            }
        }
    }
}

require_once __DIR__ . '/includes/header.php';
?>

<div class="row mb-4">
    <div class="col-12">
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
            <div>
                <h2 class="fw-bold mb-1"><i class="bi bi-plus-circle-fill text-success me-2"></i>Question 3: Record New Polling Unit &amp; Results</h2>
                <p class="text-muted mb-0">Add a new polling unit and record election results for all contesting political parties simultaneously.</p>
            </div>
            <a href="index.php" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left me-1"></i> Back to Q1 Results
            </a>
        </div>
    </div>
</div>

<?php if (!empty($success_message)): ?>
    <div class="alert alert-success alert-dismissible fade show shadow-sm p-4" role="alert">
        <div class="d-flex align-items-start gap-3">
            <i class="bi bi-check-circle-fill text-success fs-2"></i>
            <div>
                <h5 class="alert-heading fw-bold mb-1">New Polling Unit Created!</h5>
                <p class="mb-2"><?= htmlspecialchars($success_message) ?></p>
                <div class="d-flex gap-2">
                    <a href="index.php?pu_uniqueid=<?= $created_pu_uniqueid ?>" class="btn btn-success btn-sm">
                        <i class="bi bi-eye me-1"></i> View Results on Q1 Page
                    </a>
                    <a href="lga_results.php?lga_id=<?= (int)$_POST['lga_id'] ?>" class="btn btn-outline-success btn-sm">
                        <i class="bi bi-calculator me-1"></i> View Updated LGA Sum (Q2)
                    </a>
                </div>
            </div>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<?php if (!empty($error_message)): ?>
    <div class="alert alert-danger alert-dismissible fade show shadow-sm" role="alert">
        <i class="bi bi-exclamation-triangle-fill me-2"></i> <?= htmlspecialchars($error_message) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<form method="POST" action="new_polling_unit.php" id="newPuForm" novalidate>
    <div class="row g-4">
        <!-- Left Column: Unit Metadata -->
        <div class="col-lg-6">
            <div class="card shadow-sm h-100">
                <div class="card-header bg-white py-3 border-bottom">
                    <h5 class="fw-bold mb-0 text-dark">
                        <i class="bi bi-geo-alt-fill text-primary me-2"></i>1. Polling Unit Information
                    </h5>
                </div>
                <div class="card-body p-4">
                    <div class="mb-3">
                        <label for="lgaSelect" class="form-label fw-semibold">Local Government Area (LGA) <span class="text-danger">*</span></label>
                        <select class="form-select" id="lgaSelect" name="lga_id" required>
                            <option value="">-- Choose LGA --</option>
                            <?php foreach ($lgas as $lga): ?>
                                <option value="<?= htmlspecialchars($lga['lga_id']) ?>" <?= (isset($_POST['lga_id']) && $_POST['lga_id'] == $lga['lga_id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($lga['lga_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label for="wardSelect" class="form-label fw-semibold">Ward <span class="text-danger">*</span></label>
                        <select class="form-select" id="wardSelect" name="ward_id" required disabled>
                            <option value="">-- First Choose LGA Above --</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label for="puName" class="form-label fw-semibold">Polling Unit Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="puName" name="polling_unit_name" placeholder="e.g. Asaba Civic Hall Unit 1" value="<?= htmlspecialchars($_POST['polling_unit_name'] ?? '') ?>" required>
                    </div>

                    <div class="mb-3">
                        <label for="puNumber" class="form-label fw-semibold">Polling Unit Code / Number</label>
                        <input type="text" class="form-control" id="puNumber" name="polling_unit_number" placeholder="e.g. DT/05/01/008" value="<?= htmlspecialchars($_POST['polling_unit_number'] ?? '') ?>">
                        <small class="text-muted">Optional INEC unit reference code</small>
                    </div>

                    <div class="mb-3">
                        <label for="puDesc" class="form-label fw-semibold">Description / Location Details</label>
                        <textarea class="form-control" id="puDesc" name="polling_unit_description" rows="2" placeholder="e.g. Located opposite the central market"><?= htmlspecialchars($_POST['polling_unit_description'] ?? '') ?></textarea>
                    </div>

                    <div class="mb-3">
                        <label for="enteredBy" class="form-label fw-semibold">Presiding Officer Name</label>
                        <input type="text" class="form-control" id="enteredBy" name="entered_by_user" value="<?= htmlspecialchars($_POST['entered_by_user'] ?? 'Cleopatra Agboli') ?>">
                    </div>
                </div>
            </div>
        </div>

        <!-- Right Column: Results for ALL Parties -->
        <div class="col-lg-6">
            <div class="card shadow-sm h-100">
                <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                    <h5 class="fw-bold mb-0 text-dark">
                        <i class="bi bi-input-cursor-text text-success me-2"></i>2. Votes by Political Party
                    </h5>
                    <span class="badge bg-light text-dark border"><?= count($parties) ?> Contesting Parties</span>
                </div>
                <div class="card-body p-4">
                    <p class="text-muted small mb-3">
                        <i class="bi bi-info-circle me-1"></i> Enter the number of votes obtained by each party. All inputs must be non-negative integers.
                    </p>

                    <div class="row g-3">
                        <?php foreach ($parties as $party): 
                            $p_code = htmlspecialchars($party['partyid']);
                            $p_name = htmlspecialchars($party['partyname']);
                            $p_lower = strtolower(trim($p_code));
                            $curr_val = isset($_POST['party_scores'][$p_code]) ? $_POST['party_scores'][$p_code] : '0';
                        ?>
                            <div class="col-sm-6">
                                <div class="p-3 border rounded bg-light">
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <label for="score_<?= $p_code ?>" class="form-label fw-bold mb-0">
                                            <span class="party-badge party-<?= $p_lower ?> border me-1"><?= $p_code ?></span>
                                        </label>
                                        <small class="text-muted"><?= $p_name ?></small>
                                    </div>
                                    <div class="input-group">
                                        <span class="input-group-text bg-white"><i class="bi bi-check2-square"></i></span>
                                        <input type="number" 
                                               min="0" 
                                               step="1" 
                                               class="form-control form-control-lg text-end party-score-input" 
                                               id="score_<?= $p_code ?>" 
                                               name="party_scores[<?= $p_code ?>]" 
                                               value="<?= htmlspecialchars($curr_val) ?>" 
                                               required>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <div class="p-3 mt-4 bg-primary-subtle rounded border border-primary-subtle d-flex justify-content-between align-items-center">
                        <span class="fw-bold text-primary"><i class="bi bi-calculator me-1"></i> Total Calculated Votes:</span>
                        <span class="fs-4 fw-bold text-primary" id="totalScoreLive">0</span>
                    </div>

                    <div class="mt-4 pt-2">
                        <button type="submit" class="btn btn-success btn-lg w-100 shadow-sm">
                            <i class="bi bi-cloud-arrow-up-fill me-2"></i> Save Polling Unit &amp; Results
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</form>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const lgaSelect = document.getElementById('lgaSelect');
    const wardSelect = document.getElementById('wardSelect');
    const scoreInputs = document.querySelectorAll('.party-score-input');
    const totalScoreLive = document.getElementById('totalScoreLive');

    // Chained Ward dropdown
    async function loadWardsForLga(lgaId, preselectWardId = null) {
        wardSelect.innerHTML = '<option value="">Loading wards...</option>';
        wardSelect.disabled = true;

        if (!lgaId) {
            wardSelect.innerHTML = '<option value="">-- First Choose LGA Above --</option>';
            return;
        }

        try {
            const res = await fetch(`api/get_wards.php?lga_id=${lgaId}`);
            const result = await res.json();
            if (result.success && result.data.length > 0) {
                wardSelect.innerHTML = '<option value="">-- Choose Ward --</option>';
                result.data.forEach(w => {
                    const opt = document.createElement('option');
                    opt.value = w.ward_id;
                    opt.textContent = w.ward_name;
                    if (preselectWardId && w.ward_id == preselectWardId) {
                        opt.selected = true;
                    }
                    wardSelect.appendChild(opt);
                });
                wardSelect.disabled = false;
            } else {
                wardSelect.innerHTML = '<option value="">No wards found</option>';
                wardSelect.disabled = false;
            }
        } catch (e) {
            wardSelect.innerHTML = '<option value="">Error loading wards</option>';
        }
    }

    lgaSelect.addEventListener('change', function () {
        loadWardsForLga(this.value);
    });

    // Calculate live total
    function calculateTotal() {
        let total = 0;
        scoreInputs.forEach(input => {
            const val = parseInt(input.value, 10);
            if (!isNaN(val) && val > 0) {
                total += val;
            }
        });
        totalScoreLive.textContent = total.toLocaleString();
    }

    scoreInputs.forEach(input => {
        input.addEventListener('input', calculateTotal);
    });
    calculateTotal();

    // Check if LGA was already selected on reload
    const selectedLga = '<?= $_POST['lga_id'] ?? '' ?>';
    const selectedWard = '<?= $_POST['ward_id'] ?? '' ?>';
    if (selectedLga) {
        loadWardsForLga(selectedLga, selectedWard);
    }
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
