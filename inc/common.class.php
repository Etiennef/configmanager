<?php



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
		return array(self::TYPE_GLOBAL, self::TYPE_USERENTITY, self::TYPE_ITEMENTITY, self::TYPE_PROFILE, self::TYPE_USER);
	}
	
	
	private static $configparams_instance = NULL;
	
	/**
	 * Renvoie le tableau représentant la description de la configuration (la méta-configuration, quelque part)
	 */
	protected final static function getConfigParams() {
		if(! isset(self::$configparams_instance)) {
			self::$configparams_instance = static::makeConfigParams();
		}
		return self::$configparams_instance;
	}
	
	static function makeConfigParams() {
		return array();
	}
	
	
	
	
	/**
	 * Renvoie les types de configurations correspondant à un type d'objet de GLPI
	 * @param string $glpiobjecttype le type de l'objet GLPI (classiquement obtenu via CommonGLPI::getType())
	 * @return array:string tableau des types de configurations possibles
	 */
	protected final static function getTypeForGLPIObject($glpiobjecttype) {
		//CHANGE WHEN ADD GLPI_TYPE
		switch($glpiobjecttype) {
			case 'Config' :
				return array(self::TYPE_GLOBAL);
			case 'Entity' :
				return array(self::TYPE_USERENTITY, self::TYPE_ITEMENTITY);
			case 'Profile' :
				return array(self::TYPE_PROFILE);
			case 'User' :
				return array(self::TYPE_USER);
			case 'Preference' :
				return array(self::TYPE_USER);
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
	 * Fonction générique pour tester les droits sur cette entrée de config. C'est juste une version générlque pour canViewItem et canCreateItem
	 * @param string $right 'r' ou 'w' selon qu'on s'intéresse aux droits en lecture ou en écriture
	 * @return boolean l'utilisateur courant a-t-il le droit demandé
	 */
	protected static final function canItemStatic($type, $type_id, $right) {
		//CHANGE WHEN ADD CONFIG_TYPE
		if(! static::hasFieldsForType($type)) return false;
	
		switch($type) {
			case self::TYPE_GLOBAL :
				return Session::haveRight('config', $right);
			case self::TYPE_USERENTITY :
			case self::TYPE_ITEMENTITY :
				return (new Entity())->can($type_id, $right);
			case self::TYPE_PROFILE :
				return Session::haveRight('profile', $right);
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
	
	/**
	 * Détermine le type_id de la configuration courante pour un type de configuration donné, en tenant compte d'abord du tableau de paramètres, et s'il est absent du tableau en devinant la bonne valeur à partir des informations de session.
	 * @param string $type type de configuration
	 * @param array:string $values tableau indiquant la valeur des paramètres souhaité (les clés sont les $type possibles)
	 * @return type_id à recherche en BDD
	 */
	protected final static function getTypeIdForCurrentConfig($type, $values=array()) {
		if(isset($values[$type])) return $values[$type];
		
		//CHANGE WHEN ADD CONFIG_TYPE
		//TODO faire le tour des endroits où cette fonction est utilisée
		switch($type) {
			case self::TYPE_GLOBAL : return 0;
			case self::TYPE_USERENTITY : return $_SESSION['glpiactive_entity'];
			case self::TYPE_ITEMENTITY : return $_SESSION['glpiactive_entity'];
			case self::TYPE_PROFILE : return $_SESSION['glpiactiveprofile']['id'];
			case self::TYPE_USER : return Session::getLoginUserID();
			default : return false;
		}
	}
	
	/**
	 * Regarde dans la description s'il existe des éléments de configuration pour ce type
	 * @param string $type type de configuration
	 * @return boolean
	 */
	protected static function hasFieldsForType($type) {
		return false;
	}
	
	/**
	 * Définit les onglets à afficher pour les objets auxquels peuvent se ratacher des configurations.
	 * En particulier, teste les différents types de configs pour savoir s'il y a lieu d'afficher un onglet pour l'objet GLPI.
	 * .
	 * @see CommonGLPI::getTabNameForItem()
	 */
	final function getTabNameForItem(CommonGLPI $item, $withtemplate = 0) {
		if(($types = self::getTypeForGLPIObject($item->getType())) === '') {
			return '';
		}
	
		$res = array();
		foreach ($types as $type) {
			if(static::hasFieldsForType($type)) {
				$res[] = static::getTabNameForConfigType($type);
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
		return static::showForm($type, $type_id);
	}
	
	/**
	 * Fonction d'affichage du contenu de l'onglet de configuration pour un item de configuration
	 * @param string $type type de configuration
	 * @param integer $type_id type_id de l'item à afficher
	 * @return boolean true, sauf si une erreur est contatée (ne devrait pas se produire en principe)
	 */
	protected static function showForm($type, $type_id) {
		echo 'This function should have been overridden';
	}

	/**
	 * Détermine le type_id de la (des) configuration(s) rattachée à un objet GLPI
	 * @param CommonGLPI $item l'objet auquel est rattaché la configuration
	 * @return number type_id de la (des) configuration(s) associée
	 */
	protected final static function getTypeIdForGLPIItem(CommonGLPI $item) {
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
	 * Détermine le nom de l'onglet pour un type de configuration donné. Par défaut, c'est le nom de l'objet de configuration donné par getName, mais peut être surchargé pour régler les noms au cas par cas.
	 * @param string $type type de configuration
	 * @return String: nom de l'onglet à afficher
	 */
	private function getTabNameForConfigType($type) {
		return static::getName();
	}
	
	
	/**
	 * Renvoie le titre à afficher dans la page de configuration, pour un type de configuration donné.
	 * Peut être surchargée pour personnaliser l'affichage.
	 * @param unknown $type type de configuration
	 * @return string titre à afficher
	 */
	protected function getConfigPageTitle($type) {
		//CHANGE WHEN ADD CONFIG_TYPE
		switch($type) {
			//TRANS: %s is the plugin name
			case self::TYPE_GLOBAL : return sprintf(__('Global configuration for plugin %s', 'configmanager'), $this->getName());
			//TRANS: %s is the plugin name
			case self::TYPE_USERENTITY : return sprintf(__('User entity configuration for plugin %s', 'configmanager'), $this->getName());
			//TRANS: %s is the plugin name
			case self::TYPE_ITEMENTITY : return sprintf(__('Item entity configuration for plugin %s', 'configmanager'), $this->getName());
			//TRANS: %s is the plugin name
			case self::TYPE_PROFILE : return sprintf(__('Profile configuration for plugin %s', 'configmanager'), $this->getName());
			//TRANS: %s is the plugin name
			case self::TYPE_USER : return sprintf(__('User preference for plugin %s', 'configmanager'), $this->getName());
			default : return false;
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
			case self::TYPE_GLOBAL : return __('Inherit from global configuration', 'configmanager');
			case self::TYPE_USERENTITY : return __('Inherit from user entity configuration', 'configmanager');
			case self::TYPE_ITEMENTITY : return __('Inherit from item entity configuration', 'configmanager');
			case self::TYPE_PROFILE : return __('Inherit from profile configuration', 'configmanager');
			case self::TYPE_USER : return __('Inherit from user preference', 'configmanager');
			default : return false;
		}
	}
	
	/**
	 * Renvoie le message à afficher dans les choix pour l'option 'hériter'.
	 * Peut être surchargée pour personnaliser l'affichage.
	 * @param string $type le type de configuration dont on hérite
	 * @return string le texte à afficher dans les options du paramètre.
	 */
	protected static function getInheritedFromMessage($type) {
		//CHANGE WHEN ADD CONFIG_TYPE
		switch($type) {
			case self::TYPE_GLOBAL : return __('Inherited from global configuration', 'configmanager');
			case self::TYPE_USERENTITY : return __('Inherited from user entity configuration', 'configmanager');
			case self::TYPE_ITEMENTITY : return __('Inherited from item entity configuration', 'configmanager');
			case self::TYPE_PROFILE : return __('Inherited from profile configuration', 'configmanager');
			case self::TYPE_USER : return __('Inheriteds from user preference', 'configmanager');
			default : return false;
		}
	}
	
	/**
	 * Détermine si un paramtère correspond à un choix multiple
	 * @param string $param nom du paramètre
	 * @return boolean
	 */
	protected final static function isMultipleParam($param) {
		$desc = self::getConfigParams()[$param];
		return isset($desc['options']['multiple']) && $desc['options']['multiple'];
	}
}