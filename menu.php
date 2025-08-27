<?php
// La session est déjà démarrée dans le fichier principal
?>
<div class="nav-container">
    <a href="gestion_demande_vehicule.php" class="nav-btn">
        <i class="fas fa-home"></i> Accueil
    </a>
    <a href="gerer_chauffeurs.php" class="nav-btn">
        <i class="fas fa-id-card"></i> Chauffeurs
    </a>
    <a href="gerer_vehicules.php" class="nav-btn">
        <i class="fas fa-car"></i> Véhicules
    </a>
    <a href="gerer_employes.php" class="nav-btn">
        <i class="fas fa-users"></i> Employés
    </a>
    <a href="gerer_deplacements.php" class="nav-btn">
        <i class="fas fa-route"></i> Déplacements
    </a>
    <a href="logout.php" class="nav-btn nav-btn-danger">
        <i class="fas fa-sign-out-alt"></i> Déconnexion
    </a>
</div>

<style>
:root {
    --primary-color: #4CAF50;
    --primary-dark: #388E3C;
    --primary-light: #C8E6C9;
    --accent-color: #FF5722;
    --text-primary: #212121;
    --text-secondary: #757575;
    --divider-color: #BDBDBD;
    --background-light: #f5f5f5;
    --white: #ffffff;
    --danger: #dc3545;
    --warning: #ffc107;
    --success: #28a745;
}

.nav-container {
    display: flex;
    gap: 1rem;
    align-items: center;
    margin-top: 1rem;
}

.nav-btn {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.5rem 1rem;
    background: var(--white);
    color: var(--text-primary);
    text-decoration: none;
    border-radius: 5px;
    transition: all 0.3s ease;
    border: 1px solid var(--divider-color);
}

.nav-btn:hover {
    background: var(--primary-color);
    color: var(--white);
    transform: translateY(-2px);
}

.nav-btn i {
    font-size: 1rem;
}

.nav-btn-danger {
    background: var(--danger);
    color: var(--white);
    border: none;
}

.nav-btn-danger:hover {
    background: #c82333;
}

@media (max-width: 768px) {
    .nav-container {
        flex-wrap: wrap;
        justify-content: center;
    }

    .nav-btn {
        min-width: 120px;
        justify-content: center;
    }
}
</style> 