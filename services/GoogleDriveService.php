<?php
/**
 * GoogleDriveService - Upload files to Google Drive using OAuth or a Service Account.
 * 
 * Uses raw cURL calls (no Composer/SDK) for shared hosting compatibility.
 * Features:
 * - Resilient Folder ID resolution (ID, full URL, or auto-discovery by folder name "AhmadLearningHub")
 * - Notes stored below Notes/Class X/Book/Chapter/{Admin|User}
 * - Textbooks stored below Books/Class X/BookName at the configured root
 * - User question-paper and MCQ source files use separate configured roots
 * - Self-healing diagnostics for debugging connection, permissions, and quota
 * - Public share link generation
 */

require_once __DIR__ . '/../config/env.php';

class GoogleDriveService
{
    private string $keyFilePath;
    private string $rawFolderConfig;
    /** @var array<string,string> */
    private array $layoutFolderConfig = [];
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
        $this->layoutFolderConfig = [
            'books' => trim((string) EnvLoader::get('GOOGLE_DRIVE_BOOKS_FOLDER_ID', '')),
            'notes' => trim((string) EnvLoader::get('GOOGLE_DRIVE_NOTES_FOLDER_ID', '')),
            'question_paper' => trim((string) EnvLoader::get('GOOGLE_DRIVE_QUESTION_PAPER_FOLDER_ID', '')),
            'mcqs' => trim((string) EnvLoader::get('GOOGLE_DRIVE_MCQS_FOLDER_ID', '')),
            'deleted_files' => trim((string) EnvLoader::get('GOOGLE_DRIVE_DELETED_FILES_FOLDER_ID', '')),
            'class_9' => trim((string) EnvLoader::get('GOOGLE_DRIVE_CLASS_9_FOLDER_ID', '')),
            'class_10' => trim((string) EnvLoader::get('GOOGLE_DRIVE_CLASS_10_FOLDER_ID', '')),
            'class_11' => trim((string) EnvLoader::get('GOOGLE_DRIVE_CLASS_11_FOLDER_ID', '')),
            'class_12' => trim((string) EnvLoader::get('GOOGLE_DRIVE_CLASS_12_FOLDER_ID', '')),
        ];
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
     * Return folder metadata, including parents, without exposing credentials.
     *
     * @return array<string,mixed>|null
     */
    private function getFolderMetadata(string $folderId, string $token): ?array
    {
        if (!preg_match('/^[A-Za-z0-9_-]+$/', $folderId)) {
            return null;
        }
        $url = 'https://www.googleapis.com/drive/v3/files/' . rawurlencode($folderId)
            . '?fields=id,name,mimeType,parents,trashed&supportsAllDrives=true';
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => ["Authorization: Bearer {$token}"],
            CURLOPT_TIMEOUT => 15,
        ]);
        $response = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($response === false || $code !== 200) {
            return null;
        }
        $data = json_decode($response, true);
        return is_array($data) ? $data : null;
    }

    private function extractFolderId(string $value): string
    {
        $value = trim($value);
        if (preg_match('#folders/([A-Za-z0-9_-]+)#', $value, $matches)) {
            return $matches[1];
        }
        return preg_match('/^[A-Za-z0-9_-]+$/', $value) ? $value : '';
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
        $url = "https://www.googleapis.com/drive/v3/files?q=" . urlencode($q) . "&fields=files(id,name)&orderBy=name&supportsAllDrives=true&includeItemsFromAllDrives=true";

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
        if (($code === 200 || $code === 201) && !empty($created['id'])) {
            return $created['id'];
        }

        // A concurrent request may have created the same child between the
        // lookup and create calls. Re-read the parent before failing instead
        // of creating another branch or reporting a false failure.
        $existingUrl = "https://www.googleapis.com/drive/v3/files?q=" . urlencode($q) . "&fields=files(id,name)&orderBy=name&supportsAllDrives=true&includeItemsFromAllDrives=true";
        $retry = curl_init($existingUrl);
        curl_setopt_array($retry, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => ["Authorization: Bearer {$token}"],
            CURLOPT_TIMEOUT => 15,
        ]);
        $retryResponse = curl_exec($retry);
        curl_close($retry);
        $retryData = json_decode((string) $retryResponse, true);
        if (!empty($retryData['files'][0]['id'])) {
            return (string) $retryData['files'][0]['id'];
        }

        throw new Exception("Failed to auto-create subfolder '{$cleanName}' in Google Drive (HTTP $code)");
    }

    /**
     * Resolve a configured folder only when it is the expected child of the
     * configured parent. Invalid/stale IDs fall back to a parent-scoped lookup
     * and automatic creation, preventing folders in unrelated Drive locations.
     */
    private function resolveLayoutFolder(string $key, string $folderName, string $parentId, string $token): string
    {
        $configuredId = $this->extractFolderId($this->layoutFolderConfig[$key] ?? '');
        if ($configuredId !== '') {
            $metadata = $this->getFolderMetadata($configuredId, $token);
            $parents = is_array($metadata['parents'] ?? null) ? $metadata['parents'] : [];
            if ($metadata !== null
                && empty($metadata['trashed'])
                && ($metadata['mimeType'] ?? '') === 'application/vnd.google-apps.folder'
                && in_array($parentId, $parents, true)) {
                return $configuredId;
            }
        }

        return $this->getOrCreateSubfolder($parentId, $folderName, $token);
    }

    public function getBooksRootFolderId(?string $token = null): string
    {
        $token = $token ?? $this->getAccessToken();
        return $this->resolveLayoutFolder('books', 'Books', $this->getRootFolderId($token), $token);
    }

    public function getNotesRootFolderId(?string $token = null): string
    {
        $token = $token ?? $this->getAccessToken();
        return $this->resolveLayoutFolder('notes', 'Notes', $this->getRootFolderId($token), $token);
    }

    public function getQuestionPaperRootFolderId(?string $token = null): string
    {
        $token = $token ?? $this->getAccessToken();
        return $this->resolveLayoutFolder('question_paper', 'QuestionPaperUpload', $this->getRootFolderId($token), $token);
    }

    public function getMcqsRootFolderId(?string $token = null): string
    {
        $token = $token ?? $this->getAccessToken();
        return $this->resolveLayoutFolder('mcqs', 'McqsUploads', $this->getRootFolderId($token), $token);
    }

    public function getDeletedFilesFolderId(?string $token = null): string
    {
        $token = $token ?? $this->getAccessToken();
        return $this->resolveLayoutFolder('deleted_files', 'deleteFiles', $this->getRootFolderId($token), $token);
    }

    public function getClassFolderId(string $classLabel, ?string $token = null): string
    {
        $token = $token ?? $this->getAccessToken();
        return $this->getClassFolderIdUnderRoot($classLabel, $this->getBooksRootFolderId($token), $token);
    }

    private function getClassFolderIdUnderRoot(string $classLabel, string $parentId, string $token): string
    {
        $classNumber = preg_replace('/[^0-9]/', '', trim($classLabel));
        $classNumber = in_array($classNumber, ['9', '10', '11', '12'], true) ? $classNumber : 'unknown';
        $key = 'class_' . $classNumber;
        return $this->resolveLayoutFolder($key, $this->classFolderName($classNumber), $parentId, $token);
    }

    /**
     * Create/resolve the stable top-level layout. Book, chapter, and owner
     * folders remain dynamic and are created only when the corresponding file
     * is uploaded.
     *
     * @return array<string,string|array<string,string>>
     */
    public function ensureStandardFolderStructure(): array
    {
        $token = $this->getAccessToken();
        $rootId = $this->getRootFolderId($token);
        $classFolders = [];
        foreach (['9', '10', '11', '12'] as $classNumber) {
            $classFolders[$classNumber] = $this->getClassFolderId($classNumber, $token);
        }

        return [
            'root' => $rootId,
            'books' => $this->getBooksRootFolderId($token),
            'notes' => $this->getNotesRootFolderId($token),
            'classes' => $classFolders,
            'question_paper' => $this->getQuestionPaperRootFolderId($token),
            'mcqs' => $this->getMcqsRootFolderId($token),
            'delete_files' => $this->getDeletedFilesFolderId($token),
        ];
    }

    /**
     * List files below the configured AhmadLearningHub root with their relative
     * Drive folder path. This is intentionally read-only; sync decides which
     * paths are managed and how they map into application tables.
     *
     * @return array<int,array<string,mixed>>
     */
    public function listManagedFiles(): array
    {
        $token = $this->getAccessToken();
        $rootId = $this->getRootFolderId($token);
        $files = [];
        $this->walkDriveFolder($rootId, [], $token, $files);
        return $files;
    }

    /**
     * Download a known Drive file to the operating-system temp directory.
     * Persistent application storage is deliberately rejected here.
     */
    public function downloadFileToTemp(string $fileId, string $destination): int
    {
        if (!preg_match('/^[A-Za-z0-9_-]+$/', $fileId)) {
            throw new InvalidArgumentException('Invalid Google Drive file ID.');
        }

        $tempRoot = realpath(sys_get_temp_dir());
        $destinationDirectory = dirname($destination);
        if (!is_dir($destinationDirectory) && !@mkdir($destinationDirectory, 0700, true)) {
            throw new Exception('Could not create the temporary Drive download directory.');
        }
        $resolvedDirectory = realpath($destinationDirectory);
        if ($tempRoot === false || $resolvedDirectory === false || !$this->isWithinDirectory($resolvedDirectory, $tempRoot)) {
            throw new InvalidArgumentException('Drive downloads must remain outside application storage.');
        }

        $token = $this->getAccessToken();
        $handle = @fopen($destination, 'wb');
        if ($handle === false) {
            throw new Exception('Could not open the temporary Drive download file.');
        }

        $bytesWritten = 0;
        $ch = curl_init('https://www.googleapis.com/drive/v3/files/' . rawurlencode($fileId) . '?alt=media&supportsAllDrives=true');
        curl_setopt_array($ch, [
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_RETURNTRANSFER => false,
            CURLOPT_CONNECTTIMEOUT => 15,
            CURLOPT_TIMEOUT => 300,
            CURLOPT_HTTPHEADER => ["Authorization: Bearer {$token}"],
            CURLOPT_WRITEFUNCTION => static function ($curlHandle, string $chunk) use ($handle, &$bytesWritten): int {
                $chunkLength = strlen($chunk);
                $offset = 0;
                while ($offset < $chunkLength) {
                    $written = fwrite($handle, substr($chunk, $offset));
                    if ($written === false || $written === 0) {
                        return 0;
                    }
                    $offset += $written;
                }
                $bytesWritten += $chunkLength;
                return $chunkLength;
            },
        ]);
        $ok = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);
        fflush($handle);
        fclose($handle);

        if ($ok === false || $httpCode !== 200) {
            @unlink($destination);
            throw new Exception('Google Drive download failed' . ($curlError !== '' ? ': ' . $curlError : " (HTTP {$httpCode})"));
        }

        if ($bytesWritten <= 0) {
            @unlink($destination);
            throw new Exception('Google Drive returned an empty file.');
        }

        return $bytesWritten;
    }

    public function isFileAvailable(string $fileId): bool
    {
        if (!preg_match('/^[A-Za-z0-9_-]+$/', $fileId)) {
            throw new InvalidArgumentException('Invalid Google Drive file ID.');
        }

        $token = $this->getAccessToken();
        $ch = curl_init('https://www.googleapis.com/drive/v3/files/' . rawurlencode($fileId) . '?fields=id,trashed&supportsAllDrives=true');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 15,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTPHEADER => ["Authorization: Bearer {$token}"],
        ]);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            throw new Exception('Google Drive file check failed: ' . $curlError);
        }
        if ($httpCode === 404) {
            return false;
        }
        if ($httpCode !== 200) {
            throw new Exception("Google Drive file check failed (HTTP {$httpCode})");
        }

        $data = json_decode($response, true);
        return is_array($data) && empty($data['trashed']);
    }

    /**
     * @param string[] $pathSegments
     * @param array<int,array<string,mixed>> $files
     */
    private function walkDriveFolder(string $folderId, array $pathSegments, string $token, array &$files, int $depth = 0): void
    {
        if ($depth > 6) {
            return;
        }

        foreach ($this->listDriveChildren($folderId, $token) as $item) {
            $name = trim((string) ($item['name'] ?? ''));
            if ($name === '') {
                continue;
            }

            if (($item['mimeType'] ?? '') === 'application/vnd.google-apps.folder') {
                $this->walkDriveFolder((string) $item['id'], array_merge($pathSegments, [$name]), $token, $files, $depth + 1);
                continue;
            }

            $files[] = [
                'file_id' => (string) ($item['id'] ?? ''),
                'name' => $name,
                'mime_type' => (string) ($item['mimeType'] ?? 'application/octet-stream'),
                'size' => (int) ($item['size'] ?? 0),
                'modified_time' => (string) ($item['modifiedTime'] ?? ''),
                'web_url' => (string) ($item['webViewLink'] ?? ''),
                'folder_id' => $folderId,
                'path_segments' => $pathSegments,
                'relative_path' => implode('/', array_merge($pathSegments, [$name])),
            ];
        }
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private function listDriveChildren(string $folderId, string $token): array
    {
        $files = [];
        $pageToken = '';

        do {
            $query = "'{$folderId}' in parents and trashed = false";
            $params = [
                'q' => $query,
                'pageSize' => 1000,
                'fields' => 'nextPageToken,files(id,name,mimeType,size,modifiedTime,webViewLink)',
                'supportsAllDrives' => 'true',
                'includeItemsFromAllDrives' => 'true',
                'orderBy' => 'folder,name',
            ];
            if ($pageToken !== '') {
                $params['pageToken'] = $pageToken;
            }

            $url = 'https://www.googleapis.com/drive/v3/files?' . http_build_query($params);
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CONNECTTIMEOUT => 15,
                CURLOPT_TIMEOUT => 45,
                CURLOPT_HTTPHEADER => ["Authorization: Bearer {$token}"],
            ]);
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);

            if ($response === false || $httpCode !== 200) {
                throw new Exception('Google Drive listing failed' . ($curlError !== '' ? ': ' . $curlError : " (HTTP {$httpCode})"));
            }

            $data = json_decode($response, true);
            if (!is_array($data)) {
                throw new Exception('Google Drive returned an invalid file listing.');
            }
            if (!empty($data['files']) && is_array($data['files'])) {
                $files = array_merge($files, $data['files']);
            }
            $pageToken = (string) ($data['nextPageToken'] ?? '');
        } while ($pageToken !== '');

        return $files;
    }

    private function isWithinDirectory(string $path, string $directory): bool
    {
        $path = rtrim(strtolower(str_replace('\\', '/', $path)), '/') . '/';
        $directory = rtrim(strtolower(str_replace('\\', '/', $directory)), '/') . '/';
        return str_starts_with($path, $directory);
    }

    /**
     * Upload a class note with automatic subfolder organization.
     * Files are stored under Notes/Class {9|10|11|12}/{Book}/{Chapter}/{Admin|User}.
     * 
     * @param string $filePath Local file path
     * @param string $fileName Desired file name on Drive
     * @param string $mimeType MIME type
     * @param string|null $classLabel Optional class (e.g. "9", "Class 9")
     * @param string|null $subjectLabel Optional subject (e.g. "Physics")
     * @param string $uploaderType Whether the uploader is an admin or a user
     * @param string|null $chapterLabel Optional chapter; empty values use General
     * @return array ['file_id' => string, 'url' => string, 'folder_id' => string]
     */
    public function uploadFile(
        string $filePath,
        string $fileName,
        string $mimeType,
        ?string $classLabel = null,
        ?string $subjectLabel = null,
        string $uploaderType = 'user',
        ?string $chapterLabel = null
    ): array
    {
        if (!file_exists($filePath)) {
            throw new Exception("File not found: $filePath");
        }

        $token = $this->getAccessToken();
        $targetFolderId = $this->getNotesRootFolderId($token);

        // Auto-create/navigate into Class subfolder (e.g. "Class 9").
        if (!empty($classLabel)) {
            $targetFolderId = $this->getClassFolderIdUnderRoot(
                $classLabel,
                $this->getNotesRootFolderId($token),
                $token
            );

            // The note subject is the dynamic book folder in the shared
            // Books hierarchy. Keep "Other" as a valid fallback folder.
            $bookFolder = $this->cleanFolderName((string) ($subjectLabel ?? ''), 'Book');
            $targetFolderId = $this->getOrCreateSubfolder($targetFolderId, $bookFolder, $token);

            $chapterFolder = $this->cleanFolderName((string) ($chapterLabel ?? ''), 'General');
            $targetFolderId = $this->getOrCreateSubfolder($targetFolderId, $chapterFolder, $token);

            // Separate administrator material from community submissions. The
            // singular User name is part of the stable Drive contract.
            $ownerFolder = strtolower(trim($uploaderType)) === 'admin' ? 'Admin' : 'User';
            $targetFolderId = $this->getOrCreateSubfolder($targetFolderId, $ownerFolder, $token);
        }

        $fileSize = filesize($filePath);
        return $this->uploadToFolder($filePath, $fileName, $mimeType, $targetFolderId, $token, $fileSize);
    }

    /**
     * Upload a textbook directly into AhmadLearningHub/Books/Class X/BookName.
     * Textbooks intentionally do not use the chapter or Admin/User branches.
     *
     * @return array ['file_id' => string, 'url' => string, 'folder_id' => string]
     */
    public function uploadBookFile(
        string $filePath,
        string $fileName,
        string $mimeType,
        string $classLabel,
        string $bookLabel
    ): array
    {
        if (!file_exists($filePath)) {
            throw new Exception("File not found: $filePath");
        }

        $token = $this->getAccessToken();
        $classFolder = $this->getClassFolderId($classLabel, $token);
        $bookFolder = $this->getOrCreateSubfolder($classFolder, $this->cleanFolderName($bookLabel, 'Book'), $token);

        return $this->uploadToFolder(
            $filePath,
            $fileName,
            $mimeType,
            $bookFolder,
            $token,
            (int) filesize($filePath)
        );
    }

    /**
     * Store user-provided source material outside the Books hierarchy.
     * These roots are intentionally flat: application metadata keeps the
     * original filename and generated-question relationships.
     *
     * @return array{file_id:string,url:string,folder_id:string}
     */
    public function uploadUserQuestionPaperFile(string $filePath, string $fileName, string $mimeType): array
    {
        return $this->uploadUserSourceFile($filePath, $fileName, $mimeType, 'question_paper');
    }

    /**
     * @return array{file_id:string,url:string,folder_id:string}
     */
    public function uploadUserMcqFile(string $filePath, string $fileName, string $mimeType): array
    {
        return $this->uploadUserSourceFile($filePath, $fileName, $mimeType, 'mcqs');
    }

    /**
     * @return array{file_id:string,url:string,folder_id:string}
     */
    private function uploadUserSourceFile(string $filePath, string $fileName, string $mimeType, string $category): array
    {
        if (!file_exists($filePath)) {
            throw new Exception("File not found: $filePath");
        }

        $token = $this->getAccessToken();
        $targetFolderId = $category === 'mcqs'
            ? $this->getMcqsRootFolderId($token)
            : $this->getQuestionPaperRootFolderId($token);

        return $this->uploadToFolder(
            $filePath,
            $this->cleanFolderName($fileName, 'uploaded-file'),
            $mimeType !== '' ? $mimeType : 'application/octet-stream',
            $targetFolderId,
            $token,
            (int) filesize($filePath)
        );
    }

    private function uploadToFolder(
        string $filePath,
        string $fileName,
        string $mimeType,
        string $targetFolderId,
        string $token,
        int $fileSize
    ): array
    {
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

    private function classFolderName(string $classLabel): string
    {
        $normalizedClass = preg_replace('/^class\s*/i', '', trim($classLabel));
        $normalizedClass = preg_replace('/[^0-9A-Za-z_-]/', '', (string) $normalizedClass);
        return 'Class ' . ($normalizedClass !== '' ? $normalizedClass : 'Unknown');
    }

    private function cleanFolderName(string $value, string $fallback): string
    {
        $clean = preg_replace('/[\\x00-\\x1F\\x7F]+/u', ' ', trim($value));
        $clean = preg_replace('/\s+/u', ' ', (string) $clean);
        $clean = trim((string) $clean, '. ');
        if ($clean === '') {
            return $fallback;
        }

        return function_exists('mb_substr')
            ? mb_substr($clean, 0, 120)
            : substr($clean, 0, 120);
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
     * Move a file into the protected deleteFiles archive below the configured
     * AhmadLearningHub root. This keeps an admin deletion recoverable while
     * removing it from the active Books/notes/source locations.
     */
    public function moveFileToDeleteFiles(string $fileId): bool
    {
        if (!preg_match('/^[A-Za-z0-9_-]+$/', $fileId)) {
            return false;
        }

        try {
            $token = $this->getAccessToken();
            $archiveFolderId = $this->getDeletedFilesFolderId($token);
            $url = 'https://www.googleapis.com/drive/v3/files/' . rawurlencode($fileId)
                . '?fields=id,parents,trashed&supportsAllDrives=true';
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER => ["Authorization: Bearer {$token}"],
                CURLOPT_TIMEOUT => 20,
            ]);
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            if ($response === false || $httpCode !== 200) {
                // A file already absent from Drive is not an archive failure
                // for callers that are cleaning an already-missing record.
                return $httpCode === 404;
            }

            $file = json_decode($response, true);
            if (!is_array($file) || !empty($file['trashed'])) {
                return false;
            }
            $parents = array_values(array_filter(array_map('strval', (array) ($file['parents'] ?? []))));
            if (in_array($archiveFolderId, $parents, true)) {
                return true;
            }

            $query = http_build_query([
                'addParents' => $archiveFolderId,
                'supportsAllDrives' => 'true',
                'fields' => 'id,parents',
            ]);
            if ($parents !== []) {
                $query .= '&removeParents=' . rawurlencode(implode(',', $parents));
            }
            $moveCh = curl_init('https://www.googleapis.com/drive/v3/files/' . rawurlencode($fileId) . '?' . $query);
            curl_setopt_array($moveCh, [
                CURLOPT_CUSTOMREQUEST => 'PATCH',
                CURLOPT_POSTFIELDS => '{}',
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CONNECTTIMEOUT => 15,
                CURLOPT_TIMEOUT => 30,
                CURLOPT_HTTPHEADER => [
                    "Authorization: Bearer {$token}",
                    'Content-Type: application/json',
                ],
            ]);
            $moveResponse = curl_exec($moveCh);
            $moveCode = curl_getinfo($moveCh, CURLINFO_HTTP_CODE);
            $moveError = curl_error($moveCh);
            curl_close($moveCh);
            if ($moveCode !== 200) {
                error_log('Google Drive deleteFiles archive move failed' . ($moveError !== '' ? ': ' . $moveError : " (HTTP {$moveCode})"));
                return false;
            }
            return is_array(json_decode((string) $moveResponse, true));
        } catch (Throwable $e) {
            error_log('Google Drive deleteFiles archive error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Permanently delete a file from Google Drive. This remains available for
     * failed-upload cleanup; admin-facing deletes use moveFileToDeleteFiles().
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
            'auto_organization' => 'Active (Books -> Class X -> Book; Notes -> Class X -> Book -> Chapter -> Admin/User; separate QuestionPaperUpload and McqsUploads roots)',
            'errors' => [],
            'fix_instructions' => [
                'step_1' => "Open the admin Google Drive OAuth page: /google_drive_callback.php",
                'step_2' => "Connect the Google account that should own uploaded notes and textbooks.",
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
