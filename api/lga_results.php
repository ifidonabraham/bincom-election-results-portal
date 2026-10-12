<?php
// lga_results.php - Question 2: Displays the SUMMED TOTAL result of all polling units under a chosen local government.
require_once __DIR__ . '/includes/db.php';

$page_title = "LGA Summed Results";
$active_tab = "q2";

// Fetch LGAs for Delta State (state_id = 25 or all available)
$lgas = [];
try {
    $stmt = $pdo->query("SELECT lga_id, lga_name FROM lga WHERE state_id = 25 ORDER BY lga_name ASC");
    $lgas = $stmt->fetchAll();
    if (empty($lgas)) {
        $stmt = $pdo->query("SELECT lga_id, lga_name FROM lga ORDER BY lga_name ASC");
        $lgas = $stmt->fetchAll();
    }
} catch (PDOException $e) {
    $error_msg = "Could not load LGAs: " . $e->getMessage();
}

// Handle LGA selection
$selected_lga_id = filter_input(INPUT_GET, 'lga_id', FILTER_VALIDATE_INT);
$selected_lga = null;
$summed_results = [];
$total_pu_summed_votes = 0;
$total_polling_units_counted = 0;
$official_lga_results = [];
$official_total_votes = 0;

if ($selected_lga_id) {
    try {
        // Find selected LGA name
        $lga_stmt = $pdo->prepare("SELECT lga_id, lga_name FROM lga WHERE lga_id = ? LIMIT 1");
        $lga_stmt->execute([$selected_lga_id]);
        $selected_lga = $lga_stmt->fetch();

        // 1. Question 2 requirement:
        // Sum announced_pu_results across all polling units belonging to this LGA
        // Join: polling_unit.uniqueid = announced_pu_results.polling_unit_uniqueid
        $sum_stmt = $pdo->prepare("
            SELECT 
                r.party_abbreviation, 
                SUM(r.party_score) AS total_score,
                COUNT(r.result_id) AS total_records
            FROM announced_pu_results r
            INNER JOIN polling_unit pu ON pu.uniqueid = r.polling_unit_uniqueid
            WHERE pu.lga_id = ?
            GROUP BY r.party_abbreviation
            ORDER BY total_score DESC
        ");
        $sum_stmt->execute([$selected_lga_id]);
        $summed_results = $sum_stmt->fetchAll();

        // Count unique polling units contributing to this LGA sum
        $pu_count_stmt = $pdo->prepare("
            SELECT COUNT(DISTINCT pu.uniqueid) as pu_count
            FROM polling_unit pu
            INNER JOIN announced_pu_results r ON pu.uniqueid = r.polling_unit_uniqueid
            WHERE pu.lga_id = ?
        ");
        $pu_count_stmt->execute([$selected_lga_id]);
        $total_polling_units_counted = (int)$pu_count_stmt->fetchColumn();

        foreach ($summed_results as $row) {
            $total_pu_summed_votes += (int)$row['total_score'];
        }

        // 2. Optional Nice Touch (from brief):
        // Query announced_lga_results to display alongside computed sum for cross-check/comparison
        // In bincom_test, lga_name in announced_lga_results may be stored as lga_id or name
        $off_stmt = $pdo->prepare("
            SELECT party_abbreviation, party_score
            FROM announced_lga_results
            WHERE lga_name = ? OR lga_name = ?
            ORDER BY party_score DESC
        ");
        $off_stmt->execute([(string)$selected_lga_id, $selected_lga['lga_name'] ?? '']);
        $official_rows = $off_stmt->fetchAll();

        // Map official results by party
        foreach ($official_rows as $row) {
            $official_lga_results[$row['party_abbreviation']] = (int)$row['party_score'];
            $official_total_votes += (int)$row['party_score'];
        }

    } catch (PDOException $e) {
        $error_msg = "Error calculating LGA results: " . $e->getMessage();
    }
}

require_once __DIR__ . '/includes/header.php';
?>

<div class="row mb-4">
    <div class="col-12">
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
            <div>
                <h2 class="fw-bold mb-1"><i class="bi bi-calculator-fill text-primary me-2"></i>Question 2: Summed Total LGA Results</h2>
                <p class="text-muted mb-0">Computes the total votes for all parties by dynamically summing individual polling unit results under the selected LGA.</p>
            </div>
            <span class="badge bg-info-subtle text-info-emphasis border border-info-subtle px-3 py-2 fs-6">
                <i class="bi bi-info-circle me-1"></i> Summed from <code>announced_pu_results</code>
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

<!-- LGA Selection Card -->
<div class="card shadow-sm mb-4">
    <div class="card-header bg-white py-3">
        <span class="fw-bold"><i class="bi bi-filter-circle text-primary me-1"></i> Select Local Government Area (LGA)</span>
    </div>
    <div class="card-body p-4">
        <form method="GET" action="lga_results.php" class="row g-3 align-items-center">
            <div class="col-md-8 col-lg-6">
                <label for="lgaSelect" class="form-label fw-semibold">Choose LGA (Delta State, ID 25):</label>
                <div class="input-group input-group-lg">
                    <span class="input-group-text bg-light"><i class="bi bi-building"></i></span>
                    <select class="form-select" id="lgaSelect" name="lga_id" onchange="this.form.submit()" required>
                        <option value="">-- Select an LGA to Compute Results --</option>
                        <?php foreach ($lgas as $lga): ?>
                            <option value="<?= htmlspecialchars($lga['lga_id']) ?>" <?= ($selected_lga_id == $lga['lga_id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($lga['lga_name']) ?> (LGA ID: <?= htmlspecialchars($lga['lga_id']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="col-md-4 col-lg-3 pt-md-4">
                <button type="submit" class="btn btn-primary btn-lg w-100">
                    <i class="bi bi-calculator me-1"></i> Calculate Sum
                </button>
            </div>
        </form>
    </div>
</div>

<?php if ($selected_lga_id && $selected_lga): ?>
    <div class="card shadow-sm mb-4">
        <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div>
                <h4 class="fw-bold mb-0 text-dark"><?= htmlspecialchars($selected_lga['lga_name']) ?> LGA</h4>
                <small class="text-muted">LGA ID: <?= htmlspecialchars($selected_lga['lga_id']) ?> &bull; Contributing Polling Units: <strong><?= $total_polling_units_counted ?></strong></small>
            </div>
            <div>
                <span class="badge bg-primary px-3 py-2 fs-6">
                    <i class="bi bi-check2-circle me-1"></i> Live Computed Sum
                </span>
            </div>
        </div>

        <div class="card-body p-4">
            <!-- Metrics Summary Cards -->
            <div class="row g-3 mb-4">
                <div class="col-md-4">
                    <div class="p-3 bg-primary-subtle rounded border border-primary-subtle stat-card">
                        <small class="text-primary fw-semibold d-block">SUMMED TOTAL VOTES (PU ACCUMULATED)</small>
                        <span class="fs-2 fw-bold text-primary"><?= number_format($total_pu_summed_votes) ?></span>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="p-3 bg-secondary-subtle rounded border border-secondary-subtle">
                        <small class="text-secondary fw-semibold d-block">OFFICIALLY ANNOUNCED LGA TOTAL</small>
                        <span class="fs-2 fw-bold text-dark">
                            <?= !empty($official_total_votes) ? number_format($official_total_votes) : '<span class="text-muted fs-5">None on file</span>' ?>
                        </span>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="p-3 bg-light rounded border">
                        <small class="text-muted fw-semibold d-block">TOTAL CONTRIBUTING POLLING UNITS</small>
                        <span class="fs-2 fw-bold text-dark"><?= number_format($total_polling_units_counted) ?></span>
                    </div>
                </div>
            </div>

            <?php if (!empty($summed_results)): ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle border">
                        <thead class="table-light">
                            <tr>
                                <th style="width: 70px;">#</th>
                                <th>Political Party</th>
                                <th class="text-end">Summed PU Total (Computed)</th>
                                <th style="width: 25%;">Computed Vote Share</th>
                                <th class="text-end bg-light border-start">Official LGA Announced</th>
                                <th class="text-center bg-light">Variance / Check</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $idx = 1;
                            foreach ($summed_results as $row): 
                                $party = trim($row['party_abbreviation']);
                                $computed_score = (int)$row['total_score'];
                                $pct = $total_pu_summed_votes > 0 ? round(($computed_score / $total_pu_summed_votes) * 100, 1) : 0;
                                $official_score = $official_lga_results[$party] ?? null;
                                $variance = ($official_score !== null) ? ($computed_score - $official_score) : null;
                                $party_lower = strtolower($party);
                            ?>
                                <tr>
                                    <td class="fw-semibold text-muted"><?= $idx++ ?></td>
                                    <td>
                                        <span class="party-badge party-<?= htmlspecialchars($party_lower) ?> border">
                                            <?= htmlspecialchars($party) ?>
                                        </span>
                                    </td>
                                    <td class="text-end fw-bold fs-5 text-primary">
                                        <?= number_format($computed_score) ?>
                                    </td>
                                    <td>
                                        <div class="d-flex align-items-center gap-2">
                                            <div class="progress flex-grow-1" style="height: 10px;">
                                                <div class="progress-bar bg-primary" role="progressbar" style="width: <?= $pct ?>%;" aria-valuenow="<?= $pct ?>" aria-valuemin="0" aria-valuemax="100"></div>
                                            </div>
                                            <small class="text-muted fw-bold" style="min-width: 45px;"><?= $pct ?>%</small>
                                        </div>
                                    </td>
                                    <td class="text-end border-start fw-semibold <?= ($official_score !== null) ? 'text-dark' : 'text-muted' ?>">
                                        <?= ($official_score !== null) ? number_format($official_score) : '&mdash;' ?>
                                    </td>
                                    <td class="text-center">
                                        <?php if ($variance === null): ?>
                                            <span class="badge bg-secondary-subtle text-secondary">N/A</span>
                                        <?php elseif ($variance === 0): ?>
                                            <span class="badge bg-success"><i class="bi bi-check-lg"></i> Match</span>
                                        <?php else: ?>
                                            <span class="badge bg-warning-subtle text-dark border border-warning">
                                                <?= ($variance > 0 ? '+' : '') . number_format($variance) ?>
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot class="table-light fw-bold">
                            <tr>
                                <td colspan="2" class="text-end">Total Sum:</td>
                                <td class="text-end text-primary fs-5"><?= number_format($total_pu_summed_votes) ?></td>
                                <td>100%</td>
                                <td class="text-end border-start fs-5"><?= number_format($official_total_votes) ?></td>
                                <td></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>

                <div class="alert alert-light border mt-3 small text-muted">
                    <i class="bi bi-info-circle-fill text-primary me-1"></i>
                    <strong>Collation Verification Note:</strong> Per the test specifications, the <em>Summed PU Total</em> is calculated strictly by summing records from <code>announced_pu_results</code> where <code>polling_unit.lga_id = <?= htmlspecialchars($selected_lga_id) ?></code>. The <em>Official LGA Announced</em> column is displayed alongside for cross-check auditing.
                </div>
            <?php else: ?>
                <div class="text-center py-5">
                    <i class="bi bi-calculator text-muted display-4 d-block mb-3"></i>
                    <h5 class="fw-semibold text-secondary">No Polling Unit Results Recorded For This LGA</h5>
                    <p class="text-muted">There are currently no polling unit records with announced results for <strong><?= htmlspecialchars($selected_lga['lga_name']) ?></strong> in the database.</p>
                    <a href="new_polling_unit.php" class="btn btn-primary btn-sm">
                        <i class="bi bi-plus-circle me-1"></i> Enter New Results for this LGA
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>
<?php else: ?>
    <!-- LGA Quick Suggestion Chips -->
    <div class="card shadow-sm border-0 bg-white p-4">
        <h5 class="fw-bold mb-3"><i class="bi bi-lightning-charge-fill text-warning me-1"></i> Popular LGAs with recorded election data:</h5>
        <div class="d-flex flex-wrap gap-2">
            <a href="lga_results.php?lga_id=6" class="btn btn-outline-primary btn-sm">
                <i class="bi bi-geo-alt me-1"></i> Ethiope West (LGA #6) &bull; 4 PUs
            </a>
            <a href="lga_results.php?lga_id=22" class="btn btn-outline-primary btn-sm">
                <i class="bi bi-geo-alt me-1"></i> Uvwie (LGA #22) &bull; 5 PUs
            </a>
            <a href="lga_results.php?lga_id=19" class="btn btn-outline-primary btn-sm">
                <i class="bi bi-geo-alt me-1"></i> Ughelli North (LGA #19) &bull; 2 PUs
            </a>
            <a href="lga_results.php?lga_id=17" class="btn btn-outline-primary btn-sm">
                <i class="bi bi-geo-alt me-1"></i> Sapele (LGA #17) &bull; 1 PU
            </a>
            <a href="lga_results.php?lga_id=35" class="btn btn-outline-primary btn-sm">
                <i class="bi bi-geo-alt me-1"></i> Warri South West (LGA #35) &bull; 3 PUs
            </a>
        </div>
    </div>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
