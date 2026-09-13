<?php
/**
 * GoogleDriveService - Upload files to Google Drive using OAuth or a Service Account.
 * 
 * Uses raw cURL calls (no Composer/SDK) for shared hosting compatibility.
 * Features:
 * - Resilient Folder ID resolution (ID, full URL, or auto-discovery by folder name "AhmadLearningHub")
 * - Automatic subfolder creation (e.g., AhmadLearningHub -> Class 9 -> Physics)
 * - Self-healing diagnostics for debugging connection, permissions, and quota
 * - Public share link generation
 */

require_once __DIR__ . '/../config/env.php';

class GoogleDriveService
{
    private string $keyFilePath;
    private string $rawFolderConfig;
    private ?string $resolvedFolderId = null;
    private ?string $accessToken = null;
    private int $tokenExpiry = 0;
    private string $tokenCacheFile;
    private string $oauthTokenFile;
    private string $authMode;
    private string $clientId;
    private string $clientSecret;
    private string $redirectUri;

    public function __construct()
    {
        $this->keyFilePath = __DIR__ . '/../config/google_service_account.json';
        $this->rawFolderConfig = EnvLoader::get('GOOGLE_DRIVE_FOLDER_ID', '');
        $this->tokenCacheFile = __DIR__ . '/../storage/gdrive_token_cache.json';
        $this->oauthTokenFile = __DIR__ . '/../storage/gdrive_oauth_token.json';
        $this->authMode = strtolower((string) EnvLoader::get('GOOGLE_DRIVE_AUTH_MODE', 'auto'));
        $this->clientId = trim((string) EnvLoader::get('GOOGLE_DRIVE_CLIENT_ID', ''));
        $this->clientSecret = trim((string) EnvLoader::get('GOOGLE_DRIVE_CLIENT_SECRET', ''));
        $this->redirectUri = trim((string) EnvLoader::get('GOOGLE_DRIVE_REDIRECT_URI', 'https://ahmadlearninghub.com.pk/google_drive_callback.php'));

        $storageDir = dirname($this->tokenCacheFile);
        if (!is_dir($storageDir)) {
            @mkdir($storageDir, 0755, true);
        }

        if (!$this->hasOAuthClient() && !file_exists($this->keyFilePath)) {
            throw new Exception('Google Drive is not configured. Add OAuth client settings or restore the service account key file.');
        }
    }

    /**
     * Get the service account client email
     */
    public function getServiceAccountEmail(): string
    {
        if (!file_exists($this->keyFilePath)) {
            return 'Not configured';
        }
        $key = json_decode(file_get_contents($this->keyFilePath), true);
        return $key['client_email'] ?? 'Unknown';
    }

    public function getAuthorizationUrl(): string
    {
        if (!$this->hasOAuthClient()) {
            throw new Exception('GOOGLE_DRIVE_CLIENT_ID and GOOGLE_DRIVE_CLIENT_SECRET must be set before connecting OAuth.');
        }

        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $_SESSION['google_drive_oauth_state'] = bin2hex(random_bytes(24));

        return 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query([
            'client_id' => $this->clientId,
            'redirect_uri' => $this->redirectUri,
            'response_type' => 'code',
            'scope' => 'https://www.googleapis.com/auth/drive',
            'access_type' => 'offline',
            'prompt' => 'consent',
            'state' => $_SESSION['google_drive_oauth_state'],
        ]);
    }

    public function exchangeAuthorizationCode(string $code): array
    {
        if (!$this->hasOAuthClient()) {
            throw new Exception('OAuth client settings are missing.');
        }

        $tokenData = $this->postTokenRequest([
            'code' => $code,
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
            'redirect_uri' => $this->redirectUri,
            'grant_type' => 'authorization_code',
        ]);

        if (empty($tokenData['refresh_token'])) {
            $stored = $this->readOAuthTokenFile();
            if (!empty($stored['refresh_token'])) {
                $tokenData['refresh_token'] = $stored['refresh_token'];
            }
        }

        if (empty($tokenData['refresh_token'])) {
            throw new Exception('Google did not return a refresh token. Revoke the app access in your Google Account, then connect again with consent.');
        }

        $this->saveOAuthTokenData($tokenData);
        return $tokenData;
    }

    public function getOAuthStatus(): array
    {
        $stored = $this->readOAuthTokenFile();
        $envRefreshToken = trim((string) EnvLoader::get('GOOGLE_DRIVE_REFRESH_TOKEN', ''));
        $connected = !empty($stored['refresh_token']) || $envRefreshToken !== '';

        return [
            'client_configured' => $this->hasOAuthClient(),
            'connected' => $connected,
            'token_file' => $this->oauthTokenFile,
            'redirect_uri' => $this->redirectUri,
        ];
    }

    /**
     * Check if a folder ID exists and is accessible
     */
    public function verifyFolderExists(string $folderId, ?string $token = null): bool
    {
        $token = $token ?? $this->getAccessToken();
        $ch = curl_init("https://www.googleapis.com/drive/v3/files/{$folderId}?fields=id,name,mimeType,trashed&supportsAllDrives=true");
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => ["Authorization: Bearer {$token}"],
            CURLOPT_TIMEOUT => 10
        ]);
        $resp = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($code === 200) {
            $data = json_decode($resp, true);
            return empty($data['trashed']) && ($data['mimeType'] ?? '') === 'application/vnd.google-apps.folder';
        }
        return false;
    }

    /**
     * Resolve the Root Folder ID (supporting raw ID, Google Drive URL, or auto-discovery)
     */
    public function getRootFolderId(?string $token = null): string
    {
        if ($this->resolvedFolderId !== null) {
            return $this->resolvedFolderId;
        }

        $token = $token ?? $this->getAccessToken();
        $raw = trim($this->rawFolderConfig);
        $candidateId = null;

        // 1. If it's a full Google Drive URL, extract the ID
        if (preg_match('#folders/([a-zA-Z0-9_-]+)#', $raw, $matches)) {
            $candidateId = $matches[1];
        } elseif (!empty($raw) && !str_contains($raw, '@') && $raw !== 'AhmadLearningHub') {
            $candidateId = $raw;
        }

        // If candidate ID is given, verify it exists and is accessible
        if (!empty($candidateId) && $this->verifyFolderExists($candidateId, $token)) {
            $this->resolvedFolderId = $candidateId;
            return $this->resolvedFolderId;
        }

        // 2. Otherwise, search Google Drive for the shared folder named "AhmadLearningHub"
        $folderId = $this->findFolderByName('AhmadLearningHub', $token);
        if ($folderId) {
            $this->resolvedFolderId = $folderId;
            return $this->resolvedFolderId;
        }

        // 3. If candidate ID was provided but gave 404 and search also failed
        if (!empty($candidateId)) {
            throw new Exception("Configured Google Drive folder ID '{$candidateId}' was not found or is not accessible to the connected Google Drive account.");
        }

        // 4. If search failed and raw was an email, throw descriptive exception
        if (str_contains($raw, '@')) {
            throw new Exception("GOOGLE_DRIVE_FOLDER_ID in config/.env is currently set to an email address ('$raw'). It must be the Google Drive Folder ID from your folder URL.");
        }

        throw new Exception("Could not find folder 'AhmadLearningHub' in Google Drive. Create it in the connected Google account or set GOOGLE_DRIVE_FOLDER_ID in config/.env.");
    }

    /**
     * Search for a folder by name accessible to the service account
     */
    public function findFolderByName(string $name, ?string $token = null): ?string
    {
        $token = $token ?? $this->getAccessToken();
        $q = "mimeType = 'application/vnd.google-apps.folder' and name = '" . addslashes($name) . "' and trashed = false";
        $url = "https://www.googleapis.com/drive/v3/files?q=" . urlencode($q) . "&fields=files(id,name,capabilities)&supportsAllDrives=true&includeItemsFromAllDrives=true";

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => ["Authorization: Bearer {$token}"],
            CURLOPT_TIMEOUT => 15
        ]);
        $res = curl_exec($ch);
        curl_close($ch);

        $data = json_decode($res, true);
        if (!empty($data['files'][0]['id'])) {
            return $data['files'][0]['id'];
        }
        return null;
    }

    /**
     * Get or automatically create a subfolder inside a parent folder
     */
    public function getOrCreateSubfolder(string $parentId, string $folderName, ?string $token = null): string
    {
        $token = $token ?? $this->getAccessToken();
        $cleanName = trim($folderName);
        if ($cleanName === '') {
            return $parentId;
        }

        // Check if subfolder already exists in parent
        $q = "mimeType = 'application/vnd.google-apps.folder' and name = '" . addslashes($cleanName) . "' and '{$parentId}' in parents and trashed = false";
        $url = "https://www.googleapis.com/drive/v3/files?q=" . urlencode($q) . "&fields=files(id,name)&supportsAllDrives=true&includeItemsFromAllDrives=true";

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => ["Authorization: Bearer {$token}"],
            CURLOPT_TIMEOUT => 15
        ]);
        $res = curl_exec($ch);
        curl_close($ch);

        $data = json_decode($res, true);
        if (!empty($data['files'][0]['id'])) {
            return $data['files'][0]['id'];
        }

        // Subfolder doesn't exist yet -> Auto-create it!
        $metadata = [
            'name' => $cleanName,
            'mimeType' => 'application/vnd.google-apps.folder',
            'parents' => [$parentId]
        ];

        $ch = curl_init("https://www.googleapis.com/drive/v3/files?supportsAllDrives=true&fields=id,name");
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($metadata),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_HTTPHEADER => [
                "Authorization: Bearer {$token}",
                "Content-Type: application/json; charset=UTF-8"
            ]
        ]);
        $createResp = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $created = json_decode($createResp, true);
        if ($code === 200 && !empty($created['id'])) {
            return $created['id'];
        }

        throw new Exception("Failed to auto-create subfolder '{$cleanName}' in Google Drive (HTTP $code)");
    }

    /**
     * Upload a file with automatic subfolder organization (e.g. AhmadLearningHub/Class 9/Physics)
     * 
     * @param string $filePath Local file path
     * @param string $fileName Desired file name on Drive
     * @param string $mimeType MIME type
     * @param string|null $classLabel Optional class (e.g. "9", "Class 9")
     * @param string|null $subjectLabel Optional subject (e.g. "Physics")
     * @return array ['file_id' => string, 'url' => string, 'folder_id' => string]
     */
    public function uploadFile(string $filePath, string $fileName, string $mimeType, ?string $classLabel = null, ?string $subjectLabel = null): array
    {
        if (!file_exists($filePath)) {
            throw new Exception("File not found: $filePath");
        }

        $token = $this->getAccessToken();
        $targetFolderId = $this->getRootFolderId($token);

        // Auto-create/navigate into Class subfolder (e.g. "Class 9")
        if (!empty($classLabel)) {
            $folderName = str_starts_with(strtolower($classLabel), 'class') ? $classLabel : "Class $classLabel";
            $targetFolderId = $this->getOrCreateSubfolder($targetFolderId, $folderName, $token);

            // Auto-create/navigate into Subject subfolder (e.g. "Physics")
            if (!empty($subjectLabel) && $subjectLabel !== 'Other') {
                $targetFolderId = $this->getOrCreateSubfolder($targetFolderId, $subjectLabel, $token);
            }
        }

        $fileSize = filesize($filePath);
        $metadata = [
            'name' => $fileName,
            'parents' => [$targetFolderId]
        ];

        if ($fileSize <= 5 * 1024 * 1024) {
            $result = $this->simpleUpload($filePath, $fileName, $mimeType, $metadata, $token);
        } else {
            $result = $this->resumableUpload($filePath, $fileName, $mimeType, $metadata, $token);
        }

        $result['folder_id'] = $targetFolderId;
        return $result;
    }

    /**
     * Simple multipart upload for files <= 5MB
     */
    private function simpleUpload(string $filePath, string $fileName, string $mimeType, array $metadata, string $token): array
    {
        $boundary = 'gdrive_boundary_' . uniqid();
        $fileContent = file_get_contents($filePath);

        $body = "--{$boundary}\r\n";
        $body .= "Content-Type: application/json; charset=UTF-8\r\n\r\n";
        $body .= json_encode($metadata) . "\r\n";
        $body .= "--{$boundary}\r\n";
        $body .= "Content-Type: {$mimeType}\r\n\r\n";
        $body .= $fileContent . "\r\n";
        $body .= "--{$boundary}--";

        $ch = curl_init('https://www.googleapis.com/upload/drive/v3/files?uploadType=multipart&fields=id,webViewLink,webContentLink&supportsAllDrives=true');
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 15,
            CURLOPT_TIMEOUT => 120,
            CURLOPT_HTTPHEADER => [
                "Authorization: Bearer {$token}",
                "Content-Type: multipart/related; boundary={$boundary}",
                "Content-Length: " . strlen($body)
            ]
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        if ($response === false) {
            $err = curl_error($ch);
            curl_close($ch);
            throw new Exception("cURL error during Drive upload: $err");
        }
        curl_close($ch);

        if ($httpCode !== 200) {
            error_log("Google Drive upload failed (HTTP $httpCode): $response");
            $errData = json_decode($response, true);
            $msg = $errData['error']['message'] ?? "HTTP $httpCode";
            if ($httpCode === 404) {
                throw new Exception("Destination folder not found in Google Drive. Please verify the folder 'AhmadLearningHub' is shared with {$this->getServiceAccountEmail()} as Editor.");
            }
            throw new Exception("Google Drive upload failed ({$msg})");
        }

        $data = json_decode($response, true);
        $this->setFilePublic($data['id'], $token);

        return [
            'file_id' => $data['id'],
            'url' => $data['webViewLink'] ?? "https://drive.google.com/file/d/{$data['id']}/view"
        ];
    }

    /**
     * Resumable upload for files > 5MB
     */
    private function resumableUpload(string $filePath, string $fileName, string $mimeType, array $metadata, string $token): array
    {
        // Step 1: Initiate resumable session
        $ch = curl_init('https://www.googleapis.com/upload/drive/v3/files?uploadType=resumable&fields=id,webViewLink,webContentLink&supportsAllDrives=true');
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($metadata),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => true,
            CURLOPT_CONNECTTIMEOUT => 15,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTPHEADER => [
                "Authorization: Bearer {$token}",
                "Content-Type: application/json; charset=UTF-8",
                "X-Upload-Content-Type: {$mimeType}",
                "X-Upload-Content-Length: " . filesize($filePath)
            ]
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            $errData = json_decode($response, true);
            $msg = $errData['error']['message'] ?? "HTTP $httpCode";
            throw new Exception("Failed to initiate resumable upload ({$msg})");
        }

        preg_match('/Location:\s*(.+)/i', $response, $matches);
        $uploadUri = trim($matches[1] ?? '');
        if (empty($uploadUri)) {
            throw new Exception("Could not get resumable upload URI from Google Drive");
        }

        // Step 2: Upload file body
        $fileContent = file_get_contents($filePath);
        $ch = curl_init($uploadUri);
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST => 'PUT',
            CURLOPT_POSTFIELDS => $fileContent,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 15,
            CURLOPT_TIMEOUT => 300,
            CURLOPT_HTTPHEADER => [
                "Content-Type: {$mimeType}",
                "Content-Length: " . strlen($fileContent)
            ]
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200 && $httpCode !== 201) {
            throw new Exception("Failed to complete file upload (HTTP $httpCode)");
        }

        $data = json_decode($response, true);
        $this->setFilePublic($data['id'], $token);

        return [
            'file_id' => $data['id'],
            'url' => $data['webViewLink'] ?? "https://drive.google.com/file/d/{$data['id']}/view"
        ];
    }

    /**
     * Set file permission to anyone with link can view
     */
    private function setFilePublic(string $fileId, string $token): void
    {
        $permission = [
            'role' => 'reader',
            'type' => 'anyone'
        ];

        $ch = curl_init("https://www.googleapis.com/drive/v3/files/{$fileId}/permissions?supportsAllDrives=true");
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($permission),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_HTTPHEADER => [
                "Authorization: Bearer {$token}",
                "Content-Type: application/json"
            ]
        ]);

        curl_exec($ch);
        curl_close($ch);
    }

    /**
     * Delete a file from Google Drive
     */
    public function deleteFile(string $fileId): bool
    {
        if (empty($fileId)) return false;
        try {
            $token = $this->getAccessToken();
            $ch = curl_init("https://www.googleapis.com/drive/v3/files/{$fileId}?supportsAllDrives=true");
            curl_setopt_array($ch, [
                CURLOPT_CUSTOMREQUEST => 'DELETE',
                CURLOPT_HTTPHEADER => ["Authorization: Bearer {$token}"],
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 15,
            ]);
            curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            return ($httpCode === 204 || $httpCode === 200);
        } catch (Exception $e) {
            error_log("Google Drive delete error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Run complete diagnostics on Google Drive connection & folder structure
     */
    public function diagnoseConnection(): array
    {
        $serviceEmail = 'Not used';
        if (file_exists($this->keyFilePath)) {
            $key = json_decode(file_get_contents($this->keyFilePath), true);
            $serviceEmail = $key['client_email'] ?? 'Unknown';
        }
        $usingOAuth = $this->shouldUseOAuth();

        $diag = [
            'success' => false,
            'status' => 'error',
            'auth_mode' => $usingOAuth ? 'oauth' : 'service_account',
            'oauth_client_configured' => $this->hasOAuthClient(),
            'oauth_connected' => $this->hasOAuthCredentials(),
            'oauth_connect_url' => $this->hasOAuthClient() ? '../../google_drive_callback.php' : '',
            'oauth_redirect_uri' => $this->redirectUri,
            'service_email' => $serviceEmail,
            'key_file_found' => false,
            'token_success' => false,
            'configured_folder' => $this->rawFolderConfig,
            'root_folder_name' => '',
            'root_folder_id' => '',
            'root_folder_url' => '',
            'can_upload' => false,
            'can_add_children' => false,
            'subfolders' => [],
            'auto_organization' => 'Active (AhmadLearningHub -> Class {X} -> {Subject})',
            'errors' => [],
            'fix_instructions' => [
                'step_1' => "Open the admin Google Drive OAuth page: /google_drive_callback.php",
                'step_2' => "Connect the Google account that should own uploaded notes.",
                'step_3' => "Create or locate a folder named 'AhmadLearningHub'.",
                'step_4' => "Copy its folder ID into config/.env as GOOGLE_DRIVE_FOLDER_ID, or leave it empty if the folder is named exactly AhmadLearningHub.",
                'step_5' => "Click 'Test Connection' again to verify."
            ]
        ];

        if (!$usingOAuth && !file_exists($this->keyFilePath)) {
            $diag['errors'][] = "Service account JSON key file not found at: {$this->keyFilePath}";
            return $diag;
        }
        $diag['key_file_found'] = file_exists($this->keyFilePath);

        if ($usingOAuth && !$this->hasOAuthCredentials()) {
            $diag['errors'][] = $this->hasOAuthClient()
                ? "Google Drive OAuth is not connected yet. Open /google_drive_callback.php as an admin and connect Google Drive."
                : "Google Drive OAuth client settings are missing. Set GOOGLE_DRIVE_CLIENT_ID and GOOGLE_DRIVE_CLIENT_SECRET in config/.env.";
            return $diag;
        }

        try {
            $token = $this->getAccessToken();
            $diag['token_success'] = true;
        } catch (Exception $e) {
            $diag['errors'][] = "Failed to obtain Google access token: " . $e->getMessage();
            return $diag;
        }

        try {
            $folderId = $this->getRootFolderId($token);
            $diag['root_folder_id'] = $folderId;
            $diag['root_folder_url'] = "https://drive.google.com/drive/folders/{$folderId}";

            // Fetch folder details and capabilities
            $ch = curl_init("https://www.googleapis.com/drive/v3/files/{$folderId}?fields=id,name,capabilities&supportsAllDrives=true");
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER => ["Authorization: Bearer {$token}"],
                CURLOPT_TIMEOUT => 15
            ]);
            $resp = curl_exec($ch);
            $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($code === 200) {
                $fData = json_decode($resp, true);
                $diag['root_folder_name'] = $fData['name'] ?? 'AhmadLearningHub';
                $diag['can_add_children'] = $fData['capabilities']['canAddChildren'] ?? false;
                $diag['can_upload'] = $diag['can_add_children'];

                if (!$diag['can_add_children']) {
                    $diag['errors'][] = "Folder '{$diag['root_folder_name']}' was found, but the connected Google Drive credential cannot add files.";
                }

                // Check existing subfolders
                $q = "'{$folderId}' in parents and mimeType = 'application/vnd.google-apps.folder' and trashed = false";
                $ch2 = curl_init("https://www.googleapis.com/drive/v3/files?q=" . urlencode($q) . "&fields=files(id,name)&supportsAllDrives=true");
                curl_setopt_array($ch2, [
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_HTTPHEADER => ["Authorization: Bearer {$token}"]
                ]);
                $sResp = curl_exec($ch2);
                curl_close($ch2);
                $sData = json_decode($sResp, true);
                if (!empty($sData['files'])) {
                    $diag['subfolders'] = array_column($sData['files'], 'name');
                }

                if ($diag['can_add_children']) {
                    $diag['status'] = 'ok';
                    $diag['success'] = true;
                }
            } else {
                $err = json_decode($resp, true);
                $diag['errors'][] = "Google Drive folder returned HTTP $code: " . ($err['error']['message'] ?? 'Folder not accessible');
            }
        } catch (Exception $e) {
            $diag['errors'][] = $e->getMessage();
        }

        return $diag;
    }

    /**
     * Get OAuth2 access token using Service Account JWT
     */
    public function getAccessToken(): string
    {
        if ($this->shouldUseOAuth()) {
            return $this->getOAuthAccessToken();
        }

        if ($this->accessToken && time() < $this->tokenExpiry) {
            return $this->accessToken;
        }

        if (!file_exists($this->keyFilePath)) {
            throw new Exception('Google Service Account key file not found at: ' . $this->keyFilePath);
        }

        if (file_exists($this->tokenCacheFile)) {
            $cached = json_decode(file_get_contents($this->tokenCacheFile), true);
            if ($cached && isset($cached['access_token']) && time() < ($cached['expiry'] ?? 0)) {
                $this->accessToken = $cached['access_token'];
                $this->tokenExpiry = $cached['expiry'];
                return $this->accessToken;
            }
        }

        $key = json_decode(file_get_contents($this->keyFilePath), true);
        if (!$key) throw new Exception('Invalid service account JSON key file');

        $now = time();
        $header = ['alg' => 'RS256', 'typ' => 'JWT'];
        $claimSet = [
            'iss' => $key['client_email'],
            'scope' => 'https://www.googleapis.com/auth/drive',
            'aud' => 'https://oauth2.googleapis.com/token',
            'iat' => $now,
            'exp' => $now + 3600
        ];

        $headerEncoded = $this->base64UrlEncode(json_encode($header));
        $claimSetEncoded = $this->base64UrlEncode(json_encode($claimSet));
        $signatureInput = "{$headerEncoded}.{$claimSetEncoded}";

        $privateKey = openssl_pkey_get_private($key['private_key']);
        if (!$privateKey) throw new Exception('Failed to load private key from service account');

        openssl_sign($signatureInput, $signature, $privateKey, 'SHA256');
        $signatureEncoded = $this->base64UrlEncode($signature);
        $jwt = "{$signatureInput}.{$signatureEncoded}";

        $ch = curl_init('https://oauth2.googleapis.com/token');
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query([
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => $jwt
            ]),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_HTTPHEADER => ['Content-Type' => 'application/x-www-form-urlencoded']
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            throw new Exception("Google token exchange failed (HTTP $httpCode)");
        }

        $tokenData = json_decode($response, true);
        $this->accessToken = $tokenData['access_token'];
        $this->tokenExpiry = $now + ($tokenData['expires_in'] ?? 3600) - 120;

        file_put_contents($this->tokenCacheFile, json_encode([
            'access_token' => $this->accessToken,
            'expiry' => $this->tokenExpiry
        ]));

        return $this->accessToken;
    }

    private function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private function shouldUseOAuth(): bool
    {
        if ($this->authMode === 'oauth') {
            return true;
        }

        return $this->authMode === 'auto' && $this->hasOAuthCredentials();
    }

    private function hasOAuthClient(): bool
    {
        return $this->clientId !== '' && $this->clientSecret !== '';
    }

    private function hasOAuthCredentials(): bool
    {
        if (!$this->hasOAuthClient()) {
            return false;
        }

        return $this->getOAuthRefreshToken() !== '';
    }

    private function getOAuthAccessToken(): string
    {
        if ($this->accessToken && time() < $this->tokenExpiry) {
            return $this->accessToken;
        }

        $stored = $this->readOAuthTokenFile();
        if (!empty($stored['access_token']) && time() < (int)($stored['expiry'] ?? 0)) {
            $this->accessToken = $stored['access_token'];
            $this->tokenExpiry = (int)$stored['expiry'];
            return $this->accessToken;
        }

        $refreshToken = $this->getOAuthRefreshToken();
        if ($refreshToken === '') {
            throw new Exception('Google Drive OAuth is not connected. Visit /google_drive_callback.php as an admin and connect Google Drive.');
        }

        $tokenData = $this->postTokenRequest([
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
            'refresh_token' => $refreshToken,
            'grant_type' => 'refresh_token',
        ]);

        if (empty($tokenData['access_token'])) {
            throw new Exception('Google OAuth refresh did not return an access token.');
        }

        $tokenData['refresh_token'] = $refreshToken;
        $this->saveOAuthTokenData($tokenData);

        return $this->accessToken;
    }

    private function postTokenRequest(array $fields): array
    {
        $ch = curl_init('https://oauth2.googleapis.com/token');
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query($fields),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded']
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($response === false || $httpCode !== 200) {
            $errData = json_decode((string)$response, true);
            $message = $errData['error_description'] ?? $errData['error'] ?? $curlError ?: "HTTP $httpCode";
            throw new Exception("Google OAuth token request failed: {$message}");
        }

        $data = json_decode($response, true);
        if (!is_array($data)) {
            throw new Exception('Google OAuth token response was not valid JSON.');
        }

        return $data;
    }

    private function getOAuthRefreshToken(): string
    {
        $envToken = trim((string) EnvLoader::get('GOOGLE_DRIVE_REFRESH_TOKEN', ''));
        if ($envToken !== '') {
            return $envToken;
        }

        $stored = $this->readOAuthTokenFile();
        return trim((string)($stored['refresh_token'] ?? ''));
    }

    private function readOAuthTokenFile(): array
    {
        if (!file_exists($this->oauthTokenFile)) {
            return [];
        }

        $data = json_decode((string) file_get_contents($this->oauthTokenFile), true);
        return is_array($data) ? $data : [];
    }

    private function saveOAuthTokenData(array $tokenData): void
    {
        $now = time();
        $this->accessToken = $tokenData['access_token'] ?? $this->accessToken;
        $this->tokenExpiry = $now + (int)($tokenData['expires_in'] ?? 3600) - 120;

        $payload = $this->readOAuthTokenFile();
        $payload['access_token'] = $this->accessToken;
        $payload['refresh_token'] = $tokenData['refresh_token'] ?? ($payload['refresh_token'] ?? '');
        $payload['expiry'] = $this->tokenExpiry;
        $payload['token_type'] = $tokenData['token_type'] ?? ($payload['token_type'] ?? 'Bearer');
        $payload['updated_at'] = date('c');

        file_put_contents($this->oauthTokenFile, json_encode($payload, JSON_PRETTY_PRINT));
    }
}
