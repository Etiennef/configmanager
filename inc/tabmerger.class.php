
<?php
class PluginConfigmanagerTabmerger extends CommonGLPI {
	protected static $tabs = array(
		// '__.*' => 'html code',
		// CommonGLPI => tabnum|'all',
		);
	
	protected static function getMergedTabName() {
		$matches = array();
		if(preg_match('/Plugin([[:upper:]][[:lower:]]+)[[:upper:]][[:lower:]]+/', get_called_class(), $matches)) {
			return $matches[1];
		} else return get_called_class();
	}
	
	function getTabNameForItem(CommonGLPI $item, $withtemplate = 0) {
		foreach(static::$tabs as $objectName => $tabnum) {
			if(preg_match('/__.*/', $objectName))
				continue;
			if(!empty((new $objectName())->getTabNameForItem($item, $withtemplate)))
				return static::getMergedTabName();
		}
		return '';
	}
	
	static function displayTabContentForItem(CommonGLPI $item, $tabnum = 1, $withtemplate = 0) {
		$res = true;
		
		foreach(static::$tabs as $objectName => $tabnum) {
			if(preg_match('/__.*/', $objectName)) {
				echo $tabnum;
				continue;
			}
			
			if($tabnum === 'all') {
				$tabs2 = call_user_func("$objectName::getTabNameForItem", $item, $withtemplate);
				foreach($tabs2 as $tabnum2 => $tabname2) {
					$res &= call_user_func("$objectName::displayTabContentForItem", $item, $tabnum2, $withtemplate);
				}
			} else {
				$res &= call_user_func("$objectName::displayTabContentForItem", $item, $tabnum, $withtemplate);
			}
		}
		
		return $res;
	}
}
