# Configmanager
Ce plugin n'a en fait aucun intérêt en lui-même. Son objectif est de fournir une base partagée pour gérer la configuration d'autres plugins.
Le principe est le suivant : plutôt que s'embêter à chaque fois qu'on a des configurations à gérer à faire des objets héritant de CommonDBTM et à réimplémenter une interface pour 3 dropdowns, il suffit d'hériter de la classe fournie pas ce plugin, de sur-charger deux-trois fonctions très simples pour décrire la configuration qu'on veut (un peu à la manière d'un modèle de données), et le tour est joué.
De plus, ce plugin offre la possibilité de définir plusieurs niveau de configurations pouvant se surcharger les uns les autres (le cas d'usage typique, c'est d'avoir une configuration générale, qu'un utilisateur peut surcharger dans ses préférences).

# Mode d'emploi de PluginConfigmanagerConfig

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
        'type' => 'dropdown',
        'types' => array(self::TYPE_USER, self::TYPE_GLOBAL),
        'maxlength' => '25',
        'text' => __('Description du paramètre 1', 'monplugin'),
//        'tooltip' => __('Is it a bird ?', 'monplugin'),
        'values' => array(
            '1' => Dropdown::getYesNo('1'),
            '0' => Dropdown::getYesNo('0'),
            'value1' => __('texte de la valeur 1', 'monplugin'),
            'value2' => __('texte de la valeur 2', 'monplugin')
        ),
        'default' => '0',
//        'multiple' => false,
//        'size' => 5,
//        'mark_unmark_all' => false
    ),
    'param2' => array(
        'type' => 'text input',
        'types' => array(self::TYPE_PROFILE, self::TYPE_GLOBAL),
        'maxlength' => '250',
        'text' => __('Description de la valeur 2', 'monplugin'),
//        'tooltip' => 'yolo',
        'default' => 'Is it a plane?',
//        'size' => 75,
    ),
    'param2' => array(
        'type' => 'text input',
        'types' => array(self::TYPE_USER, self::TYPE_GLOBAL),
        'maxlength' => '250',
        'text' => __('Description de la valeur 2', 'monplugin'),
//        'tooltip' => 'With great power comes great responsibilities',
        'default' => 'No it\'s Superman !!',
//        'cols' => 50,
//        'rows' => 10,
//        'resize' => 'both'
    ),
    'whatever' => array(
        'type' => 'readonly text',
        'text' => '<td colspan="2">This is exactly why I don\'t want any super-power</td>',
        'types' => array(self::TYPE_PROFILE, self::TYPE_GLOBAL),
    ), ...
);
```

Explication de texte :
* les clés du tableau (param1, param2) sont les valeurs des paramètres de configuration. Elle n'apparaitront jamais aux yeux de l'utilisateur, mais ce seront les clés du tableau de configuration que vous réccuppérerez quand vous voudrez lire la configcourante. Deux types de valeurs interdites :
    * Tout ce qui fait une injection sql (car ce sera utilisé pour le nom des colonne de la BDD) (non, il n'y a pas de vérification, je pars du principe que si vous codez un plugin, vous savez faire attention à ça)
    * `config__type` et `config__type_id`, qui sont utilisés par le mécanisme interne du plugin.
    * tout ce qui commence par _, qui est ignoré pour l'insertion en base de donnée par les mécanismes natifs de GLPI, utilisés par ce plugin.
*`type` décrit le type d'entrée de cette clé
    * 'dropdown' pour un dropdown
    * 'text input' pour un champs de saisie libre
    * 'text area' pour un champs de saisie libre sous forme d'un textarea
    * 'readonly text' pour un texte qui sera inséré dans le formulaire, mais ne correspond pas à une option (typiquement un intertitre, un texte explicatif...)
    * A noter : il n'est pas prévu d'utiliser des checkbox, puisque pour gérer l'héritage, il faut forcément au moins trois choix : oui/non/hériter
* `types` représente les situations dans lesquelles ce paramètre de configuration a du sens. Cela signifie que le réglage de ces options sera proposés aux endroits de l'interface utilisateur pertinents (dans un onglet dédié), et que lorsque l'on va charger la configuration courante, ce paramètre sera pris en compte pour ce type de contexte. L'ordre du tableau est important : le premier élément va surcharger le deuxième, qui va surcharger le troisième... S'ils sont définis bien sûr, sinon la configuration sera héritée du niveau de dessus. On distingue 5 types de configuration :
    * TYPE_GLOBAL => configuration générale (se règle au niveau de la configuration générale de GLPI, ou en cliquant sur le nom du plugin dans la page de gestion des plugins). Il ne peut y en avoir qu'une seule pour tout le plugin.
    * TYPE_PROFILE => configuration par profil. Lorsque la configuration est lue, la ligne du profil courant de l'utilisateur sera prise en compte.
    * TYPE_USERENTITY => configuration par entité. Lorsque la configuration est lue, la ligne de l'entité courant dans laquelle est l'utilisateur sera prise en compte (qui peut être différente de l'entité des objets manipulés par l'utilisateur)
    * TYPE_ITEMENTITY => configuration par entité. Lorsque la configuration est lue, la ligne de l'entité demandée lors de l'appel de la fonction de lecture sera prise en compte (usage prévu: on a une configuration qui doit s'appliquer selon l'entité d'appartenance d'un objet, on va appeller la fonction de lecture de la configuration en lui passant l'entité de l'objet en question)
    * TYPE_USER => configuration par utilisateur (réglable soit dans la page de gestion des utilisateurs, soit dans le menu préférences de l'utilisateur)
*`maxlength` est la taille maximale de l'enregistrement en base de donnée, sachant que tout sera enregistré dans des varchar (du fait de la façon dont est géré l'héritage). Selon les types utilisés, cette valeur pourra être utilisée pour garantir que le formulaire ne permettra pas de rentrer des configuration 'trop grandes'. Si la valeur fournie est inférieure à 25, l'enregistrement en base de donnée permettra tout de même une entrée de 25 caractères, encore une fois du fait du fonctionnement de l'héritage.

* `default` est la valeur par défaut du paramètre. Elle doit impérativement avoir un sens pour votre plugin, dans la mesure où elle sera potentiellement renvoyée comme valeur courante de la configuration. Lors de la création d'une entrée d'un type donné (par exemple TYPE_USER, qui correspond aux préférences d'un utilisateur), chaque valeur de la base de donnée peut être initialisée de deux manières : soit TYPE_USER est le type 'racine' de ce paramètre (cad qu'il n'hérite d'aucun autre type de configuration) et c'est la valeur par défaut qui est insérée, soit ce type n'est pas le type de référence, alors c'est un marqueur disant que la configuration doit être héritée du niveau de dessus qui est inséré. Inversement, lors de la lecture de la configuration courante, on va remonter la chaîne d'héritage jusqu'à retrouver une valeur qui ne soit pas le marqueur d'héritage. Si on en trouve pas (ce qui signifie que la configuration 'racine' n'est pas définie en base), on considère que c'est la valeur par défaut qui s'applique.
* `tooltip` est une chaîne de caractères qui sera affichée en info-bulle quand on placera la souris sur la ligne correspondant à une entrée de la configuration. Ce paramètre peut être ignoré (auquel cas pas d'info-bulle, comme on pouvait s'en douter!)

### `type==='readonly text'`
Permet d'afficher du texte libre, qui ne sera pas considéré comme un champ de la configuration. La chaîne de caractère de la valeur `text` sera insérée dans une ligne du tableau. On peut y mettre du HTML (et même du javascript, même si c'est pas vraiment conseillé).
Une fonction `self::makeHeaderLine($text)` est proposée pour fabriquer le code HTML qui affichera `$text` comme titre de tableau, utile pour faire le titre, ou des inter-titres sans avoir besoin de trop comprendre comment le formuaire s'affiche. Sinon, il faut bien être consient que le html fourni sera inséré dans un `<tr>`, donc il faut s'occuper soit-même des `<td>` (sachant que le tableau a deux colonnes).
A noter : les champ `maxlength` et `default` peuvent être ignorés, et l'ordre dans lequel les arguements de `types` sont données importe peu, puisqu'il n'y a pas de notion d'héritage, ils servent selement à indiquer s'il faut afficher ou non le texte readonly.

### `type==='dropdown'`
Permet d'afficher un dropdown. En plus des paramètres communs, il prend les paramètres suviants (defaut:xxx signifie que le paramètre est optionnel, et que sa valeur par défaut est xxx)
* `values` représente les valeurs possibles du dropdown sous forme d'un tableau ('value'=>'texte à afficher comme option du dropdown')
* `multiple` (defaut:false) => indique s'il doit être possible de sélectionner plusieurs valeurs à la fois (attention à la longueur prévue en base de donnée)
* `size` (defaut:1) => hauteur de dropdown de saisie
* `mark_unmark_all` (defaut:false) => permet d'afficher les options tout sélectionner/tout désélectionner (valable seulement si multiple est vrai)

A noter : si `multiple===true`, les données seront stockées en base sous la forme d'un tableau sous format json, il faut donc prévoir comme taille de la colonne (`maxlength`) au minimum la taille cumulée de toutes les valeurs, plus les guillemets, virgules et crochets associés. De plus, dans ce cas `default` doit être un tableau en format json valide pour votre plugin (par exemple `'default'=>'["toto","tutu"]'`...

### `type==='text input'`
Permet d'afficher un texte en saisie libre dans un input (une ligne). En plus des paramètres communs, il prend les paramètres suviants :
* `size` (defaut:50) => largeur en caractères du champ de saisie affiché

A noter : le nombre de caractères saisissables dans le champ est limité par la taille prévue dans la base de données (paramètre `maxlength`). De cette façon, on ne risque pas de se retrouver avec un texte saisi tronqué au moment de l'enregistrement.

### `type==='area input'`
Permet d'afficher un texte en saisie libre dans un input multi-ligne. En plus des paramètres communs, il prend les paramètres suviants :
* `cols` (defaut:50) => largeur en caractères du champ de saisie affiché
* `rows` (defaut:5) => hauteur en caractères du champ de saisie affiché
* `resize` (defaut:both) => possibilité de redimensionner le champ de saisie (valeurs possibles : none, vertical, horizontal, both)

A noter : le nombre de caractères saisissables dans le champ est limité par la taille prévue dans la base de données (paramètre `maxlength`). De cette façon, on ne risque pas de se retrouver avec un texte saisi tronqué au moment de l'enregistrement.


## 3- Personnaliser l'affichage
Le plugin `ConfigManager` produit des textes pour le nom des onglets, en se basant sur divers éléments. Ces méthodes très génériques ne correspondront pas nécessairement à quelque chose d'intelligent dans tous les contextes, vous pouvez donc les personnaliser:
* `getTabNameForConfigType` => définit le nom de l'onglet de configuration en fonction du type de configuration (par défaut, renvoie le nom du plugin)
* `getInheritFromMessage` => définit le message affiché dans le formulaire pour dire que l'option choisie est d'hériter du niveau de dessus. Sera utilisé, par exemple, dans les dropdowns (valeur par défaut, de la forme `'Hériter de la configuration xxx'`, ou xxx dépend du type)
* `getInheritedFromMessage` => définit le message affiché dans le formulaire pour dire que l'option choisie est d'hériter du niveau de dessus. Sera utilisé, par exemple, dans les dropdowns (valeur par défaut, de la forme `'Hériter de la configuration xxx'`, ou xxx dépend du type)

Ces fonctions prennent en paramètre le type de configuration. Il est donc recommandé de faire un switch/case (copier-coller de la fonction surchargée si vous êtes fainéants). Notez que la fonction n'est appellée que pour les types réellement utilisés, donc vous n'êtes pas obligés de couvrir tous les cas si votre configuration n'utilise pas tous les types.

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
    $PLUGIN_HOOKS['config_page']['monplugin'] = "../../front/config.form.php?forcetab=" . urlencode('PluginMonpluginConfig$1');
}    
```
Note : cette façon de brancher la configuration n'est pas obligatoire. Vous remarquerez que l'onglet de la configuration générale et numéroté 1. En fait, chaque type d'onglet a un numéro fixe, afin de pouvoir être référencé de façon sûre dans un forcetab si nécessaire :
* self::TYPE_GLOBAL => 1
* self::TYPE_USERENTITY => 2
* self::TYPE_ITEMENTITY => 3
* self::TYPE_PROFILE => 4
* self::TYPE_USER => 5

Et enfin dans `plugin_monplugin_check_prerequisites` :
```
//Vérifie la présence de ConfigManager
$configManager = new Plugin();
if(! ($configManager->getFromDBbyDir("configmanager") && $configManager->fields['state'] == Plugin::ACTIVATED)) {
    echo __("Plugin requires ConfigManager x.x", 'monplugin');
    return false;
}
```

## 5- Utiliser la configuration
Il suffit d'utiliser la fonction statique `getConfigValues`, qui renvoie un tableau décrivant la configuration courante.
Elle prend en paramètre un tableau décrivant les id des différents types de configuration à utiliser. Si celui-ci est omis, le plugin devine 'au mieux' ceux à utiliser :
* self::TYPE_GLOBAL => 0
* self::TYPE_USERENTITY => id de l'entité active
* self::TYPE_ITEMENTITY => id de l'entité active
* self::TYPE_PROFILE => id du profil actif
* self::TYPE_USER => id de l'utilisateur loggé

Autant certaines ne présentent pas vraiment d'intérêt à être surchargée, autant dans certains cas c'est indispensable : si par exemple j'ai une configuration qui indique ce que je peux faire sur un ticket selon l'entité dans lequel il se trouve, il faut passer en argument l'entité du ticket, qui peut être différente de l'entité active si on est dans une vue réccursive ou 'voir tous'.

# Mode d'emploi de PluginConfigmanagerRule
Dans les grandes lignes, c'est le même principe que PluginConfigmanagerConfig (PCC). Seulement, comme on traite de règles, il y a quelques petites différences :
* plusieurs règles peuvent s'appliquer en même temps. Il n'y a donc plus de notion de surcharge, mais les règles se cumulent (dans un ordre donné). Libre au plugin qui les traite de décider ce qu'il en fait (on traite tout, on s'arrête à la première respectée...)
* le paramètre `types` n'est pas propre à une valeur de configuration précise puisque les règles ne vont pas être démembrées. Il est donc définit une fois dans l'objet, et pas au niveau de chaque entrée.
* les entrées sont présentées en colonnes, plus en lignes.

## 1- Créer l'objet de configuration
Il suffit de faire un objet héritant de `PluginConfigmanagerRule`. Le choix du nom est libre, mais pour l'exemple, on l'appellera `PluginMonpluginRule`. Contrairement à PCC, qui présente peu d'intérêt à être intancié plusieurs fois, vous voudrez peut-être faire plusieurs variantes de PluginConfigmanagerRule, c'est possible.
```
class PluginMonpluginRule extends PluginConfigmanagerRule { ...
```

## 2- Définir le modèle
Pour cela, il faut définir l'ordre d'héritage des règles (les types sont les mêmes que pour PCC) :
`protected static $inherit_order = array(self::TYPE_USER, self::TYPE_GLOBAL);`

Ensuite, il faut surcharger la fonction `makeConfigParams`. La syntaxe est exactement la même que pour PCC, sauf que `types` peut cette fois être ignoré, puisque déjà traité :
```
array(
    'param1' => array(
        'type' => 'dropdown',
        'maxlength' => '25',
        'text' => __('Description du paramètre 1', 'monplugin'),
//        'tooltip' => __('Is it a bird ?', 'monplugin'),
        'values' => array(
            '1' => Dropdown::getYesNo('1'),
            '0' => Dropdown::getYesNo('0'),
            'value1' => __('texte de la valeur 1', 'monplugin'),
            'value2' => __('texte de la valeur 2', 'monplugin')
        ),
        'default' => '0',
//        'multiple' => false,
//         'size' => 5,
//         'mark_unmark_all' => false
    ),
    ...);
```

Explication de texte (seulement ce qui diffère de PCC) :
* `default` est toujours la valeur par défaut du paramètre. Par contre, comme l'héritage se fait sur le cumul de règles, et non sur une valeur donnée, elle est utilisée un peu différement : à chaque fois que dans l'interface, vous créez une nouvelle règle, pour chacun de ses paramètres, elle prend la valeur par défaut.
* `tooltip` ne sera plus affiché sur une ligne de configuration, mais sur la colonne correspondant au critère de la règle.
* `type==='readonly text'` Au lieu d'afficher le texte dans une ligne dédiée, il sera affiché dans la ligne de titre indiquant le nom des colonnes, et dans chaque règle. Dans les deux cas, on pourra utiliser du HTML, mais le code sera inséré dans une cellule (`<td>readonly text</td>`), ce qui en limite l'intérêt.

Il existe trois valeurs spécifiques de clés ayant une fonction particulière :
* `_header` => code HTML qui sera inséré avant la ligne contenant les `test`. Exemple : '<tr><th class="headerRow" colspan="'.(count(self::getConfigParams())+1).'">Ceci est un titre</th></tr>' insère un titre pour le tableau qui contient les règles.
* `_pre_save` => même chose, mais sous les règles, et juste au dessus du bouton 'sauvegarder'
* `_footer` => même chose après le boutton 'sauvegarder'
Ces clés sont donc soumises aux paramètres obligatoire habituels, et doivent impérativement être de type `'readonly text'`.

Enfin, on dispose aussi de la fonction `self::makeHeaderLine($text)`, qui a le même effet que pour PCC

## 3&4- Personnalisation de l'affichage et hooks
Pas de différence avec PCC

## 5- Utiliser la configuration
La fonction s'appelle `getRulesValues`, mais sinon c'est exactement le même principe. La valeur renvoyée est un tableau de règles, chaque règle étant elle-même un tableau (un peu à la manière de CommonDBTM::find)

# Mode d'emploi de PluginConfigmanagerTabmerger
L'objectif de cet objet des de rassembler dans le même onglet ce qui aurait nativement été sur plusieurs onglets, un peu à la façon de l'onglet tous. L'intérêt principal est de rassembler toute la config de votre plugin sur un unique onglet, et ce même si vous avec un jeu de configuration et 3 jeux de règles.

Son utilisation est triviale : on crée un objet qui hérite de PluginConfigmanagerTabmerger, on surcharge getTabsConfig pour renvoyer un tableau qui indique dans l'ordre les onglets à centraliser. Exemple pratique : 
```
<?php

class PluginSmartredirectTabmerger extends PluginConfigmanagerTabmerger {
    protected static function getTabsConfig() {
        return array(
            // '__.*' => 'html code',
            // CommonGLPI => tabnum|'all',
            'PluginMonpluginConfig' => 'all',
            'PluginMonpluginRule' => 'all'
        );
    }
}
```
Notes :
* si l'usage principale pour lequel il est pensé est de centraliser des onglets de Configmanager, ça marche parfaitement pour n'importe quel objet qui hérite de CommonGLPI.
* rapellez-vous, j'ai indiqué plus haut que pour chaque type de configuration, le numéro de l'onglet étati fixe. C'était pas un hasard, puisqu'ici, c'est plutôt pratique d'avoir un numéro fixe. Si vous souhaitez fusionner les onglets pour plusieurs types, pas de soucis, vous pouvez référencer plusieurs onglets d'un même objet, ils ne seront affichés que quand c'est pertinent (Tabmerger vérifie avant de réclammer l'affichage de l'onglet que celui-ci aurait été considéré comme affichable s'il avait été géré directement par son objet)
* toute entrée qui commence en `__` sera insérée directement comme code html. Il est recommandé de le mettre dans un `<table class="tab_cadre_fixe">` pour la cohérence visuelle, mais ça n'a rien d'obligatoire.

Ensuite, on remplace les hooks des onglets rassemblés par celui de l'onglet fusionné. Il faut que les classez déclarées en addtabon soient l'union de toutes les classes qui auraient été appelées par les différents onglets fusionnés. Il faut par contre bien laisser les registerClass, sous peine d'avoir des erreurs PHP.
```
Plugin::registerClass('PluginMonpluginConfig');
Plugin::registerClass('PluginMonpluginRule');
    
Plugin::registerClass('PluginMonpluginTabmerger', array('addtabon' => array(
    'User',
    'Preference',
    'Config'
)));
```

#Idées pour des évolutions futures
Rien de planifié à court terme:
* Ajouter des modes de saisie au fil des besoins (couleur...)
* Réfléchir à un moyen de gérer les changements de modèle de façon intelligente (par exemple en ajoutant/supprimant des colonnes dans tout casser)
