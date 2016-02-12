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
	const TYPE_USERENTITY = 'userentity';
	const TYPE_ITEMENTITY = 'itementity';
	const TYPE_PROFILE = 'profile';
	const TYPE_USER = 'user';
	
	private final static function getAllConfigTypes() {
		//CHANGE WHEN ADD CONFIG_TYPE
		return array(self::TYPE_GLOBAL, self::TYPE_USERENTITY, self::TYPE_ITEMENTITY, self::TYPE_PROFILE, self::TYPE_USER);
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
			case self::TYPE_USERENTITY :
			case self::TYPE_ITEMENTITY :
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
						//TRANS: %s is the description of the option
						$msg = sprintf(__('Warning, you defined the inherit option together with one or more other options for the parameter "%s".', 'configmanager'), $desc['text']);
						$msg .= ' ' . __('Only the inherit option will be taken into account', 'configmanager');
						
						Session::addMessageAfterRedirect($msg, false, ERROR);
					}
					$input[$param] = self::INHERIT_VALUE;
				} else {
					$input[$param] = exportArrayToDB($input[$param]);
				}
			}
		}
		return $input;
	}

	
	private static $configValues_instance = array();
	/**
	 * Réccupére la conficuration courante Fonctionne avec un singleton pour éviter les appels à la bdd inutiles
	 */
	public final static function getConfigValues($values=array()) {
		// $key représentant le tableau de paramètres
		ksort($values);
		$key = json_encode($values);
		
		if(! isset(self::$configValues_instance[$key])) {
			self::$configValues_instance[$key] = self::readFromDB($values);
		}
		return self::$configValues_instance[$key];
	}
	
	private final static function getTypeIdForCurrentConfig($type, $values) {
		//CHANGE WHEN ADD CONFIG_TYPE
		switch($type) {
			case self::TYPE_GLOBAL : return 0;
			case self::TYPE_USERENTITY : return $_SESSION['glpiactive_entity'];
			case self::TYPE_ITEMENTITY : return isset($values['itementity'])?$values['itementity']:false;
			case self::TYPE_PROFILE : return $_SESSION['glpiactiveprofile']['id'];
			case self::TYPE_USER : return Session::getLoginUserID();
			default : return false;
		}
	}
	
	/**
	 * Calcul de la configuration applicable dans la situation actuelle, en tenant compte des différents héritages.
	 * @return array tableau de valeurs de configuration à appliquer
	 */
	private final static function readFromDB($values) {
		$configObject = new static();
		
		// lit dans la DB les configs susceptibles de s'appliquer
		$configTable = array();
		foreach(self::getAllConfigTypes() as $type) {
			$type_id = self::getTypeIdForCurrentConfig($type, $values);
			if($type_id!==false && $configObject->getFromDBByQuery("WHERE `config__type`='$type' AND `config__type_id`='$type_id'")) {
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
		if(($types = self::getTypeForGLPIObject($item->getType())) === '') {
			return '';
		}
	
		$res = array();
		foreach ($types as $type) {
			if(self::hasFieldsForType($type)) {
				$res[] = static::getTabNameForConfigType($type);
			}
		}
	
		return $res;
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
	
	private function getTabNameForConfigType($type) {
		return static::getName();
	}
	
	
	final static function displayTabContentForItem(CommonGLPI $item, $tabnum = 1, $withtemplate = 0) {
		$type = self::getTypeForGLPIObject($item->getType())[$tabnum];
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
			
			$inheritText = isset($desc['types'][$pos + 1])?static::getInheritFromMessage($desc['types'][$pos+1]):'';
			
			if($pos !== false) {
				
				echo "<tr class='tab_bg_2'>";
				echo "<td>" . $desc['text'] . "</td><td>";
				
				if(is_array($desc['values'])) {
					self::showDropdown($param, $desc, $inheritText, $can_write);
				} else {
					self::showTextInput($param, $desc, $inheritText, $can_write);
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
	
	private final function showDropdown($param, $desc, $inheritText, $can_write) {
		$doesinherit = $this->fields[$param] === self::INHERIT_VALUE;
		
		$options = isset($desc['options']) ? $desc['options'] : array();
		
		$choices = $desc['values'];
		if($inheritText) $choices[self::INHERIT_VALUE] = $inheritText;
		
		if($this->fields[$param] != self::INHERIT_VALUE && self::isMultipleParam($param)) {
			$options['values'] = importArrayFromDB($this->fields[$param]);
		} else {
			$options['values'] = array($this->fields[$param]);
		}
		
		if($can_write) {
			Dropdown::showFromArray($param, $choices, $options);
		} else {
			if($doesinherit) {
				echo $inheritText;
			} else {
				foreach($options['values'] as $value) {
					if(isset($choices[$value])) { //test certes contreintuitif, mais nécessaire pour gérer le fait que la liste de choix puisse être variable selon les droits de l'utilisateur.
						echo $choices[$value] . '</br>';
					}
				}
			}
		}
	}
	
	
	private final function showTextInput($param, $desc, $inheritText, $can_write) {
		$size = isset($desc['options']['size']) ? $desc['options']['size'] : 50;
		$maxlength = isset($desc['options']['maxlength']) ? $desc['options']['maxlength'] : 250;
		$value = $this->fields[$param];
		$doesinherit = $value === self::INHERIT_VALUE;
		
		if($can_write) {
			if($inheritText !== '') {
				// L'héritage est géré en mettant 2 champs, un caché, l'autre affiché, et en désactivant celui qui n'est pas pertinent.
				// Un checkbox permet de choisir entre les deux
				
				$chkid = 'configmanager_checkbox_inherit_'.$param;
				$txtid = 'configmanager_text_inherit_'.$param;
				
				echo $inheritText . ' <input type="checkbox" id="'.$chkid.'" '. ($doesinherit ? 'checked' : '') .'><br>';
				echo '<input type="hidden" id="'.$txtid.'_inherit" value="'.self::INHERIT_VALUE.'" name="'.$param.'" size="'.$size.'" maxlength="'.$maxlength.'" '. (!$doesinherit ? 'disabled' : '') .'>';
				echo '<input type="text" id="'.$txtid.'_value" value="'. ($doesinherit ? '' : $value) .'" name="'.$param.'" size="'.$size.'" maxlength="'.$maxlength.'" '. ($doesinherit ? 'disabled' : '') .'>';
				
				// Ajout du script permettant de basculer l'activation des champs de saisie
				echo "<script>
					Ext.get($chkid).addListener('change',function(ev, el){
						var todisable = el.checked ? '{$txtid}_value' : '{$txtid}_inherit';
						var toenable = el.checked ? '{$txtid}_inherit' : '{$txtid}_value';
						
						Ext.get(todisable).set({'disabled' : ''});
						Ext.get(toenable).set({'disabled' : null}, false);
					});
				</script>";
				
			} else {
				echo '<input type="text" name="'.$param.'" value="'.$value.'" size="'.$size.'" maxlength="'.$maxlength.'">';
			}
		} else {
			if($doesinherit) {
				echo $inheritText;
			} else {
				echo $value;
			}
		}
	}
	
	
	
	private final static function isMultipleParam($param) {
		$desc = self::getConfigParams()[$param];
		return isset($desc['options']) && isset($desc['options']['multiple']) && $desc['options']['multiple'];
	}
	
	
	private static function getConfigPageTitle($type, $pluginName) {
		//CHANGE WHEN ADD CONFIG_TYPE
		switch($type) {
			//TRANS: %s is the plugin name
			case self::TYPE_GLOBAL : return sprintf(__('Global configuration for plugin %s', 'configmanager'), $pluginName);
			//TRANS: %s is the plugin name
			case self::TYPE_USERENTITY : return sprintf(__('User entity configuration for plugin %s', 'configmanager'), $pluginName);
			//TRANS: %s is the plugin name
			case self::TYPE_ITEMENTITY : return sprintf(__('Item entity configuration for plugin %s', 'configmanager'), $pluginName);
			//TRANS: %s is the plugin name
			case self::TYPE_PROFILE : return sprintf(__('Profile configuration for plugin %s', 'configmanager'), $pluginName);
			//TRANS: %s is the plugin name
			case self::TYPE_USER : return sprintf(__('User preference for plugin %s', 'configmanager'), $pluginName);
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
			case self::TYPE_USERENTITY : return __('Inherit from user entity configuration', 'configmanager');
			case self::TYPE_ITEMENTITY : return __('Inherit from item entity configuration', 'configmanager');
			case self::TYPE_PROFILE : return __('Inherit from profile configuration', 'configmanager');
			case self::TYPE_USER : return __('Inherit from user preference', 'configmanager');
			default : return false;
		}
	}
	
}
?>


























