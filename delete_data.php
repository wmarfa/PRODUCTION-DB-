<?php
require_once "config.php";

$database = Database::getInstance();
$db = $database->getConnection();

if (isset($_GET['id'])) {
    $id = $_GET['id'];
    
    try {
        // Start transaction
        $db->beginTransaction();
        
        // Delete related ASSY data
        $delete_assy = "DELETE FROM assy_performance WHERE daily_performance_id = ?";
        $stmt_assy = $db->prepare($delete_assy);
        $stmt_assy->bindParam(1, $id);
        $stmt_assy->execute();
        
        // Delete related Packing data
        $delete_packing = "DELETE FROM packing_performance WHERE daily_performance_id = ?";
        $stmt_packing = $db->prepare($delete_packing);
        $stmt_packing->bindParam(1, $id);
        $stmt_packing->execute();
        
        // Delete main record
        $query = "DELETE FROM daily_performance WHERE id = ?";
        $stmt = $db->prepare($query);
        $stmt->bindParam(1, $id);
        $stmt->execute();
        
        // Commit transaction
        $db->commit();
        
        header("Location: view_data.php?message=Record+deleted+successfully");
    } catch (PDOException $exception) {
        // Rollback transaction on error
        $db->rollBack();
        header("Location: view_data.php?error=" . urlencode($exception->getMessage()));
    }
} else {
    header("Location: view_data.php?error=No+record+ID+specified");
}
exit();
?>