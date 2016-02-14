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
	
	$config->check($_POST['id'],'w');
	$config->update($_POST);
}



Html::back();