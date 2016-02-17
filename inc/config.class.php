<?php

/**
 * Objet générique de gestion de la configuration, offrant la possibilité d'avoir plusieurs niveaux de paramétrage, avec héritage entre les niveaux et surcharge si nécessaire. Cet objet a vocation à être utilisé en définissant un objet qui en hérite, et en redéfinissant certaines fonctions.
 * Dans le modèle de données, cet objet représente une série de paramètre de configuration, pour un seul type de configuration (générale OU utilisateur...). La configuration dans un contexte donné pour un utilisateur correspond donc à un croisement (tenant compte des surcharges) de plusieurs objets PluginConfigmanagerConfig de différents types (mais au plus un seul de chaque type).
 * Chaque objet PluginConfigmanagerConfig est instancié à la volée quand on essaie d'y accéder en écriture. L'absence de l'objet est considérée comme équivalente à un héritage de l'objet du niveau de dessus, ou à la valeur par défaut s'il n'y a pas de niveau de dessus.
 * 
 * @author Etiennef
 */
class PluginConfigmanagerConfig extends PluginConfigmanagerCommon {
	//Le fait que la variable commence par un nombre abscon est important : il faut que lorsqu'elle est comparée à un nombre avec ==, elle ne renvoit jamais vrai (ne pas mettre de nombre abscon revenant à mettre 0)
	const INHERIT_VALUE = '965482.5125475__inherit__';
	
	/**
	 * Regarde dans la description s'il existe des éléments de configuration pour ce type
	 * @param string $type type de configuration
	 * @return boolean
	 */
	protected final static function hasFieldsForType($type) {
		foreach(self::getConfigParams() as $param => $desc) {
			if($desc['type'] === 'readonly text') continue;
			if(in_array($type, $desc['types'])) return true;
		}
		return false;
	}

	
	
	
	
	/**
	 * Lit la configuration pour un item de configuration donné, sans tenir compte de l'héritage.
	 * Fonctionne avec un jeu de singletons pour éviter les appels à la base inutiles.
	 * @param string $type type de configuration
	 * @param integer $type_id type_id de l'item à lire
	 * @return array tableau représentant la configuration (brut de BDD) ou false si aucune config n'est trouvée
	 */
	private final static function getFromDBStaticNoInherit($type, $type_id) {
		static $_configs_instances = array();
		
		if(! isset($_configs_instances[get_called_class()][$type][$type_id])) {
			if(!isset($_configs_instances[get_called_class()])) $_configs_instances[get_called_class()] = array();
			if(!isset($_configs_instances[get_called_class()][$type])) $_configs_instances[get_called_class()][$type] = array();
			
			$config = new static();
			if($config->getFromDBByQuery("WHERE `config__type`='$type' AND `config__type_id`='$type_id'")) {
				$_configs_instances[get_called_class()][$type][$type_id] = $config->fields;
			} else {
				$_configs_instances[get_called_class()][$type][$type_id] = false;
			}
		}
		return $_configs_instances[get_called_class()][$type][$type_id];
	}

	/**
	 * Lit l'état de la configuration à appliquer, en tenant compte de l'héritage
	 * Fonctionne avec un jeu de singletons pour éviter les appels à la base inutiles.
	 * @param array(string) $values valeurs de type_id à utiliser pour lire les configurations héritées (devinées si non précisées)
	 * @return array tableau représentant la configuration
	 */
	public final static function getConfigValues($values=array()) {
		$configObject = new static();
	
		// lit dans la DB les configs susceptibles de s'appliquer
		$configTable = array();
		foreach(self::getAllConfigTypes() as $type) {
			$type_id = self::getTypeIdForCurrentConfig($type, $values);
			if($tmp = self::getFromDBStaticNoInherit($type, $type_id)) {
				$configTable[$type] = $tmp;
			}
		}
		
		// Pour chaque paramètre, cherche la config qui s'applique en partant de celle qui écrase les autres
		$config = array();
		foreach(self::getConfigParams() as $param => $desc) {
			if($desc['type'] === 'readonly text') continue;
			
			for($i = 0, $current = self::INHERIT_VALUE ; $current == self::INHERIT_VALUE ; $i ++) {
				if(isset($configTable[$desc['types'][$i]])) {
					$current = $configTable[$desc['types'][$i]][$param];
				} else if(!isset($desc['types'][$i+1])) {
					// cas où on n'a pas trouvé de valeur, mais où on est au dernier niveau
					$current = $desc['default'];
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
	

	
	
	/**
	 * Gère la transformation des inputs multiples en quelque chose d'inserable dans la base (en l'occurence une chaine json).
	 * .
	 * @see CommonDBTM::prepareInputForAdd()
	 */
	final function prepareInputForAdd($input) {
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

	
	
	

	/**
	 * Calcule la valeur des paramètres par défaut pour un item de configuration (hérite pour ce qui hérite, valeur par défaut pour ce qui n'hérite pas).
	 * @param string $type type de configuration
	 * @param number $type_id id de l'objet correspondant (sera écrasé à 0 si $type = TYPE_GLOBAL)
	 */
	private static final function makeEmptyConfig($type, $type_id) {
		if($type == self::TYPE_GLOBAL) $type_id = 0;
	
		$input = array();
		foreach(self::getConfigParams() as $param => $desc) {
			if($desc['type'] === 'readonly text') continue;
			
			$pos = array_search($type, $desc['types']);
			if($pos !== false && ! isset($desc['types'][$pos + 1])) {
				$input[$param] = $desc['default'];
			} else {
				$input[$param] = self::INHERIT_VALUE;
			}
		}
	
		$input['config__type'] = $type;
		$input['config__type_id'] = $type_id;
		$input['id'] = -1;
		return $input;
	}
	

	protected static final function showFormStatic($type, $type_id) {
		if(! self::canItemStatic($type, $type_id, 'r')) {
			return false;
		}
		$can_write = self::canItemStatic($type, $type_id, 'w');
		
		// lecture des données à afficher
		$config = self::getFromDBStaticNoInherit($type, $type_id);
		
		//Préparation de données 'vides' pour une création
		if($config === false) {
			$config = self::makeEmptyConfig($type, $type_id);
		}
		
		
		// Entêtes du formulaire
		if($can_write) {
			echo '<form action="' . PluginConfigmanagerConfig::getFormURL() . '" method="post">';
		}
		
		echo '<table class="tab_cadre_fixe">';
		echo '<tr><th colspan="2" class="center b">';
		echo static::getConfigPageTitle($type);
		echo '</th></tr>';
		
		//Affichage de la configuration
		foreach(self::getConfigParams() as $param => $desc) {
			$pos = array_search($type, $desc['types']);
			
			$inheritText = isset($desc['types'][$pos + 1])?static::getInheritFromMessage($desc['types'][$pos+1]):'';
			
			if($pos !== false) {
				$tooltip = isset($desc['tooltip'])?(' title="'.$desc['tooltip'].'"'):'';
				
				switch($desc['type']) {
					case 'dropdown' : 
						echo '<tr' . $tooltip . '><td>' . $desc['text'] . '</td><td>';
						self::showDropdown($param, $desc, $config[$param], $can_write, $inheritText);
						echo '</td></tr>';
						break;
					case 'text input' :
						echo '<tr' . $tooltip . '><td>' . $desc['text'] . '</td><td>';
						self::showTextInput($param, $desc, $config[$param], $can_write, $inheritText);
						echo '</td></tr>';
						break;
					case 'text input' :
						echo '<tr>' . $desc['text'] . '<tr>';
						break;
				}
				
			}
		}

		// Affichage du 'bas de formulaire' (champs cachés et bouton)
		if($can_write) {
			echo '<tr>';
			echo '<td class="center" colspan="2">';
			echo '<input type="hidden" name="id" value="' . $config[self::getIndexName()] . '">';
			echo '<input type="hidden" name="config__type" value="' . $config['config__type'] . '">';
			echo '<input type="hidden" name="config__type_id" value="' . $config['config__type_id'] . '">';
			echo '<input type="hidden" name="config__object_name" value="' . get_called_class() . '">';
			echo '<input type="submit" name="update" value="' . _sx('button', 'Save') . '" class="submit">';
			echo '</td></tr>';
		}
		echo '</table>';
		Html::closeForm();
	}
	
	
	/**
	 * Fonction d'affichage d'un champs de saisie via dropdown
	 * @param unknown $param nom du paramètre à afficher
	 * @param unknown $desc description de la configuration de ce paramètre
	 * @param unknown $inheritText texte à afficher pour le choix 'hériter', ou '' si l'héritage est impossible pour cette option
	 * @param unknown $can_write vrai ssi on doit afficher un menu sélectionnable, sinon on affiche juste le texte.
	 */
	private static final function showDropdown($param, $desc, $value, $can_write, $inheritText) {
		$doesinherit = $value === self::INHERIT_VALUE;
		
		$options = isset($desc['options']) ? $desc['options'] : array();
		
		$choices = $desc['values'];
		if($inheritText) $choices[self::INHERIT_VALUE] = $inheritText;
		
		if($value != self::INHERIT_VALUE && self::isMultipleParam($param)) {
			$options['values'] = importArrayFromDB($value);
		} else {
			$options['values'] = array($value);
		}
		
		if($can_write) {
			Dropdown::showFromArray($param, $choices, $options);
		} else {
			if($doesinherit) {
				echo $inheritText;
			} else {
				foreach($options['values'] as $val) {
					if(isset($choices[$val])) { //test certes contreintuitif, mais nécessaire pour gérer le fait que la liste de choix puisse être variable selon les droits de l'utilisateur.
						echo $choices[$val] . '</br>';
					}
				}
			}
		}
	}
	

	/**
	 * Fonction d'affichage d'un champs de saisie texte libre
	 * @param unknown $param nom du paramètre à afficher
	 * @param unknown $desc description de la configuration de ce paramètre
	 * @param unknown $inheritText texte à afficher pour le choix 'hériter', ou '' si l'héritage est impossible pour cette option
	 * @param unknown $can_write vrai ssi on doit afficher un input éditable, sinon on affiche juste le texte.
	 */
	private final static function showTextInput($param, $desc, $value, $can_write, $inheritText) {
		$size = isset($desc['options']['size']) ? $desc['options']['size'] : 50;
		$maxlength = isset($desc['options']['maxlength']) ? $desc['options']['maxlength'] : 250;
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
	
	
}
?>


























