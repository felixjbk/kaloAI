<?php
require_once __DIR__ . '/configuracion.php';

/**
 * Función para realizar consultas a la API de Gemini.
 * Implementa un sistema de rotación de claves para asegurar disponibilidad.
 */
function preguntarGemini($prompt) {
    $keys = GEMINI_API_KEYS;
    $ultimoError = "No hay API Keys configuradas";

    // Recorro las API Keys disponibles para gestionar posibles límites o fallos
    foreach ($keys as $index => $key) {
        $url = GEMINI_API_URL . "?key=" . $key;

        // Configuro la petición manteniendo el formato de JSON estricto y la temperatura
        $data = [
            "contents" => [
                ["parts" => [["text" => $prompt]]]
            ],
            "generationConfig" => [
                "temperature" => 0.7,
                "response_mime_type" => "application/json"
            ]
        ];

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        
        // Ajustes de seguridad y tiempos de espera para evitar bloqueos del script
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); 
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10); 
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);

        $respuesta = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        // Si la respuesta es correcta, extraigo el contenido textual
        if ($httpCode === 200) {
            $jsonResponse = json_decode($respuesta, true);
            
            // Obtengo el texto limpio generado por la IA para su posterior procesamiento
            $textoIA = $jsonResponse['candidates'][0]['content']['parts'][0]['text'] ?? null;
            
            if ($textoIA) {
                return $textoIA; 
            }
        } 
        
        // En caso de fallo, registro el error y continúo intentando con la siguiente API Key
        $errorData = json_decode($respuesta, true);
        $ultimoError = $errorData['error']['message'] ?? "Error HTTP $httpCode";
        error_log("KaloAI: Falló API Key " . ($index + 1) . ". Error: " . $ultimoError);
    }

    // Si se agotan todas las llaves sin éxito, retorno un error estructurado
    return json_encode([
        'error' => "Todas las API Keys fallaron. Último error: " . $ultimoError
    ]);
}