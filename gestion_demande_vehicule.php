<?php
session_start();
require_once 'check_auth.php';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Véhicules - Tableau de Bord</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .dashboard {
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
            width: 100%;
        }
        .welcome-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 2px solid #eee;
        }
        .welcome-header h2 {
            font-size: 24px;
            color: #333;
            margin: 0;
        }
        .welcome-header .user-info {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }
        .welcome-header .user-name {
            font-size: 24px;
            color: #333;
            margin: 0;
        }
        .welcome-header .user-role {
            font-size: 16px;
            color: #666;
            margin: 0;
        }
        .logout-btn {
            background-color: #dc3545;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            text-decoration: none;
            font-size: 16px;
            transition: background-color 0.3s ease;
        }
        .logout-btn:hover {
            background-color: #c82333;
        }
        .dashboard-menu {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 25px;
            margin-top: 30px;
        }
        .menu-item {
            background: #f8f9fa;
            padding: 25px;
            border-radius: 12px;
            text-align: center;
            transition: all 0.3s ease;
            border: 1px solid #e9ecef;
        }
        .menu-item:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            border-color: #007bff;
        }
        .menu-item h3 {
            margin: 15px 0;
            color: #2c3e50;
            font-size: 20px;
        }
        .menu-item p {
            color: #6c757d;
            margin: 10px 0;
            font-size: 15px;
        }
        .menu-item i {
            font-size: 24px;
            color: #007bff;
            margin-bottom: 15px;
        }
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem;
            background-color: #fff;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 2rem;
            position: relative;
            height: 80px;
        }
        .header-logo {
            width: 100px;
            height: auto;
            object-fit: contain;
            position: absolute;
            left: 50%;
            transform: translateX(-50%);
            margin-top: -10px;
        }
        @media (max-width: 768px) {
            .dashboard {
                padding: 20px;
            }
            .welcome-header {
                flex-direction: column;
                text-align: center;
                gap: 15px;
            }
            .dashboard-menu {
                grid-template-columns: 1fr;
            }
        }
    </style>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <div class="container">
        <div class="dashboard">
            <div class="header">
                <div class="user-info">
                    <span class="user-name">
                        <?php 
                        if (isset($_SESSION['admin_nom']) && isset($_SESSION['admin_prenoms'])) {
                            echo htmlspecialchars($_SESSION['admin_prenoms'] . ' ' . $_SESSION['admin_nom']);
                        } else {
                            echo htmlspecialchars($_SESSION['admin_username']);
                        }
                        ?>
                    </span>
                    <span class="user-role">Administrateur</span>
                </div>
                <img src="images/Logo2.png" alt="" class="header-logo">
                <a href="logout.php" class="logout-btn">
                    <i class="fas fa-sign-out-alt"></i> Déconnexion
                </a>
            </div>

            <div class="dashboard-menu">
                <div class="menu-item" onclick="window.location.href='gerer_chauffeurs.php';" style="cursor: pointer;">
                    <i class="fas fa-id-card"></i>
                    <h3>Chauffeurs</h3>
                    <p>Gérer les chauffeurs et leurs permis</p>
                </div>
                
                <div class="menu-item" onclick="window.location.href='gerer_vehicules.php';" style="cursor: pointer;">
                    <i class="fas fa-car"></i>
                    <h3>Véhicules</h3>
                    <p>Gérer la flotte de véhicules</p>
                </div>
                
                <div class="menu-item" onclick="window.location.href='gerer_employes.php';" style="cursor: pointer;">
                    <i class="fas fa-users"></i>
                    <h3>Employés</h3>
                    <p>Gérer les employés</p>
                </div>
                
                <div class="menu-item" onclick="window.location.href='gerer_destinations.php';" style="cursor: pointer;">
                    <i class="fas fa-map-marker-alt"></i>
                    <h3>Destinations</h3>
                    <p>Gérer les sites et organisations</p>
                </div>
 
                <div class="menu-item" onclick="window.location.href='gerer_demandes.php';" style="cursor: pointer;">
                    <i class="fas fa-clipboard-list"></i>
                    <h3>Demandes</h3>
                    <p>Gérer les demandes de véhicules</p>
                </div>

                <div class="menu-item" onclick="window.location.href='gerer_deplacements.php';" style="cursor: pointer;">
                    <i class="fas fa-route"></i>
                    <h3>Déplacements</h3>
                    <p>Suivi des déplacements</p>
                </div>

                <div class="menu-item" onclick="window.location.href='calendrier.php';" style="cursor: pointer;">
                    <i class="fas fa-calendar-alt"></i>
                    <h3>Calendrier</h3>
                    <p>Planning des déplacements</p>
                </div>

                <div class="menu-item" onclick="window.location.href='rapports.php';" style="cursor: pointer;">
                    <i class="fas fa-chart-bar"></i>
                    <h3>Rapports</h3>
                    <p>Statistiques et analyses</p>
                </div>
                <div class="menu-item" onclick="window.location.href='gerer_vidanges.php';" style="cursor: pointer;">
                    <i class="fas fa-oil-can"></i>
                    <h3>Vidanges</h3>
                    <p>Historique et gestion des vidanges</p>
                </div>
                <div class="menu-item" onclick="window.location.href='gerer_assurances.php';" style="cursor: pointer;">
                    <i class="fas fa-shield-alt"></i>
                    <h3>Assurances</h3>
                    <p>Historique et gestion des assurances</p>
                </div>
                <div class="menu-item" onclick="window.location.href='gerer_visites_techniques.php';" style="cursor: pointer;">
                    <i class="fas fa-file-medical"></i>
                    <h3>Visites techniques</h3>
                    <p>Historique et gestion des visites techniques</p>
                </div>
            </div>
        </div>
    </div>
</body>
</html> 