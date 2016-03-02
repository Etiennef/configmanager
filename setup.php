<?php

/**
 * Fonction de définition de la version du plugin
 * @return array description du plugin
 */
function plugin_version_configmanager() {
   return array(
         'name' => "ConfigManager",
         'version' => '1.0.0',
         'author' => 'Etiennef',
         'license' => 'GPLv2+',
         'homepage' => 'https://github.com/Etiennef/configmanager',
         'minGlpiVersion' => '0.84'
   );
}

/**
 * Fonction de vérification des prérequis
 *
 * @return boolean le plugin peut s'exécuter sur ce GLPI
 */
function plugin_configmanager_check_prerequisites() {
   // TODO tester sur des vraies versions à terme
   if (version_compare(GLPI_VERSION, '0.84.8', 'lt') || version_compare(GLPI_VERSION, '0.92', 'ge')) {
      echo __("Plugin has been tested only for GLPI 0.84.8", 'configmanager');
      return false;
   }

   return true;
}

/**
 * Fonction de vérification de la configuration initiale
 *
 * @param type $verbose
 * @return boolean la config est faite
 */
function plugin_configmanager_check_config($verbose = false) {
   if (true) {
      // il n'y a aucun prérequis, le test ne peut pas échouer.
      return true;
   }
   if ($verbose) {
      echo 'Installed / not configured';
   }
   return false;
}

/**
 * Fonction d'initialisation du plugin.
 *
 * @global array $PLUGIN_HOOKS
 */
function plugin_init_configmanager() {
   global $PLUGIN_HOOKS;
   $PLUGIN_HOOKS['csrf_compliant']['configmanager'] = true;
}









