/*global define: true, ko: true */
define(['Boiler', '../group/component', './viewmodel', 'text!./view.html'], function(Boiler, GroupComponent, ViewModel, template) {
    "use strict";
	var Component = function(moduleContext) {

		var vm, panel = null, context = new Boiler.Context(moduleContext);

        context.ds = GroupComponent.ds;

		this.activate = function(parent, params) {
			if (!panel) {
				panel = new Boiler.ViewTemplate(parent, template, null);
				vm = new ViewModel(context);
				ko.applyBindings(vm, panel.getDomElement());
			}

			panel.show();
			
		};

		this.deactivate = function() {
			if(panel) {
				panel.hide();
			}
			
		};

	};

	return Component;

}); 