<?php
/**
 * This class contains functions that are common beween config and rules.
 * No direct use
 */
class PluginConfigmanagerCommon extends CommonDBTM {
   const TYPE_GLOBAL = 'global';
   const TYPE_USERENTITY = 'userentity';
   const TYPE_ITEMENTITY = 'itementity';
   const TYPE_PROFILE = 'profile';
   const TYPE_USER = 'user';

   /**
    * Return an array of all possible types
    * @return string[]
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
    * Return array representing the config description (meta-configuration, sort of)
    * Works as a singleton, meta-config is build once for each subclass (at most)
    * @return array
    */
   protected final static function getConfigParams() {
      static $configparams_instance = array();
      // This array is mandatory because this static var is share between every class inheriting from this one
      // It stores the config for each subclass
      if(! isset($configparams_instance[get_called_class()])) {
         $configparams_instance[get_called_class()] = static::makeConfigParams();
      }
      return $configparams_instance[get_called_class()];
   }

   /**
    * Builds the metaconfiguration.
    * This function should be overridden.
    * Bear in mind that the plugin will call it once and cache the result
    * @return array
    */
   static function makeConfigParams() {
      return array();
   }

   /**
    * Create DB table required for the configuration storage.
    * Has to be used when the plugin is installed
    * @param $additionnal_param string table colomn to add
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
    * Drop DB table related to this configuration/rules.
    * Has to be used when the plugin is uninstalled
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
    * Returns configuration types corresponding to a GLPI type
    * Note to self : numeric keys are important, because they are needed in displayTabContentForItem, to determine which config corresponds to a tab
    * @param string $glpiobjecttype GLPI object type (used by CommonGLPI::getType())
    * @return string[] array of corresponding configuration types
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

    /**
    * @inheritdoc
    */
    static final function canView() {
      return true;
   }

   /**
    * @inheritdoc
    */
   static final function canCreate() {
      return true;
   }

   /**
    * Generic function testing right on a single configuration line or rule.
    * It's only a generic version of canViewItem and canCreateItem
    * @param string $type Configuration type
    * @param integer $type_id id GLPI object related to this configuration line/rule
    * @param string $right 'r' or 'w' for read or write right
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

   /**
    * @inheritdoc
    */
   final function canViewItem() {
      return self::canItemStatic($this->fields['config__type'], $this->fields['config__type_id'], 'r');
   }

   /**
    * @inheritdoc
    */
   final function canCreateItem() {
      return self::canItemStatic($this->fields['config__type'], $this->fields['config__type_id'], 'w');
   }

    /**
    * @inheritdoc
    */
    final function canUpdateItem() {
      return self::canItemStatic($this->fields['config__type'], $this->fields['config__type_id'], 'r');
   }

   /**
    * Forbids change of some params on config update.
    * This has to be done to avoid a malicious user to transform a config he is allowed to write into a config he shouldn't (canUpdate is checked before the actual update)
    * @see CommonDBTM::prepareInputForUpdate()
    */
   function prepareInputForUpdate($input) {
      // ce sont des champs qu'il est interdit de modifier
      unset($input['config__type']);
      unset($input['config__type_id']);

      return $input;
   }

   /**
    * Returns the id of the pertinent GLPI object id related to a given type.
    * - from an array
    * - or guessed from context (current entity, profile...)
    * Most of the time, context is ok, but array can be used to force the choice, or to provide a result when context is unsure.
    * @param string $type Configuration type
    * @param string[] $values Array with prefered values (keys are configuration types for which user has a preference)
    * @return pertinent GLPI object id to use
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
    * Check the meta-configuration if a config exists for this configuration type
    * @param string $type configuration type
    * @return boolean
    */
   protected static function hasFieldsForType($type) {
      echo 'This function should have been overridden';
   }

   /**
    * Define tabs to display in a CommonGLPI object.
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
    * Display config or rule tab content, for a given GLPI object, and tab n°
    *
    * @param CommonGLPI $item GLPI object to which the config to display is associated
    * @param number $tabnum tab number (allows to find which config tab we display in case there are several config type for the GLPI type)
    * @param number $withtemplate comes from GLPI, unused for us
    * @return boolean true, except in case of error (not really likely here)
    */
   final static function displayTabContentForItem(CommonGLPI $item, $tabnum = 1, $withtemplate = 0) {
      $type = self::getTypeForGLPIObject($item->getType())[$tabnum];
      $type_id = self::getTypeIdForGLPIItem($item);
      return static::showFormStatic($type, $type_id);
   }

   /**
    * Display config or rule tab content, for a given config type and id
    *
    * @param string $type configuration type
    * @param integer $type_id configuration type id
    * @return boolean true, except in case of error (not really likely here)
    */
   protected static function showFormStatic($type, $type_id) {
      echo 'This function should have been overridden';
   }

   /**
    * Determine configuration type_id cannonicly attached to a given GLPI object
    * @param CommonGLPI $item GLPI object the config is attached to
    * @return number type_id of the config
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
    * Determine the title of the config tab.
    * Defaults to the plugion name, but can be overridden by the user.
    * @param string $type configuration type
    * @return String title of the tab
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
    * Get the message to display too chose the 'inherit' option (in dropdown for example)
    * Can be overridden.
    * @param string $type configuration type from which it would be inherited
    * @return string
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
    * Get the message to display too indicate a configuration/rule is inherited
    * Can be overridden.
    * @param string $type configuration type the config is inherited from
    * @return string
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
