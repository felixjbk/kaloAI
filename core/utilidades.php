<?php

/**
 * GESTIÓN DE SEGURIDAD (Contraseñas)
 * Implemento funciones para el manejo seguro de credenciales mediante hashing.
 */

/**
 * Genero un hash seguro para la contraseña utilizando el algoritmo por defecto del sistema.
 * Actualmente utiliza Bcrypt o Argon2 según la versión de PHP.
 */
function hashContrasena(string $password): string {
    return password_hash($password, PASSWORD_DEFAULT);
}

/**
 * Comparo la contraseña introducida en el login con el hash almacenado en la base de datos.
 * Devuelve true si la verificación es exitosa.
 */
function verificarContrasena(string $password, string $hash): bool {
    return password_verify($password, $hash);
}


/**
 * SANITIZACIÓN Y VALIDACIÓN DE DATOS
 * Conjunto de funciones para limpiar entradas de usuario y prevenir ataques como XSS.
 */

/**
 * Limpio las cadenas de texto eliminando etiquetas HTML, barras invertidas y 
 * convirtiendo caracteres especiales para evitar ejecuciones de script maliciosas.
 */
function limpiarInput(string $data): string {
    $data = strip_tags($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    return trim($data);
}

/**
 * Verifico si el formato del correo electrónico es válido siguiendo los estándares de PHP.
 */
function emailValido(string $email): bool {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Realizo una redirección HTTP forzosa y finalizo la ejecución del script actual.
 */
function redirect(string $url): void {
    header("Location: " . $url);
    exit();
}


/**
 * CÁLCULOS NUTRICIONALES Y LÓGICA DE SALUD
 * Funciones encargadas de procesar los datos antropométricos del usuario.
 */

/**
 * Obtengo la edad exacta calculando la diferencia entre la fecha de nacimiento y la fecha actual.
 */
function calcularEdad(string $fechaNacimiento): int {
    try {
        $fechaNacimientoObj = new DateTime($fechaNacimiento);
        $hoy = new DateTime();
        $diferencia = $hoy->diff($fechaNacimientoObj);
        return $diferencia->y;
    } catch (Exception $e) {
        // Registro fallos en el formato de fecha para depuración
        error_log("Error al calcular la edad: " . $e->getMessage());
        return 0;
    }
}

/**
 * Calculo el Gasto Energético Diario Total (TDEE).
 * Primero obtengo la Tasa Metabólica Basal (TMB) con la ecuación de Mifflin-St Jeor 
 * y luego aplico el factor de actividad física seleccionado.
 */
function calcularTDEE(string $genero, float $pesoKg, int $estaturaCm, int $edad, float $nivelActividad): int {
    // Calculo la base metabólica diferenciando por género
    $tmb = (10 * $pesoKg) + (6.25 * $estaturaCm) - (5 * $edad);

    if ($genero === "M") {
        $tmb += 5; // Ajuste para hombres
    } else {
        $tmb -= 161; // Ajuste para mujeres
    }

    // Multiplico por el factor de actividad para obtener las calorías de mantenimiento
    $tdee = $tmb * $nivelActividad;

    return (int) round($tdee);
}


/**
 * Determino los objetivos de macronutrientes en gramos.
 * Ajusto las calorías totales según el objetivo (déficit o superávit) y reparto 
 * los porcentajes de Proteínas, Carbohidratos y Grasas.
 */
function calcularMacrosObjetivo(int $tdeeCalorias, string $objetivo): array {
    // Valores energéticos estándar por gramo de macronutriente
    $KCAL_POR_PROTEINA = 4;
    $KCAL_POR_CARBOHIDRATO = 4;
    $KCAL_POR_GRASA = 9;

    $caloriasNetas = $tdeeCalorias;
    
    /**
     * AJUSTE CALÓRICO POR OBJETIVO
     * Aplico superávit para ganancia muscular o déficit para pérdida de peso.
     */
    switch ($objetivo) {
        case 'perdida_rapida':
            $caloriasNetas -= 750;
            break;
        case 'perdida_moderada':
            $caloriasNetas -= 500;
            break;
        case 'perdida_grasa':
            $caloriasNetas -= 350;
            break;
        case 'ganancia_muscular':
            $caloriasNetas += 300;
            break;
        case 'recomposicion_corporal':
            $caloriasNetas = $tdeeCalorias;
            break;
        case 'rendimiento_deportivo':
            $caloriasNetas += 500;
            break;
        case 'mantenimiento':
        default:
            $caloriasNetas = $tdeeCalorias;
            break;
    }
    
    // Establezco un suelo calórico de seguridad para evitar dietas extremas
    $caloriasNetas = max(1200, $caloriasNetas);
    
    /**
     * REPARTO DE MACRONUTRIENTES
     * Distribuyo las calorías totales en porcentajes optimizados para cada meta.
     */
    $macroPorcentajes = [
        'proteinas' => 0.30, 
        'carbohidratos' => 0.45, 
        'grasas' => 0.25 
    ];

    switch ($objetivo) {
        case 'perdida_rapida':
        case 'perdida_grasa':
        case 'recomposicion_corporal':
            // Priorizo la proteína para proteger la masa muscular en déficit
            $macroPorcentajes = ['proteinas' => 0.35, 'carbohidratos' => 0.40, 'grasas' => 0.25];
            break;
        case 'ganancia_muscular':
            // Priorizo carbohidratos para favorecer el entrenamiento intenso
            $macroPorcentajes = ['proteinas' => 0.30, 'carbohidratos' => 0.50, 'grasas' => 0.20];
            break;
        case 'rendimiento_deportivo':
            $macroPorcentajes = ['proteinas' => 0.25, 'carbohidratos' => 0.55, 'grasas' => 0.20];
            break;
    }

    // Calculo el aporte calórico de cada grupo y convierto a gramos
    $caloriasProteinas = $caloriasNetas * $macroPorcentajes['proteinas'];
    $caloriasCarbohidratos = $caloriasNetas * $macroPorcentajes['carbohidratos'];
    $caloriasGrasas = $caloriasNetas - $caloriasProteinas - $caloriasCarbohidratos;
    
    $proteinasGramos = $caloriasProteinas / $KCAL_POR_PROTEINA;
    $carbohidratosGramos = $caloriasCarbohidratos / $KCAL_POR_CARBOHIDRATO;
    $grasasGramos = $caloriasGrasas / $KCAL_POR_GRASA;

    return [
        'proteinas' => (int) round($proteinasGramos),
        'carbohidratos' => (int) round($carbohidratosGramos),
        'grasas' => (int) round($grasasGramos),
        'calorias_netas' => (int) round($caloriasNetas)
    ];
}

/**
 * Formateo fechas para una visualización más amigable en la interfaz (ej: 14 Nov 2025).
 */
function formatearFecha($date) {
    if (!$date) return '';
    $timestamp = strtotime($date);
    if (!$timestamp) return $date;
    return date('d M Y', $timestamp);
}

/**
 * Extraigo el nombre de pila de una cadena de nombre completo para personalizar el saludo.
 */
function getPrimerNombre($fullName) {
    if (empty($fullName)) return 'KaloAI';
    $parts = explode(' ', $fullName);
    return htmlspecialchars($parts[0]);
}

/**
 * Defino los factores de conversión para las distintas unidades de medida del sistema.
 */
function getDetallesConversion() {
    return [
        'g' => ['group' => 'peso', 'base_unit' => 'g', 'conversion_factor' => 1],
        'kg' => ['group' => 'peso', 'base_unit' => 'g', 'conversion_factor' => 1000],
        'ml' => ['group' => 'volumen', 'base_unit' => 'ml', 'conversion_factor' => 1],
        'L' => ['group' => 'volumen', 'base_unit' => 'ml', 'conversion_factor' => 1000],
        'unidad' => ['group' => 'conteo', 'base_unit' => 'unidad', 'conversion_factor' => 1],
        'caja' => ['group' => 'conteo', 'base_unit' => 'caja', 'conversion_factor' => 1],
    ];
}

/**
 * Normalizo cadenas de texto eliminando tildes y espacios extra para comparaciones precisas.
 */
function normalizarString($str) {
    $str = mb_strtolower($str, 'UTF-8');
    $str = str_replace(
        ['á', 'é', 'í', 'ó', 'ú', 'Á', 'É', 'Í', 'Ó', 'Ú', 'ñ', 'Ñ'],
        ['a', 'e', 'i', 'o', 'u', 'A', 'E', 'I', 'O', 'U', 'n', 'N'],
        $str
    );
    $str = trim(preg_replace('/\s+/', ' ', $str));
    return $str;
}

/**
 * Redirijo al usuario a su panel correspondiente según el nivel de acceso asignado.
 */
function redirigirPorRol($role_id) {
    switch ($role_id) {
        case 1: // Administrador
            header("Location: ../admin/dashboard_admin.php");
            break;
        case 2: // Nutricionista
            header("Location: ../nutri/dashboard_nutri.php");
            break;
        case 3: // Usuario final
            header("Location: ../dashboard.php");
            break;
        default:
            header("Location: ../auth/login.php");
            break;
    }
    exit;
}


/**
 * CONFIGURACIÓN ESTÁTICA PARA ENTORNO LOCAL
 * Forzamos la ruta para evitar errores de carpetas duplicadas en las vistas.
 */

// Definimos la URL base apuntando a tu carpeta en htdocs
// Asegúrate de que esta ruta coincida exactamente con tu carpeta en XAMPP
define('BASE_URL', 'http://localhost/kaloai/');

// Definimos la ruta física del sistema para los include/require internos
define('ROOT_PATH', dirname(__DIR__) . '/');