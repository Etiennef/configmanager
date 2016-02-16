<?php
include ("../../../inc/includes.php");


if(isset($_POST['update']) && isset($_POST['config__object_name']) &&
		 class_exists($_POST['config__object_name'])) {
	$config = new $_POST['config__object_name']();
	if(!$config instanceof PluginConfigmanagerConfig) {
		// pas de traduction car ne peut se produire qu'en cas de 'hack' ou d'erreur de programmation
		Session::addMessageAfterRedirect("Unable to intanciate config class ".$_POST['config__object_name']);
		Html::back();
	}
	
	if($_POST['id'] == -1) {
		unset($_POST['id']);
		$config->check(-1,'w', $_POST);
		$config->add($_POST);
	} else {
		$config->check($_POST['id'],'w');
		$config->update($_POST);
	}
}



Html::back();