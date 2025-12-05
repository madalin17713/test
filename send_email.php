<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

error_reporting(E_ALL);
ini_set('display_errors', 1);

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Create uploads directory if it doesn't exist
    $uploadDir = __DIR__ . '/uploads/';
    if (!file_exists($uploadDir)) {
        if (!mkdir($uploadDir, 0755, true) && !is_dir($uploadDir)) {
            die('Eroare: Nu s-a putut crea directorul pentru încărcări.');
        }
    }

    // File upload handling
    $uploadedFile = '';
    $originalFileName = '';
    
    if (isset($_FILES['poza']) && $_FILES['poza']['error'] === UPLOAD_ERR_OK) {
        $fileTmpPath = $_FILES['poza']['tmp_name'];
        $fileName = $_FILES['poza']['name'];
        $originalFileName = $fileName; // Keep original name for email
        $fileSize = $_FILES['poza']['size'];
        $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

        // Validate file type
        $allowedExtensions = ["jpg", "jpeg", "png"];
        if (!in_array($fileExtension, $allowedExtensions, true)) {
            die("Eroare: Doar fișiere de tip JPG, JPEG și PNG sunt permise.");
        }

        // Validate file size (5MB max)
        $maxFileSize = 5 * 1024 * 1024; // 5MB in bytes
        if ($fileSize > $maxFileSize) {
            die("Eroare: Fișierul este prea mare. Mărimea maximă permisă este de 5MB.");
        }

        // Generate unique filename
        $newFileName = uniqid('img_', true) . '.' . $fileExtension;
        $targetPath = $uploadDir . $newFileName;

        // Move the uploaded file
        if (move_uploaded_file($fileTmpPath, $targetPath)) {
            $uploadedFile = $targetPath;
            // Set proper permissions
            chmod($targetPath, 0644);
        } else {
            die("Eroare: Nu s-a putut salva fișierul. Vă rugăm încercați din nou.");
        }
    } else {
        // Handle file upload errors
        $uploadErrors = [
            UPLOAD_ERR_INI_SIZE => 'Fișierul depășește limita maximă de încărcare definită în server.',
            UPLOAD_ERR_FORM_SIZE => 'Fișierul depășește limita maximă specificată în formular.',
            UPLOAD_ERR_PARTIAL => 'Încărcarea fișierului a fost întreruptă.',
            UPLOAD_ERR_NO_FILE => 'Nu a fost selectat niciun fișier.',
            UPLOAD_ERR_NO_TMP_DIR => 'Lipsește un director temporar.',
            UPLOAD_ERR_CANT_WRITE => 'Nu s-a putut scrie fișierul pe disc.',
            UPLOAD_ERR_EXTENSION => 'O extensie PHP a oprit încărcarea fișierului.'
        ];
        
        $errorCode = $_FILES['poza']['error'] ?? null;
        if ($errorCode !== null && $errorCode !== UPLOAD_ERR_OK) {
            die('Eroare la încărcarea fișierului: ' . ($uploadErrors[$errorCode] ?? 'Eroare necunoscută.'));
        }
    }

    // Sanitize form data
    $nume = filter_input(INPUT_POST, 'nume', FILTER_SANITIZE_STRING) ?: '';
    $prenume = filter_input(INPUT_POST, 'prenume', FILTER_SANITIZE_STRING) ?: '';
    $dataNastere = filter_input(INPUT_POST, 'dataNastere', FILTER_SANITIZE_STRING) ?: '';
    $oraNastere = filter_input(INPUT_POST, 'oraNastere', FILTER_SANITIZE_STRING) ?: '';
    $locNastere = filter_input(INPUT_POST, 'locNastere', FILTER_SANITIZE_STRING) ?: '';
    $intentie = nl2br(htmlspecialchars(trim($_POST['intentie'] ?? ''), ENT_QUOTES, 'UTF-8'));

    // Validate required fields
    $required = ['nume', 'prenume', 'dataNastere', 'oraNastere', 'locNastere'];
    $missing = [];
    foreach ($required as $field) {
        if (empty($_POST[$field])) {
            $missing[] = $field;
        }
    }
    
    if (!empty($missing)) {
        die('Eroare: Toate câmpurile obligatorii trebuie completate: ' . implode(', ', $missing));
    }

    // Email configuration - UPDATE THESE WITH YOUR ACTUAL EMAIL SETTINGS
    $to = 'contact@domeniul-tau.ro';
    $subject = "Formular Aura - $nume $prenume";

    // Build email message with better formatting
    $message = "
    <!DOCTYPE html>
    <html lang='ro'>
    <head>
        <meta charset='UTF-8'>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; }
            .header { background-color: #6c63ff; color: white; padding: 20px; text-align: center; }
            .content { padding: 20px; }
            .footer { margin-top: 20px; padding: 10px; text-align: center; font-size: 12px; color: #777; }
            .field { margin-bottom: 10px; }
            .field-label { font-weight: bold; color: #555; }
        </style>
    </head>
    <body>
        <div class='header'>
            <h2>Formular Aura - Noua cerere</h2>
        </div>
        <div class='content'>
            <div class='field'><span class='field-label'>Nume:</span> " . htmlspecialchars($nume, ENT_QUOTES, 'UTF-8') . "</div>
            <div class='field'><span class='field-label'>Prenume:</span> " . htmlspecialchars($prenume, ENT_QUOTES, 'UTF-8') . "</div>
            <div class='field'><span class='field-label'>Data nașterii:</span> " . htmlspecialchars($dataNastere, ENT_QUOTES, 'UTF-8') . "</div>
            <div class='field'><span class='field-label'>Ora nașterii:</span> " . htmlspecialchars($oraNastere, ENT_QUOTES, 'UTF-8') . "</div>
            <div class='field'><span class='field-label'>Locul nașterii:</span> " . htmlspecialchars($locNastere, ENT_QUOTES, 'UTF-8') . "</div>";
            
    if (!empty($intentie)) {
        $message .= "
            <div class='field'><span class='field-label'>Intenție:</span> $intentie</div>";
    }
    
    if (!empty($uploadedFile)) {
        $message .= "
            <div class='field'><span class='field-label'>Fișier încărcat:</span> " . htmlspecialchars($originalFileName, ENT_QUOTES, 'UTF-8') . "</div>";
    }
    
    $message .= "
        </div>
        <div class='footer'>
            <p>Acest email a fost trimis de pe formularul de pe site-ul AuraScan.</p>
        </div>
    </body>
    </html>";

    // Send email using PHPMailer
    $mail = new PHPMailer(true);

    try {
        // Server settings
         $mail->isSMTP();
        $mail->Host = 'mail.domeniul-tau.ro';
        $mail->SMTPAuth = true;
        $mail->Username = 'contact@domeniul-tau.ro+';
        $mail->Password = 'PAROLA_EMAIL';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS; // sau SMTPS (SSL)
        $mail->Port = 587; // sau 465 pentru SSL
        $mail->CharSet = 'UTF-8';
        
        // Set the email sender and recipient
        $mail->setFrom('contact@domeniul-tau.ro', 'AuraScan');
        $mail->addAddress($to);
        
        // Add reply-to with user's email if available
        if (!empty($_POST['email'])) {
            $email = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);
            if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $mail->addReplyTo($email, "$nume $prenume");
            }
        }

        // Content
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $message;
        $mail->AltBody = strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $message));

        // Add attachment if file was uploaded
        if (!empty($uploadedFile)) {
            $mail->addAttachment(
                $uploadedFile,
                'Poza_' . preg_replace('/[^\p{L}\p{N}\s.-]/u', '', $originalFileName),
                'base64',
                '',
                'inline'
            );
            
            // Add embedded image in the email body
            $cid = md5($uploadedFile);
            $message = str_replace(
                '</body>',
                '<div style="margin-top: 20px; border-top: 1px solid #eee; padding-top: 15px;">' .
                '<h3>Previzualizare imagine:</h3>' .
                '<img src="cid:' . $cid . '" alt="Imagine încărcată" style="max-width: 100%; height: auto; border: 1px solid #ddd; border-radius: 4px; padding: 5px; background-color: #f9f9f9; max-height: 300px;">' .
                '</div></body>',
                $message
            );
            $mail->addEmbeddedImage($uploadedFile, $cid, 'poza_utilizator.jpg');
            $mail->Body = $message;
        }

        $mail->send();
        header('Location: multumim.html');
        exit();

    } catch (Exception $e) {
        echo "Eroare la trimiterea emailului: " . $mail->ErrorInfo;
    }

} else {
    header('Location: index.html');
    exit();
}
?>