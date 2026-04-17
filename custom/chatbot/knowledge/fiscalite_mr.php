<?php
/**
 * Fiscalite Mauritanienne - Base de connaissances
 * Reference: Code General des Impots (CGI) de Mauritanie
 * Mise a jour selon les dernieres lois de finances
 */

function get_fiscalite_knowledge() {
    $content = '';
    if (file_exists(__DIR__.'/CGI-Fr-2023.txt')) {
        $content = file_get_contents(__DIR__.'/CGI-Fr-2023.txt');
        // Limit size to avoid API token limits (approx 50k chars)
        if (strlen($content) > 100000) $content = substr($content, 0, 100000) . "... (Tronqué pour respecter les limites)";
    }
    return $content;
}

