<?php
/**
 * Plan Comptable Mauritanien (PCM) - Base de connaissances
 * Conforme au Systeme Comptable Mauritanien (SCM)
 * Reference: Reglement ANC Mauritanie
 */

function get_plan_comptable_knowledge() {
    $content = '';
    if (file_exists(__DIR__.'/PCM.txt')) {
        $content = file_get_contents(__DIR__.'/PCM.txt');
        // Limit size to avoid API token limits (approx 50k chars)
        if (strlen($content) > 100000) $content = substr($content, 0, 100000) . "... (Tronqué pour respecter les limites)";
    }
    return $content;
}

