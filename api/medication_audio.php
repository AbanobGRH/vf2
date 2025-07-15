<?php
require_once 'config.php';

validateApiKey();

$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'POST':
        generateMedicationAudio();
        break;
    case 'GET':
        getMedicationAudioFiles();
        break;
    default:
        sendError('Method not allowed', 405);
}

function generateMedicationAudio() {
    $userId = $_POST['user_id'] ?? '';
    if (!$userId) {
        sendError('User ID required');
    }
    
    $pdo = getDBConnection();
    
    // Get upcoming medication reminders
    $stmt = $pdo->prepare("
        SELECT mr.*, m.name, m.dosage, m.instructions 
        FROM medication_reminders mr 
        JOIN medications m ON mr.medication_id = m.id 
        WHERE mr.user_id = ? 
        AND mr.reminder_time > NOW() 
        AND mr.reminder_time <= DATE_ADD(NOW(), INTERVAL 24 HOUR)
        AND mr.is_taken = FALSE
        ORDER BY mr.reminder_time ASC
    ");
    $stmt->execute([$userId]);
    $reminders = $stmt->fetchAll();
    
    $audioFiles = [];
    
    foreach ($reminders as $reminder) {
        $message = "Time to take your medication. {$reminder['name']}, {$reminder['dosage']}. {$reminder['instructions']}";
        $filename = "reminder_" . $reminder['id'] . "_" . time() . ".mp3";
        $filepath = AUDIO_UPLOAD_PATH . $filename;
        
        // Generate audio using text-to-speech (you can integrate with services like Google TTS, Amazon Polly, etc.)
        if (generateTTSAudio($message, $filepath)) {
            // Update reminder with audio file path
            $updateStmt = $pdo->prepare("UPDATE medication_reminders SET audio_file_path = ? WHERE id = ?");
            $updateStmt->execute([$filename, $reminder['id']]);
            
            $audioFiles[] = [
                'reminder_id' => $reminder['id'],
                'filename' => $filename,
                'reminder_time' => $reminder['reminder_time'],
                'medication' => $reminder['name']
            ];
        }
    }
    
    sendResponse(['audio_files' => $audioFiles]);
}

function getMedicationAudioFiles() {
    $userId = $_GET['user_id'] ?? '';
    $lastCheck = $_GET['last_check'] ?? date('Y-m-d H:i:s', strtotime('-5 minutes'));
    
    if (!$userId) {
        sendError('User ID required');
    }
    
    $pdo = getDBConnection();
    
    // Get new audio files since last check
    $stmt = $pdo->prepare("
        SELECT mr.*, m.name, m.dosage 
        FROM medication_reminders mr 
        JOIN medications m ON mr.medication_id = m.id 
        WHERE mr.user_id = ? 
        AND mr.audio_file_path IS NOT NULL
        AND mr.created_at > ?
        AND mr.is_taken = FALSE
        ORDER BY mr.reminder_time ASC
    ");
    $stmt->execute([$userId, $lastCheck]);
    $newFiles = $stmt->fetchAll();
    
    $response = [
        'new_files' => [],
        'current_time' => date('Y-m-d H:i:s')
    ];
    
    foreach ($newFiles as $file) {
        if (file_exists(AUDIO_UPLOAD_PATH . $file['audio_file_path'])) {
            $response['new_files'][] = [
                'reminder_id' => $file['id'],
                'filename' => $file['audio_file_path'],
                'reminder_time' => $file['reminder_time'],
                'medication' => $file['name'],
                'dosage' => $file['dosage'],
                'url' => 'http://' . $_SERVER['HTTP_HOST'] . '/api/audio/' . $file['audio_file_path']
            ];
        }
    }
    
    sendResponse($response);
}

function generateTTSAudio($text, $filepath) {
    // Simple TTS using espeak (Linux) - you can replace with better TTS service
    $command = "espeak -s 150 -v en+f3 -w " . escapeshellarg($filepath) . " " . escapeshellarg($text);
    
    // For Windows, you could use SAPI:
    // $command = "powershell -Command \"Add-Type -AssemblyName System.Speech; \$speak = New-Object System.Speech.Synthesis.SpeechSynthesizer; \$speak.SetOutputToWaveFile('$filepath'); \$speak.Speak('$text'); \$speak.Dispose()\"";
    
    exec($command, $output, $returnCode);
    
    // Convert WAV to MP3 if needed (requires ffmpeg)
    if ($returnCode === 0 && file_exists($filepath)) {
        $mp3Path = str_replace('.wav', '.mp3', $filepath);
        exec("ffmpeg -i " . escapeshellarg($filepath) . " " . escapeshellarg($mp3Path), $output2, $returnCode2);
        
        if ($returnCode2 === 0 && file_exists($mp3Path)) {
            unlink($filepath); // Remove WAV file
            return true;
        }
    }
    
    return file_exists($filepath);
}

// Serve audio files
if (isset($_GET['file'])) {
    $filename = basename($_GET['file']);
    $filepath = AUDIO_UPLOAD_PATH . $filename;
    
    if (file_exists($filepath)) {
        header('Content-Type: audio/mpeg');
        header('Content-Length: ' . filesize($filepath));
        header('Content-Disposition: inline; filename="' . $filename . '"');
        readfile($filepath);
        exit();
    } else {
        http_response_code(404);
        echo json_encode(['error' => 'File not found']);
    }
}
?>