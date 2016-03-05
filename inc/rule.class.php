<?php

/**
 * Objet générique de gestion de règles, offrant la possibilité d'avoir plusieurs niveaux de paramétrage, avec héritage entre les niveaux (les règles se cumulent). Cet objet a vocation à être utilisé en définissant un objet qui en hérite, et en redéfinissant certaines fonctions.
 * Dans le modèle de données, cet objet représente une unique règle, pour un seul type de configuration (générale OU utilisateur...). Les règles applicables dans un contexte donné pour un utilisateur correspond donc à l'union de plusieurs objets PluginConfigmanagerConfig de différents types, classé dans un ordre donné. L'application qui utilise ces règles est ensuite libre de choisir comment elle les utilise (la règle de dessus surchage l'autre, toutes s'appliquent en parallèle...).
 * @author Etiennef
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
   }
   // Note: la fonction n'est pas utilisée dans cette classe, mais elle est appellée depuis common.class
   public final static function install($additionnal_param = '') {
      parent::install("`config__order` int(11) collate utf8_unicode_ci NOT NULL,");
   }

   /**
    * Lit un jeu de règle pour un item de configuration donné, sans tenir compte de l'héritage.
    * Fonctionne avec un jeu de singletons pour éviter les appels à la base inutiles.
    *
    * @param string $type
    *           type de configuration
    * @param integer $type_id
    *           type_id de l'item à lire
    * @return array tableau représentant le jeu de règle (brut de BDD)
    */
   private final static function getFromDBStaticNoInherit($type, $type_id) {
      static $_rules_instances = array();

      if (!isset($_rules_instances[get_called_class()][$type][$type_id])) {
         if (!isset($_rules_instances[get_called_class()]))
            $_rules_instances[get_called_class()] = array();
         if (!isset($_rules_instances[get_called_class()][$type]))
            $_rules_instances[get_called_class()][$type] = array();

         $_rules_instances[get_called_class()][$type][$type_id] = (new static())->find("`config__type`='$type' AND `config__type_id`='$type_id'", "config__order");
      }
      return $_rules_instances[get_called_class()][$type][$type_id];
   }

   /**
    * Lit un jeu de règle pour un item de configuration donné, en tenant compte de l'héritage (mais seulement à partir du type donné en argument).
    * Fonctionne avec un jeu de singletons pour éviter les appels à la base inutiles.
    *
    * @param string $type
    *           type de configuration
    * @param integer $type_id
    *           type_id de l'item à lire
    * @param array(string) $values
    *           valeurs de type_id à utiliser pour lire les règles héritées (devinées si non précisées)
    * @return array tableau représentant le jeu de règle (brut de BDD)
    */
   private final static function getFromDBStatic($type, $type_id, $values = array()) {
      $pos = array_search($type, static::$inherit_order);

      // Lecture des règles de cet item de configuration
      $rules = self::getFromDBStaticNoInherit($type, $type_id);

      // Réccupère les règles du niveau de dessus si pertinent
      if (isset(static::$inherit_order[$pos + 1])) {
         $type2 = static::$inherit_order[$pos + 1];
         $type_id2 = self::getTypeIdForCurrentConfig($type2);
         $inherited_rules = self::getFromDBStatic($type2, $type_id2, $values);
      } else {
         return $rules;
      }

      // Fusion des règles de cet item avec les règles héritées
      $result = array();
      $beforezero = true;
      foreach ( $rules as $id => $rule ) {
         if ($rule['config__order'] > 0 && $beforezero) {
            $beforezero = false;
            foreach ( $inherited_rules as $id2 => $rule2 ) {
               $result[$id2] = $rule2;
            }
         }
         $result[$id] = $rule;
      }
      if ($beforezero)
         $result = array_merge($result, $inherited_rules);

      return $result;
   }

   /**
    * Lit le jeu de règles à appliquer, en tenant compte de l'héritage.
    * Fonctionne avec un jeu de singletons pour éviter les appels à la base inutiles.
    *
    * @param array(string) $values
    *           valeurs de type_id à utiliser pour lire les règles héritées (devinées si non précisées)
    * @return array tableau représentant le jeu de règle (brut de BDD)
    */
   public static function getRulesValues($values = array()) {
      $type = static::$inherit_order[0];
      $type_id = self::getTypeIdForCurrentConfig($type, $values);
      $res = self::getFromDBStatic($type, $type_id, $values);

      foreach ( self::getConfigParams() as $param => $desc ) {
         foreach ( $res as $i => $rule ) {
            if (isset($desc['multiple']) && $desc['multiple']) {
               $res[$i][$param] = importArrayFromDB($rule[$param]);
            }
         }
      }

      return $res;
   }


   static final function canDelete() {
      return true;
   }

   final function canDeleteItem() {
      return self::canItemStatic($this->fields['config__type'], $this->fields['config__type_id'], UPDATE);
   }

   /**
    * Vérifie que l'utilisateur a les droits de faire l'ensemble d'action décrits dans $input
    * Agit comme une série de CommonDBTM::check en faisant varier l'objet sur lequel elle s'applique et le droit demandé
    *
    * @param array $input
    *           tableau d'actions (typiquement POST après le formulaire)
    */
   public final static function checkAll($input) {
      $instance = new static();

      if (isset($input['rules'])) {
         foreach ( $input['rules'] as $id => $rule ) {
            if (preg_match('@' . self::NEW_ID_TAG . '(\d*)@', $id)) {
               $instance->check(-1, CREATE, $rule);
            } else {
               $instance->check($id, UPDATE);
            }
         }
      }

      if (isset($input['delete_rules'])) {
         foreach ( $input['delete_rules'] as $id ) {
            $instance->check($id, DELETE);
         }
      }
   }

   /**
    * Enregistre en BDD l'ensemble d'action décrits dans $input
    * Agit comme une série de CommonDBTM::add/update/delete sur différents objets
    *
    * @param array $input
    *           tableau d'actions (typiquement POST après le formulaire)
    */
   public final static function updateAll($input) {
      $instance = new static();

      if (isset($input['rules'])) {
         foreach ( $input['rules'] as $id => $rule ) {
            if (preg_match('@' . self::NEW_ID_TAG . '(\d*)@', $id)) {
               $instance->add($rule);
            } else {
               $rule[self::getIndexName()] = $id;
               $instance->update($rule);
            }
         }
      }

      if (isset($input['delete_rules'])) {
         foreach ( $input['delete_rules'] as $id ) {
            $instance->delete(array(
                  self::getIndexName() => $id
            ));
         }
      }
   }

   /**
    * Gère la transformation des inputs multiples en quelque chose d'inserable dans la base (en l'occurence une chaine json).
    * .
    *
    * @see CommonDBTM::prepareInputForAdd()
    */
   final function prepareInputForAdd($input) {
      foreach ( self::getConfigParams() as $param => $desc ) {
         if (isset($input[$param]) && isset($desc['multiple']) && $desc['multiple']) {
            $input[$param] = exportArrayToDB($input[$param]);
         }
      }
      return $input;
   }

   /**
    * Calcule la valeur des paramètres par défaut pour un item de configuration (hérite pour ce qui hérite, valeur par défaut pour ce qui n'hérite pas).
    *
    * @param string $type
    *           type de configuration
    * @param number $type_id
    *           id de l'objet correspondant (sera écrasé à 0 si $type = TYPE_GLOBAL)
    */
   private static final function makeEmptyRule($type, $type_id) {
      if ($type == self::TYPE_GLOBAL)
         $type_id = 0;

      $input = array(
            'id' => self::NEW_ID_TAG,
            'config__type' => $type,
            'config__type_id' => $type_id,
            'config__order' => self::NEW_ORDER_TAG
      );

      foreach ( self::getConfigParams() as $param => $desc ) {
         if ($desc['type'] === 'readonly text')
            continue;
         $input[$param] = $desc['default'];
      }

      return $input;
   }

   static protected final function showFormStatic($type, $type_id) {
      global $CFG_GLPI;

      if (!self::canItemStatic($type, $type_id, READ)) {
         return false;
      }
      $can_write = self::canItemStatic($type, $type_id, UPDATE);

      // lecture des données à afficher
      $current_rules = self::getFromDBStatic($type, $type_id);

      // racine de tous les identifiants du formulaire (doit être unique même dans le cas où plusieurs jeux de règles sont rassemblés sur la même page)
      $rootid = 'configmanager' . mt_rand();

      // Entêtes du formulaire
      if ($can_write) {
         $form_id = $rootid . '_form';
         echo '<form id="' . $form_id . '" action="' . PluginConfigmanagerRule::getFormURL() . '" method="post">';
      }

      echo '<table class="tab_cadre_fixe">';

      if (isset(self::getConfigParams()['_header']['text'])) {
         echo self::getConfigParams()['_header']['text'];
      }

      // Ligne de titres
      echo '<tr class="headerRow">';
      foreach ( self::getConfigParams() as $param => $desc ) {
         if ($param[0] != '_') {
            $tooltip = isset($desc['tooltip']) ? ' title="' . ($desc['tooltip']) . '"' : '';
            echo '<th' . $tooltip . '>' . $desc['text'] . '</th>';
         }
      }
      echo '<th>' . __('Actions', 'configmanager') . '</th>';
      echo '</tr>';

      /*
       * Affichage des règles
       * $beforezero est un marqueur servant à suivre si on doit encore afficher les règles héritées. On l'initialise à vrai ssi il est possible qu'il y ait des règles à hériter, puis si on s'apperçoit qu'on est en train d'afficher des règles d'ordre >0 alors qu'on doit encore afficher les règles héritées, ça veut dire qu'il n'y en a pas. On glisse donc le message indiquant que les règles seraient là. Idem à la fin, pour le cas où toutes les règles ont un order<0
       */
      $table_id = $rootid . '_tbody';
      echo '<tbody id="' . $table_id . '">';

      $beforezero = isset(static::$inherit_order[array_search($type, static::$inherit_order) + 1]);
      foreach ( $current_rules as $rule ) {
         $can_write2 = $can_write && $rule['config__type'] == $type && $rule['config__type_id'] == $type_id;

         if ($rule['config__order'] > 0 && $beforezero && $rule['config__type'] == $type) {
            // afficher une ligne bidon pour indiquer l'emplacement des règles héritées s'il n'y a rien d'hérité
            $beforezero = false;
            echo self::makeFakeInheritRow();
         } else if ($rule['config__type'] != $type) {
            $beforezero = false;
         }

         echo self::makeRuleTablerow($rule, $rootid, $can_write2);
      }

      if ($beforezero) {
         echo self::makeFakeInheritRow();
      }

      echo '</tbody>';

      if (isset(self::getConfigParams()['_pre_save']['text'])) {
         echo self::getConfigParams()['_pre_save']['text'];
      }

      // Affichage du 'bas de formulaire' (champs cachés et boutons)
      if ($can_write) {
         echo '<tr>';
         echo '<td class="center"><a class="pointer" onclick="' . $rootid . '.addlast()"><img src="/pics/menu_add.png" title=""></a></td>';
         echo '<td class="center" colspan="' . (count(self::getConfigParams())) . '">';
         echo '<input type="hidden" name="config__object_name" value="' . get_called_class() . '">';
         echo '<input type="submit" name="update" value="' . _sx('button', 'Save') . '" class="submit">';
         echo '</td></tr>';
      }

      if (isset(self::getConfigParams()['_footer']['text'])) {
         echo self::getConfigParams()['_footer']['text'];
      }
      echo '</table>';
      Html::closeForm();

      // Préparation de données 'vides' pour une création
      $newidtag = self::NEW_ID_TAG;
      $newordertag = self::NEW_ORDER_TAG;

      $empty_rule = self::makeEmptyRule($type, $type_id);
      $empty_rule_html = self::makeRuleTablerow($empty_rule, $rootid, true);
      $empty_rule_html = preg_replace("@[\n\r]@", " ", $empty_rule_html);
      $empty_rule_html = preg_replace("@<script.*?</script>@", "", $empty_rule_html);
      $empty_rule_html = addslashes($empty_rule_html);

      // Préparation des données pour la suppression d'une règle
      $delete_rule_html = addslashes('<input type="hidden" name="delete_rules[]" value="' . $newidtag . '">');

      echo Html::scriptBlock(<<<JS
      var $rootid = {};

      $(function() {
         var tableNode = document.getElementById('$table_id');
         var formNode = document.getElementById('$form_id');
         var rules = {}; //id=> order, dom (ne contient pas les 0)

         $rootid = {
               moveup : moveup,
               movedown : movedown,
               add : add,
               addlast : addlast,
               remove : remove,
         };

         initialize();

         /**
          * Initialisation du script : les règles sont 'scannées' pour pouvoir être manipulées facilement par la suite
          */
         function initialize() {
            rows = tableNode.children;
            for (var i in rows) {
               var row = rows[i];
               if(row.nodeType) {
                  if(!row.id.match(/{$rootid}_rule_(\d)/)) continue;
                   id = row.id.match(/{$rootid}_rule_(\d)/)[1];

                  orderDom = row.querySelector('[name$="rules['+id+'][config__order]"]');
                  // orderDom.length == null ssi c'est une règle héritée
                  if(orderDom) {
                       rules[id] = {
                        id : id,
                        dom : row,
                        order : parseInt(orderDom.value),
                       };
                  }
               }
            }
         }

         /**
          * Déplace une règle vers le haut
          */
         function moveup(id) {
            var current = rules[id];
            var prev = null;

            //On recherche la règle précédente
            Object.keys(rules).forEach(function(rule_id) {
               rule = rules[rule_id];
               if(rule.id === id) return;
               if(rules[rule_id].order < current.order && (prev === null || rules[rule_id].order > prev.order)) {
                  prev = rule;
               }
            });


            if(prev===null && current.order < 0) {
               // cas où il n'y a pas de précédent et qu'on est déjà avant les règles héritées
               return;
            } else if((prev===null || prev.order<0) && current.order>0) {
               // cas où on doit croiser les règles héritées
               Object.keys(rules).forEach(function(rule_id) {
                  setOrder(rules[rule_id], rules[rule_id].order-1);
               });
               setOrder(current, -1);
               if(prev) {
                  tableNode.insertBefore(current.dom, prev.dom.nextSibling);
               } else {
                  tableNode.insertBefore(current.dom, tableNode.firstElementChild);
               }
            } else {
               // cas où on ne croise qu'un règle
               var tmp = current.order
               setOrder(current, prev.order);
               setOrder(prev, tmp);
               tableNode.insertBefore(current.dom, prev.dom);
            }
         }

         /**
          * Déplace une règle vers le bas
          */
         function movedown(id) {
            var current = rules[id];
            var next = null;

            //On recherche la règle suivante
            Object.keys(rules).forEach(function(rule_id) {
               rule = rules[rule_id];
               if(rule.id === id) return;
               if(rules[rule_id].order > current.order && (next === null || rules[rule_id].order < next.order)) {
                  next = rule;
               }
            });


            if(next===null && current.order > 0) {
               // cas où il n'y a pas de précédent et qu'on est déjà avant les règles héritées
               return;
            } else if((next===null || next.order>0) && current.order<0) {
               // cas où on doit croiser les règles héritées
               Object.keys(rules).forEach(function(rule_id) {
                  setOrder(rules[rule_id], rules[rule_id].order+1);
               });
               setOrder(current, 1);
               if(next) {
                  tableNode.insertBefore(current.dom, next.dom);
               } else {
                  tableNode.appendChild(current.dom);
               }
            } else {
               // cas où on ne croise qu'un règle
               var tmp = current.order
               setOrder(current, next.order);
               setOrder(next, tmp);
               tableNode.insertBefore(next.dom, current.dom);
            }
         }


         addcnt = 1;
         /**
          * Ajoute une règle juste après celle dont l'id est donné en argument
          */
         function add(id) {
            var current = rules[id];

            var template = '$empty_rule_html';

            var newOrder = current.order<0?current.order:current.order+1;
            var newID = '$newidtag'+addcnt++;

            // Adaptation du modèle de ligne à notre cas particulier
            template = template.replace(/$newidtag/g, newID);
            template = template.replace(/$newordertag/g, newOrder);

            // Décalage des objets pour faire de la place au nouveau
            Object.keys(rules).forEach(function(rule_id) {
               rule = rules[rule_id];
               if(newOrder>0 && rule.order>=newOrder) setOrder(rule, rule.order+1);
               if(newOrder<0 && rule.order<=newOrder) setOrder(rule, rule.order-1);
            });

            // création du DOM du nouvel objet
            var tbody = document.createElement('tbody');
            tbody.innerHTML = template;

            newRule = {
                  id : newID,
                  order : newOrder,
                  dom : tbody.firstElementChild,
            };
            rules[newID] = newRule;

            // Ajout de la nouvelle ligne juste après celle à partir de laquelle on a cliqué
            if(current.dom.nextSibling) {
               tableNode.insertBefore(newRule.dom, current.dom.nextSibling);
            } else {
               tableNode.appendChild(newRule.dom);
            }

            prettyDropdown(newRule.dom);

         }

         /**
          * Ajoute une règle en dernière position
          */
         function addlast() {
            var newID = '$newidtag'+addcnt++;
            var newOrder = null;

            Object.keys(rules).forEach(function(rule_id) {
               rule = rules[rule_id];
               if(newOrder === null || newOrder<rule.order) newOrder = rule.order;
            });
            if(newOrder === null) newOrder = 1;
            else newOrder++;

            // Adaptation du modèle de ligne à notre cas particulier
            var template = '$empty_rule_html';
            template = template.replace(/$newidtag/g, newID);
            template = template.replace(/$newordertag/g, newOrder);

            // création du DOM du nouvel objet
            var tbody = document.createElement('tbody');
            tbody.innerHTML = template;

            newRule = {
                  id : newID,
                  order : newOrder,
                  dom : tbody.firstElementChild,
            };
            rules[newID] = newRule;

            tableNode.appendChild(newRule.dom);
            prettyDropdown(newRule.dom);
         }

         /**
          * Retire la règle dont l'id est donné en argument
          */
         function remove(id) {
            var current = rules[id];

            tableNode.removeChild(current.dom);

            // Ajout de l'input signalant la suppression d'une règle (seulement si règle existante côté serveur)
            if(!/<?php echo self::NEW_ID_TAG;?>\d*/.test(id)) {
               var template = '$delete_rule_html';
               template = template.replace(/$newidtag/g, id);
               var div = document.createElement('div');
               div.innerHTML = template;
               formNode.appendChild(div.firstElementChild);

            }

            // Décalage des objets pour boucher le trou
            Object.keys(rules).forEach(function(rule_id) {
               rule = rules[rule_id];
               if(current.order>0 && rule.order>current.order) setOrder(rule, rule.order-1);
               if(current.order<0 && rule.order<current.order) setOrder(rule, rule.order+1);
            });

            delete rules[id];
         }

         function setOrder(rule, order) {
            rule.order = order;
            tableNode.querySelector('[name$="rules['+rule.id+'][config__order]"]').value = order;
         }

         /**
          * Transforms a simple dropdown into a jQuery select
          * Adapted from Html::jsAdaptDropdown & Dropdown::show (end of function)
          */
         function prettyDropdown(dom) {
            console.log('coucou');
            console.log(dom);
            $('#'+dom.id+' select').each(function(test, toto) {
               console.log(test, toto);
            });
            $('#'+dom.id+' select').select2({
               width: '100%',
               closeOnSelect: false,
               dropdownAutoWidth: true,
               quietMillis: 100,
               minimumResultsForSearch: '$CFG_GLPI[ajax_limit_count]',
               formatSelection: function(object, container) {
                  text = object.text;
                  if (object.element[0].parentElement.nodeName == 'OPTGROUP') {
                     text = object.element[0].parentElement.getAttribute('label') + ' - ' + text;
                  }
                  return text;
               },
               formatResult: function (result, container) {
                  return $('<div>', {title: result.title}).text(result.text);
               }
            });

            $('#'+dom.id+' select').each(function(i, el) {
               var multichecksappend = false;
               $('#'+el.id).on('select2-open', function() {
                  if (!multichecksappend) {
                     $('#select2-drop').append($('#selectallbuttons_'+el.id).html());
                     multichecksappend = true;
                  }
               });
            });
         }
      });
JS
      );
   }

   private final static function makeFakeInheritRow() {
      return '<tr><td colspan="' . (count(self::getConfigParams()) + 1) . '" class="center b" style="background-color:rgb(140,200,140)">' . __('There are currently no rules inherited, but this is where they would be.') . '</td></tr>';
   }

   /**
    * Construit le code HTML pour la ligne de tableau correspondant à une règle
    *
    * @param array $rule
    *           la règle à afficher
    * @param string $rootid
    *           racine à utiliser pour nommer les objets js et html
    * @param boolean $can_write
    *           indique si la règle doit être affichée en lecture seule ou éditable
    * @return string code html perméttant d'afficher la règle
    */
   private static final function makeRuleTablerow($rule, $rootid, $can_write) {
      $output = '';
      $output .= '<tr id="' . $rootid . '_rule_' . $rule['id'] . '">';
      foreach ( self::getConfigParams() as $param => $desc ) {
         // ignore les paramètres spéciaux _header etc...
         if ($param[0] == '_')
            continue;

         $tooltip = isset($desc['tooltip']) ? ' title="' . ($desc['tooltip']) . '"' : '';
         $output .= '<td' . $tooltip . ' style="vertical-align:middle">';

         switch ($desc['type']) {
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
      if ($can_write) {
         $output .= '<input type="hidden" name="rules[' . $rule['id'] . '][config__type]" value="' . $rule['config__type'] . '">';
         $output .= '<input type="hidden" name="rules[' . $rule['id'] . '][config__type_id]" value="' . $rule['config__type_id'] . '">';
         $output .= '<input type="hidden" name="rules[' . $rule['id'] . '][config__order]" value="' . $rule['config__order'] . '">';

         $output .= '<table><tr style="vertical-align:middle">';
         $output .= '<td><a class="pointer" onclick="' . $rootid . '.moveup(\'' . $rule['id'] . '\')"><img src="/pics/deplier_up.png" title=""></a></td>';
         $output .= '<td><a class="pointer" onclick="' . $rootid . '.movedown(\'' . $rule['id'] . '\')"><img src="/pics/deplier_down.png" title=""></a></td>';
         $output .= '<td><a class="pointer" onclick="' . $rootid . '.add(\'' . $rule['id'] . '\')"><img src="/pics/menu_add.png" title=""></a></td>';
         $output .= '<td><a class="pointer" onclick="' . $rootid . '.remove(\'' . $rule['id'] . '\')"><img src="/pics/reset.png" title=""></a></td>';
         $output .= '</table></tr>';
      } else {
         $output .= self::getInheritedFromMessage($rule['config__type']);
      }

      $output .= '</td></tr>';

      return $output;
   }

   /**
    * Construit le code HTML pour un champ de saisie via dropdown
    *
    * @param integer/string $id
    *           id de la règle dont fait partie le dropdown (integer ou tag de nouvel id)
    * @param string $param
    *           nom du paramètre à afficher (champ name du select)
    * @param array $desc
    *           description du paramètre à afficher
    * @param string $values
    *           valeur(s) à pré-sélectionner (sous forme de tableau json si la sélection multiple est possible)
    * @param boolean $can_write
    *           vrai ssi on doit afficher un menu sélectionnable, sinon on affiche juste le texte.
    * @return string code html à afficher
    */
   private static final function makeDropdown($id, $param, $desc, $values, $can_write) {
      $options = array(
            'multiple' => isset($desc['multiple']) && $desc['multiple'],
            'width' =>  isset($desc['width']) ? $desc['width'] : '100%'
      );

      $result = '';
      $options['display'] = false;

      if ($options['multiple']) {
         $options['values'] = importArrayFromDB($values);
      } else {
         $options['values'] = array(
               $values
         );
      }

      if ($can_write) {
         $result .= Dropdown::showFromArray("rules[$id][$param]", $desc['values'], $options);
      } else {
         foreach ( $options['values'] as $value ) {
            if (isset($desc['values'][$value])) { // test certes contre-intuitif, mais nécessaire pour gérer le fait que la liste de choix puisse être variable selon les droits de l'utilisateur.
               $result .= $desc['values'][$value] . '</br>';
            }
         }
      }

      return $result;
   }

   /**
    * Construit le code HTML pour un champ de saisie texte libre
    *
    * @param integer/string $id
    *           id de la règle dont fait partie le champ (integer ou tag de nouvel id)
    * @param string $param
    *           nom du paramètre à afficher (champ name du select)
    * @param array $desc
    *           description du paramètre à afficher
    * @param string $values
    *           valeur à utiliser pour préremplir le champ (doit être html-échappée)
    * @param boolean $can_write
    *           vrai ssi on doit afficher un menu sélectionnable, sinon on affiche juste le texte.
    * @return string code html à afficher
    */
   private static final function makeTextInput($id, $param, $desc, $value, $can_write) {
      $result = '';
      $size = isset($desc['size']) ? $desc['size'] : 50;
      $maxlength = $desc['maxlength'];

      if ($can_write) {
         $result .= '<input type="text" name="rules[' . $id . '][' . $param . ']" value="' . Html::cleanInputText($value) . '" size="' . $size . '" maxlength="' . $maxlength . '">';
      } else {
         $result .= $value;
      }

      return $result;
   }

   /**
    * Construit le code HTML pour un champ de saisie texte libre en textarea
    *
    * @param integer/string $id
    *           id de la règle dont fait partie le champ (integer ou tag de nouvel id)
    * @param string $param
    *           nom du paramètre à afficher (champ name du select)
    * @param array $desc
    *           description du paramètre à afficher
    * @param string $values
    *           valeur à utiliser pour préremplir le champ (doit être html-échappée)
    * @param boolean $can_write
    *           vrai ssi on doit afficher un menu sélectionnable, sinon on affiche juste le texte.
    * @return string code html à afficher
    */
   private static final function makeTextArea($id, $param, $desc, $value, $can_write) {
      $result = '';
      $rows = isset($desc['rows']) ? $desc['rows'] : 5;
      $cols = isset($desc['cols']) ? $desc['cols'] : 50;
      $resize = isset($desc['resize']) ? $desc['resize'] : 'both';
      $maxlength = $desc['maxlength'];

      if ($can_write) {
         $result .= '<textarea name="rules[' . $id . '][' . $param . ']" rows="' . $rows . '" cols="' . $cols . '" style="resize:' . $resize . '" maxlength="' . $maxlength . '">' . Html::cleanPostForTextArea($value) . '</textarea>';
      } else {
         $result .= nl2br($value);
      }

      return $result;
   }

   protected static final function makeHeaderLine($text) {
      return '<tr><th class="headerRow" colspan="1000">' . $text . '</th></tr>';
   }

}
?>


























