<?php


/**
 * Cette classe contient le tronc commun pour les objets génériques de configuration et de règles.
 * Pas d'intérêt pour elle-même
 * @author Etiennef
 */
class PluginConfigmanagerCommon extends CommonDBTM {
   const TYPE_GLOBAL = 'global';
   const TYPE_USERENTITY = 'userentity';
   const TYPE_ITEMENTITY = 'itementity';
   const TYPE_PROFILE = 'profile';
   const TYPE_USER = 'user';


   /**
    * Renvoie un tableau de tous les types de config possibles
    * @return multitype:string
    */
   protected final static function getAllConfigTypes() {
      //CHANGE WHEN ADD CONFIG_TYPE
      return array(
            self::TYPE_GLOBAL,
            self::TYPE_USERENTITY,
            self::TYPE_ITEMENTITY,
            self::TYPE_PROFILE,
            self::TYPE_USER,
      );
   }


   /**
    * Renvoie le tableau représentant la description de la configuration (la méta-configuration, quelque part)
    */
   protected final static function getConfigParams() {
      static $configparams_instance = array(); // tableau nécessaire car sinon le singleton est partagé entre toutes les classes qui héritent
      if(! isset($configparams_instance[get_called_class()])) {
         $configparams_instance[get_called_class()] = static::makeConfigParams();
      }
      return $configparams_instance[get_called_class()];
   }

   static function makeConfigParams() {
      return array();
   }



   /**
    * Création des tables liées à cet objet.
    * Utilisée lors de l'installation du plugin
    * @param $additionnal_param string colonne à ajouter dans la table
    */
   public static function install($additionnal_param='') {
      global $DB;
      $table = self::getTable();
      $request = '';

      $query = "CREATE TABLE `$table` (
      `" . self::getIndexName() . "` int(11) NOT NULL AUTO_INCREMENT,
               `config__type` varchar(50) collate utf8_unicode_ci NOT NULL,
               `config__type_id` int(11) collate utf8_unicode_ci NOT NULL, ";

      $query .= $additionnal_param;

      foreach(self::getConfigParams() as $param => $desc) {
         if($desc['type'] === 'readonly text') continue;
         $query .= "`$param` varchar(" . $desc['maxlength'] . ") collate utf8_unicode_ci,";
      }

      $query .= "PRIMARY KEY  (`" . self::getIndexName() . "`)
            ) ENGINE=MyISAM  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci";

      if(! TableExists($table)) {
         $DB->queryOrDie($query, $DB->error());
      }
   }

   /**
    * Suppression des tables liées à cet objet.
    * Utilisé lors de la désinstallation du plugin
    * @return boolean
    */
   public final static function uninstall() {
      global $DB;
      $table = self::getTable();

      if(TableExists($table)) {
         $query = "DROP TABLE `$table`";
         $DB->queryOrDie($query, $DB->error());
      }
      return true;
   }




   /**
    * Renvoie les types de configurations correspondant à un type d'objet de GLPI
    * @param string $glpiobjecttype le type de l'objet GLPI (classiquement obtenu via CommonGLPI::getType())
    * @return array : string tableau des types de configurations possibles
    */
   protected final static function getTypeForGLPIObject($glpiobjecttype) {
      //CHANGE WHEN ADD GLPI_TYPE
      switch($glpiobjecttype) {
         case 'Config' :
            return array(1=>self::TYPE_GLOBAL);
         case 'Entity' :
            return array(
               2=>self::TYPE_USERENTITY,
               3=>self::TYPE_ITEMENTITY,
            );
         case 'Profile' :
            return array(4=>self::TYPE_PROFILE);
         case 'User' :
            return array(5=>self::TYPE_USER);
         case 'Preference' :
            return array(5=>self::TYPE_USER);
         default :
            return '';
      }
   }


   static final function canView() {
      return true;
   }

   static final function canCreate() {
      return true;
   }


   /**
    * Fonction générique pour tester les droits sur cette entrée de config.
    * C'est juste une version générlque pour canViewItem et canCreateItem
    * @param string $right 'r' ou 'w' selon qu'on s'intéresse aux droits en lecture ou en écriture
    * @return boolean l'utilisateur courant a-t-il le droit demandé
    */
   protected static final function canItemStatic($type, $type_id, $right) {
      //CHANGE WHEN ADD CONFIG_TYPE
      if(! static::hasFieldsForType($type)) return false;

      switch($type) {
         case self::TYPE_GLOBAL :
            return $type_id==0 && Session::haveRight('config', $right);
         case self::TYPE_USERENTITY :
         case self::TYPE_ITEMENTITY :
            return (new Entity())->can($type_id, $right);
         case self::TYPE_PROFILE :
            return (new Profile())->can($type_id, $right);
         case self::TYPE_USER :
            return Session::getLoginUserID() == $type_id || (new User())->can($type_id, $right);
         default :
            return false;
      }
   }

   final function canViewItem() {
      return self::canItemStatic($this->fields['config__type'], $this->fields['config__type_id'], 'r');
   }

   final function canCreateItem() {
      return self::canItemStatic($this->fields['config__type'], $this->fields['config__type_id'], 'w');
   }

   final function canUpdateItem() {
      return self::canItemStatic($this->fields['config__type'], $this->fields['config__type_id'], UPDATE);
   }

   /**
    * Gère la transformation des inputs multiples en quelque chose d'inserable dans la base (en l'occurence une chaine json).
    * .
    * @see CommonDBTM::prepareInputForUpdate()
    */
   function prepareInputForUpdate($input) {
      // ce sont des champs qu'il est interdit de modifier
      unset($input['config__type']);
      unset($input['config__type_id']);

      return $input;
   }


   /**
    * Détermine le type_id de la configuration courante pour un type de configuration donné, en tenant compte d'abord du tableau de paramètres, et s'il est absent du tableau en devinant la bonne valeur à partir des informations de session.
    * @param string $type type de configuration
    * @param array:string $values tableau indiquant la valeur des paramètres souhaité (les clés sont les $type possibles)
    * @return type_id à recherche en BDD
    */
   protected final static function getTypeIdForCurrentConfig($type, $values=array()) {
      if(isset($values[$type])) {
         return $values[$type];
      }

      //CHANGE WHEN ADD CONFIG_TYPE
      switch($type) {
         case self::TYPE_GLOBAL :
            return 0;
         case self::TYPE_USERENTITY :
            return $_SESSION['glpiactive_entity'];
         case self::TYPE_ITEMENTITY :
            return $_SESSION['glpiactive_entity'];
         case self::TYPE_PROFILE :
            return $_SESSION['glpiactiveprofile']['id'];
         case self::TYPE_USER :
            return Session::getLoginUserID();
         default :
            return false;
      }
   }

   /**
    * Regarde dans la description s'il existe des éléments de configuration pour ce type
    * @param string $type type de configuration
    * @return boolean
    */
   protected static function hasFieldsForType($type) {
      echo 'This function should have been overridden';
   }

   /**
    * Définit les onglets à afficher pour les objets auxquels peuvent se rattacher des configurations.
    * En particulier, teste les différents types de configs pour savoir s'il y a lieu d'afficher un onglet pour l'objet GLPI.
    * .
    * @see CommonGLPI::getTabNameForItem()
    */
   final function getTabNameForItem(CommonGLPI $item, $withtemplate = 0) {
      if(($types = self::getTypeForGLPIObject($item->getType())) === '') {
         return '';
      }

      $res = array();
      foreach ($types as $tab => $type) {
         if(static::hasFieldsForType($type)) {
            $res[$tab] = static::getTabNameForConfigType($type);
         }
      }

      return $res;
   }



   /**
    * Fonction d'affichage du contenu de l'onglet de configuration, pour un objet GLPI, et un n° d'onglet (la combinaison des eux permet de déterminer de quel type de config il s'agit, si l'objet GLPI a deux types de config)
    * @param CommonGLPI $item Objet GLPI auquel sont ratachés des objets de config
    * @param number $tabnum n° de l'onglet (permet de savoir à quel type de config correspnd l'onglet demandé)
    * @param number $withtemplate inutilisé dans notre contexte
    * @return boolean true, sauf si une erreur est contatée (ne devrait pas se produire en principe)
    */
   final static function displayTabContentForItem(CommonGLPI $item, $tabnum = 1, $withtemplate = 0) {
      $type = self::getTypeForGLPIObject($item->getType())[$tabnum];
      $type_id = self::getTypeIdForGLPIItem($item);
      return static::showFormStatic($type, $type_id);
   }

   /**
    * Fonction d'affichage du contenu de l'onglet de configuration pour un item de configuration
    * @param string $type type de configuration
    * @param integer $type_id type_id de l'item à afficher
    * @return boolean true, sauf si une erreur est contatée (ne devrait pas se produire en principe)
    */
   protected static function showFormStatic($type, $type_id) {
      echo 'This function should have been overridden';
   }

   /**
    * Détermine le type_id de la (des) configuration(s) rattachée à un objet GLPI
    * @param CommonGLPI $item l'objet auquel est rattaché la configuration
    * @return number type_id de la (des) configuration(s) associée
    */
   private final static function getTypeIdForGLPIItem(CommonGLPI $item) {
      //CHANGE WHEN ADD GLPI_TYPE
      switch($item->getType()) {
         case 'Config' : return 0;
         case 'Entity' :
         case 'Profile' :
         case 'User' : return $item->getId();
         case 'Preference' : return Session::getLoginUserID();
         default : return false;
      }
   }


   /**
    * Détermine le nom de l'onglet pour un type de configuration donné. Par défaut, c'est le nom du plugin, mais peut être surchargé pour régler les noms au cas par cas.
    * @param string $type type de configuration
    * @return String: nom de l'onglet à afficher
    */
   protected static function getTabNameForConfigType($type) {
      $matches = array();
      if(preg_match('@Plugin([[:upper:]][[:lower:]]+)[[:upper:]][[:lower:]]+@', get_called_class(), $matches)) {
         return $matches[1];
      } else {
         return get_called_class();
      }
   }

   /**
    * Renvoie le message à afficher dans les choix pour l'option 'hériter'.
    * Peut être surchargée pour personnaliser l'affichage.
    * @param string $type le type de configuration dont on hérite
    * @return string le texte à afficher dans les options du paramètre.
    */
   protected static function getInheritFromMessage($type) {
      //CHANGE WHEN ADD CONFIG_TYPE
      switch($type) {
         case self::TYPE_GLOBAL :
            return __('Inherit from global configuration', 'configmanager');
         case self::TYPE_USERENTITY :
            return __('Inherit from user entity configuration', 'configmanager');
         case self::TYPE_ITEMENTITY :
            return __('Inherit from item entity configuration', 'configmanager');
         case self::TYPE_PROFILE :
            return __('Inherit from profile configuration', 'configmanager');
         case self::TYPE_USER :
            return __('Inherit from user preference', 'configmanager');
         default : return false;
      }
   }

   /**
    * Renvoie le message à afficher pour indiquer qu'une règle/config est héritée.
    * Peut être surchargée pour personnaliser l'affichage.
    * @param string $type le type de configuration dont on hérite
    * @return string le texte à afficher
    */
   protected static function getInheritedFromMessage($type) {
      //CHANGE WHEN ADD CONFIG_TYPE
      switch($type) {
         case self::TYPE_GLOBAL :
            return __('Inherited from global configuration', 'configmanager');
         case self::TYPE_USERENTITY :
            return __('Inherited from user entity configuration', 'configmanager');
         case self::TYPE_ITEMENTITY :
            return __('Inherited from item entity configuration', 'configmanager');
         case self::TYPE_PROFILE :
            return __('Inherited from profile configuration', 'configmanager');
         case self::TYPE_USER :
            return __('Inheriteds from user preference', 'configmanager');
         default : return false;
      }
   }
}
