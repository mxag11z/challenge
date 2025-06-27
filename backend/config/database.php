<?php
/**
 * Configuración de conexión a la base de datos
 */

class Database {
    private $host = 'localhost';
    private $db_name = 'textile_inventory';
    private $username = 'root';
    private $password = '';
    private $conn;

    /**
     * Obtener conexión a la base de datos
     */
    public function getConnection() {
        $this->conn = null;
        
        try {
            $this->conn = new PDO(
                "mysql:host=" . $this->host . ";dbname=" . $this->db_name . ";charset=utf8",
                $this->username,
                $this->password
            );
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch(PDOException $exception) {
            error_log("Error de conexión: " . $exception->getMessage());
            throw new Exception("Error de conexión a la base de datos");
        }
        
        return $this->conn;
    }
}

/**
 * Función auxiliar para respuestas JSON
 */
function sendJsonResponse($data, $status = 200) {
    http_response_code($status);
    header('Content-Type: application/json');
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization');
    
    echo json_encode($data);
    exit;
}

/**
 * Función para validar datos de entrada
 */
function validateInput($data, $rules) {
    $errors = [];
    
    foreach ($rules as $field => $rule) {
        if (!isset($data[$field]) || empty(trim($data[$field]))) {
            if (in_array('required', $rule)) {
                $errors[$field] = "El campo $field es requerido";
            }
            continue;
        }
        
        $value = trim($data[$field]);
        
        // Validar tipo numérico
        if (in_array('numeric', $rule) && !is_numeric($value)) {
            $errors[$field] = "El campo $field debe ser numérico";
        }
        
        // Validar valor positivo
        if (in_array('positive', $rule) && $value <= 0) {
            $errors[$field] = "El campo $field debe ser mayor a 0";
        }
        
        // Validar longitud mínima
        foreach ($rule as $r) {
            if (strpos($r, 'min:') === 0) {
                $min = (int)str_replace('min:', '', $r);
                if (strlen($value) < $min) {
                    $errors[$field] = "El campo $field debe tener al menos $min caracteres";
                }
            }
        }
        
        // Validar longitud máxima
        foreach ($rule as $r) {
            if (strpos($r, 'max:') === 0) {
                $max = (int)str_replace('max:', '', $r);
                if (strlen($value) > $max) {
                    $errors[$field] = "El campo $field no puede tener más de $max caracteres";
                }
            }
        }
        
        // Validar formato de fecha
        if (in_array('date', $rule)) {
            $date = DateTime::createFromFormat('Y-m-d', $value);
            if (!$date || $date->format('Y-m-d') !== $value) {
                $errors[$field] = "El campo $field debe tener formato de fecha válido (YYYY-MM-DD)";
            }
        }
    }
    
    return $errors;
}
?>