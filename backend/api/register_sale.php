<?php
/**
 * API para registrar ventas de tela
 */

require_once '../config/database.php';

// Manejar preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type');
    exit;
}

// Solo permitir método POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJsonResponse(['error' => 'Método no permitido'], 405);
}

try {
    // Obtener datos JSON del cuerpo de la petición
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        sendJsonResponse(['error' => 'Datos inválidos'], 400);
    }
    
    // Validar campos requeridos
    $validationRules = [
        'roll_id' => ['required', 'numeric', 'positive'],
        'meters_sold' => ['required', 'numeric', 'positive'],
        'sale_date' => ['required', 'date']
    ];
    
    $errors = validateInput($input, $validationRules);
    
    if (!empty($errors)) {
        sendJsonResponse(['error' => 'Datos inválidos', 'details' => $errors], 400);
    }
    
    // Validar que la fecha no sea futura
    $saleDate = new DateTime($input['sale_date']);
    $today = new DateTime();
    
    if ($saleDate > $today) {
        sendJsonResponse(['error' => 'La fecha de venta no puede ser futura'], 400);
    }
    
    // Conectar a la base de datos
    $database = new Database();
    $db = $database->getConnection();
    
    // Iniciar transacción
    $db->beginTransaction();
    
    try {
        // Verificar que el rollo existe y obtener su stock actual
        $checkQuery = "SELECT id, fabric_type, color, current_length FROM fabric_rolls WHERE id = :roll_id";
        $checkStmt = $db->prepare($checkQuery);
        $checkStmt->bindParam(':roll_id', $input['roll_id']);
        $checkStmt->execute();
        
        $roll = $checkStmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$roll) {
            throw new Exception('El rollo especificado no existe');
        }
        
        // Validar que hay suficiente stock
        if ($input['meters_sold'] > $roll['current_length']) {
            throw new Exception("No hay suficiente stock. Disponible: {$roll['current_length']} metros, solicitado: {$input['meters_sold']} metros");
        }
        
        // Registrar la venta
        $saleQuery = "INSERT INTO sales (roll_id, meters_sold, sale_date) VALUES (:roll_id, :meters_sold, :sale_date)";
        $saleStmt = $db->prepare($saleQuery);
        $saleStmt->bindParam(':roll_id', $input['roll_id']);
        $saleStmt->bindParam(':meters_sold', $input['meters_sold']);
        $saleStmt->bindParam(':sale_date', $input['sale_date']);
        
        if (!$saleStmt->execute()) {
            throw new Exception('Error al registrar la venta');
        }
        
        // Actualizar el stock del rollo
        $newLength = $roll['current_length'] - $input['meters_sold'];
        $updateQuery = "UPDATE fabric_rolls SET current_length = :new_length WHERE id = :roll_id";
        $updateStmt = $db->prepare($updateQuery);
        $updateStmt->bindParam(':new_length', $newLength);
        $updateStmt->bindParam(':roll_id', $input['roll_id']);
        
        if (!$updateStmt->execute()) {
            throw new Exception('Error al actualizar el stock');
        }
        
        // Confirmar transacción
        $db->commit();
        
        // Obtener datos actualizados del rollo
        $updatedRoll = $checkStmt->fetch(PDO::FETCH_ASSOC);
        $checkStmt->execute();
        $updatedRoll = $checkStmt->fetch(PDO::FETCH_ASSOC);
        $updatedRoll['current_length'] = $newLength;
        
        sendJsonResponse([
            'success' => true,
            'message' => 'Venta registrada exitosamente',
            'data' => [
                'sale_id' => $db->lastInsertId(),
                'updated_roll' => $updatedRoll,
                'meters_sold' => $input['meters_sold'],
                'remaining_stock' => $newLength
            ]
        ], 201);
        
    } catch (Exception $e) {
        // Rollback en caso de error
        $db->rollback();
        throw $e;
    }
    
} catch (Exception $e) {
    error_log("Error en register_sale.php: " . $e->getMessage());
    sendJsonResponse(['error' => $e->getMessage()], 400);
}
?>