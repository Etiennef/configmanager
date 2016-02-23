
<?php
class PluginConfigmanagerTabmerger extends CommonGLPI {
	protected static function getTabsConfig() {
		return array(
			// '__.*' => 'html code',
			// CommonGLPI => tabnum|'all',
		);
	}
	
	protected static function getMergedTabName() {
		$matches = array();
		if(preg_match('/Plugin([[:upper:]][[:lower:]]+)[[:upper:]][[:lower:]]+/', get_called_class(), $matches)) {
			return $matches[1];
		} else return get_called_class();
	}
	
	function getTabNameForItem(CommonGLPI $item, $withtemplate = 0) {
		foreach(static::getTabsConfig() as $objectName => $tabnum) {
			if(preg_match('/__.*/', $objectName))
				continue;
			if(!empty((new $objectName())->getTabNameForItem($item, $withtemplate)))
				return static::getMergedTabName();
		}
		return '';
	}
	
	static function displayTabContentForItem(CommonGLPI $item, $tabnum = 1, $withtemplate = 0) {
		$res = true;
		
		foreach(static::getTabsConfig() as $objectName => $tabnum) {
			if(preg_match('/__.*/', $objectName)) {
				echo $tabnum;
				continue;
			}
			
			$tabs = (new $objectName())->getTabNameForItem($item, $withtemplate);
			
			if($tabnum === 'all') {
				foreach($tabs as $tabnum2 => $tabname2) {
					$res &= call_user_func("$objectName::displayTabContentForItem", $item, $tabnum2, $withtemplate);
				}
			} else if(isset($tabs[$tabnum])){
				// le isset permet de vérifier que l'onglet aurait été affiché dans ce contexte par l'objet de base
				$res &= call_user_func("$objectName::displayTabContentForItem", $item, $tabnum, $withtemplate);
			}
		}
		
		return $res;
	}
}
