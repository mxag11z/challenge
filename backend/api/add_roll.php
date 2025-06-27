<?php
/**
 * API para agregar nuevos rollos de tela
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
        'fabric_type' => ['required', 'min:2', 'max:100'],
        'color' => ['required', 'min:2', 'max:50'],
        'length' => ['required', 'numeric', 'positive'],
        'entry_date' => ['required', 'date']
    ];
    
    $errors = validateInput($input, $validationRules);
    
    if (!empty($errors)) {
        sendJsonResponse(['error' => 'Datos inválidos', 'details' => $errors], 400);
    }
    
    // Validar que la fecha no sea futura
    $entryDate = new DateTime($input['entry_date']);
    $today = new DateTime();
    
    if ($entryDate > $today) {
        sendJsonResponse(['error' => 'La fecha de ingreso no puede ser futura'], 400);
    }
    
    // Conectar a la base de datos
    $database = new Database();
    $db = $database->getConnection();
    
    // Preparar consulta SQL
    $query = "INSERT INTO fabric_rolls (fabric_type, color, original_length, current_length, entry_date) 
              VALUES (:fabric_type, :color, :length, :length, :entry_date)";
    
    $stmt = $db->prepare($query);
    
    // Bind parameters
    $stmt->bindParam(':fabric_type', $input['fabric_type']);
    $stmt->bindParam(':color', $input['color']);
    $stmt->bindParam(':length', $input['length']);
    $stmt->bindParam(':entry_date', $input['entry_date']);
    
    // Ejecutar consulta
    if ($stmt->execute()) {
        $rollId = $db->lastInsertId();
        
        // Obtener el rollo recién creado para devolverlo
        $selectQuery = "SELECT * FROM fabric_rolls WHERE id = :id";
        $selectStmt = $db->prepare($selectQuery);
        $selectStmt->bindParam(':id', $rollId);
        $selectStmt->execute();
        
        $newRoll = $selectStmt->fetch(PDO::FETCH_ASSOC);
        
        sendJsonResponse([
            'success' => true,
            'message' => 'Rollo agregado exitosamente',
            'data' => $newRoll
        ], 201);
    } else {
        sendJsonResponse(['error' => 'Error al agregar el rollo'], 500);
    }
    
} catch (Exception $e) {
    error_log("Error en add_roll.php: " . $e->getMessage());
    sendJsonResponse(['error' => 'Error interno del servidor'], 500);
}
?>