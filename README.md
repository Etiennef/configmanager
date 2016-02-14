# configmanager
Ce plugin n'a en fait aucun intérêt en lui-même. Son objectif est de fournir une base partagée pour gérer la configuration d'autres plugins.
Le principe est le suivant : plutôt que s'embêter à chaque fois qu'on a des configurations à gérer à faire des objets héritant de CommonDBTM et à réimplémenter une interface pour 3 dropdowns, il suffit d'hériter de la classe fournie pas ce plugin, de sur-charger deux-trois fonctions très simples pour décrire la configuration qu'on veut (un peu à la manière d'un modèle de données), et le tour est joué.
De plus, ce plugin offre la possibilité de définir plusieurs niveau de configurations pouvant se surcharger les uns les autres (le cas d'usage typique, c'est d'avoir une configuration générale, qu'un utilisateur peut surcharger dans ses préférences).

# Mode d'emploi

## 1- Créer l'objet de configuration
Il suffit de faire un objet héritant de `PluginConfigmanagerConfig`. Le choix du nom est libre, mais pour l'exemple, on l'appellera `PluginMonpluginConfig`. Si vos besoins le nécessitent, vous pouvez tout à fait en faire plusieurs, même si à priori j'en vois pas trop dans quelle situation ça présente un intérêt.
```
class PluginMonpluginConfig extends PluginConfigmanagerConfig { ...
```

## 2- Définir le modèle
Pour cela, il faut surcharger la fonction `makeConfigParams`.
Elle doit renvoyer un tableau ayant la structure suivante :
```
array(
	'param1' => array(
		'text' => __('Description du paramètre 1', 'monplugin'),
		'values' => array(
			'1' => Dropdown::getYesNo('1'),
			'0' => Dropdown::getYesNo('0'),
			'value1' => __('texte de la valeur 1', 'monplugin'),
			'value2' => __('texte de la valeur 2', 'monplugin')
		),
		'types' => array(self::TYPE_USER, self::TYPE_GLOBAL),
		'dbtype' => 'varchar(25)',
		'default' => '0',
		'options' => array(
	 		'multiple' => false,
	 		'size' => 5,
	 		'mark_unmark_all' => false
	 	)
	),
	'param2' => array(
		'text' => __('Description de la valeur 2', 'monplugin'),
		'values' => 'text input',
		'types' => array(self::TYPE_PROFILE, self::TYPE_GLOBAL),
		'dbtype' => 'varchar(250)',
		'default' => 'value1',
		'options' => array(
			'size' => 75,
			'maxlength' => 250
		);
	), ...
);
```

Explication de texte :
* les clés du tableau (param1, param2) sont les valeurs des paramètres de configuration. Elle n'apparaitront jamais aux yeux de l'utilisateur, mais ce seront les clés du tableau de configuration que vous réccuppérerez quand vous voudrez lire la configcourante. Deux types de valeurs interdites :
	* Tout ce qui fait une injection sql (car ce sera utilisé pour le nom des colonne de la BDD) (non, il n'y a pas de vérification, je pars du principe que si vous codez un plugin, vous savez faire attention à ça)
	* `config__type` et `config__type_id`, qui sont utilisés par le mécanisme interne du plugin.
* `values` représente les valeurs possibles de la configuration
	* Soit sous forme d'un tableau pour afficher un dropdown (sous la forme 'value'=>'texte à afficher comme option du dropdown')
	* Soit `'text input'` si vous souhaitez un champs de saisie libre (input de type text)
	* d'autres options seront certainement ajoutées à l'avenir (textarea...)
* `types` représente les situations dans lesquelles ce paramètre de configuration a du sens. Cela signifie que le réglage de ces options ser proposés aux endroits de l'interface utilisateur pertinents (dans un onglet dédié), et que lorsque l'on va charger la configuration courante, ce paramètre sera pris en compte pour ce type de contexte. L'ordre du tableau est important : le premier élément va surcharger le deuxième, qui va surcharger le troisième... S'ils sont définis bien sûr, sinon la configuration sera héritée du niveau de dessus. On distingue 5 types de configuration :
	* TYPE_GLOBAL => configuration générale (se règle au niveau de la configuration générale de GLPI, ou en cliquant sur le nom du plugin dans la page de gestion des plugins). Il ne peut y en avoir qu'une seule pour tout le plugin.
	* TYPE_PROFILE => configuration par profil. Lorsque la configuration est lue, la ligne du profil courant de l'utilisateur sera prise en compte.
	* TYPE_USERENTITY => configuration par entité. Lorsque la configuration est lue, la ligne de l'entité courant dans laquelle est l'utilisateur sera prise en compte (qui peut être différente de l'entité des objets manipulés par l'utilisateur)
	* TYPE_ITEMENTITY => configuration par entité. Lorsque la configuration est lue, la ligne de l'entité demandée lors de l'appel de la fonction de lecture sera prise en compte (usage prévu: on a une configuration qui doit s'appliquer selon l'entité d'appartenance d'un objet, on va appeller la fonction de lecture de la configuration en lui passant l'entité de l'objet en question)
	* TYPE_USER => configuration par utilisateur (réglable soit dans la page de gestion des utilisateurs, soit dans le menu préférences de l'utilisateur)
* `dbtype` est le type de la colonne de base de donnée qui sera créé. Doit être un type valide en SQL et du fait de la façon dont le plugin gère les héritages, ce type doit accepter une chaine d'au moins 25 carachères (c'est assez simple). Mais surtout, doit être compatible avec les autres options, ce qui nécessite un peu plus d'attentions. A priori, varchar sera privilégié, la longueur dépendra de la longueur des valeurs que vous serez ammenés à stocker.
	* si `values` est un tableau, max(25, longueur de la plus grande valeur possible) (attention, dans le cas où options['multiple'] est sélectionné, les différentes options sélectionnées sont concaténées sous la forme d'un tableau json, ce qui nécessite potentiellement une longueur égale à la somme de la longueur de toutes les options, plus les , et " intercalaires)
	* si `values` est `text input` max(25, options['maxlength'])
* `default` est la valeur par défaut du paramètre. Elle doit impérativement avoir un sens pour votre plugin, dans la mesure où elle sera potentiellement renvoyée comme valeur courante de la configuration. Lors de la création d'une entrée d'un type donné (par exemple TYPE_USER, qui correspond aux préférences d'un utilisateur), chaque valeur de la base de donnée peut être initialisée de deux manières :
	* soit le type est le type 'de référence' de ce paramètre (comprendre qu'il ne peut pas hériter d'un autre type de configuration), et c'est la valeur par défaut qui est insérée
	* soit ce type n'est pas le type de référence, alors c'est un marqueur disant que la configuration doit être héritée du niveau de dessus qui est inséré.
* `options` est comme son nom l'indique optionnel (les valeurs par défaut sont indiquées entre parenthèses). Les valeur possibles dépendent du format de paramètre :
	* si `values` est un tableau :
		`multiple` (false) => indique s'il doit être possible de sélectionner plusieurs valeurs à la fois (attention à la longueur prévue en base de donnée)
		`size` (1) => hauteur de dropdown de saisie
		`mark_unmark_all` (false) => permet d'afficher les options tout sélectionner/tout désélectionner (valable seulement si multiple est vrai)
   * si `values` est `text input` max(25, options['maxlength'])
		`size` (50) => largeur en caractères du champs de saisie affiché
		`maxlength` (250) => taille en caractères du texte saisissable (attention à bien être compatible avec la taille de l'entrée en base de donnée!!)

## 3- Personnaliser l'affichage
Le plugin `ConfigManager` produit des textes pour le nom des onglets, en se basant sur divers éléments. Ces méthodes très génériques ne correspondront pas nécessairement à quelque chose d'intelligent dans tous les contextes, vous pouvez donc les personnaliser:
* `getTabNameForConfigType` => définit le nom de l'onglet de configuration en fonction du type de configuration (par défaut, appelle `static::getName`)
* `getConfigPageTitle` => définit le titre de la 'zone' du formulaire de configuration pour un type de configuration (par défaut de la forme `'Configuration pour le type xxx du plugin '.static::getName()`, où xxx dépend du type)
* `getInheritFromMessage` => définit le message affiché dans le formulaire pour dire que l'option choisie est d'hériter du niveau de dessus. Sera utilisé, par exemple, dans les dropdowns (valeur par défaut, de la forme `'Hériter de la configuration xxx'`, ou xxx dépend du type)

On remarque deux choses :
* Les fonctions utilisent getName, donc si la personnalisation souhaitée est minimaliste, il suffit de surcharger getName.
* Les fonctions prennent en paramètre le type de configuration. Il est donc recommandé de faire un switch/case (copier-coller de la fonction surchargée si vous êtes fainéants). Notez que la fonction n'est appellée que pour les types réellement utilisés, donc vous n'êtes pas obligés de couvrir tous les cas si votre configuration n'utilise pas tous les types.

## 4- Brancher la configuration dans les fichiers de config de votre plugin
A nouveau parce que c'est difficile à faire autrement qu'à la main. Ca se fait en ajoutant (en plus évidemment de ce que vous avez déjà) quelques lignes dans 4 fonctions :

Dans `plugin_monplugin_install` :
```
include 'inc/config.class.php';
PluginMonpluginConfig::install();
```

Dans `plugin_monplugin_uninstall` :
```
include 'inc/config.class.php';
PluginMonpluginConfig::uninstall();
```

Dans `plugin_init_monplugin` (vous pouvez retirer les addtabon non pertinents dans votre cas d'utilisation):
```
Plugin::registerClass('PluginMonpluginConfig', array('addtabon' => array(
		'User',
		'Preference',
		'Config',
		'Entity',
		'Profile' 
	)));
if((new Plugin())->isActivated('monplugin')) {
	$PLUGIN_HOOKS['config_page']['monplugin'] = "../../front/config.form.php?forcetab=" . urlencode('PluginMonpluginConfig$0');
}	

```

Et enfin dans `plugin_monplugin_check_prerequisites` :
```
//Vérifie la présence de ConfigManager
$configManager = new Plugin();
if(! ($configManager->getFromDBbyDir("configmanager") && $configManager->fields['state'] == Plugin::ACTIVATED)) {
	echo __("Plugin requires ConfigManager x.x", 'monplugin');
	return false;
}
```

#Forces et faiblesses
## Forces
Simplifie grandement la gestion des configurations d'un plugin. Dans les cas de configuration très simples on y gagne pas tant que ça, mais c'est particulièrement intéressant si on veut permettre des configurations surchargées par les préférences de l'utilisateur, ou par des configurations intermédiaires, ce qui devient trop vite une usine à gaz si on n'y fait pas attention.

## Faiblesses
Clairement, tous les cas ne sont pas couverts (modes de saisie un peu particulier, possibilité d'avoir des séries d'options par type, comme des règles, gestion intelligente des changements de modèle...)
En particulier, pour l'instant, rien n'est prévu pour gérer les upgrades d'une version à l'autre, il faut désinstaller/réinstaller le plugin pour écraser la table des configurations, ce qui fait évidemment perdre des données.

#Idées pour des évolutions futures
Rien de planifié à court terme:
* Ajouter des modes de saisie au fil des besoins (text area, couleur...)
* Ajouter la gestion de séries de réglages sans limitation de nombre pour chaque type de configuration (sur le modèle des règles) (et du coup réfléchir à ce que signifirait héritage dans ce cas : concaténation, remplacement de listes??)
* Réfléchir à un moyen de gérer les changements de modèle de façon intelligente (par exemple en ajoutant/supprimant des colonnes dans tout casser)