<?php
require_once "config.php";

$database = new Database();
$db = $database->getConnection();

$message = "";
if (isset($_GET['message'])) {
    $message = htmlspecialchars($_GET['message']);
}
if (isset($_GET['error'])) {
    $message = "Error: " . htmlspecialchars($_GET['error']);
}

// Fetch all daily performance records
$query = "SELECT * FROM daily_performance ORDER BY date DESC, line_shift ASC";
$stmt = $db->prepare($query);
$stmt->execute();
$data = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Helper function to safely format numbers
function safe_number_format($value, $decimals = 0) {
    if ($value === null || $value === '') {
        return '0';
    }
    return number_format(floatval($value), $decimals);
}

// Helper function to safely format decimal numbers
function safe_decimal_format($value, $decimals = 1) {
    if ($value === null || $value === '') {
        return '0.' . str_repeat('0', $decimals);
    }
    return number_format(floatval($value), $decimals);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daily Performance Data View</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="styles.css" rel="stylesheet">
</head>
<body>
    <?php require_once "navbar.php"; ?>
    
    <div class="container-fluid mt-5 mb-5" style="max-width: 1600px;">
        <div class="page-header mb-4">
            <h1 class="page-title">DAILY PERFORMANCE DATA VIEW</h1>
            <p class="lead text-muted">Manage and review all performance records</p>
        </div>

        <?php if ($message): ?>
            <div class="alert <?= strpos($message, 'Error') !== false ? 'alert-danger' : 'alert-success' ?> d-flex align-items-center" role="alert">
                <i class="fas <?= strpos($message, 'Error') !== false ? 'fa-times-circle' : 'fa-check-circle' ?> me-2"></i>
                <div><?= $message ?></div>
            </div>
        <?php endif; ?>

        <div class="card mb-4">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <a href="entry_form.php" class="btn btn-primary me-2">
                            <i class="fas fa-plus-circle me-2"></i> Enter New Data
                        </a>
                        <a href="export_data.php" class="btn btn-success me-2">
                            <i class="fas fa-file-export me-2"></i> Export All to CSV
                        </a>
                    </div>
                    <a href="index.php" class="btn btn-secondary">
                        <i class="fas fa-home me-2"></i> Back to Home
                    </a>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <i class="fas fa-database me-2"></i> All Daily Records (<?= count($data) ?> Total)
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm table-striped table-hover mb-0">
                        <thead>
                            <tr>
                                <th style="width: 3%;">ID</th>
                                <th style="width: 8%;">Date</th>
                                <th style="width: 10%;">Line/Shift</th>
                                <th style="width: 10%;">Leader</th>
                                <th class="text-end" style="width: 5%;">Total MP</th>
                                <th class="text-end" style="width: 5%;">Absent</th>
                                <th class="text-end" style="width: 5%;">Separated</th>
                                <th class="text-end" style="width: 5%;">Plan (Pcs)</th>
                                <th class="text-end" style="width: 5%;">ASSY WT (H)</th>
                                <th class="text-end" style="width: 5%;">OT Hours</th>
                                <th class="text-end" style="width: 8%;">MP (No OT)</th>
                                <th class="text-end" style="width: 8%;">MP (OT)</th>
                                <th style="width: 15%;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($data) > 0): ?>
                                <?php foreach ($data as $record): ?>
                                <tr>
                                    <td><?= htmlspecialchars($record['id']) ?></td>
                                    <td><?= date('Y-m-d', strtotime($record['date'])) ?></td>
                                    <td><?= htmlspecialchars($record['line_shift']) ?></td>
                                    <td><?= htmlspecialchars($record['leader']) ?></td>
                                    <td class="text-end"><?= safe_number_format($record['mp']) ?></td>
                                    <td class="text-end text-danger"><?= safe_number_format($record['absent']) ?></td>
                                    <td class="text-end text-danger"><?= safe_number_format($record['separated_mp']) ?></td>
                                    <td class="text-end"><?= safe_number_format($record['plan']) ?></td>
                                    <td class="text-end"><?= safe_decimal_format($record['assy_wt'], 1) ?></td>
                                    <td class="text-end"><?= safe_decimal_format($record['ot_hours'], 1) ?></td>
                                    <td class="text-end"><?= safe_number_format($record['no_ot_mp']) ?></td>
                                    <td class="text-end"><?= safe_number_format($record['ot_mp']) ?></td>
                                    <td>
                                        <a href="edit_data.php?id=<?= $record['id'] ?>" class="btn btn-warning btn-sm" title="Edit Record">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <a href="view_details.php?id=<?= $record['id'] ?>" class="btn btn-info btn-sm" title="View Details">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <button class="btn btn-danger btn-sm" title="Delete Record" 
                                                onclick="confirmDelete(<?= $record['id'] ?>, '<?= date('Y-m-d', strtotime($record['date'])) ?>', '<?= $record['line_shift'] ?>')">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="13" class="text-center p-4 text-muted">No daily performance records found.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <footer class="footer">
        <div class="container">
            <div class="row">
                <div class="col-md-6">
                    <h5 class="mb-3">Performance Tracking System</h5>
                    <p class="text-light">Operational oversight and data management solution for manufacturing performance tracking.</p>
                </div>
                <div class="col-md-6 text-md-end">
                    <p class="mb-0">&copy; <?php echo date('Y'); ?> Performance Tracking System. All rights reserved.</p>
                </div>
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function confirmDelete(id, date, line) {
            if (confirm(`Are you sure you want to delete the performance record for Date: ${date}, Line: ${line} (ID: ${id})? This will delete all associated product outputs and cannot be undone.`)) {
                window.location.href = 'delete_data.php?id=' + id;
            }
        }
    </script>
</body>
</html>