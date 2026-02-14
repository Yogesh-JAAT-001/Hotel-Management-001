<?php
require_once '../config.php';

initApiRequest(['POST']);

// Check if user is admin
if (!isAdmin()) {
    jsonResponse(['error' => 'Admin access required'], 403);
}

$response = ["success" => false, "message" => ""];

if (!isset($_FILES["image"])) {
    jsonResponse(['error' => 'No file selected'], 400);
}

$type = isset($_POST['type']) ? sanitize($_POST['type']) : 'room'; // room or food
if (!in_array($type, ['room', 'food'], true)) {
    jsonResponse(['error' => 'Invalid upload type'], 400);
}
$targetDir = "assets/images/" . $type . "s/";

// Create directory if it doesn't exist
if (!file_exists($targetDir)) {
    if (!mkdir($targetDir, 0755, true)) {
        jsonResponse(['error' => 'Failed to create upload directory'], 500);
    }
}

$file = $_FILES["image"];
$fileName = $file["name"];
$fileTmpName = $file["tmp_name"];
$fileSize = $file["size"];
$fileError = $file["error"];

// Check for upload errors
if ($fileError !== UPLOAD_ERR_OK) {
    $errorMessages = [
        UPLOAD_ERR_INI_SIZE => 'File size exceeds server limit',
        UPLOAD_ERR_FORM_SIZE => 'File size exceeds form limit',
        UPLOAD_ERR_PARTIAL => 'File was only partially uploaded',
        UPLOAD_ERR_NO_FILE => 'No file was uploaded',
        UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder',
        UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
        UPLOAD_ERR_EXTENSION => 'File upload stopped by extension'
    ];
    
    $message = isset($errorMessages[$fileError]) ? $errorMessages[$fileError] : 'Unknown upload error';
    jsonResponse(['error' => $message], 400);
}

// Validate file type
$fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
$allowedTypes = ["jpg", "jpeg", "png", "webp"];

if (!in_array($fileExtension, $allowedTypes)) {
    jsonResponse(['error' => 'Only JPG, PNG, and WEBP files are allowed'], 400);
}

// Validate file size (2MB limit)
$maxSize = 2 * 1024 * 1024; // 2 MB
if ($fileSize > $maxSize) {
    jsonResponse(['error' => 'File size exceeds 2 MB limit'], 400);
}

// Validate image
$imageInfo = getimagesize($fileTmpName);
if ($imageInfo === false) {
    jsonResponse(['error' => 'Invalid image file'], 400);
}

// Generate unique filename
$uniqueName = uniqid() . '_' . time() . '.' . $fileExtension;
$targetFilePath = $targetDir . $uniqueName;

// Move uploaded file
if (move_uploaded_file($fileTmpName, $targetFilePath)) {
    // Resize image if needed (optional)
    resizeImage($targetFilePath, $imageInfo[2], 800, 600);
    
    jsonResponse([
        'success' => true,
        'message' => 'Image uploaded successfully',
        'data' => [
            'filename' => $uniqueName,
            'filepath' => $targetFilePath,
            'url' => APP_URL . '/' . $targetFilePath,
            'size' => $fileSize,
            'type' => $imageInfo['mime']
        ]
    ]);
} else {
    jsonResponse(['error' => 'Failed to move uploaded file'], 500);
}

function resizeImage($filePath, $imageType, $maxWidth, $maxHeight) {
    // Get current dimensions
    list($width, $height) = getimagesize($filePath);
    
    // Calculate new dimensions
    $ratio = min($maxWidth / $width, $maxHeight / $height);
    
    // Only resize if image is larger than max dimensions
    if ($ratio < 1) {
        $newWidth = (int)($width * $ratio);
        $newHeight = (int)($height * $ratio);
        
        // Create new image resource
        $newImage = imagecreatetruecolor($newWidth, $newHeight);
        
        // Load original image
        switch ($imageType) {
            case IMAGETYPE_JPEG:
                $originalImage = imagecreatefromjpeg($filePath);
                break;
            case IMAGETYPE_PNG:
                $originalImage = imagecreatefrompng($filePath);
                imagealphablending($newImage, false);
                imagesavealpha($newImage, true);
                break;
            case IMAGETYPE_WEBP:
                $originalImage = imagecreatefromwebp($filePath);
                break;
            default:
                return false;
        }
        
        // Resize image
        imagecopyresampled($newImage, $originalImage, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
        
        // Save resized image
        switch ($imageType) {
            case IMAGETYPE_JPEG:
                imagejpeg($newImage, $filePath, 85);
                break;
            case IMAGETYPE_PNG:
                imagepng($newImage, $filePath, 8);
                break;
            case IMAGETYPE_WEBP:
                imagewebp($newImage, $filePath, 85);
                break;
        }
        
        // Clean up memory
        imagedestroy($originalImage);
        imagedestroy($newImage);
    }
    
    return true;
}
?>
