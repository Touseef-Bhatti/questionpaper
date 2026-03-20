<?php
/**
 * admin/topics/TopicAIService.php - Dedicated AI Service for Topic Management (NVIDIA Qwen)
 * Uses NVIDIA's OpenAI-compatible API for keyword generation.
 */

class TopicAIService {
    
    /**
     * Call NVIDIA AI API (OpenAI compatible)
     * @param string $apiKey The NVIDIA API Key
     * @param string $prompt The prompt to send
     * @param string $model The model to use
     * @return array [response_text, http_code]
     */
    public static function callAI($apiKey, $prompt, $model = "qwen/qwen3-next-80b-a3b-instruct") {
        $url = "https://integrate.api.nvidia.com/v1/chat/completions";
        
        $payload = [
            'model' => $model,
            'messages' => [
                ['role' => 'user', 'content' => $prompt]
            ],
            'temperature' => 0.6,
            'top_p' => 0.7,
            'max_tokens' => 1024,
            'stream' => false // Stream is false for simpler PHP handling
        ];

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $apiKey
            ],
            CURLOPT_TIMEOUT => 60,
        ]);

        $resp = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($code === 200 && $resp) {
            $dec = json_decode($resp, true);
            if (isset($dec['choices'][0]['message']['content'])) {
                return [$dec['choices'][0]['message']['content'], $code];
            }
        }
        
        return [$resp ?: $error, $code];
    }
}
