<?php
/**
 * API para obtener el inventario de telas
 */

require_once '../config/database.php';

// Manejar preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type');
    exit;
}

// Solo permitir método GET
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendJsonResponse(['error' => 'Método no permitido'], 405);
}

try {
    // Conectar a la base de datos
    $database = new Database();
    $db = $database->getConnection();
    
    // Obtener parámetros de ordenamiento
    $orderBy = isset($_GET['order_by']) ? $_GET['order_by'] : 'entry_date';
    $orderDir = isset($_GET['order_dir']) ? $_GET['order_dir'] : 'DESC';
    
    // Validar parámetros de ordenamiento
    $allowedOrderBy = ['entry_date', 'current_length', 'fabric_type', 'color'];
    $allowedOrderDir = ['ASC', 'DESC'];
    
    if (!in_array($orderBy, $allowedOrderBy)) {
        $orderBy = 'entry_date';
    }
    
    if (!in_array(strtoupper($orderDir), $allowedOrderDir)) {
        $orderDir = 'DESC';
    }
    
    // Obtener filtros opcionales
    $fabricType = isset($_GET['fabric_type']) ? trim($_GET['fabric_type']) : '';
    $color = isset($_GET['color']) ? trim($_GET['color']) : '';
    $minStock = isset($_GET['min_stock']) ? floatval($_GET['min_stock']) : 0;
    
    // Construir consulta base
    $query = "SELECT 
                id,
                fabric_type,
                color,
                original_length,
                current_length,
                entry_date,
                ROUND(((current_length / original_length) * 100), 2) as stock_percentage,
                created_at,
                updated_at
              FROM fabric_rolls 
              WHERE current_length >= :min_stock";
    
    $params = [':min_stock' => $minStock];
    
    // Aplicar filtros
    if (!empty($fabricType)) {
        $query .= " AND fabric_type LIKE :fabric_type";
        $params[':fabric_type'] = "%$fabricType%";
    }
    
    if (!empty($color)) {
        $query .= " AND color LIKE :color";
        $params[':color'] = "%$color%";
    }
    
    // Aplicar ordenamiento
    $query .= " ORDER BY $orderBy $orderDir";
    
    // Preparar y ejecutar consulta
    $stmt = $db->prepare($query);
    
    foreach ($params as $param => $value) {
        $stmt->bindValue($param, $value);
    }
    
    $stmt->execute();
    $rolls = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Obtener estadísticas del inventario
    $statsQuery = "SELECT 
                     COUNT(*) as total_rolls,
                     SUM(current_length) as total_meters,
                     AVG(current_length) as avg_meters_per_roll,
                     COUNT(DISTINCT fabric_type) as fabric_types_count,
                     COUNT(DISTINCT color) as colors_count
                   FROM fabric_rolls 
                   WHERE current_length > 0";
    
    $statsStmt = $db->prepare($statsQuery);
    $statsStmt->execute();
    $stats = $statsStmt->fetch(PDO::FETCH_ASSOC);
    
    // Obtener tipos de tela únicos para filtros
    $typesQuery = "SELECT DISTINCT fabric_type FROM fabric_rolls ORDER BY fabric_type";
    $typesStmt = $db->prepare($typesQuery);
    $typesStmt->execute();
    $fabricTypes = $typesStmt->fetchAll(PDO::FETCH_COLUMN);
    
    // Obtener colores únicos para filtros
    $colorsQuery = "SELECT DISTINCT color FROM fabric_rolls ORDER BY color";
    $colorsStmt = $db->prepare($colorsQuery);
    $colorsStmt->execute();
    $colors = $colorsStmt->fetchAll(PDO::FETCH_COLUMN);
    
    // Preparar respuesta
    $response = [
        'success' => true,
        'data' => $rolls,
        'stats' => [
            'total_rolls' => (int)$stats['total_rolls'],
            'total_meters' => round($stats['total_meters'], 2),
            'avg_meters_per_roll' => round($stats['avg_meters_per_roll'], 2),
            'fabric_types_count' => (int)$stats['fabric_types_count'],
            'colors_count' => (int)$stats['colors_count']
        ],
        'filters' => [
            'fabric_types' => $fabricTypes,
            'colors' => $colors
        ],
        'pagination' => [
            'total' => count($rolls),
            'order_by' => $orderBy,
            'order_dir' => $orderDir
        ]
    ];
    
    sendJsonResponse($response);
    
} catch (Exception $e) {
    error_log("Error en get_inventory.php: " . $e->getMessage());
    sendJsonResponse(['error' => 'Error interno del servidor'], 500);
}
?>