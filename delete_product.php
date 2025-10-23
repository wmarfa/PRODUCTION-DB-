<?php
require_once "config.php";

$database = Database::getInstance();
$db = $database->getConnection();

if (isset($_GET['id'])) {
    $id = $_GET['id'];
    
    try {
        // Check if product is being used in any performance records
        $check_assy = "SELECT COUNT(*) as count FROM assy_performance WHERE product_id = ?";
        $stmt_assy = $db->prepare($check_assy);
        $stmt_assy->execute([$id]);
        $assy_count = $stmt_assy->fetch(PDO::FETCH_ASSOC)['count'];
        
        $check_packing = "SELECT COUNT(*) as count FROM packing_performance WHERE product_id = ?";
        $stmt_packing = $db->prepare($check_packing);
        $stmt_packing->execute([$id]);
        $packing_count = $stmt_packing->fetch(PDO::FETCH_ASSOC)['count'];
        
        if ($assy_count > 0 || $packing_count > 0) {
            header("Location: products.php?error=Cannot+delete+product.+It+is+being+used+in+performance+records.");
            exit();
        }
        
        // Delete product
        $query = "DELETE FROM products WHERE id = ?";
        $stmt = $db->prepare($query);
        $stmt->execute([$id]);
        
        header("Location: products.php?message=Product+deleted+successfully");
    } catch (PDOException $exception) {
        header("Location: products.php?error=" . urlencode($exception->getMessage()));
    }
} else {
    header("Location: products.php?error=No+product+ID+specified");
}
exit();
?>