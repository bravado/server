/*global define: true, metaproject: true, ko: true */
define(['Boiler', './model', './viewmodel', 'text!./view.html', 'i18n!./nls/resources'], function(Boiler, Model, ViewModel, template, nls) {
    "use strict";

    var ds = new metaproject.DataSource('controller/user.php', { model: Model.User, key: 'uidnumber' });
	var Component = function(moduleContext) {

		var vm, panel = null, context = new Boiler.Context(moduleContext);
        context.ds = ds;
		this.activate = function(parent, params) {
			if (!panel) {
				panel = new Boiler.ViewTemplate(parent, template, nls);
				vm = new ViewModel(context);
				ko.applyBindings(vm, panel.getDomElement());
			}

            vm.load(params.id);
			panel.show();
			
		};

		this.deactivate = function() {
			if(panel) {
				panel.hide();
			}
			
		};

	};

    Component.ds = ds;
	return Component;

}); 