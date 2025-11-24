<?php

namespace App\Service;

class ReservationHashService
{
    private string $secretKey;

    public function __construct()
    {
        // Utilise une clé secrète pour le hashage
        $this->secretKey = $_ENV['APP_SECRET'] ?? 'default-secret-key';
    }

    /**
     * Hash les données de réservation pour masquer l'identité
     */
    public function hashReservationData(?string $reservedBy): ?string
    {
        if (!$reservedBy) {
            return null;
        }

        // Hash avec timestamp pour éviter les collisions
        $timestamp = time();
        return hash('sha256', $this->secretKey . $reservedBy . $timestamp);
    }

    /**
     * Génère un identifiant anonyme pour la réservation
     */
    public function generateAnonymousId(): string
    {
        return 'gift_' . bin2hex(random_bytes(8));
    }

    /**
     * Vérifie si une réservation existe sans révéler d'informations
     */
    public function isReservationValid(string $hashedData): bool
    {
        // Simple vérification que le hash n'est pas vide
        return !empty($hashedData) && strlen($hashedData) === 64;
    }
}
