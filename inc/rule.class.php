<?php

/**
 * Objet générique de gestion de la configuration, offrant la possibilité d'avoir plusieurs niveaux de paramétrage, avec héritage entre les niveaux et surcharge si nécessaire. Cet objet a vocation à être utilisé en définissant un objet qui en hérite, et en redéfinissant certaines fonctions.
 * Dans le modèle de données, cet objet représente une série de paramètre de configuration, pour un seul type de configuration (générale OU utilisateur...). La configuration dans un contexte donné pour un utilisateur correspond donc à un croisement (tenant compte des surcharges) de plusieurs objets PluginConfigmanagerConfig de différents types (mais au plus un seul de chaque type).
 * Chaque objet PluginConfigmanagerConfig est instancié à la volée quand on essaie d'y accéder en écriture. L'absence de l'objet est considérée comme équivalente à un héritage de l'objet du niveau de dessus, ou à la valeur par défaut s'il n'y a pas de niveau de dessus.
 * 
 * @author Etiennef
 */


/*
 * $params, les clés de $values, $size, $maxlength doivent êter htmlentities
 */


class PluginConfigmanagerRule extends PluginConfigmanagerCommon {
	const NEW_ID_TAG = '__newid__';
	const NEW_ORDER_TAG = '__neworder__';
	
	/**
	 * Description de l'ordre dans lequel l'héritage des règles se déroule
	 * Dans l'ordre, le premier hérite du second, etc...
	 * Doit être surchargé
	 */
	protected static $inherit_order = array();
	
	protected final static function hasFieldsForType($type) {
		return in_array($type, static::$inherit_order);
	} // Note: la fonction n'est pas utilisée dans cette classe, mais elle est appellée depuis common.class
	
	/**
	 * Création des tables liées à cet objet. Utilisée lors de l'installation du plugin
	 * @param $additionnal_param string colonne à ajouter dans la table
	 */
	public final static function install($additionnal_param='') {
		parent::install("`config__order` int(11) collate utf8_unicode_ci NOT NULL,");
	}
	
	
	
	/**
	 * Lit un jeu de règle pour un item de configuration donné, sans tenir compte de l'héritage.
	 * Fonctionne avec un jeu de singletons pour éviter les appels à la base inutiles.
	 * @param string $type type de configuration
	 * @param integer $type_id type_id de l'item à lire
	 * @return array tableau représentant le jeu de règle (brut de BDD)
	 */
	private final static function getFromDBStaticNoInherit($type, $type_id) {
		static $_rules_instances = array();
		
		if(! isset($_rules_instances[get_called_class()][$type][$type_id])) {
			if(!isset($_rules_instances[get_called_class()])) $_rules_instances[get_called_class()] = array();
			if(!isset($_rules_instances[get_called_class()][$type])) $_rules_instances[get_called_class()][$type] = array();
			
			$_rules_instances[get_called_class()][$type][$type_id] = (new static())->find("`config__type`='$type' AND `config__type_id`='$type_id'", "config__order");
		}
		return $_rules_instances[get_called_class()][$type][$type_id];
	}
	
	/**
	 * Lit un jeu de règle pour un item de configuration donné, en tenant compte de l'héritage (mais seulement à partir du type donné en argument).
	 * Fonctionne avec un jeu de singletons pour éviter les appels à la base inutiles.
	 * @param string $type type de configuration
	 * @param integer $type_id type_id de l'item à lire
	 * @param array(string) $values valeurs de type_id à utiliser pour lire les règles héritées (devinées si non précisées)
	 * @return array tableau représentant le jeu de règle (brut de BDD)
	 */
	private final static function getFromDBStatic($type, $type_id, $values=array()) {
		$pos = array_search($type, static::$inherit_order);
		
		//Lecture des règles de cet item de configuration
		$rules = self::getFromDBStaticNoInherit($type, $type_id);
		
		// Réccupère les règles du niveau de dessus si pertinent
		if(isset(static::$inherit_order[$pos + 1])) {
			$type2 = static::$inherit_order[$pos + 1];
			$type_id2 = self::getTypeIdForCurrentConfig($type2);
			$inherited_rules = self::getFromDBStatic($type2, $type_id2, $values);
		} else {
			return $rules;
		}
		
		//Fusion des règles de cet item avec les règles héritées
		$result = array(); $beforezero = true;
		foreach($rules as $id => $rule) {
			if($rule['config__order']>0 && $beforezero) {
				$beforezero = false;
				foreach($inherited_rules as $id2=>$rule2) {
					$result[$id2] = $rule2;
				}
			}
			$result[$id] = $rule;
		}
		if($beforezero) $result = array_merge($result, $inherited_rules);
		
		return $result;
	}
	
	/**
	 * Lit le jeu de règles à appliquer, en tenant compte de l'héritage.
	 * Fonctionne avec un jeu de singletons pour éviter les appels à la base inutiles.
	 * @param array(string) $values valeurs de type_id à utiliser pour lire les règles héritées (devinées si non précisées)
	 * @return array tableau représentant le jeu de règle (brut de BDD)
	 */
	public final static function getRulesValues($values=array()) {
		$type = static::$inherit_order[0];
		$type_id = self::getTypeIdForCurrentConfig($type, $values);
		$res = self::getFromDBStatic($type, $type_id, $values);
		
		foreach(self::getConfigParams() as $param => $desc) {
			foreach($res as $i=>$rule) {
				if(self::isMultipleParam($param)) {
					$res[$i][$param] = importArrayFromDB($rule[$param]);
				}
			}
		}
		
		return $res;
	}
	
	

	
	
	/**
	 * Vérifie que l'utilisateur a les droits de faire l'ensemble d'action décrits dans $input
	 * Agit comme une série de CommonDBTM::check en faisant varier l'objet sur lequel elle s'applique et le droit demandé
	 * @param array $input tableau d'actions (typiquement POST après le formulaire)
	 */
	public final static function checkAll($input) {
		$instance = new static();
	
		if(isset($input['rules'])) {
			foreach($input['rules'] as $id=>$rule) {
				if(preg_match('/'.self::NEW_ID_TAG.'(\d*)/', $id)) {
					$instance->check(-1, 'w', $rule);
				} else {
					$instance->check($id, 'w');
				}
			}
		}
	
		if(isset($input['delete_rules'])) {
			foreach($input['delete_rules'] as $id) {
				$instance->check($id, 'd');
			}
		}
	}
	
	/**
	 * Enregistre en BDD l'ensemble d'action décrits dans $input
	 * Agit comme une série de CommonDBTM::add/update/delete sur différents objets
	 * @param array $input tableau d'actions (typiquement POST après le formulaire)
	 */
	public final static function updateAll($input) {
		$instance = new static();
	
		if(isset($input['rules'])) {
			foreach($input['rules'] as $id=>$rule) {
				if(preg_match('/'.self::NEW_ID_TAG.'(\d*)/', $id)) {
					$instance->add($rule);
				} else {
					$rule[self::getIndexName()] = $id;
					$instance->update($rule);
				}
			}
		}
	
		if(isset($input['delete_rules'])) {
			foreach($input['delete_rules'] as $id) {
				$instance->delete(array(self::getIndexName() => $id));
			}
		}
	}

	/**
	 * Gère la transformation des inputs multiples en quelque chose d'inserable dans la base (en l'occurence une chaine json).
	 * .
	 * @see CommonDBTM::prepareInputForAdd()
	 */
	final function prepareInputForAdd($input) {
		foreach(self::getConfigParams() as $param => $desc) {
			if(isset($input[$param]) && self::isMultipleParam($param)) {
				$input[$param] = exportArrayToDB($input[$param]);
			}
		}
		return $input;
	}
	
	
	
	/**
	 * Calcule la valeur des paramètres par défaut pour un item de configuration (hérite pour ce qui hérite, valeur par défaut pour ce qui n'hérite pas).
	 * @param string $type type de configuration
	 * @param number $type_id id de l'objet correspondant (sera écrasé à 0 si $type = TYPE_GLOBAL)
	 */
	private static final function makeEmptyRule($type, $type_id) {
		if($type == self::TYPE_GLOBAL) $type_id = 0;
	
		$input = array(
			'id' => self::NEW_ID_TAG,
			'config__type' => $type,
			'config__type_id' => $type_id,
			'config__order' => self::NEW_ORDER_TAG
		);
		
		foreach(self::getConfigParams() as $param => $desc) {
			if($desc['type'] === 'readonly text') continue;
			$input[$param] = $desc['default'];
		}
	
		return $input;
	}
	
	static protected final function showFormStatic($type, $type_id) {
		if(! self::canItemStatic($type, $type_id, 'r')) {
			return false;
		}
		$can_write = self::canItemStatic($type, $type_id, 'w');
		
		// lecture des données à afficher
		$current_rules = self::getFromDBStatic($type, $type_id);

		// racine de tous les identifiants du formulaire (doit être unique même dans le cas où plusieurs jeux de règles sont rassemblés sur la même page)
		$rootid = 'configmanager'.mt_rand();
		
		
		//Préparation de données 'vides' pour une création
		$empty_rule = self::makeEmptyRule($type, $type_id);
		$empty_rule_html = self::makeRuleTablerow($empty_rule, $rootid, true);
		//Préparation des données pour la suppression d'une règle
		$delete_rule_html = '<input type="hidden" name="delete_rules[]" value="'.self::NEW_ID_TAG.'">';
		
		// Entêtes du formulaire
		if($can_write) {
			$form_id = $rootid.'_form';
			echo '<form id="'.$form_id.'" action="' . PluginConfigmanagerRule::getFormURL() . '" method="post">';
		}
		
		echo '<table class="tab_cadre_fixe">';
		echo '<tr><th colspan="'.(count(self::getConfigParams())+1).'" class="center b">';
		echo static::getConfigPageTitle($type);
		echo '</th></tr>';
		
		// Ligne de titres
		echo '<tr class="headerRow">';
		foreach(self::getConfigParams() as $param => $desc) {
			echo '<th title="'.(isset($desc['tooltip'])?$desc['tooltip']:'').'">'.$desc['text'].'</th>';
		}
		echo '<th>'.__('Actions', 'configmanager').'</th>';
		echo '</tr>';
		
		/* Affichage des règles
		 * $beforezero est un marqueur servant à suivre si on doit encore afficher les règles héritées. On l'initialise à vrai ssi il est possible qu'il y ait des règles à hériter, puis si on s'apperçoit qu'on est en train d'afficher des règles d'ordre >0 alors qu'on doit encore afficher les règles héritées, ça veut dire qu'il n'y en a pas. On glisse donc le message indiquant que les règles seraient là. Idem à la fin, pour le cas où toutes les règles ont un order<0
		 */
		$table_id = $rootid.'_tbody';
		echo '<tbody id="'.$table_id.'">';
		
		$beforezero = isset(static::$inherit_order[array_search($type, static::$inherit_order) + 1]);
		foreach($current_rules as $rule) {
			$can_write2 = $can_write && $rule['config__type']==$type && $rule['config__type_id']==$type_id;
			
			if($rule['config__order']>0 && $beforezero && $rule['config__type']==$type) {
				// afficher une ligne bidon pour indiquer l'emplacement des règles héritées s'il n'y a rien d'hérité
				$beforezero = false;
				echo self::makeFakeInheritRow();
			} else if($rule['config__type']!=$type) {
				$beforezero = false;
			}
				
			echo self::makeRuleTablerow($rule, $rootid, $can_write2);
		}
		
		if($beforezero) {
			echo self::makeFakeInheritRow();
		}
		
		echo '</tbody>';
		
		// Affichage du 'bas de formulaire' (champs cachés et boutons)
		if($can_write) {
			echo '<tr>';
			echo '<td class="center"><a class="pointer" onclick="'.$rootid.'.addlast()"><img src="/pics/menu_add.png" title=""></a></td>';
			echo '<td class="center" colspan="'.(count(self::getConfigParams())).'">';
			echo '<input type="hidden" name="config__object_name" value="' . get_called_class() . '">';
			echo '<input type="submit" name="update" value="' . _sx('button', 'Save') . '" class="submit">';
			echo '</td></tr>';
		}
		echo '</table>';
		Html::closeForm();

		include GLPI_ROOT . "/plugins/configmanager/scripts/rules.js.php";
	}
	
	private final static function makeFakeInheritRow() {
		return '<tr><td colspan="'.(count(self::getConfigParams())+1).'" class="center b" style="background-color:rgb(140,200,140)">'.__('There are currently no rules inherited, but this is where they would be.').'</td></tr>';
	}
	
	/**
	 * Construit le code HTML pour la ligne de tableau correspondant à une règle
	 * @param array $rule la règle à afficher
	 * @param string $rootid racine à utiliser pour nommer les objets js et html
	 * @param boolean $can_write indique si la règle doit être affichée en lecture seule ou éditable
	 * @return string code html perméttant d'afficher la règle
	 */
	private static final function makeRuleTablerow($rule, $rootid, $can_write) {
		$output = '';
		$output .= '<tr id="'.$rootid.'_rule_'.$rule['id'].'">';
		foreach(self::getConfigParams() as $param => $desc) {
			$output .= '<td style="vertical-align:middle">';
			
			switch($desc['type']) {
				case 'dropdown' :
					$output .= self::makeDropdown($rule['id'], $param, $desc, $rule[$param], $can_write);
					break;
				case 'text input' :
					$output .= self::makeTextInput($rule['id'], $param, $desc, $rule[$param], $can_write);
					break;
				case 'text area' :
					$output .= self::makeTextArea($rule['id'], $param, $desc, $rule[$param], $can_write);
					break;
				case 'readonly text' : 
					$output .= $desc['text'];
					break;
			}
			
			$output .= '</td>';
		}
		
		$output .= '<td style="vertical-align:middle">';
		if($can_write) {
			$output .= '<input type="hidden" name="rules['.$rule['id'].'][config__type]" value="'.$rule['config__type'].'">';
			$output .= '<input type="hidden" name="rules['.$rule['id'].'][config__type_id]" value="'.$rule['config__type_id'].'">';
			$output .= '<input type="hidden" name="rules['.$rule['id'].'][config__order]" value="'.$rule['config__order'].'">';
			
			// TODO ajouter des infobulles
			$output .= '<table><tr style="vertical-align:middle">';
			$output .= '<td><a class="pointer" onclick="'.$rootid.'.moveup(\''.$rule['id'].'\')"><img src="/pics/deplier_up.png" title=""></a></td>';
			$output .= '<td><a class="pointer" onclick="'.$rootid.'.movedown(\''.$rule['id'].'\')"><img src="/pics/deplier_down.png" title=""></a></td>';
			$output .= '<td><a class="pointer" onclick="'.$rootid.'.add(\''.$rule['id'].'\')"><img src="/pics/menu_add.png" title=""></a></td>';
			$output .= '<td><a class="pointer" onclick="'.$rootid.'.remove(\''.$rule['id'].'\')"><img src="/pics/reset.png" title=""></a></td>';
			$output .= '</table></tr>';
		} else {
			$output .= self::getInheritedFromMessage($rule['config__type']);
		}
		
		$output .= '</td></tr>';
		
		return $output;
	}
	
	/**
	 * Construit le code HTML pour un champ de saisie via dropdown
	 * @param integer/string $id id de la règle dont fait partie le dropdown (integer ou tag de nouvel id)
	 * @param string $param nom du paramètre à afficher (champ name du select)
	 * @param array $desc description du paramètre à afficher
	 * @param string $values valeur(s) à pré-sélectionner (sous forme de tableau json si la sélection multiple est possible)
	 * @param boolean $can_write vrai ssi on doit afficher un menu sélectionnable, sinon on affiche juste le texte.
	 * @return string code html à afficher
	 */
	private static final function makeDropdown($id, $param, $desc, $values, $can_write) {
		$result = '';
		$options = isset($desc['options']) ? $desc['options'] : array();
		$options['display'] = false;
		
		if(isset($options['multiple']) && $options['multiple']) {
			$options['values'] = importArrayFromDB($values);
		} else {
			$options['values'] = array($values);
		}
		
		if($can_write) {
			$result .= Dropdown::showFromArray("rules[$id][$param]", $desc['values'], $options);
		} else {
			foreach($options['values'] as $value) {
				if(isset($desc['values'][$value])) { //test certes contre-intuitif, mais nécessaire pour gérer le fait que la liste de choix puisse être variable selon les droits de l'utilisateur.
					$result .= $desc['values'][$value] . '</br>';
				}
			}
		}
		
		return $result;
	}
	
	/**
	 * Construit le code HTML pour un champ de saisie texte libre
	 * @param integer/string $id id de la règle dont fait partie le champ (integer ou tag de nouvel id)
	 * @param string $param nom du paramètre à afficher (champ name du select)
	 * @param array $desc description du paramètre à afficher
	 * @param string $values valeur à utiliser pour préremplir le champ (doit être html-échappée)
	 * @param boolean $can_write vrai ssi on doit afficher un menu sélectionnable, sinon on affiche juste le texte.
	 * @return string code html à afficher
	 */
	private static final function makeTextInput($id, $param, $desc, $value, $can_write) {
		$result = '';
		$size = isset($desc['options']['size']) ? $desc['options']['size'] : 50;
		$maxlength = isset($desc['options']['maxlength']) ? $desc['options']['maxlength'] : 250;
		
		if($can_write) {
			$result .= '<input type="text" name="rules['.$id.']['.$param.']" value="'.Html::cleanInputText($value).'" size="'.$size.'" maxlength="'.$maxlength.'">';
		} else {
			$result .= $value;
		}
		
		return $result;
	}
	
	/**
	 * Construit le code HTML pour un champ de saisie texte libre en textarea
	 * @param integer/string $id id de la règle dont fait partie le champ (integer ou tag de nouvel id)
	 * @param string $param nom du paramètre à afficher (champ name du select)
	 * @param array $desc description du paramètre à afficher
	 * @param string $values valeur à utiliser pour préremplir le champ (doit être html-échappée)
	 * @param boolean $can_write vrai ssi on doit afficher un menu sélectionnable, sinon on affiche juste le texte.
	 * @return string code html à afficher
	 */
	private static final function makeTextArea($id, $param, $desc, $value, $can_write) {
		$result = '';
		$rows = isset($desc['options']['rows']) ? $desc['options']['rows'] : 5;
		$cols = isset($desc['options']['cols']) ? $desc['options']['cols'] : 50;
		$resize = isset($desc['options']['resize']) ? $desc['options']['resize'] : 'both';
		$maxlength = isset($desc['options']['maxlength']) ? $desc['options']['maxlength'] : 500;
	
		if($can_write) {
			$result .= '<textarea name="rules['.$id.']['.$param.']" rows="'.$rows.'" cols="'.$cols.'" style="resize:'.$resize.'" maxlength="'.$maxlength.'">'.Html::cleanPostForTextArea($value).'</textarea>';
		} else {
			$result .= nl2br($value);
		}
	
		return $result;
	}
	
	
}
?>


























