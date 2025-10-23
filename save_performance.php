<?php
require_once "config.php";

if ($_POST) {
    $database = Database::getInstance();
    $db = $database->getConnection();

    try {
        $db->beginTransaction();

        // Insert daily performance
        $query = "INSERT INTO daily_performance 
                  (date, line_shift, leader, mp, qc, absent, separated_mp, no_ot_mp, ot_mp, ot_hours, plan, assy_wt) 
                  VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $db->prepare($query);
        $stmt->execute([
            $_POST['date'],
            $_POST['line_shift'],
            $_POST['leader'],
            $_POST['mp'],
            $_POST['qc'] ?? 0,
            $_POST['absent'],
            $_POST['separated_mp'],
            $_POST['no_ot_mp'],
            $_POST['ot_mp'],
            $_POST['ot_hours'] ?? 0,
            $_POST['plan'],
            $_POST['assy_wt']
        ]);

        $daily_performance_id = $db->lastInsertId();

        // Insert ASSY performance
        if (isset($_POST['assy_product_id'])) {
            for ($i = 0; $i < count($_POST['assy_product_id']); $i++) {
                if (!empty($_POST['assy_product_id'][$i]) && !empty($_POST['assy_output'][$i])) {
                    $query = "INSERT INTO assy_performance (daily_performance_id, product_id, assy_output) VALUES (?, ?, ?)";
                    $stmt = $db->prepare($query);
                    $stmt->execute([
                        $daily_performance_id, 
                        $_POST['assy_product_id'][$i], 
                        $_POST['assy_output'][$i]
                    ]);
                }
            }
        }

        // Insert Packing performance
        if (isset($_POST['packing_product_id'])) {
            for ($i = 0; $i < count($_POST['packing_product_id']); $i++) {
                if (!empty($_POST['packing_product_id'][$i]) && !empty($_POST['packing_output'][$i])) {
                    $query = "INSERT INTO packing_performance (daily_performance_id, product_id, packing_output) VALUES (?, ?, ?)";
                    $stmt = $db->prepare($query);
                    $stmt->execute([
                        $daily_performance_id, 
                        $_POST['packing_product_id'][$i], 
                        $_POST['packing_output'][$i]
                    ]);
                }
            }
        }

        $db->commit();
        header("Location: entry_form.php?success=1");
        exit();

    } catch (Exception $e) {
        $db->rollBack();
        error_log("Save Performance Error: " . $e->getMessage());
        header("Location: entry_form.php?error=" . urlencode("Database error: " . $e->getMessage()));
        exit();
    }
} else {
    header("Location: entry_form.php?error=No data submitted");
    exit();
}
?>