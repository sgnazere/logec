<?php
require_once 'config.php';
require_once 'functions.php';

// Vérification de sécurité
if (!isset($_SESSION['admin_id']) || !isAdmin()) {
    die("Accès non autorisé");
}

// Vérification si c'est une requête POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    // Générer un token CSRF si non défini
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    // Si ce n'est pas une requête POST, afficher le formulaire
    ?>
    <!DOCTYPE html>
    <html lang="fr">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Réinitialisation du mot de passe - LOGEC</title>
        <link rel="stylesheet" href="style.css">
    </head>
    <body>
        <div class="reset-password-form">
            <h3>Réinitialiser le mot de passe administrateur</h3>
            <form id="resetAdminForm" method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                <div class="form-group">
                    <p>Êtes-vous sûr de vouloir réinitialiser le mot de passe de l'administrateur ?</p>
                    <p>Cette action enverra un email à l'utilisateur avec ses nouveaux identifiants.</p>
                </div>
                <div class="form-group">
                    <button type="submit" class="btn btn-warning" onclick="return confirm('Êtes-vous sûr de vouloir réinitialiser le mot de passe ?');">
                        Réinitialiser le mot de passe
                    </button>
                </div>
            </form>
        </div>

        <script>
        document.getElementById('resetAdminForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            if (confirm('Êtes-vous sûr de vouloir réinitialiser le mot de passe ?')) {
                fetch('reset_admin.php', {
                    method: 'POST',
                    body: new FormData(this)
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert(data.message);
                    } else {
                        alert(data.message);
                    }
                })
                .catch(error => {
                    alert('Une erreur est survenue');
                    console.error('Error:', error);
                });
            }
        });
        </script>
    </body>
    </html>
    <?php
    exit();
}

// Le reste du code pour le traitement POST
try {
    $db = getDBConnection();

    // Définir le nouveau mot de passe
    $password = "AJN@2024"; // Mot de passe par défaut
    $hash = password_hash($password, PASSWORD_DEFAULT);

    // Vérifier si l'utilisateur existe
    $checkQuery = $db->prepare("SELECT id, email FROM admin WHERE username = 'affiba.j'");
    $checkQuery->execute();
    $user = $checkQuery->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        echo json_encode([
            'success' => false,
            'message' => "Utilisateur non trouvé"
        ]);
        exit();
    }

    // Mettre à jour le mot de passe de l'admin
    $query = $db->prepare("UPDATE admin SET 
        password = :password,
        force_password_change = 1,
        password_changed_at = NOW()
        WHERE username = 'affiba.j'");
    $query->execute(['password' => $hash]);

    // Journaliser l'action
    error_log("Réinitialisation du mot de passe pour l'utilisateur affiba.j effectuée par " . $_SESSION['admin_username']);

    // Envoyer un email de notification
    $to = $user['email'];
    $subject = "Réinitialisation de votre mot de passe LOGEC";
    $message = "Bonjour,\n\n";
    $message .= "Votre mot de passe a été réinitialisé par un administrateur.\n";
    $message .= "Vos nouveaux identifiants sont :\n";
    $message .= "Nom d'utilisateur: affiba.j\n";
    $message .= "Mot de passe: AJN@2024\n\n";
    $message .= "Pour des raisons de sécurité, vous devrez changer votre mot de passe lors de votre prochaine connexion.\n\n";
    $message .= "Cordialement,\nL'équipe LOGEC";

    // Envoyer l'email
    $headers = "From: noreply@logec.ci\r\n";
    $headers .= "Reply-To: support@logec.ci\r\n";
    $headers .= "X-Mailer: PHP/" . phpversion();

    mail($to, $subject, $message, $headers);

    // Afficher le message de succès
    echo json_encode([
        'success' => true,
        'message' => "Le mot de passe a été réinitialisé avec succès. Un email a été envoyé à l'utilisateur."
    ]);

} catch(PDOException $e) {
    error_log("Erreur lors de la réinitialisation du mot de passe : " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => "Une erreur est survenue lors de la réinitialisation du mot de passe."
    ]);
}
?>