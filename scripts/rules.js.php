<script>
var <?php echo $rootid;?> = {};

Ext.onReady(function() {
	var tableNode = document.getElementById('<?php echo $table_id;?>');
	var formNode = document.getElementById('<?php echo $form_id;?>');
	var rules = {}; //id=> order, dom (ne contient pas les 0)

	<?php echo $rootid;?> = {
			moveup : moveup,
			movedown : movedown,
			add : add,
			addlast : addlast,
			remove : remove,
	};
	
	initialize();

	function initialize() {
		rows = tableNode.children;
		for (var i in rows) {
			var row = rows[i];
			if(row.nodeType) {
				if(!row.id.match(/<?php echo $rootid;?>_rule_(\d)/)) continue;
		    	id = row.id.match(/<?php echo $rootid;?>_rule_(\d)/)[1];

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
	function add(id) {
		var current = rules[id];

		var template = '<?php echo addslashes($empty_rule_html);?>';

		var newOrder = current.order<0?current.order:current.order+1;
		var newID = '<?php echo self::NEW_ID_TAG;?>'+addcnt++;

		// Adaptation du modèle de ligne à notre cas particulier
		template = template.replace(/<?php echo self::NEW_ID_TAG;?>/g, newID);
		template = template.replace(/<?php echo self::NEW_ORDER_TAG;?>/g, newOrder);
		
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

	}

	function addlast(id) {
		var newID = '<?php echo self::NEW_ID_TAG;?>'+addcnt++;
		var newOrder = null;
		
		Object.keys(rules).forEach(function(rule_id) {
			rule = rules[rule_id];
			if(newOrder === null || newOrder<rule.order) newOrder = rule.order;
		});
		if(newOrder === null) newOrder = 1;
		else newOrder++;
		
		// Adaptation du modèle de ligne à notre cas particulier
		var template = '<?php echo addslashes($empty_rule_html);?>';
		template = template.replace(/<?php echo self::NEW_ID_TAG;?>/g, newID);
		template = template.replace(/<?php echo self::NEW_ORDER_TAG;?>/g, newOrder);

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
	}

	function remove(id) {
		var current = rules[id];
		
		tableNode.removeChild(current.dom);

		// Ajout de l'input signalant la suppression d'une règle (seulement si règle existante côté serveur)
		if(!/<?php echo self::NEW_ID_TAG;?>\d*/.test(id)) {
			var template = '<?php echo addslashes($delete_rule_html);?>';
			template = template.replace(/<?php echo self::NEW_ID_TAG;?>/g, id);
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
	
});
</script>