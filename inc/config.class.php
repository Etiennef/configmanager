<?php

/**
 * Objet générique de gestion de la configuration
 * Prend en compte l'héritage de configuration
 * 
 * 
 * Usage : Définir makeConfigParams, faire le registerclass pour tous les objets intéressants, et le pluginhook
 * 
 * @author Etiennef
 */
class PluginConfigmanagerConfig extends CommonDBTM {
	const TYPE_GLOBAL = 'global';
	const TYPE_ENTITY = 'entity';
	const TYPE_PROFILE = 'profile';
	const TYPE_USER = 'user';
	
	private final static function getAllConfigTypes() {
		//CHANGE WHEN ADD CONFIG_TYPE
		return array(self::TYPE_GLOBAL, self::TYPE_ENTITY, self::TYPE_PROFILE, self::TYPE_USER);
	}
	
	
	//Le fait que la variable commence par un nombre abscon est important : il faut que lorsqu'elle est comparée à un nombre avec ==, elle ne renvoit jamais vrai (ne pas mettre de nombre abscon revenant à mettre 0)
	const INHERIT_VALUE = '965482.5125475__inherit__';
	
	
	
	private static $configparams_instance = NULL;
	
	/**
	 * Réccupére la conficuration courante Fonctionne avec un singleton pour éviter les appels à la bdd inutiles
	 */
	private final static function getConfigParams() {
		if(! isset(self::$configparams_instance)) {
			self::$configparams_instance = static::makeConfigParams();
		}
		return self::$configparams_instance;
	}
	
	static function makeConfigParams() {
		return array();
	}
	

	/**
	 * Création des tables liées à cet objet. Utilisée lors de l'installation du plugin
	 */
	public final static function install() {
		global $DB;
		$table = self::getTable();
		$request = '';
		
		$query = "CREATE TABLE `$table` (
					`" . self::getIndexName() . "` int(11) NOT NULL AUTO_INCREMENT,
					`config__type` varchar(50) collate utf8_unicode_ci NOT NULL,
					`config__type_id` int(11) collate utf8_unicode_ci NOT NULL,";
		
		foreach(self::getConfigParams() as $param => $desc) {
			$query .= "`$param` " . $desc['dbtype'] . " collate utf8_unicode_ci,";
		}
		
		$query .= "PRIMARY KEY  (`" . self::getIndexName() . "`)
				) ENGINE=MyISAM  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci";
		
		if(! TableExists($table)) {
			$DB->queryOrDie($query, $DB->error());
		}
	}

	/**
	 * Suppression des tables liées à cet objet. Utilisé lors de la désinstallation du plugin
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

	private final static function hasFieldsForType($type) {
		foreach(self::getConfigParams() as $param => $desc) {
			if(in_array($type, $desc['types'])) return true;
		}
		return false;
	}

	private final static function getTypeForGLPIObject($glpiobjecttype) {
		//CHANGE WHEN ADD GLPI_TYPE
		switch($glpiobjecttype) {
			case 'Config' :
				return self::TYPE_GLOBAL;
			case 'Entity' :
				return self::TYPE_ENTITY;
			case 'Profile' :
				return self::TYPE_PROFILE;
			case 'User' :
				return self::TYPE_USER;
			case 'Preference' :
				return self::TYPE_USER;
			default :
				return '';
		}
	}

	private final function createEmpty($type, $type_id = 0) {
		if($type == self::TYPE_GLOBAL) $type_id = 0;
		
		$input = array();
		foreach(self::getConfigParams() as $param => $desc) {
			$pos = array_search($type, $desc['types']);
			if($pos !== false && ! isset($desc['types'][$pos + 1])) {
				$input[$param] = $desc['default'];
			} else {
				$input[$param] = self::INHERIT_VALUE;
			}
		}
		
		$input['config__type'] = $type;
		$input['config__type_id'] = $type_id;
		$id = $this->add($input);
		$this->getFromDB($id);
	}

	static final function canView() {
		return true;
	}

	static final function canCreate() {
		return true;
	}

	private final function canItem($right) {
		//CHANGE WHEN ADD CONFIG_TYPE
		if(! self::hasFieldsForType($this->fields['config__type'])) return false;
		
		switch($this->fields['config__type']) {
			case self::TYPE_GLOBAL :
				return Session::haveRight('config', $right);
			case self::TYPE_ENTITY :
				return (new Entity())->can($this->fields['config__type_id'], $right);
			case self::TYPE_PROFILE :
				return Session::haveRight('profile', $right);
			case self::TYPE_USER :
				return Session::getLoginUserID() == $this->fields['config__type_id'] ||
						 (new User())->can($this->fields['config__type_id'], $right);
			default :
				return false;
		}
	}
	
	final function canViewItem() {
		return $this->canItem('r');
	}

	final function canCreateItem() {
		return $this->canItem('w');
	}
	

	final function prepareInputForUpdate($input) {
		foreach(self::getConfigParams() as $param => $desc) {
			if(isset($input[$param]) && self::isMultipleParam($param)) {
				if(in_array(self::INHERIT_VALUE, $input[$param])) {
					if(count($input[$param]) > 1) {
						//TODO personnaliser pour l'option
						Session::addMessageAfterRedirect(__('Warning, you defined the inherit option together with other option. Only inherit will but taken into account', 'configmanager'), false, ERROR);
					}
					$input[$param] = self::INHERIT_VALUE;
				} else {
					$input[$param] = exportArrayToDB($input[$param]);
				}
			}
		}
		return $input;
	}

	
	private static $configValues_instance = NULL;
	/**
	 * Réccupére la conficuration courante Fonctionne avec un singleton pour éviter les appels à la bdd inutiles
	 */
	public final static function getConfigValues() {
		//TODO permettre de charger la config en préréglant certains champs
		if(! isset(self::$configValues_instance)) {
			self::$configValues_instance = self::readFromDB();
		}
		return self::$configValues_instance;
	}
	
	private final static function getTypeIdForCurrentConfig($type) {
		//CHANGE WHEN ADD CONFIG_TYPE
		switch($type) {
			case self::TYPE_GLOBAL : return 0;
			case self::TYPE_ENTITY : return $_SESSION['glpiactive_entity'];
			case self::TYPE_PROFILE : return $_SESSION['glpiactiveprofile']['id'];
			case self::TYPE_USER : return Session::getLoginUserID();
			default : return false;
		}
	}
	
	/**
	 * Calcul de la configuration applicable dans la situation actuelle, en tenant compte des différents héritages.
	 * @return array tableau de valeurs de configuration à appliquer
	 */
	private final static function readFromDB() {
		$configObject = new static();
		
		// lit dans la DB les configs susceptibles de s'appliquer
		$configTable = array();
		foreach(self::getAllConfigTypes() as $type) {
			$type_id = self::getTypeIdForCurrentConfig($type);
			if($configObject->getFromDBByQuery("WHERE `config__type`='$type' AND `config__type_id`='$type_id'")) {
				$configTable[$type] = $configObject->fields;
			}
		}
		
		// Pour chaque paramètre, cherche la config qui s'applique en partant de celle qui écrase les autres
		$config = array();
		foreach(self::getConfigParams() as $param => $desc) {
			for($i = 0, $current = self::INHERIT_VALUE ; $current == self::INHERIT_VALUE ; $i ++) {
				if(isset($configTable[$desc['types'][$i]])) {
					$current = $configTable[$desc['types'][$i]][$param];
				}
			}
			
			if(self::isMultipleParam($param)) {
				$config[$param] = importArrayFromDB($current);
			} else {
				$config[$param] = $current;
			}
			
		}
		
		return $config;
	}

	final function getTabNameForItem(CommonGLPI $item, $withtemplate = 0) {
		$type = self::getTypeForGLPIObject($item->getType());
		if($type != '' && self::hasFieldsForType($type)) {
			return $this->getName();
		} else {
			return '';
		}
	}

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
	
	final static function displayTabContentForItem(CommonGLPI $item, $tabnum = 1, $withtemplate = 0) {
		$type = self::getTypeForGLPIObject($item->getType());
		$type_id = self::getTypeIdForGLPIItem($item);
		
		$config = new static();
		if(! $config->getFromDBByQuery("WHERE `config__type`='$type' AND `config__type_id`='$type_id'")) {
			$config->createEmpty($type, $type_id);
		}
		return $config->showForm();
	}

	/**
	 * Fonction qui affiche le formulaire de configuration du plugin
	 */
	final function showForm() {
		if(! $this->can($this->getID(), 'r')) {
			return false;
		}
		$can_write = $this->can($this->getID(), 'w');
		
		if($can_write) {
			echo '<form action="' . $this->getFormURL() . '" method="post">';
		}
		
		echo '<table class="tab_cadre_fixe">';
		echo '<tr><th colspan="2" class="center b">';
		echo static::getConfigPageTitle($this->fields['config__type'], static::getName());
		echo '</th></tr>';
		
		foreach(self::getConfigParams() as $param => $desc) {
			$pos = array_search($this->fields['config__type'], $desc['types']);
			
			if($pos !== false) {
				$options = isset($desc['options']) ? $desc['options'] : array();
				$choices = $desc['values'];
				
				if(isset($desc['types'][$pos + 1])) {
					//TODO En fait pas parfait, parce que si le niveau d'au-dessus hérite lui aussi, en fait c'est pas bon
					// Idéalement, il faudrait aller chercher à quel niveau on hérite (ce qui permettrait au passage de connaitre et afficher la valeur héritée)
					$choices[self::INHERIT_VALUE] = static::getInheritFromMessage($desc['types'][$pos+1]);
				}
				
				if($this->fields[$param] != self::INHERIT_VALUE && self::isMultipleParam($param)) {
					$options['values'] = importArrayFromDB($this->fields[$param]);
				} else {
					$options['values'] = array($this->fields[$param]);
				}
				
				echo "<tr class='tab_bg_2'>";
				echo "<td>" . $desc['text'] . "</td><td>";
				if($can_write) {
					Dropdown::showFromArray($param, $choices, $options);
				} else {
					foreach($options['values'] as $value) {
						echo $choices[$value] . '</br>';
					}
				}
				echo "</td></tr>";
			}
		}
		
		if($can_write) {
			echo '<tr class="tab_bg_1">';
			echo '<td class="center" colspan="2">';
			echo '<input type="hidden" name="id" value="' . $this->getID() . '">';
			echo '<input type="submit" name="update"' . _sx('button', 'Upgrade') . ' class="submit">';
			echo '</td></tr>';
		}
		echo '</table>';
		Html::closeForm();
	}
	
	private final static function isMultipleParam($param) {
		$desc = self::getConfigParams()[$param];
		return isset($desc['options']) && isset($desc['options']['multiple']) && $desc['options']['multiple'];
	}
	
	
	private static function getConfigPageTitle($type, $pluginName) {
		//CHANGE WHEN ADD CONFIG_TYPE
		switch($type) {
			case self::TYPE_GLOBAL : return __('Global configuration for plugin ', 'configmanager') . $pluginName;
			case self::TYPE_ENTITY : return __('Entity configuration for plugin ', 'configmanager') . $pluginName;
			case self::TYPE_PROFILE : return __('Profile configuration for plugin ', 'configmanager') . $pluginName;
			case self::TYPE_USER : return __('User preference for plugin ', 'configmanager') . $pluginName;
			default : return false;
		}
	}
	
	/**
	 * Get the message displayed as the 'inherit' option in dropdowns.
	 * @param string $type the configtype we inherit from
	 * @return translated|boolean The text to display, or false if $type is unknown
	 */
	private static function getInheritFromMessage($type) {
		//CHANGE WHEN ADD CONFIG_TYPE
		switch($type) {
			case self::TYPE_GLOBAL : return __('Inherit from global configuration', 'configmanager');
			case self::TYPE_ENTITY : return __('Inherit from entity configuration', 'configmanager');
			case self::TYPE_PROFILE : return __('Inherit from profile configuration', 'configmanager');
			case self::TYPE_USER : return __('Inherit from user preference', 'configmanager');
			default : return false;
		}
	}
	
}
?>


























